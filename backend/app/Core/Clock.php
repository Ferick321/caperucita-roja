<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Reloj centralizado.
 *
 * La base de datos guarda SIEMPRE UTC. La zona horaria del negocio es un
 * ajuste editable desde el panel y solo se aplica al presentar/interpretar
 * fechas de cara al usuario.
 */
final class Clock
{
    private static ?DateTimeImmutable $frozen = null;

    private static string $businessTimezone = 'UTC';

    public static function setBusinessTimezone(string $tz): void
    {
        if (in_array($tz, timezone_identifiers_list(), true)) {
            self::$businessTimezone = $tz;
        }
    }

    public static function businessTimezone(): string
    {
        return self::$businessTimezone;
    }

    public static function now(): DateTimeImmutable
    {
        return self::$frozen ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public static function nowUtc(string $format = 'Y-m-d H:i:s'): string
    {
        return self::now()->format($format);
    }

    public static function nowLocal(): DateTimeImmutable
    {
        return self::now()->setTimezone(new DateTimeZone(self::$businessTimezone));
    }

    public static function today(): string
    {
        return self::nowLocal()->format('Y-m-d');
    }

    /** Convierte "Y-m-d H:i:s" en hora del negocio a la cadena UTC equivalente. */
    public static function localToUtc(string $localDateTime): string
    {
        $dt = new DateTimeImmutable($localDateTime, new DateTimeZone(self::$businessTimezone));

        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** Convierte una cadena UTC a hora del negocio. */
    public static function utcToLocal(string $utcDateTime, string $format = 'Y-m-d H:i:s'): string
    {
        $dt = new DateTimeImmutable($utcDateTime, new DateTimeZone('UTC'));

        return $dt->setTimezone(new DateTimeZone(self::$businessTimezone))->format($format);
    }

    /** Congela el reloj (pruebas deterministas). */
    public static function freeze(?string $dateTime): void
    {
        self::$frozen = $dateTime === null
            ? null
            : new DateTimeImmutable($dateTime, new DateTimeZone('UTC'));
    }
}
