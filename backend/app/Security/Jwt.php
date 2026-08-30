<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Config;

/**
 * JWT HS256 minimo para la app movil.
 *
 * Decisiones de seguridad:
 *  - solo se acepta el algoritmo HS256 (bloquea el ataque "alg: none");
 *  - la firma se compara con hash_equals (tiempo constante);
 *  - vida corta del token de acceso (15 min) + refresh rotativo en base de datos;
 *  - el token lleva "jti" para poder revocarlo.
 */
final class Jwt
{
    public static function issue(array $claims, int $ttlSeconds): string
    {
        $now = time();

        $payload = array_merge([
            'iss' => (string) Config::get('app.url', 'estilo'),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttlSeconds,
            'jti' => bin2hex(random_bytes(16)),
        ], $claims);

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $segments = [
            self::base64UrlEncode(self::json($header)),
            self::base64UrlEncode(self::json($payload)),
        ];

        $signing = implode('.', $segments);
        $signature = hash_hmac('sha256', $signing, self::secret(), true);
        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * @return array<string,mixed>|null Claims si el token es valido, null si no.
     */
    public static function verify(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $header = json_decode((string) self::base64UrlDecode($headerB64), true);

        if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256' || ($header['typ'] ?? 'JWT') !== 'JWT') {
            return null;
        }

        $expected = hash_hmac('sha256', $headerB64 . '.' . $payloadB64, self::secret(), true);
        $provided = self::base64UrlDecode($signatureB64);

        if ($provided === false || !hash_equals($expected, $provided)) {
            return null;
        }

        $payload = json_decode((string) self::base64UrlDecode($payloadB64), true);

        if (!is_array($payload)) {
            return null;
        }

        $now = time();
        $leeway = 30; // tolerancia por desfase de reloj

        if (isset($payload['exp']) && $now > ((int) $payload['exp'] + $leeway)) {
            return null;
        }

        if (isset($payload['nbf']) && $now < ((int) $payload['nbf'] - $leeway)) {
            return null;
        }

        return $payload;
    }

    private static function secret(): string
    {
        $secret = (string) Config::get('security.jwt.secret', '');

        if ($secret === '') {
            $secret = (string) Config::get('app.key', '');
        }

        if ($secret === '') {
            throw new \RuntimeException('No hay secreto para firmar tokens (JWT_SECRET o APP_KEY).');
        }

        return hash('sha256', 'estilo-jwt|' . $secret, true);
    }

    private static function json(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new \RuntimeException('No se pudo serializar el token.');
        }

        return $json;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string|false
    {
        return base64_decode(strtr($data, '-_', '+/'), true);
    }
}
