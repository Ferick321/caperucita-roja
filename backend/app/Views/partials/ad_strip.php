<?php
/**
 * Franja publicitaria bajo el menu.
 *
 * @var array<string,mixed> $banner
 */
?>
<div class="ad-strip"
     data-banner-id="<?= (int) $banner['id'] ?>"
     data-banner-placement="web_strip"
     style="background: <?= e($banner['background_color']) ?>; color: <?= e($banner['text_color']) ?>">
    <span><strong><?= e($banner['title']) ?></strong>
        <?php if ((string) $banner['subtitle'] !== ''): ?>
            &nbsp;<?= e($banner['subtitle']) ?>
        <?php endif; ?>
    </span>

    <?php if ((string) $banner['cta_label'] !== '' && (string) $banner['cta_url'] !== ''): ?>
        <a class="btn btn--sm btn--ghost" data-banner-cta href="<?= e_url($banner['cta_url']) ?>">
            <?= e($banner['cta_label']) ?>
        </a>
    <?php endif; ?>

    <?php if ((bool) $banner['is_dismissible']): ?>
        <button type="button" class="ad-strip__close" data-banner-close aria-label="Cerrar aviso">&times;</button>
    <?php endif; ?>
</div>
