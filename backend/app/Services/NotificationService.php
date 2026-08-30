<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Logger;
use App\Core\QueryBuilder;
use App\Core\Url;

/**
 * Notificaciones transaccionales.
 *
 * Nada se envia en linea durante la peticion del usuario: todo entra en una
 * cola que procesa la tarea programada, de modo que un servidor de correo
 * lento nunca ralentiza el agendamiento.
 *
 * Las plantillas son editables desde el panel.
 */
final class NotificationService
{
    /** @param array<string,mixed> $appointment */
    public static function onAppointmentCreated(array $appointment): void
    {
        if (!SettingsService::bool('notifications.confirm_email', true)) {
            return;
        }

        $vars = self::appointmentVars($appointment);
        $key = (string) $appointment['status'] === 'confirmed' ? 'cita_confirmada' : 'cita_recibida';

        self::queueForAppointment($appointment, $key, $vars);

        // Recordatorio programado con la antelacion configurada.
        if (SettingsService::bool('notifications.reminder_enabled', true)) {
            $hoursBefore = SettingsService::int('notifications.reminder_hours_before', 24);
            $remindAt = strtotime((string) $appointment['starts_at']) - $hoursBefore * 3600;

            if ($remindAt > time()) {
                self::queueForAppointment(
                    $appointment,
                    'recordatorio_cita',
                    $vars,
                    gmdate('Y-m-d H:i:s', $remindAt)
                );
            }
        }

        self::notifyStaff($appointment, 'Nueva cita ' . (string) $appointment['code']);
    }

    /** @param array<string,mixed> $appointment */
    public static function onAppointmentStatusChanged(array $appointment, string $previousStatus): void
    {
        $status = (string) $appointment['status'];

        $key = match ($status) {
            'confirmed' => 'cita_confirmada',
            'cancelled' => 'cita_cancelada',
            'completed' => 'cita_completada',
            default => '',
        };

        if ($key === '') {
            return;
        }

        self::queueForAppointment($appointment, $key, self::appointmentVars($appointment));

        if ($status === 'cancelled') {
            // Se anulan los recordatorios pendientes de esa cita.
            QueryBuilder::table('notification_queue')
                ->where('related_type', 'appointment')
                ->where('related_id', (int) $appointment['id'])
                ->where('status', 'pending')
                ->where('template_key', 'recordatorio_cita')
                ->update(['status' => 'cancelled']);
        }

        if ($status === 'completed' && SettingsService::bool('notifications.review_request_enabled', true)) {
            $delay = SettingsService::int('notifications.review_request_hours_after', 3) * 3600;

            self::queueForAppointment(
                $appointment,
                'solicitar_resena',
                self::appointmentVars($appointment),
                gmdate('Y-m-d H:i:s', time() + $delay)
            );
        }
    }

    /** @param array<string,mixed> $appointment */
    public static function onAppointmentRescheduled(array $appointment): void
    {
        self::queueForAppointment($appointment, 'cita_reprogramada', self::appointmentVars($appointment));
    }

    public static function onProofUploaded(int $paymentId): void
    {
        $payment = QueryBuilder::table('payments')->where('id', $paymentId)->first();

        if ($payment === null) {
            return;
        }

        $recipients = QueryBuilder::table('users')
            ->select(['email', 'first_name'])
            ->whereIn('role', ['super_admin', 'admin', 'manager'])
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->get();

        foreach ($recipients as $recipient) {
            if ((string) $recipient['email'] === '') {
                continue;
            }

            self::queue(
                'email',
                (string) $recipient['email'],
                'Nuevo comprobante de pago por verificar',
                '<p>Se recibio un comprobante de pago por '
                . e(money((float) $payment['amount']))
                . '. Revisalo en el panel: '
                . '<a href="' . e(Url::to('/panel/pagos')) . '">Ver pagos pendientes</a></p>',
                null,
                'payment',
                $paymentId
            );
        }
    }

    /**
     * @param array<string,mixed> $appointment
     * @param array<string,mixed> $payment
     */
    public static function onPaymentApproved(array $appointment, array $payment): void
    {
        $vars = self::appointmentVars($appointment);
        $vars['{importe}'] = money((float) $payment['amount']);

        self::queueForAppointment($appointment, 'pago_aprobado', $vars);
    }

