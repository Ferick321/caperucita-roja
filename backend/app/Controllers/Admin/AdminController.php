<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Security\Auth;

/** Base de los controladores del panel: comparte datos y comprueba permisos. */
abstract class AdminController extends Controller
{
    /** @param array<string,mixed> $data */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        View::share('adminUser', Auth::user());
        View::share('adminRole', Auth::role());

        return parent::view($template, $data, $status);
    }

    protected function can(string $permission): bool
    {
        return Auth::can($permission);
    }

    protected function authorize(string $permission): void
    {
        Auth::authorize($permission);
    }

    /** Numero de pagina saneado. */
    protected function page(Request $request): int
    {
        return max(1, $request->int('pagina', 1));
    }
}
