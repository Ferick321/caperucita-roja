<?php
/** @var bool $done */

use App\Core\View;

View::extend('layouts.public');
?>
<?php View::start('title'); ?>Baja de comunicaciones<?php View::stop(); ?>

<?php View::start('content'); ?>
<section class="section">
    <div class="container text-center" style="max-width:560px">
        <?php if ($done): ?>
            <div class="empty-state__icon">&#10003;</div>
            <h1>Listo</h1>
            <p class="text-muted">
                No volveremos a enviarte comunicaciones comerciales.
                Seguiras recibiendo unicamente los avisos de tus citas.
            </p>
        <?php else: ?>
            <div class="empty-state__icon">&#9888;</div>
            <h1>Enlace no valido</h1>
            <p class="text-muted">
                Este enlace de baja ya no es valido. Puedes ajustar tus preferencias
                desde tu perfil.
            </p>
            <a class="btn btn--primary" href="<?= e(url('/mi-perfil')) ?>">Ir a mi perfil</a>
        <?php endif; ?>
    </div>
</section>
<?php View::stop(); ?>
