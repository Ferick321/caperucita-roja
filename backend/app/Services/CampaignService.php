<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\QueryBuilder;
use App\Core\Url;
use App\Security\Audit;

/**
 * Campanas de publicidad hacia los clientes registrados.
 *
 * Solo se contacta a quien dio consentimiento explicito y por el canal que
 * autorizo. Cada mensaje lleva enlace de baja: sin eso, la campana no sale.
 */
final class CampaignService
{
    /**
     * Calcula el publico objetivo y crea las filas de destinatarios.
     *
     * @return int numero de destinatarios
     */
    public static function buildAudience(int $campaignId): int
    {
        $campaign = QueryBuilder::table('campaigns')->where('id', $campaignId)->first();

        if ($campaign === null) {
            throw new HttpException(404, 'La campana no existe.');
        }

        if (!in_array((string) $campaign['status'], ['draft', 'scheduled'], true)) {
            throw new HttpException(422, 'Solo se puede recalcular el publico de una campana en borrador.');
        }

        $channel = (string) $campaign['channel'];
        $consentColumn = match ($channel) {
            'sms' => 'accepts_sms',
            'push' => 'accepts_push',
            'whatsapp' => 'accepts_whatsapp',
            default => 'accepts_email',
        };

        $query = QueryBuilder::table('users')
            ->select(['id', 'email', 'phone', 'first_name'])
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->where('role', 'client')
            ->where('accepts_marketing', 1)
            ->where($consentColumn, 1);

        // El canal exige un dato de contacto valido.
        if ($channel === 'email') {
            $query->where('email', '!=', '');
        } elseif (in_array($channel, ['sms', 'whatsapp'], true)) {
            $query->where('phone', '!=', '');
        }

        $inactiveDays = max(1, (int) $campaign['inactive_days']);

        switch ((string) $campaign['audience']) {
            case 'new_clients':
                $query->where('total_visits', '<=', 1);
                break;

            case 'inactive_clients':
                $cutoff = gmdate('Y-m-d H:i:s', time() - $inactiveDays * 86400);
                $query->whereGroup(static function (QueryBuilder $q) use ($cutoff): void {
                    $q->whereNull('last_visit_at')->orWhere('last_visit_at', '<', $cutoff);
                });
                break;

            case 'frequent_clients':
                $query->where('total_visits', '>=', 5);
                break;

            case 'birthday':
                $query->whereNotNull('birth_date');
                break;
        }

        $users = $query->limit(50000)->get();
        $now = Clock::nowUtc();
        $inserted = 0;

        Database::instance()->transaction(static function () use ($campaignId, $users, $channel, $campaign, $now, &$inserted): void {
            QueryBuilder::table('campaign_recipients')->where('campaign_id', $campaignId)->delete();

            $todayMonthDay = Clock::nowLocal()->format('m-d');

            foreach ($users as $user) {
                // El filtro de cumpleanos se aplica sobre la fecha local.
                if ((string) $campaign['audience'] === 'birthday') {
                    $birth = QueryBuilder::table('users')->where('id', (int) $user['id'])->value('birth_date');

                    if (!is_string($birth) || substr($birth, 5) !== $todayMonthDay) {
                        continue;
                    }
                }

                $destination = match ($channel) {
                    'sms', 'whatsapp' => (string) $user['phone'],
                    'push' => '',
                    default => (string) $user['email'],
                };

                if ($channel === 'push') {
                    $tokens = QueryBuilder::table('push_devices')
                        ->where('user_id', (int) $user['id'])
                        ->where('is_active', 1)
                        ->pluck('token');

                    foreach ($tokens as $token) {
                        QueryBuilder::table('campaign_recipients')->insert([
                            'campaign_id' => $campaignId,
                            'user_id' => (int) $user['id'],
                            'destination' => mb_substr((string) $token, 0, 190),
                            'status' => 'pending',
                            'tracking_token' => bin2hex(random_bytes(16)),
                            'created_at' => $now,
                        ]);
                        $inserted++;
                    }

                    continue;
                }

                if ($destination === '') {
                    continue;
                }

                QueryBuilder::table('campaign_recipients')->insert([
                    'campaign_id' => $campaignId,
                    'user_id' => (int) $user['id'],
                    'destination' => mb_substr($destination, 0, 190),
                    'status' => 'pending',
                    'tracking_token' => bin2hex(random_bytes(16)),
                    'created_at' => $now,
                ]);
                $inserted++;
            }

            QueryBuilder::table('campaigns')->where('id', $campaignId)->update([
                'total_recipients' => $inserted,
                'updated_at' => $now,
            ]);
        });

        return $inserted;
    }

