<?php

declare(strict_types=1);

namespace App\Core;

/** Utilidades de URL con foco en evitar redirecciones abiertas. */
final class Url
{
    public static function base(): string
    {
        return rtrim((string) Config::get('app.url', ''), '/');
    }

    public static function to(string $path = '/'): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        return self::base() . '/' . ltrim($path, '/');
    }

    /**
     * Solo permite redirigir a rutas internas o a hosts explicitamente
     * autorizados. Cualquier otra cosa cae en la pagina de inicio.
     */
    public static function safeRedirect(string $target): string
    {
        $target = trim($target);

        if ($target === '') {
            return self::to('/');
        }

        // Ruta relativa interna: aceptada (rechaza "//evil.com" y "/\evil.com").
        if (str_starts_with($target, '/')
            && !str_starts_with($target, '//')
            && !str_starts_with($target, '/\\')
        ) {
            return $target;
        }

        $host = parse_url($target, PHP_URL_HOST);

        if (is_string($host)) {
            $allowed = array_filter([
                parse_url(self::base(), PHP_URL_HOST),
                ...(array) Config::get('app.allowed_redirect_hosts', []),
            ]);

            if (in_array(strtolower($host), array_map('strtolower', $allowed), true)) {
                return $target;
            }
        }

        Logger::warning('Redireccion bloqueada', ['target' => mb_substr($target, 0, 200)]);

        return self::to('/');
    }

    /** @param array<string,scalar|null> $params */
    public static function withQuery(string $path, array $params): string
    {
        $filtered = array_filter($params, static fn (mixed $v): bool => $v !== null && $v !== '');

        if ($filtered === []) {
            return $path;
        }

        $separator = str_contains($path, '?') ? '&' : '?';

        return $path . $separator . http_build_query($filtered);
    }

    public static function slug(string $text, int $maxLength = 120): string
    {
        $text = trim($text);

        if (function_exists('transliterator_transliterate')) {
            $converted = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
            if (is_string($converted)) {
                $text = $converted;
            }
        } else {
            $text = strtr(mb_strtolower($text), [
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
                'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
                'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
                'ñ' => 'n', 'ç' => 'c',
            ]);
        }

        $text = preg_replace('/[^a-z0-9]+/i', '-', mb_strtolower($text)) ?? '';
        $text = trim($text, '-');

        return mb_substr($text === '' ? 'item' : $text, 0, $maxLength);
    }
}
