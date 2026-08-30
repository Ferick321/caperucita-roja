<?php
/** @var list<array<string,mixed>> $items @var array<string,array<string,mixed>> $blocks */

use App\Core\View;

View::extend('layouts.public');
?>
<?php View::start('title'); ?>Galeria<?php View::stop(); ?>

<?php View::start('content'); ?>
<section class="section">
    <div class="container">
        <div class="section__head">
            <span class="section__eyebrow">Nuestro trabajo</span>
            <h1><?= e($blocks['gallery_intro']['title'] ?? 'Galeria') ?></h1>
            <p><?= e($blocks['gallery_intro']['subtitle'] ?? '') ?></p>
        </div>

        <?php if ($items === []): ?>
            <div class="empty-state">
                <div class="empty-state__icon">&#128247;</div>
                <p>Aun no hay fotos publicadas.</p>
            </div>
        <?php else: ?>
            <div class="gallery-grid">
                <?php foreach ($items as $item): ?>
                    <figure class="gallery-item">
                        <img src="<?= e(media_url($item['image_path'])) ?>"
                             alt="<?= e($item['title'] ?: 'Trabajo realizado') ?>" loading="lazy">
                        <?php if ((string) $item['title'] !== ''): ?>
                            <figcaption class="gallery-item__caption"><?= e($item['title']) ?></figcaption>
                        <?php endif; ?>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p class="text-center mt-4">
            <a class="btn btn--primary" href="<?= e(url('/agendar')) ?>">Quiero un resultado asi</a>
        </p>
    </div>
</section>
<?php View::stop(); ?>
