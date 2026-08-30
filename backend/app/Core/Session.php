<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sesion endurecida.
 *
 * Medidas aplicadas:
 *  - cookie HttpOnly + SameSite + Secure (segun HTTPS);
 *  - identificador regenerado al iniciar sesion y cada N minutos;
 *  - huella de navegador/IP para detectar robo de cookie;
 *  - caducidad por inactividad y caducidad absoluta;
 *  - almacenamiento fuera del directorio publico.
 */
final class Session
{
    private static bool $started = false;

    public static function start(Request $request): void
    {
        if (self::$started || PHP_SAPI === 'cli') {
            return;
        }

        $lifetime = (int) Config::get('session.lifetime_minutes', 120) * 60;
        $path = (string) Config::get('session.path', '');

        if ($path !== '' && is_dir($path)) {
            session_save_path($path);
        }

        session_name((string) Config::get('session.name', 'estilo_sid'));

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => (string) Config::get('session.domain', ''),
            'secure' => $request->isSecure() || (bool) Config::get('session.force_secure', false),
            'httponly' => true,
            'samesite' => (string) Config::get('session.same_site', 'Lax'),
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        // session.sid_length / sid_bits_per_character quedaron obsoletos en PHP 8.4;
        // el valor por defecto del motor ya son 128 bits de entropia por identificador.

        session_start();
        self::$started = true;

        self::enforceLifetimes($lifetime);
        self::enforceFingerprint($request);
    }

    private static function enforceLifetimes(int $idleSeconds): void
    {
        $now = time();
        $absolute = (int) Config::get('session.absolute_lifetime_minutes', 720) * 60;

        $lastActivity = (int) ($_SESSION['__last_activity'] ?? $now);
        $startedAt = (int) ($_SESSION['__started_at'] ?? $now);

        if ($now - $lastActivity > $idleSeconds || $now - $startedAt > $absolute) {
            self::destroy();
            session_start();
            self::$started = true;
            $_SESSION['__started_at'] = $now;
            $_SESSION['__expired'] = true;
        }

        $_SESSION['__last_activity'] = $now;
        $_SESSION['__started_at'] ??= $now;

        // Rotacion periodica del identificador.
        $rotateEvery = (int) Config::get('session.rotate_minutes', 20) * 60;
        $lastRotation = (int) ($_SESSION['__rotated_at'] ?? 0);

        if ($now - $lastRotation > $rotateEvery) {
            session_regenerate_id(true);
            $_SESSION['__rotated_at'] = $now;
        }
    }

    /**
     * Vincula la sesion al agente de usuario y (opcionalmente) al bloque /24
     * de la IP. Un atacante que robe la cookie desde otro equipo la pierde.
     */
    private static function enforceFingerprint(Request $request): void
    {
        $parts = [substr(hash('sha256', $request->userAgent()), 0, 32)];

        if ((bool) Config::get('session.bind_ip', false)) {
            $ip = $request->ip();
            $block = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
                ? implode('.', array_slice(explode('.', $ip), 0, 3))
                : $ip;
            $parts[] = substr(hash('sha256', $block), 0, 32);
        }

        $fingerprint = implode('|', $parts);
        $stored = $_SESSION['__fingerprint'] ?? null;

        if ($stored === null) {
            $_SESSION['__fingerprint'] = $fingerprint;

            return;
        }

        if (!hash_equals((string) $stored, $fingerprint)) {
            Logger::warning('Huella de sesion no coincide; sesion invalidada', ['ip' => $request->ip()]);
            self::destroy();
            session_start();
            self::$started = true;
            $_SESSION['__fingerprint'] = $fingerprint;
            $_SESSION['__started_at'] = time();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Regenera el identificador conservando los datos (post-login). */
    public static function regenerate(): void
    {
        if (self::$started) {
            session_regenerate_id(true);
            $_SESSION['__rotated_at'] = time();
        }
    }

    public static function destroy(): void
    {
        if (!self::$started) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name() ?: 'estilo_sid', '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]);
        }

        session_destroy();
        self::$started = false;
    }

    // ---- Mensajes flash -------------------------------------------------

    public static function flash(string $type, string $message): void
    {
        $_SESSION['__flash'][$type][] = $message;
    }

    public static function success(string $message): void
    {
        self::flash('success', $message);
    }

    public static function error(string $message): void
    {
        self::flash('error', $message);
    }

    /** @return array<string,list<string>> */
    public static function pullFlash(): array
    {
        $flash = $_SESSION['__flash'] ?? [];
        unset($_SESSION['__flash']);

        return is_array($flash) ? $flash : [];
    }

    /** Guarda la entrada del formulario para repoblarlo tras un error. */
    public static function flashInput(array $input): void
    {
        unset($input['password'], $input['password_confirmation'], $input['csrf_token'], $input['_method']);
        $_SESSION['__old'] = $input;
    }

    /** @return array<string,mixed> */
    public static function pullOldInput(): array
    {
        $old = $_SESSION['__old'] ?? [];
        unset($_SESSION['__old']);

        return is_array($old) ? $old : [];
    }

    public static function flashErrors(array $errors): void
    {
        $_SESSION['__errors'] = $errors;
    }

    /** @return array<string,list<string>> */
    public static function pullErrors(): array
    {
        $errors = $_SESSION['__errors'] ?? [];
        unset($_SESSION['__errors']);

        return is_array($errors) ? $errors : [];
    }
}
