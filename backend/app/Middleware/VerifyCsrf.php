<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Security\Csrf;

/** Exige token anti-CSRF valido en toda peticion que modifique estado. */
final class VerifyCsrf implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        if (!Csrf::verify($request)) {
            Logger::warning('Peticion rechazada por CSRF', [
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            throw new HttpException(
                419,
                'Tu sesion expiro o el formulario no es valido. Recarga la pagina e intentalo de nuevo.'
            );
        }

        return $next($request);
    }
}
