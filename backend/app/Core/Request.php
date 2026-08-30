<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Peticion HTTP inmutable.
 *
 * Toda lectura de entrada del usuario pasa por aqui; nunca se accede a
 * $_GET/$_POST directamente desde controladores.
 */
final class Request
{
    /** @var array<string,mixed> */
    private array $query;

    /** @var array<string,mixed> */
    private array $body;

    /** @var array<string,mixed> */
    private array $files;

    /** @var array<string,string> */
    private array $headers;

    /** @var array<string,string> */
    private array $routeParams = [];

    private string $method;

    private string $path;

    private string $rawBody;

    public function __construct(
        array $query,
        array $body,
        array $files,
        array $server,
        string $rawBody = ''
    ) {
        $this->query = $query;
        $this->body = $body;
        $this->files = $files;
        $this->rawBody = $rawBody;
        $this->headers = self::extractHeaders($server);

        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));

        // Method override solo desde POST (formularios HTML no envian PUT/DELETE).
        if ($method === 'POST') {
            $override = strtoupper((string) ($body['_method'] ?? ''));
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        $this->method = $method;

        $uri = (string) ($server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $this->path = '/' . trim(is_string($path) ? $path : '/', '/');
    }

    public static function capture(): self
    {
        $raw = (string) file_get_contents('php://input');
        $body = $_POST;

        // Cuerpo JSON (la app movil habla JSON).
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if ($raw !== '' && str_contains($contentType, 'application/json')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        return new self($_GET, $body, $_FILES, $_SERVER, $raw);
    }

    /** @return array<string,string> */
    private static function extractHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr((string) $key, 5)));
                $headers[$name] = (string) $value;
            }
        }

        foreach (['CONTENT_TYPE' => 'content-type', 'CONTENT_LENGTH' => 'content-length'] as $k => $n) {
            if (isset($server[$k])) {
                $headers[$n] = (string) $server[$k];
            }
        }

        return $headers;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization', '');

        if ($auth !== null && preg_match('/^Bearer\s+(\S+)$/i', $auth, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /** Lee de query + body (body tiene prioridad). */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        if (is_array($value)) {
            return $default;
        }

        // Normaliza a UTF-8 valido y quita caracteres de control.
        $value = (string) $value;
        $value = mb_scrub($value, 'UTF-8');
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return trim($value);
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->input($key);

        return is_numeric($value) ? (float) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->input($key);

        if ($value === null) {
            return $default;
        }

        return in_array(
            is_string($value) ? strtolower($value) : $value,
            [true, 1, '1', 'true', 'on', 'yes', 'si'],
            true
        );
    }

    /** @return list<mixed> */
    public function array(string $key): array
    {
        $value = $this->input($key, []);

        return is_array($value) ? array_values($value) : [];
    }

    /** @return list<int> */
    public function intArray(string $key): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $v): int => is_numeric($v) ? (int) $v : 0,
            $this->array($key)
        ), static fn (int $v): bool => $v > 0));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body) || array_key_exists($key, $this->query);
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->query + $this->body;
    }

    /**
     * @param list<string> $keys
     * @return array<string,mixed>
     */
    public function only(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            if ($this->has($key)) {
                $result[$key] = $this->input($key);
            }
        }

        return $result;
    }

    /** @return array<string,mixed>|null */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    public function hasFile(string $key): bool
    {
        $file = $this->file($key);

        return $file !== null
            && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            && ($file['size'] ?? 0) > 0;
    }

    /** @param array<string,string> $params */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function param(string $key, ?string $default = null): ?string
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function paramInt(string $key, int $default = 0): int
    {
        $value = $this->routeParams[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    public function isJson(): bool
    {
        return str_contains(strtolower((string) $this->header('content-type', '')), 'application/json');
    }

    public function wantsJson(): bool
    {
        $accept = strtolower((string) $this->header('accept', ''));

        return $this->isJson()
            || str_contains($accept, 'application/json')
            || str_starts_with($this->path, '/api/');
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('x-requested-with', '')) === 'xmlhttprequest';
    }

    public function isSecure(): bool
    {
        if (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        if (!Config::get('app.trust_proxy', false)) {
            return false;
        }

        return strtolower((string) $this->header('x-forwarded-proto', '')) === 'https';
    }

    /**
     * IP del cliente.
     *
     * Solo confia en X-Forwarded-For si el proxy esta declarado como confiable
     * en la configuracion; de lo contrario cualquiera podria falsear su IP y
     * saltarse el limitador de peticiones.
     */
    public function ip(): string
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        if (!Config::get('app.trust_proxy', false)) {
            return $remote;
        }

        $trusted = (array) Config::get('app.trusted_proxies', []);

        if ($trusted !== [] && !in_array($remote, $trusted, true)) {
            return $remote;
        }

        $forwarded = (string) $this->header('x-forwarded-for', '');

        foreach (explode(',', $forwarded) as $candidate) {
            $candidate = trim($candidate);
            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                return $candidate;
            }
        }

        return $remote;
    }

    public function userAgent(): string
    {
        return mb_substr((string) $this->header('user-agent', ''), 0, 255);
    }

    /**
     * URL absoluta de la peticion.
     *
     * Usa el host configurado en APP_URL y no la cabecera Host, que el cliente
     * controla (evita envenenamiento de enlaces en correos y redirecciones).
     */
    public function url(): string
    {
        $base = rtrim((string) Config::get('app.url', ''), '/');

        if ($base === '') {
            $base = ($this->isSecure() ? 'https' : 'http') . '://localhost';
        }

        return $base . $this->path;
    }
}
