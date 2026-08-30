<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Motor de plantillas basado en PHP puro.
 *
 * Las vistas nunca imprimen variables con <?= $x ?>: se usa e($x), que escapa
 * para contexto HTML. Los helpers de escape viven en Support/helpers.php.
 */
final class View
{
    private static string $path = '';

    /** @var array<string,mixed> */
    private static array $shared = [];

    /** @var array<string,string> */
    private static array $sections = [];

    /** @var list<string> */
    private static array $sectionStack = [];

    public static function setPath(string $path): void
    {
        self::$path = rtrim($path, '/');
    }

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /** @return array<string,mixed> */
    public static function sharedData(): array
    {
        return self::$shared;
    }

    /** @param array<string,mixed> $data */
    public static function render(string $template, array $data = []): string
    {
        $file = self::resolve($template);

        $data = array_merge(self::$shared, $data);

        $render = static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();

            try {
                include $__file;
            } catch (\Throwable $e) {
                ob_end_clean();

                throw $e;
            }

            return (string) ob_get_clean();
        };

        $content = $render($file, $data);

        // Si la vista declaro un layout, se envuelve el contenido.
        if (isset(self::$sections['__layout'])) {
            $layout = self::$sections['__layout'];
            unset(self::$sections['__layout']);

            self::$sections['content'] = $content;
            $content = $render(self::resolve($layout), $data);
        }

        self::$sections = [];

        return $content;
    }

    private static function resolve(string $template): string
    {
        // Solo se aceptan nombres tipo "admin.settings.index": sin ../ ni rutas absolutas.
        if (preg_match('/^[A-Za-z0-9_.\-]+$/', $template) !== 1) {
            throw new \InvalidArgumentException("Nombre de vista no valido: {$template}");
        }

        $file = self::$path . '/' . str_replace('.', '/', $template) . '.php';

        if (!is_file($file)) {
            throw new \RuntimeException("Vista no encontrada: {$template}");
        }

        return $file;
    }

    public static function extend(string $layout): void
    {
        self::$sections['__layout'] = $layout;
    }

    public static function start(string $section): void
    {
        self::$sectionStack[] = $section;
        ob_start();
    }

    public static function stop(): void
    {
        $section = array_pop(self::$sectionStack);

        if ($section === null) {
            ob_end_clean();

            return;
        }

        self::$sections[$section] = (string) ob_get_clean();
    }

    public static function section(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    public static function hasSection(string $name): bool
    {
        return isset(self::$sections[$name]) && trim(self::$sections[$name]) !== '';
    }

    /** @param array<string,mixed> $data */
    public static function partial(string $template, array $data = []): string
    {
        $file = self::resolve($template);
        $data = array_merge(self::$shared, $data);

        extract($data, EXTR_SKIP);
        ob_start();
        include $file;

        return (string) ob_get_clean();
    }
}
