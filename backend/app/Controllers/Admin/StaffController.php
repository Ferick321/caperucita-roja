<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Clock;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Security\Audit;
use App\Security\Auth;
use App\Security\Hash;
use App\Services\MediaService;
use Database\Seeds\InitialSeeder;

/** Equipo: fichas, servicios que presta cada quien, horarios y ausencias. */
final class StaffController extends AdminController
{
    public function index(Request $request): Response
    {
        $this->authorize('personal.ver');

        $staff = QueryBuilder::table('staff')
            ->select(['staff.*', 'branches.name AS branch_name'])
            ->leftJoin('branches', 'branches.id', '=', 'staff.branch_id')
            ->whereNull('staff.deleted_at')
            ->orderBy('staff.sort_order')
            ->get();

        foreach ($staff as $index => $member) {
            $staff[$index]['service_count'] = QueryBuilder::table('staff_services')
                ->where('staff_id', (int) $member['id'])->count();

            $staff[$index]['upcoming'] = QueryBuilder::table('appointments')
                ->where('staff_id', (int) $member['id'])
                ->whereIn('status', ['pending', 'confirmed'])
                ->where('starts_at', '>', Clock::nowUtc())
                ->whereNull('deleted_at')
                ->count();
        }

        return $this->view('admin.staff.index', ['staff' => $staff]);
    }

    public function form(Request $request): Response
    {
        $this->authorize('personal.editar');

        $id = $request->paramInt('id');
        $member = $id > 0 ? QueryBuilder::table('staff')->where('id', $id)->first() : null;

        if ($id > 0 && $member === null) {
            throw new HttpException(404, 'El profesional no existe.');
        }

        $categories = QueryBuilder::table('service_categories')
            ->where('is_active', 1)->whereNull('deleted_at')->orderBy('sort_order')->get();

        foreach ($categories as $index => $category) {
            $categories[$index]['services'] = QueryBuilder::table('services')
                ->where('category_id', (int) $category['id'])
                ->whereNull('deleted_at')
                ->orderBy('sort_order')
                ->get();
        }

        return $this->view('admin.staff.form', [
            'member' => $member,
            'branches' => QueryBuilder::table('branches')->whereNull('deleted_at')->orderBy('sort_order')->get(),
            'categories' => $categories,
            'assignedServices' => $id > 0
                ? array_map('intval', QueryBuilder::table('staff_services')->where('staff_id', $id)->pluck('service_id'))
                : [],
            'schedules' => $id > 0
                ? QueryBuilder::table('staff_schedules')->where('staff_id', $id)->orderBy('weekday')->get()
                : [],
            'timeOff' => $id > 0
                ? QueryBuilder::table('staff_time_off')->where('staff_id', $id)
                    ->where('ends_at', '>=', Clock::nowUtc())->orderBy('starts_at')->get()
                : [],
        ]);
    }

    public function save(Request $request): Response
    {
        $this->authorize('personal.editar');

        $id = $request->paramInt('id');

        $data = $this->validate($request, [
            'display_name' => 'required|string|min:2|max:120|no_html',
            'branch_id' => 'required|int|min:1',
            'title' => 'optional|string|max:100|no_html',
            'bio' => 'optional|string|max:2000|no_html',
            'phone' => 'optional|phone',
            'email' => 'optional|email',
            'instagram' => 'optional|string|max:120|no_html',
            'color' => 'optional|hex_color',
            'commission_percent' => 'optional|numeric|between:0,100',
            'sort_order' => 'optional|int|between:0,9999',
        ], ['display_name' => 'nombre', 'branch_id' => 'sucursal', 'email' => 'correo']);

        $existing = $id > 0 ? QueryBuilder::table('staff')->where('id', $id)->first() : null;

        $payload = [
            'branch_id' => (int) $data['branch_id'],
            'display_name' => $data['display_name'],
            'slug' => $this->uniqueSlug(Url::slug((string) $data['display_name']), $id),
            'title' => (string) ($data['title'] ?? ''),
            'bio' => (string) ($data['bio'] ?? ''),
            'phone' => (string) ($data['phone'] ?? ''),
            'email' => (string) ($data['email'] ?? ''),
            'instagram' => (string) ($data['instagram'] ?? ''),
            'color' => (string) ($data['color'] ?? '#0ea5e9'),
            'commission_percent' => (float) ($data['commission_percent'] ?? 0),
            'accepts_online' => $request->bool('accepts_online') ? 1 : 0,
            'is_active' => $request->bool('is_active') ? 1 : 0,
            'show_on_web' => $request->bool('show_on_web') ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'updated_at' => Clock::nowUtc(),
        ];

        if ($request->hasFile('photo')) {
            $payload['photo_path'] = MediaService::replace(
                (string) ($existing['photo_path'] ?? ''),
                (array) $request->file('photo'),
                'equipo',
                Auth::id(),
                800
            );
        }

        if ($id > 0) {
            QueryBuilder::table('staff')->where('id', $id)->update($payload);
        } else {
            $payload['created_at'] = Clock::nowUtc();
            $id = QueryBuilder::table('staff')->insert($payload);
        }

        // Servicios que presta.
        $serviceIds = $request->intArray('service_ids');
        QueryBuilder::table('staff_services')->where('staff_id', $id)->delete();

        foreach ($serviceIds as $serviceId) {
            Database::instance()->statement(
                'INSERT IGNORE INTO staff_services (staff_id, service_id) VALUES (:s, :v)',
                ['s' => $id, 'v' => $serviceId]
            );
        }

        Audit::record('personal.guardado', 'staff', $id, $existing, $payload, $request);
        Session::success('Ficha del profesional guardada.');

        return $this->redirect('/panel/personal/' . $id . '/editar');
    }

