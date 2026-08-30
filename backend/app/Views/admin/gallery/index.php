<?php
/**
 * @var array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int} $result
 * @var list<array<string,mixed>> $categories
 * @var list<array<string,mixed>> $staffList
 */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Galeria<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="grid grid--sidebar">
    <div>
        <?php if ($result['data'] === []): ?>
            <div class="card">
                <div class="empty-state">
                    <div class="empty-state__icon">&#128247;</div>
                    <p>Aun no hay fotos publicadas.</p>
                    <p class="text-small">Sube tus mejores trabajos: es lo que mas convence a un cliente nuevo.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid--3">
                <?php foreach ($result['data'] as $item): ?>
                    <div class="card">
                        <img src="<?= e(media_url((string) $item['image_path'])) ?>" alt=""
                             style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:9px;margin-bottom:10px"
                             data-lightbox-src="<?= e(media_url((string) $item['image_path'])) ?>">

                        <?php if ((string) $item['title'] !== ''): ?>
                            <strong class="text-small"><?= e($item['title']) ?></strong>
                        <?php endif; ?>

                        <div class="flex gap-1 flex-wrap mt-1">
                            <?php if ((bool) $item['is_active']): ?>
                                <span class="pill pill--success">Visible</span>
                            <?php else: ?>
                                <span class="pill pill--danger">Oculta</span>
                            <?php endif; ?>
                            <?php if ((bool) $item['is_featured']): ?>
                                <span class="pill pill--primary">Destacada</span>
                            <?php endif; ?>
                            <?php if (($item['category_name'] ?? null) !== null): ?>
                                <span class="pill"><?= e($item['category_name']) ?></span>
                            <?php endif; ?>
                        </div>

                        <details class="collapsible mt-1">
                            <summary class="text-small">Editar</summary>
                            <form method="post" enctype="multipart/form-data" class="mt-1"
                                  action="<?= e(url('/panel/contenido/galeria/' . (int) $item['id'])) ?>">
                                <?= csrf_field() ?>
                                <div class="field">
                                    <label for="gt-<?= (int) $item['id'] ?>">Titulo</label>
                                    <input id="gt-<?= (int) $item['id'] ?>" type="text" name="title"
                                           value="<?= e($item['title']) ?>" maxlength="160">
                                </div>
                                <div class="field">
                                    <label for="gc-<?= (int) $item['id'] ?>">Categoria</label>
                                    <select id="gc-<?= (int) $item['id'] ?>" name="category_id">
                                        <option value="0">Sin categoria</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= (int) $category['id'] ?>"
                                                    <?= (int) ($item['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>
                                                <?= e($category['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <label class="checkbox">
                                    <input type="checkbox" name="is_active" value="1" <?= (bool) $item['is_active'] ? 'checked' : '' ?>>
                                    <span>Visible</span>
                                </label>
                                <label class="checkbox">
                                    <input type="checkbox" name="is_featured" value="1" <?= (bool) $item['is_featured'] ? 'checked' : '' ?>>
                                    <span>Destacada</span>
                                </label>
                                <button type="submit" class="btn btn--primary btn--sm">Guardar</button>
                            </form>

                            <form method="post" class="mt-1" data-confirm="Eliminar esta foto?"
                                  action="<?= e(url('/panel/contenido/galeria/' . (int) $item['id'] . '/eliminar')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn--ghost btn--sm">Eliminar</button>
                            </form>
                        </details>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?= View::partial('partials.pagination', [
            'result' => $result, 'baseUrl' => url('/panel/contenido/galeria'), 'query' => [],
        ]) ?>
    </div>

    <div class="card">
        <h3>Subir una foto</h3>

        <form method="post" action="<?= e(url('/panel/contenido/galeria')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="field">
                <label for="image">Imagen *</label>
                <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp" required
                       data-preview="#pv-gallery">
                <img id="pv-gallery" class="hidden mt-1" alt="" style="max-height:150px;border-radius:9px">
            </div>

            <div class="field">
                <label for="before_image">Foto "antes" (opcional)</label>
                <input id="before_image" type="file" name="before_image" accept="image/jpeg,image/png,image/webp">
            </div>

            <div class="field">
                <label for="title">Titulo</label>
                <input id="title" type="text" name="title" maxlength="160" placeholder="Ej. Degradado con barba">
            </div>

            <div class="field">
                <label for="description">Descripcion</label>
                <input id="description" type="text" name="description" maxlength="500">
            </div>

            <div class="field">
                <label for="category_id">Categoria</label>
                <select id="category_id" name="category_id">
                    <option value="0">Sin categoria</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="staff_id">Realizado por</label>
                <select id="staff_id" name="staff_id">
                    <option value="0">Sin especificar</option>
                    <?php foreach ($staffList as $member): ?>
                        <option value="<?= (int) $member['id'] ?>"><?= e($member['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <label class="checkbox">
                <input type="checkbox" name="is_active" value="1" checked>
                <span>Publicar de inmediato</span>
            </label>
            <label class="checkbox">
                <input type="checkbox" name="is_featured" value="1">
                <span>Destacar en la portada</span>
            </label>

            <button type="submit" class="btn btn--primary btn--block">Subir foto</button>
        </form>
    </div>
</div>
<?php View::stop(); ?>
