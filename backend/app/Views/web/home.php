<?php
/**
 * Portada.
 *
 * Cada bloque se pinta solo si esta activo en el panel; el orden y los textos
 * tambien salen de alli.
 *
 * @var array<string,array<string,mixed>> $blocks
 * @var list<array<string,mixed>> $categories
 * @var list<array<string,mixed>> $featuredServices
 * @var list<array<string,mixed>> $team
 * @var list<array<string,mixed>> $gallery
 * @var list<array<string,mixed>> $reviews
 * @var list<array<string,mixed>> $faqs
 * @var list<array<string,mixed>> $branches
 * @var array<string,int> $stats
 * @var list<array<string,mixed>> $heroBanners
 * @var array<string,mixed>|null $sidebarBanner
 */

use App\Core\View;
use App\Services\SettingsService;

View::extend('layouts.public');

$hero = $blocks['hero'] ?? null;
$appPromo = $blocks['app_promo'] ?? null;
$about = $blocks['about'] ?? null;
?>

<?php View::start('title'); ?>Inicio<?php View::stop(); ?>

<?php View::start('content'); ?>

<?php if ($hero !== null): ?>
<section class="hero">
    <div class="container">
        <div class="hero__grid">
            <div>
                <span class="hero__eyebrow"><?= e(SettingsService::string('business.tagline', 'Cita previa')) ?></span>
                <h1><?= e($hero['title']) ?></h1>
                <p class="hero__lead"><?= e($hero['subtitle']) ?></p>

                <div class="hero__actions">
                    <?php if ((string) $hero['cta_label'] !== ''): ?>
                        <a class="btn btn--primary" href="<?= e_url($hero['cta_url'] ?: '/agendar') ?>">
                            <?= e($hero['cta_label']) ?>
                        </a>
                    <?php endif; ?>
                    <?php if ((string) $hero['cta_secondary_label'] !== ''): ?>
                        <a class="btn btn--ghost" href="<?= e_url($hero['cta_secondary_url'] ?: '/app') ?>">
                            <?= e($hero['cta_secondary_label']) ?>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="hero__stats">
                    <?php if (($stats['services'] ?? 0) > 0): ?>
                        <div class="hero__stat">
                            <strong><?= (int) $stats['services'] ?></strong>
                            <span>Servicios disponibles</span>
                        </div>
                    <?php endif; ?>
                    <?php if (($stats['staff'] ?? 0) > 0): ?>
                        <div class="hero__stat">
                            <strong><?= (int) $stats['staff'] ?></strong>
                            <span><?= $stats['staff'] === 1 ? 'Profesional' : 'Profesionales' ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (($stats['completed'] ?? 0) >= 25): ?>
                        <div class="hero__stat">
                            <strong><?= (int) $stats['completed'] ?>+</strong>
                            <span>Clientes atendidos</span>
                        </div>
                    <?php endif; ?>
                    <div class="hero__stat">
                        <strong>24/7</strong>
                        <span>Agenda en linea</span>
                    </div>
                </div>
            </div>

            <div class="hero__media">
                <img src="<?= e((string) $hero['image_path'] !== ''
                    ? media_url($hero['image_path'])
                    : asset('img/hero-default.svg')) ?>"
                     alt="" width="640" height="480" fetchpriority="high">
            </div>
        </div>

        <?php foreach ($heroBanners as $banner): ?>
            <div class="ad-hero mt-4"
                 data-banner-id="<?= (int) $banner['id'] ?>"
                 data-banner-placement="web_hero"
                 style="background: <?= e($banner['background_color']) ?>; color: <?= e($banner['text_color']) ?>">
                <div class="ad-hero__body">
                    <h3><?= e($banner['title']) ?></h3>
                    <p><?= e($banner['subtitle']) ?></p>
                    <?php if ((string) $banner['cta_label'] !== ''): ?>
                        <a class="btn btn--primary" data-banner-cta href="<?= e_url($banner['cta_url']) ?>">
                            <?= e($banner['cta_label']) ?>
                        </a>
                    <?php endif; ?>
                </div>
                <?php if ((string) $banner['image_url'] !== ''): ?>
                    <div class="ad-hero__media">
                        <img src="<?= e($banner['image_url']) ?>" alt="" loading="lazy">
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($categories !== []): ?>
<section class="section section--alt">
    <div class="container">
        <div class="section__head">
            <span class="section__eyebrow">Que necesitas hoy</span>
            <h2><?= e($blocks['services_intro']['title'] ?? 'Nuestros servicios') ?></h2>
            <p><?= e($blocks['services_intro']['subtitle'] ?? '') ?></p>
        </div>

        <div class="grid grid--3">
            <?php foreach ($categories as $category): ?>
                <?php if (!(bool) $category['show_on_home']) { continue; } ?>
                <a class="card" href="<?= e(url('/servicios?categoria=' . rawurlencode((string) $category['slug']))) ?>">
                    <?php if ((string) $category['image_path'] !== ''): ?>
                        <div class="card__media">
                            <img src="<?= e(media_url($category['image_path'])) ?>" alt="" loading="lazy">
                        </div>
                    <?php endif; ?>
                    <div class="card__body">
                        <span class="pill" style="background: <?= e($category['color']) ?>22; color: <?= e($category['color']) ?>">
                            <?= e($category['name']) ?>
                        </span>
                        <h3 class="card__title mt-2"><?= e($category['name']) ?></h3>
                        <p class="card__text"><?= e($category['description']) ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($featuredServices !== []): ?>