    public static function onPaymentRejected(int $paymentId, string $reason): void
    {
        $payment = QueryBuilder::table('payments')->where('id', $paymentId)->first();

        if ($payment === null || $payment['appointment_id'] === null) {
            return;
        }

        $appointment = QueryBuilder::table('appointments')->where('id', (int) $payment['appointment_id'])->first();

        if ($appointment === null) {
            return;
        }

        $vars = self::appointmentVars($appointment);
        $vars['{motivo}'] = $reason;

        self::queueForAppointment($appointment, 'pago_rechazado', $vars);
    }

    /** @param array<string,mixed> $user */
    public static function onClientRegistered(array $user): void
    {
        $vars = [
            '{cliente}' => (string) $user['first_name'],
            '{negocio}' => SettingsService::string('business.name', 'Nuestro salon'),
            '{url_app}' => SettingsService::string('app.download_url_android', Url::to('/app')),
            '{url_sitio}' => Url::to('/'),
        ];

        self::queue(
            'email',
            (string) $user['email'],
            self::renderTemplateSubject('bienvenida', $vars, 'Bienvenido a ' . $vars['{negocio}']),
            self::renderTemplateBody('bienvenida', $vars, self::defaultWelcomeBody()),
            (int) $user['id'],
            'user',
            (int) $user['id'],
            'bienvenida'
        );

        if (SettingsService::bool('loyalty.enabled', true)) {
            LoyaltyService::grant(
                (int) $user['id'],
                SettingsService::int('loyalty.welcome_points', 50),
                'Puntos de bienvenida'
            );
        }
    }

