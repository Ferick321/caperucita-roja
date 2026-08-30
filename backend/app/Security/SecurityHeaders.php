<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;

/**
 * Cabeceras de seguridad de la respuesta.
 *
 * La politica de contenido usa un "nonce" por peticion: solo se ejecutan los
 * <script> que lo lleven, de modo que un XSS inyectado no puede ejecutarse.
 */
final class SecurityHeaders
{
    private static string $nonce = '';

    public static function nonce(): string
    {
        if (self::$nonce === '') {
            self::$nonce = base64_encode(random_bytes(16));
        }

        return self::$nonce;
    }

    public static function apply(Response $response, Request $request, bool $isAdmin = false): Response
    {
        $nonce = self::nonce();

        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'nonce-{$nonce}'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "media-src 'self'",
            "manifest-src 'self'",
            "worker-src 'self'",
        ];

        // Dominios extra autorizados desde la configuracion (mapas, analitica...).
        foreach ((array) Config::get('security.csp_extra', []) as $directive => $sources) {
            if (!is_string($directive) || !is_array($sources) || $sources === []) {
                continue;
            }

            foreach ($directives as $index => $existing) {
                if (str_starts_with($existing, $directive . ' ')) {
                    $directives[$index] = $existing . ' ' . implode(' ', $sources);
                }
            }
        }

        if ($request->isSecure()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        $response->header('Content-Security-Policy', implode('; ', $directives));
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', 'DENY');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->header('Cross-Origin-Opener-Policy', 'same-origin');
        $response->header('Cross-Origin-Resource-Policy', 'same-site');
        $response->header('X-Permitted-Cross-Domain-Policies', 'none');
        $response->header(
            'Permissions-Policy',
            'accelerometer=(), autoplay=(), camera=(self), display-capture=(), '
            . 'encrypted-media=(), fullscreen=(self), geolocation=(self), gyroscope=(), '
            . 'magnetometer=(), microphone=(), midi=(), payment=(), usb=()'
        );

        if ($request->isSecure()) {
            $response->header(
                'Strict-Transport-Security',
                'max-age=' . (int) Config::get('security.hsts_max_age', 31536000) . '; includeSubDomains; preload'
            );
        }

        // El panel jamas debe quedar en cache de navegador ni de proxy.
        if ($isAdmin) {
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->header('Pragma', 'no-cache');
        }

        return $response;
    }
}
