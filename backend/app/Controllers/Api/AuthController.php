<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Clock;
use App\Core\Config;
use App\Core\HttpException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Security\Audit;
use App\Security\Auth;
use App\Security\Hash;
use App\Security\Jwt;
use App\Security\RateLimiter;
use App\Services\Mailer;
use App\Services\NotificationService;
use App\Services\PushService;
use Database\Seeds\InitialSeeder;

/**
 * Autenticacion de la app movil.
 *
 * Token de acceso de vida corta + token de refresco rotativo: cada uso del
 * refresco emite uno nuevo y revoca el anterior. Si un token ya usado vuelve
 * a aparecer, se asume robo y se cierran todas las sesiones del usuario.
 */
final class AuthController extends ApiController
{
    public function register(Request $request): Response
    {
        $limit = RateLimiter::hit('api:registro:' . $request->ip(), 8, 3600);

        if (!$limit['allowed']) {
            throw new HttpException(429, 'Demasiados registros desde esta conexion.');
        }

        $data = $this->validate($request, [
            'first_name' => 'required|string|min:2|max:80|no_html',
            'last_name' => 'optional|string|max:80|no_html',
            'email' => 'required|email',
            'phone' => 'required|phone',
            'password' => 'required|password',
            'accepts_terms' => 'required',
        ], [
            'first_name' => 'nombre', 'email' => 'correo',
            'phone' => 'telefono', 'password' => 'contrasena',
            'accepts_terms' => 'aceptacion de los terminos',
        ]);

        if (QueryBuilder::table('users')->where('email', $data['email'])->exists()) {
            throw new HttpException(409, 'Ya existe una cuenta con ese correo.');
        }

        $acceptsMarketing = $request->bool('accepts_marketing');
        $now = Clock::nowUtc();

        $userId = QueryBuilder::table('users')->insert([
            'uuid' => InitialSeeder::uuid4(),
            'role' => 'client',
            'first_name' => $data['first_name'],
            'last_name' => (string) ($data['last_name'] ?? ''),
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password_hash' => Hash::make((string) $data['password']),
            'password_changed_at' => $now,
            'status' => 'active',
            'accepts_marketing' => $acceptsMarketing ? 1 : 0,
            'accepts_email' => 1,
            'accepts_push' => 1,
            'marketing_consent_at' => $acceptsMarketing ? $now : null,
            'marketing_consent_ip' => $acceptsMarketing ? $request->ip() : '',
            'referral_code' => strtoupper(bin2hex(random_bytes(4))),
            'source' => 'app',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $user = QueryBuilder::table('users')->where('id', $userId)->first() ?? [];

        NotificationService::onClientRegistered($user);
        Audit::record('cliente.registrado', 'user', $userId, null, ['origen' => 'app'], $request, $userId);

        return $this->ok($this->issueTokens($user, $request));
    }

    public function login(Request $request): Response
    {
        $data = $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required|string|max:200',
        ], ['email' => 'correo', 'password' => 'contrasena']);

        $result = Auth::attempt((string) $data['email'], (string) $data['password'], $request);
        $user = $result['user'];

        // La app movil es para clientes: el personal entra por el panel web.
        if ((string) $user['role'] !== 'client') {
            throw new HttpException(403, 'Esta cuenta es de personal. Usa el panel web.');
        }

        if ($result['needs_2fa']) {
            throw new HttpException(403, 'Esta cuenta usa verificacion en dos pasos. Ingresa desde la web.');
        }

        return $this->ok($this->issueTokens($user, $request));
    }

    /** Rotacion del token de refresco. */
    public function refresh(Request $request): Response
    {
        $token = $request->string('refresh_token');

        if ($token === '') {
            throw new HttpException(422, 'Falta el token de refresco.');
        }

        $hash = Hash::hashToken($token);
        $stored = QueryBuilder::table('refresh_tokens')->where('token_hash', $hash)->first();

        if ($stored === null) {
            throw new HttpException(401, 'Sesion no valida. Inicia sesion de nuevo.');
        }

        // Un token ya revocado que vuelve a usarse indica que fue robado.
        if ($stored['revoked_at'] !== null) {
            QueryBuilder::table('refresh_tokens')
                ->where('user_id', (int) $stored['user_id'])
                ->whereNull('revoked_at')
                ->update(['revoked_at' => Clock::nowUtc()]);

            \App\Core\Logger::warning('Reutilizacion de token de refresco: sesiones cerradas', [
                'user_id' => (int) $stored['user_id'],
                'ip' => $request->ip(),
            ]);

            throw new HttpException(401, 'Sesion invalidada por seguridad. Inicia sesion de nuevo.');
        }

        if (strtotime((string) $stored['expires_at']) < time()) {
            throw new HttpException(401, 'Tu sesion expiro. Inicia sesion de nuevo.');
        }

        $user = QueryBuilder::table('users')
            ->where('id', (int) $stored['user_id'])
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        if ($user === null) {
            throw new HttpException(401, 'La cuenta ya no esta disponible.');
        }

        QueryBuilder::table('refresh_tokens')->where('id', (int) $stored['id'])->update([
            'revoked_at' => Clock::nowUtc(),
            'last_used_at' => Clock::nowUtc(),
        ]);

        return $this->ok($this->issueTokens($user, $request, (int) $stored['id']));
    }

