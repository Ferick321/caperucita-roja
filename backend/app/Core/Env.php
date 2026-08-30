<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lector del archivo .env.
 *
 * No usa putenv()/getenv() para evitar que las credenciales se filtren a
 * procesos hijos lanzados por la aplicacion.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $values = [];

    private static bool $loaded = false;

    public static function load(string $file): void
    {
        self::$loaded = true;

        if (!is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $pos = strpos($line, '=');

            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            // Quita comillas envolventes conservando el contenido literal.
            if (strlen($value) >= 2) {
                $first = $value[0];
                if (($first === '"' || $first === "'") && str_ends_with($value, $first)) {
                    $value = substr($value, 1, -1);
                }
            }

            if ($key !== '') {
                self::$values[$key] = $value;
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            return $default;
        }

        if (!array_key_exists($key, self::$values)) {
            return $default;
        }

        $value = self::$values[$key];

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }

    public static function required(string $key): string
    {
        $value = self::get($key);

        if ($value === null || $value === '') {
            throw new \RuntimeException("Falta la variable de entorno obligatoria: {$key}");
        }

        return (string) $value;
    }

    /** Solo para pruebas automatizadas. */
    public static function setForTesting(string $key, string $value): void
    {
        self::$loaded = true;
        self::$values[$key] = $value;
    }
}