    /** Alta o baja del acceso al panel para un profesional. */
    public function toggleAccess(Request $request): Response
    {
        $this->authorize('personal.editar');
        Auth::authorize('ajustes.editar');

        $id = $request->paramInt('id');
        $member = QueryBuilder::table('staff')->where('id', $id)->first();

        if ($member === null) {
            throw new HttpException(404, 'El profesional no existe.');
        }

        // Revocar acceso.
        if ($member['user_id'] !== null) {
            QueryBuilder::table('users')->where('id', (int) $member['user_id'])->update([
                'status' => 'blocked',
                'tokens_valid_after' => Clock::nowUtc(),
                'updated_at' => Clock::nowUtc(),
            ]);

            QueryBuilder::table('staff')->where('id', $id)->update(['user_id' => null]);

            Audit::record('personal.acceso_revocado', 'staff', $id, null, null, $request);
            Session::success('Acceso al panel revocado.');

            return $this->back($request, '/panel/personal/' . $id . '/editar');
        }

        // Conceder acceso: exige un correo valido en la ficha.
        $email = mb_strtolower(trim((string) $member['email']));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Session::error('Anade un correo valido en la ficha antes de darle acceso al panel.');

            return $this->back($request, '/panel/personal/' . $id . '/editar');
        }

        if (QueryBuilder::table('users')->where('email', $email)->exists()) {
            Session::error('Ya existe una cuenta con ese correo.');

            return $this->back($request, '/panel/personal/' . $id . '/editar');
        }

        $temporaryPassword = bin2hex(random_bytes(6));

        $userId = QueryBuilder::table('users')->insert([
            'uuid' => InitialSeeder::uuid4(),
            'role' => 'staff',
            'first_name' => (string) $member['display_name'],
            'email' => $email,
            'email_verified_at' => Clock::nowUtc(),
            'phone' => (string) $member['phone'],
            'password_hash' => Hash::make($temporaryPassword),
            'password_changed_at' => Clock::nowUtc(),
            'status' => 'active',
            'referral_code' => strtoupper(bin2hex(random_bytes(4))),
            'source' => 'panel',
            'created_at' => Clock::nowUtc(),
            'updated_at' => Clock::nowUtc(),
        ]);

        QueryBuilder::table('staff')->where('id', $id)->update(['user_id' => $userId]);

        Audit::record('personal.acceso_concedido', 'staff', $id, null, ['user_id' => $userId], $request);
        Session::success(
            'Acceso creado. Usuario: ' . $email . ' | Contrasena temporal: ' . $temporaryPassword
            . ' (pidele que la cambie al entrar).'
        );

