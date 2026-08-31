<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Clock;
use App\Core\Config;
use App\Core\HttpException;
use App\Core\Model;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Security\Audit;
use App\Security\Auth;
use App\Services\MaintenanceService;
use App\Services\QueueWorker;
use App\Services\SettingsService;

/**
 * Mantenimiento del sistema.
 *
 * Es el modulo que responde a "poder eliminar de verdad y optimizar espacio":
 * muestra cuanto ocupa cada tabla, cuantos archivos hay, deja simular la
 * limpieza antes de ejecutarla y permite compactar las tablas.
 */
final class MaintenanceController extends AdminController
{
    public function index(Request $request): Response
    {
        $this->authorize('sistema.mantenimiento');

        $databaseUsage = MaintenanceService::databaseUsage();
        $storage = MaintenanceService::storageUsage();

        $totalData = 0.0;
        $totalFree = 0.0;

        foreach ($databaseUsage as $row) {
            $totalData += $row['data_mb'] + $row['index_mb'];
            $totalFree += $row['free_mb'];
        }

        return $this->view('admin.maintenance.index', [
            'policies' => QueryBuilder::table('retention_policies')->orderBy('label')->get(),
            'databaseUsage' => array_slice($databaseUsage, 0, 25),
            'totalDataMb' => round($totalData, 2),
            'totalFreeMb' => round($totalFree, 2),
            'storage' => $storage,
            'storageHuman' => MaintenanceService::formatBytes($storage['bytes']),
            'runs' => QueryBuilder::table('maintenance_runs')->orderBy('created_at', 'DESC')->limit(15)->get(),
            'queue' => [
                'pending' => QueryBuilder::table('notification_queue')->where('status', 'pending')->count(),
                'failed' => QueryBuilder::table('notification_queue')->where('status', 'failed')->count(),
                'sent' => QueryBuilder::table('notification_queue')->where('status', 'sent')->count(),
            ],
            'softDeleted' => $this->softDeletedCounts(),
            'environment' => [
                'php' => PHP_VERSION,
                'debug' => (bool) Config::get('app.debug', false),
                'https' => (bool) Config::get('app.force_https', false),
                'mail' => (string) Config::get('mail.transport', 'log'),
                'maintenance' => SettingsService::bool('system.maintenance_mode', false),
                'auto_purge' => SettingsService::bool('system.auto_purge_enabled', true),
            ],
        ]);
    }

    /** Simula la limpieza para ver que se borraria antes de hacerlo. */
    public function previewCleanup(Request $request): Response
    {
        $this->authorize('sistema.mantenimiento');

        $retention = MaintenanceService::runRetentionPolicies(Auth::id(), true);
        $orphans = MaintenanceService::cleanOrphanFiles(true);

        return $this->view('admin.maintenance.preview', [
            'retention' => $retention,
            'orphans' => $orphans,
            'orphanSize' => MaintenanceService::formatBytes($orphans['bytes']),
            'softDeleted' => $this->softDeletedCounts(),
        ]);
    }

    public function runCleanup(Request $request): Response
    {
        $this->authorize('sistema.mantenimiento');

        if ($request->string('confirm') !== 'LIMPIAR') {
            Session::error('Escribe LIMPIAR para confirmar. Esta accion elimina datos de forma definitiva.');

            return $this->redirect('/panel/mantenimiento');
        }

        $tasks = $request->array('tasks');
        $summary = [];

        if (in_array('retencion', $tasks, true)) {
            $result = MaintenanceService::runRetentionPolicies(Auth::id());
            $summary[] = sprintf('%d fila(s) purgadas por retencion', $result['total_rows']);
        }

        if (in_array('borrados', $tasks, true)) {
            $days = max(0, $request->int('soft_delete_days', 30));
            $deleted = MaintenanceService::purgeSoftDeleted($days, Auth::id());
            $summary[] = sprintf('%d fila(s) marcadas como eliminadas purgadas', array_sum($deleted));
        }

        if (in_array('archivos', $tasks, true)) {
            $orphans = MaintenanceService::cleanOrphanFiles(false, Auth::id());
            $summary[] = sprintf(
                '%d archivo(s) huerfanos eliminados (%s)',
                $orphans['files'],
                MaintenanceService::formatBytes($orphans['bytes'])
            );
        }

        if (in_array('optimizar', $tasks, true)) {
            MaintenanceService::optimizeTables(Auth::id());
            $summary[] = 'tablas compactadas';
        }

        if (in_array('registros', $tasks, true)) {
            $removed = \App\Core\Logger::purgeOlderThan(max(1, $request->int('log_days', 30)));
            $summary[] = sprintf('%d archivo(s) de registro eliminados', $removed);
        }

        Audit::record('sistema.limpieza', 'maintenance', null, null, ['tareas' => $tasks], $request);

        Session::success($summary === []
            ? 'No seleccionaste ninguna tarea.'
            : 'Limpieza completada: ' . implode(', ', $summary) . '.');

        return $this->redirect('/panel/mantenimiento');
    }

