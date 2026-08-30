<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\MiddlewareInterface;

/** Enrutador con parametros nombrados y middlewares por grupo. */
final class Router
{
    /** @var array<string, list<array{pattern:string,params:list<string>,handler:mixed,middleware:list<string>,name:string}>> */
    private array $routes = [];

    /** @var array<string,string> */
    private array $namedRoutes = [];

    /** @var list<string> */
    private array $groupMiddleware = [];

    private string $groupPrefix = '';

    /** @var array<string,class-string<MiddlewareInterface>> */
    private array $middlewareAliases = [];

    /** @param array<string,class-string<MiddlewareInterface>> $aliases */
    public function registerMiddleware(array $aliases): void
    {
        $this->middlewareAliases = $aliases + $this->middlewareAliases;
    }

    /** @param list<string> $middleware */
    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix = rtrim($previousPrefix . '/' . trim($prefix, '/'), '/');
        $this->groupMiddleware = [...$previousMiddleware, ...$middleware];

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    public function get(string $path, mixed $handler, array $middleware = [], string $name = ''): void
    {
        $this->add('GET', $path, $handler, $middleware, $name);
        $this->add('HEAD', $path, $handler, $middleware, '');
    }

    public function post(string $path, mixed $handler, array $middleware = [], string $name = ''): void
    {
        $this->add('POST', $path, $handler, $middleware, $name);
    }

    public function put(string $path, mixed $handler, array $middleware = [], string $name = ''): void
    {
        $this->add('PUT', $path, $handler, $middleware, $name);
    }

    public function patch(string $path, mixed $handler, array $middleware = [], string $name = ''): void
    {
        $this->add('PATCH', $path, $handler, $middleware, $name);
    }

    public function delete(string $path, mixed $handler, array $middleware = [], string $name = ''): void
    {
        $this->add('DELETE', $path, $handler, $middleware, $name);
    }

    private function add(string $method, string $path, mixed $handler, array $middleware, string $name): void
    {
        $full = $this->groupPrefix . '/' . ltrim($path, '/');
        $full = '/' . trim($full, '/');
        $full = $full === '/' ? '/' : rtrim($full, '/');

        $params = [];

        // {id}, {slug}, {token} => grupos nombrados con caracteres seguros.
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::(int|slug|uuid|any))?\}/',
            static function (array $m) use (&$params): string {
                $params[] = $m[1];

                return match ($m[2] ?? 'slug') {
                    'int' => '(?P<' . $m[1] . '>[0-9]+)',
                    'uuid' => '(?P<' . $m[1] . '>[0-9a-fA-F-]{36})',
                    'any' => '(?P<' . $m[1] . '>[^/]+)',
                    default => '(?P<' . $m[1] . '>[A-Za-z0-9._-]+)',
                };
            },
            $full
        ) ?? $full;

        $route = [
            'pattern' => '#^' . $pattern . '$#',
            'params' => $params,
            'handler' => $handler,
            'middleware' => [...$this->groupMiddleware, ...$middleware],
            'name' => $name,
        ];

        $this->routes[$method][] = $route;

        if ($name !== '') {
            $this->namedRoutes[$name] = $full;
        }
    }

    public function route(string $name, array $params = []): string
    {
        $path = $this->namedRoutes[$name] ?? '/';

        foreach ($params as $key => $value) {
            $path = preg_replace('/\{' . preg_quote((string) $key, '/') . '(?::[a-z]+)?\}/', rawurlencode((string) $value), $path) ?? $path;
        }

        return Url::to($path);
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();
        $candidates = $this->routes[$method] ?? [];

        foreach ($candidates as $route) {
            if (preg_match($route['pattern'], $path, $matches) !== 1) {
                continue;
            }

            $params = [];
            foreach ($route['params'] as $param) {
                $params[$param] = $matches[$param] ?? '';
            }

            $request->setRouteParams($params);

            return $this->runMiddleware($route['middleware'], $request, fn (): Response => $this->call($route['handler'], $request));
        }

        // Distingue 404 de 405 para diagnosticar mejor.
        foreach ($this->routes as $otherMethod => $routes) {
            if ($otherMethod === $method) {
                continue;
            }

            foreach ($routes as $route) {
                if (preg_match($route['pattern'], $path) === 1) {
                    throw new HttpException(405, 'Metodo no permitido para esta ruta.');
                }
            }
        }

        throw new HttpException(404, 'Recurso no encontrado.');
    }

    /** @param list<string> $middleware */
    private function runMiddleware(array $middleware, Request $request, callable $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($middleware),
            function (callable $next, string $alias): callable {
                return function (Request $request) use ($next, $alias): Response {
                    // "can:citas.editar" => alias "can" con argumento "citas.editar".
                    [$name, $argument] = array_pad(explode(':', $alias, 2), 2, null);

                    $class = $this->middlewareAliases[$name] ?? null;

                    if ($class === null || !class_exists($class)) {
                        throw new \RuntimeException("Middleware no registrado: {$name}");
                    }

                    /** @var MiddlewareInterface $instance */
                    $instance = $argument === null ? new $class() : new $class($argument);

                    return $instance->handle($request, $next);
                };
            },
            $destination
        );

        return $pipeline($request);
    }

    private function call(mixed $handler, Request $request): Response
    {
        if (is_callable($handler)) {
            $result = $handler($request);
        } elseif (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;

            if (!class_exists($class)) {
                throw new \RuntimeException("Controlador inexistente: {$class}");
            }

            $controller = new $class();

            if (!method_exists($controller, $method)) {
                throw new \RuntimeException("Accion inexistente: {$class}::{$method}");
            }

            $result = $controller->{$method}($request);
        } else {
            throw new \RuntimeException('Manejador de ruta no valido.');
        }

        if ($result instanceof Response) {
            return $result;
        }

        if (is_string($result)) {
            return Response::html($result);
        }

        return Response::json($result);
    }
}
