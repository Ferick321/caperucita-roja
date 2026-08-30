<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * Comprobacion granular de permisos.
 *
 * Las rutas declaran "can:citas.editar"; el enrutador crea el middleware sin
 * argumentos, asi que el permiso se extrae del alias registrado.
 */
final class RequirePermission implements MiddlewareInterface
{
    private string $permission;

    public function __construct(string $permission = '')
    {
        $this->permission = $permission;
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->permission !== '') {
            \App\Security\Auth::authorize($this->permission);
        }

        return $next($request);
    }
}
