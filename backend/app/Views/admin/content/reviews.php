<?php
/**
 * @var array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int} $result
 * @var string $filter
 */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Resenas<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="help-box">
    Las resenas solo se publican en la web despues de que las apruebes. Puedes responder
    publicamente a cada una: es una de las mejores formas de generar confianza.
</div>

<div class="tabs">
    <?php foreach (['pendientes' => 'Por moderar', 'publicadas' => 'Publicadas', 'todas' => 'Todas'] as $key => $label): ?>
        <a class="tab <?= $filter === $key ? 'is-active' : '' ?>"
           href="<?= e(url('/panel/contenido/resenas?estado=' . $key)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($result['data'] === []): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state__icon">&#9733;</div>
            <p>No hay resenas en esta seccion.</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($result['data'] as $review): ?>
        <div class="card">
            <div class="card__head">
                <div>
                    <strong><?= e($review['author_name']) ?></strong>
                    <span style="color:var(--a-warning)"><?= e(str_repeat('*', (int) $review['rating'])) ?></span>
                    <div class="text-small text-muted">
                        <?= e(local_datetime((string) $review['created_at'])) ?>
                        <?php if (($review['staff_name'] ?? null) !== null): ?>
                            &middot; sobre <?= e($review['staff_name']) ?>
                        <?php endif; ?>
                        <?php if (($review['appointment_code'] ?? null) !== null): ?>
                            &middot; cita <?= e($review['appointment_code']) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flex gap-1">
                    <?php if ((bool) $review['is_approved']): ?>
                        <span class="pill pill--success">Publicada</span>
                    <?php else: ?>
                        <span class="pill pill--warning">Por moderar</span>
                    <?php endif; ?>
                    <?php if ((bool) $review['is_featured']): ?>
                        <span class="pill pill--primary">Destacada</span>
                    <?php endif; ?>
                </div>
            </div>

            <p><?= nl2br(e((string) ($review['comment'] ?? ''))) ?></p>

            <?php if ((string) ($review['reply'] ?? '') !== ''): ?>
                <div class="alert alert--info">
                    <span><strong>Tu respuesta:</strong> <?= e($review['reply']) ?></span>
                </div>
            <?php endif; ?>

            <div class="btn-row mt-2">
                <?php if (!(bool) $review['is_approved']): ?>
                    <form method="post" action="<?= e(url('/panel/contenido/resenas/' . (int) $review['id'])) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="aprobar">
                        <button type="submit" class="btn btn--success btn--sm">Publicar</button>
                    </form>
                <?php endif; ?>

                <form method="post" action="<?= e(url('/panel/contenido/resenas/' . (int) $review['id'])) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="destacar">
                    <button type="submit" class="btn btn--ghost btn--sm">
                        <?= (bool) $review['is_featured'] ? 'Quitar destacado' : 'Destacar' ?>
                    </button>
                </form>

                <details class="collapsible" style="flex:1;min-width:240px">
                    <summary class="btn btn--ghost btn--sm">Responder</summary>
                    <form method="post" class="mt-1"
                          action="<?= e(url('/panel/contenido/resenas/' . (int) $review['id'])) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="responder">
                        <div class="field">
                            <label for="reply-<?= (int) $review['id'] ?>">Respuesta publica</label>
                            <textarea id="reply-<?= (int) $review['id'] ?>" name="reply" rows="3"
                                      maxlength="2000"><?= e($review['reply'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn--primary btn--sm">Publicar respuesta</button>
                    </form>
                </details>

                <form method="post" data-confirm="Eliminar esta resena?"
                      action="<?= e(url('/panel/contenido/resenas/' . (int) $review['id'])) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="eliminar">
                    <button type="submit" class="btn btn--ghost btn--sm">Eliminar</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?= View::partial('partials.pagination', [
    'result' => $result, 'baseUrl' => url('/panel/contenido/resenas'), 'query' => ['estado' => $filter],
]) ?>
<?php View::stop(); ?>