    /**
     * Encola un mensaje.
     *
     * @param array<string,mixed> $payload
     */
    public static function queue(
        string $channel,
        string $destination,
        string $subject,
        string $body,
        ?int $userId = null,
        string $relatedType = '',
        ?int $relatedId = null,
        string $templateKey = '',
        ?string $scheduledAt = null,
        array $payload = []
    ): void {
        $destination = trim($destination);

        if ($destination === '') {
            return;
        }

        try {
            QueryBuilder::table('notification_queue')->insert([
                'channel' => in_array($channel, ['email', 'sms', 'push', 'whatsapp'], true) ? $channel : 'email',
                'destination' => mb_substr($destination, 0, 190),
                'user_id' => $userId,
                'subject' => mb_substr($subject, 0, 200),
                'body' => $body,
                'payload_json' => $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE),
                'template_key' => mb_substr($templateKey, 0, 60),
                'related_type' => mb_substr($relatedType, 0, 40),
                'related_id' => $relatedId,
                'status' => 'pending',
                'scheduled_at' => $scheduledAt ?? Clock::nowUtc(),
                'created_at' => Clock::nowUtc(),
            ]);
        } catch (\Throwable $e) {
            Logger::error('No se pudo encolar la notificacion', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param array<string,mixed> $appointment
     * @param array<string,string> $vars
     */
    private static function queueForAppointment(
        array $appointment,
        string $templateKey,
        array $vars,
        ?string $scheduledAt = null
    ): void {
        $email = (string) ($appointment['client_email'] ?? '');
        $userId = $appointment['client_id'] === null ? null : (int) $appointment['client_id'];

        if ($email !== '') {
            self::queue(
                'email',
                $email,
                self::renderTemplateSubject($templateKey, $vars, self::defaultSubject($templateKey, $vars)),
                self::renderTemplateBody($templateKey, $vars, self::defaultBody($templateKey, $vars)),
                $userId,
                'appointment',
                (int) $appointment['id'],
                $templateKey,
                $scheduledAt
            );
        }

        // Notificacion push a los dispositivos del cliente.
        if ($userId !== null) {
            $tokens = QueryBuilder::table('push_devices')
                ->select(['token'])
                ->where('user_id', $userId)
                ->where('is_active', 1)
                ->pluck('token');

            foreach ($tokens as $token) {
                self::queue(
                    'push',
                    (string) $token,
                    self::defaultSubject($templateKey, $vars),
                    strip_tags(self::defaultBody($templateKey, $vars)),
                    $userId,
                    'appointment',
                    (int) $appointment['id'],
                    $templateKey,
                    $scheduledAt,
                    ['appointment_code' => (string) $appointment['code'], 'type' => $templateKey]
                );
            }
        }
    }

    /** @param array<string,mixed> $appointment */
    private static function notifyStaff(array $appointment, string $subject): void
    {
        $staffId = $appointment['staff_id'] ?? null;

        if ($staffId === null) {
            return;
        }

        $email = QueryBuilder::table('staff')->where('id', (int) $staffId)->value('email');

        if (!is_string($email) || $email === '') {
            return;
        }

        self::queue(
            'email',
            $email,
            $subject,
            '<p>Tienes una nueva cita el <strong>'
            . e(local_datetime((string) $appointment['starts_at']))
            . '</strong> con ' . e((string) $appointment['client_name']) . '.</p>'
            . '<p><a href="' . e(Url::to('/panel/citas')) . '">Ver en la agenda</a></p>',
            null,
            'appointment',
            (int) $appointment['id']
        );
    }

    /** @return array<string,string> */
    public static function appointmentVars(array $appointment): array
    {
        $services = QueryBuilder::table('appointment_services')
            ->select(['service_name'])
            ->where('appointment_id', (int) $appointment['id'])
            ->pluck('service_name');

        $staffName = '';

        if (($appointment['staff_id'] ?? null) !== null) {
            $staffName = (string) (QueryBuilder::table('staff')
                ->where('id', (int) $appointment['staff_id'])
                ->value('display_name') ?? '');
        }

        return [
            '{cliente}' => (string) $appointment['client_name'],
            '{codigo}' => (string) $appointment['code'],
            '{fecha}' => local_datetime((string) $appointment['starts_at'], 'd/m/Y'),
            '{hora}' => local_datetime((string) $appointment['starts_at'], 'H:i'),
            '{fecha_hora}' => local_datetime((string) $appointment['starts_at']),
            '{profesional}' => $staffName !== '' ? $staffName : 'nuestro equipo',
            '{servicios}' => implode(', ', array_map('strval', $services)),
            '{total}' => money((float) $appointment['total']),
            '{negocio}' => SettingsService::string('business.name', 'Nuestro salon'),
            '{telefono}' => SettingsService::string('business.phone', ''),
            '{direccion}' => SettingsService::string('business.address', ''),
            '{url_sitio}' => Url::to('/'),
            '{url_cita}' => Url::to('/mis-citas'),
        ];
    }

    /** @param array<string,string> $vars */
    private static function renderTemplateSubject(string $key, array $vars, string $fallback): string
    {
        $template = QueryBuilder::table('notification_templates')
            ->where('template_key', $key)
            ->where('channel', 'email')
            ->where('is_active', 1)
            ->first();

        $subject = $template === null ? $fallback : (string) $template['subject'];

        return strtr($subject, $vars);
    }

    /** @param array<string,string> $vars */
    private static function renderTemplateBody(string $key, array $vars, string $fallback): string
    {
        $template = QueryBuilder::table('notification_templates')
            ->where('template_key', $key)
            ->where('channel', 'email')
            ->where('is_active', 1)
            ->first();

        $body = $template === null ? $fallback : (string) $template['body'];

        // Las variables se escapan antes de sustituirse: una plantilla no puede
        // inyectar HTML a traves de los datos del cliente.
        $safe = [];
        foreach ($vars as $name => $value) {
            $safe[$name] = e($value);
        }

        return strtr($body, $safe);
    }

    /** @param array<string,string> $vars */
    private static function defaultSubject(string $key, array $vars): string
    {
        return strtr(match ($key) {
            'cita_recibida' => 'Recibimos tu solicitud de cita {codigo}',
            'cita_confirmada' => 'Tu cita {codigo} esta confirmada',
            'cita_cancelada' => 'Tu cita {codigo} fue cancelada',
            'cita_completada' => 'Gracias por tu visita a {negocio}',
            'cita_reprogramada' => 'Tu cita {codigo} cambio de horario',
            'recordatorio_cita' => 'Recordatorio: tu cita es el {fecha} a las {hora}',
            'solicitar_resena' => 'Como estuvo tu visita a {negocio}?',
            'pago_aprobado' => 'Confirmamos tu pago de la cita {codigo}',
            'pago_rechazado' => 'Necesitamos revisar el pago de tu cita {codigo}',
            default => 'Notificacion de {negocio}',
        }, $vars);
    }

    /** @param array<string,string> $vars */
    private static function defaultBody(string $key, array $vars): string
    {
        $intro = match ($key) {
            'cita_recibida' => 'Recibimos tu solicitud. Te avisaremos en cuanto quede confirmada.',
            'cita_confirmada' => 'Tu cita quedo confirmada. Te esperamos!',
            'cita_cancelada' => 'Tu cita fue cancelada. Puedes agendar una nueva cuando quieras.',
            'cita_completada' => 'Gracias por visitarnos. Esperamos verte pronto.',
            'cita_reprogramada' => 'Tu cita cambio de horario. Estos son los nuevos datos:',
            'recordatorio_cita' => 'Te recordamos tu proxima cita:',
            'solicitar_resena' => 'Nos encantaria conocer tu opinion sobre tu ultima visita.',
            'pago_aprobado' => 'Verificamos tu comprobante y tu pago quedo registrado.',
            'pago_rechazado' => 'No pudimos validar tu comprobante. Motivo: {motivo}',
            default => '',
        };

        return self::wrapHtml(
            '<p>Hola <strong>{cliente}</strong>,</p>'
            . '<p>' . $intro . '</p>'
            . '<table role="presentation" style="margin:18px 0;font-size:15px">'
            . '<tr><td style="padding:4px 12px 4px 0;color:#6b7280">Codigo</td><td><strong>{codigo}</strong></td></tr>'
            . '<tr><td style="padding:4px 12px 4px 0;color:#6b7280">Fecha</td><td>{fecha_hora}</td></tr>'
            . '<tr><td style="padding:4px 12px 4px 0;color:#6b7280">Servicios</td><td>{servicios}</td></tr>'
            . '<tr><td style="padding:4px 12px 4px 0;color:#6b7280">Profesional</td><td>{profesional}</td></tr>'
            . '<tr><td style="padding:4px 12px 4px 0;color:#6b7280">Total</td><td>{total}</td></tr>'
            . '</table>'
            . '<p><a href="{url_cita}" style="display:inline-block;padding:12px 22px;background:#c9a227;'
            . 'color:#111;border-radius:10px;text-decoration:none;font-weight:600">Ver mi cita</a></p>'
        );
    }

    private static function defaultWelcomeBody(): string
    {
        return self::wrapHtml(
            '<p>Hola <strong>{cliente}</strong>,</p>'
            . '<p>Gracias por registrarte en <strong>{negocio}</strong>. '
            . 'Desde ahora puedes agendar tus citas en segundos.</p>'
            . '<p><a href="{url_app}" style="display:inline-block;padding:12px 22px;background:#c9a227;'
            . 'color:#111;border-radius:10px;text-decoration:none;font-weight:600">Descargar la app</a></p>'
            . '<p style="color:#6b7280;font-size:13px">Tambien puedes reservar desde <a href="{url_sitio}">nuestra web</a>.</p>'
        );
    }

    private static function wrapHtml(string $content): string
    {
        $business = e(SettingsService::string('business.name', 'Estilo'));

        return '<!doctype html><html lang="es"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:24px;background:#f4f4f5;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif">'
            . '<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:16px;padding:32px;'
            . 'box-shadow:0 2px 12px rgba(0,0,0,.06)">'
            . '<h1 style="margin:0 0 20px;font-size:20px;color:#111827">' . $business . '</h1>'
            . $content
            . '<hr style="border:none;border-top:1px solid #e5e7eb;margin:28px 0">'
            . '<p style="font-size:12px;color:#9ca3af;margin:0">'
            . 'Este mensaje se envio automaticamente, por favor no respondas a este correo.</p>'
            . '</div></body></html>';
    }
}
