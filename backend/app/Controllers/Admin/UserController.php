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
use Database\Seeds\InitialSeeder;
use App\Security\Audit;
use App\Security\Auth;
use App\Security\Hash;

/**
 * Usuarios del panel.
 *
 * Da de alta a quien puede entrar a administrar y con que permisos. Es el
 * modulo mas delicado del sistema, asi que lleva sus propias reglas:
 * nadie puede darse mas poder del que tiene, ni dejarse fuera a si mismo,
 * ni dejar el negocio sin ningun administrador.
 */
final class UserController extends AdminController
{
    /** Roles que pueden entrar al panel, de mas a menos poder. */
    private const ROLES = [
        'super_admin' => 'Super administrador (control total)',
        'admin' => 'Administrador',
        'manager' => 'Recepcion',
        'staff' => 'Profesional',
    ];

    public function index(Request $request): Response
    {
        $this->authorize('usuarios.ver');

        $query = QueryBuilder::table('users')
            ->whereIn('role', array_keys(self::ROLES))
            ->whereNull('deleted_at');

        $search = $request->string('q');
        if ($search !== '') {
            $query->search($search, ['first_name', 'last_name', 'email']);
        }

        $query->orderBy('role')->orderBy('first_name');

        return $this->view('admin.users.index', [
            'result' => Model::paginate($query, $this->page($request), 50),
            'filters' => ['q' => $search],
            'roles' => self::ROLES,
            'yoId' => Auth::id(),
            'miRol' => Auth::role(),
        ]);
    }

    public function form(Request $request): Response
    {
        $this->authorize('usuarios.editar');

        $id = $request->paramInt('id');
        $user = $id > 0 ? $this->findPanelUser($id) : null;

        return $this->view('admin.users.form', [
            'user' => $user,
            'roles' => $this->rolesQuePuedoAsignar(),
        ]);
    }

    public function save(Request $request): Response
    {
        $this->authorize('usuarios.editar');

        $id = $request->paramInt('id');
        $esNuevo = $id === 0;

        $data = $this->validate($request, [
            'first_name' => 'required|string|min:2|max:80|no_html',
            'last_name' => 'optional|string|max:80|no_html',
            'email' => 'required|email|max:190',
            'phone' => 'optional|string|max:20|no_html',
            'role' => 'required|string|max:40',
            'password' => ($esNuevo ? 'required' : 'optional') . '|string|password',
        ], [
            'first_name' => 'nombre', 'last_name' => 'apellido', 'email' => 'correo',
            'phone' => 'telefono', 'role' => 'rol', 'password' => 'contrasena',
        ]);

        $rol = (string) $data['role'];

        // Nadie puede crear una cuenta con mas poder del que tiene: si no,
        // un administrador se ascenderia a super administrador creando otro.
        if (!array_key_exists($rol, $this->rolesQuePuedoAsignar())) {
            throw new HttpException(403, 'No puedes asignar ese rol.');
        }

        $existing = $esNuevo ? null : $this->findPanelUser($id);
        $email = (string) $data['email'];

        $duplicado = QueryBuilder::table('users')->where('email', $email)->whereNull('deleted_at');

        if (!$esNuevo) {
            $duplicado->where('id', '!=', $id);
        }

        if ($duplicado->exists()) {
            Session::error("Ya hay una cuenta con el correo {$email}.");

            return $this->redirect($esNuevo ? '/panel/usuarios/nuevo' : '/panel/usuarios/' . $id . '/editar');
        }

        if (!$esNuevo) {
            $this->assertPuedoTocar($existing, 'editar');

            // Bajarse a uno mismo de rol dejaria al negocio sin quien mande
            // si es el ultimo administrador.
            if ($id === Auth::id() && $rol !== (string) $existing['role']) {
                Session::error('No puedes cambiarte el rol a ti mismo. Pideselo a otro administrador.');

                return $this->redirect('/panel/usuarios/' . $id . '/editar');
            }

            if ($rol !== (string) $existing['role']) {
                $this->assertQuedanAdministradores($existing, $rol);
            }
        }

        $payload = [
            'first_name' => $data['first_name'],
            'last_name' => (string) ($data['last_name'] ?? ''),
            'email' => $email,
            'phone' => (string) ($data['phone'] ?? ''),
            'role' => $rol,
            'status' => $request->bool('is_active') ? 'active' : 'blocked',
            'updated_at' => Clock::nowUtc(),
        ];

        $clave = (string) ($data['password'] ?? '');

        if ($clave !== '') {
            $payload['password_hash'] = Hash::make($clave);
            $payload['password_changed_at'] = Clock::nowUtc();

            // Cerrar las sesiones abiertas con la clave vieja.
            $payload['tokens_valid_after'] = Clock::nowUtc();
        }

        if ($esNuevo) {
            $payload['uuid'] = InitialSeeder::uuid4();
            $payload['email_verified_at'] = Clock::nowUtc();
            $payload['referral_code'] = strtoupper(bin2hex(random_bytes(4)));
            $payload['source'] = 'panel';
            $payload['created_at'] = Clock::nowUtc();

            $id = QueryBuilder::table('users')->insert($payload);
            Audit::record('usuario.creado', 'user', $id, null, $this->sinClave($payload), $request);
            Session::success('Usuario creado. Ya puede entrar al panel.');
        } else {
            QueryBuilder::table('users')->where('id', $id)->update($payload);
            Audit::record('usuario.actualizado', 'user', $id, $this->sinClave($existing), $this->sinClave($payload), $request);
            Session::success('Usuario actualizado.');
        }

        return $this->redirect('/panel/usuarios');
    }

