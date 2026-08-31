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
use App\Services\MediaService;

/**
 * Sucursales: locales, horario semanal y dias cerrados.
 *
 * Es el modulo que manda sobre la agenda: el motor de disponibilidad parte
 * del horario de la sucursal antes de mirar el del profesional, asi que un
 * cambio aqui se nota en el acto en la web y en la app.
 */
final class BranchController extends AdminController
{
    /** Dias de la semana como los guarda branch_hours (0 = domingo). */
    private const WEEKDAYS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miercoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sabado',
        0 => 'Domingo',
    ];

    public function index(Request $request): Response
    {
        $this->authorize('sucursales.ver');

        $branches = QueryBuilder::table('branches')
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        foreach ($branches as $index => $branch) {
            $branches[$index]['staff_count'] = QueryBuilder::table('staff')
                ->where('branch_id', (int) $branch['id'])
                ->whereNull('deleted_at')
                ->count();

            $branches[$index]['open_days'] = QueryBuilder::table('branch_hours')
                ->where('branch_id', (int) $branch['id'])
                ->where('is_closed', 0)
                ->count();
        }

        return $this->view('admin.branches.index', ['branches' => $branches]);
    }

    public function form(Request $request): Response
    {
        $this->authorize('sucursales.editar');

        $id = $request->paramInt('id');
        $branch = $id > 0 ? $this->findBranch($id) : null;

        return $this->view('admin.branches.form', [
            'branch' => $branch,
            'weekdays' => self::WEEKDAYS,
            'hours' => $id > 0 ? $this->hoursByDay($id) : [],
            'closures' => $id > 0
                ? QueryBuilder::table('branch_closures')
                    ->where('branch_id', $id)
                    ->orderBy('starts_on', 'DESC')
                    ->limit(50)
                    ->get()
                : [],
            'timezones' => \DateTimeZone::listIdentifiers(),
        ]);
    }

    public function save(Request $request): Response
    {
        $this->authorize('sucursales.editar');

        $id = $request->paramInt('id');

        $data = $this->validate($request, [
            'name' => 'required|string|min:2|max:120|no_html',
            'address' => 'optional|string|max:255|no_html',
            'city' => 'optional|string|max:100|no_html',
            'phone' => 'optional|string|max:30|no_html',
            'whatsapp' => 'optional|string|max:30|no_html',
            'email' => 'optional|email|max:190',
            'maps_url' => 'optional|url|max:500',
            'latitude' => 'optional|numeric|between:-90,90',
            'longitude' => 'optional|numeric|between:-180,180',
            'timezone' => 'optional|string|max:64',
            'sort_order' => 'optional|int|between:0,9999',
        ], [
            'name' => 'nombre', 'address' => 'direccion', 'city' => 'ciudad',
            'phone' => 'telefono', 'maps_url' => 'enlace del mapa',
            'timezone' => 'zona horaria',
        ]);

        $existing = $id > 0 ? $this->findBranch($id) : null;

        $timezone = (string) ($data['timezone'] ?? '');
        if ($timezone !== '' && !in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            throw new HttpException(422, 'Esa zona horaria no existe.');
        }

        $payload = [
            'name' => $data['name'],
            'slug' => $this->uniqueBranchSlug(Url::slug((string) $data['name']), $id),
            'address' => (string) ($data['address'] ?? ''),
            'city' => (string) ($data['city'] ?? ''),
            'phone' => (string) ($data['phone'] ?? ''),
            'whatsapp' => (string) ($data['whatsapp'] ?? ''),
            'email' => (string) ($data['email'] ?? ''),
            'maps_url' => (string) ($data['maps_url'] ?? ''),
            'latitude' => ($data['latitude'] ?? '') !== '' ? (float) $data['latitude'] : null,
            'longitude' => ($data['longitude'] ?? '') !== '' ? (float) $data['longitude'] : null,
            'timezone' => $timezone !== '' ? $timezone : 'America/Guayaquil',
            'is_active' => $request->bool('is_active') ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'updated_at' => Clock::nowUtc(),
        ];

        if ($request->hasFile('photo')) {
            $payload['photo_path'] = MediaService::replace(
                (string) ($existing['photo_path'] ?? ''),
                (array) $request->file('photo'),
                'sucursales',
                Auth::id(),
                1400
            );
        }

        if ($id > 0) {
            QueryBuilder::table('branches')->where('id', $id)->update($payload);
            Audit::record('sucursal.actualizada', 'branch', $id, $existing, $payload, $request);
        } else {
            $payload['is_default'] = QueryBuilder::table('branches')->whereNull('deleted_at')->exists() ? 0 : 1;
            $payload['created_at'] = Clock::nowUtc();
            $id = QueryBuilder::table('branches')->insert($payload);

            // Una sucursal sin horario no ofrece ni una sola cita, asi que se
            // crea de lunes a sabado y el duenio ajusta desde ahi.
            $this->seedDefaultHours($id);

            Audit::record('sucursal.creada', 'branch', $id, null, $payload, $request);
        }

        Session::success('Sucursal guardada.');

        return $this->redirect('/panel/sucursales/' . $id . '/editar');
    }

    /** Marca la sucursal como la principal del negocio. */
    public function makeDefault(Request $request): Response
    {
        $this->authorize('sucursales.editar');

        $id = $request->paramInt('id');
        $this->findBranch($id);

        Database::instance()->transaction(static function () use ($id): void {
            QueryBuilder::table('branches')->where('id', '!=', 0)->update(['is_default' => 0]);
            QueryBuilder::table('branches')->where('id', $id)->update(['is_default' => 1]);
        });

        Audit::record('sucursal.principal', 'branch', $id, null, null, $request);
        Session::success('Esa sucursal es ahora la principal.');

        return $this->redirect('/panel/sucursales');
    }

    public function delete(Request $request): Response
    {
        $this->authorize('sucursales.editar');

        $id = $request->paramInt('id');
        $branch = $this->findBranch($id);

        if ((bool) $branch['is_default']) {
            Session::error('No puedes eliminar la sucursal principal. Marca otra como principal antes.');

            return $this->redirect('/panel/sucursales');
        }

        $activas = QueryBuilder::table('appointments')
            ->where('branch_id', $id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereNull('deleted_at')
            ->count();

        if ($activas > 0) {
            Session::error("Esa sucursal tiene {$activas} cita(s) por atender. Atiendelas o cambialas de local antes de eliminarla.");

            return $this->redirect('/panel/sucursales');
        }

        QueryBuilder::table('branches')->where('id', $id)->update([
            'deleted_at' => Clock::nowUtc(),
            'is_active' => 0,
            'updated_at' => Clock::nowUtc(),
        ]);

        Audit::record('sucursal.eliminada', 'branch', $id, $branch, null, $request);
        Session::success('Sucursal eliminada. Puedes borrarla del todo desde Mantenimiento.');

        return $this->redirect('/panel/sucursales');
    }

    // ---- Horario semanal -------------------------------------------------

    /** Guarda de una vez los siete dias de la semana. */
    public function saveHours(Request $request): Response
    {
        $this->authorize('sucursales.editar');

        $id = $request->paramInt('id');
        $this->findBranch($id);

        // Se usan nombres planos (abre_1, cierra_1...) igual que en el horario
        // del personal: Request::array reindexa y perderia el numero del dia.
        foreach (array_keys(self::WEEKDAYS) as $dia) {
            $estaCerrado = $request->bool('cerrado_' . $dia);

            $horario = [
                'opens_at' => $this->hora($request->string('abre_' . $dia), '09:00:00'),
                'closes_at' => $this->hora($request->string('cierra_' . $dia), '19:00:00'),
                'break_start' => $this->hora($request->string('descanso_ini_' . $dia), null),
                'break_end' => $this->hora($request->string('descanso_fin_' . $dia), null),
                'is_closed' => $estaCerrado ? 1 : 0,
            ];

            // Un horario invertido dejaria el dia sin turnos sin avisar.
            if (!$estaCerrado && $horario['closes_at'] <= $horario['opens_at']) {
                Session::error(
                    'En ' . self::WEEKDAYS[$dia] . ' la hora de cierre debe ser posterior a la de apertura.'
                );

                return $this->redirect('/panel/sucursales/' . $id . '/editar');
            }

            if ($horario['break_start'] !== null && $horario['break_end'] !== null
                && $horario['break_end'] <= $horario['break_start']) {
                Session::error('En ' . self::WEEKDAYS[$dia] . ' el descanso termina antes de empezar.');

                return $this->redirect('/panel/sucursales/' . $id . '/editar');
            }

            $existe = QueryBuilder::table('branch_hours')
                ->where('branch_id', $id)
                ->where('weekday', $dia)
                ->first();

            if ($existe !== null) {
                QueryBuilder::table('branch_hours')->where('id', (int) $existe['id'])->update($horario);
            } else {
                $horario['branch_id'] = $id;
                $horario['weekday'] = $dia;
                QueryBuilder::table('branch_hours')->insert($horario);
            }
        }

        Audit::record('sucursal.horario', 'branch', $id, null, null, $request);
        Session::success('Horario actualizado. Ya se aplica a la web y a la app.');

        return $this->redirect('/panel/sucursales/' . $id . '/editar');
    }

    // ---- Feriados y cierres ----------------------------------------------

    public function addClosure(Request $request): Response
    {
        $this->authorize('sucursales.editar');

        $id = $request->paramInt('id');
        $this->findBranch($id);

        $data = $this->validate($request, [
            'starts_on' => 'required|date',
            'ends_on' => 'required|date',
            'reason' => 'optional|string|max:160|no_html',
        ], [
            'starts_on' => 'fecha de inicio', 'ends_on' => 'fecha de fin', 'reason' => 'motivo',
        ]);

        if ((string) $data['ends_on'] < (string) $data['starts_on']) {
            Session::error('La fecha de fin no puede ser anterior a la de inicio.');

            return $this->redirect('/panel/sucursales/' . $id . '/editar');
        }

        QueryBuilder::table('branch_closures')->insert([
            'branch_id' => $id,
            'starts_on' => (string) $data['starts_on'],
            'ends_on' => (string) $data['ends_on'],
            'reason' => (string) ($data['reason'] ?? ''),
            'created_at' => Clock::nowUtc(),
        ]);

        Audit::record('sucursal.cierre_agregado', 'branch', $id, null, $data, $request);
        Session::success('Dias cerrados guardados. Esas fechas ya no aceptan citas.');

        return $this->redirect('/panel/sucursales/' . $id . '/editar');
    }

    public function deleteClosure(Request $request): Response
    {
        $this->authorize('sucursales.editar');

        $id = $request->paramInt('id');
        $closureId = $request->paramInt('closureId');

        QueryBuilder::table('branch_closures')
            ->where('id', $closureId)
            ->where('branch_id', $id)
            ->delete();

        Audit::record('sucursal.cierre_eliminado', 'branch', $id, null, ['cierre' => $closureId], $request);
        Session::success('Cierre eliminado. Esos dias vuelven a estar disponibles.');

        return $this->redirect('/panel/sucursales/' . $id . '/editar');
    }

    // ---- Apoyo -----------------------------------------------------------

    /** @return array<string,mixed> */
    private function findBranch(int $id): array
    {
        $branch = QueryBuilder::table('branches')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if ($branch === null) {
            throw new HttpException(404, 'Esa sucursal no existe.');
        }

        return $branch;
    }

    /** @return array<int,array<string,mixed>> */
    private function hoursByDay(int $branchId): array
    {
        $rows = QueryBuilder::table('branch_hours')->where('branch_id', $branchId)->get();
        $byDay = [];

        foreach ($rows as $row) {
            $byDay[(int) $row['weekday']] = $row;
        }

        return $byDay;
    }

    /** Normaliza una hora del formulario; devuelve null si viene vacia. */
    private function hora(mixed $value, ?string $default): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        if ($value === '') {
            return $default;
        }

        if (preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $value) !== 1) {
            return $default;
        }

        return mb_strlen($value) === 5 ? $value . ':00' : $value;
    }

    private function seedDefaultHours(int $branchId): void
    {
        foreach (array_keys(self::WEEKDAYS) as $dia) {
            QueryBuilder::table('branch_hours')->insert([
                'branch_id' => $branchId,
                'weekday' => $dia,
                'opens_at' => '09:00:00',
                'closes_at' => '19:00:00',
                'break_start' => null,
                'break_end' => null,
                'is_closed' => $dia === 0 ? 1 : 0,
            ]);
        }
    }

    private function uniqueBranchSlug(string $base, int $ignoreId): string
    {
        $slug = $base === '' ? 'sucursal' : $base;
        $candidate = $slug;
        $suffix = 1;

        while (true) {
            $query = QueryBuilder::table('branches')->where('slug', $candidate);

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
