<?php
/** @var array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int} $result */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Mensajes de contacto<?php View::stop(); ?>

<?php View::start('content'); ?>
<?php if ($result['data'] === []): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state__icon">&#128172;</div>
            <p>No hay mensajes.</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($result['data'] as $message): ?>
        <div class="card">
            <div class="card__head">
                <div>
                    <strong><?= e($message['name']) ?></strong>
                    <?php if (!(bool) $message['is_read']): ?>
                        <span class="pill pill--warning">Nuevo</span>
                    <?php endif; ?>
                    <div class="text-small text-muted">
                        <?= e(local_datetime((string) $message['created_at'])) ?>
                        <?php if ((string) $message['email'] !== ''): ?>
                            &middot; <a href="mailto:<?= e($message['email']) ?>"><?= e($message['email']) ?></a>
                        <?php endif; ?>
                        <?php if ((string) $message['phone'] !== ''): ?>
                            &middot; <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $message['phone']) ?? '') ?>">
                                <?= e($message['phone']) ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ((string) $message['subject'] !== ''): ?>
                <p class="text-small text-muted mb-1"><strong><?= e($message['subject']) ?></strong></p>
            <?php endif; ?>

            <p><?= nl2br(e((string) $message['message'])) ?></p>

            <div class="btn-row">
                <?php if (!(bool) $message['is_read']): ?>
                    <form method="post" action="<?= e(url('/panel/contenido/mensajes/' . (int) $message['id'] . '/leido')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--ghost btn--sm">Marcar como atendido</button>
                    </form>
                <?php endif; ?>

                <form method="post" data-confirm="Eliminar este mensaje?"
                      action="<?= e(url('/panel/contenido/mensajes/' . (int) $message['id'] . '/eliminar')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--ghost btn--sm">Eliminar</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?= View::partial('partials.pagination', [
    'result' => $result, 'baseUrl' => url('/panel/contenido/mensajes'), 'query' => [],
]) ?>
<?php View::stop(); ?>
