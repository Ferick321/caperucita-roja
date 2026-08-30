<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'Plataforma Estilo'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => (bool) Env::get('APP_DEBUG', false),
    'url' => rtrim((string) Env::get('APP_URL', 'http://localhost:8080'), '/'),
    'key' => Env::get('APP_KEY', ''),
    'version' => '1.0.0',

    // Fuerza HTTPS con redireccion 301 (dejar en true en produccion).
    'force_https' => (bool) Env::get('FORCE_HTTPS', false),

    // Solo activar detras de un balanceador o CDN de confianza.
    'trust_proxy' => (bool) Env::get('TRUST_PROXY', false),
    'trusted_proxies' => array_filter(explode(',', (string) Env::get('TRUSTED_PROXIES', ''))),

    'allowed_redirect_hosts' => array_filter(explode(',', (string) Env::get('ALLOWED_REDIRECT_HOSTS', ''))),

    'log_path' => dirname(__DIR__) . '/storage/logs',
    'log_level' => Env::get('LOG_LEVEL', 'info'),
];
