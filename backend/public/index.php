<?php

declare(strict_types=1);

/**
 * Unico punto de entrada de la aplicacion.
 *
 * El servidor web debe apuntar su raiz de documentos a este directorio
 * (public/). Todo lo demas -codigo, configuracion, subidas y registros-
 * queda fuera del alcance del navegador.
 */

// Servidor embebido de PHP (solo desarrollo): deja que sirva por si mismo los
// archivos estaticos que existen, en lugar de enrutarlos por la aplicacion.
if (PHP_SAPI === 'cli-server') {
    $requested = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $candidate = __DIR__ . '/' . ltrim(is_string($requested) ? $requested : '', '/');

    if ($requested !== '/' && is_file($candidate)) {
        return false;
    }
}

/** @var App\Core\App $app */
$app = require dirname(__DIR__) . '/bootstrap.php';

$app->loadRoutes('web.php', 'admin.php', 'api.php');

$request = App\Core\Request::capture();
$response = $app->handle($request);

$response->send();
