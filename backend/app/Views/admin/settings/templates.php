<?php
/** @var list<array<string,mixed>> $templates */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Plantillas de correo<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="help-box">
    Estos son los mensajes que reciben tus clientes. Puedes cambiar el texto usando las variables
    entre llaves: se sustituyen automaticamente por los datos reales de cada cita.
</div>

<?php foreach ($templates as $template): ?>
    <div class="card">
        <details class="collapsible">
            <summary>
                <span>
                    <strong><?= e($template['name']) ?></strong>
                    <span class="text-muted text-small">&middot; <?= e($template['template_key']) ?></span>
                </span>
                <?php if (!(bool) $template['is_active']): ?>
                    <span class="pill pill--danger">Desactivada</span>
                <?php endif; ?>
            </summary>

            <form method="post" action="<?= e(url('/panel/ajustes/plantillas/' . (int) $template['id'])) ?>" class="mt-2">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="subject-<?= (int) $template['id'] ?>">Asunto</label>
                    <input id="subject-<?= (int) $template['id'] ?>" type="text" name="subject"
                           value="<?= e($template['subject']) ?>" maxlength="200" required>
                </div>

                <div class="field">
                    <label for="body-<?= (int) $template['id'] ?>">Contenido</label>
                    <textarea id="body-<?= (int) $template['id'] ?>" name="body" rows="9" required><?= e($template['body']) ?></textarea>
                    <span class="field__hint">
                        Variables disponibles:
                        <span class="mono"><?= e($template['available_vars']) ?></span>
                    </span>
                </div>

                <label class="checkbox">
                    <input type="checkbox" name="is_active" value="1" <?= (bool) $template['is_active'] ? 'checked' : '' ?>>
                    <span>Enviar este aviso</span>
                </label>

                <button type="submit" class="btn btn--primary btn--sm">Guardar plantilla</button>
            </form>
        </details>
    </div>
<?php endforeach; ?>
<?php View::stop(); ?>