    /** Suspende o reactiva el acceso al panel. */
    public function toggle(Request $request): Response
    {
        $this->authorize('usuarios.editar');

        $id = $request->paramInt('id');
        $user = $this->findPanelUser($id);

        $this->assertPuedoTocar($user, 'suspender');

        if ($id === Auth::id()) {
            Session::error('No puedes suspender tu propia cuenta.');

            return $this->redirect('/panel/usuarios');
        }

        $activo = (string) $user['status'] === 'active';

        if ($activo) {
            $this->assertQuedanAdministradores($user, null);
        }

        QueryBuilder::table('users')->where('id', $id)->update([
            'status' => $activo ? 'blocked' : 'active',
            // Al suspender se invalidan sus sesiones y tokens de la app.
            'tokens_valid_after' => $activo ? Clock::nowUtc() : $user['tokens_valid_after'],
            'updated_at' => Clock::nowUtc(),
        ]);

        Audit::record('usuario.estado', 'user', $id, null, ['activo' => !$activo], $request);
        Session::success($activo ? 'Acceso suspendido.' : 'Acceso reactivado.');

        return $this->redirect('/panel/usuarios');
    }

    public function delete(Request $request): Response
    {
        $this->authorize('usuarios.editar');

        $id = $request->paramInt('id');
        $user = $this->findPanelUser($id);

        $this->assertPuedoTocar($user, 'eliminar');

        if ($id === Auth::id()) {
            Session::error('No puedes eliminar tu propia cuenta.');

            return $this->redirect('/panel/usuarios');
        }

        $this->assertQuedanAdministradores($user, null);

        QueryBuilder::table('users')->where('id', $id)->update([
            'deleted_at' => Clock::nowUtc(),
            'status' => 'blocked',
            'tokens_valid_after' => Clock::nowUtc(),
            'updated_at' => Clock::nowUtc(),
        ]);

        Audit::record('usuario.eliminado', 'user', $id, $this->sinClave($user), null, $request);
        Session::success('Usuario eliminado. Ya no puede entrar.');

        return $this->redirect('/panel/usuarios');
    }

    // ---- Reglas de seguridad ---------------------------------------------

    /**
     * Roles que el usuario actual puede repartir: los de su nivel hacia
     * abajo, nunca por encima.
     *
     * @return array<string,string>
     */
    private function rolesQuePuedoAsignar(): array
    {
        $mio = Auth::role();

        if ($mio === 'super_admin') {
            return self::ROLES;
        }

        if ($mio === 'admin') {
            return array_diff_key(self::ROLES, ['super_admin' => '']);
        }

        return [];
    }

    /** Un administrador no puede tocar a un super administrador. */
    private function assertPuedoTocar(array $user, string $accion): void
    {
        if (Auth::role() !== 'super_admin' && (string) $user['role'] === 'super_admin') {
            throw new HttpException(403, "No puedes {$accion} a un super administrador.");
        }
    }

    /**
     * Impide quedarse sin nadie que pueda administrar.
     *
     * @param string|null $nuevoRol rol al que se le va a cambiar, o null si
     *                              se le va a suspender o eliminar
     */
    private function assertQuedanAdministradores(array $user, ?string $nuevoRol): void
    {
        $rolActual = (string) $user['role'];

        if (!in_array($rolActual, ['super_admin', 'admin'], true)) {
            return;
        }

        if ($nuevoRol !== null && in_array($nuevoRol, ['super_admin', 'admin'], true)) {
            return;
        }

        $quedan = QueryBuilder::table('users')
            ->whereIn('role', ['super_admin', 'admin'])
            ->where('status', 'active')
            ->where('id', '!=', (int) $user['id'])
            ->whereNull('deleted_at')
            ->count();

        if ($quedan === 0) {
            throw new HttpException(
                422,
                'Es el unico administrador activo. Crea o activa otro antes de quitarle el acceso, '
                . 'o nadie podra entrar al panel.'
            );
        }
    }

    /** @return array<string,mixed> */
    private function findPanelUser(int $id): array
    {
        $user = QueryBuilder::table('users')
            ->where('id', $id)
            ->whereIn('role', array_keys(self::ROLES))
            ->whereNull('deleted_at')
            ->first();

        if ($user === null) {
            throw new HttpException(404, 'Ese usuario no existe.');
        }

        return $user;
    }

    /**
     * Quita el hash de la contrasena antes de guardarlo en la auditoria:
     * la bitacora la puede leer cualquier administrador.
     *
     * @param array<string,mixed>|null $row
     * @return array<string,mixed>|null
     */
    private function sinClave(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        unset($row['password_hash'], $row['two_factor_secret'], $row['two_factor_recovery']);

        return $row;
    }
}
