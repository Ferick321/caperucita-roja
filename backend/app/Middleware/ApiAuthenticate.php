<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Security\Auth;
use App\Security\Jwt;

/** Autenticacion de la API por token Bearer (JWT). */
final class ApiAuthenticate implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null) {
            throw new HttpException(401, 'Falta el token de acceso.');
        }

        $claims = Jwt::verify($token);

        if ($claims === null || ($claims['type'] ?? '') !== 'access') {
            throw new HttpException(401, 'Token invalido o expirado.');
        }

        $userId = (int) ($claims['sub'] ?? 0);

        if ($userId <= 0) {
            throw new HttpException(401, 'Token invalido.');
        }

        $user = QueryBuilder::table('users')
            ->where('id', $userId)
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->first();

        if ($user === null) {
            throw new HttpException(401, 'La cuenta ya no esta disponible.');
        }

        // Invalida tokens emitidos antes de un cambio de contrasena o cierre global.
        $invalidBefore = $user['tokens_valid_after'] ?? null;

        if ($invalidBefore !== null && (int) ($claims['iat'] ?? 0) < strtotime((string) $invalidBefore)) {
            throw new HttpException(401, 'Sesion cerrada. Inicia sesion de nuevo.');
        }

        Auth::setApiUser($user);

        return $next($request);
    }
}
