<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;

/** Redirige a HTTPS cuando el entorno lo exige. */
final class ForceHttps implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if ((bool) Config::get('app.force_https', false) && !$request->isSecure()) {
            return Response::redirect(Url::to($request->path()), 301);
        }

        return $next($request);
    }
}
