<?php
/** @var list<array<string,mixed>> $categories */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Categorias<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/servicios')) ?>">&larr; Servicios</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="help-box">
    Las categorias agrupan tu oferta: barberia, peluqueria, manicure, pedicure, estetica o las
    que necesites. Aparecen como filtros en la web y en la app.
</div>

<div class="grid grid--sidebar">
    <div class="card card--flush">
        <?php if ($categories === []): ?>
            <div class="empty-state"><p>Aun no hay categorias.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Categoria</th><th>Servicios</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td>
                                    <span class="pill" style="background: <?= e($category['color']) ?>22; color: <?= e($category['color']) ?>">
                                        <?= e($category['name']) ?>
                                    </span>
                                    <?php if ((string) $category['description'] !== ''): ?>
                                        <div class="text-small text-muted mt-1"><?= e(str_limit((string) $category['description'], 70)) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) $category['service_count'] ?></td>
                                <td>
                                    <?php if ((bool) $category['is_active']): ?>
                                        <span class="pill pill--success">Activa</span>
                                    <?php else: ?>
                                        <span class="pill pill--danger">Oculta</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <details class="collapsible">
                                            <summary class="btn btn--ghost btn--sm">Editar</summary>
                                            <form method="post" enctype="multipart/form-data" class="mt-2"
                                                  action="<?= e(url('/panel/servicios/categorias/' . (int) $category['id'])) ?>">
                                                <?= csrf_field() ?>
                                                <div class="field">
                                                    <label for="cn-<?= (int) $category['id'] ?>">Nombre</label>
                                                    <input id="cn-<?= (int) $category['id'] ?>" type="text" name="name"
                                                           value="<?= e($category['name']) ?>" required maxlength="100">
                                                </div>
                                                <div class="field">
                                                    <label for="cd-<?= (int) $category['id'] ?>">Descripcion</label>
                                                    <input id="cd-<?= (int) $category['id'] ?>" type="text" name="description"
                                                           value="<?= e($category['description']) ?>" maxlength="500">
                                                </div>
                                                <div class="field">
                                                    <label for="cc-<?= (int) $category['id'] ?>">Color</label>
                                                    <div class="flex gap-1 items-center">
                                                        <input type="color" value="<?= e($category['color']) ?>"
                                                               data-color-sync="#cc-<?= (int) $category['id'] ?>">
                                                        <input id="cc-<?= (int) $category['id'] ?>" type="text" name="color"
                                                               value="<?= e($category['color']) ?>" class="mono" style="max-width:130px">
                                                    </div>
                                                </div>
                                                <div class="field">
                                                    <label for="ci-<?= (int) $category['id'] ?>">Imagen</label>
                                                    <input id="ci-<?= (int) $category['id'] ?>" type="file" name="image"
                                                           accept="image/jpeg,image/png,image/webp">
                                                </div>
                                                <label class="checkbox">
                                                    <input type="checkbox" name="is_active" value="1"
                                                           <?= (bool) $category['is_active'] ? 'checked' : '' ?>>
                                                    <span>Activa</span>
                                                </label>
                                                <label class="checkbox">
                                                    <input type="checkbox" name="show_on_home" value="1"
                                                           <?= (bool) $category['show_on_home'] ? 'checked' : '' ?>>
                                                    <span>Mostrar en la portada</span>
                                                </label>
                                                <div class="field">
                                                    <label for="co-<?= (int) $category['id'] ?>">Orden</label>
                                                    <input id="co-<?= (int) $category['id'] ?>" type="number" name="sort_order"
                                                           value="<?= (int) $category['sort_order'] ?>" min="0">
                                                </div>
                                                <button type="submit" class="btn btn--primary btn--sm">Guardar</button>
                                            </form>
                                        </details>

                                        <form method="post" data-confirm="Eliminar esta categoria?"
                                              action="<?= e(url('/panel/servicios/categorias/' . (int) $category['id'] . '/eliminar')) ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn--ghost btn--sm">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Nueva categoria</h3>

        <form method="post" action="<?= e(url('/panel/servicios/categorias')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="field">
                <label for="new-name">Nombre *</label>
                <input id="new-name" type="text" name="name" required maxlength="100"
                       placeholder="Ej. Tratamientos capilares">
                <?= field_error('name') ?>
            </div>

            <div class="field">
                <label for="new-desc">Descripcion</label>
                <input id="new-desc" type="text" name="description" maxlength="500">
            </div>

            <div class="field">
                <label for="new-color">Color</label>
                <div class="flex gap-1 items-center">
                    <input type="color" value="#8b5cf6" data-color-sync="#new-color">
                    <input id="new-color" type="text" name="color" value="#8b5cf6" class="mono" style="max-width:130px">
                </div>
            </div>

            <div class="field">
                <label for="new-image">Imagen</label>
                <input id="new-image" type="file" name="image" accept="image/jpeg,image/png,image/webp">
            </div>

            <label class="checkbox">
                <input type="checkbox" name="is_active" value="1" checked>
                <span>Activa</span>
            </label>
            <label class="checkbox">
                <input type="checkbox" name="show_on_home" value="1" checked>
                <span>Mostrar en la portada</span>
            </label>

            <button type="submit" class="btn btn--primary btn--block mt-1">Crear categoria</button>
        </form>
    </div>
</div>
<?php View::stop(); ?>
