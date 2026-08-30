<?php
/** @var list<array<string,mixed>> $faqs */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Preguntas frecuentes<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="grid grid--sidebar">
    <div>
        <?php if ($faqs === []): ?>
            <div class="card"><div class="empty-state"><p>Aun no hay preguntas.</p></div></div>
        <?php endif; ?>

        <?php foreach ($faqs as $faq): ?>
            <div class="card">
                <details class="collapsible">
                    <summary>
                        <span><strong><?= e($faq['question']) ?></strong></span>
                        <?php if (!(bool) $faq['is_active']): ?>
                            <span class="pill pill--danger">Oculta</span>
                        <?php endif; ?>
                    </summary>

                    <form method="post" action="<?= e(url('/panel/contenido/preguntas/' . (int) $faq['id'])) ?>" class="mt-2">
                        <?= csrf_field() ?>

                        <div class="field">
                            <label for="q-<?= (int) $faq['id'] ?>">Pregunta</label>
                            <input id="q-<?= (int) $faq['id'] ?>" type="text" name="question" required
                                   maxlength="300" value="<?= e($faq['question']) ?>">
                        </div>

                        <div class="field">
                            <label for="a-<?= (int) $faq['id'] ?>">Respuesta</label>
                            <textarea id="a-<?= (int) $faq['id'] ?>" name="answer" rows="4" required
                                      maxlength="4000"><?= e($faq['answer']) ?></textarea>
                        </div>

                        <div class="flex gap-2 items-center flex-wrap">
                            <label class="checkbox" style="margin:0">
                                <input type="checkbox" name="is_active" value="1" <?= (bool) $faq['is_active'] ? 'checked' : '' ?>>
                                <span>Visible</span>
                            </label>
                            <div class="field" style="margin:0;max-width:110px">
                                <label for="so-<?= (int) $faq['id'] ?>">Orden</label>
                                <input id="so-<?= (int) $faq['id'] ?>" type="number" name="sort_order" min="0"
                                       value="<?= (int) $faq['sort_order'] ?>">
                            </div>
                        </div>

                        <button type="submit" class="btn btn--primary btn--sm mt-2">Guardar</button>
                    </form>

                    <form method="post" class="mt-1" data-confirm="Eliminar esta pregunta?"
                          action="<?= e(url('/panel/contenido/preguntas/' . (int) $faq['id'] . '/eliminar')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--ghost btn--sm">Eliminar</button>
                    </form>
                </details>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h3>Nueva pregunta</h3>

        <form method="post" action="<?= e(url('/panel/contenido/preguntas')) ?>">
            <?= csrf_field() ?>

            <div class="field">
                <label for="new-question">Pregunta *</label>
                <input id="new-question" type="text" name="question" required maxlength="300"
                       placeholder="Ej. Aceptan pago con tarjeta?">
                <?= field_error('question') ?>
            </div>

            <div class="field">
                <label for="new-answer">Respuesta *</label>
                <textarea id="new-answer" name="answer" rows="4" required maxlength="4000"></textarea>
                <?= field_error('answer') ?>
            </div>

            <label class="checkbox">
                <input type="checkbox" name="is_active" value="1" checked>
                <span>Publicar en la web</span>
            </label>

            <button type="submit" class="btn btn--primary btn--block">Anadir pregunta</button>
        </form>
    </div>
</div>
<?php View::stop(); ?>
