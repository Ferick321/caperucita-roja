<?php

use App\Core\View;

View::extend('layouts.public');
?>
<?php View::start('title'); ?>Recuperar contrasena<?php View::stop(); ?>

<?php View::start('content'); ?>
<section class="section">
    <div class="container" style="max-width:440px">
        <div class="card" style="padding:32px">
            <h1 style="font-size:1.5rem">Recuperar contrasena</h1>
            <p class="text-muted text-small">
                Escribe tu correo y te enviaremos un enlace para crear una nueva. El enlace caduca en una hora.
            </p>

            <form method="post" action="<?= e(url('/recuperar')) ?>" class="form-grid mt-3">
                <?= csrf_field() ?>
                <?= honeypot_field() ?>

                <div class="field">
                    <label for="f-email">Correo electronico</label>
                    <input id="f-email" type="email" name="email" required autocomplete="email" autofocus>
                    <?= field_error('email') ?>
                </div>

                <button type="submit" class="btn btn--primary btn--block">Enviarme el enlace</button>
            </form>

            <p class="text-center text-small mt-3 mb-0">
                <a href="<?= e(url('/ingresar')) ?>">Volver a ingresar</a>
            </p>
        </div>
    </div>
</section>
<?php View::stop(); ?>
