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
use App\Security\Audit;
use App\Security\Auth;
use App\Services\LoyaltyService;
use App\Services\MaintenanceService;
use Database\Seeds\InitialSeeder;

/** Clientes: ficha, historial, puntos y derecho al olvido. */
final class ClientController extends AdminController
{
    public function index(Request $request): Response
    {
        $this->authorize('clientes.ver');

        $query = QueryBuilder::table('users')
            ->where('role', 'client')
            ->whereNull('deleted_at');

        $search = $request->string('q');
        if ($search !== '') {
            $query->search($search, ['first_name', 'last_name', 'email', 'phone']);
        }

        $filter = $request->string('filtro');

        match ($filter) {
            'nuevos' => $query->where('total_visits', '<=', 1),
            'frecuentes' => $query->where('total_visits', '>=', 5),
            'inactivos' => $query->whereGroup(static function (\App\Core\QueryBuilder $q): void {
                $q->whereNull('last_visit_at')
                    ->orWhere('last_visit_at', '<', gmdate('Y-m-d H:i:s', time() - 60 * 86400));
            }),
            'marketing' => $query->where('accepts_marketing', 1),
            'bloqueados' => $query->where('status', 'blocked'),
            default => null,
        };

        $query->orderBy($this->safeSort($request->string('orden')), $request->string('dir') === 'asc' ? 'ASC' : 'DESC');

        return $this->view('admin.clients.index', [
            'result' => Model::paginate($query, $this->page($request), 30),
            'filters' => ['q' => $search, 'filtro' => $filter],
            'counts' => [
                'total' => QueryBuilder::table('users')->where('role', 'client')->whereNull('deleted_at')->count(),
                'marketing' => QueryBuilder::table('users')->where('role', 'client')
                    ->where('accepts_marketing', 1)->whereNull('deleted_at')->count(),
            ],
        ]);
    }

