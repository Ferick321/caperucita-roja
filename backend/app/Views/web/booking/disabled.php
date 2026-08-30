<?php

use App\Core\View;
use App\Services\SettingsService;

View::extend('layouts.public');
?>
<?php View::start('title'); ?>Agenda temporalmente cerrada<?php View::stop(); ?>

<?php View::start('content'); ?>
<section class="section">
    <div class="container text-center" style="max-width:560px">
        <div class="empty-state__icon">&#128197;</div>
        <h1>La agenda en linea esta pausada</h1>
        <p class="text-muted">
            Por el momento no estamos aceptando reservas por la web.
            Comunicate con nosotros y con gusto te atendemos.
        </p>

        <div class="flex gap-2 mt-3" style="justify-content:center;flex-wrap:wrap">
            <?php if (SettingsService::string('business.phone', '') !== ''): ?>
                <a class="btn btn--primary"
                   href="tel:<?= e(preg_replace('/[^0-9+]/', '', SettingsService::string('business.phone')) ?? '') ?>">
                    Llamarnos
                </a>
            <?php endif; ?>
            <a class="btn btn--ghost" href="<?= e(url('/contacto')) ?>">Escribirnos</a>
        </div>
    </div>
</section>
<?php View::stop(); ?>
