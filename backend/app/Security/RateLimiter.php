<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Clock;
use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * Limitador de peticiones con ventana deslizante, persistido en base de datos
 * (funciona en hosting compartido sin Redis).
 *
 * Se aplica a: login, registro, recuperacion de contrasena, agendamiento,
 * subida de comprobantes y a toda la API.
 */
final class RateLimiter
{
    private const TABLE = 'rate_limits';

    /**
     * @return array{allowed:bool,remaining:int,retry_after:int}
     */
    public static function hit(string $key, int $maxAttempts, int $decaySeconds): array
    {
        $hashed = hash('sha256', $key);
        $now = time();
        $windowStart = $now - $decaySeconds;

        $db = Database::instance();

        // Limpieza oportunista de ventanas vencidas (1 de cada 50 peticiones).
        if (random_int(1, 50) === 1) {
            $db->statement(
                'DELETE FROM ' . self::TABLE . ' WHERE expires_at < :cutoff',
                ['cutoff' => Clock::nowUtc()]
            );
        }

        $row = QueryBuilder::table(self::TABLE)->where('bucket_key', $hashed)->first();

        if ($row === null || strtotime((string) $row['window_start']) < $windowStart) {
            $db->statement(
                'INSERT INTO ' . self::TABLE . ' (bucket_key, attempts, window_start, expires_at)
                 VALUES (:k, 1, :s, :e)
                 ON DUPLICATE KEY UPDATE attempts = 1, window_start = :s2, expires_at = :e2',
                [
                    'k' => $hashed,
                    's' => gmdate('Y-m-d H:i:s', $now),
                    'e' => gmdate('Y-m-d H:i:s', $now + $decaySeconds),
                    's2' => gmdate('Y-m-d H:i:s', $now),
                    'e2' => gmdate('Y-m-d H:i:s', $now + $decaySeconds),
                ]
            );

            return ['allowed' => true, 'remaining' => $maxAttempts - 1, 'retry_after' => 0];
        }

        $attempts = (int) $row['attempts'] + 1;

        $db->statement(
            'UPDATE ' . self::TABLE . ' SET attempts = attempts + 1 WHERE bucket_key = :k',
            ['k' => $hashed]
        );

        if ($attempts > $maxAttempts) {
            $retryAfter = max(1, strtotime((string) $row['window_start']) + $decaySeconds - $now);

            return ['allowed' => false, 'remaining' => 0, 'retry_after' => $retryAfter];
        }

        return ['allowed' => true, 'remaining' => max(0, $maxAttempts - $attempts), 'retry_after' => 0];
    }

    public static function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        return self::hit($key, $maxAttempts, $decaySeconds)['allowed'] === false;
    }

    public static function clear(string $key): void
    {
        QueryBuilder::table(self::TABLE)->where('bucket_key', hash('sha256', $key))->delete();
    }

    /** Retardo progresivo tras varios intentos fallidos (frena la fuerza bruta). */
    public static function progressiveDelay(int $failedAttempts): void
    {
        if ($failedAttempts <= 0 || PHP_SAPI === 'cli') {
            return;
        }

        $microseconds = (int) min(2_000_000, 150_000 * (2 ** min($failedAttempts - 1, 4)));
        usleep($microseconds);
    }
}
