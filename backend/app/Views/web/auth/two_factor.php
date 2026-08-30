<?php

use App\Core\View;

View::extend('layouts.public');
?>
<?php View::start('title'); ?>Verificacion en dos pasos<?php View::stop(); ?>

<?php View::start('content'); ?>
<section class="section">
    <div class="container" style="max-width:420px">
        <div class="card" style="padding:32px">
            <h1 style="font-size:1.5rem">Verificacion en dos pasos</h1>
            <p class="text-muted text-small">
                Abre tu aplicacion de autenticacion e ingresa el codigo de 6 digitos.
                Tambien puedes usar uno de tus codigos de respaldo.
            </p>

            <form method="post" action="<?= e(url('/verificacion')) ?>" class="form-grid mt-3">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="tf-code">Codigo</label>
                    <input id="tf-code" type="text" name="code" required autocomplete="one-time-code"
                           inputmode="numeric" maxlength="20" autofocus
                           style="letter-spacing:.3em;text-align:center;font-size:1.3rem">
                </div>

                <button type="submit" class="btn btn--primary btn--block">Verificar</button>
            </form>
        </div>
    </div>
</section>
<?php View::stop(); ?>
