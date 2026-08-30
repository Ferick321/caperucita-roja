<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Registro en archivo con rotacion diaria.
 *
 * Los valores sensibles se enmascaran antes de escribir: nunca deben quedar
 * contrasenas, tokens ni numeros de cuenta en los registros.
 */
final class Logger
{
    private const REDACTED = '***';

    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password', 'new_password',
        'token', 'access_token', 'refresh_token', 'api_key', 'secret',
        'authorization', 'cookie', 'csrf_token', 'card', 'cvv', 'pin',
        'account_number', 'numero_cuenta', 'clave', 'contrasena', 'totp_secret',
    ];

    private static string $directory = '';

    private static string $minLevel = 'debug';

    private const LEVELS = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3, 'critical' => 4];

    public static function configure(string $directory, string $minLevel = 'debug'): void
    {
        self::$directory = rtrim($directory, '/');
        self::$minLevel = array_key_exists($minLevel, self::LEVELS) ? $minLevel : 'debug';
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::log('critical', $message, $context);
    }

    /** @param array<string,mixed> $context */
    public static function log(string $level, string $message, array $context = []): void
    {
        if (self::$directory === '') {
            return;
        }

        if ((self::LEVELS[$level] ?? 0) < (self::LEVELS[self::$minLevel] ?? 0)) {
            return;
        }

        if (!is_dir(self::$directory)) {
            @mkdir(self::$directory, 0770, true);
        }

        $entry = [
            'ts' => gmdate('c'),
            'level' => $level,
            'msg' => mb_substr($message, 0, 2000),
            'ctx' => self::redact($context),
            'req' => $_SERVER['REQUEST_URI'] ?? 'cli',
        ];

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        if ($line === false) {
            return;
        }

        $file = self::$directory . '/app-' . gmdate('Y-m-d') . '.log';

        @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        @chmod($file, 0640);
    }

    /**
     * @param array<mixed> $context
     * @return array<mixed>
     */
    public static function redact(array $context): array
    {
        $clean = [];

        foreach ($context as $key => $value) {
            $isSensitive = is_string($key) && self::isSensitiveKey($key);

            if ($isSensitive) {
                $clean[$key] = self::REDACTED;

                continue;
            }

            if (is_array($value)) {
                $clean[$key] = self::redact($value);

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $clean[$key] = is_string($value) ? mb_substr($value, 0, 500) : $value;

                continue;
            }

            $clean[$key] = '[' . get_debug_type($value) . ']';
        }

        return $clean;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $needle = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($needle, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    /** Borra registros mas antiguos que N dias (tarea de mantenimiento). */
    public static function purgeOlderThan(int $days): int
    {
        if (self::$directory === '' || !is_dir(self::$directory)) {
            return 0;
        }

        $cutoff = time() - ($days * 86400);
        $removed = 0;

        foreach (glob(self::$directory . '/app-*.log') ?: [] as $file) {
            if (filemtime($file) < $cutoff && @unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }
}
