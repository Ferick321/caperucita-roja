<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Database;
use App\Core\QueryBuilder;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Motor de disponibilidad.
 *
 * Calcula los huecos reales de la agenda cruzando:
 *   horario de la sucursal + jornada del profesional - descansos
 *   - ausencias - feriados - citas ya reservadas (con sus margenes)
 *   - reglas de antelacion minima y maxima.
 *
 * Todas las reglas salen de la configuracion editable, no del codigo.
 */
final class AvailabilityService
{
    /**
     * Huecos disponibles de un dia.
     *
     * @param string $date fecha local del negocio (Y-m-d)
     * @return list<array{time:string,label:string,staff:list<int>,starts_at_utc:string,ends_at_utc:string}>
     */
    public static function slotsForDate(
        string $date,
        int $branchId,
        int $totalDurationMinutes,
        ?int $staffId = null,
        array $serviceIds = [],
        ?int $excludeAppointmentId = null
    ): array {
        $totalDurationMinutes = max(5, $totalDurationMinutes);
        $tz = new DateTimeZone(Clock::businessTimezone());

        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);

        if ($day === false) {
            return [];
        }

        if (self::isClosedDay($branchId, $date)) {
            return [];
        }

        $weekday = (int) $day->format('w');
        $branchHours = self::branchHours($branchId, $weekday);

        if ($branchHours === null) {
            return [];
        }

        $candidates = self::eligibleStaff($branchId, $staffId, $serviceIds);

        if ($candidates === []) {
            return [];
        }

        $interval = max(5, SettingsService::int('booking.slot_interval_minutes', 15));
        $minLeadMinutes = SettingsService::int('booking.min_hours_before', 2) * 60;
        $earliestUtc = Clock::now()->modify('+' . $minLeadMinutes . ' minutes');

        $staffIds = array_map(static fn (array $s): int => (int) $s['id'], $candidates);
        $busy = self::busyIntervals($staffIds, $day, $excludeAppointmentId);
        $timeOff = self::timeOffIntervals($staffIds, $day);

        /** @var array<string,array{time:string,label:string,staff:list<int>,starts_at_utc:string,ends_at_utc:string}> $slots */
        $slots = [];

        foreach ($candidates as $staff) {
            $currentStaffId = (int) $staff['id'];

            foreach (self::workingWindows($currentStaffId, $weekday, $date) as $window) {
                // La jornada nunca puede exceder el horario de la sucursal.
                $windowStart = max($window['start'], $branchHours['open']);
                $windowEnd = min($window['end'], $branchHours['close']);

                if ($windowStart >= $windowEnd) {
                    continue;
                }

                $cursor = self::alignToInterval($windowStart, $interval);

                while (($cursor + $totalDurationMinutes) <= $windowEnd) {
                    $startLocal = $day->setTime(intdiv($cursor, 60), $cursor % 60);
                    $endLocal = $startLocal->modify('+' . $totalDurationMinutes . ' minutes');

                    $startUtc = $startLocal->setTimezone(new DateTimeZone('UTC'));
                    $endUtc = $endLocal->setTimezone(new DateTimeZone('UTC'));

                    // Antelacion minima respecto al momento actual.
                    if ($startUtc <= $earliestUtc) {
                        $cursor += $interval;

                        continue;
                    }

                    if (self::overlapsAny($startUtc, $endUtc, $busy[$currentStaffId] ?? [])
                        || self::overlapsAny($startUtc, $endUtc, $timeOff[$currentStaffId] ?? [])
                    ) {
                        $cursor += $interval;

                        continue;
                    }

                    $key = $startLocal->format('H:i');

                    if (!isset($slots[$key])) {
                        $slots[$key] = [
                            'time' => $key,
                            'label' => $startLocal->format('H:i'),
                            'staff' => [],
                            'starts_at_utc' => $startUtc->format('Y-m-d H:i:s'),
                            'ends_at_utc' => $endUtc->format('Y-m-d H:i:s'),
                        ];
                    }

                    $slots[$key]['staff'][] = $currentStaffId;
                    $cursor += $interval;
                }
            }
        }

        ksort($slots);

