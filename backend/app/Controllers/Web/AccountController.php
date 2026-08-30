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
use App\Security\Audit;
use App\Security\Auth;
use App\Security\Hash;
use App\Services\BookingService;
use App\Services\LoyaltyService;
use App\Services\MediaService;
use App\Services\PaymentService;
use App\Services\SettingsService;

/** Area privada del cliente: perfil, citas, pagos y fidelidad. */
final class AccountController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $user = Auth::user();
        $userId = (int) $user['id'];

        return $this->view('web.account.dashboard', [
            'user' => $user,
            'upcoming' => $this->appointmentsQuery($userId)
                ->whereIn('appointments.status', ['pending', 'confirmed', 'in_progress'])
                ->where('appointments.starts_at', '>=', Clock::nowUtc())
                ->orderBy('appointments.starts_at')
                ->limit(5)
                ->get(),
            'past' => $this->appointmentsQuery($userId)
                ->whereIn('appointments.status', ['completed', 'cancelled', 'no_show'])
                ->orderBy('appointments.starts_at', 'DESC')
                ->limit(5)
                ->get(),
            'loyaltyPoints' => (int) $user['loyalty_points'],
            'loyaltyValue' => LoyaltyService::pointsToMoney((int) $user['loyalty_points']),
        ]);
    }

    public function appointments(Request $request): Response
    {
        $userId = (int) Auth::user()['id'];
        $filter = $request->string('estado', 'proximas');

        $query = $this->appointmentsQuery($userId);

        if ($filter === 'historial') {
            $query->whereIn('appointments.status', ['completed', 'cancelled', 'no_show'])
                ->orderBy('appointments.starts_at', 'DESC');
        } else {
            $query->whereIn('appointments.status', ['pending', 'confirmed', 'in_progress'])
                ->orderBy('appointments.starts_at');
        }

        $appointments = $query->limit(50)->get();

        foreach ($appointments as $index => $appointment) {
            $appointments[$index]['services'] = QueryBuilder::table('appointment_services')
                ->where('appointment_id', (int) $appointment['id'])
                ->orderBy('sort_order')
                ->get();
        }

        return $this->view('web.account.appointments', [
            'appointments' => $appointments,
            'filter' => $filter,
            'cancellationHours' => SettingsService::int('booking.cancellation_hours', 4),
            'canCancel' => SettingsService::bool('booking.allow_client_cancel', true),
        ]);
    }

    public function cancelAppointment(Request $request): Response
    {
        $appointmentId = $request->paramInt('id');
        $userId = (int) Auth::user()['id'];

        BookingService::cancelByClient($appointmentId, $userId, $request->string('reason'));

        Session::success('Tu cita fue cancelada.');

        return $this->redirect('/mis-citas');
    }

    /** Pantalla de pago de una cita: metodos, datos bancarios y comprobante. */
    public function paymentPage(Request $request): Response
    {
        $appointment = $this->ownedAppointment($request);

        $payments = QueryBuilder::table('payments')
            ->where('appointment_id', (int) $appointment['id'])
            ->orderBy('id', 'DESC')
            ->get();

        foreach ($payments as $index => $payment) {
            $payments[$index]['proofs'] = QueryBuilder::table('payment_proofs')
                ->where('payment_id', (int) $payment['id'])
                ->get();
        }

        return $this->view('web.account.payment', [
            'appointment' => $appointment,
            'services' => QueryBuilder::table('appointment_services')
                ->where('appointment_id', (int) $appointment['id'])
                ->get(),
            'payments' => $payments,
            'methods' => PaymentService::availableMethods(),
            'bankAccounts' => PaymentService::bankAccounts(true),
            'instructions' => SettingsService::string('payments.transfer_instructions', ''),
        ]);
    }

    /** Registra el pago y adjunta el comprobante (archivo o foto de camara). */
    public function submitPayment(Request $request): Response
    {
        $appointment = $this->ownedAppointment($request);
        $userId = Auth::id();

        $data = $this->validate($request, [
            'payment_method_id' => 'required|int|min:1',
            'reference' => 'optional|string|max:120|no_html',
            'transferred_at' => 'optional|date',
            'amount' => 'optional|numeric|min:0',
        ], [
            'payment_method_id' => 'metodo de pago',
            'reference' => 'numero de comprobante',
            'transferred_at' => 'fecha de la transferencia',
            'amount' => 'importe',
        ]);

        $method = QueryBuilder::table('payment_methods')
            ->where('id', (int) $data['payment_method_id'])
            ->where('is_active', 1)
            ->first();

        if ($method === null) {
            throw new HttpException(422, 'El metodo de pago no esta disponible.');
        }

        if ((bool) $method['requires_proof'] && !$request->hasFile('proof')) {
            Session::error('Adjunta el comprobante para poder verificar tu pago.');

            return $this->back($request, '/mis-citas/' . (int) $appointment['id'] . '/pago');
        }

        $payment = PaymentService::registerForAppointment(
            (int) $appointment['id'],
            (int) $data['payment_method_id'],
            $request->int('bank_account_id') ?: null,
            (float) ($data['amount'] ?? 0),
            (string) ($data['reference'] ?? ''),
            isset($data['transferred_at']) && $data['transferred_at'] !== null
                ? Clock::localToUtc((string) $data['transferred_at'] . ' 12:00:00')
                : null,
            $userId
        );

        if ($request->hasFile('proof')) {
            PaymentService::attachProof(
                (int) $payment['id'],
                (array) $request->file('proof'),
                'web',
                $userId
            );

            Session::success('Recibimos tu comprobante. Lo verificaremos y te avisaremos en breve.');
        } else {
            Session::success('Registramos tu forma de pago.');
        }

        return $this->redirect('/mis-citas/' . (int) $appointment['id'] . '/pago');
    }

    public function profile(Request $request): Response
    {
        return $this->view('web.account.profile', [
            'user' => Auth::user(),
            'loyaltyHistory' => LoyaltyService::history((int) Auth::user()['id'], 20),
        ]);
    }

    public function updateProfile(Request $request): Response
    {
        $user = Auth::user();
        $userId = (int) $user['id'];

        $data = $this->validate($request, [
            'first_name' => 'required|string|min:2|max:80|no_html',
            'last_name' => 'optional|string|max:80|no_html',
            'phone' => 'required|phone',
            'birth_date' => 'optional|date',
        ], [
            'first_name' => 'nombre',
            'last_name' => 'apellido',
            'phone' => 'telefono',
            'birth_date' => 'fecha de nacimiento',
        ]);

        $acceptsMarketing = $request->bool('accepts_marketing');

        $updates = [
            'first_name' => $data['first_name'],
            'last_name' => (string) ($data['last_name'] ?? ''),
            'phone' => $data['phone'],
            'birth_date' => $data['birth_date'] ?? null,
            'accepts_marketing' => $acceptsMarketing ? 1 : 0,
            'accepts_email' => $request->bool('accepts_email') ? 1 : 0,
            'accepts_push' => $request->bool('accepts_push') ? 1 : 0,
            'accepts_whatsapp' => $request->bool('accepts_whatsapp') ? 1 : 0,
            'updated_at' => Clock::nowUtc(),
        ];

        // Se anota cuando y desde donde se dio el consentimiento comercial.
        if ($acceptsMarketing && !(bool) $user['accepts_marketing']) {
            $updates['marketing_consent_at'] = Clock::nowUtc();
            $updates['marketing_consent_ip'] = $request->ip();
        }

        if ($request->hasFile('avatar')) {
            $updates['avatar_path'] = MediaService::replace(
                (string) $user['avatar_path'],
                (array) $request->file('avatar'),
                'avatares',
                $userId,
                400
            );
        }

        QueryBuilder::table('users')->where('id', $userId)->update($updates);
        Auth::forgetCache();

        Session::success('Actualizamos tus datos.');

        return $this->redirect('/mi-perfil');
    }

    public function changePassword(Request $request): Response
    {
        $user = Auth::user();

        $data = $this->validate($request, [
            'current_password' => 'required|string',
            'password' => 'required|password|confirmed',
        ], [
            'current_password' => 'contrasena actual',
            'password' => 'nueva contrasena',
        ]);

        if (!Hash::verify((string) $data['current_password'], (string) $user['password_hash'])) {
            Session::error('La contrasena actual no es correcta.');

            return $this->redirect('/mi-perfil');
        }

        $now = Clock::nowUtc();

        QueryBuilder::table('users')->where('id', (int) $user['id'])->update([
            'password_hash' => Hash::make((string) $data['password']),
            'password_changed_at' => $now,
            'tokens_valid_after' => $now,
            'updated_at' => $now,
        ]);

        Audit::record('cuenta.clave_cambiada', 'user', (int) $user['id'], null, null, $request);
        Session::success('Tu contrasena se actualizo. Cerramos las sesiones abiertas en otros dispositivos.');

        return $this->redirect('/mi-perfil');
    }

    /** Baja voluntaria de la cuenta: elimina los datos personales. */
    public function deleteAccount(Request $request): Response
    {
        $user = Auth::user();

        $data = $this->validate($request, [
            'password' => 'required|string',
            'confirm' => 'required|in:ELIMINAR',
        ], ['password' => 'contrasena', 'confirm' => 'confirmacion']);

        if (!Hash::verify((string) $data['password'], (string) $user['password_hash'])) {
            Session::error('La contrasena no es correcta.');

            return $this->redirect('/mi-perfil');
        }

        $userId = (int) $user['id'];
        Auth::logout();

        \App\Services\MaintenanceService::forgetClient($userId, $userId);

        Session::success('Tu cuenta y tus datos personales fueron eliminados.');

        return $this->redirect('/');
    }

    /** @return array<string,mixed> */
    private function ownedAppointment(Request $request): array
    {
        $appointment = QueryBuilder::table('appointments')
            ->where('id', $request->paramInt('id'))
            ->where('client_id', (int) Auth::user()['id'])
            ->whereNull('deleted_at')
            ->first();

        if ($appointment === null) {
            throw new HttpException(404, 'No encontramos esa cita.');
        }

        return $appointment;
    }

    private function appointmentsQuery(int $userId): \App\Core\QueryBuilder
    {
        return QueryBuilder::table('appointments')
            ->select([
                'appointments.*',
                'staff.display_name AS staff_name',
                'staff.photo_path AS staff_photo',
                'branches.name AS branch_name',
                'branches.address AS branch_address',
            ])
            ->leftJoin('staff', 'staff.id', '=', 'appointments.staff_id')
            ->leftJoin('branches', 'branches.id', '=', 'appointments.branch_id')
            ->where('appointments.client_id', $userId)
            ->whereNull('appointments.deleted_at');
    }
}