    /** Pasa la campana a la cola de envio. */
    public static function dispatch(int $campaignId, ?int $actorId = null): int
    {
        $campaign = QueryBuilder::table('campaigns')->where('id', $campaignId)->first();

        if ($campaign === null) {
            throw new HttpException(404, 'La campana no existe.');
        }

        if (in_array((string) $campaign['status'], ['sending', 'sent'], true)) {
            throw new HttpException(422, 'Esta campana ya fue enviada.');
        }

        if ((int) $campaign['total_recipients'] === 0) {
            self::buildAudience($campaignId);
            $campaign = QueryBuilder::table('campaigns')->where('id', $campaignId)->first() ?? $campaign;
        }

        $recipients = QueryBuilder::table('campaign_recipients')
            ->where('campaign_id', $campaignId)
            ->where('status', 'pending')
            ->get();

        if ($recipients === []) {
            throw new HttpException(422, 'No hay destinatarios que cumplan los criterios de esta campana.');
        }

        $now = Clock::nowUtc();
        $channel = (string) $campaign['channel'];
        $queued = 0;

        foreach ($recipients as $recipient) {
            $body = self::renderBody($campaign, $recipient);

            NotificationService::queue(
                $channel,
                (string) $recipient['destination'],
                (string) $campaign['subject'],
                $body,
                $recipient['user_id'] === null ? null : (int) $recipient['user_id'],
                'campaign',
                $campaignId,
                'campana',
                $now,
                ['campaign_id' => $campaignId, 'tracking' => (string) $recipient['tracking_token']]
            );

            $queued++;
        }

        QueryBuilder::table('campaigns')->where('id', $campaignId)->update([
            'status' => 'sending',
            'started_at' => $now,
            'updated_at' => $now,
        ]);

        Audit::record('campana.enviada', 'campaign', $campaignId, null, ['destinatarios' => $queued], null, $actorId);
        Logger::info('Campana encolada', ['campaign' => $campaignId, 'recipients' => $queued]);

        return $queued;
    }

    /**
     * @param array<string,mixed> $campaign
     * @param array<string,mixed> $recipient
     */
    private static function renderBody(array $campaign, array $recipient): string
    {
        $name = 'cliente';

        if ($recipient['user_id'] !== null) {
            $name = (string) (QueryBuilder::table('users')
                ->where('id', (int) $recipient['user_id'])
                ->value('first_name') ?? 'cliente');
        }

        $unsubscribeUrl = Url::to('/baja/' . rawurlencode((string) $recipient['tracking_token']));

        $vars = [
            '{cliente}' => e($name),
            '{negocio}' => e(SettingsService::string('business.name', '')),
            '{url_sitio}' => e(Url::to('/')),
            '{url_app}' => e(SettingsService::string('app.download_url_android', Url::to('/app'))),
            '{url_baja}' => e($unsubscribeUrl),
        ];

        $body = strtr((string) $campaign['body'], $vars);

        $cta = '';
        if ((string) $campaign['cta_label'] !== '' && (string) $campaign['cta_url'] !== '') {
            $cta = '<p style="margin:24px 0"><a href="' . e_url((string) $campaign['cta_url'])
                . '" style="display:inline-block;padding:12px 24px;background:#c9a227;color:#111;'
                . 'border-radius:10px;text-decoration:none;font-weight:600">'
                . e((string) $campaign['cta_label']) . '</a></p>';
        }

        $image = '';
        if ((string) $campaign['image_path'] !== '') {
            $image = '<img src="' . e(media_url((string) $campaign['image_path']))
                . '" alt="" style="max-width:100%;border-radius:12px;margin-bottom:20px">';
        }

        // El enlace de baja es obligatorio en todo mensaje comercial.
        return '<!doctype html><html lang="es"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:24px;background:#f4f4f5;font-family:Segoe UI,Roboto,Arial,sans-serif">'
            . '<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:16px;padding:32px">'
            . $image
            . '<div style="font-size:15px;line-height:1.6;color:#1f2937">' . $body . '</div>'
            . $cta
            . '<hr style="border:none;border-top:1px solid #e5e7eb;margin:28px 0">'
            . '<p style="font-size:12px;color:#9ca3af">Recibes este mensaje porque aceptaste recibir novedades de '
            . $vars['{negocio}'] . '. '
            . '<a href="' . $vars['{url_baja}'] . '" style="color:#6b7280">Darme de baja</a></p>'
            . '</div></body></html>';
    }

    /** Baja voluntaria desde el enlace del correo. */
    public static function unsubscribe(string $trackingToken): bool
    {
        $recipient = QueryBuilder::table('campaign_recipients')
            ->where('tracking_token', $trackingToken)
            ->first();

        if ($recipient === null) {
            return false;
        }

        QueryBuilder::table('campaign_recipients')
            ->where('id', (int) $recipient['id'])
            ->update(['status' => 'unsubscribed']);

        if ($recipient['user_id'] !== null) {
            QueryBuilder::table('users')->where('id', (int) $recipient['user_id'])->update([
                'accepts_marketing' => 0,
                'updated_at' => Clock::nowUtc(),
            ]);
        }

        return true;
    }

    /** Marca la apertura del correo (pixel de seguimiento). */
    public static function trackOpen(string $trackingToken): void
    {
        $recipient = QueryBuilder::table('campaign_recipients')
            ->where('tracking_token', $trackingToken)
            ->first();

        if ($recipient === null || $recipient['opened_at'] !== null) {
            return;
        }

        QueryBuilder::table('campaign_recipients')->where('id', (int) $recipient['id'])->update([
            'status' => 'opened',
            'opened_at' => Clock::nowUtc(),
        ]);

        Database::instance()->statement(
            'UPDATE campaigns SET total_opened = total_opened + 1 WHERE id = :id',
            ['id' => (int) $recipient['campaign_id']]
        );
    }
}
