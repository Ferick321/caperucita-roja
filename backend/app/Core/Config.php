<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Configuracion de arranque (archivos PHP en /config).
 *
 * Ojo: la configuracion *del negocio* (colores, textos, horarios, publicidad)
 * NO vive aqui sino en la base de datos, editable desde el panel admin.
 * Este objeto solo guarda lo que necesita el proceso para levantar.
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $items = [];

    public static function loadDirectory(string $dir): void
    {
        foreach (glob(rtrim($dir, '/') . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            $data = require $file;

            if (is_array($data)) {
                self::$items[$name] = $data;
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref = &self::$items;

        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }

            $ref = &$ref[$segment];
        }

        $ref = $value;
    }
}
