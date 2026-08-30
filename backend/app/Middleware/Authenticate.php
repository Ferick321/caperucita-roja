<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Security\Auth;

/** Exige sesion iniciada (sitio web y panel). */
final class Authenticate implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (!Auth::check()) {
            if ($request->wantsJson()) {
                throw new HttpException(401, 'Debes iniciar sesion.');
            }

            Session::put('__intended', $request->path());
            Session::error('Inicia sesion para continuar.');

            $isAdminArea = str_starts_with($request->path(), '/panel');

            return Response::redirect($isAdminArea ? '/panel/acceso' : '/ingresar');
        }

        return $next($request);
    }
}
