<?php
/**
 * @var array<string,mixed> $service
 * @var list<array<string,mixed>> $staff
 * @var list<array<string,mixed>> $related
 */

use App\Core\View;
use App\Services\BookingService;

View::extend('layouts.public');
$price = BookingService::currentPrice($service);
?>

<?php View::start('title'); ?><?= e($service['name']) ?><?php View::stop(); ?>
<?php View::start('description'); ?><?= e($service['short_description']) ?><?php View::stop(); ?>

<?php View::start('content'); ?>
<section class="section">
    <div class="container">
        <p class="text-small text-muted">
            <a href="<?= e(url('/servicios')) ?>">Servicios</a> /
            <?= e($service['category_name']) ?>
        </p>

        <div class="hero__grid mt-2">
            <div>
                <h1><?= e($service['name']) ?></h1>
                <p class="hero__lead"><?= e($service['short_description']) ?></p>

                <div class="flex gap-2 flex-wrap mt-2">
                    <span class="pill pill--primary"><?= e(money($price)) ?></span>
                    <span class="pill"><?= e(minutes_to_human((int) $service['duration_minutes'])) ?></span>
                    <?php if ((int) $service['loyalty_points'] > 0): ?>
                        <span class="pill pill--success">+<?= (int) $service['loyalty_points'] ?> puntos</span>
                    <?php endif; ?>
                    <?php if ((bool) $service['deposit_required']): ?>
                        <span class="pill pill--warning">Requiere abono</span>
                    <?php endif; ?>
                </div>

                <?php if ((string) ($service['description'] ?? '') !== ''): ?>
                    <div class="mt-3"><p class="text-muted"><?= nl2br(e($service['description'])) ?></p></div>
                <?php endif; ?>

                <a class="btn btn--primary mt-3" href="<?= e(url('/agendar?servicio=' . (int) $service['id'])) ?>">
                    Reservar este servicio
                </a>
            </div>

            <?php if ((string) $service['image_path'] !== ''): ?>
                <div><img src="<?= e(media_url($service['image_path'])) ?>" alt="<?= e($service['name']) ?>"
                          style="border-radius:var(--radius)"></div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($staff !== []): ?>
<section class="section section--alt">
    <div class="container">
        <div class="section__head"><h2>Quien puede atenderte</h2></div>
        <div class="grid grid--4">
            <?php foreach ($staff as $member): ?>
                <article class="card staff-card">
                    <?php if ((string) $member['photo_path'] !== ''): ?>
                        <img class="staff-card__photo" src="<?= e(media_url($member['photo_path'])) ?>"
                             alt="<?= e($member['display_name']) ?>" loading="lazy" width="118" height="118">
                    <?php else: ?>
                        <div class="staff-card__photo"><?= e(initials((string) $member['display_name'])) ?></div>
                    <?php endif; ?>
                    <div class="card__body">
                        <h3 class="card__title"><?= e($member['display_name']) ?></h3>
                        <p class="staff-card__role"><?= e($member['title']) ?></p>
                        <a class="btn btn--ghost btn--sm mt-2"
                           href="<?= e(url('/agendar?servicio=' . (int) $service['id'] . '&profesional=' . (int) $member['id'])) ?>">
                            Reservar
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($related !== []): ?>
<section class="section">
    <div class="container">
        <div class="section__head"><h2>Tambien te puede interesar</h2></div>
        <div class="grid grid--4">
            <?php foreach ($related as $item): ?>
                <a class="card" href="<?= e(url('/servicios/' . rawurlencode((string) $item['slug']))) ?>">
                    <div class="card__body">
                        <h3 class="card__title"><?= e($item['name']) ?></h3>
                        <p class="card__text"><?= e($item['short_description']) ?></p>
                        <span class="card__price mt-2"><?= e(money(BookingService::currentPrice($item))) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
<?php View::stop(); ?>
