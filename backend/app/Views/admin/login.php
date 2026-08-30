<?php

use App\Services\SettingsService;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Acceso al panel</title>
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body>
<main class="login-page">
    <div class="login-card">
        <div class="text-center mb-3">
            <?php if (SettingsService::string('business.logo', '') !== ''): ?>
                <img src="<?= e(media_url(SettingsService::string('business.logo'))) ?>" alt=""
                     style="max-height:56px;margin:0 auto 14px">
            <?php endif; ?>
            <h1 style="font-size:1.35rem;margin-bottom:4px"><?= e(SettingsService::string('business.name', 'Panel')) ?></h1>
            <p class="text-muted text-small">Acceso para el personal</p>
        </div>

        <?php foreach (($flash ?? []) as $type => $messages): ?>
            <?php foreach ($messages as $message): ?>
                <div class="alert alert--<?= e($type === 'success' ? 'success' : 'error') ?>" role="alert">
                    <span><?= e($message) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <form method="post" action="<?= e(url('/panel/acceso')) ?>" class="card">
            <?= csrf_field() ?>

            <div class="field">
                <label for="email">Correo electronico</label>
                <input id="email" type="email" name="email" required autocomplete="username"
                       value="<?= e(old('email')) ?>" autofocus>
                <?= field_error('email') ?>
            </div>

            <div class="field">
                <label for="password">Contrasena</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
                <?= field_error('password') ?>
            </div>

            <button type="submit" class="btn btn--primary btn--block">Entrar</button>

            <p class="text-center text-small mt-2 mb-0">
                <a href="<?= e(url('/recuperar')) ?>">Olvidaste tu contrasena?</a>
            </p>
        </form>

        <p class="text-center text-small text-muted">
            <a href="<?= e(url('/')) ?>">Volver al sitio publico</a>
        </p>
    </div>
</main>
</body>
</html>
