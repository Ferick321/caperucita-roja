<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Autocargador PSR-4 minimo.
 *
 * Permite desplegar el sistema en hosting compartido sin ejecutar Composer.
 * Si el autoload de Composer existe, bootstrap.php lo prefiere.
 */
final class Autoloader
{
    /** @var array<string,string> prefijo de namespace => directorio base */
    private array $prefixes = [];

    public function addNamespace(string $prefix, string $baseDir): void
    {
        $prefix = trim($prefix, '\\') . '\\';
        $this->prefixes[$prefix] = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;
    }

    public function register(): void
    {
        spl_autoload_register([$this, 'load']);
    }

    public function load(string $class): void
    {
        foreach ($this->prefixes as $prefix => $baseDir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

            if (is_file($file)) {
                require_once $file;

                return;
            }
        }
    }
}
