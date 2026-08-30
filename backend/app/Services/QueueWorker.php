<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Database;
use App\Core\Logger;
use App\Core\QueryBuilder;

/**
 * Procesador de la cola de notificaciones.
 *
 * Lo invoca la tarea programada (cron). Marca cada mensaje como "sending"
 * antes de enviarlo para que dos ejecuciones simultaneas no dupliquen envios.
 */
final class QueueWorker
{
    /** @return array{processed:int,sent:int,failed:int} */
    public static function process(int $limit = 50): array
    {
        $pending = QueryBuilder::table('notification_queue')
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', Clock::nowUtc())
            ->orderBy('scheduled_at')
            ->limit(max(1, min(500, $limit)))
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($pending as $message) {
            $id = (int) $message['id'];

            // Reserva atomica: solo un proceso se queda con el mensaje.
            $claimed = Database::instance()->statement(
                "UPDATE notification_queue
                    SET status = 'sending', attempts = attempts + 1
                  WHERE id = :id AND status = 'pending'",
                ['id' => $id]
            );

            if ($claimed === 0) {
                continue;
            }

            try {
                $ok = self::deliver($message);
            } catch (\Throwable $e) {
                Logger::error('Fallo al entregar la notificacion', ['id' => $id, 'error' => $e->getMessage()]);
                $ok = false;
            }

            if ($ok) {
                QueryBuilder::table('notification_queue')->where('id', $id)->update([
                    'status' => 'sent',
                    'sent_at' => Clock::nowUtc(),
                ]);

                self::afterSent($message);
                $sent++;

                continue;
            }

            $attempts = (int) $message['attempts'] + 1;
            $maxAttempts = (int) $message['max_attempts'];

            QueryBuilder::table('notification_queue')->where('id', $id)->update([
                'status' => $attempts >= $maxAttempts ? 'failed' : 'pending',
                // Reintento con espera creciente.
                'scheduled_at' => gmdate('Y-m-d H:i:s', time() + (60 * (2 ** min($attempts, 5)))),
                'last_error' => 'No se pudo entregar el mensaje.',
            ]);

            $failed++;
        }

        self::updateCampaignProgress();

        return ['processed' => count($pending), 'sent' => $sent, 'failed' => $failed];
    }

    /** @param array<string,mixed> $message */
    private static function deliver(array $message): bool
    {
        $channel = (string) $message['channel'];

        return match ($channel) {
            'email' => Mailer::send(
                (string) $message['destination'],
                (string) $message['subject'],
                (string) $message['body']
            ),
            'push' => PushService::send(
                (string) $message['destination'],
                (string) $message['subject'],
                strip_tags((string) $message['body']),
                json_decode((string) ($message['payload_json'] ?? '{}'), true) ?: []
            ),
            // SMS y WhatsApp se registran hasta que el negocio configure un
            // proveedor; el panel muestra el mensaje como pendiente de canal.
            default => self::logUnsupported($message),
        };
    }

    /** @param array<string,mixed> $message */
    private static function logUnsupported(array $message): bool
    {
        Logger::info('Canal sin proveedor configurado; mensaje registrado', [
            'channel' => (string) $message['channel'],
            'destination' => (string) $message['destination'],
        ]);

        return true;
    }

    /** @param array<string,mixed> $message */
    private static function afterSent(array $message): void
    {
        if ((string) $message['related_type'] !== 'campaign') {
            return;
        }

        $payload = json_decode((string) ($message['payload_json'] ?? '{}'), true);
        $token = is_array($payload) ? (string) ($payload['tracking'] ?? '') : '';

        if ($token === '') {
            return;
        }

        QueryBuilder::table('campaign_recipients')
            ->where('tracking_token', $token)
            ->update(['status' => 'sent', 'sent_at' => Clock::nowUtc()]);

        Database::instance()->statement(
            'UPDATE campaigns SET total_sent = total_sent + 1 WHERE id = :id',
            ['id' => (int) $message['related_id']]
        );
    }

    /** Cierra las campanas cuyos mensajes ya salieron todos. */
    private static function updateCampaignProgress(): void
    {
        $sending = QueryBuilder::table('campaigns')->where('status', 'sending')->pluck('id');

        foreach ($sending as $campaignId) {
            $pending = QueryBuilder::table('notification_queue')
                ->where('related_type', 'campaign')
                ->where('related_id', (int) $campaignId)
                ->whereIn('status', ['pending', 'sending'])
                ->count();

            if ($pending === 0) {
                QueryBuilder::table('campaigns')->where('id', (int) $campaignId)->update([
                    'status' => 'sent',
                    'finished_at' => Clock::nowUtc(),
                    'updated_at' => Clock::nowUtc(),
                ]);
            }
        }
    }

    /** Lanza las campanas programadas cuya hora ya llego. */
    public static function releaseScheduledCampaigns(): int
    {
        $due = QueryBuilder::table('campaigns')
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', Clock::nowUtc())
            ->pluck('id');

        $released = 0;

        foreach ($due as $campaignId) {
            try {
                CampaignService::dispatch((int) $campaignId);
                $released++;
            } catch (\Throwable $e) {
                Logger::error('No se pudo lanzar la campana programada', [
                    'campaign' => (int) $campaignId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $released;
    }

    /** Marca como "no asistio" las citas confirmadas que ya pasaron. */
    public static function markNoShows(int $graceMinutes = 60): int
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - $graceMinutes * 60);

        $stale = QueryBuilder::table('appointments')
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('ends_at', '<', $cutoff)
            ->whereNull('deleted_at')
            ->limit(200)
            ->pluck('id');

        foreach ($stale as $appointmentId) {
            try {
                BookingService::changeStatus((int) $appointmentId, 'no_show', null, 'Marcada automaticamente');
            } catch (\Throwable $e) {
                Logger::warning('No se pudo marcar la ausencia', [
                    'appointment' => (int) $appointmentId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return count($stale);
    }
}
