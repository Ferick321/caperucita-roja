<?php

declare(strict_types=1);

use App\Core\Env;

return [
    // "smtp" usa el transporte propio; "mail" usa la funcion mail() de PHP;
    // "log" solo escribe el mensaje en el registro (util en pruebas).
    'transport' => Env::get('MAIL_TRANSPORT', 'log'),
    'host' => Env::get('MAIL_HOST', ''),
    'port' => (int) Env::get('MAIL_PORT', 587),
    'username' => Env::get('MAIL_USERNAME', ''),
    'password' => Env::get('MAIL_PASSWORD', ''),
    'encryption' => Env::get('MAIL_ENCRYPTION', 'tls'),
    'timeout' => (int) Env::get('MAIL_TIMEOUT', 15),
    'from_address' => Env::get('MAIL_FROM_ADDRESS', 'no-reply@localhost'),
    'from_name' => Env::get('MAIL_FROM_NAME', 'Plataforma Estilo'),
];
