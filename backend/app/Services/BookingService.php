<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\QueryBuilder;
use App\Security\Audit;

/**
 * Reglas de negocio del agendamiento.
 *
 * Punto unico de creacion de citas: lo usan la web, el panel y la API movil,
 * de modo que las reglas no se dupliquen ni se contradigan.
 */
final class BookingService
{
    /**
     * Crea una cita de forma atomica.
     *
     * La comprobacion del hueco se hace DENTRO de la transaccion y bloqueando
     * las filas del profesional, para que dos clientes que pulsan a la vez no
     * reserven el mismo horario.
     *
     * @param array{
     *   branch_id:int, staff_id:?int, service_ids:list<int>, date:string, time:string,
     *   client_id:?int, client_name:string, client_phone:string, client_email:string,
     *   notes?:string, custom_request?:string, source?:string, coupon_code?:string,
     *   payment_method_id?:int
     * } $data
     * @return array<string,mixed> la cita creada
     */
    public static function create(array $data): array
    {
        if (!SettingsService::bool('booking.enabled', true)) {
            throw new HttpException(403, 'El agendamiento en linea esta desactivado por el momento.');
        }

        $serviceIds = array_values(array_unique(array_filter($data['service_ids'] ?? [])));
        $customRequest = trim((string) ($data['custom_request'] ?? ''));

        if ($serviceIds === [] && $customRequest === '') {
            throw new HttpException(422, 'Selecciona al menos un servicio o describe lo que necesitas.');
        }

        $maxServices = SettingsService::int('booking.max_services_per_appointment', 4);

        if (count($serviceIds) > $maxServices) {
            throw new HttpException(422, "Puedes reservar hasta {$maxServices} servicios en una misma cita.");
        }

        $branchId = (int) $data['branch_id'];
        $staffId = isset($data['staff_id']) && (int) $data['staff_id'] > 0 ? (int) $data['staff_id'] : null;
        $clientId = isset($data['client_id']) && (int) $data['client_id'] > 0 ? (int) $data['client_id'] : null;

        self::assertClientQuota($clientId, (string) ($data['client_phone'] ?? ''));
        self::assertWithinBookingWindow((string) $data['date']);

        $services = self::loadServices($serviceIds, $staffId);
        $duration = self::durationFor($services, $customRequest);

        $startLocal = trim((string) $data['date'] . ' ' . (string) $data['time']);
        $startUtc = Clock::localToUtc($startLocal . (strlen((string) $data['time']) === 5 ? ':00' : ''));
        $endUtc = date('Y-m-d H:i:s', strtotime($startUtc) + $duration * 60);

        if (strtotime($startUtc) < time()) {
            throw new HttpException(422, 'No puedes agendar una cita en el pasado.');
        }

        $coupon = null;
        if (!empty($data['coupon_code'])) {
            $coupon = CouponService::validate((string) $data['coupon_code'], $services, $clientId);
        }

        $totals = self::calculateTotals($services, $coupon);

        return Database::instance()->transaction(static function () use (
            $data, $branchId, $staffId, $clientId, $services, $serviceIds,
            $duration, $startUtc, $endUtc, $totals, $coupon, $customRequest
        ): array {
            // Se resuelve el profesional dentro de la transaccion.
            $assignedStaffId = $staffId ?? self::pickAvailableStaff($branchId, $serviceIds, $startUtc, $endUtc);

            if ($assignedStaffId === null) {
                throw new HttpException(409, 'Ese horario acaba de ocuparse. Elige otro, por favor.');
            }

            // Bloqueo pesimista: nadie mas puede insertar sobre este profesional
            // hasta que la transaccion termine.
            QueryBuilder::table('appointments')
                ->where('staff_id', $assignedStaffId)
                ->where('starts_at', '<', $endUtc)
                ->where('ends_at', '>', $startUtc)
                ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
                ->forUpdate()
                ->get();

            if (!AvailabilityService::isSlotFree($assignedStaffId, $startUtc, $endUtc)) {
                throw new HttpException(409, 'Ese horario acaba de ocuparse. Elige otro, por favor.');
            }

            $autoConfirm = SettingsService::bool('booking.auto_confirm', false);
            $now = Clock::nowUtc();
            $code = self::generateCode();

            $appointmentId = QueryBuilder::table('appointments')->insert([
                'code' => $code,
                'branch_id' => $branchId,
                'client_id' => $clientId,
                'staff_id' => $assignedStaffId,
                'client_name' => mb_substr(trim((string) $data['client_name']), 0, 160),
                'client_phone' => mb_substr(trim((string) ($data['client_phone'] ?? '')), 0, 20),
                'client_email' => mb_substr(mb_strtolower(trim((string) ($data['client_email'] ?? ''))), 0, 190),
                'starts_at' => $startUtc,
                'ends_at' => $endUtc,
                'duration_minutes' => $duration,
                'status' => $autoConfirm ? 'confirmed' : 'pending',
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount'],
                'tax_amount' => $totals['tax'],
                'total' => $totals['total'],
                'currency' => SettingsService::string('business.currency', 'USD'),
                'coupon_id' => $coupon['id'] ?? null,
                'payment_status' => 'unpaid',
                'client_notes' => mb_substr(trim((string) ($data['notes'] ?? '')), 0, 2000),
                'custom_request' => mb_substr($customRequest, 0, 255),
                'source' => in_array($data['source'] ?? 'web', ['web', 'app', 'panel', 'phone', 'walk_in'], true)
                    ? (string) $data['source']
                    : 'web',
                'confirmed_at' => $autoConfirm ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($services as $index => $service) {
                QueryBuilder::table('appointment_services')->insert([
                    'appointment_id' => $appointmentId,
                    'service_id' => (int) $service['id'],
                    'service_name' => (string) $service['name'],
                    'duration_minutes' => (int) $service['effective_duration'],
                    'price' => $service['effective_price'],
                    'quantity' => 1,
                    'sort_order' => $index,
                ]);
            }

            QueryBuilder::table('appointment_status_history')->insert([
                'appointment_id' => $appointmentId,
                'from_status' => '',
                'to_status' => $autoConfirm ? 'confirmed' : 'pending',
                'changed_by' => $clientId,
                'note' => 'Cita creada desde ' . ($data['source'] ?? 'web'),
                'created_at' => $now,
            ]);

            if ($coupon !== null) {
                CouponService::redeem((int) $coupon['id'], $clientId, $appointmentId, $totals['discount']);
            }

            $appointment = QueryBuilder::table('appointments')->where('id', $appointmentId)->first() ?? [];

            NotificationService::onAppointmentCreated($appointment);
            Audit::record('cita.creada', 'appointment', $appointmentId, null, ['code' => $code]);

            return $appointment;
        });
    }

    /** Cambia el estado de una cita respetando las transiciones validas. */
    public static function changeStatus(int $appointmentId, string $newStatus, ?int $actorId = null, string $note = ''): array
    {
        $valid = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'];

        if (!in_array($newStatus, $valid, true)) {
            throw new HttpException(422, 'Estado de cita no valido.');
        }

        return Database::instance()->transaction(static function () use ($appointmentId, $newStatus, $actorId, $note): array {
            $appointment = QueryBuilder::table('appointments')->where('id', $appointmentId)->first();

            if ($appointment === null) {
                throw new HttpException(404, 'La cita no existe.');
            }

            $current = (string) $appointment['status'];

            if ($current === $newStatus) {
                return $appointment;
            }

            // Una cita cerrada no vuelve atras.
            if (in_array($current, ['completed', 'cancelled', 'no_show'], true)
                && !in_array($newStatus, ['completed'], true)
            ) {
                throw new HttpException(422, 'Esta cita ya esta cerrada y no admite mas cambios.');
            }

            $now = Clock::nowUtc();
            $updates = ['status' => $newStatus, 'updated_at' => $now];

            switch ($newStatus) {
                case 'confirmed':
                    $updates['confirmed_at'] = $now;
                    break;
                case 'in_progress':
                    $updates['started_at'] = $now;
                    break;
                case 'completed':
                    $updates['completed_at'] = $now;
                    break;
                case 'cancelled':
                    $updates['cancelled_at'] = $now;
                    $updates['cancelled_by'] = $actorId;
                    $updates['cancellation_reason'] = mb_substr($note, 0, 255);
                    break;
            }

            QueryBuilder::table('appointments')->where('id', $appointmentId)->update($updates);

            QueryBuilder::table('appointment_status_history')->insert([
                'appointment_id' => $appointmentId,
                'from_status' => $current,
                'to_status' => $newStatus,
                'changed_by' => $actorId,
                'note' => mb_substr($note, 0, 255),
                'created_at' => $now,
            ]);

            $updated = array_merge($appointment, $updates);

            if ($newStatus === 'completed') {
                self::onCompleted($updated);
            }

            NotificationService::onAppointmentStatusChanged($updated, $current);
            Audit::record('cita.estado', 'appointment', $appointmentId, ['status' => $current], ['status' => $newStatus]);

            return $updated;
        });
    }

    /** Cancelacion iniciada por el cliente, sujeta a la antelacion configurada. */
    public static function cancelByClient(int $appointmentId, int $clientId, string $reason = ''): array
    {
        if (!SettingsService::bool('booking.allow_client_cancel', true)) {
            throw new HttpException(403, 'Las cancelaciones en linea estan desactivadas. Llamanos, por favor.');
        }

        $appointment = QueryBuilder::table('appointments')
            ->where('id', $appointmentId)
            ->where('client_id', $clientId)
            ->whereNull('deleted_at')
            ->first();

        if ($appointment === null) {
            throw new HttpException(404, 'No encontramos esa cita.');
        }

        if (in_array((string) $appointment['status'], ['cancelled', 'completed', 'no_show'], true)) {
            throw new HttpException(422, 'Esta cita ya no se puede cancelar.');
        }

        $minHours = SettingsService::int('booking.cancellation_hours', 4);
        $hoursLeft = (strtotime((string) $appointment['starts_at']) - time()) / 3600;

        if ($hoursLeft < $minHours) {
            throw new HttpException(422, sprintf(
                'Las cancelaciones se aceptan con al menos %d horas de antelacion. Comunicate con el local.',
                $minHours
            ));
        }

        return self::changeStatus($appointmentId, 'cancelled', $clientId, $reason ?: 'Cancelada por el cliente');
    }

    /** Reprogramacion: valida el nuevo hueco y mueve la cita. */
    public static function reschedule(int $appointmentId, string $date, string $time, ?int $staffId, ?int $actorId): array
    {
        $appointment = QueryBuilder::table('appointments')->where('id', $appointmentId)->first();

        if ($appointment === null) {
            throw new HttpException(404, 'La cita no existe.');
        }

        if (in_array((string) $appointment['status'], ['cancelled', 'completed', 'no_show'], true)) {
            throw new HttpException(422, 'Esta cita ya esta cerrada.');
        }

        self::assertWithinBookingWindow($date);

        $duration = (int) $appointment['duration_minutes'];
        $startUtc = Clock::localToUtc($date . ' ' . (strlen($time) === 5 ? $time . ':00' : $time));
        $endUtc = date('Y-m-d H:i:s', strtotime($startUtc) + $duration * 60);
        $targetStaff = $staffId ?? (int) $appointment['staff_id'];

        if (!AvailabilityService::isSlotFree($targetStaff, $startUtc, $endUtc, $appointmentId)) {
            throw new HttpException(409, 'Ese horario ya esta ocupado.');
        }

        $now = Clock::nowUtc();

        QueryBuilder::table('appointments')->where('id', $appointmentId)->update([
            'starts_at' => $startUtc,
            'ends_at' => $endUtc,
            'staff_id' => $targetStaff,
            'reminder_sent_at' => null,
            'updated_at' => $now,
        ]);

        QueryBuilder::table('appointment_status_history')->insert([
            'appointment_id' => $appointmentId,
            'from_status' => (string) $appointment['status'],
            'to_status' => (string) $appointment['status'],
            'changed_by' => $actorId,
            'note' => 'Reprogramada para ' . $date . ' ' . $time,
            'created_at' => $now,
        ]);

        $updated = QueryBuilder::table('appointments')->where('id', $appointmentId)->first() ?? [];

        NotificationService::onAppointmentRescheduled($updated);
        Audit::record('cita.reprogramada', 'appointment', $appointmentId,
            ['starts_at' => $appointment['starts_at']], ['starts_at' => $startUtc]);

        return $updated;
    }

    // ---- Auxiliares ----------------------------------------------------

    /** @param array<string,mixed> $appointment */
    private static function onCompleted(array $appointment): void
    {
        $clientId = $appointment['client_id'] ?? null;

        if ($clientId === null) {
            return;
        }

        $clientId = (int) $clientId;
        $total = (float) $appointment['total'];

        QueryBuilder::table('users')->where('id', $clientId)->update([
            'last_visit_at' => Clock::nowUtc(),
            'updated_at' => Clock::nowUtc(),
        ]);

        Database::instance()->statement(
            'UPDATE users SET total_visits = total_visits + 1, total_spent = total_spent + :amount WHERE id = :id',
            ['amount' => $total, 'id' => $clientId]
        );

        if (SettingsService::bool('loyalty.enabled', true)) {
            LoyaltyService::awardForAppointment($clientId, (int) $appointment['id'], $total);
        }
    }

    /**
     * @param list<int> $serviceIds
     * @return list<array<string,mixed>>
     */
    private static function loadServices(array $serviceIds, ?int $staffId): array
    {
        if ($serviceIds === []) {
            return [];
        }

        $services = QueryBuilder::table('services')
            ->whereIn('id', $serviceIds)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->get();

        if (count($services) !== count($serviceIds)) {
            throw new HttpException(422, 'Alguno de los servicios seleccionados ya no esta disponible.');
        }

        $overrides = [];

        if ($staffId !== null) {
            foreach (QueryBuilder::table('staff_services')
                ->where('staff_id', $staffId)
                ->whereIn('service_id', $serviceIds)
                ->get() as $row
            ) {
                $overrides[(int) $row['service_id']] = $row;
            }
        }

        foreach ($services as $index => $service) {
            $id = (int) $service['id'];
            $override = $overrides[$id] ?? null;

            $services[$index]['effective_duration'] = $override !== null && $override['custom_duration'] !== null
                ? (int) $override['custom_duration']
                : (int) $service['duration_minutes'];

            $services[$index]['effective_price'] = $override !== null && $override['custom_price'] !== null
                ? (float) $override['custom_price']
                : self::currentPrice($service);
        }

        return $services;
    }

    /** Precio vigente considerando la promocion activa. @param array<string,mixed> $service */
    public static function currentPrice(array $service): float
    {
        $promo = $service['promo_price'] ?? null;

        if ($promo === null) {
            return (float) $service['price'];
        }

        $now = time();
        $from = $service['promo_starts_at'] ?? null;
        $to = $service['promo_ends_at'] ?? null;

        if ($from !== null && strtotime((string) $from) > $now) {
            return (float) $service['price'];
        }

        if ($to !== null && strtotime((string) $to) < $now) {
            return (float) $service['price'];
        }

        return (float) $promo;
    }

    /** @param list<array<string,mixed>> $services */
    private static function durationFor(array $services, string $customRequest): int
    {
        $total = 0;

        foreach ($services as $service) {
            $total += (int) $service['effective_duration']
                + (int) $service['buffer_before_minutes']
                + (int) $service['buffer_after_minutes'];
        }

        // Peticion libre sin servicios: se reserva un bloque de consulta.
        if ($total === 0 && $customRequest !== '') {
            $total = SettingsService::int('booking.custom_request_minutes', 30);
        }

        return max(5, $total);
    }

    /**
     * @param list<array<string,mixed>> $services
     * @param array<string,mixed>|null $coupon
     * @return array{subtotal:float,discount:float,tax:float,total:float}
     */
    private static function calculateTotals(array $services, ?array $coupon): array
    {
        $subtotal = 0.0;

        foreach ($services as $service) {
            $subtotal += (float) $service['effective_price'];
        }

        $discount = $coupon === null ? 0.0 : CouponService::discountFor($coupon, $subtotal);
        $taxable = max(0.0, $subtotal - $discount);
        $taxRate = SettingsService::float('business.tax_percent', 0.0);
        $tax = round($taxable * $taxRate / 100, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'tax' => $tax,
            'total' => round($taxable + $tax, 2),
        ];
    }

    /** @param list<int> $serviceIds */
    private static function pickAvailableStaff(int $branchId, array $serviceIds, string $startUtc, string $endUtc): ?int
    {
        $candidates = AvailabilityService::eligibleStaff($branchId, null, $serviceIds);

        foreach ($candidates as $staff) {
            if (AvailabilityService::isSlotFree((int) $staff['id'], $startUtc, $endUtc)) {
                return (int) $staff['id'];
            }
        }

        return null;
    }

    /** Evita que una misma persona sature la agenda con reservas abiertas. */
    private static function assertClientQuota(?int $clientId, string $phone): void
    {
        $max = SettingsService::int('booking.max_active_per_client', 3);

        if ($max <= 0) {
            return;
        }

        $query = QueryBuilder::table('appointments')
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereNull('deleted_at')
            ->where('starts_at', '>', Clock::nowUtc());

        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        } elseif ($phone !== '') {
            $query->where('client_phone', $phone);
        } else {
            return;
        }

        if ($query->count() >= $max) {
            throw new HttpException(422, sprintf(
                'Ya tienes %d citas activas. Cancela o completa alguna antes de agendar otra.',
                $max
            ));
        }
    }

    private static function assertWithinBookingWindow(string $date): void
    {
        $maxDays = SettingsService::int('booking.max_days_ahead', 60);
        $limit = strtotime('+' . $maxDays . ' days', strtotime(Clock::today()));

        if (strtotime($date) > $limit) {
            throw new HttpException(422, "Solo puedes agendar con hasta {$maxDays} dias de anticipacion.");
        }

        if (strtotime($date) < strtotime(Clock::today())) {
            throw new HttpException(422, 'La fecha seleccionada ya paso.');
        }
    }

    /** Codigo corto e irrepetible que el cliente usa como referencia. */
    private static function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sin caracteres ambiguos

        for ($attempt = 0; $attempt < 12; $attempt++) {
            $code = 'CT-';

            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            if (!QueryBuilder::table('appointments')->where('code', $code)->exists()) {
                return $code;
            }
        }

        Logger::warning('Colisiones repetidas al generar el codigo de cita');

        return 'CT-' . strtoupper(bin2hex(random_bytes(5)));
    }
}
