<?php
/**
 * @var array<string,mixed> $user
 * @var list<array<string,mixed>> $upcoming
 * @var list<array<string,mixed>> $past
 * @var int $loyaltyPoints
 * @var float $loyaltyValue
 */

use App\Core\View;
use App\Services\SettingsService;

View::extend('layouts.public');
?>
<?php View::start('title'); ?>Mi cuenta<?php View::stop(); ?>

<?php View::start('content'); ?>
<section class="section">
    <div class="container">
        <div class="flex justify-between items-center flex-wrap gap-2 mb-3">
            <div>
                <h1 style="margin-bottom:4px">Hola, <?= e($user['first_name']) ?></h1>
                <p class="text-muted mb-0">Aqui tienes un resumen de tu actividad.</p>
            </div>
            <a class="btn btn--primary" href="<?= e(url('/agendar')) ?>">Agendar nueva cita</a>
        </div>

        <div class="grid grid--4 mb-4">
            <div class="card" style="padding:20px">
                <p class="text-muted text-small mb-1">Proximas citas</p>
                <p style="font-size:1.9rem;font-weight:700;margin:0;color:var(--color-primary)">
                    <?= (int) count($upcoming) ?>
                </p>
            </div>
            <div class="card" style="padding:20px">
                <p class="text-muted text-small mb-1">Visitas realizadas</p>
                <p style="font-size:1.9rem;font-weight:700;margin:0"><?= (int) $user['total_visits'] ?></p>
            </div>
            <?php if (SettingsService::bool('loyalty.enabled', true)): ?>
                <div class="card" style="padding:20px">
                    <p class="text-muted text-small mb-1">Puntos acumulados</p>
                    <p style="font-size:1.9rem;font-weight:700;margin:0;color:var(--color-primary)">
                        <?= (int) $loyaltyPoints ?>
                    </p>
                    <p class="text-small text-muted mb-0">Equivalen a <?= e(money($loyaltyValue)) ?></p>
                </div>
            <?php endif; ?>
            <div class="card" style="padding:20px">
                <p class="text-muted text-small mb-1">Tu codigo de referido</p>
                <p style="font-size:1.3rem;font-weight:700;margin:0;letter-spacing:.08em">
                    <?= e($user['referral_code']) ?>
                </p>
                <button type="button" class="copy-btn mt-1" data-copy="<?= e($user['referral_code']) ?>">Copiar</button>
            </div>
        </div>

        <h2 style="font-size:1.25rem">Proximas citas</h2>

        <?php if ($upcoming === []): ?>
            <div class="card" style="padding:32px;text-align:center">
                <p class="text-muted">No tienes citas programadas.</p>
                <a class="btn btn--primary" href="<?= e(url('/agendar')) ?>">Agendar ahora</a>
            </div>
        <?php else: ?>
            <div class="grid grid--2">
                <?php foreach ($upcoming as $appointment): ?>
                    <?= View::partial('partials.appointment_card', ['appointment' => $appointment]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($past !== []): ?>
            <h2 class="mt-4" style="font-size:1.25rem">Historial reciente</h2>
            <div class="grid grid--2">
                <?php foreach ($past as $appointment): ?>
                    <?= View::partial('partials.appointment_card', ['appointment' => $appointment]) ?>
                <?php endforeach; ?>
            </div>
            <p class="mt-3">
                <a class="btn btn--ghost" href="<?= e(url('/mis-citas?estado=historial')) ?>">Ver todo el historial</a>
            </p>
        <?php endif; ?>
    </div>
</section>
<?php View::stop(); ?>
