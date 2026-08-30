<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Clock;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Services\SettingsService;
use App\Services\StatsService;

/** Tablero principal del panel. */
final class DashboardController extends AdminController
{
    public function index(Request $request): Response
    {
        $this->authorize('panel.ver');

        $todayStart = Clock::localToUtc(Clock::today() . ' 00:00:00');
        $todayEnd = Clock::localToUtc(Clock::today() . ' 23:59:59');

        return $this->view('admin.dashboard', [
            'stats' => StatsService::dashboard(),
            'series' => StatsService::dailySeries(14),
            'statusBreakdown' => StatsService::statusBreakdown(30),
            'topServices' => StatsService::topServices(6, 90),
            'staffPerformance' => StatsService::staffPerformance(30),
            'todayAgenda' => QueryBuilder::table('appointments')
                ->select([
                    'appointments.*',
                    'staff.display_name AS staff_name',
                    'staff.color AS staff_color',
                ])
                ->leftJoin('staff', 'staff.id', '=', 'appointments.staff_id')
                ->whereNull('appointments.deleted_at')
                ->whereBetween('appointments.starts_at', $todayStart, $todayEnd)
                ->orderBy('appointments.starts_at')
                ->limit(30)
                ->get(),
            'pendingPayments' => QueryBuilder::table('payments')
                ->select(['payments.*', 'appointments.code AS appointment_code', 'appointments.client_name'])
                ->leftJoin('appointments', 'appointments.id', '=', 'payments.appointment_id')
                ->where('payments.status', 'awaiting_verification')
                ->orderBy('payments.created_at', 'DESC')
                ->limit(8)
                ->get(),
            'setupPending' => $this->setupChecklist(),
        ]);
    }

    /**
     * Lista de tareas de puesta en marcha: guia al negocio para que el sistema
     * quede completo sin necesidad de leer un manual.
     *
     * @return list<array{label:string,done:bool,url:string}>
     */
    private function setupChecklist(): array
    {
        $items = [
            [
                'label' => 'Poner el nombre, el telefono y la direccion del negocio',
                'done' => SettingsService::string('business.phone', '') !== ''
                    && SettingsService::string('business.address', '') !== '',
                'url' => '/panel/ajustes/business',
            ],
            [
                'label' => 'Subir el logotipo',
                'done' => SettingsService::string('business.logo', '') !== '',
                'url' => '/panel/ajustes/business',
            ],
            [
                'label' => 'Revisar el catalogo de servicios y sus precios',
                'done' => QueryBuilder::table('services')->whereNull('deleted_at')->count() > 0,
                'url' => '/panel/servicios',
            ],
            [
                'label' => 'Registrar a tu equipo y sus horarios',
                'done' => QueryBuilder::table('staff')->whereNull('deleted_at')->where('is_active', 1)->count() > 0,
                'url' => '/panel/personal',
            ],
            [
                'label' => 'Cargar las cuentas bancarias para las transferencias',
                'done' => QueryBuilder::table('bank_accounts')->whereNull('deleted_at')->count() > 0,
                'url' => '/panel/pagos/cuentas',
            ],
            [
                'label' => 'Publicar el enlace de descarga de la app',
                'done' => SettingsService::string('app.download_url_android', '') !== ''
                    || SettingsService::string('app.apk_direct_url', '') !== '',
                'url' => '/panel/ajustes/app',
            ],
            [
                'label' => 'Crear tu primer anuncio o promocion',
                'done' => QueryBuilder::table('banners')->whereNull('deleted_at')->count() > 0,
                'url' => '/panel/publicidad',
            ],
            [
                'label' => 'Configurar el envio de correos (SMTP)',
                'done' => (string) config('mail.transport', 'log') !== 'log',
                'url' => '/panel/ajustes/notifications',
            ],
        ];

        return array_values(array_filter($items, static fn (array $i): bool => !$i['done']));
    }
}
