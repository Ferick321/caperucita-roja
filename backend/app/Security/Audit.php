<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Clock;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Logger;

/**
 * Bitacora de auditoria.
 *
 * Deja rastro de toda accion sensible del panel: quien, que, cuando, desde
 * donde y con que valores. Es la pieza que permite investigar un incidente.
 */
final class Audit
{
    public const TABLE = 'audit_logs';

    /**
     * @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after
     */
    public static function record(
        string $action,
        string $entityType = '',
        ?int $entityId = null,
        ?array $before = null,
        ?array $after = null,
        ?Request $request = null,
        ?int $userId = null
    ): void {
        try {
            $userId ??= Auth::id();

            QueryBuilder::table(self::TABLE)->insert([
                'user_id' => $userId,
                'action' => mb_substr($action, 0, 100),
                'entity_type' => mb_substr($entityType, 0, 80),
                'entity_id' => $entityId,
                'changes_before' => $before === null ? null : self::encode($before),
                'changes_after' => $after === null ? null : self::encode($after),
                'ip_address' => $request?->ip() ?? '',
                'user_agent' => $request?->userAgent() ?? '',
                'created_at' => Clock::nowUtc(),
            ]);
        } catch (\Throwable $e) {
            // La auditoria nunca debe tumbar la operacion principal.
            Logger::error('No se pudo registrar la auditoria', ['error' => $e->getMessage(), 'action' => $action]);
        }
    }

    /** @param array<string,mixed> $data */
    private static function encode(array $data): string
    {
        $safe = Logger::redact($data);
        $json = json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return $json === false ? '{}' : mb_substr($json, 0, 8000);
    }

    /**
     * Diferencia entre dos estados: solo guarda lo que realmente cambio.
     *
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     * @return array{0:array<string,mixed>,1:array<string,mixed>}
     */
    public static function diff(array $before, array $after): array
    {
        $changedBefore = [];
        $changedAfter = [];

        foreach ($after as $key => $value) {
            $old = $before[$key] ?? null;

            if ((string) $old !== (string) $value) {
                $changedBefore[$key] = $old;
                $changedAfter[$key] = $value;
            }
        }

        return [$changedBefore, $changedAfter];
    }
}
