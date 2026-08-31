<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Clock;
use App\Core\HttpException;
use App\Core\Model;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Security\Auth;
use App\Security\RateLimiter;
use App\Services\BookingService;
use App\Services\SettingsService;

/** Citas del cliente desde la app movil. */
final class AppointmentController extends ApiController
{
    public function index(Request $request): Response
    {
        $userId = (int) Auth::id();

        $query = QueryBuilder::table('appointments')
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

        $scope = $request->string('scope', 'upcoming');

        if ($scope === 'past') {
            $query->whereIn('appointments.status', ['completed', 'cancelled', 'no_show'])
                ->orderBy('appointments.starts_at', 'DESC');
        } else {
            $query->whereIn('appointments.status', ['pending', 'confirmed', 'in_progress'])
                ->orderBy('appointments.starts_at');
        }

        $result = Model::paginate($query, $this->page($request), 20);

        $data = [];

        foreach ($result['data'] as $appointment) {
            $services = QueryBuilder::table('appointment_services')
                ->where('appointment_id', (int) $appointment['id'])
                ->orderBy('sort_order')
                ->get();

            $data[] = $this->appointmentResource($appointment, $services);
        }

        $result['data'] = $data;

        return $this->paginated($result);
    }

    public function show(Request $request): Response
    {
        $appointment = $this->findOwned($request->paramInt('id'));

        $services = QueryBuilder::table('appointment_services')
            ->where('appointment_id', (int) $appointment['id'])
            ->orderBy('sort_order')
            ->get();

        $payments = QueryBuilder::table('payments')
            ->where('appointment_id', (int) $appointment['id'])
            ->whereNull('deleted_at')
            ->orderBy('id', 'DESC')
            ->get();

        $resource = $this->appointmentResource($appointment, $services);

        $resource['payments'] = array_map(static function (array $payment): array {
            $proofs = QueryBuilder::table('payment_proofs')
                ->where('payment_id', (int) $payment['id'])
                ->get();

            return [
                'id' => (int) $payment['id'],
                'amount' => round((float) $payment['amount'], 2),
                'method' => (string) $payment['method_code'],
                'status' => (string) $payment['status'],
                'reference' => (string) $payment['reference'],
                'rejection_reason' => (string) $payment['rejection_reason'],
                'created_at' => local_datetime((string) $payment['created_at'], 'Y-m-d H:i'),
                'proofs' => array_map(static fn (array $p): array => [
                    'url' => media_url((string) $p['file_path']),
                    'mime' => (string) $p['file_mime'],
                ], $proofs),
            ];
        }, $payments);

        return $this->ok($resource);
    }

    public function store(Request $request): Response
    {
        $limit = RateLimiter::hit('api:agendar:' . Auth::id(), 12, 3600);

        if (!$limit['allowed']) {
            throw new HttpException(429, 'Has creado varias citas seguidas. Intentalo mas tarde.');
        }

        $data = $this->validate($request, [
            'branch_id' => 'required|int|min:1',
            'date' => 'required|date',
            'time' => 'required|time',
            'notes' => 'optional|string|max:1000|no_html',
            'custom_request' => 'optional|string|max:255|no_html',
            'coupon_code' => 'optional|string|max:40',
        ], [
            'branch_id' => 'sucursal', 'date' => 'fecha', 'time' => 'hora',
            'notes' => 'comentario', 'custom_request' => 'peticion',
        ]);

        $user = Auth::user() ?? [];

        $appointment = BookingService::create([
            'branch_id' => (int) $data['branch_id'],
            'staff_id' => $request->int('staff_id'),
            'service_ids' => $request->intArray('service_ids'),
            'date' => (string) $data['date'],
            'time' => (string) $data['time'],
            'client_id' => (int) $user['id'],
            'client_name' => trim((string) $user['first_name'] . ' ' . (string) $user['last_name']),
            'client_phone' => (string) $user['phone'],
            'client_email' => (string) $user['email'],
            'notes' => (string) ($data['notes'] ?? ''),
            'custom_request' => (string) ($data['custom_request'] ?? ''),
            'coupon_code' => (string) ($data['coupon_code'] ?? ''),
            'source' => 'app',
        ]);

        $services = QueryBuilder::table('appointment_services')
            ->where('appointment_id', (int) $appointment['id'])
            ->get();

        return Response::json([
            'ok' => true,
            'data' => $this->appointmentResource($appointment, $services),
        ], 201);
    }

