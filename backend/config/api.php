<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'version' => 'v1',
    // La app movil no envia Origin; esto es para paneles web externos.
    'allowed_origins' => array_filter(explode(',', (string) Env::get('API_ALLOWED_ORIGINS', ''))),
    'rate_limit' => Env::get('API_RATE_LIMIT', '120,60'),
    // Version minima de la app aceptada (fuerza actualizacion).
    'min_app_version' => Env::get('MIN_APP_VERSION', '1.0.0'),
];
