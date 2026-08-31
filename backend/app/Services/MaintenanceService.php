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
     * Tablas que el panel permite vaciar por completo.
     *
     * Solo entran datos operativos o de registro que el negocio puede
     * descartar. El catalogo y la configuracion (servicios, personal,
     * sucursales, ajustes, usuarios) nunca se vacian desde aqui: perderlos
     * dejaria el sistema inservible.
     *
     * @var array<string,string> tabla => que contiene, en palabras del duenio
     */
    private const EMPTYABLE_TABLES = [
        'audit_logs' => 'Historial de acciones del panel',
        'login_attempts' => 'Intentos de acceso',
        'banner_events' => 'Vistas y clics de la publicidad',
        'notification_queue' => 'Cola de avisos ya procesados',
        'contact_messages' => 'Mensajes del formulario de contacto',
        'maintenance_runs' => 'Historial de limpiezas',
        'rate_limits' => 'Contadores de intentos',
        'daily_stats' => 'Estadisticas diarias acumuladas',
        'waitlist' => 'Lista de espera',
        'campaign_recipients' => 'Destinatarios de campanas enviadas',
        'coupon_redemptions' => 'Canjes de cupones',
        'email_verifications' => 'Verificaciones de correo pendientes',
        'password_resets' => 'Enlaces de recuperacion de clave',
        'refresh_tokens' => 'Sesiones abiertas de la app movil',
    ];

    /** @return array<string,string> */
    public static function emptyableTables(): array
    {
        return self::EMPTYABLE_TABLES;
    }

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
        $referenced = self::referencedPaths();

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

    /**
     * Rutas de archivo que alguna fila sigue usando.
     *
     * @return array<string,true> ruta relativa => true
     */
    private static function referencedPaths(): array
    {
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

        $referenced = [];

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

        return $referenced;
    }

    /** Carpeta donde se guardan las copias de seguridad. */
    private static function backupDir(): string
    {
        return dirname(FileUploader::baseDir()) . '/backups';
    }

    /**
     * Inventario de tablas para el panel: cuantas filas tiene cada una,
     * cuanto ocupa y si el duenio puede vaciarla.
     *
     * @return list<array{table:string,rows:int,size_mb:float,free_mb:float,emptyable:bool,label:string}>
     */
    public static function tableInventory(): array
    {
        $usage = self::databaseUsage();
        $emptyable = self::EMPTYABLE_TABLES;
        $inventory = [];

        foreach ($usage as $row) {
            $table = $row['table'];

            // table_rows es una estimacion en InnoDB. Para las tablas que se
            // pueden vaciar se cuenta de verdad, porque el duenio decide sobre
            // ese numero y una estimacion podria enganiarle.
            $rows = $row['rows'];

            if (isset($emptyable[$table])) {
                try {
                    $rows = (int) (Database::instance()->selectOne(
                        'SELECT COUNT(*) AS c FROM `' . $table . '`'
                    )['c'] ?? 0);
                } catch (\Throwable) {
                    // Se queda con la estimacion.
                }
            }

            $inventory[] = [
                'table' => $table,
                'rows' => $rows,
                'size_mb' => round($row['data_mb'] + $row['index_mb'], 2),
                'free_mb' => $row['free_mb'],
                'emptyable' => isset($emptyable[$table]),
                'label' => $emptyable[$table] ?? '',
            ];
        }

        return $inventory;
    }

    /**
     * Vacia una tabla concreta. Solo funciona con las de la lista blanca.
     *
     * Se usa DELETE y no TRUNCATE porque TRUNCATE hace un commit implicito y
     * falla si hay claves foraneas apuntando a la tabla; DELETE respeta las
     * reglas del esquema y avisa si algo depende de esos datos.
     *
     * @return int filas eliminadas
     */
    public static function emptyTable(string $table, ?int $actorId = null): int
    {
        if (!isset(self::EMPTYABLE_TABLES[$table])) {
            throw new HttpException(422, 'Esa tabla no se puede vaciar desde el panel.');
        }

        $start = microtime(true);
        $deleted = Database::instance()->statement('DELETE FROM `' . $table . '`');

        self::recordRun('vaciar_tabla', $deleted, 0, 0, $start, ['tabla' => $table], $actorId);

        Audit::record('mantenimiento.tabla_vaciada', 'maintenance', null, null, [
            'tabla' => $table,
            'filas' => $deleted,
        ]);

        return $deleted;
    }

    /**
     * Compacta una sola tabla, para no bloquear toda la base cuando solo
     * una ha crecido.
     */
    public static function optimizeTable(string $table, ?int $actorId = null): string
    {
        self::assertIdentifier($table);

        if (Database::instance()->driver() !== 'mysql') {
            return 'La optimizacion solo esta disponible en MySQL/MariaDB.';
        }

        $known = array_column(self::databaseUsage(), 'table');

        if (!in_array($table, $known, true)) {
            throw new HttpException(404, 'Esa tabla no existe.');
        }

        $start = microtime(true);

        try {
            Database::instance()->statement('OPTIMIZE TABLE `' . $table . '`');
            $result = 'ok';
        } catch (\Throwable $e) {
            $result = 'error: ' . mb_substr($e->getMessage(), 0, 120);
        }

        self::recordRun('optimizar_tabla', 0, 0, 0, $start, [$table => $result], $actorId);

        return $result;
    }

    /**
     * Archivos subidos, agrupados por carpeta, para el gestor del panel.
     *
     * Marca cuales estan huerfanos (ninguna fila los referencia) para que el
     * duenio vea de un vistazo que puede borrar sin romper nada.
     *
     * @return array{folders:array<string,list<array{path:string,bytes:int,human:string,modified:string,orphan:bool}>>,total_files:int,total_bytes:int,orphans:int}
     */
    public static function fileInventory(?string $folder = null): array
    {
        $base = FileUploader::baseDir();
        $empty = ['folders' => [], 'total_files' => 0, 'total_bytes' => 0, 'orphans' => 0];

        if ($base === '' || !is_dir($base)) {
            return $empty;
        }

        $referenced = self::referencedPaths();
        $folders = [];
        $totalFiles = 0;
        $totalBytes = 0;
        $orphans = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', mb_substr($fileInfo->getPathname(), mb_strlen($base) + 1));
            $group = explode('/', $relative)[0];
            $isOrphan = !isset($referenced[$relative]) && !str_ends_with($relative, '.gitkeep');

            $totalFiles++;
            $totalBytes += $fileInfo->getSize();

            if ($isOrphan) {
                $orphans++;
            }

            if ($folder !== null && $group !== $folder) {
                continue;
            }

            $folders[$group][] = [
                'path' => $relative,
                'bytes' => $fileInfo->getSize(),
                'human' => self::formatBytes($fileInfo->getSize()),
                'modified' => gmdate('Y-m-d H:i', $fileInfo->getMTime()),
                'orphan' => $isOrphan,
            ];
        }

        foreach ($folders as $name => $files) {
            usort($files, static fn (array $a, array $b): int => $b['bytes'] <=> $a['bytes']);
            $folders[$name] = array_slice($files, 0, 300);
        }

        ksort($folders);

        return [
            'folders' => $folders,
            'total_files' => $totalFiles,
            'total_bytes' => $totalBytes,
            'orphans' => $orphans,
        ];
    }

    /**
     * Borra un archivo subido concreto y limpia la fila de "media" si existe.
     *
     * La ruta llega del navegador, asi que se comprueba que siga dentro de la
     * carpeta de subidas despues de resolverla: sin eso, un ".." permitiria
     * borrar cualquier archivo del servidor.
     */
    public static function deleteUploadedFile(string $relativePath, ?int $actorId = null): int
    {
        $base = FileUploader::baseDir();

        if ($base === '' || !is_dir($base)) {
            throw new HttpException(404, 'No hay carpeta de archivos.');
        }

        if (preg_match('#^[A-Za-z0-9._/-]{1,255}$#', $relativePath) !== 1 || str_contains($relativePath, '..')) {
            throw new HttpException(422, 'Ruta de archivo no valida.');
        }

        $full = realpath($base . '/' . $relativePath);
        $realBase = realpath($base);

        if ($full === false || $realBase === false || !str_starts_with($full, $realBase . '/') || !is_file($full)) {
            throw new HttpException(404, 'El archivo no existe.');
        }

        $bytes = (int) filesize($full);

        // Primero la fila y despues el archivo: si la base falla, el archivo
        // sigue ahi y se puede reintentar. Al reves quedaria una fila
        // apuntando a un archivo que ya no existe.
        QueryBuilder::table('media')->where('file_path', $relativePath)->delete();

        if (!@unlink($full)) {
            throw new HttpException(500, 'No se pudo borrar el archivo del disco.');
        }

        Audit::record('mantenimiento.archivo_borrado', 'maintenance', null, null, [
            'archivo' => $relativePath,
            'bytes' => $bytes,
        ]);

        return $bytes;
    }

    /**
     * Copia de seguridad en SQL, generada sin depender de mysqldump para que
     * funcione tambien en hostings compartidos donde no hay binarios.
     *
     * @return string ruta absoluta del archivo generado
     */
    public static function createBackup(?int $actorId = null, bool $includeData = true): string
    {
        $start = microtime(true);
        $dir = self::backupDir();

        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new HttpException(500, 'No se pudo crear la carpeta de copias.');
        }

        $name = 'copia_' . gmdate('Ymd_His') . '.sql';
        $file = $dir . '/' . $name;
        $handle = fopen($file, 'wb');

        if ($handle === false) {
            throw new HttpException(500, 'No se pudo escribir la copia.');
        }

        $db = Database::instance();
        $pdo = $db->pdo();
        $database = (string) Config::get('database.database', '');

        fwrite($handle, "-- Copia de seguridad de {$database}\n");
        fwrite($handle, '-- Generada el ' . gmdate('Y-m-d H:i:s') . " UTC\n");
        fwrite($handle, "-- Para restaurarla: importa este archivo en una base vacia.\n\n");
        fwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

        $tables = array_column($db->select(
            'SELECT table_name AS t FROM information_schema.TABLES
              WHERE table_schema = :db AND table_type = \'BASE TABLE\' ORDER BY table_name',
            ['db' => $database]
        ), 't');

        $rowCount = 0;

        foreach ($tables as $table) {
            $table = (string) $table;
            self::assertIdentifier($table);

            $create = $db->selectOne('SHOW CREATE TABLE `' . $table . '`');
            $sql = (string) ($create['Create Table'] ?? '');

            fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n{$sql};\n\n");

            if (!$includeData) {
                continue;
            }

            // Se lee por bloques para que una tabla grande no agote la memoria.
            $offset = 0;

            while (true) {
                $rows = $db->select('SELECT * FROM `' . $table . '` LIMIT 500 OFFSET ' . $offset);

                if ($rows === []) {
                    break;
                }

                foreach ($rows as $row) {
                    $columns = implode('`, `', array_keys($row));
                    $values = implode(', ', array_map(
                        static fn (mixed $v): string => $v === null ? 'NULL' : $pdo->quote((string) $v),
                        array_values($row)
                    ));

                    fwrite($handle, "INSERT INTO `{$table}` (`{$columns}`) VALUES ({$values});\n");
                    $rowCount++;
                }

                $offset += 500;
            }

            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($handle);
        @chmod($file, 0640);

        self::recordRun('copia_seguridad', $rowCount, 1, (int) filesize($file), $start, [
            'archivo' => $name,
            'tablas' => count($tables),
        ], $actorId);

        Audit::record('mantenimiento.copia_creada', 'maintenance', null, null, ['archivo' => $name]);

        return $file;
    }

    /**
     * Copias guardadas en el servidor, de la mas nueva a la mas vieja.
     *
     * @return list<array{name:string,bytes:int,human:string,created:string}>
     */
    public static function listBackups(): array
    {
        $dir = self::backupDir();

        if (!is_dir($dir)) {
            return [];
        }

        $backups = [];

        foreach (glob($dir . '/*.sql') ?: [] as $path) {
            $backups[] = [
                'name' => basename($path),
                'bytes' => (int) filesize($path),
                'human' => self::formatBytes((int) filesize($path)),
                'created' => gmdate('Y-m-d H:i', (int) filemtime($path)),
            ];
        }

        usort($backups, static fn (array $a, array $b): int => strcmp($b['name'], $a['name']));

        return $backups;
    }

    /** Ruta absoluta de una copia, validando el nombre. */
    public static function backupPath(string $name): string
    {
        if (preg_match('/^copia_[0-9]{8}_[0-9]{6}\.sql$/', $name) !== 1) {
            throw new HttpException(422, 'Nombre de copia no valido.');
        }

        $path = self::backupDir() . '/' . $name;

        if (!is_file($path)) {
            throw new HttpException(404, 'Esa copia ya no existe.');
        }

        return $path;
    }

    public static function deleteBackup(string $name): void
    {
        $path = self::backupPath($name);

        if (!@unlink($path)) {
            throw new HttpException(500, 'No se pudo borrar la copia.');
        }

        Audit::record('mantenimiento.copia_borrada', 'maintenance', null, null, ['archivo' => $name]);
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
