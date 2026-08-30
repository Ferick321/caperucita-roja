<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Clock;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Session;

/**
 * Autenticacion y autorizacion.
 *
 * Dos vias de acceso conviven:
 *  - web (panel y sitio publico): sesion PHP endurecida;
 *  - API movil: JWT de vida corta + refresh rotativo ligado al dispositivo.
 *
 * Los permisos no estan escritos en el codigo: se leen de la base de datos,
 * de modo que el super administrador puede reasignarlos desde el panel.
 */
final class Auth
{
    private const SESSION_USER = '__user_id';

    private const SESSION_2FA = '__2fa_passed';

    /** @var array<string,mixed>|null */
    private static ?array $user = null;

    /** @var list<string>|null */
    private static ?array $permissions = null;

    // ---- Estado -------------------------------------------------------

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }

        $id = Session::get(self::SESSION_USER);

        if (!is_int($id) && !is_numeric($id)) {
            return null;
        }

        $user = QueryBuilder::table('users')
            ->where('id', (int) $id)
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->first();

        if ($user === null) {
            self::logout();

            return null;
        }

        self::$user = $user;

        return $user;
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user === null ? null : (int) $user['id'];
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** Inyecta el usuario autenticado por JWT (peticiones de la API). */
    public static function setApiUser(array $user): void
    {
        self::$user = $user;
        self::$permissions = null;
    }

    public static function forgetCache(): void
    {
        self::$user = null;
        self::$permissions = null;
    }

    // ---- Inicio de sesion ---------------------------------------------

    /**
     * Verifica credenciales aplicando limitacion de intentos y bloqueo de cuenta.
     *
     * @return array{user:array<string,mixed>,needs_2fa:bool}
     * @throws HttpException si las credenciales o el estado no permiten entrar
     */
    public static function attempt(string $email, string $password, Request $request): array
    {
        $email = mb_strtolower(trim($email));
        $ip = $request->ip();

        $ipLimit = RateLimiter::hit(
            'login:ip:' . $ip,
            (int) Config::get('security.login.max_attempts_per_ip', 20),
            (int) Config::get('security.login.decay_seconds', 900)
        );

        if (!$ipLimit['allowed']) {
            throw new HttpException(429, sprintf(
                'Demasiados intentos desde esta conexion. Espera %d minutos.',
                (int) ceil($ipLimit['retry_after'] / 60)
            ));
        }

        $accountLimit = RateLimiter::hit(
            'login:acct:' . $email,
            (int) Config::get('security.login.max_attempts_per_account', 8),
            (int) Config::get('security.login.decay_seconds', 900)
        );

        if (!$accountLimit['allowed']) {
            throw new HttpException(429, sprintf(
                'Demasiados intentos para esta cuenta. Espera %d minutos o restablece tu contrasena.',
                (int) ceil($accountLimit['retry_after'] / 60)
            ));
        }

        $user = QueryBuilder::table('users')
            ->where('email', $email)
            ->whereNull('deleted_at')
            ->first();

        $hash = (string) ($user['password_hash'] ?? '');
        $valid = Hash::verify($password, $hash);

        if ($user === null || !$valid) {
            self::recordFailure($email, $ip, $request, $user === null ? 'usuario_inexistente' : 'password_incorrecta');
            RateLimiter::progressiveDelay((int) ($user['failed_logins'] ?? 1));

            // Mensaje generico: no revela si el correo existe.
            throw new HttpException(401, 'Correo o contrasena incorrectos.');
        }

        if ((string) $user['status'] === 'blocked') {
            self::recordFailure($email, $ip, $request, 'cuenta_bloqueada');

            throw new HttpException(403, 'Tu cuenta esta bloqueada. Comunicate con el negocio.');
        }

        if ((string) $user['status'] === 'pending') {
            throw new HttpException(403, 'Tu cuenta aun no ha sido verificada. Revisa tu correo.');
        }

        $lockedUntil = $user['locked_until'] ?? null;

        if ($lockedUntil !== null && strtotime((string) $lockedUntil) > time()) {
            throw new HttpException(403, 'La cuenta esta temporalmente bloqueada por seguridad. Intenta mas tarde.');
        }

        // Credenciales correctas: se reinician los contadores.
        RateLimiter::clear('login:acct:' . $email);

        $updates = [
            'failed_logins' => 0,
            'locked_until' => null,
            'last_login_at' => Clock::nowUtc(),
            'last_login_ip' => $ip,
        ];

        // Rehash transparente si cambiaron los parametros de coste.
        if (Hash::needsRehash($hash)) {
            $updates['password_hash'] = Hash::make($password);
        }

        QueryBuilder::table('users')->where('id', (int) $user['id'])->update($updates);

        self::recordLogin($email, $ip, $request, true);

        return [
            'user' => array_merge($user, $updates),
            'needs_2fa' => (bool) ($user['two_factor_enabled'] ?? false),
        ];
    }

    /** Fija la sesion tras un inicio de sesion valido. */
    public static function login(array $user, bool $twoFactorSatisfied = true): void
    {
        Session::regenerate();
        Csrf::rotate();

        Session::put(self::SESSION_USER, (int) $user['id']);
        Session::put(self::SESSION_2FA, $twoFactorSatisfied);
        Session::put('__login_at', time());

        self::$user = $user;
        self::$permissions = null;
    }

    public static function logout(): void
    {
        Session::forget(self::SESSION_USER);
        Session::forget(self::SESSION_2FA);
        Session::destroy();

        self::$user = null;
        self::$permissions = null;
    }

    public static function twoFactorSatisfied(): bool
    {
        $user = self::user();

        if ($user === null) {
            return false;
        }

        if (!(bool) ($user['two_factor_enabled'] ?? false)) {
            return true;
        }

        return (bool) Session::get(self::SESSION_2FA, false);
    }

    public static function markTwoFactorPassed(): void
    {
        Session::put(self::SESSION_2FA, true);
        Session::regenerate();
    }

    // ---- Autorizacion --------------------------------------------------

    public static function role(): string
    {
        $user = self::user();

        return (string) ($user['role'] ?? 'guest');
    }

    public static function isAdmin(): bool
    {
        return in_array(self::role(), ['super_admin', 'admin'], true);
    }

    public static function isStaff(): bool
    {
        return in_array(self::role(), ['super_admin', 'admin', 'manager', 'staff'], true);
    }

    public static function isClient(): bool
    {
        return self::role() === 'client';
    }

    /** @return list<string> */
    public static function permissions(): array
    {
        if (self::$permissions !== null) {
            return self::$permissions;
        }

        $role = self::role();

        if ($role === 'guest') {
            return self::$permissions = [];
        }

        if ($role === 'super_admin') {
            return self::$permissions = ['*'];
        }

        $rows = Database::instance()->select(
            'SELECT p.slug
               FROM permissions p
               INNER JOIN role_permissions rp ON rp.permission_id = p.id
               INNER JOIN roles r ON r.id = rp.role_id
              WHERE r.slug = :role',
            ['role' => $role]
        );

        return self::$permissions = array_map(static fn (array $r): string => (string) $r['slug'], $rows);
    }

    public static function can(string $permission): bool
    {
        $permissions = self::permissions();

        if (in_array('*', $permissions, true)) {
            return true;
        }

        if (in_array($permission, $permissions, true)) {
            return true;
        }

        // Comodin por modulo: "citas.*" cubre "citas.editar".
        $module = explode('.', $permission)[0] ?? '';

        return $module !== '' && in_array($module . '.*', $permissions, true);
    }

    public static function authorize(string $permission): void
    {
        if (!self::can($permission)) {
            Logger::warning('Acceso denegado', ['permission' => $permission, 'user' => self::id()]);

            throw new HttpException(403, 'No tienes permiso para realizar esta accion.');
        }
    }

    // ---- Registro de accesos ------------------------------------------

    private static function recordFailure(string $email, string $ip, Request $request, string $reason): void
    {
        self::recordLogin($email, $ip, $request, false, $reason);

        $user = QueryBuilder::table('users')->where('email', $email)->first();

        if ($user === null) {
            return;
        }

        $failed = (int) ($user['failed_logins'] ?? 0) + 1;
        $threshold = (int) Config::get('security.login.lockout_threshold', 10);
        $lockMinutes = (int) Config::get('security.login.lockout_minutes', 30);

        $updates = ['failed_logins' => $failed];

        if ($failed >= $threshold) {
            $updates['locked_until'] = gmdate('Y-m-d H:i:s', time() + $lockMinutes * 60);
            Logger::warning('Cuenta bloqueada por intentos fallidos', ['user_id' => (int) $user['id']]);
        }

        QueryBuilder::table('users')->where('id', (int) $user['id'])->update($updates);
    }

    private static function recordLogin(
        string $email,
        string $ip,
        Request $request,
        bool $success,
        string $reason = ''
    ): void {
        try {
            QueryBuilder::table('login_attempts')->insert([
                'email' => mb_substr($email, 0, 190),
                'ip_address' => $ip,
                'user_agent' => $request->userAgent(),
                'successful' => $success ? 1 : 0,
                'failure_reason' => mb_substr($reason, 0, 60),
                'created_at' => Clock::nowUtc(),
            ]);
        } catch (\Throwable $e) {
            Logger::error('No se pudo registrar el intento de acceso', ['error' => $e->getMessage()]);
        }
    }
}