    public function savePolicy(Request $request): Response
    {
        $this->authorize('sistema.mantenimiento');

        $id = $request->paramInt('id');

        $data = $this->validate($request, [
            'retention_days' => 'required|int|between:1,7300',
        ], ['retention_days' => 'dias de retencion']);

        QueryBuilder::table('retention_policies')->where('id', $id)->update([
            'retention_days' => (int) $data['retention_days'],
            'is_active' => $request->bool('is_active') ? 1 : 0,
            'updated_at' => Clock::nowUtc(),
        ]);

        Audit::record('retencion.actualizada', 'retention_policy', $id, null, $data, $request);
        Session::success('Politica de retencion actualizada.');

        return $this->redirect('/panel/mantenimiento');
    }

    /** Procesa la cola de avisos a mano (util si el cron aun no esta puesto). */
    public function processQueue(Request $request): Response
    {
        $this->authorize('sistema.mantenimiento');

        $result = QueueWorker::process(100);

        Session::success(sprintf(
            'Cola procesada: %d enviados, %d con error, de %d intentados.',
            $result['sent'],
            $result['failed'],
            $result['processed']
        ));

        return $this->redirect('/panel/mantenimiento');
    }

    public function retryFailed(Request $request): Response
    {
        $this->authorize('sistema.mantenimiento');

        $affected = QueryBuilder::table('notification_queue')
            ->where('status', 'failed')
            ->update([
                'status' => 'pending',
                'attempts' => 0,
                'scheduled_at' => Clock::nowUtc(),
                'last_error' => '',
            ]);

        Session::success("{$affected} mensaje(s) vuelven a la cola.");

        return $this->redirect('/panel/mantenimiento');
    }

    // ---- Tablas de la base de datos -------------------------------------

    /** Listado de tablas con su tamanio y las acciones disponibles. */
    public function tables(Request $request): Response
    {
        $this->authorize('sistema.mantenimiento');

        $inventory = MaintenanceService::tableInventory();
        $totalSize = 0.0;
        $totalFree = 0.0;

        foreach ($inventory as $row) {
            $totalSize += $row['size_mb'];
            $totalFree += $row['free_mb'];
        }

        return $this->view('admin.maintenance.tables', [
            'inventory' => $inventory,
            'totalSizeMb' => round($totalSize, 2),
            'totalFreeMb' => round($totalFree, 2),
        ]);
    }

    /** Compacta una sola tabla. */
    public function optimizeOne(Request $request): Response
    {
        $this->authorize('sistema.mantenimiento');

        $table = $request->string('tabla');
        $result = MaintenanceService::optimizeTable($table, Auth::id());

        if ($result === 'ok') {
            Session::success("Tabla {$table} compactada. El espacio libre vuelve al servidor.");
        } else {
            Session::error("No se pudo compactar {$table}: {$result}");
        }

        return $this->redirect('/panel/mantenimiento/tablas');
    }

    /** Compacta todas las tablas de golpe. */
    public function optimizeAll(Request $request): Response
    {
        $this->authorize('sistema.mantenimiento');

        $results = MaintenanceService::optimizeTables(Auth::id());
        $failed = count(array_filter($results, static fn (string $r): bool => $r !== 'ok'));

        Session::success(sprintf(
            '%d tabla(s) compactadas%s.',
            count($results) - $failed,
            $failed > 0 ? ", {$failed} con error" : ''
        ));

        return $this->redirect('/panel/mantenimiento/tablas');
    }

    /**
     * Vacia una tabla por completo.
     *
     * Se exige escribir el nombre de la tabla: es irreversible y no queremos
     * que un clic distraido borre el historial entero.
     */
    public function emptyTable(Request $request): Response
    {
        $this->authorize('sistema.mantenimiento');

        $table = $request->string('tabla');

        if ($request->string('confirm') !== $table) {
            Session::error('Para vaciar una tabla escribe su nombre exacto en la casilla de confirmacion.');

            return $this->redirect('/panel/mantenimiento/tablas');
        }

        $deleted = MaintenanceService::emptyTable($table, Auth::id());
        MaintenanceService::optimizeTable($table, Auth::id());

        Session::success("Tabla {$table} vaciada: {$deleted} fila(s) eliminadas y espacio devuelto.");

        return $this->redirect('/panel/mantenimiento/tablas');
    }

    // ---- Archivos subidos ------------------------------------------------

