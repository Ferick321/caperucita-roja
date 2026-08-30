<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Config;
use App\Core\Logger;

/**
 * Cifrado autenticado con libsodium (XChaCha20-Poly1305).
 *
 * Se usa para los datos bancarios que el duenio del negocio guarda en el
 * panel: aunque alguien lea la base de datos, sin APP_KEY no obtiene el
 * numero de cuenta.
 */
final class Crypto
{
    private const PREFIX = 'enc.v1:';

    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }

        $key = self::key();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);

        sodium_memzero($key);

        return self::PREFIX . base64_encode($nonce . $cipher);
    }

    public static function decrypt(string $payload): string
    {
        if ($payload === '') {
            return '';
        }

        // Compatibilidad: un valor guardado sin cifrar se devuelve tal cual.
        if (!str_starts_with($payload, self::PREFIX)) {
            return $payload;
        }

        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);

        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            Logger::error('Payload cifrado con formato invalido');

            return '';
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $key = self::key();

        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
        sodium_memzero($key);

        if ($plain === false) {
            Logger::error('Fallo al descifrar: clave incorrecta o dato manipulado');

            return '';
        }

        return $plain;
    }

    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    /** Enmascara un dato sensible para mostrarlo en pantalla (****1234). */
    public static function mask(string $value, int $visible = 4): string
    {
        $length = mb_strlen($value);

        if ($length <= $visible) {
            return str_repeat('*', max(0, $length));
        }

        return str_repeat('*', $length - $visible) . mb_substr($value, -$visible);
    }

    private static function key(): string
    {
        $appKey = (string) Config::get('app.key', '');

        if ($appKey === '') {
            throw new \RuntimeException('APP_KEY no configurada: ejecuta "php cli/console.php key:generate".');
        }

        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);

            if ($decoded !== false && strlen($decoded) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                return $decoded;
            }
        }

        // Deriva una clave de 32 bytes a partir de cualquier cadena.
        return hash('sha256', 'estilo-crypto|' . $appKey, true);
    }

    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    }
}
