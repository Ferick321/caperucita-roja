<?php

declare(strict_types=1);

use App\Core\Clock;
use App\Core\Config;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;
use App\Security\Csrf;
use App\Services\SettingsService;

if (!function_exists('e')) {
    /** Escape para contexto HTML. Es el unico modo permitido de imprimir datos en vistas. */
    function e(mixed $value): string
    {
        if ($value === null || is_bool($value) || is_array($value)) {
            $value = is_bool($value) ? ($value ? '1' : '') : (is_array($value) ? '' : '');
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('e_attr')) {
    /** Escape para atributos HTML sin comillas o con comillas simples. */
    function e_attr(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('e_js')) {
    /** Serializa un valor para incrustarlo con seguridad en un bloque <script>. */
    function e_js(mixed $value): string
    {
        $json = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
        );

        return $json === false ? 'null' : $json;
    }
}

if (!function_exists('e_url')) {
    /** Solo deja pasar esquemas seguros; bloquea javascript:, data:, vbscript:. */
    function e_url(mixed $value): string
    {
        $url = trim((string) $value);

        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return e($url);
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (!in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
            return '#';
        }

        return e($url);
    }
}

if (!function_exists('setting')) {
    /** Lee un ajuste editable desde el panel de administracion. */
    function setting(string $key, mixed $default = null): mixed
    {
        return SettingsService::get($key, $default);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('url')) {
    function url(string $path = '/'): string
    {
        return Url::to($path);
    }
}

if (!function_exists('asset')) {
    /** URL de un recurso estatico con cache-busting por fecha de modificacion. */
    function asset(string $path): string
    {
        $relative = '/assets/' . ltrim($path, '/');
        $file = dirname(__DIR__, 2) . '/public' . $relative;
        $version = is_file($file) ? (string) filemtime($file) : (string) Config::get('app.version', '1');

        return Url::to($relative) . '?v=' . $version;
    }
}

if (!function_exists('media_url')) {
    /** URL publica de un archivo subido (se sirve por controlador, no desde disco). */
    function media_url(?string $storedPath, string $variant = ''): string
    {
        if ($storedPath === null || $storedPath === '') {
            return Url::to('/assets/img/placeholder.svg');
        }

        if (preg_match('#^https?://#i', $storedPath) === 1) {
            return $storedPath;
        }

        $query = $variant !== '' ? '?v=' . rawurlencode($variant) : '';

        return Url::to('/media/' . ltrim($storedPath, '/')) . $query;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    /** Campo oculto con el token anti-CSRF; obligatorio en TODO formulario. */
    function csrf_field(): string
    {
        return '<input type="hidden" name="' . e(Csrf::FIELD) . '" value="' . e(Csrf::token()) . '">';
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('honeypot_field')) {
    /** Trampa para bots: un humano nunca rellena este campo (oculto por CSS). */
    function honeypot_field(): string
    {
        return '<div class="hp-field" aria-hidden="true">'
            . '<label>No rellenar<input type="text" name="website_url" tabindex="-1" autocomplete="off"></label>'
            . '<input type="hidden" name="form_rendered_at" value="' . e((string) time()) . '">'
            . '</div>';
    }
}

if (!function_exists('old')) {
    /** Repuebla un formulario tras un error de validacion. */
    function old(string $key, mixed $default = ''): mixed
    {
        $old = View::sharedData()['old'] ?? [];

        return is_array($old) ? ($old[$key] ?? $default) : $default;
    }
}

if (!function_exists('field_error')) {
    function field_error(string $key): string
    {
        $errors = View::sharedData()['errors'] ?? [];

        if (!is_array($errors) || !isset($errors[$key][0])) {
            return '';
        }

        return '<p class="field-error" role="alert">' . e($errors[$key][0]) . '</p>';
    }
}

if (!function_exists('money')) {
    /** Formatea un importe con la moneda configurada en el panel. */
    function money(float|int|string $amount, ?string $currency = null): string
    {
        $amount = (float) $amount;
        $symbol = $currency ?? (string) setting('business.currency_symbol', '$');
        $decimals = (int) setting('business.currency_decimals', 2);
        $position = (string) setting('business.currency_position', 'before');

        $formatted = number_format(
            $amount,
            $decimals,
            (string) setting('business.decimal_separator', '.'),
            (string) setting('business.thousand_separator', ',')
        );

        return $position === 'after' ? $formatted . ' ' . $symbol : $symbol . ' ' . $formatted;
    }
}

if (!function_exists('minutes_to_human')) {
    function minutes_to_human(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' min';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest === 0 ? $hours . ' h' : $hours . ' h ' . $rest . ' min';
    }
}

if (!function_exists('local_datetime')) {
    /** Convierte una fecha UTC de la base de datos a la zona horaria del negocio. */
    function local_datetime(?string $utc, string $format = 'd/m/Y H:i'): string
    {
        if ($utc === null || $utc === '') {
            return '-';
        }

        return Clock::utcToLocal($utc, $format);
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = []): string
    {
        return View::render($template, $data);
    }
}

if (!function_exists('flash_success')) {
    function flash_success(string $message): void
    {
        Session::success($message);
    }
}

if (!function_exists('flash_error')) {
    function flash_error(string $message): void
    {
        Session::error($message);
    }
}

if (!function_exists('array_get')) {
    function array_get(array $array, string $key, mixed $default = null): mixed
    {
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }

            $array = $array[$segment];
        }

        return $array;
    }
}

if (!function_exists('str_limit')) {
    function str_limit(string $value, int $limit = 120, string $end = '...'): string
    {
        return mb_strlen($value) <= $limit ? $value : mb_substr($value, 0, $limit) . $end;
    }
}

if (!function_exists('initials')) {
    function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = array_map(static fn (string $p): string => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($parts, 0, 2));

        return implode('', $letters) ?: '?';
    }
}
