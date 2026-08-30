<?php

declare(strict_types=1);

/**
 * Arranque comun de la aplicacion.
 *
 * Lo cargan tanto el punto de entrada web (public/index.php) como la consola
 * (cli/console.php). Prefiere el autoload de Composer si existe; si no, usa
 * el autocargador propio para funcionar en hosting compartido.
 */

$basePath = __DIR__;

if (is_file($basePath . '/vendor/autoload.php')) {
    require $basePath . '/vendor/autoload.php';
} else {
    require $basePath . '/app/Core/Autoloader.php';

    $autoloader = new App\Core\Autoloader();
    $autoloader->addNamespace('App', $basePath . '/app');
    $autoloader->addNamespace('Database', $basePath . '/database');
    $autoloader->register();

    require $basePath . '/app/Support/helpers.php';
}

// El autoload de Composer no incluye las semillas por defecto.
if (!class_exists(Database\Seeds\InitialSeeder::class, false)
    && is_file($basePath . '/database/seeds/InitialSeeder.php')
) {
    require_once $basePath . '/database/seeds/InitialSeeder.php';
}

return App\Core\App::boot($basePath);
