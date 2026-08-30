<?php

use App\Core\View;

View::extend('layouts.public');
?>
<?php View::start('title'); ?>Ingresar<?php View::stop(); ?>

<?php View::start('content'); ?>
<section class="section">
    <div class="container" style="max-width:440px">
        <div class="card" style="padding:32px">
            <h1 style="font-size:1.6rem">Ingresa a tu cuenta</h1>
            <p class="text-muted text-small">Consulta tus citas, tus puntos y tus comprobantes.</p>

            <form method="post" action="<?= e(url('/ingresar')) ?>" class="form-grid mt-3">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="l-email">Correo electronico</label>
                    <input id="l-email" type="email" name="email" required autocomplete="email"
                           value="<?= e(old('email')) ?>" autofocus>
                    <?= field_error('email') ?>
                </div>

                <div class="field">
                    <label for="l-password">Contrasena</label>
                    <input id="l-password" type="password" name="password" required autocomplete="current-password">
                    <?= field_error('password') ?>
                </div>

                <button type="submit" class="btn btn--primary btn--block">Ingresar</button>
            </form>

            <p class="text-center text-small mt-3 mb-0">
                <a href="<?= e(url('/recuperar')) ?>">Olvidaste tu contrasena?</a>
            </p>
            <p class="text-center text-small mb-0">
                No tienes cuenta? <a href="<?= e(url('/registro')) ?>">Crea una gratis</a>
            </p>
        </div>

        <p class="text-center text-small text-muted mt-3">
            Tambien puedes <a href="<?= e(url('/agendar')) ?>">reservar sin registrarte</a>.
        </p>
    </div>
</section>
<?php View::stop(); ?>
