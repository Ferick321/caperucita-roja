<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Request;
use App\Core\Session;

/**
 * Proteccion contra falsificacion de peticiones entre sitios.
 *
 * Doble comprobacion: token sincronizado en sesion + verificacion del origen
 * de la peticion (cabeceras Origin/Referer).
 */
final class Csrf
{
    public const FIELD = 'csrf_token';

    public const HEADER = 'x-csrf-token';

    private const SESSION_KEY = '__csrf';

    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);

        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            Session::put(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public static function rotate(): void
    {
        Session::put(self::SESSION_KEY, bin2hex(random_bytes(32)));
    }

    public static function verify(Request $request): bool
    {
        $expected = Session::get(self::SESSION_KEY);

        if (!is_string($expected) || $expected === '') {
            return false;
        }

        $provided = (string) ($request->input(self::FIELD) ?? $request->header(self::HEADER, '') ?? '');

        if ($provided === '' || !hash_equals($expected, $provided)) {
            return false;
        }

        return self::verifyOrigin($request);
    }

    /**
     * Comprueba que la peticion provenga del mismo sitio.
     * Si no llega Origin ni Referer se acepta (algunos clientes los omiten),
     * pero el token sincronizado ya cubre ese caso.
     */
    public static function verifyOrigin(Request $request): bool
    {
        $expectedHost = strtolower((string) parse_url((string) \App\Core\Config::get('app.url', ''), PHP_URL_HOST));

        if ($expectedHost === '') {
            return true;
        }

        foreach (['origin', 'referer'] as $header) {
            $value = $request->header($header, '');

            if ($value === null || $value === '') {
                continue;
            }

            $host = strtolower((string) parse_url($value, PHP_URL_HOST));

            if ($host === '') {
                continue;
            }

            return $host === $expectedHost;
        }

        return true;
    }
}