        return $this->back($request, '/panel/personal/' . $id . '/editar');
    }

    public function delete(Request $request): Response
    {
        $this->authorize('personal.editar');

        $id = $request->paramInt('id');

        $upcoming = QueryBuilder::table('appointments')
            ->where('staff_id', $id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('starts_at', '>', Clock::nowUtc())
            ->whereNull('deleted_at')
            ->count();

        if ($upcoming > 0) {
            Session::error("No se puede eliminar: tiene {$upcoming} cita(s) proxima(s). Reasignalas o cancelalas primero.");

            return $this->redirect('/panel/personal');
        }

        QueryBuilder::table('staff')->where('id', $id)->update([
            'deleted_at' => Clock::nowUtc(),
            'is_active' => 0,
            'show_on_web' => 0,
            'updated_at' => Clock::nowUtc(),
        ]);

        Audit::record('personal.eliminado', 'staff', $id, null, null, $request);
        Session::success('Profesional eliminado.');

        return $this->redirect('/panel/personal');
    }

    // ---- Horarios --------------------------------------------------------

    public function saveSchedule(Request $request): Response
    {
        $this->authorize('personal.horarios');

        $staffId = $request->paramInt('id');

        if (!QueryBuilder::table('staff')->where('id', $staffId)->whereNull('deleted_at')->exists()) {
            throw new HttpException(404, 'El profesional no existe.');
        }

        Database::instance()->transaction(static function () use ($request, $staffId): void {
            QueryBuilder::table('staff_schedules')->where('staff_id', $staffId)->delete();

            for ($weekday = 0; $weekday <= 6; $weekday++) {
                if (!$request->bool('day_' . $weekday)) {
                    continue;
                }

                $start = $request->string('start_' . $weekday, '09:00');
                $end = $request->string('end_' . $weekday, '19:00');
                $breakStart = $request->string('break_start_' . $weekday);
                $breakEnd = $request->string('break_end_' . $weekday);

                if (!self::isTime($start) || !self::isTime($end) || $end <= $start) {
                    continue;
                }

                $hasBreak = self::isTime($breakStart) && self::isTime($breakEnd) && $breakEnd > $breakStart;

                QueryBuilder::table('staff_schedules')->insert([
                    'staff_id' => $staffId,
                    'weekday' => $weekday,
                    'starts_at' => $start . ':00',
                    'ends_at' => $end . ':00',
                    'break_start' => $hasBreak ? $breakStart . ':00' : null,
                    'break_end' => $hasBreak ? $breakEnd . ':00' : null,
                    'is_active' => 1,
                ]);
            }
        });

        Audit::record('personal.horario', 'staff', $staffId, null, null, $request);
        Session::success('Horario actualizado.');

        return $this->redirect('/panel/personal/' . $staffId . '/editar');
    }

    public function addTimeOff(Request $request): Response
    {
        $this->authorize('personal.horarios');

        $staffId = $request->paramInt('id');

        $data = $this->validate($request, [
            'starts_on' => 'required|date',
            'ends_on' => 'required|date',
            'reason' => 'optional|string|max:160|no_html',
        ], ['starts_on' => 'fecha de inicio', 'ends_on' => 'fecha de fin']);

        $fullDay = $request->bool('is_full_day', true);
        $startTime = $fullDay ? '00:00:00' : ($request->string('start_time', '09:00') . ':00');
        $endTime = $fullDay ? '23:59:59' : ($request->string('end_time', '19:00') . ':00');

        $startUtc = Clock::localToUtc((string) $data['starts_on'] . ' ' . $startTime);
        $endUtc = Clock::localToUtc((string) $data['ends_on'] . ' ' . $endTime);

        if ($endUtc <= $startUtc) {
            Session::error('La fecha de fin debe ser posterior a la de inicio.');

            return $this->back($request, '/panel/personal/' . $staffId . '/editar');
        }

        QueryBuilder::table('staff_time_off')->insert([
            'staff_id' => $staffId,
            'starts_at' => $startUtc,
            'ends_at' => $endUtc,
            'reason' => (string) ($data['reason'] ?? ''),
            'is_full_day' => $fullDay ? 1 : 0,
            'created_by' => Auth::id(),
            'created_at' => Clock::nowUtc(),
        ]);

        // Avisa si el bloqueo choca con citas ya agendadas.
        $conflicts = QueryBuilder::table('appointments')
            ->where('staff_id', $staffId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('starts_at', '<', $endUtc)
            ->where('ends_at', '>', $startUtc)
            ->whereNull('deleted_at')
            ->count();

        Session::success($conflicts > 0
            ? "Ausencia registrada. ATENCION: hay {$conflicts} cita(s) en ese periodo que debes reprogramar."
            : 'Ausencia registrada.');

        return $this->redirect('/panel/personal/' . $staffId . '/editar');
    }

    public function deleteTimeOff(Request $request): Response
    {
        $this->authorize('personal.horarios');

        $timeOffId = $request->paramInt('timeOffId');
        $staffId = $request->paramInt('id');

        QueryBuilder::table('staff_time_off')
            ->where('id', $timeOffId)
            ->where('staff_id', $staffId)
            ->delete();

        Session::success('Ausencia eliminada.');

        return $this->redirect('/panel/personal/' . $staffId . '/editar');
    }

    private static function isTime(string $value): bool
    {
        return preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $value) === 1;
    }

    private function uniqueSlug(string $base, int $ignoreId): string
    {
        $slug = $base === '' ? 'profesional' : $base;
        $candidate = $slug;
        $suffix = 1;

        while (true) {
            $query = QueryBuilder::table('staff')->where('slug', $candidate);

            if ($ignoreId > 0) {
                $query->where('id', '!=', $ignoreId);
            }

            if (!$query->exists()) {
                return $candidate;
            }

            $candidate = $slug . '-' . (++$suffix);
        }
    }
}
