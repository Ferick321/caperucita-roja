<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Clock;
use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Security\Audit;
use App\Services\StatsService;

/** Informes de negocio: ingresos, ocupacion, servicios y publicidad. */
final class ReportController extends AdminController
{
    public function index(Request $request): Response
    {
        $this->authorize('reportes.ver');

        $days = max(7, min(365, $request->int('dias', 30)));
        $from = Clock::localToUtc(
            Clock::nowLocal()->modify('-' . ($days - 1) . ' days')->format('Y-m-d') . ' 00:00:00'
        );

        return $this->view('admin.reports.index', [
            'days' => $days,
            'series' => StatsService::dailySeries(min(90, $days)),
            'statusBreakdown' => StatsService::statusBreakdown($days),
            'topServices' => StatsService::topServices(12, $days),
            'staffPerformance' => StatsService::staffPerformance($days),
            'bannerPerformance' => StatsService::bannerPerformance($days),
            'peakHours' => StatsService::peakHours($days),
            'revenue' => $this->revenueSummary($from),
            'sources' => $this->sourceBreakdown($from),
            'newClients' => QueryBuilder::table('users')
                ->where('role', 'client')
                ->whereNull('deleted_at')
                ->where('created_at', '>=', $from)
                ->count(),
            'repeatRate' => $this->repeatRate(),
        ]);
    }

    /** Exporta el detalle de citas del periodo en CSV. */
    public function exportAppointments(Request $request): Response
    {
        $this->authorize('reportes.ver');

        $days = max(1, min(730, $request->int('dias', 30)));
        $from = gmdate('Y-m-d H:i:s', time() - $days * 86400);

        $rows = Database::instance()->select(
            "SELECT a.code, a.starts_at, a.status, a.payment_status, a.client_name, a.client_phone,
                    a.subtotal, a.discount_amount, a.total, a.paid_amount, a.source,
                    COALESCE(s.display_name, '') AS profesional,
                    COALESCE(GROUP_CONCAT(aps.service_name SEPARATOR ' + '), '') AS servicios
               FROM appointments a
               LEFT JOIN staff s ON s.id = a.staff_id
               LEFT JOIN appointment_services aps ON aps.appointment_id = a.id
              WHERE a.deleted_at IS NULL AND a.starts_at >= :from
              GROUP BY a.id
              ORDER BY a.starts_at DESC
              LIMIT 20000",
            ['from' => $from]
        );

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new \RuntimeException('No se pudo generar el archivo.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'Codigo', 'Fecha', 'Estado', 'Pago', 'Cliente', 'Telefono', 'Profesional',
            'Servicios', 'Subtotal', 'Descuento', 'Total', 'Pagado', 'Origen',
        ], ';');

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['code'],
                local_datetime((string) $row['starts_at']),
                $this->statusLabel((string) $row['status']),
                $this->paymentLabel((string) $row['payment_status']),
                $row['client_name'],
                $row['client_phone'],
                $row['profesional'],
                $row['servicios'],
                $row['subtotal'],
                $row['discount_amount'],
                $row['total'],
                $row['paid_amount'],
                $row['source'],
            ], ';');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        Audit::record('reportes.exportado', 'appointment', null, null, ['dias' => $days, 'filas' => count($rows)], $request);

        return Response::make($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="citas-' . date('Y-m-d') . '.csv"')
            ->header('Cache-Control', 'no-store');
    }

    /** @return array<string,float> */
    private function revenueSummary(string $from): array
    {
        $row = Database::instance()->selectOne(
            "SELECT
                COALESCE(SUM(CASE WHEN status = 'completed' THEN total ELSE 0 END), 0) AS facturado,
                COALESCE(SUM(paid_amount), 0) AS cobrado,
                COALESCE(SUM(discount_amount), 0) AS descuentos,
                COALESCE(AVG(CASE WHEN status = 'completed' THEN total END), 0) AS ticket_medio
               FROM appointments
              WHERE deleted_at IS NULL AND starts_at >= :from",
            ['from' => $from]
        ) ?? [];

        return [
            'facturado' => (float) ($row['facturado'] ?? 0),
            'cobrado' => (float) ($row['cobrado'] ?? 0),
            'descuentos' => (float) ($row['descuentos'] ?? 0),
            'ticket_medio' => (float) ($row['ticket_medio'] ?? 0),
        ];
    }

    /** @return list<array{source:string,total:int}> */
    private function sourceBreakdown(string $from): array
    {
        $rows = Database::instance()->select(
            'SELECT source, COUNT(*) AS total
               FROM appointments
              WHERE deleted_at IS NULL AND starts_at >= :from
              GROUP BY source
              ORDER BY total DESC',
            ['from' => $from]
        );

        return array_map(static fn (array $r): array => [
            'source' => (string) $r['source'],
            'total' => (int) $r['total'],
        ], $rows);
    }

    /** Porcentaje de clientes que vuelven: mide la fidelizacion real. */
    private function repeatRate(): float
    {
        $total = QueryBuilder::table('users')
            ->where('role', 'client')->whereNull('deleted_at')->where('total_visits', '>', 0)->count();

        if ($total === 0) {
            return 0.0;
        }

        $repeat = QueryBuilder::table('users')
            ->where('role', 'client')->whereNull('deleted_at')->where('total_visits', '>', 1)->count();

        return round($repeat / $total * 100, 1);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'confirmed' => 'Confirmada',
            'in_progress' => 'En curso',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
            'no_show' => 'No asistio',
            default => $status,
        };
    }

    private function paymentLabel(string $status): string
    {
        return match ($status) {
            'unpaid' => 'Sin pagar',
            'deposit_paid' => 'Abono pagado',
            'awaiting_verification' => 'En verificacion',
            'paid' => 'Pagada',
            'refunded' => 'Reembolsada',
            default => $status,
        };
    }
}
