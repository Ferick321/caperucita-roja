<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\QueryBuilder;
use App\Security\Audit;
use App\Security\FileUploader;

/**
 * Mantenimiento y liberacion de espacio.
 *
 * Cubre tres necesidades:
 *  1. purgar de verdad lo que se marco como eliminado (no dejar basura);
 *  2. borrar los archivos huerfanos del disco cuando su fila ya no existe;
 *  3. compactar las tablas para recuperar el espacio en el motor.
 *
 * Cada politica de retencion es editable desde el panel: el administrador
 * decide cuantos dias se conserva cada tipo de dato.
 */
final class MaintenanceService
{
    /** Tablas sobre las que se permite actuar. Es una lista blanca cerrada. */
    private const ALLOWED_TABLES = [
        'appointments', 'appointment_status_history', 'audit_logs', 'banner_events',
        'campaign_recipients', 'campaigns', 'contact_messages', 'login_attempts',
        'media', 'notification_queue', 'password_resets', 'email_verifications',
        'payments', 'payment_proofs', 'rate_limits', 'refresh_tokens', 'reviews',
        'users', 'waitlist', 'gallery_items', 'coupon_redemptions', 'loyalty_transactions',
        'maintenance_runs', 'daily_stats',
    ];

    /**
     * Ejecuta todas las politicas activas.
     *
     * @return array{policies:list<array<string,mixed>>,total_rows:int,total_files:int,bytes_freed:int}
     */
    public static function runRetentionPolicies(?int $triggeredBy = null, bool $dryRun = false): array
    {
        $start = microtime(true);

        $policies = QueryBuilder::table('retention_policies')
            ->where('is_active', 1)
            ->orderBy('id')
            ->get();

        $results = [];
        $totalRows = 0;
        $totalFiles = 0;
        $bytesFreed = 0;

        foreach ($policies as $policy) {
            try {
                $outcome = self::applyPolicy($policy, $dryRun);
            } catch (\Throwable $e) {
                Logger::error('Fallo una politica de retencion', [
                    'policy' => (string) $policy['policy_key'],
                    'error' => $e->getMessage(),
                ]);

                $outcome = ['rows' => 0, 'files' => 0, 'bytes' => 0, 'error' => $e->getMessage()];
            }

            $results[] = [
                'policy' => (string) $policy['policy_key'],
                'label' => (string) $policy['label'],
                'table' => (string) $policy['target_table'],
                'retention_days' => (int) $policy['retention_days'],
                'rows_deleted' => $outcome['rows'],
                'files_deleted' => $outcome['files'],
                'error' => $outcome['error'] ?? null,
            ];

            $totalRows += $outcome['rows'];
            $totalFiles += $outcome['files'];
            $bytesFreed += $outcome['bytes'];

            if (!$dryRun) {
                QueryBuilder::table('retention_policies')->where('id', (int) $policy['id'])->update([
                    'last_run_at' => Clock::nowUtc(),
                    'last_deleted_count' => $outcome['rows'],
                    'updated_at' => Clock::nowUtc(),
                ]);
            }
        }

        if (!$dryRun) {
            self::recordRun('retencion', $totalRows, $totalFiles, $bytesFreed, $start, $results, $triggeredBy);
        }

        return [
            'policies' => $results,
            'total_rows' => $totalRows,
            'total_files' => $totalFiles,
            'bytes_freed' => $bytesFreed,
        ];
    }

    /**
     * @param array<string,mixed> $policy
     * @return array{rows:int,files:int,bytes:int}
     */
    private static function applyPolicy(array $policy, bool $dryRun): array
    {
        $table = (string) $policy['target_table'];
        $dateColumn = (string) $policy['date_column'];
        $days = max(1, (int) $policy['retention_days']);

        self::assertTableAllowed($table);
        self::assertIdentifier($dateColumn);

        $cutoff = gmdate('Y-m-d H:i:s', time() - $days * 86400);

        // La condicion extra la define el sistema en la semilla, nunca el usuario.
        $condition = trim((string) $policy['condition_sql']);
        $where = '`' . $dateColumn . '` < :cutoff';

        if ($condition !== '') {
            if (!self::isSafeCondition($condition)) {
                throw new \RuntimeException('Condicion de retencion no permitida.');
            }

            $where .= ' AND (' . $condition . ')';
        }

        $countSql = 'SELECT COUNT(*) FROM `' . $table . '` WHERE ' . $where;
        $rows = (int) Database::instance()->scalar($countSql, ['cutoff' => $cutoff]);

        if ($rows === 0) {
            return ['rows' => 0, 'files' => 0, 'bytes' => 0];
        }

        $filesDeleted = 0;
        $bytesFreed = 0;

        // Antes de borrar filas se eliminan sus archivos del disco.
        if ((bool) $policy['deletes_files']) {
            [$filesDeleted, $bytesFreed] = self::deleteFilesFor($table, $where, ['cutoff' => $cutoff], $dryRun);
        }

        if ($dryRun) {
            return ['rows' => $rows, 'files' => $filesDeleted, 'bytes' => $bytesFreed];
        }

        // Se borra por lotes para no bloquear la tabla en sitios con mucho historico.
        $deleted = 0;

        do {
            $affected = Database::instance()->statement(
                'DELETE FROM `' . $table . '` WHERE ' . $where . ' LIMIT 1000',
                ['cutoff' => $cutoff]
            );

            $deleted += $affected;
        } while ($affected === 1000);

        return ['rows' => $deleted, 'files' => $filesDeleted, 'bytes' => $bytesFreed];
    }

