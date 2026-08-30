<?php
/** @var string $message */

use App\Services\SettingsService;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>En mantenimiento</title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<main class="container" style="min-height:90vh;display:grid;place-items:center;text-align:center">
    <div style="max-width:520px">
        <div style="font-size:4rem">&#9881;</div>
        <h1><?= e(SettingsService::string('business.name', 'Estamos trabajando')) ?></h1>
        <p class="text-muted"><?= e($message) ?></p>

        <?php if (SettingsService::string('business.phone', '') !== ''): ?>
            <p class="mt-3">
                Mientras tanto puedes llamarnos:
                <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', SettingsService::string('business.phone')) ?? '') ?>">
                    <?= e(SettingsService::string('business.phone')) ?>
                </a>
            </p>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
