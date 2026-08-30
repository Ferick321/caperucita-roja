<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Security\Auth;

/**
 * Acceso al panel: sesion valida, rol operativo, segundo factor superado y
 * (opcionalmente) IP dentro de la lista autorizada.
 */
final class RequireAdmin implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (!Auth::check()) {
            return (new Authenticate())->handle($request, $next);
        }

        $allowlist = array_filter((array) Config::get('security.admin_ip_allowlist', []));

        if ($allowlist !== [] && !in_array($request->ip(), $allowlist, true)) {
            Logger::warning('Acceso al panel desde IP no autorizada', [
                'ip' => $request->ip(),
                'user' => Auth::id(),
            ]);

            throw new HttpException(403, 'Acceso restringido desde esta red.');
        }

        if (!Auth::isStaff()) {
            throw new HttpException(403, 'Esta area es solo para el personal del negocio.');
        }

        if (!Auth::twoFactorSatisfied()) {
            return Response::redirect('/panel/verificacion');
        }

        return $next($request);
    }
}
