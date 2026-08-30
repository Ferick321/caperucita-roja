<?php
/**
 * @var list<array<string,mixed>> $services
 * @var list<array<string,mixed>> $categories
 * @var string $activeCategory
 * @var string $search
 */

use App\Core\View;
use App\Services\BookingService;

View::extend('layouts.public');
?>

<?php View::start('title'); ?>Servicios<?php View::stop(); ?>

<?php View::start('content'); ?>
<section class="section">
    <div class="container">
        <div class="section__head">
            <span class="section__eyebrow">Catalogo</span>
            <h1>Nuestros servicios</h1>
            <p>Elige lo que necesitas y reserva en linea. Si no encuentras algo, escribenos y lo resolvemos.</p>
        </div>

        <form method="get" action="<?= e(url('/servicios')) ?>" class="flex gap-2 flex-wrap mb-3" style="justify-content:center">
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Buscar un servicio..."
                   style="max-width:280px" aria-label="Buscar servicio">
            <select name="categoria" aria-label="Filtrar por categoria" style="max-width:220px">
                <option value="">Todas las categorias</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e($category['slug']) ?>" <?= $activeCategory === (string) $category['slug'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn--primary">Filtrar</button>
        </form>

        <?php if ($services === []): ?>
            <div class="empty-state">
                <div class="empty-state__icon">&#9986;</div>
                <p>No encontramos servicios con esos criterios.</p>
                <a class="btn btn--ghost" href="<?= e(url('/servicios')) ?>">Ver todos</a>
            </div>
        <?php else: ?>
            <div class="grid grid--3">
                <?php foreach ($services as $service): ?>
                    <?php $price = BookingService::currentPrice($service); ?>
                    <article class="card">
                        <?php if ((string) $service['image_path'] !== ''): ?>
                            <div class="card__media">
                                <img src="<?= e(media_url($service['image_path'])) ?>"
                                     alt="<?= e($service['name']) ?>" loading="lazy">
                            </div>
                        <?php endif; ?>
                        <div class="card__body">
                            <span class="pill" style="background: <?= e($service['category_color']) ?>22; color: <?= e($service['category_color']) ?>">
                                <?= e($service['category_name']) ?>
                            </span>
                            <h2 class="card__title mt-2" style="font-size:1.08rem"><?= e($service['name']) ?></h2>
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

                            <div class="flex gap-1 mt-2">
                                <a class="btn btn--primary btn--sm" style="flex:1"
                                   href="<?= e(url('/agendar?servicio=' . (int) $service['id'])) ?>">Reservar</a>
                                <a class="btn btn--ghost btn--sm"
                                   href="<?= e(url('/servicios/' . rawurlencode((string) $service['slug']))) ?>">Detalle</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php View::stop(); ?>
