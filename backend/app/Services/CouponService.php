<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\QueryBuilder;

/** Cupones de descuento creados y controlados desde el panel. */
final class CouponService
{
    /**
     * Valida un cupon para un carrito concreto.
     *
     * @param list<array<string,mixed>> $services
     * @return array<string,mixed>
     */
    public static function validate(string $code, array $services, ?int $userId): array
    {
        $code = strtoupper(trim($code));

        $coupon = QueryBuilder::table('coupons')
            ->where('code', $code)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->first();

        if ($coupon === null) {
            throw new HttpException(422, 'El cupon no existe o ya no esta disponible.');
        }

        $now = Clock::nowUtc();

        if ($coupon['starts_at'] !== null && (string) $coupon['starts_at'] > $now) {
            throw new HttpException(422, 'Este cupon aun no esta vigente.');
        }

        if ($coupon['ends_at'] !== null && (string) $coupon['ends_at'] < $now) {
            throw new HttpException(422, 'Este cupon ya vencio.');
        }

        $usageLimit = (int) $coupon['usage_limit'];

        if ($usageLimit > 0 && (int) $coupon['times_used'] >= $usageLimit) {
            throw new HttpException(422, 'Este cupon alcanzo su limite de usos.');
        }

        if ($userId !== null) {
            $perUser = (int) $coupon['usage_limit_per_user'];

            if ($perUser > 0) {
                $used = QueryBuilder::table('coupon_redemptions')
                    ->where('coupon_id', (int) $coupon['id'])
                    ->where('user_id', $userId)
                    ->count();

                if ($used >= $perUser) {
                    throw new HttpException(422, 'Ya usaste este cupon.');
                }
            }

            if ((bool) $coupon['first_visit_only']) {
                $visits = (int) (QueryBuilder::table('users')->where('id', $userId)->value('total_visits') ?? 0);

                if ($visits > 0) {
                    throw new HttpException(422, 'Este cupon es solo para la primera visita.');
                }
            }
        }

        // Cupon atado a un servicio concreto.
        if ($coupon['service_id'] !== null) {
            $serviceIds = array_map(static fn (array $s): int => (int) $s['id'], $services);

            if (!in_array((int) $coupon['service_id'], $serviceIds, true)) {
                throw new HttpException(422, 'Este cupon no aplica a los servicios seleccionados.');
            }
        }

        $subtotal = 0.0;
        foreach ($services as $service) {
            $subtotal += (float) ($service['effective_price'] ?? $service['price'] ?? 0);
        }

        if ($subtotal < (float) $coupon['min_amount']) {
            throw new HttpException(422, sprintf(
                'Este cupon requiere una compra minima de %s.',
                money((float) $coupon['min_amount'])
            ));
        }

        return $coupon;
    }

    /** @param array<string,mixed> $coupon */
    public static function discountFor(array $coupon, float $subtotal): float
    {
        $value = (float) $coupon['discount_value'];

        $discount = (string) $coupon['discount_type'] === 'percent'
            ? $subtotal * $value / 100
            : $value;

        if ($coupon['max_discount'] !== null) {
            $discount = min($discount, (float) $coupon['max_discount']);
        }

        return round(min($discount, $subtotal), 2);
    }

    public static function redeem(int $couponId, ?int $userId, int $appointmentId, float $discount): void
    {
        QueryBuilder::table('coupon_redemptions')->insert([
            'coupon_id' => $couponId,
            'user_id' => $userId,
            'appointment_id' => $appointmentId,
            'discount_applied' => $discount,
            'created_at' => Clock::nowUtc(),
        ]);

        Database::instance()->statement(
            'UPDATE coupons SET times_used = times_used + 1 WHERE id = :id',
            ['id' => $couponId]
        );
    }
}
