<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Config;

/**
 * Hashing de contrasenas.
 *
 * Argon2id (o bcrypt si el binario de PHP no lo trae) + "pepper" secreto
 * guardado fuera de la base de datos: si alguien roba un volcado de la tabla
 * de usuarios, los hashes siguen siendo inutiles sin el pepper del .env.
 */
final class Hash
{
    public static function make(string $password): string
    {
        $hash = password_hash(self::pepper($password), self::algorithm(), self::options());

        if ($hash === false) {
            throw new \RuntimeException('No se pudo generar el hash de la contrasena.');
        }

        return $hash;
    }

    public static function verify(string $password, string $hash): bool
    {
        if ($hash === '') {
            // Se compara igualmente contra un hash ficticio para que el tiempo de
            // respuesta no revele si el usuario existe.
            password_verify(self::pepper($password), '$2y$12$usuarioinexistenteusuarioinexistenteusuarioinexiste');

            return false;
        }

        return password_verify(self::pepper($password), $hash);
    }

    /** Indica si conviene rehashear (por cambio de coste o de algoritmo). */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::algorithm(), self::options());
    }

    private static function algorithm(): string
    {
        if (defined('PASSWORD_ARGON2ID') && in_array('argon2id', password_algos(), true)) {
            return PASSWORD_ARGON2ID;
        }

        return PASSWORD_BCRYPT;
    }

    /** @return array<string,int> */
    private static function options(): array
    {
        if (self::algorithm() === PASSWORD_BCRYPT) {
            return ['cost' => (int) Config::get('security.password.bcrypt_cost', 12)];
        }

        return [
            'memory_cost' => (int) Config::get('security.password.argon_memory', 65536),
            'time_cost' => (int) Config::get('security.password.argon_time', 4),
            'threads' => (int) Config::get('security.password.argon_threads', 2),
        ];
    }

    /**
     * Mezcla el pepper con la contrasena. Se usa HMAC (y no concatenacion)
     * para no chocar con el limite de 72 bytes de bcrypt.
     */
    private static function pepper(string $password): string
    {
        $pepper = (string) Config::get('security.password.pepper', '');

        if ($pepper === '') {
            return $password;
        }

        return base64_encode(hash_hmac('sha256', $password, $pepper, true));
    }

    /** Token aleatorio en hexadecimal, apto para enlaces de un solo uso. */
    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /** Hash de un token para guardarlo en base de datos (nunca en claro). */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function equals(string $known, string $given): bool
    {
        return hash_equals($known, $given);
    }
}
