<?php
/** @var list<array<string,mixed>> $team @var array<string,array<string,mixed>> $blocks */

use App\Core\View;

View::extend('layouts.public');
?>
<?php View::start('title'); ?>Nuestro equipo<?php View::stop(); ?>

<?php View::start('content'); ?>
<section class="section">
    <div class="container">
        <div class="section__head">
            <span class="section__eyebrow">Profesionales</span>
            <h1><?= e($blocks['team_intro']['title'] ?? 'Nuestro equipo') ?></h1>
            <p><?= e($blocks['team_intro']['subtitle'] ?? '') ?></p>
        </div>

        <?php if ($team === []): ?>
            <div class="empty-state"><p>Pronto presentaremos a nuestro equipo.</p></div>
        <?php else: ?>
            <div class="grid grid--3">
                <?php foreach ($team as $member): ?>
                    <article class="card staff-card">
                        <?php if ((string) $member['photo_path'] !== ''): ?>
                            <img class="staff-card__photo" src="<?= e(media_url($member['photo_path'])) ?>"
                                 alt="<?= e($member['display_name']) ?>" loading="lazy" width="118" height="118">
                        <?php else: ?>
                            <div class="staff-card__photo"><?= e(initials((string) $member['display_name'])) ?></div>
                        <?php endif; ?>

                        <div class="card__body">
                            <h2 class="card__title" style="font-size:1.1rem"><?= e($member['display_name']) ?></h2>
                            <p class="staff-card__role"><?= e($member['title']) ?></p>

                            <?php if ((string) ($member['bio'] ?? '') !== ''): ?>
                                <p class="card__text"><?= e(str_limit((string) $member['bio'], 170)) ?></p>
                            <?php endif; ?>

                            <?php if (!empty($member['services'])): ?>
                                <div class="flex gap-1 flex-wrap mt-2" style="justify-content:center">
                                    <?php foreach ($member['services'] as $serviceName): ?>
                                        <span class="pill"><?= e($serviceName) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ((int) $member['rating_count'] > 0): ?>
                                <p class="rating mt-2">
                                    <?= e(str_repeat('*', (int) round((float) $member['rating_average']))) ?>
                                    <span class="text-muted text-small">(<?= (int) $member['rating_count'] ?> opiniones)</span>
                                </p>
                            <?php endif; ?>

                            <a class="btn btn--primary btn--sm mt-2"
                               href="<?= e(url('/agendar?profesional=' . (int) $member['id'])) ?>">Reservar cita</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php View::stop(); ?>
