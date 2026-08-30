<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Security\Audit;
use App\Security\Auth;

/** Acceso al panel del personal. */
final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        if (Auth::check() && Auth::isStaff() && Auth::twoFactorSatisfied()) {
            return $this->redirect('/panel');
        }

        return $this->view('admin.login');
    }

    public function login(Request $request): Response
    {
        $data = $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required|string|max:200',
        ], ['email' => 'correo', 'password' => 'contrasena']);

        $result = Auth::attempt($data['email'], (string) $data['password'], $request);
        $user = $result['user'];

        if (!in_array((string) $user['role'], ['super_admin', 'admin', 'manager', 'staff'], true)) {
            Audit::record('panel.acceso_denegado', 'user', (int) $user['id'], null, null, $request, (int) $user['id']);
            Session::error('Esta cuenta no tiene acceso al panel.');

            return $this->redirect('/panel/acceso');
        }

        if ($result['needs_2fa']) {
            Session::put('__2fa_pending_user', (int) $user['id']);
            Session::put('__2fa_target', '/panel');

            return $this->redirect('/panel/verificacion');
        }

        Auth::login($user);
        Audit::record('panel.acceso', 'user', (int) $user['id'], null, null, $request, (int) $user['id']);

        return $this->redirect('/panel');
    }

    public function logout(Request $request): Response
    {
        $userId = Auth::id();
        Auth::logout();

        if ($userId !== null) {
            Audit::record('panel.salida', 'user', $userId, null, null, $request, $userId);
        }

        return $this->redirect('/panel/acceso');
    }
}
