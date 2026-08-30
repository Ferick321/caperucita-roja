<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Database;
use App\Core\QueryBuilder;

/** Programa de fidelidad por puntos, configurable desde el panel. */
final class LoyaltyService
{
    public static function grant(int $userId, int $points, string $reason, ?int $appointmentId = null): void
    {
        if ($points === 0 || !SettingsService::bool('loyalty.enabled', true)) {
            return;
        }

        Database::instance()->transaction(static function () use ($userId, $points, $reason, $appointmentId): void {
            Database::instance()->statement(
                'UPDATE users SET loyalty_points = GREATEST(0, loyalty_points + :points) WHERE id = :id',
                ['points' => $points, 'id' => $userId]
            );

            $balance = (int) (QueryBuilder::table('users')->where('id', $userId)->value('loyalty_points') ?? 0);

            QueryBuilder::table('loyalty_transactions')->insert([
                'user_id' => $userId,
                'appointment_id' => $appointmentId,
                'points' => $points,
                'balance_after' => $balance,
                'reason' => mb_substr($reason, 0, 160),
                'created_at' => Clock::nowUtc(),
            ]);
        });
    }

    public static function awardForAppointment(int $userId, int $appointmentId, float $amountSpent): void
    {
        $perCurrency = SettingsService::float('loyalty.points_per_currency', 1);
        $points = (int) floor($amountSpent * $perCurrency);

        // Puntos extra definidos en cada servicio de la cita.
        $bonus = (int) Database::instance()->scalar(
            'SELECT COALESCE(SUM(s.loyalty_points), 0)
               FROM appointment_services aps
               INNER JOIN services s ON s.id = aps.service_id
              WHERE aps.appointment_id = :id',
            ['id' => $appointmentId]
        );

        self::grant($userId, $points + $bonus, 'Visita completada', $appointmentId);
    }

    /** Valor en dinero de los puntos acumulados. */
    public static function pointsToMoney(int $points): float
    {
        $rate = SettingsService::float('loyalty.points_to_currency', 100);

        return $rate <= 0 ? 0.0 : round($points / $rate, 2);
    }

    public static function redeem(int $userId, int $points, string $reason, ?int $appointmentId = null): bool
    {
        $balance = (int) (QueryBuilder::table('users')->where('id', $userId)->value('loyalty_points') ?? 0);

        if ($points <= 0 || $balance < $points) {
            return false;
        }

        self::grant($userId, -$points, $reason, $appointmentId);

        return true;
    }

    /** @return list<array<string,mixed>> */
    public static function history(int $userId, int $limit = 30): array
    {
        return QueryBuilder::table('loyalty_transactions')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }
}