    public function cancel(Request $request): Response
    {
        $appointment = BookingService::cancelByClient(
            $request->paramInt('id'),
            (int) Auth::id(),
            $request->string('reason')
        );

        return $this->ok($this->appointmentResource($appointment));
    }

    public function reschedule(Request $request): Response
    {
        if (!SettingsService::bool('booking.allow_client_reschedule', true)) {
            throw new HttpException(403, 'Las reprogramaciones en linea estan desactivadas.');
        }

        $data = $this->validate($request, [
            'date' => 'required|date',
            'time' => 'required|time',
        ], ['date' => 'fecha', 'time' => 'hora']);

        $appointment = $this->findOwned($request->paramInt('id'));

        $minHours = SettingsService::int('booking.cancellation_hours', 4);
        $hoursLeft = (strtotime((string) $appointment['starts_at']) - time()) / 3600;

        if ($hoursLeft < $minHours) {
            throw new HttpException(422, sprintf(
                'Los cambios se aceptan con al menos %d horas de antelacion.',
                $minHours
            ));
        }

        $updated = BookingService::reschedule(
            (int) $appointment['id'],
            (string) $data['date'],
            (string) $data['time'],
            $request->int('staff_id') ?: null,
            (int) Auth::id()
        );

        return $this->ok($this->appointmentResource($updated));
    }

    /** Resena tras una visita completada. */
    public function review(Request $request): Response
    {
        $appointment = $this->findOwned($request->paramInt('id'));

        if ((string) $appointment['status'] !== 'completed') {
            throw new HttpException(422, 'Solo puedes opinar sobre citas ya realizadas.');
        }

        if (QueryBuilder::table('reviews')->where('appointment_id', (int) $appointment['id'])->exists()) {
            throw new HttpException(409, 'Ya dejaste tu opinion sobre esta cita.');
        }

        $data = $this->validate($request, [
            'rating' => 'required|int|between:1,5',
            'comment' => 'optional|string|max:2000|no_html',
        ], ['rating' => 'puntuacion', 'comment' => 'comentario']);

        $user = Auth::user() ?? [];

        QueryBuilder::table('reviews')->insert([
            'appointment_id' => (int) $appointment['id'],
            'client_id' => (int) $user['id'],
            'staff_id' => $appointment['staff_id'] === null ? null : (int) $appointment['staff_id'],
            'author_name' => trim((string) $user['first_name'] . ' ' . mb_substr((string) $user['last_name'], 0, 1)),
            'rating' => (int) $data['rating'],
            'comment' => (string) ($data['comment'] ?? ''),
            // Se publica solo tras la moderacion del negocio.
            'is_approved' => 0,
            'created_at' => Clock::nowUtc(),
            'updated_at' => Clock::nowUtc(),
        ]);

        return $this->ok([
            'message' => 'Gracias por tu opinion. La publicaremos tras revisarla.',
        ]);
    }

    /** @return array<string,mixed> */
    private function findOwned(int $id): array
    {
        $appointment = QueryBuilder::table('appointments')
            ->select([
                'appointments.*',
                'staff.display_name AS staff_name',
                'staff.photo_path AS staff_photo',
                'branches.name AS branch_name',
                'branches.address AS branch_address',
            ])
            ->leftJoin('staff', 'staff.id', '=', 'appointments.staff_id')
            ->leftJoin('branches', 'branches.id', '=', 'appointments.branch_id')
            ->where('appointments.id', $id)
            ->where('appointments.client_id', (int) Auth::id())
            ->whereNull('appointments.deleted_at')
            ->first();

        if ($appointment === null) {
            throw new HttpException(404, 'No encontramos esa cita.');
        }

        return $appointment;
    }
}
