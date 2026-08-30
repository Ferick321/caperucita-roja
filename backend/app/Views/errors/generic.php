<?php
/** @var int $status @var string $message @var string $trace */

$titles = [
    400 => 'Peticion incorrecta',
    401 => 'Necesitas iniciar sesion',
    403 => 'Acceso restringido',
    404 => 'Pagina no encontrada',
    405 => 'Metodo no permitido',
    419 => 'La sesion expiro',
    422 => 'Revisa los datos',
    429 => 'Demasiadas peticiones',
    500 => 'Algo salio mal',
    503 => 'Servicio no disponible',
];

$title = $titles[$status] ?? 'Error';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<main class="container" style="min-height:80vh;display:grid;place-items:center;text-align:center">
    <div style="max-width:520px">
        <p style="font-size:5rem;font-weight:800;color:var(--color-primary);margin:0;line-height:1">
            <?= e((string) $status) ?>
        </p>
        <h1><?= e($title) ?></h1>
        <p class="text-muted"><?= e($message) ?></p>

        <div class="flex gap-2 justify-between mt-4" style="justify-content:center">
            <a class="btn btn--primary" href="<?= e(url('/')) ?>">Ir al inicio</a>
            <a class="btn btn--ghost" href="<?= e(url('/agendar')) ?>">Agendar una cita</a>
        </div>

        <?php if ($trace !== ''): ?>
            <pre style="text-align:left;overflow:auto;background:var(--color-surface);padding:16px;
                        border-radius:12px;font-size:.75rem;margin-top:28px"><?= e($trace) ?></pre>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
