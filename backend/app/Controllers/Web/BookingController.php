<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Security\Auth;
use App\Security\RateLimiter;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\PaymentService;
use App\Services\SettingsService;

/** Flujo de agendamiento en la web. */
final class BookingController extends Controller
{
    public function start(Request $request): Response
    {
        if (!SettingsService::bool('booking.enabled', true)) {
            return $this->view('web.booking.disabled');
        }

        if (SettingsService::bool('booking.require_login', false) && !Auth::check()) {
            Session::put('__intended', '/agendar');
            Session::error('Inicia sesion o crea tu cuenta para agendar.');

            return $this->redirect('/ingresar');
        }

        $branches = QueryBuilder::table('branches')
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->get();

        $categories = QueryBuilder::table('service_categories')
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->get();

        foreach ($categories as $index => $category) {
            $categories[$index]['services'] = QueryBuilder::table('services')
                ->where('category_id', (int) $category['id'])
                ->where('is_active', 1)
                ->where('bookable_online', 1)
                ->whereNull('deleted_at')
                ->orderBy('sort_order')
                ->get();
        }

        return $this->view('web.booking.start', [
            'branches' => $branches,
            'categories' => $categories,
            'staff' => QueryBuilder::table('staff')
                ->where('is_active', 1)
                ->where('accepts_online', 1)
                ->whereNull('deleted_at')
                ->orderBy('sort_order')
                ->get(),
            'paymentMethods' => PaymentService::availableMethods(),
            'preselectedService' => $request->int('servicio'),
            'preselectedStaff' => $request->int('profesional'),
        ]);
    }