    /** Gestor de archivos: ver que ocupa el disco y borrar lo que sobra. */
    public function files(Request $request): Response
    {
        $this->authorize('sistema.mantenimiento');

        $folder = $request->string('carpeta');
        $inventory = MaintenanceService::fileInventory($folder !== '' ? $folder : null);

        return $this->view('admin.maintenance.files', [
            'inventory' => $inventory,
            'totalHuman' => MaintenanceService::formatBytes($inventory['total_bytes']),
            'folder' => $folder,
        ]);
    }

    public function deleteFile(Request $request): Response
    {
        $this->authorize('sistema.mantenimiento');

        $path = $request->string('archivo');
        $bytes = MaintenanceService::deleteUploadedFile($path, Auth::id());

        Session::success('Archivo eliminado (' . MaintenanceService::formatBytes($bytes) . ' liberados).');

        return $this->redirect('/panel/mantenimiento/archivos');
    }

    // ---- Copias de seguridad ---------------------------------------------

    public function backups(Request $request): Response
    {
        $this->authorize('sistema.mantenimiento');

        return $this->view('admin.maintenance.backups', [
            'backups' => MaintenanceService::listBackups(),
        ]);
    }

    public function createBackup(Request $request): Response
    {
        $this->authorize('sistema.mantenimiento');

        $file = MaintenanceService::createBackup(Auth::id(), !$request->bool('solo_estructura'));

        Session::success(
            'Copia creada: ' . basename($file)
            . ' (' . MaintenanceService::formatBytes((int) filesize($file)) . '). Descargala y guardala fuera del servidor.'
        );

        return $this->redirect('/panel/mantenimiento/copias');
    }

    public function downloadBackup(Request $request): Response
    {
        $this->authorize('sistema.mantenimiento');

        $name = $request->string('archivo');
        $path = MaintenanceService::backupPath($name);

        return Response::file($path, 'application/sql', $name, false);
    }

    public function deleteBackup(Request $request): Response
    {
        $this->authorize('sistema.mantenimiento');

        MaintenanceService::deleteBackup($request->string('archivo'));
        Session::success('Copia eliminada del servidor.');

        return $this->redirect('/panel/mantenimiento/copias');
    }

    // ---- Politicas de retencion (CRUD completo) --------------------------

    public function policies(Request $request): Response
    {
        $this->authorize('sistema.mantenimiento');

        return $this->view('admin.maintenance.policies', [
            'policies' => QueryBuilder::table('retention_policies')->orderBy('label')->get(),
            'tables' => array_keys(MaintenanceService::emptyableTables()),
        ]);
    }

    public function policyForm(Request $request): Response
    {
        $this->authorize('sistema.mantenimiento');

        $id = $request->paramInt('id');
        $policy = $id > 0 ? QueryBuilder::table('retention_policies')->where('id', $id)->first() : null;

        if ($id > 0 && $policy === null) {
            throw new HttpException(404, 'Esa politica no existe.');
        }

        return $this->view('admin.maintenance.policy_form', [
            'policy' => $policy,
            'tables' => array_keys(MaintenanceService::emptyableTables()),
        ]);
    }

    /** Alta y edicion de una politica de limpieza. */
    public function savePolicyFull(Request $request): Response
    {
        $this->authorize('sistema.mantenimiento');

        $id = $request->paramInt('id');

        $data = $this->validate($request, [
            'label' => 'required|string|min:3|max:160|no_html',
            'description' => 'optional|string|max:500|no_html',
            'target_table' => 'required|string|max:64',
            'date_column' => 'required|string|max:64',
            'retention_days' => 'required|int|between:1,7300',
        ], [
            'label' => 'nombre', 'target_table' => 'tabla',
            'date_column' => 'columna de fecha', 'retention_days' => 'dias de retencion',
        ]);

        // La tabla y la columna acaban dentro de una consulta, asi que se
        // comprueba que existan de verdad en vez de confiar en el formulario.
        $this->assertColumnExists((string) $data['target_table'], (string) $data['date_column']);

        $existing = $id > 0 ? QueryBuilder::table('retention_policies')->where('id', $id)->first() : null;

        if ($id > 0 && $existing === null) {
            throw new HttpException(404, 'Esa politica no existe.');
        }

        $payload = [
            'label' => $data['label'],
            'description' => (string) ($data['description'] ?? ''),
            'target_table' => $data['target_table'],
            'date_column' => $data['date_column'],
            'retention_days' => (int) $data['retention_days'],
            'deletes_files' => $request->bool('deletes_files') ? 1 : 0,
            'is_active' => $request->bool('is_active') ? 1 : 0,
            'updated_at' => Clock::nowUtc(),
        ];

        if ($id > 0) {
            QueryBuilder::table('retention_policies')->where('id', $id)->update($payload);
            Audit::record('retencion.actualizada', 'retention_policy', $id, $existing, $payload, $request);
        } else {
            $payload['policy_key'] = $this->uniquePolicyKey((string) $data['target_table']);
            $payload['condition_sql'] = '';
            $payload['created_at'] = Clock::nowUtc();
            $id = QueryBuilder::table('retention_policies')->insert($payload);
            Audit::record('retencion.creada', 'retention_policy', $id, null, $payload, $request);
        }

        Session::success('Politica de limpieza guardada.');

        return $this->redirect('/panel/mantenimiento/retencion');
    }

