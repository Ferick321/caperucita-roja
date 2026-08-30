<?php

use App\Core\View;
use App\Services\SettingsService;

View::extend('layouts.public');
?>
<?php View::start('title'); ?>Crear cuenta<?php View::stop(); ?>

<?php View::start('content'); ?>
<section class="section">
    <div class="container" style="max-width:520px">
        <div class="card" style="padding:32px">
            <h1 style="font-size:1.6rem">Crea tu cuenta</h1>
            <p class="text-muted text-small">
                Reserva mas rapido, guarda tu historial y acumula
                <?= (int) SettingsService::int('loyalty.welcome_points', 50) ?> puntos de bienvenida.
            </p>

            <form method="post" action="<?= e(url('/registro')) ?>" class="form-grid mt-3">
                <?= csrf_field() ?>
                <?= honeypot_field() ?>

                <div class="form-grid form-grid--2">
                    <div class="field">
                        <label for="r-first">Nombre *</label>
                        <input id="r-first" type="text" name="first_name" required maxlength="80"
                               autocomplete="given-name" value="<?= e(old('first_name')) ?>">
                        <?= field_error('first_name') ?>
                    </div>
                    <div class="field">
                        <label for="r-last">Apellido</label>
                        <input id="r-last" type="text" name="last_name" maxlength="80"
                               autocomplete="family-name" value="<?= e(old('last_name')) ?>">
                    </div>
                </div>

                <div class="field">
                    <label for="r-email">Correo electronico *</label>
                    <input id="r-email" type="email" name="email" required autocomplete="email"
                           value="<?= e(old('email')) ?>">
                    <?= field_error('email') ?>
                </div>

                <div class="field">
                    <label for="r-phone">Telefono / WhatsApp *</label>
                    <input id="r-phone" type="tel" name="phone" required autocomplete="tel"
                           value="<?= e(old('phone')) ?>" placeholder="0999999999">
                    <span class="field__hint">Lo usamos para avisarte de tu cita.</span>
                    <?= field_error('phone') ?>
                </div>

                <div class="field">
                    <label for="r-password">Contrasena *</label>
                    <input id="r-password" type="password" name="password" required autocomplete="new-password"
                           minlength="<?= (int) config('security.password.min_length', 10) ?>">
                    <span class="field__hint">
                        Minimo <?= (int) config('security.password.min_length', 10) ?> caracteres,
                        combinando mayusculas, minusculas, numeros o simbolos.
                    </span>
                    <?= field_error('password') ?>
                </div>

                <div class="field">
                    <label for="r-password2">Repite la contrasena *</label>
                    <input id="r-password2" type="password" name="password_confirmation" required autocomplete="new-password">
                </div>

                <label class="checkbox">
                    <input type="checkbox" name="accepts_terms" value="1" required>
                    <span>
                        Acepto los <a href="<?= e(url('/legal/terminos')) ?>" target="_blank">terminos</a> y la
                        <a href="<?= e(url('/legal/privacidad')) ?>" target="_blank">politica de privacidad</a>. *
                    </span>
                </label>

                <label class="checkbox">
                    <input type="checkbox" name="accepts_marketing" value="1" checked>
                    <span>Quiero recibir promociones y novedades. Puedo darme de baja cuando quiera.</span>
                </label>

                <button type="submit" class="btn btn--primary btn--block">Crear mi cuenta</button>
            </form>

            <p class="text-center text-small mt-3 mb-0">
                Ya tienes cuenta? <a href="<?= e(url('/ingresar')) ?>">Ingresa aqui</a>
            </p>
        </div>
    </div>
</section>
<?php View::stop(); ?>
