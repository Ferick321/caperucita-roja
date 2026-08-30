<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'driver' => Env::get('DB_DRIVER', 'mysql'),
    'host' => Env::get('DB_HOST', '127.0.0.1'),
    'port' => (int) Env::get('DB_PORT', 3306),
    'database' => Env::get('DB_DATABASE', 'estilo'),
    'username' => Env::get('DB_USERNAME', 'estilo'),
    'password' => Env::get('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'socket' => Env::get('DB_SOCKET', ''),

    // Conexion cifrada al motor (recomendado si la base esta en otro servidor).
    'ssl' => [
        'enabled' => (bool) Env::get('DB_SSL', false),
        'ca' => Env::get('DB_SSL_CA', ''),
        'verify' => (bool) Env::get('DB_SSL_VERIFY', true),
    ],
];
