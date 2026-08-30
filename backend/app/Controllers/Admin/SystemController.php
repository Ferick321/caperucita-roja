<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Clock;
use App\Core\Config;
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
final class SystemController extends AdminController
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

        return $this->view('admin.system.index', [
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

        return $this->view('admin.system.preview', [
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

            return $this->redirect('/panel/sistema');
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

        return $this->redirect('/panel/sistema');
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

        return $this->redirect('/panel/sistema');
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

        return $this->redirect('/panel/sistema');
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

        return $this->redirect('/panel/sistema');
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

        return $this->view('admin.system.audit', [
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

        return $this->view('admin.system.logins', [
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