    /** Consulta de horarios libres (peticion asincrona desde el formulario). */
    public function availability(Request $request): Response
    {
        $limit = RateLimiter::hit('disponibilidad:' . $request->ip(), 120, 300);

        if (!$limit['allowed']) {
            throw new HttpException(429, 'Demasiadas consultas. Espera un momento.');
        }

        $serviceIds = $request->intArray('service_ids');
        $branchId = $request->int('branch_id');
        $staffId = $request->int('staff_id');
        $date = $request->string('date');

        if ($branchId <= 0) {
            $branchId = (int) (QueryBuilder::table('branches')
                ->where('is_active', 1)
                ->orderBy('is_default', 'DESC')
                ->value('id') ?? 0);
        }

        $duration = $serviceIds === []
            ? SettingsService::int('booking.custom_request_minutes', 30)
            : AvailabilityService::totalDuration($serviceIds, $staffId > 0 ? $staffId : null);

        if ($date === '') {
            return Response::apiOk([
                'days' => AvailabilityService::availableDays(
                    $branchId,
                    $duration,
                    $staffId > 0 ? $staffId : null,
                    $serviceIds
                ),
                'duration_minutes' => $duration,
            ]);
        }

        $slots = AvailabilityService::slotsForDate(
            $date,
            $branchId,
            $duration,
            $staffId > 0 ? $staffId : null,
            $serviceIds
        );

        return Response::apiOk([
            'date' => $date,
            'duration_minutes' => $duration,
            'slots' => array_map(static fn (array $slot): array => [
                'time' => $slot['time'],
                'label' => $slot['label'],
                'staff' => $slot['staff'],
            ], $slots),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->assertNotBot($request);

        $limit = RateLimiter::hit('agendar:' . $request->ip(), 10, 3600);

        if (!$limit['allowed']) {
            throw new HttpException(429, 'Has creado varias citas seguidas. Intentalo mas tarde.');
        }

        $user = Auth::user();

        $rules = [
            'branch_id' => 'required|int|min:1',
            'date' => 'required|date',
            'time' => 'required|time',
            'notes' => 'optional|string|max:1000|no_html',
            'custom_request' => 'optional|string|max:255|no_html',
            'coupon_code' => 'optional|string|max:40',
            'payment_method_id' => 'optional|int',
        ];

        if ($user === null) {
            $rules['client_name'] = 'required|string|min:2|max:160|no_html';
            $rules['client_phone'] = 'required|phone';
            $rules['client_email'] = 'optional|email';
        }

        $data = $this->validate($request, $rules, [
            'branch_id' => 'sucursal',
            'date' => 'fecha',
            'time' => 'hora',
            'client_name' => 'nombre',
            'client_phone' => 'telefono',
            'client_email' => 'correo',
            'notes' => 'comentario',
            'custom_request' => 'peticion',
        ]);

        $appointment = BookingService::create([
            'branch_id' => (int) $data['branch_id'],
            'staff_id' => $request->int('staff_id'),
            'service_ids' => $request->intArray('service_ids'),
            'date' => (string) $data['date'],
            'time' => (string) $data['time'],
            'client_id' => $user === null ? null : (int) $user['id'],
            'client_name' => $user === null
                ? (string) $data['client_name']
                : trim((string) $user['first_name'] . ' ' . (string) $user['last_name']),
            'client_phone' => $user === null ? (string) $data['client_phone'] : (string) $user['phone'],
            'client_email' => $user === null ? (string) ($data['client_email'] ?? '') : (string) $user['email'],
            'notes' => (string) ($data['notes'] ?? ''),
            'custom_request' => (string) ($data['custom_request'] ?? ''),
            'coupon_code' => (string) ($data['coupon_code'] ?? ''),
            'source' => 'web',
        ]);

        // Si eligio un metodo de pago, se registra la intencion.
        $methodId = (int) ($data['payment_method_id'] ?? 0);

        if ($methodId > 0 && SettingsService::bool('payments.enabled', true)) {
            try {
                PaymentService::registerForAppointment(
                    (int) $appointment['id'],
                    $methodId,
                    $request->int('bank_account_id') ?: null,
                    (float) $appointment['total'],
                    '',
                    null,
                    $user === null ? null : (int) $user['id']
                );
            } catch (\Throwable $e) {
                // Un fallo aqui no debe anular una cita ya creada.
                \App\Core\Logger::warning('No se pudo registrar el pago inicial', [
                    'appointment' => (int) $appointment['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Session::put('__last_appointment', (string) $appointment['code']);

        return $this->redirect('/agendar/confirmacion/' . rawurlencode((string) $appointment['code']));
    }

    public function confirmation(Request $request): Response
    {
        $code = (string) $request->param('code');

        $appointment = QueryBuilder::table('appointments')
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->first();

        if ($appointment === null) {
            throw new HttpException(404, 'No encontramos esa cita.');
        }

        // Solo puede verla quien la creo (sesion) o quien acaba de reservarla.
        $ownedBySession = Session::get('__last_appointment') === $code;
        $ownedByUser = Auth::id() !== null && (int) ($appointment['client_id'] ?? 0) === Auth::id();

        if (!$ownedBySession && !$ownedByUser && !Auth::isStaff()) {
            throw new HttpException(403, 'No tienes acceso a esta cita.');
        }

        $payment = QueryBuilder::table('payments')
            ->where('appointment_id', (int) $appointment['id'])
            ->orderBy('id', 'DESC')
            ->first();

        $method = null;
        $bankAccounts = [];

        if ($payment !== null && $payment['payment_method_id'] !== null) {
            $method = QueryBuilder::table('payment_methods')
                ->where('id', (int) $payment['payment_method_id'])
                ->first();

            if ($method !== null && (bool) $method['shows_bank_accounts']) {
                // El cliente ya eligio transferir: se muestran los datos completos.
                $bankAccounts = PaymentService::bankAccounts(true);
            }
        }

        return $this->view('web.booking.confirmation', [
            'appointment' => $appointment,
            'services' => QueryBuilder::table('appointment_services')
                ->where('appointment_id', (int) $appointment['id'])
                ->orderBy('sort_order')
                ->get(),
            'staff' => $appointment['staff_id'] === null
                ? null
                : QueryBuilder::table('staff')->where('id', (int) $appointment['staff_id'])->first(),
            'branch' => QueryBuilder::table('branches')->where('id', (int) $appointment['branch_id'])->first(),
            'payment' => $payment,
            'method' => $method,
            'bankAccounts' => $bankAccounts,
            'paymentMethods' => PaymentService::availableMethods(),
        ]);
    }
}
