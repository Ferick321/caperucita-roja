<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Config;
use App\Core\Logger;
use App\Core\QueryBuilder;

/**
 * Notificaciones push a la app movil (Firebase Cloud Messaging).
 *
 * La clave del servidor se configura desde el panel. Si no hay clave, el
 * mensaje se registra y la cola lo da por entregado, de modo que el resto
 * del sistema sigue funcionando mientras se completa la configuracion.
 */
final class PushService
{
    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send';

    /** @param array<string,mixed> $data */
    public static function send(string $token, string $title, string $body, array $data = []): bool
    {
        $serverKey = SettingsService::string('push.fcm_server_key', '');

        if ($serverKey === '' || $token === '') {
            Logger::info('Push sin proveedor configurado; se registra localmente', [
                'title' => $title,
            ]);

            return true;
        }

        $payload = [
            'to' => $token,
            'priority' => 'high',
            'notification' => [
                'title' => mb_substr($title, 0, 120),
                'body' => mb_substr($body, 0, 300),
                'sound' => 'default',
                'android_channel_id' => 'citas',
            ],
            'data' => $data,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return false;
        }

        $ch = curl_init(self::ENDPOINT);

        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 5,
            // La verificacion del certificado nunca se desactiva.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Authorization: key=' . $serverKey,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $status >= 400) {
            Logger::warning('Fallo el envio push', ['status' => $status, 'error' => $error]);

            // Un token invalido se desactiva para no reintentar en vano.
            if (is_string($response) && str_contains($response, 'NotRegistered')) {
                QueryBuilder::table('push_devices')->where('token', $token)->update(['is_active' => 0]);
            }

            return false;
        }

        return true;
    }

    /** Registra o actualiza el dispositivo del cliente. */
    public static function registerDevice(
        int $userId,
        string $token,
        string $platform = 'android',
        string $deviceName = '',
        string $appVersion = ''
    ): void {
        if ($token === '') {
            return;
        }

        $now = Clock::nowUtc();
        $existing = QueryBuilder::table('push_devices')->where('token', $token)->first();

        if ($existing !== null) {
            QueryBuilder::table('push_devices')->where('id', (int) $existing['id'])->update([
                'user_id' => $userId,
                'is_active' => 1,
                'app_version' => mb_substr($appVersion, 0, 20),
                'last_seen_at' => $now,
            ]);

            return;
        }

        QueryBuilder::table('push_devices')->insert([
            'user_id' => $userId,
            'token' => mb_substr($token, 0, 255),
            'platform' => in_array($platform, ['android', 'ios', 'web'], true) ? $platform : 'android',
            'device_name' => mb_substr($deviceName, 0, 120),
            'app_version' => mb_substr($appVersion, 0, 20),
            'is_active' => 1,
            'last_seen_at' => $now,
            'created_at' => $now,
        ]);
    }
}
