<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Clock;
use App\Core\HttpException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Security\Audit;
use App\Security\Auth;
use App\Security\Hash;
use App\Services\LoyaltyService;
use App\Services\MaintenanceService;
use App\Services\MediaService;

/** Perfil, preferencias y fidelidad del cliente en la app. */
final class ProfileController extends ApiController
{
    public function show(Request $request): Response
    {
        $user = Auth::user() ?? [];

        return $this->ok([
            'id' => (int) $user['id'],
            'first_name' => (string) $user['first_name'],
            'last_name' => (string) $user['last_name'],
            'email' => (string) $user['email'],
            'phone' => (string) $user['phone'],
            'birth_date' => $user['birth_date'],
            'avatar_url' => (string) $user['avatar_path'] !== ''
                ? media_url((string) $user['avatar_path'])
                : null,
            'loyalty_points' => (int) $user['loyalty_points'],
            'loyalty_value' => LoyaltyService::pointsToMoney((int) $user['loyalty_points']),
            'total_visits' => (int) $user['total_visits'],
            'total_spent' => round((float) $user['total_spent'], 2),
            'last_visit_at' => $user['last_visit_at'] === null
                ? null
                : local_datetime((string) $user['last_visit_at'], 'Y-m-d'),
            'referral_code' => (string) $user['referral_code'],
            'preferences' => [
                'accepts_marketing' => (bool) $user['accepts_marketing'],
                'accepts_email' => (bool) $user['accepts_email'],
                'accepts_push' => (bool) $user['accepts_push'],
                'accepts_whatsapp' => (bool) $user['accepts_whatsapp'],
            ],
        ]);
    }

    public function update(Request $request): Response
    {
        $user = Auth::user() ?? [];
        $userId = (int) $user['id'];

        $data = $this->validate($request, [
            'first_name' => 'optional|string|min:2|max:80|no_html',
            'last_name' => 'optional|string|max:80|no_html',
            'phone' => 'optional|phone',
            'birth_date' => 'optional|date',
        ], ['first_name' => 'nombre', 'phone' => 'telefono']);

        $updates = ['updated_at' => Clock::nowUtc()];

        foreach (['first_name', 'last_name', 'phone', 'birth_date'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        // Preferencias de contacto: solo se tocan las que llegan.
        foreach (['accepts_marketing', 'accepts_email', 'accepts_push', 'accepts_whatsapp'] as $preference) {
            if ($request->has($preference)) {
                $updates[$preference] = $request->bool($preference) ? 1 : 0;
            }
        }

        if (($updates['accepts_marketing'] ?? 0) === 1 && !(bool) $user['accepts_marketing']) {
            $updates['marketing_consent_at'] = Clock::nowUtc();
            $updates['marketing_consent_ip'] = $request->ip();
        }

        QueryBuilder::table('users')->where('id', $userId)->update($updates);
        Auth::forgetCache();

        return $this->show($request);
    }

    public function updateAvatar(Request $request): Response
    {
        if (!$request->hasFile('avatar')) {
            throw new HttpException(422, 'No se recibio ninguna imagen.');
        }

        $user = Auth::user() ?? [];

        $path = MediaService::replace(
            (string) $user['avatar_path'],
            (array) $request->file('avatar'),
            'avatares',
            (int) $user['id'],
            400
        );

        QueryBuilder::table('users')->where('id', (int) $user['id'])->update([
            'avatar_path' => $path,
            'updated_at' => Clock::nowUtc(),
        ]);

        Auth::forgetCache();

        return $this->ok(['avatar_url' => media_url($path)]);
    }

    public function changePassword(Request $request): Response
    {
        $user = Auth::user() ?? [];

        $data = $this->validate($request, [
            'current_password' => 'required|string',
            'password' => 'required|password',
        ], ['current_password' => 'contrasena actual', 'password' => 'nueva contrasena']);

        if (!Hash::verify((string) $data['current_password'], (string) $user['password_hash'])) {
            throw new HttpException(401, 'La contrasena actual no es correcta.');
        }

        $now = Clock::nowUtc();

        QueryBuilder::table('users')->where('id', (int) $user['id'])->update([
            'password_hash' => Hash::make((string) $data['password']),
            'password_changed_at' => $now,
            'tokens_valid_after' => $now,
            'updated_at' => $now,
        ]);

        // Todas las sesiones abiertas quedan invalidadas.
        QueryBuilder::table('refresh_tokens')
            ->where('user_id', (int) $user['id'])
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $now]);

        Audit::record('cuenta.clave_cambiada', 'user', (int) $user['id'], null, null, $request);

        return $this->ok([
            'message' => 'Contrasena actualizada. Vuelve a iniciar sesion.',
        ]);
    }

    public function loyalty(Request $request): Response
    {
        $user = Auth::user() ?? [];
        $points = (int) $user['loyalty_points'];

        return $this->ok([
            'points' => $points,
            'value' => LoyaltyService::pointsToMoney($points),
            'history' => array_map(static fn (array $entry): array => [
                'points' => (int) $entry['points'],
                'balance_after' => (int) $entry['balance_after'],
                'reason' => (string) $entry['reason'],
                'created_at' => local_datetime((string) $entry['created_at'], 'Y-m-d'),
            ], LoyaltyService::history((int) $user['id'], 40)),
        ]);
    }

    /** Baja definitiva desde la app (derecho al olvido). */
    public function deleteAccount(Request $request): Response
    {
        $user = Auth::user() ?? [];

        $data = $this->validate($request, [
            'password' => 'required|string',
            'confirm' => 'required|in:ELIMINAR',
        ], ['password' => 'contrasena', 'confirm' => 'confirmacion']);

        if (!Hash::verify((string) $data['password'], (string) $user['password_hash'])) {
            throw new HttpException(401, 'La contrasena no es correcta.');
        }

        MaintenanceService::forgetClient((int) $user['id'], (int) $user['id']);

        return $this->ok(['message' => 'Tu cuenta y tus datos personales fueron eliminados.']);
    }
}
