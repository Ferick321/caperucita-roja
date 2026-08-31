<?php
/**
 * @var array<string,mixed>|null $policy
 * @var list<string> $tables
 */

use App\Core\View;

View::extend('layouts.admin');

$esNueva = $policy === null;
$accion = $esNueva
    ? '/panel/mantenimiento/retencion'
    : '/panel/mantenimiento/retencion/' . (int) $policy['id'];
?>
<?php View::start('title'); ?><?= $esNueva ? 'Nueva regla de limpieza' : 'Editar regla' ?><?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/mantenimiento/retencion')) ?>">&larr; Reglas</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="help-box">
    Una regla borra sola los datos viejos. Elige que datos, con que fecha se miden y
    cuantos dias quieres conservarlos. Antes de que se borre nada puedes ver que pasaria
    con <strong>Simular limpieza</strong>.
</div>

<form method="post" action="<?= e(url($accion)) ?>">
    <?= csrf_field() ?>

    <div class="card">
        <div class="field">
            <label for="label">Nombre de la regla</label>
            <input type="text" id="label" name="label" required maxlength="160"
                   placeholder="Ej: Borrar mensajes de contacto viejos"
                   value="<?= e(old('label', $policy['label'] ?? '')) ?>">
            <?= field_error('label') ?>
        </div>

        <div class="field">
            <label for="description">Para que sirve <span class="text-muted">(opcional)</span></label>
            <textarea id="description" name="description" rows="2" maxlength="500"
                      placeholder="Una nota para recordar por que pusiste esta regla"><?= e(old('description', $policy['description'] ?? '')) ?></textarea>
            <?= field_error('description') ?>
        </div>

        <div class="grid grid--2">
            <div class="field">
                <label for="target_table">Que datos se limpian</label>
                <select id="target_table" name="target_table" required>
                    <?php $actual = (string) old('target_table', $policy['target_table'] ?? ''); ?>
                    <option value="">Elige...</option>
                    <?php foreach ($tables as $tabla): ?>
                        <option value="<?= e($tabla) ?>" <?= $actual === $tabla ? 'selected' : '' ?>>
                            <?= e($tabla) ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if ($actual !== '' && !in_array($actual, $tables, true)): ?>
                        <option value="<?= e($actual) ?>" selected><?= e($actual) ?></option>
                    <?php endif; ?>
                </select>
                <?= field_error('target_table') ?>
            </div>

            <div class="field">
                <label for="date_column">Fecha que se mira</label>
                <input type="text" id="date_column" name="date_column" required maxlength="64"
                       placeholder="created_at"
                       value="<?= e(old('date_column', $policy['date_column'] ?? 'created_at')) ?>">
                <p class="field__help">
                    Casi siempre <code>created_at</code> (la fecha en que se creo el registro).
                </p>
                <?= field_error('date_column') ?>
            </div>
        </div>

        <div class="field">
            <label for="retention_days">Cuantos dias se conservan</label>
            <input type="number" id="retention_days" name="retention_days" required min="1" max="7300"
                   value="<?= e(old('retention_days', $policy['retention_days'] ?? 90)) ?>">
            <p class="field__help">Lo mas viejo que eso se borra cuando se ejecuta la limpieza.</p>
            <?= field_error('retention_days') ?>
        </div>

        <label class="checkbox">
            <input type="checkbox" name="deletes_files" value="1"
                <?= old('deletes_files', $policy['deletes_files'] ?? 0) ? 'checked' : '' ?>>
            <span>Borrar tambien los archivos asociados <span class="text-muted">(fotos, comprobantes)</span></span>
        </label>

        <label class="checkbox">
            <input type="checkbox" name="is_active" value="1"
                <?= old('is_active', $policy['is_active'] ?? 1) ? 'checked' : '' ?>>
            <span>Regla activa</span>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Guardar regla</button>
            <a class="btn btn--ghost" href="<?= e(url('/panel/mantenimiento/retencion')) ?>">Cancelar</a>
        </div>
    </div>
</form>
<?php View::stop(); ?>
