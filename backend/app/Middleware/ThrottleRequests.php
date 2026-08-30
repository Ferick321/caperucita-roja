<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Security\Auth;
use App\Security\RateLimiter;

/**
 * Limitador generico: "throttle:60,60" = 60 peticiones por 60 segundos.
 * La clave combina usuario autenticado (si lo hay) e IP.
 */
final class ThrottleRequests implements MiddlewareInterface
{
    private int $maxAttempts;

    private int $decaySeconds;

    public function __construct(string $options = '60,60')
    {
        [$max, $decay] = array_pad(explode(',', $options), 2, '60');

        $this->maxAttempts = max(1, (int) $max);
        $this->decaySeconds = max(1, (int) $decay);
    }

    public function handle(Request $request, callable $next): Response
    {
        $identity = Auth::id() !== null ? 'u' . Auth::id() : 'ip' . $request->ip();
        $key = 'throttle:' . $identity . ':' . $request->method() . ':' . $request->path();

        $result = RateLimiter::hit($key, $this->maxAttempts, $this->decaySeconds);

        if (!$result['allowed']) {
            throw new HttpException(429, sprintf(
                'Has hecho demasiadas peticiones. Vuelve a intentarlo en %d segundos.',
                $result['retry_after']
            ));
        }

        $response = $next($request);

        return $response
            ->header('X-RateLimit-Limit', (string) $this->maxAttempts)
            ->header('X-RateLimit-Remaining', (string) $result['remaining']);
    }
}
