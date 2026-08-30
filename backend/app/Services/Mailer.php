<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;

/**
 * Envio de correo.
 *
 * Cliente SMTP propio (sin dependencias externas) con STARTTLS/SSL y
 * autenticacion. Las cabeceras se saneparan para impedir inyeccion de
 * cabeceras a traves del nombre o el asunto.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        $to = trim($to);

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            Logger::warning('Correo descartado: destinatario no valido', ['to' => $to]);

            return false;
        }

        $subject = self::sanitizeHeader($subject);
        $transport = (string) Config::get('mail.transport', 'log');

        if ($textBody === '') {
            $textBody = trim(html_entity_decode(strip_tags(
                preg_replace('/<br\s*\/?>/i', "\n", $htmlBody) ?? $htmlBody
            ), ENT_QUOTES, 'UTF-8'));
        }

        return match ($transport) {
            'smtp' => self::sendSmtp($to, $subject, $htmlBody, $textBody),
            'mail' => self::sendMailFunction($to, $subject, $htmlBody),
            default => self::logOnly($to, $subject, $textBody),
        };
    }

    private static function logOnly(string $to, string $subject, string $body): bool
    {
        Logger::info('Correo simulado (transporte "log")', [
            'to' => $to,
            'subject' => $subject,
            'preview' => mb_substr($body, 0, 300),
        ]);

        return true;
    }

    private static function sendMailFunction(string $to, string $subject, string $htmlBody): bool
    {
        $from = self::sanitizeHeader((string) Config::get('mail.from_address', 'no-reply@localhost'));
        $name = self::sanitizeHeader((string) Config::get('mail.from_name', 'Estilo'));

        $headers = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . self::encodeName($name) . ' <' . $from . '>',
            'Reply-To: ' . $from,
            'X-Mailer: PlataformaEstilo',
        ]);

        $sent = @mail($to, self::encodeSubject($subject), $htmlBody, $headers);

        if (!$sent) {
            Logger::error('Fallo el envio con mail()', ['to' => $to]);
        }

        return $sent;
    }

    private static function sendSmtp(string $to, string $subject, string $htmlBody, string $textBody): bool
    {
        $host = (string) Config::get('mail.host', '');
        $port = (int) Config::get('mail.port', 587);
        $encryption = strtolower((string) Config::get('mail.encryption', 'tls'));
        $timeout = (int) Config::get('mail.timeout', 15);
        $username = (string) Config::get('mail.username', '');
        $password = (string) Config::get('mail.password', '');
        $from = self::sanitizeHeader((string) Config::get('mail.from_address', 'no-reply@localhost'));
        $fromName = self::sanitizeHeader((string) Config::get('mail.from_name', 'Estilo'));

        if ($host === '') {
            Logger::error('SMTP sin host configurado');

            return false;
        }

        $endpoint = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;

        // El certificado del servidor SIEMPRE se verifica.
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'SNI_enabled' => true,
            ],
        ]);

        $socket = @stream_socket_client(
            $endpoint,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($socket === false) {
            Logger::error('No se pudo conectar al servidor SMTP', ['error' => $errstr, 'code' => $errno]);

            return false;
        }

        stream_set_timeout($socket, $timeout);

        try {
            self::expect($socket, 220);

            $hostname = (string) (parse_url((string) Config::get('app.url', ''), PHP_URL_HOST) ?: 'localhost');
            self::command($socket, 'EHLO ' . $hostname, 250);

            if ($encryption === 'tls') {
                self::command($socket, 'STARTTLS', 220);

                $crypto = stream_socket_enable_crypto(
                    $socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT
                );

                if ($crypto !== true) {
                    throw new \RuntimeException('No se pudo establecer el cifrado TLS.');
                }

                self::command($socket, 'EHLO ' . $hostname, 250);
            }

            if ($username !== '') {
                self::command($socket, 'AUTH LOGIN', 334);
                self::command($socket, base64_encode($username), 334);
                self::command($socket, base64_encode($password), 235);
            }

            self::command($socket, 'MAIL FROM:<' . $from . '>', 250);
            self::command($socket, 'RCPT TO:<' . $to . '>', 250);
            self::command($socket, 'DATA', 354);

            $boundary = 'bnd_' . bin2hex(random_bytes(12));

            $message = implode("\r\n", [
                'Date: ' . gmdate('r'),
                'From: ' . self::encodeName($fromName) . ' <' . $from . '>',
                'To: <' . $to . '>',
                'Subject: ' . self::encodeSubject($subject),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $hostname . '>',
                'MIME-Version: 1.0',
                'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
                '',
                '--' . $boundary,
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                '',
                chunk_split(base64_encode($textBody)),
                '--' . $boundary,
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                '',
                chunk_split(base64_encode($htmlBody)),
                '--' . $boundary . '--',
                '',
                '.',
            ]);

            fwrite($socket, $message . "\r\n");
            self::expect($socket, 250);
            self::command($socket, 'QUIT', 221);

            return true;
        } catch (\Throwable $e) {
            Logger::error('Error en el envio SMTP', ['error' => $e->getMessage(), 'to' => $to]);

            return false;
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
    }

    /** @param resource $socket */
    private static function command($socket, string $command, int $expectedCode): void
    {
        fwrite($socket, $command . "\r\n");
        self::expect($socket, $expectedCode);
    }

    /** @param resource $socket */
    private static function expect($socket, int $expectedCode): string
    {
        $response = '';

        while (($line = fgets($socket, 1024)) !== false) {
            $response .= $line;

            // Las respuestas multilinea usan "250-"; la ultima usa "250 ".
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }

        $code = (int) substr(trim($response), 0, 3);

        if ($code !== $expectedCode) {
            throw new \RuntimeException("SMTP respondio {$code}, se esperaba {$expectedCode}: " . trim($response));
        }

        return $response;
    }

    /** Elimina saltos de linea: bloquea la inyeccion de cabeceras. */
    public static function sanitizeHeader(string $value): string
    {
        return trim(str_replace(["\r", "\n", "\0", '%0a', '%0d'], '', $value));
    }

    private static function encodeSubject(string $subject): string
    {
        return mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");
    }

    private static function encodeName(string $name): string
    {
        return '=?UTF-8?B?' . base64_encode($name) . '?=';
    }
}
