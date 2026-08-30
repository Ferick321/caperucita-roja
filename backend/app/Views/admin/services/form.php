<?php
/**
 * @var array<string,mixed>|null $service
 * @var list<array<string,mixed>> $categories
 * @var list<array<string,mixed>> $staffList
 * @var list<mixed> $assignedStaff
 */

use App\Core\View;

View::extend('layouts.admin');

$id = $service === null ? 0 : (int) $service['id'];
$action = $id > 0 ? url('/panel/servicios/' . $id) : url('/panel/servicios');
$assigned = array_map('intval', $assignedStaff);
$value = static fn (string $key, mixed $default = ''): mixed => $service[$key] ?? $default;
?>
<?php View::start('title'); ?><?= $id > 0 ? 'Editar servicio' : 'Nuevo servicio' ?><?php View::stop(); ?>

<?php View::start('content'); ?>
<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="grid grid--sidebar">
        <div>
            <div class="card">
                <h2>Informacion basica</h2>

                <div class="field">
                    <label for="name">Nombre del servicio *</label>
                    <input id="name" type="text" name="name" required maxlength="140"
                           value="<?= e($value('name', old('name'))) ?>">
                    <?= field_error('name') ?>
                </div>

                <div class="field">
                    <label for="category_id">Categoria *</label>
                    <select id="category_id" name="category_id" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>"
                                    <?= (int) $value('category_id') === (int) $category['id'] ? 'selected' : '' ?>>
                                <?= e($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="short_description">Descripcion corta</label>
                    <input id="short_description" type="text" name="short_description" maxlength="255"
                           value="<?= e($value('short_description')) ?>"
                           placeholder="Se muestra en las tarjetas de la web y la app">
                </div>

                <div class="field">
                    <label for="description">Descripcion completa</label>
                    <textarea id="description" name="description" rows="5" maxlength="5000"><?= e($value('description')) ?></textarea>
                </div>

                <div class="field">
                    <label for="image">Fotografia</label>
                    <?php if ((string) $value('image_path') !== ''): ?>
                        <img src="<?= e(media_url((string) $value('image_path'))) ?>" alt=""
                             style="max-height:110px;border-radius:9px;margin-bottom:8px">
                    <?php endif; ?>
                    <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp"
                           data-preview="#preview-image">
                    <img id="preview-image" class="hidden mt-1" alt="" style="max-height:110px;border-radius:9px">
                    <span class="field__hint">Se recorta automaticamente y se optimiza para carga rapida.</span>
                </div>
            </div>

            <div class="card">
                <h2>Duracion y precio</h2>

                <div class="form-row--3 form-row">
                    <div class="field">
                        <label for="duration_minutes">Duracion (minutos) *</label>
                        <input id="duration_minutes" type="number" name="duration_minutes" required
                               min="5" max="600" step="5" value="<?= e((string) $value('duration_minutes', 30)) ?>">
                        <?= field_error('duration_minutes') ?>
                    </div>
                    <div class="field">
                        <label for="buffer_before_minutes">Preparacion previa</label>
                        <input id="buffer_before_minutes" type="number" name="buffer_before_minutes"
                               min="0" max="120" value="<?= e((string) $value('buffer_before_minutes', 0)) ?>">
                    </div>
                    <div class="field">
                        <label for="buffer_after_minutes">Limpieza posterior</label>
                        <input id="buffer_after_minutes" type="number" name="buffer_after_minutes"
                               min="0" max="120" value="<?= e((string) $value('buffer_after_minutes', 5)) ?>">
                        <span class="field__hint">Se reserva en la agenda pero no se cobra.</span>
                    </div>
                </div>

                <div class="form-row--3 form-row">
                    <div class="field">
                        <label for="price">Precio *</label>
                        <input id="price" type="number" name="price" step="0.01" min="0" required
                               value="<?= e((string) $value('price', '0.00')) ?>">
                        <?= field_error('price') ?>
                    </div>
                    <div class="field">
                        <label for="promo_price">Precio promocional</label>
                        <input id="promo_price" type="number" name="promo_price" step="0.01" min="0"
                               value="<?= e((string) ($value('promo_price') ?? '')) ?>">
                        <span class="field__hint">Dejar vacio para no aplicar.</span>
                    </div>
                    <div class="field">
                        <label for="loyalty_points">Puntos que otorga</label>
                        <input id="loyalty_points" type="number" name="loyalty_points" min="0"
                               value="<?= e((string) $value('loyalty_points', 0)) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="promo_starts_at">La promocion empieza</label>
                        <input id="promo_starts_at" type="date" name="promo_starts_at"
                               value="<?= e($value('promo_starts_at') !== null && $value('promo_starts_at') !== ''
                                   ? local_datetime((string) $value('promo_starts_at'), 'Y-m-d') : '') ?>">
                    </div>
                    <div class="field">
                        <label for="promo_ends_at">La promocion termina</label>
                        <input id="promo_ends_at" type="date" name="promo_ends_at"
                               value="<?= e($value('promo_ends_at') !== null && $value('promo_ends_at') !== ''
                                   ? local_datetime((string) $value('promo_ends_at'), 'Y-m-d') : '') ?>">
                    </div>
                </div>

                <fieldset>
                    <legend>Abono para reservar</legend>

                    <label class="checkbox">
                        <input type="checkbox" name="deposit_required" value="1"
                               <?= (bool) $value('deposit_required') ? 'checked' : '' ?>>
                        <span>Exigir un abono al reservar este servicio</span>
                    </label>

                    <div class="form-row">
                        <div class="field">
                            <label for="deposit_amount">Importe del abono</label>
                            <input id="deposit_amount" type="number" name="deposit_amount" step="0.01" min="0"
                                   value="<?= e((string) $value('deposit_amount', '0.00')) ?>">
                        </div>
                        <div class="field">
                            <label class="checkbox mt-3">
                                <input type="checkbox" name="deposit_is_percentage" value="1"
                                       <?= (bool) $value('deposit_is_percentage') ? 'checked' : '' ?>>
                                <span>El importe es un porcentaje del total</span>
                            </label>
                        </div>
                    </div>
                </fieldset>
            </div>

            <?php if ($staffList !== []): ?>
                <div class="card">
                    <h2>Quien puede prestarlo</h2>
                    <p class="text-muted text-small">
                        Solo los profesionales marcados apareceran como opcion al reservar este servicio.
                    </p>

                    <?php foreach ($staffList as $member): ?>
                        <label class="checkbox">
                            <input type="checkbox" name="staff_ids[]" value="<?= (int) $member['id'] ?>"
                                   <?= in_array((int) $member['id'], $assigned, true) ? 'checked' : '' ?>>
                            <span><?= e($member['display_name']) ?>
                                <span class="text-muted text-small">&middot; <?= e($member['title']) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div>
            <div class="card">
                <h3>Visibilidad</h3>

                <label class="checkbox">
                    <input type="checkbox" name="is_active" value="1"
                           <?= $service === null || (bool) $value('is_active') ? 'checked' : '' ?>>
                    <span>Servicio activo</span>
                </label>

                <label class="checkbox">
                    <input type="checkbox" name="bookable_online" value="1"
                           <?= $service === null || (bool) $value('bookable_online') ? 'checked' : '' ?>>
                    <span>Se puede reservar por la web y la app</span>
                </label>

                <label class="checkbox">
                    <input type="checkbox" name="is_featured" value="1"
                           <?= (bool) $value('is_featured') ? 'checked' : '' ?>>
                    <span>Destacar en la portada</span>
                </label>

                <label class="checkbox">
                    <input type="checkbox" name="requires_consultation" value="1"
                           <?= (bool) $value('requires_consultation') ? 'checked' : '' ?>>
                    <span>Requiere valoracion previa</span>
                </label>

                <div class="field mt-2">
                    <label for="gender_target">Dirigido a</label>
                    <select id="gender_target" name="gender_target">
                        <?php foreach ([
                            'all' => 'Todos', 'male' => 'Hombres', 'female' => 'Mujeres', 'kids' => 'Ninos',
                        ] as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= (string) $value('gender_target', 'all') === $key ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="max_per_day">Maximo por dia</label>
                        <input id="max_per_day" type="number" name="max_per_day" min="0"
                               value="<?= e((string) $value('max_per_day', 0)) ?>">
                        <span class="field__hint">0 = sin limite</span>
                    </div>
                    <div class="field">
                        <label for="sort_order">Orden</label>
                        <input id="sort_order" type="number" name="sort_order" min="0"
                               value="<?= e((string) $value('sort_order', 0)) ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn--primary btn--block mt-2">
                    <?= $id > 0 ? 'Guardar cambios' : 'Crear servicio' ?>
                </button>
                <a class="btn btn--ghost btn--block mt-1" href="<?= e(url('/panel/servicios')) ?>">Cancelar</a>
            </div>
        </div>
    </div>
</form>
<?php View::stop(); ?>