<section class="section">
    <div class="container">
        <div class="section__head">
            <span class="section__eyebrow">Lo mas pedido</span>
            <h2>Servicios destacados</h2>
        </div>

        <div class="grid grid--4">
            <?php foreach ($featuredServices as $service): ?>
                <?php $price = \App\Services\BookingService::currentPrice($service); ?>
                <article class="card">
                    <?php if ((string) $service['image_path'] !== ''): ?>
                        <div class="card__media">
                            <img src="<?= e(media_url($service['image_path'])) ?>" alt="<?= e($service['name']) ?>" loading="lazy">
                        </div>
                    <?php endif; ?>
                    <div class="card__body">
                        <span class="pill pill--primary"><?= e($service['category_name']) ?></span>
                        <h3 class="card__title mt-2"><?= e($service['name']) ?></h3>
                        <p class="card__text"><?= e($service['short_description']) ?></p>
                        <div class="card__footer">
                            <span class="card__price">
                                <?php if ($price < (float) $service['price']): ?>
                                    <del><?= e(money((float) $service['price'])) ?></del>
                                <?php endif; ?>
                                <?= e(money($price)) ?>
                            </span>
                            <span class="pill"><?= e(minutes_to_human((int) $service['duration_minutes'])) ?></span>
                        </div>
                        <a class="btn btn--primary btn--sm mt-2"
                           href="<?= e(url('/agendar?servicio=' . (int) $service['id'])) ?>">Reservar</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <p class="text-center mt-4">
            <a class="btn btn--ghost" href="<?= e(url('/servicios')) ?>">Ver el catalogo completo</a>
        </p>
    </div>
</section>
<?php endif; ?>

<?php if ($appPromo !== null): ?>
<section class="section section--alt">
    <div class="container">
        <div class="app-promo">
            <div class="app-promo__grid">
                <div>
                    <span class="section__eyebrow">Aplicacion movil</span>
                    <h2><?= e($appPromo['title']) ?></h2>
                    <p class="text-muted"><?= e($appPromo['subtitle']) ?></p>

                    <div class="mt-3">
                        <div class="app-feature">
                            <div class="app-feature__icon">1</div>
                            <div>
                                <strong>Agenda en segundos</strong>
                                <p class="text-muted text-small mb-0">Elige servicio, profesional y horario libre.</p>
                            </div>
                        </div>
                        <div class="app-feature">
                            <div class="app-feature__icon">2</div>
                            <div>
                                <strong>Paga como prefieras</strong>
                                <p class="text-muted text-small mb-0">Efectivo o transferencia subiendo tu comprobante.</p>
                            </div>
                        </div>
                        <div class="app-feature">
                            <div class="app-feature__icon">3</div>
                            <div>
                                <strong>Acumula puntos</strong>
                                <p class="text-muted text-small mb-0">Gana beneficios en cada visita.</p>
                            </div>
                        </div>
                    </div>

                    <div class="app-badges">
                        <a class="btn btn--primary" href="<?= e(url('/app')) ?>">
                            <?= e($appPromo['cta_label'] ?: 'Descargar la app') ?>
                        </a>
                        <a class="btn btn--ghost" href="<?= e(url('/agendar')) ?>">Prefiero reservar por la web</a>
                    </div>
                </div>

                <div>
                    <?php if ((string) $appPromo['image_path'] !== ''): ?>
                        <img src="<?= e(media_url($appPromo['image_path'])) ?>" alt="Pantalla de la aplicacion" loading="lazy">
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($team !== []): ?>
<section class="section">
    <div class="container">
        <div class="section__head">
            <span class="section__eyebrow">Quienes te atienden</span>
            <h2><?= e($blocks['team_intro']['title'] ?? 'Nuestro equipo') ?></h2>
            <p><?= e($blocks['team_intro']['subtitle'] ?? '') ?></p>
        </div>

        <div class="grid grid--4">
            <?php foreach ($team as $member): ?>
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

                        <?php if ((int) $member['rating_count'] > 0): ?>
                            <p class="rating">
                                <?= e(str_repeat('*', (int) round((float) $member['rating_average']))) ?>
                                <span class="text-muted text-small">(<?= (int) $member['rating_count'] ?>)</span>
                            </p>
                        <?php endif; ?>

                        <a class="btn btn--ghost btn--sm mt-2"
                           href="<?= e(url('/agendar?profesional=' . (int) $member['id'])) ?>">Reservar con <?= e(explode(' ', (string) $member['display_name'])[0]) ?></a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($gallery !== []): ?>
