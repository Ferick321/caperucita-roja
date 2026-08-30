<?php

declare(strict_types=1);

namespace App\Core;

/** Respuesta HTTP. */
final class Response
{
    private int $status = 200;

    /** @var array<string,string> */
    private array $headers = [];

    /** @var list<array{name:string,value:string,options:array<string,mixed>}> */
    private array $cookies = [];

    private string $content = '';

    public static function make(string $content = '', int $status = 200): self
    {
        $response = new self();
        $response->content = $content;
        $response->status = $status;

        return $response;
    }

    public static function html(string $html, int $status = 200): self
    {
        return self::make($html, $status)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $json = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        return self::make($json === false ? '{}' : $json, $status)
            ->header('Content-Type', 'application/json; charset=UTF-8')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    /** Respuesta de error uniforme para la API. */
    public static function apiError(string $message, int $status = 400, array $details = []): self
    {
        $payload = ['ok' => false, 'error' => ['message' => $message, 'code' => $status]];

        if ($details !== []) {
            $payload['error']['details'] = $details;
        }

        return self::json($payload, $status);
    }

    /** Respuesta de exito uniforme para la API. */
    public static function apiOk(mixed $data = null, array $meta = []): self
    {
        $payload = ['ok' => true, 'data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return self::json($payload);
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return self::make('', $status)->header('Location', Url::safeRedirect($to));
    }

    public static function noContent(): self
    {
        return self::make('', 204);
    }

    /** Descarga de archivo desde disco (streaming, sin cargarlo entero en memoria). */
    public static function file(string $absolutePath, string $mime, string $downloadName = '', bool $inline = true): self
    {
        $response = new self();
        $response->header('Content-Type', $mime);
        $response->header('Content-Length', (string) (filesize($absolutePath) ?: 0));
        $response->header('X-Content-Type-Options', 'nosniff');

        $disposition = $inline ? 'inline' : 'attachment';

        if ($downloadName !== '') {
            $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName) ?? 'archivo';
            $disposition .= '; filename="' . $safe . '"';
        }

        $response->header('Content-Disposition', $disposition);
        $response->content = "\0FILE\0" . $absolutePath;

        return $response;
    }

    public function status(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /** @return array<string,string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /** @param array<string,mixed> $options */
    public function cookie(string $name, string $value, array $options = []): self
    {
        $this->cookies[] = ['name' => $name, 'value' => $value, 'options' => $options];

        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }

            foreach ($this->cookies as $cookie) {
                setcookie($cookie['name'], $cookie['value'], $cookie['options']);
            }
        }

        if (str_starts_with($this->content, "\0FILE\0")) {
            $path = substr($this->content, 6);

            if (is_readable($path)) {
                $handle = fopen($path, 'rb');
                if ($handle !== false) {
                    while (!feof($handle)) {
                        echo fread($handle, 8192);
                    }
                    fclose($handle);
                }
            }

            return;
        }

        echo $this->content;
    }
}