    public function logout(Request $request): Response
    {
        $token = $request->string('refresh_token');

        if ($token !== '') {
            QueryBuilder::table('refresh_tokens')
                ->where('token_hash', Hash::hashToken($token))
                ->update(['revoked_at' => Clock::nowUtc()]);
        }

        // Desactiva el dispositivo para no seguir enviandole avisos.
        $deviceToken = $request->string('push_token');

        if ($deviceToken !== '') {
            QueryBuilder::table('push_devices')->where('token', $deviceToken)->update(['is_active' => 0]);
        }

        return $this->ok(['message' => 'Sesion cerrada.']);
    }

    /** Cierra la sesion en todos los dispositivos. */
    public function logoutAll(Request $request): Response
    {
        $userId = (int) Auth::id();

        QueryBuilder::table('refresh_tokens')
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => Clock::nowUtc()]);

        QueryBuilder::table('users')->where('id', $userId)->update([
            'tokens_valid_after' => Clock::nowUtc(),
            'updated_at' => Clock::nowUtc(),
        ]);

        Audit::record('cuenta.sesiones_cerradas', 'user', $userId, null, null, $request, $userId);

        return $this->ok(['message' => 'Se cerraron todas las sesiones.']);
    }

    public function forgotPassword(Request $request): Response
    {
        $limit = RateLimiter::hit('api:reset:' . $request->ip(), 5, 3600);

        if (!$limit['allowed']) {
            throw new HttpException(429, 'Demasiadas solicitudes. Intentalo mas tarde.');
        }

        $data = $this->validate($request, ['email' => 'required|email'], ['email' => 'correo']);

        $user = QueryBuilder::table('users')
            ->where('email', $data['email'])
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        if ($user !== null) {
            $token = Hash::randomToken(32);

            QueryBuilder::table('password_resets')->insert([
                'user_id' => (int) $user['id'],
                'token_hash' => Hash::hashToken($token),
                'ip_address' => $request->ip(),
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
                'created_at' => Clock::nowUtc(),
            ]);

            Mailer::send(
                (string) $user['email'],
                'Restablece tu contrasena',
                '<p>Hola ' . e((string) $user['first_name']) . ',</p>'
                . '<p>Toca el enlace para crear una contrasena nueva (caduca en una hora):</p>'
                . '<p><a href="' . e(Url::to('/restablecer/' . $token)) . '">Restablecer contrasena</a></p>'
            );
        }

        // Respuesta identica exista o no la cuenta.
        return $this->ok(['message' => 'Si el correo esta registrado, te enviamos las instrucciones.']);
    }

    /** Registra el dispositivo para recibir notificaciones. */
    public function registerDevice(Request $request): Response
    {
        $data = $this->validate($request, [
            'token' => 'required|string|max:255',
            'platform' => 'optional|in:android,ios,web',
        ], ['token' => 'token del dispositivo']);

        PushService::registerDevice(
            (int) Auth::id(),
            (string) $data['token'],
            (string) ($data['platform'] ?? 'android'),
            $request->string('device_name'),
            (string) $request->header('x-app-version', '')
        );

        return $this->ok(['message' => 'Dispositivo registrado.']);
    }

    /**
     * Emite el par de tokens y guarda el refresco ligado al dispositivo.
     *
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    private function issueTokens(array $user, Request $request, ?int $parentId = null): array
    {
        $accessTtl = (int) Config::get('security.jwt.access_ttl', 900);
        $refreshTtl = (int) Config::get('security.jwt.refresh_ttl', 2592000);

        $accessToken = Jwt::issue([
            'sub' => (int) $user['id'],
            'type' => 'access',
            'role' => (string) $user['role'],
            'name' => (string) $user['first_name'],
        ], $accessTtl);

        $refreshToken = Hash::randomToken(40);

        QueryBuilder::table('refresh_tokens')->insert([
            'user_id' => (int) $user['id'],
            'token_hash' => Hash::hashToken($refreshToken),
            'device_id' => mb_substr($request->string('device_id'), 0, 80),
            'device_name' => mb_substr($request->string('device_name'), 0, 120),
            'platform' => mb_substr($request->string('platform', 'android'), 0, 20),
            'app_version' => mb_substr((string) $request->header('x-app-version', ''), 0, 20),
            'ip_address' => $request->ip(),
            'parent_id' => $parentId,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + $refreshTtl),
            'last_used_at' => Clock::nowUtc(),
            'created_at' => Clock::nowUtc(),
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $accessTtl,
            'user' => [
                'id' => (int) $user['id'],
                'first_name' => (string) $user['first_name'],
                'last_name' => (string) $user['last_name'],
                'email' => (string) $user['email'],
                'phone' => (string) $user['phone'],
                'avatar_url' => (string) $user['avatar_path'] !== ''
                    ? media_url((string) $user['avatar_path'])
                    : null,
                'loyalty_points' => (int) $user['loyalty_points'],
                'total_visits' => (int) $user['total_visits'],
                'referral_code' => (string) $user['referral_code'],
                'accepts_marketing' => (bool) $user['accepts_marketing'],
            ],
        ];
    }
}
