<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Database;
use App\Core\QueryBuilder;

/** Metricas del panel: agenda, ingresos, clientes y rendimiento publicitario. */
final class StatsService
{
    /** @return array<string,mixed> */
    public static function dashboard(): array
    {
        $todayStart = Clock::localToUtc(Clock::today() . ' 00:00:00');
        $todayEnd = Clock::localToUtc(Clock::today() . ' 23:59:59');
        $monthStart = Clock::localToUtc(Clock::nowLocal()->format('Y-m-01') . ' 00:00:00');

        return [
            'today_appointments' => QueryBuilder::table('appointments')
                ->whereNull('deleted_at')
                ->whereBetween('starts_at', $todayStart, $todayEnd)
                ->whereIn('status', ['pending', 'confirmed', 'in_progress', 'completed'])
                ->count(),

            'today_pending' => QueryBuilder::table('appointments')
                ->whereNull('deleted_at')
                ->whereBetween('starts_at', $todayStart, $todayEnd)
                ->where('status', 'pending')
                ->count(),

            'upcoming' => QueryBuilder::table('appointments')
                ->whereNull('deleted_at')
                ->where('starts_at', '>', Clock::nowUtc())
                ->whereIn('status', ['pending', 'confirmed'])
                ->count(),

            'payments_to_verify' => QueryBuilder::table('payments')
                ->where('status', 'awaiting_verification')
                ->whereNull('deleted_at')
                ->count(),

            'month_revenue' => QueryBuilder::table('appointments')
                ->whereNull('deleted_at')
                ->where('status', 'completed')
                ->where('starts_at', '>=', $monthStart)
                ->sum('total'),

            'month_appointments' => QueryBuilder::table('appointments')
                ->whereNull('deleted_at')
                ->where('starts_at', '>=', $monthStart)
                ->count(),

            'total_clients' => QueryBuilder::table('users')
                ->where('role', 'client')
                ->whereNull('deleted_at')
                ->count(),

            'new_clients_month' => QueryBuilder::table('users')
                ->where('role', 'client')
                ->whereNull('deleted_at')
                ->where('created_at', '>=', $monthStart)
                ->count(),

            'pending_reviews' => QueryBuilder::table('reviews')
                ->where('is_approved', 0)
                ->whereNull('deleted_at')
                ->count(),

            'unread_messages' => QueryBuilder::table('contact_messages')
                ->where('is_read', 0)
                ->whereNull('deleted_at')
                ->count(),

            'marketing_subscribers' => QueryBuilder::table('users')
                ->where('accepts_marketing', 1)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->count(),
        ];
    }

