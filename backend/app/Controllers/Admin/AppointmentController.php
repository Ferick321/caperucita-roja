<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Clock;
use App\Core\HttpException;
use App\Core\Model;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Security\Auth;
use App\Services\AvailabilityService;
use App\Services\BookingService;

/** Agenda y gestion de citas. */
final class AppointmentController extends AdminController
{
    public function index(Request $request): Response
    {
        $this->authorize('citas.ver');

        $query = QueryBuilder::table('appointments')
            ->select([
                'appointments.*',
                'staff.display_name AS staff_name',
                'staff.color AS staff_color',
                'branches.name AS branch_name',
            ])
            ->leftJoin('staff', 'staff.id', '=', 'appointments.staff_id')
            ->leftJoin('branches', 'branches.id', '=', 'appointments.branch_id')
            ->whereNull('appointments.deleted_at');

        // El profesional solo ve su propia agenda.
        if (Auth::role() === 'staff') {
            $ownStaffId = QueryBuilder::table('staff')->where('user_id', (int) Auth::id())->value('id');
            $query->where('appointments.staff_id', (int) ($ownStaffId ?? 0));
        }

        $status = $request->string('estado');
        if ($status !== '' && in_array($status, ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'], true)) {
            $query->where('appointments.status', $status);
        }

        $staffId = $request->int('profesional');
        if ($staffId > 0) {
            $query->where('appointments.staff_id', $staffId);
        }

        $from = $request->string('desde');
        $to = $request->string('hasta');

        if ($from !== '') {
            $query->where('appointments.starts_at', '>=', Clock::localToUtc($from . ' 00:00:00'));
        }

        if ($to !== '') {
            $query->where('appointments.starts_at', '<=', Clock::localToUtc($to . ' 23:59:59'));
        }

        $search = $request->string('q');
        if ($search !== '') {
            $query->search($search, [
                'appointments.code', 'appointments.client_name',
                'appointments.client_phone', 'appointments.client_email',
            ]);
        }

        $query->orderBy('appointments.starts_at', $request->string('orden') === 'asc' ? 'ASC' : 'DESC');

        $result = Model::paginate($query, $this->page($request), 25);

        foreach ($result['data'] as $index => $appointment) {
            $result['data'][$index]['services'] = QueryBuilder::table('appointment_services')
                ->where('appointment_id', (int) $appointment['id'])
                ->orderBy('sort_order')
                ->get();
        }

        return $this->view('admin.appointments.index', [
            'result' => $result,
            'staffList' => QueryBuilder::table('staff')->whereNull('deleted_at')->orderBy('display_name')->get(),
            'filters' => [
                'estado' => $status,
                'profesional' => $staffId,
                'desde' => $from,
                'hasta' => $to,
                'q' => $search,
            ],
        ]);
    }

    /** Vista de agenda diaria por profesional. */
    public function agenda(Request $request): Response
    {
        $this->authorize('citas.ver');

        $date = $request->string('fecha') !== '' ? $request->string('fecha') : Clock::today();
        $branchId = $request->int('sucursal');

        if ($branchId <= 0) {
            $branchId = (int) (QueryBuilder::table('branches')->orderBy('is_default', 'DESC')->value('id') ?? 0);
        }

        $dayStart = Clock::localToUtc($date . ' 00:00:00');
        $dayEnd = Clock::localToUtc($date . ' 23:59:59');

        $staff = QueryBuilder::table('staff')
            ->where('branch_id', $branchId)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->get();

        $appointments = QueryBuilder::table('appointments')
            ->select(['appointments.*'])
            ->whereNull('appointments.deleted_at')
            ->where('appointments.branch_id', $branchId)
            ->whereBetween('appointments.starts_at', $dayStart, $dayEnd)
            ->orderBy('appointments.starts_at')
            ->get();

        $byStaff = [];

        foreach ($appointments as $appointment) {
            $key = (int) ($appointment['staff_id'] ?? 0);
            $appointment['local_start'] = Clock::utcToLocal((string) $appointment['starts_at'], 'H:i');
            $appointment['local_end'] = Clock::utcToLocal((string) $appointment['ends_at'], 'H:i');
            $byStaff[$key][] = $appointment;
        }

        return $this->view('admin.appointments.agenda', [
            'date' => $date,
            'branchId' => $branchId,
            'branches' => QueryBuilder::table('branches')->whereNull('deleted_at')->orderBy('sort_order')->get(),
            'staff' => $staff,
            'byStaff' => $byStaff,
            'unassigned' => $byStaff[0] ?? [],
        ]);
    }

    public function show(Request $request): Response
    {
        $this->authorize('citas.ver');

        $appointment = $this->find($request->paramInt('id'));

        return $this->view('admin.appointments.show', [
            'appointment' => $appointment,
            'services' => QueryBuilder::table('appointment_services')
                ->where('appointment_id', (int) $appointment['id'])->orderBy('sort_order')->get(),
            'history' => QueryBuilder::table('appointment_status_history')
                ->select(['appointment_status_history.*', 'users.first_name', 'users.last_name'])
                ->leftJoin('users', 'users.id', '=', 'appointment_status_history.changed_by')
                ->where('appointment_status_history.appointment_id', (int) $appointment['id'])
                ->orderBy('appointment_status_history.created_at', 'DESC')
                ->get(),
            'payments' => QueryBuilder::table('payments')
                ->where('appointment_id', (int) $appointment['id'])->orderBy('id', 'DESC')->get(),
            'staffList' => QueryBuilder::table('staff')->whereNull('deleted_at')->where('is_active', 1)->orderBy('display_name')->get(),
            'client' => $appointment['client_id'] === null
                ? null
                : QueryBuilder::table('users')->where('id', (int) $appointment['client_id'])->first(),
        ]);
    }

    public function changeStatus(Request $request): Response
    {
        $this->authorize('citas.editar');

        $id = $request->paramInt('id');
        $status = $request->string('status');
        $note = $request->string('note');

        BookingService::changeStatus($id, $status, Auth::id(), $note);
        Session::success('Estado de la cita actualizado.');

        return $this->back($request, '/panel/citas/' . $id);
    }

    public function reschedule(Request $request): Response
    {
        $this->authorize('citas.editar');

        $data = $this->validate($request, [
            'date' => 'required|date',
            'time' => 'required|time',
        ], ['date' => 'fecha', 'time' => 'hora']);

        $id = $request->paramInt('id');
        $staffId = $request->int('staff_id') ?: null;

        BookingService::reschedule($id, (string) $data['date'], (string) $data['time'], $staffId, Auth::id());
        Session::success('Cita reprogramada.');

        return $this->redirect('/panel/citas/' . $id);
    }

    /** Alta manual desde el mostrador o por telefono. */
    public function store(Request $request): Response
    {
        $this->authorize('citas.crear');

        $data = $this->validate($request, [
            'branch_id' => 'required|int|min:1',
            'date' => 'required|date',
            'time' => 'required|time',
            'client_name' => 'required|string|min:2|max:160|no_html',
            'client_phone' => 'optional|phone',
            'client_email' => 'optional|email',
            'notes' => 'optional|string|max:1000|no_html',
            'custom_request' => 'optional|string|max:255|no_html',
        ], [
            'branch_id' => 'sucursal', 'date' => 'fecha', 'time' => 'hora',
            'client_name' => 'nombre del cliente', 'client_phone' => 'telefono',
        ]);

        $appointment = BookingService::create([
            'branch_id' => (int) $data['branch_id'],
            'staff_id' => $request->int('staff_id'),
            'service_ids' => $request->intArray('service_ids'),
            'date' => (string) $data['date'],
            'time' => (string) $data['time'],
            'client_id' => $request->int('client_id') ?: null,
            'client_name' => (string) $data['client_name'],
            'client_phone' => (string) ($data['client_phone'] ?? ''),
            'client_email' => (string) ($data['client_email'] ?? ''),
            'notes' => (string) ($data['notes'] ?? ''),
            'custom_request' => (string) ($data['custom_request'] ?? ''),
            'source' => $request->string('source', 'panel'),
        ]);

        Session::success('Cita creada: ' . $appointment['code']);

        return $this->redirect('/panel/citas/' . (int) $appointment['id']);
    }

    public function create(Request $request): Response
    {
        $this->authorize('citas.crear');

        $categories = QueryBuilder::table('service_categories')
            ->where('is_active', 1)->whereNull('deleted_at')->orderBy('sort_order')->get();

        foreach ($categories as $index => $category) {
            $categories[$index]['services'] = QueryBuilder::table('services')
                ->where('category_id', (int) $category['id'])
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->orderBy('sort_order')
                ->get();
        }

        return $this->view('admin.appointments.create', [
            'branches' => QueryBuilder::table('branches')->whereNull('deleted_at')->where('is_active', 1)->orderBy('sort_order')->get(),
            'categories' => $categories,
            'staffList' => QueryBuilder::table('staff')->whereNull('deleted_at')->where('is_active', 1)->orderBy('display_name')->get(),
        ]);
    }

    /** Consulta de disponibilidad desde el panel. */
    public function availability(Request $request): Response
    {
        $this->authorize('citas.ver');

        $serviceIds = $request->intArray('service_ids');
        $branchId = $request->int('branch_id');
        $staffId = $request->int('staff_id');
        $date = $request->string('date');

        $duration = $serviceIds === []
            ? 30
            : AvailabilityService::totalDuration($serviceIds, $staffId > 0 ? $staffId : null);

        $slots = AvailabilityService::slotsForDate(
            $date !== '' ? $date : Clock::today(),
            $branchId,
            $duration,
            $staffId > 0 ? $staffId : null,
            $serviceIds
        );

        return Response::apiOk(['slots' => $slots, 'duration_minutes' => $duration]);
    }

    /** Borrado logico: la cita deja de verse pero se conserva para auditoria. */
    public function destroy(Request $request): Response
    {
        $this->authorize('citas.eliminar');

        $id = $request->paramInt('id');
        $this->find($id);

        QueryBuilder::table('appointments')->where('id', $id)->update([
            'deleted_at' => Clock::nowUtc(),
            'updated_at' => Clock::nowUtc(),
        ]);

        \App\Security\Audit::record('cita.eliminada', 'appointment', $id, null, null, $request);
        Session::success('Cita eliminada. Se purgara definitivamente segun la politica de retencion.');

        return $this->redirect('/panel/citas');
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        $appointment = QueryBuilder::table('appointments')
            ->select([
                'appointments.*',
                'staff.display_name AS staff_name',
                'branches.name AS branch_name',
                'branches.address AS branch_address',
            ])
            ->leftJoin('staff', 'staff.id', '=', 'appointments.staff_id')
            ->leftJoin('branches', 'branches.id', '=', 'appointments.branch_id')
            ->where('appointments.id', $id)
            ->whereNull('appointments.deleted_at')
            ->first();

        if ($appointment === null) {
            throw new HttpException(404, 'La cita no existe.');
        }

        return $appointment;
    }
}