    public function show(Request $request): Response
    {
        $this->authorize('clientes.ver');

        $id = $request->paramInt('id');

        $client = QueryBuilder::table('users')
            ->where('id', $id)
            ->where('role', 'client')
            ->whereNull('deleted_at')
            ->first();

        if ($client === null) {
            throw new HttpException(404, 'El cliente no existe.');
        }

        return $this->view('admin.clients.show', [
            'client' => $client,
            'appointments' => QueryBuilder::table('appointments')
                ->select(['appointments.*', 'staff.display_name AS staff_name'])
                ->leftJoin('staff', 'staff.id', '=', 'appointments.staff_id')
                ->where('appointments.client_id', $id)
                ->whereNull('appointments.deleted_at')
                ->orderBy('appointments.starts_at', 'DESC')
                ->limit(30)
                ->get(),
            'payments' => QueryBuilder::table('payments')
                ->where('client_id', $id)
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'DESC')
                ->limit(20)
                ->get(),
            'loyalty' => LoyaltyService::history($id, 20),
        ]);
    }

    public function update(Request $request): Response
    {
        $this->authorize('clientes.editar');

        $id = $request->paramInt('id');

        $data = $this->validate($request, [
            'first_name' => 'required|string|min:2|max:80|no_html',
            'last_name' => 'optional|string|max:80|no_html',
            'phone' => 'optional|phone',
            'birth_date' => 'optional|date',
            'notes' => 'optional|string|max:2000|no_html',
            'status' => 'optional|in:active,pending,blocked',
        ], ['first_name' => 'nombre', 'phone' => 'telefono', 'notes' => 'notas']);

        $before = QueryBuilder::table('users')->where('id', $id)->first();

        if ($before === null || (string) $before['role'] !== 'client') {
            throw new HttpException(404, 'El cliente no existe.');
        }

        $payload = [
            'first_name' => $data['first_name'],
            'last_name' => (string) ($data['last_name'] ?? ''),
            'phone' => (string) ($data['phone'] ?? ''),
            'birth_date' => $data['birth_date'] ?? null,
            'notes' => (string) ($data['notes'] ?? ''),
            'status' => (string) ($data['status'] ?? 'active'),
            'accepts_marketing' => $request->bool('accepts_marketing') ? 1 : 0,
            'updated_at' => Clock::nowUtc(),
        ];

        QueryBuilder::table('users')->where('id', $id)->update($payload);

        [$diffBefore, $diffAfter] = Audit::diff($before, $payload);
        Audit::record('cliente.actualizado', 'user', $id, $diffBefore, $diffAfter, $request);

        Session::success('Ficha del cliente actualizada.');

        return $this->redirect('/panel/clientes/' . $id);
    }

    /** Alta manual desde el mostrador. */
    public function store(Request $request): Response
    {
        $this->authorize('clientes.crear');

        $data = $this->validate($request, [
            'first_name' => 'required|string|min:2|max:80|no_html',
            'last_name' => 'optional|string|max:80|no_html',
            'email' => 'optional|email',
            'phone' => 'required|phone',
        ], ['first_name' => 'nombre', 'phone' => 'telefono', 'email' => 'correo']);

        $email = (string) ($data['email'] ?? '');

        if ($email !== '' && QueryBuilder::table('users')->where('email', $email)->exists()) {
            Session::error('Ya existe una cuenta con ese correo.');

            return $this->back($request, '/panel/clientes');
        }

        // Sin correo se genera uno interno para respetar la restriccion unica.
        if ($email === '') {
            $email = 'cliente-' . bin2hex(random_bytes(6)) . '@local.invalid';
        }

        $id = QueryBuilder::table('users')->insert([
            'uuid' => InitialSeeder::uuid4(),
            'role' => 'client',
            'first_name' => $data['first_name'],
            'last_name' => (string) ($data['last_name'] ?? ''),
            'email' => $email,
            'phone' => (string) $data['phone'],
            'password_hash' => '',
            'status' => 'active',
            'accepts_marketing' => $request->bool('accepts_marketing') ? 1 : 0,
            'referral_code' => strtoupper(bin2hex(random_bytes(4))),
            'source' => 'panel',
            'created_at' => Clock::nowUtc(),
            'updated_at' => Clock::nowUtc(),
        ]);

        Audit::record('cliente.creado', 'user', $id, null, ['origen' => 'panel'], $request);
        Session::success('Cliente registrado.');

        return $this->redirect('/panel/clientes/' . $id);
    }

    public function adjustPoints(Request $request): Response
    {
        $this->authorize('clientes.editar');

        $id = $request->paramInt('id');
        $points = $request->int('points');
        $reason = $request->string('reason', 'Ajuste manual');

        if ($points === 0) {
            Session::error('Indica cuantos puntos sumar o restar.');

            return $this->back($request, '/panel/clientes/' . $id);
        }

        LoyaltyService::grant($id, $points, mb_substr($reason, 0, 160));
        Audit::record('cliente.puntos_ajustados', 'user', $id, null, ['points' => $points, 'reason' => $reason], $request);

        Session::success('Puntos actualizados.');

        return $this->redirect('/panel/clientes/' . $id);
    }

    /** Borrado logico: el cliente deja de aparecer pero se conserva el historial. */
    public function delete(Request $request): Response
    {
        $this->authorize('clientes.eliminar');

        $id = $request->paramInt('id');

        QueryBuilder::table('users')->where('id', $id)->where('role', 'client')->update([
            'deleted_at' => Clock::nowUtc(),
            'status' => 'blocked',
            'tokens_valid_after' => Clock::nowUtc(),
            'updated_at' => Clock::nowUtc(),
        ]);

        Audit::record('cliente.eliminado', 'user', $id, null, null, $request);
        Session::success('Cliente desactivado. Puedes eliminarlo definitivamente desde Sistema > Mantenimiento.');

        return $this->redirect('/panel/clientes');
    }

    /**
     * Eliminacion definitiva (derecho al olvido).
     *
     * Borra la cuenta, la foto y los comprobantes, y anonimiza el historico.
     */
    public function forget(Request $request): Response
    {
        Auth::authorize('clientes.eliminar');
        Auth::authorize('sistema.mantenimiento');

        if ($request->string('confirm') !== 'ELIMINAR') {
            Session::error('Escribe ELIMINAR para confirmar la eliminacion definitiva.');

            return $this->back($request, '/panel/clientes');
        }

        MaintenanceService::forgetClient($request->paramInt('id'), Auth::id());

        Session::success('Datos personales del cliente eliminados de forma definitiva.');

        return $this->redirect('/panel/clientes');
    }

    /** Exportacion en CSV de la base de clientes. */
    public function export(Request $request): Response
    {
        $this->authorize('clientes.exportar');

        $clients = QueryBuilder::table('users')
            ->select(['first_name', 'last_name', 'email', 'phone', 'total_visits',
                      'total_spent', 'loyalty_points', 'accepts_marketing', 'created_at', 'last_visit_at'])
            ->where('role', 'client')
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'DESC')
            ->limit(50000)
            ->get();

        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new HttpException(500, 'No se pudo generar el archivo.');
        }

        // BOM para que Excel reconozca los acentos.
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            'Nombre', 'Apellido', 'Correo', 'Telefono', 'Visitas',
            'Total gastado', 'Puntos', 'Acepta publicidad', 'Alta', 'Ultima visita',
        ], ';');

        foreach ($clients as $client) {
            fputcsv($handle, [
                $client['first_name'],
                $client['last_name'],
                str_ends_with((string) $client['email'], '@local.invalid') ? '' : $client['email'],
                $client['phone'],
                $client['total_visits'],
                $client['total_spent'],
                $client['loyalty_points'],
                (bool) $client['accepts_marketing'] ? 'Si' : 'No',
                local_datetime((string) $client['created_at'], 'd/m/Y'),
                $client['last_visit_at'] === null ? '' : local_datetime((string) $client['last_visit_at'], 'd/m/Y'),
            ], ';');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        Audit::record('clientes.exportados', 'user', null, null, ['total' => count($clients)], $request);

        return Response::make($csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="clientes-' . date('Y-m-d') . '.csv"')
            ->header('Cache-Control', 'no-store');
    }

    private function safeSort(string $column): string
    {
        return in_array($column, ['created_at', 'last_visit_at', 'total_visits', 'total_spent', 'first_name'], true)
            ? $column
            : 'created_at';
    }
}