        return array_values($slots);
    }

    /**
     * Dias con al menos un hueco libre dentro del rango permitido.
     *
     * @return list<array{date:string,label:string,slots:int}>
     */
    public static function availableDays(
        int $branchId,
        int $totalDurationMinutes,
        ?int $staffId = null,
        array $serviceIds = [],
        int $daysAhead = 0
    ): array {
        $maxDays = $daysAhead > 0
            ? $daysAhead
            : max(1, SettingsService::int('booking.max_days_ahead', 60));

        $tz = new DateTimeZone(Clock::businessTimezone());
        $today = Clock::now()->setTimezone($tz);
        $days = [];

        for ($offset = 0; $offset <= $maxDays; $offset++) {
            $date = $today->modify('+' . $offset . ' days')->format('Y-m-d');
            $slots = self::slotsForDate($date, $branchId, $totalDurationMinutes, $staffId, $serviceIds);

            if ($slots !== []) {
                $days[] = [
                    'date' => $date,
                    'label' => self::formatDayLabel($date),
                    'slots' => count($slots),
                ];
            }
        }

        return $days;
    }

    /** Comprueba que un hueco concreto siga libre justo antes de reservar. */
    public static function isSlotFree(
        int $staffId,
        string $startsAtUtc,
        string $endsAtUtc,
        ?int $excludeAppointmentId = null
    ): bool {
        $query = QueryBuilder::table('appointments')
            ->where('staff_id', $staffId)
            ->whereNull('deleted_at')
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->where('starts_at', '<', $endsAtUtc)
            ->where('ends_at', '>', $startsAtUtc);

        if ($excludeAppointmentId !== null) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        if ($query->exists()) {
            return false;
        }

        return !QueryBuilder::table('staff_time_off')
            ->where('staff_id', $staffId)
            ->where('starts_at', '<', $endsAtUtc)
            ->where('ends_at', '>', $startsAtUtc)
            ->exists();
    }

    /**
     * Profesionales que pueden atender los servicios pedidos.
     *
     * @param list<int> $serviceIds
     * @return list<array<string,mixed>>
     */
    public static function eligibleStaff(int $branchId, ?int $staffId, array $serviceIds): array
    {
        $query = QueryBuilder::table('staff')
            ->select(['staff.id', 'staff.display_name', 'staff.photo_path', 'staff.title', 'staff.color'])
            ->where('staff.branch_id', $branchId)
            ->where('staff.is_active', 1)
            ->where('staff.accepts_online', 1)
            ->whereNull('staff.deleted_at');

        if ($staffId !== null && $staffId > 0) {
            $query->where('staff.id', $staffId);
        }

        $staff = $query->orderBy('staff.sort_order')->get();

        if ($serviceIds === []) {
            return $staff;
        }

        // Solo quedan quienes prestan TODOS los servicios solicitados.
        $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
        $rows = Database::instance()->select(
            'SELECT staff_id, COUNT(DISTINCT service_id) AS total
               FROM staff_services
              WHERE service_id IN (' . $placeholders . ')
              GROUP BY staff_id',
            array_values($serviceIds)
        );

        $qualified = [];
        foreach ($rows as $row) {
            if ((int) $row['total'] === count($serviceIds)) {
                $qualified[] = (int) $row['staff_id'];
            }
        }

        return array_values(array_filter(
            $staff,
            static fn (array $s): bool => in_array((int) $s['id'], $qualified, true)
        ));
    }

    /**
     * Tramos de trabajo del profesional ese dia, en minutos desde medianoche.
     *
     * @return list<array{start:int,end:int}>
     */
    private static function workingWindows(int $staffId, int $weekday, string $date): array
    {
        $schedules = QueryBuilder::table('staff_schedules')
            ->where('staff_id', $staffId)
            ->where('weekday', $weekday)
            ->where('is_active', 1)
            ->whereGroup(static function (QueryBuilder $q) use ($date): void {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $date);
            })
            ->whereGroup(static function (QueryBuilder $q) use ($date): void {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $date);
            })
            ->orderBy('starts_at')
            ->get();

        $windows = [];

        foreach ($schedules as $schedule) {
            $start = self::toMinutes((string) $schedule['starts_at']);
            $end = self::toMinutes((string) $schedule['ends_at']);

            if ($end <= $start) {
                continue;
            }

            $breakStart = $schedule['break_start'] !== null ? self::toMinutes((string) $schedule['break_start']) : null;
            $breakEnd = $schedule['break_end'] !== null ? self::toMinutes((string) $schedule['break_end']) : null;

            // El descanso parte la jornada en dos tramos.
            if ($breakStart !== null && $breakEnd !== null && $breakEnd > $breakStart
                && $breakStart > $start && $breakEnd < $end
            ) {
                $windows[] = ['start' => $start, 'end' => $breakStart];
                $windows[] = ['start' => $breakEnd, 'end' => $end];
            } else {
                $windows[] = ['start' => $start, 'end' => $end];
            }
        }

        return $windows;
    }

    /** @return array{open:int,close:int}|null */
    private static function branchHours(int $branchId, int $weekday): ?array
    {
        $row = QueryBuilder::table('branch_hours')
            ->where('branch_id', $branchId)
            ->where('weekday', $weekday)
            ->first();

        if ($row === null) {
            // Sin horario definido se asume jornada amplia; manda la del profesional.
            return ['open' => 0, 'close' => 1440];
        }

        if ((bool) $row['is_closed']) {
            return null;
        }

        return [
            'open' => self::toMinutes((string) $row['opens_at']),
            'close' => self::toMinutes((string) $row['closes_at']),
        ];
    }

    private static function isClosedDay(int $branchId, string $date): bool
    {
        return Database::instance()->scalar(
            'SELECT COUNT(*) FROM branch_closures
              WHERE (branch_id = :branch OR branch_id IS NULL)
                AND :date BETWEEN starts_on AND ends_on',
            ['branch' => $branchId, 'date' => $date]
        ) > 0;
    }

    /**
     * Citas ocupadas por profesional, ampliadas con los margenes del servicio.
     *
     * @param list<int> $staffIds
     * @return array<int,list<array{start:DateTimeImmutable,end:DateTimeImmutable}>>
     */
    private static function busyIntervals(
        array $staffIds,
        DateTimeImmutable $day,
        ?int $excludeAppointmentId
    ): array {
        if ($staffIds === []) {
            return [];
        }

        // Se consulta con un dia de margen para cubrir el cambio de zona horaria.
        $from = $day->setTime(0, 0)->modify('-1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $to = $day->setTime(23, 59, 59)->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $query = QueryBuilder::table('appointments')
            ->select(['id', 'staff_id', 'starts_at', 'ends_at'])
            ->whereIn('staff_id', $staffIds)
            ->whereNull('deleted_at')
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from);

        if ($excludeAppointmentId !== null) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        $intervals = [];

        foreach ($query->get() as $row) {
            $staffKey = (int) $row['staff_id'];
            $intervals[$staffKey][] = [
                'start' => new DateTimeImmutable((string) $row['starts_at'], new DateTimeZone('UTC')),
                'end' => new DateTimeImmutable((string) $row['ends_at'], new DateTimeZone('UTC')),
            ];
        }

        return $intervals;
    }

    /**
     * @param list<int> $staffIds
     * @return array<int,list<array{start:DateTimeImmutable,end:DateTimeImmutable}>>
     */
    private static function timeOffIntervals(array $staffIds, DateTimeImmutable $day): array
    {
        if ($staffIds === []) {
            return [];
        }

        $from = $day->setTime(0, 0)->modify('-1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $to = $day->setTime(23, 59, 59)->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $rows = QueryBuilder::table('staff_time_off')
            ->select(['staff_id', 'starts_at', 'ends_at'])
            ->whereIn('staff_id', $staffIds)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->get();

        $intervals = [];

        foreach ($rows as $row) {
            $staffKey = (int) $row['staff_id'];
            $intervals[$staffKey][] = [
                'start' => new DateTimeImmutable((string) $row['starts_at'], new DateTimeZone('UTC')),
                'end' => new DateTimeImmutable((string) $row['ends_at'], new DateTimeZone('UTC')),
            ];
        }

        return $intervals;
    }

    /** @param list<array{start:DateTimeImmutable,end:DateTimeImmutable}> $intervals */
    private static function overlapsAny(DateTimeImmutable $start, DateTimeImmutable $end, array $intervals): bool
    {
        foreach ($intervals as $interval) {
            if ($start < $interval['end'] && $end > $interval['start']) {
                return true;
            }
        }

        return false;
    }

    public static function toMinutes(string $time): int
    {
        [$hours, $minutes] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        return $hours * 60 + $minutes;
    }

    private static function alignToInterval(int $minutes, int $interval): int
    {
        return (int) (ceil($minutes / $interval) * $interval);
    }

    public static function formatDayLabel(string $date): string
    {
        static $days = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];
        static $months = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

        $dt = new DateTimeImmutable($date);
        $today = Clock::today();
        $tomorrow = (new DateTimeImmutable($today))->modify('+1 day')->format('Y-m-d');

        if ($date === $today) {
            return 'Hoy';
        }

        if ($date === $tomorrow) {
            return 'Manana';
        }

        return $days[(int) $dt->format('w')] . ' ' . $dt->format('j') . ' ' . $months[(int) $dt->format('n')];
    }

    /**
     * Duracion total de un conjunto de servicios, incluyendo margenes.
     *
     * @param list<int> $serviceIds
     */
    public static function totalDuration(array $serviceIds, ?int $staffId = null): int
    {
        if ($serviceIds === []) {
            return 0;
        }

        $rows = QueryBuilder::table('services')
            ->select(['id', 'duration_minutes', 'buffer_before_minutes', 'buffer_after_minutes'])
            ->whereIn('id', $serviceIds)
            ->whereNull('deleted_at')
            ->get();

        $custom = [];

        if ($staffId !== null && $staffId > 0) {
            foreach (QueryBuilder::table('staff_services')
                ->select(['service_id', 'custom_duration'])
                ->where('staff_id', $staffId)
                ->whereIn('service_id', $serviceIds)
                ->get() as $row
            ) {
                if ($row['custom_duration'] !== null) {
                    $custom[(int) $row['service_id']] = (int) $row['custom_duration'];
                }
            }
        }

        $total = 0;

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $total += $custom[$id] ?? (int) $row['duration_minutes'];
            $total += (int) $row['buffer_before_minutes'] + (int) $row['buffer_after_minutes'];
        }

        return $total;
    }
}