<section class="section section--alt">
    <div class="container">
        <div class="section__head">
            <span class="section__eyebrow">Resultados reales</span>
            <h2><?= e($blocks['gallery_intro']['title'] ?? 'Nuestro trabajo') ?></h2>
        </div>

        <div class="gallery-grid">
            <?php foreach ($gallery as $item): ?>
                <figure class="gallery-item">
                    <img src="<?= e(media_url($item['image_path'])) ?>"
                         alt="<?= e($item['title'] ?: 'Trabajo realizado') ?>" loading="lazy">
                    <?php if ((string) $item['title'] !== ''): ?>
                        <figcaption class="gallery-item__caption"><?= e($item['title']) ?></figcaption>
                    <?php endif; ?>
                </figure>
            <?php endforeach; ?>
        </div>

        <p class="text-center mt-4">
            <a class="btn btn--ghost" href="<?= e(url('/galeria')) ?>">Ver toda la galeria</a>
        </p>
    </div>
</section>
<?php endif; ?>

<?php if ($reviews !== []): ?>
<section class="section">
    <div class="container">
        <div class="section__head">
            <span class="section__eyebrow">Opiniones</span>
            <h2><?= e($blocks['reviews_intro']['title'] ?? 'Lo que dicen nuestros clientes') ?></h2>
        </div>

        <div class="grid grid--3">
            <?php foreach ($reviews as $review): ?>
                <article class="card">
                    <div class="card__body">
                        <p class="rating"><?= e(str_repeat('*', (int) $review['rating'])) ?></p>
                        <p class="card__text">&ldquo;<?= e(str_limit((string) $review['comment'], 220)) ?>&rdquo;</p>
                        <p class="text-small text-muted mb-0 mt-2">&mdash; <?= e($review['author_name']) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($about !== null): ?>
<section class="section section--alt">
    <div class="container">
        <div class="hero__grid">
            <div>
                <span class="section__eyebrow"><?= e($about['subtitle']) ?></span>
                <h2><?= e($about['title']) ?></h2>
                <p class="text-muted"><?= e($about['body']) ?></p>
                <a class="btn btn--primary mt-2" href="<?= e(url('/agendar')) ?>">Reservar ahora</a>
            </div>
            <?php if ((string) $about['image_path'] !== ''): ?>
                <div><img src="<?= e(media_url($about['image_path'])) ?>" alt="" loading="lazy"
                          style="border-radius:var(--radius)"></div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($faqs !== []): ?>
<section class="section">
    <div class="container" style="max-width:840px">
        <div class="section__head">
            <span class="section__eyebrow">Dudas frecuentes</span>
            <h2>Preguntas frecuentes</h2>
        </div>

        <?php foreach ($faqs as $faq): ?>
            <details class="card mb-2" style="padding:18px 20px">
                <summary style="cursor:pointer;font-weight:600"><?= e($faq['question']) ?></summary>
                <p class="text-muted mt-2 mb-0"><?= e($faq['answer']) ?></p>
            </details>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($branches !== []): ?>
<section class="section section--alt">
    <div class="container">
        <div class="section__head">
            <span class="section__eyebrow">Donde estamos</span>
            <h2><?= e($blocks['contact']['title'] ?? 'Visitanos') ?></h2>
        </div>

        <div class="grid grid--3">
            <?php foreach ($branches as $branch): ?>
                <article class="card">
                    <div class="card__body">
                        <h3 class="card__title"><?= e($branch['name']) ?></h3>
                        <p class="card__text">
                            <?= e($branch['address']) ?><br>
                            <?= e($branch['city']) ?>
                        </p>
                        <?php if ((string) $branch['phone'] !== ''): ?>
                            <p class="text-small mb-0">
                                <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $branch['phone']) ?? '') ?>">
                                    <?= e($branch['phone']) ?>
                                </a>
                            </p>
                        <?php endif; ?>
                        <?php if ((string) $branch['maps_url'] !== ''): ?>
                            <a class="btn btn--ghost btn--sm mt-2" target="_blank" rel="noopener noreferrer"
                               href="<?= e_url($branch['maps_url']) ?>">Como llegar</a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php View::stop(); ?>