    public function deletePolicy(Request $request): Response
    {
        $this->authorize('sistema.mantenimiento');

        $id = $request->paramInt('id');
        $policy = QueryBuilder::table('retention_policies')->where('id', $id)->first();

        if ($policy === null) {
            throw new HttpException(404, 'Esa politica no existe.');
        }

        QueryBuilder::table('retention_policies')->where('id', $id)->delete();
        Audit::record('retencion.eliminada', 'retention_policy', $id, $policy, null, $request);
        Session::success('Politica eliminada. Esos datos ya no se limpiaran solos.');

        return $this->redirect('/panel/mantenimiento/retencion');
    }

    /** Evita que el formulario apunte a una tabla o columna inventada. */
    private function assertColumnExists(string $table, string $column): void
    {
        if (preg_match('/^[a-z_][a-z0-9_]{0,63}$/', $table) !== 1
            || preg_match('/^[a-z_][a-z0-9_]{0,63}$/', $column) !== 1) {
            throw new HttpException(422, 'Nombre de tabla o columna no valido.');
        }

        $found = \App\Core\Database::instance()->selectOne(
            'SELECT 1 AS ok FROM information_schema.COLUMNS
              WHERE table_schema = :db AND table_name = :t AND column_name = :c',
            [
                'db' => (string) Config::get('database.database', ''),
                't' => $table,
                'c' => $column,
            ]
        );

        if ($found === null) {
            throw new HttpException(422, "La tabla {$table} no tiene una columna llamada {$column}.");
        }
    }

    private function uniquePolicyKey(string $table): string
    {
        $base = 'limpieza_' . $table;
        $candidate = $base;
        $suffix = 1;

        while (QueryBuilder::table('retention_policies')->where('policy_key', $candidate)->exists()) {
            $candidate = $base . '_' . (++$suffix);
        }

        return $candidate;
    }

    /** Bitacora de auditoria. */
    public function audit(Request $request): Response
    {
        $this->authorize('sistema.auditoria');

        $query = QueryBuilder::table('audit_logs')
            ->select(['audit_logs.*', 'users.first_name', 'users.last_name', 'users.email'])
            ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id');

        $search = $request->string('q');
        if ($search !== '') {
            $query->search($search, ['audit_logs.action', 'audit_logs.entity_type', 'users.email']);
        }

        $entity = $request->string('entidad');
        if ($entity !== '') {
            $query->where('audit_logs.entity_type', $entity);
        }

        $query->orderBy('audit_logs.created_at', 'DESC');

        return $this->view('admin.maintenance.audit', [
            'result' => Model::paginate($query, $this->page($request), 50),
            'filters' => ['q' => $search, 'entidad' => $entity],
            'entities' => array_map(
                static fn (array $r): string => (string) $r['entity_type'],
                \App\Core\Database::instance()->select(
                    "SELECT DISTINCT entity_type FROM audit_logs WHERE entity_type <> '' ORDER BY entity_type"
                )
            ),
        ]);
    }

    /** Historial de accesos correctos y fallidos. */
    public function loginAttempts(Request $request): Response
    {
        $this->authorize('sistema.auditoria');

        $query = QueryBuilder::table('login_attempts')->orderBy('created_at', 'DESC');

        if ($request->string('solo') === 'fallidos') {
            $query->where('successful', 0);
        }

        return $this->view('admin.maintenance.logins', [
            'result' => Model::paginate($query, $this->page($request), 50),
            'onlyFailed' => $request->string('solo') === 'fallidos',
        ]);
    }

    /** @return array<string,int> */
    private function softDeletedCounts(): array
    {
        $tables = [
            'appointments' => 'Citas',
            'users' => 'Clientes',
            'services' => 'Servicios',
            'staff' => 'Profesionales',
            'banners' => 'Anuncios',
            'gallery_items' => 'Galeria',
            'reviews' => 'Resenas',
            'campaigns' => 'Campanas',
            'contact_messages' => 'Mensajes',
            'bank_accounts' => 'Cuentas bancarias',
        ];

        $counts = [];

        foreach ($tables as $table => $label) {
            try {
                $total = QueryBuilder::table($table)->whereNotNull('deleted_at')->count();
            } catch (\Throwable) {
                continue;
            }

            if ($total > 0) {
                $counts[$label] = $total;
            }
        }

        return $counts;
    }
}
