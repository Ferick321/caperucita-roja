<?php

declare(strict_types=1);

use App\Core\Env;

return [
    // Fuera del directorio publico: los archivos se sirven por controlador.
    'directory' => Env::get('UPLOAD_PATH', dirname(__DIR__) . '/storage/uploads'),
    'max_bytes' => (int) Env::get('UPLOAD_MAX_BYTES', 5 * 1024 * 1024),
    'max_pixels' => (int) Env::get('UPLOAD_MAX_PIXELS', 40000000),
    'image_quality' => (int) Env::get('UPLOAD_IMAGE_QUALITY', 82),

    'variants' => [
        'thumb' => 320,
        'medium' => 800,
        'large' => 1600,
    ],
];
