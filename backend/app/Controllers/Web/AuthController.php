<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\Controller;
use App\Core\Clock;
use App\Core\HttpException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Security\Audit;
use App\Security\Auth;
use App\Security\Hash;
use App\Security\RateLimiter;
use App\Security\TwoFactor;
use App\Services\BannerService;
use App\Services\Mailer;
use App\Services\NotificationService;
use App\Services\SettingsService;
use Database\Seeds\InitialSeeder;

/** Acceso de clientes al sitio publico. */
final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        if (Auth::check()) {
            return $this->redirect('/mi-cuenta');
        }

        return $this->view('web.auth.login');
    }

    public function login(Request $request): Response
    {
        $data = $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required|string|max:200',
        ], ['email' => 'correo', 'password' => 'contrasena']);

        $result = Auth::attempt($data['email'], (string) $data['password'], $request);
        $user = $result['user'];

        if ($result['needs_2fa']) {
            Session::put('__2fa_pending_user', (int) $user['id']);

            return $this->redirect('/verificacion');
        }

        Auth::login($user);

        // El anuncio de bienvenida se marca para que la vista lo muestre.
        if (SettingsService::bool('ads.show_on_login', true)) {
            Session::put('__show_login_ad', true);
        }

        $intended = Session::get('__intended');
        Session::forget('__intended');

        if (in_array((string) $user['role'], ['super_admin', 'admin', 'manager', 'staff'], true)) {
            return $this->redirect('/panel');
        }

        return $this->redirect(is_string($intended) && $intended !== '' ? $intended : '/mi-cuenta');
    }

    public function showTwoFactor(Request $request): Response
    {
        if (!Session::has('__2fa_pending_user')) {
            return $this->redirect('/ingresar');
        }

        return $this->view('web.auth.two_factor');
    }

    public function verifyTwoFactor(Request $request): Response
    {
        $pendingId = Session::get('__2fa_pending_user');

        if (!is_int($pendingId) && !is_numeric($pendingId)) {
            return $this->redirect('/ingresar');
        }

        $limit = RateLimiter::hit('2fa:' . $pendingId, 6, 600);

        if (!$limit['allowed']) {
            Session::forget('__2fa_pending_user');

            throw new HttpException(429, 'Demasiados intentos. Vuelve a iniciar sesion.');
        }

        $user = QueryBuilder::table('users')->where('id', (int) $pendingId)->first();

        if ($user === null) {
            Session::forget('__2fa_pending_user');

            return $this->redirect('/ingresar');
        }

        $code = $request->string('code');
        $secret = \App\Security\Crypto::decrypt((string) $user['two_factor_secret']);

        $valid = TwoFactor::verify($secret, $code);

        // Codigo de respaldo: se consume y queda inutilizable.
        if (!$valid) {
            $valid = $this->consumeRecoveryCode($user, $code);
        }

        if (!$valid) {
            Session::error('El codigo no es valido o ya expiro.');

            return $this->redirect('/verificacion');
        }

        Session::forget('__2fa_pending_user');
        RateLimiter::clear('2fa:' . $pendingId);

        Auth::login($user);
        Auth::markTwoFactorPassed();
        Audit::record('acceso.2fa_ok', 'user', (int) $user['id'], null, null, $request);

        return $this->redirect(Auth::isStaff() ? '/panel' : '/mi-cuenta');
    }

    public function showRegister(Request $request): Response
    {
        if (Auth::check()) {
            return $this->redirect('/mi-cuenta');
        }

        return $this->view('web.auth.register');
    }

    public function register(Request $request): Response
    {
        $this->assertNotBot($request);

        $limit = RateLimiter::hit('registro:' . $request->ip(), 5, 3600);

        if (!$limit['allowed']) {
            throw new HttpException(429, 'Demasiados registros desde esta conexion. Intentalo mas tarde.');
        }

        $data = $this->validate($request, [
            'first_name' => 'required|string|min:2|max:80|no_html',
            'last_name' => 'optional|string|max:80|no_html',
            'email' => 'required|email',
            'phone' => 'required|phone',
            'password' => 'required|password|confirmed',
            'accepts_terms' => 'required',
        ], [
            'first_name' => 'nombre',
            'last_name' => 'apellido',
            'email' => 'correo',
            'phone' => 'telefono',
            'password' => 'contrasena',
            'accepts_terms' => 'aceptacion de los terminos',
        ]);

        if (QueryBuilder::table('users')->where('email', $data['email'])->exists()) {
            Session::flashInput($request->all());
            Session::error('Ya existe una cuenta con ese correo. Puedes iniciar sesion o recuperar tu contrasena.');

            return $this->redirect('/registro');
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
            'source' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $user = QueryBuilder::table('users')->where('id', $userId)->first() ?? [];

        NotificationService::onClientRegistered($user);
        Audit::record('cliente.registrado', 'user', $userId, null, ['origen' => 'web'], $request, $userId);

        Auth::login($user);
        Session::success('Tu cuenta esta lista. Ya puedes agendar tu cita.');

        if (SettingsService::bool('ads.show_on_login', true)) {
            Session::put('__show_login_ad', true);
        }

        return $this->redirect('/agendar');
    }

    public function logout(Request $request): Response
    {
        $userId = Auth::id();
        Auth::logout();

        if ($userId !== null) {
            Audit::record('acceso.salida', 'user', $userId, null, null, $request, $userId);
        }

        return $this->redirect('/');
    }

    // ---- Recuperacion de contrasena -------------------------------------

    public function showForgot(Request $request): Response
    {
        return $this->view('web.auth.forgot');
    }

    public function sendResetLink(Request $request): Response
    {
        $this->assertNotBot($request);

        $data = $this->validate($request, ['email' => 'required|email'], ['email' => 'correo']);

        $limit = RateLimiter::hit('reset:' . $request->ip(), 5, 3600);

        if (!$limit['allowed']) {
            throw new HttpException(429, 'Demasiadas solicitudes. Intentalo en una hora.');
        }

        $user = QueryBuilder::table('users')
            ->where('email', $data['email'])
            ->whereNull('deleted_at')
            ->first();

        // La respuesta es siempre la misma: no revela si el correo existe.
        if ($user !== null && (string) $user['status'] === 'active') {
            $token = Hash::randomToken(32);

            QueryBuilder::table('password_resets')->insert([
                'user_id' => (int) $user['id'],
                'token_hash' => Hash::hashToken($token),
                'ip_address' => $request->ip(),
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
                'created_at' => Clock::nowUtc(),
            ]);

            $link = Url::to('/restablecer/' . $token);

            Mailer::send(
                (string) $user['email'],
                'Restablece tu contrasena',
                '<p>Hola ' . e((string) $user['first_name']) . ',</p>'
                . '<p>Recibimos una solicitud para restablecer tu contrasena. '
                . 'El enlace caduca en una hora:</p>'
                . '<p><a href="' . e($link) . '">Crear una contrasena nueva</a></p>'
                . '<p style="color:#6b7280;font-size:13px">Si no fuiste tu, ignora este mensaje: '
                . 'tu contrasena sigue siendo la misma.</p>'
            );
        }

        Session::success('Si el correo esta registrado, te enviamos un enlace para restablecer tu contrasena.');

        return $this->redirect('/recuperar');
    }

    public function showReset(Request $request): Response
    {
        return $this->view('web.auth.reset', ['token' => (string) $request->param('token')]);
    }

    public function resetPassword(Request $request): Response
    {
        $data = $this->validate($request, [
            'token' => 'required|string|length:64',
            'password' => 'required|password|confirmed',
        ], ['token' => 'enlace', 'password' => 'contrasena']);

        $reset = QueryBuilder::table('password_resets')
            ->where('token_hash', Hash::hashToken((string) $data['token']))
            ->whereNull('used_at')
            ->where('expires_at', '>', Clock::nowUtc())
            ->first();

        if ($reset === null) {
            Session::error('El enlace no es valido o ya caduco. Solicita uno nuevo.');

            return $this->redirect('/recuperar');
        }

        $now = Clock::nowUtc();

        QueryBuilder::table('users')->where('id', (int) $reset['user_id'])->update([
            'password_hash' => Hash::make((string) $data['password']),
            'password_changed_at' => $now,
            'failed_logins' => 0,
            'locked_until' => null,
            // Invalida las sesiones abiertas en la app movil.
            'tokens_valid_after' => $now,
            'updated_at' => $now,
        ]);

        QueryBuilder::table('password_resets')->where('id', (int) $reset['id'])->update(['used_at' => $now]);

        // Cualquier otro enlace pendiente queda inservible.
        QueryBuilder::table('password_resets')
            ->where('user_id', (int) $reset['user_id'])
            ->whereNull('used_at')
            ->update(['used_at' => $now]);

        QueryBuilder::table('refresh_tokens')
            ->where('user_id', (int) $reset['user_id'])
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $now]);

        Audit::record('cuenta.clave_restablecida', 'user', (int) $reset['user_id'], null, null, $request);
        Session::success('Tu contrasena se actualizo. Ya puedes iniciar sesion.');

        return $this->redirect('/ingresar');
    }

    /** @param array<string,mixed> $user */
    private function consumeRecoveryCode(array $user, string $code): bool
    {
        $stored = (string) ($user['two_factor_recovery'] ?? '');

        if ($stored === '') {
            return false;
        }

        $decrypted = \App\Security\Crypto::decrypt($stored);
        $codes = json_decode($decrypted, true);

        if (!is_array($codes)) {
            return false;
        }

        $normalized = strtoupper(trim($code));
        $index = array_search($normalized, array_map('strval', $codes), true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);

        QueryBuilder::table('users')->where('id', (int) $user['id'])->update([
            'two_factor_recovery' => \App\Security\Crypto::encrypt(
                (string) json_encode(array_values($codes))
            ),
            'updated_at' => Clock::nowUtc(),
        ]);

        return true;
    }
}