    /**
     * @param array<string,mixed> $bindings
     * @return array{0:int,1:int}
     */
    private static function deleteFilesFor(string $table, string $where, array $bindings, bool $dryRun): array
    {
        $fileColumns = match ($table) {
            'payment_proofs' => ['file_path'],
            'media' => ['file_path'],
            'gallery_items' => ['image_path', 'before_path'],
            default => [],
        };

        if ($fileColumns === []) {
            return [0, 0];
        }

        $select = implode(', ', array_map(static fn (string $c): string => '`' . $c . '`', $fileColumns));
        $rows = Database::instance()->select(
            'SELECT ' . $select . ' FROM `' . $table . '` WHERE ' . $where,
            $bindings
        );

        $count = 0;
        $bytes = 0;

        foreach ($rows as $row) {
            foreach ($fileColumns as $column) {
                $path = (string) ($row[$column] ?? '');

                if ($path === '') {
                    continue;
                }

                $absolute = FileUploader::absolutePath($path);

                if ($absolute === null) {
                    continue;
                }

                $size = (int) (filesize($absolute) ?: 0);

                if ($dryRun || @unlink($absolute)) {
                    $count++;
                    $bytes += $size;
                }
            }
        }

        return [$count, $bytes];
    }

    /**
     * Purga definitiva de las filas marcadas como eliminadas hace mas de N dias.
     *
     * @return array<string,int> tabla => filas eliminadas
     */
    public static function purgeSoftDeleted(int $olderThanDays = 30, ?int $triggeredBy = null): array
    {
        $start = microtime(true);
        $cutoff = gmdate('Y-m-d H:i:s', time() - max(0, $olderThanDays) * 86400);

        $tables = [
            'appointments', 'users', 'services', 'service_categories', 'staff',
            'branches', 'banners', 'gallery_items', 'reviews', 'campaigns',
            'contact_messages', 'media', 'bank_accounts', 'coupons', 'payments',
        ];

        $results = [];
        $total = 0;

        foreach ($tables as $table) {
            self::assertTableAllowedForSoftDelete($table);

            try {
                $deleted = Database::instance()->statement(
                    'DELETE FROM `' . $table . '` WHERE deleted_at IS NOT NULL AND deleted_at < :cutoff',
                    ['cutoff' => $cutoff]
                );
            } catch (\Throwable $e) {
                // Las claves foraneas pueden impedir el borrado; se registra y sigue.
                Logger::warning('No se pudo purgar una tabla', [
                    'table' => $table,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if ($deleted > 0) {
                $results[$table] = $deleted;
                $total += $deleted;
            }
        }

        self::recordRun('purga_borrados', $total, 0, 0, $start, $results, $triggeredBy);

        return $results;
    }

    /**
     * Archivos que ya no referencia ninguna fila.
     *
     * @return array{files:int,bytes:int,paths:list<string>}
     */
    public static function cleanOrphanFiles(bool $dryRun = false, ?int $triggeredBy = null): array
    {
        $start = microtime(true);
        $base = FileUploader::baseDir();

        if ($base === '' || !is_dir($base)) {
            return ['files' => 0, 'bytes' => 0, 'paths' => []];
        }

        // Todas las rutas referenciadas por la base de datos.
        $referenced = [];

        $sources = [
            ['media', 'file_path'],
            ['payment_proofs', 'file_path'],
            ['gallery_items', 'image_path'],
            ['gallery_items', 'before_path'],
            ['banners', 'image_path'],
            ['banners', 'mobile_image_path'],
            ['services', 'image_path'],
            ['service_categories', 'image_path'],
            ['staff', 'photo_path'],
            ['users', 'avatar_path'],
            ['content_blocks', 'image_path'],
            ['content_blocks', 'background_path'],
            ['campaigns', 'image_path'],
            ['branches', 'photo_path'],
            ['bank_accounts', 'logo_path'],
        ];

        foreach ($sources as [$table, $column]) {
            try {
                $rows = Database::instance()->select(
                    'SELECT DISTINCT `' . $column . '` AS p FROM `' . $table . '` WHERE `' . $column . '` <> \'\''
                );
            } catch (\Throwable) {
                continue;
            }

            foreach ($rows as $row) {
                $referenced[(string) $row['p']] = true;
            }
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        $orphans = [];
        $bytes = 0;
        $graceSeconds = 3600; // no toca lo subido en la ultima hora

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            $relative = ltrim(str_replace($base, '', $fileInfo->getPathname()), '/\\');
            $relative = str_replace('\\', '/', $relative);

            if (isset($referenced[$relative]) || str_ends_with($relative, '.gitkeep')) {
                continue;
            }

            if (time() - $fileInfo->getMTime() < $graceSeconds) {
                continue;
            }

            $orphans[] = $relative;
            $bytes += $fileInfo->getSize();

            if (!$dryRun) {
                @unlink($fileInfo->getPathname());
            }
        }

        if (!$dryRun) {
            self::recordRun('archivos_huerfanos', 0, count($orphans), $bytes, $start,
                ['orphans' => array_slice($orphans, 0, 100)], $triggeredBy);
        }

        return ['files' => count($orphans), 'bytes' => $bytes, 'paths' => array_slice($orphans, 0, 200)];
    }

    /**
     * Compacta las tablas para devolver el espacio libre al sistema de archivos.
     *
     * @return array<string,string>
     */
    public static function optimizeTables(?int $triggeredBy = null): array
    {
        $start = microtime(true);
        $driver = Database::instance()->driver();

        if ($driver !== 'mysql') {
            return ['aviso' => 'La optimizacion de tablas solo esta disponible en MySQL/MariaDB.'];
        }

        $results = [];

        foreach (self::ALLOWED_TABLES as $table) {
            try {
                Database::instance()->statement('OPTIMIZE TABLE `' . $table . '`');
                $results[$table] = 'ok';
            } catch (\Throwable $e) {
                $results[$table] = 'error: ' . mb_substr($e->getMessage(), 0, 120);
            }
        }

        self::recordRun('optimizar_tablas', 0, 0, 0, $start, $results, $triggeredBy);

        return $results;
    }

    /**
     * Uso de espacio por tabla, para el panel de mantenimiento.
     *
     * @return list<array{table:string,rows:int,data_mb:float,index_mb:float,free_mb:float}>
     */
    public static function databaseUsage(): array
    {
        if (Database::instance()->driver() !== 'mysql') {
            return [];
        }

        $rows = Database::instance()->select(
            'SELECT table_name AS t,
                    table_rows AS r,
                    ROUND(data_length / 1048576, 2) AS d,
                    ROUND(index_length / 1048576, 2) AS i,
                    ROUND(data_free / 1048576, 2) AS f
               FROM information_schema.TABLES
              WHERE table_schema = :db
              ORDER BY (data_length + index_length) DESC',
            ['db' => (string) Config::get('database.database', '')]
        );

        return array_map(static fn (array $row): array => [
            'table' => (string) $row['t'],
            'rows' => (int) $row['r'],
            'data_mb' => (float) $row['d'],
            'index_mb' => (float) $row['i'],
            'free_mb' => (float) $row['f'],
        ], $rows);
    }

    /** @return array{files:int,bytes:int} */
    public static function storageUsage(): array
    {
        $base = FileUploader::baseDir();

        if ($base === '' || !is_dir($base)) {
            return ['files' => 0, 'bytes' => 0];
        }

        $files = 0;
        $bytes = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo instanceof \SplFileInfo && $fileInfo->isFile()) {
                $files++;
                $bytes += $fileInfo->getSize();
            }
        }

        return ['files' => $files, 'bytes' => $bytes];
    }

    /**
     * Elimina por completo a un cliente y todo lo suyo (derecho al olvido).
     *
     * Las citas se conservan de forma anonima porque forman parte de la
     * contabilidad del negocio, pero se borra cualquier dato identificable.
     */
    public static function forgetClient(int $userId, ?int $actorId = null): void
    {
        $user = QueryBuilder::table('users')->where('id', $userId)->first();

        if ($user === null) {
            throw new HttpException(404, 'El cliente no existe.');
        }

        if ((string) $user['role'] !== 'client') {
            throw new HttpException(422, 'Esta operacion solo aplica a cuentas de cliente.');
        }

        Database::instance()->transaction(static function () use ($userId, $user, $actorId): void {
            // 1. Archivos personales.
            if ((string) $user['avatar_path'] !== '') {
                FileUploader::delete((string) $user['avatar_path']);
            }

            $proofs = Database::instance()->select(
                'SELECT pp.file_path
                   FROM payment_proofs pp
                   INNER JOIN payments p ON p.id = pp.payment_id
                  WHERE p.client_id = :id',
                ['id' => $userId]
            );

            foreach ($proofs as $proof) {
                FileUploader::delete((string) $proof['file_path']);
            }

            // 2. Datos personales dentro del historico de citas.
            QueryBuilder::table('appointments')->where('client_id', $userId)->update([
                'client_name' => 'Cliente eliminado',
                'client_phone' => '',
                'client_email' => '',
                'client_notes' => null,
                'client_id' => null,
            ]);

            QueryBuilder::table('reviews')->where('client_id', $userId)->update([
                'author_name' => 'Anonimo',
                'client_id' => null,
            ]);

            // 3. Filas que solo tienen sentido con el cliente.
            foreach (['refresh_tokens', 'push_devices', 'password_resets', 'email_verifications',
                      'notification_queue', 'campaign_recipients', 'banner_events',
                      'loyalty_transactions'] as $table) {
                QueryBuilder::table($table)->where('user_id', $userId)->delete();
            }

            // La lista de espera referencia al cliente con otro nombre de columna.
            QueryBuilder::table('waitlist')->where('client_id', $userId)->delete();

            // 4. La cuenta desaparece de la tabla de usuarios.
            QueryBuilder::table('users')->where('id', $userId)->delete();

            Audit::record(
                'cliente.eliminado_definitivo',
                'user',
                $userId,
                ['email' => (string) $user['email']],
                ['resultado' => 'datos personales eliminados'],
                null,
                $actorId
            );
        });
    }

    /** Purga la cola de notificaciones ya procesadas. */
    public static function purgeSentNotifications(int $olderThanDays = 30): int
    {
        return Database::instance()->statement(
            "DELETE FROM notification_queue
              WHERE status IN ('sent','cancelled','failed')
                AND created_at < :cutoff",
            ['cutoff' => gmdate('Y-m-d H:i:s', time() - $olderThanDays * 86400)]
        );
    }

    /** @param array<string,mixed>|list<mixed> $detail */
    private static function recordRun(
        string $task,
        int $rows,
        int $files,
        int $bytes,
        float $start,
        array $detail,
        ?int $triggeredBy
    ): void {
        try {
            QueryBuilder::table('maintenance_runs')->insert([
                'task' => $task,
                'rows_affected' => $rows,
                'files_removed' => $files,
                'bytes_freed' => $bytes,
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
                'detail' => mb_substr((string) json_encode($detail, JSON_UNESCAPED_UNICODE), 0, 8000),
                'triggered_by' => $triggeredBy,
                'created_at' => Clock::nowUtc(),
            ]);
        } catch (\Throwable $e) {
            Logger::error('No se pudo registrar la tarea de mantenimiento', ['error' => $e->getMessage()]);
        }
    }

    private static function assertTableAllowed(string $table): void
    {
        if (!in_array($table, self::ALLOWED_TABLES, true)) {
            throw new \RuntimeException("Tabla no permitida para mantenimiento: {$table}");
        }
    }

    private static function assertTableAllowedForSoftDelete(string $table): void
    {
        if (preg_match('/^[a-z_]{3,64}$/', $table) !== 1) {
            throw new \RuntimeException("Nombre de tabla no valido: {$table}");
        }
    }

    private static function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $identifier) !== 1) {
            throw new \RuntimeException("Identificador no valido: {$identifier}");
        }
    }

    /** Solo se admiten comparaciones simples definidas por el sistema. */
    private static function isSafeCondition(string $condition): bool
    {
        if (preg_match('/^[A-Za-z0-9_\s=<>!\'(),.%-]+$/', $condition) !== 1) {
            return false;
        }

        foreach ([';', '--', '/*', 'union', 'select', 'insert', 'update', 'drop', 'delete', 'into', 'load_file'] as $bad) {
            if (stripos($condition, $bad) !== false) {
                return false;
            }
        }

        return true;
    }

    public static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return round($value, $index === 0 ? 0 : 2) . ' ' . $units[$index];
    }
}
