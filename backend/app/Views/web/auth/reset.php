<?php
/** @var string $token */

use App\Core\View;

View::extend('layouts.public');
?>
<?php View::start('title'); ?>Nueva contrasena<?php View::stop(); ?>

<?php View::start('content'); ?>
<section class="section">
    <div class="container" style="max-width:440px">
        <div class="card" style="padding:32px">
            <h1 style="font-size:1.5rem">Crea una contrasena nueva</h1>

            <form method="post" action="<?= e(url('/restablecer')) ?>" class="form-grid mt-3">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="field">
                    <label for="rp-password">Nueva contrasena</label>
                    <input id="rp-password" type="password" name="password" required autocomplete="new-password"
                           minlength="<?= (int) config('security.password.min_length', 10) ?>" autofocus>
                    <?= field_error('password') ?>
                </div>

                <div class="field">
                    <label for="rp-password2">Repite la contrasena</label>
                    <input id="rp-password2" type="password" name="password_confirmation" required autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn--primary btn--block">Guardar y continuar</button>
            </form>
        </div>
    </div>
</section>
<?php View::stop(); ?>
