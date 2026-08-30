<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'password' => [
        'min_length' => (int) Env::get('PASSWORD_MIN_LENGTH', 10),
        // Secreto extra que NO vive en la base de datos.
        'pepper' => Env::get('PASSWORD_PEPPER', ''),
        'argon_memory' => (int) Env::get('ARGON_MEMORY', 65536),
        'argon_time' => (int) Env::get('ARGON_TIME', 4),
        'argon_threads' => (int) Env::get('ARGON_THREADS', 2),
        'bcrypt_cost' => (int) Env::get('BCRYPT_COST', 12),
        // Dias tras los que se pide cambiar la clave al personal (0 = nunca).
        'max_age_days_staff' => (int) Env::get('PASSWORD_MAX_AGE_STAFF', 0),
    ],

    'login' => [
        'max_attempts_per_ip' => (int) Env::get('LOGIN_MAX_IP', 20),
        'max_attempts_per_account' => (int) Env::get('LOGIN_MAX_ACCOUNT', 8),
        'decay_seconds' => (int) Env::get('LOGIN_DECAY', 900),
        'lockout_threshold' => (int) Env::get('LOGIN_LOCKOUT_THRESHOLD', 10),
        'lockout_minutes' => (int) Env::get('LOGIN_LOCKOUT_MINUTES', 30),
    ],

    'jwt' => [
        'secret' => Env::get('JWT_SECRET', ''),
        'access_ttl' => (int) Env::get('JWT_ACCESS_TTL', 900),          // 15 minutos
        'refresh_ttl' => (int) Env::get('JWT_REFRESH_TTL', 2592000),    // 30 dias
    ],

    'hsts_max_age' => (int) Env::get('HSTS_MAX_AGE', 31536000),

    // Restringe el panel a IPs concretas (vacio = sin restriccion).
    'admin_ip_allowlist' => array_filter(explode(',', (string) Env::get('ADMIN_IP_ALLOWLIST', ''))),

    // Dominios extra permitidos por la politica de contenido.
    'csp_extra' => [
        'img-src' => array_filter(explode(',', (string) Env::get('CSP_IMG_SRC', ''))),
        'connect-src' => array_filter(explode(',', (string) Env::get('CSP_CONNECT_SRC', ''))),
        'frame-src' => array_filter(explode(',', (string) Env::get('CSP_FRAME_SRC', ''))),
    ],

    // Verificacion obligatoria del segundo factor para estos roles.
    'require_2fa_roles' => array_filter(explode(',', (string) Env::get('REQUIRE_2FA_ROLES', 'super_admin'))),
];
