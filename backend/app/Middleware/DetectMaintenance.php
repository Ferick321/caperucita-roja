<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Security\Auth;
use App\Services\SettingsService;

/**
 * Modo mantenimiento activable desde el panel: el sitio publico muestra un
 * aviso configurable mientras el personal sigue trabajando con normalidad.
 */
final class DetectMaintenance implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $enabled = (bool) SettingsService::get('system.maintenance_mode', false);

        if (!$enabled || Auth::isStaff() || str_starts_with($request->path(), '/panel')) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            return Response::apiError(
                (string) SettingsService::get('system.maintenance_message', 'Estamos en mantenimiento.'),
                503
            );
        }

        return Response::html(
            \App\Core\View::render('errors.maintenance', [
                'message' => (string) SettingsService::get(
                    'system.maintenance_message',
                    'Estamos realizando mejoras. Volvemos en unos minutos.'
                ),
            ]),
            503
        )->header('Retry-After', '600');
    }
}