    /**
     * Serie diaria de citas e ingresos para el grafico del panel.
     *
     * @return list<array{date:string,label:string,appointments:int,revenue:float}>
     */
    public static function dailySeries(int $days = 14): array
    {
        $days = max(1, min(120, $days));
        $since = Clock::localToUtc(
            Clock::nowLocal()->modify('-' . ($days - 1) . ' days')->format('Y-m-d') . ' 00:00:00'
        );

        $rows = Database::instance()->select(
            "SELECT DATE(starts_at) AS d,
                    COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN total ELSE 0 END), 0) AS revenue
               FROM appointments
              WHERE deleted_at IS NULL AND starts_at >= :since
              GROUP BY DATE(starts_at)
              ORDER BY d",
            ['since' => $since]
        );

        $byDate = [];
        foreach ($rows as $row) {
            $byDate[(string) $row['d']] = $row;
        }

        $series = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = Clock::nowLocal()->modify('-' . $offset . ' days')->format('Y-m-d');
            $row = $byDate[$date] ?? null;

            $series[] = [
                'date' => $date,
                'label' => substr($date, 8, 2) . '/' . substr($date, 5, 2),
                'appointments' => (int) ($row['total'] ?? 0),
                'revenue' => (float) ($row['revenue'] ?? 0),
            ];
        }

        return $series;
    }

    /** @return list<array<string,mixed>> */
    public static function topServices(int $limit = 8, int $days = 90): array
    {
        $since = gmdate('Y-m-d H:i:s', time() - $days * 86400);

        return Database::instance()->select(
            'SELECT aps.service_name AS name,
                    COUNT(*) AS total,
                    COALESCE(SUM(aps.price), 0) AS revenue
               FROM appointment_services aps
               INNER JOIN appointments a ON a.id = aps.appointment_id
              WHERE a.deleted_at IS NULL AND a.starts_at >= :since
              GROUP BY aps.service_name
              ORDER BY total DESC
              LIMIT ' . (int) $limit,
            ['since' => $since]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function staffPerformance(int $days = 30): array
    {
        $since = gmdate('Y-m-d H:i:s', time() - $days * 86400);

        return Database::instance()->select(
            "SELECT s.id, s.display_name AS name, s.photo_path,
                    COUNT(a.id) AS appointments,
                    COALESCE(SUM(CASE WHEN a.status = 'completed' THEN a.total ELSE 0 END), 0) AS revenue,
                    SUM(CASE WHEN a.status = 'no_show' THEN 1 ELSE 0 END) AS no_shows,
                    s.rating_average
               FROM staff s
               LEFT JOIN appointments a
                      ON a.staff_id = s.id AND a.deleted_at IS NULL AND a.starts_at >= :since
              WHERE s.deleted_at IS NULL AND s.is_active = 1
              GROUP BY s.id, s.display_name, s.photo_path, s.rating_average
              ORDER BY revenue DESC",
            ['since' => $since]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function bannerPerformance(int $days = 30): array
    {
        $since = gmdate('Y-m-d H:i:s', time() - $days * 86400);

        return Database::instance()->select(
            "SELECT b.id, b.name, b.is_active,
                    SUM(CASE WHEN e.event_type = 'impression' THEN 1 ELSE 0 END) AS impressions,
                    SUM(CASE WHEN e.event_type = 'click' THEN 1 ELSE 0 END) AS clicks,
                    SUM(CASE WHEN e.event_type = 'dismiss' THEN 1 ELSE 0 END) AS dismissals
               FROM banners b
               LEFT JOIN banner_events e ON e.banner_id = b.id AND e.created_at >= :since
              WHERE b.deleted_at IS NULL
              GROUP BY b.id, b.name, b.is_active
              ORDER BY impressions DESC",
            ['since' => $since]
        );
    }

    /** Distribucion de citas por estado en un periodo. @return array<string,int> */
    public static function statusBreakdown(int $days = 30): array
    {
        $since = gmdate('Y-m-d H:i:s', time() - $days * 86400);

        $rows = Database::instance()->select(
            'SELECT status, COUNT(*) AS total
               FROM appointments
              WHERE deleted_at IS NULL AND starts_at >= :since
              GROUP BY status',
            ['since' => $since]
        );

        $result = [
            'pending' => 0, 'confirmed' => 0, 'in_progress' => 0,
            'completed' => 0, 'cancelled' => 0, 'no_show' => 0,
        ];

        foreach ($rows as $row) {
            $result[(string) $row['status']] = (int) $row['total'];
        }

        return $result;
    }

    /** Horas con mas demanda: ayuda a decidir turnos del personal. @return list<array{hour:int,total:int}> */
    public static function peakHours(int $days = 60): array
    {
        $since = gmdate('Y-m-d H:i:s', time() - $days * 86400);

        $rows = Database::instance()->select(
            'SELECT HOUR(starts_at) AS h, COUNT(*) AS total
               FROM appointments
              WHERE deleted_at IS NULL AND starts_at >= :since
              GROUP BY HOUR(starts_at)
              ORDER BY h',
            ['since' => $since]
        );

        return array_map(static fn (array $r): array => [
            'hour' => (int) $r['h'],
            'total' => (int) $r['total'],
        ], $rows);
    }
}
