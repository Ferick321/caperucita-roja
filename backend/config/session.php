<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name' => Env::get('SESSION_NAME', 'estilo_sid'),
    'path' => dirname(__DIR__) . '/storage/sessions',
    'domain' => Env::get('SESSION_DOMAIN', ''),
    'lifetime_minutes' => (int) Env::get('SESSION_LIFETIME', 120),
    'absolute_lifetime_minutes' => (int) Env::get('SESSION_ABSOLUTE_LIFETIME', 720),
    'rotate_minutes' => (int) Env::get('SESSION_ROTATE', 20),
    'same_site' => Env::get('SESSION_SAME_SITE', 'Lax'),
    'force_secure' => (bool) Env::get('SESSION_FORCE_SECURE', false),
    // Ata la sesion al bloque de red del cliente (puede molestar en redes moviles).
    'bind_ip' => (bool) Env::get('SESSION_BIND_IP', false),
];
