<?php
/**
 * @var list<array<string,mixed>> $appointments
 * @var string $filter
 * @var int $cancellationHours
 * @var bool $canCancel
 */

use App\Core\View;

View::extend('layouts.public');
?>
<?php View::start('title'); ?>Mis citas<?php View::stop(); ?>

<?php View::start('content'); ?>
<section class="section">
    <div class="container">
        <div class="flex justify-between items-center flex-wrap gap-2 mb-3">
            <h1 style="margin-bottom:0">Mis citas</h1>
            <a class="btn btn--primary" href="<?= e(url('/agendar')) ?>">Nueva cita</a>
        </div>

        <div class="flex gap-1 mb-3">
            <a class="btn btn--<?= $filter !== 'historial' ? 'primary' : 'ghost' ?> btn--sm"
               href="<?= e(url('/mis-citas')) ?>">Proximas</a>
            <a class="btn btn--<?= $filter === 'historial' ? 'primary' : 'ghost' ?> btn--sm"
               href="<?= e(url('/mis-citas?estado=historial')) ?>">Historial</a>
        </div>

        <?php if ($canCancel): ?>
            <p class="text-small text-muted">
                Puedes cancelar hasta <?= (int) $cancellationHours ?> horas antes de la cita.
            </p>
        <?php endif; ?>

        <?php if ($appointments === []): ?>
            <div class="empty-state">
                <div class="empty-state__icon">&#128197;</div>
                <p><?= $filter === 'historial' ? 'Aun no tienes visitas registradas.' : 'No tienes citas programadas.' ?></p>
                <a class="btn btn--primary" href="<?= e(url('/agendar')) ?>">Agendar una cita</a>
            </div>
        <?php else: ?>
            <div class="grid grid--2">
                <?php foreach ($appointments as $appointment): ?>
                    <?= View::partial('partials.appointment_card', ['appointment' => $appointment]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php View::stop(); ?>
