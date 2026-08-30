<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;

/**
 * CORS para la API.
 *
 * Nunca responde "*" con credenciales: solo refleja origenes que esten en la
 * lista blanca de configuracion (la app movil no envia Origin, por lo que no
 * necesita entrada alguna).
 */
final class Cors implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $origin = (string) $request->header('origin', '');
        $allowed = array_map('strtolower', (array) Config::get('api.allowed_origins', []));

        $response = $request->method() === 'OPTIONS'
            ? Response::noContent()
            : $next($request);

        if ($origin !== '' && in_array(strtolower($origin), $allowed, true)) {
            $response
                ->header('Access-Control-Allow-Origin', $origin)
                ->header('Vary', 'Origin')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-CSRF-Token, X-App-Version')
                ->header('Access-Control-Max-Age', '600');
        }

        return $response;
    }
}
