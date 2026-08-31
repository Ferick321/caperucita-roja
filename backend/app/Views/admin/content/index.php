<?php
/** @var list<array<string,mixed>> $blocks */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Pagina web<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">Ver la web &rarr;</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="help-box">
    Cada bloque es una seccion de tu pagina de inicio. Cambia los textos, las imagenes y los
    botones; puedes desactivar las secciones que no quieras mostrar.
</div>

<?php foreach ($blocks as $block): ?>
    <div class="card">
        <details class="collapsible" <?= (string) $block['block_key'] === 'hero' ? 'open' : '' ?>>
            <summary>
                <span>
                    <strong><?= e($block['title'] !== '' ? $block['title'] : $block['block_key']) ?></strong>
                    <span class="text-muted text-small">&middot; <?= e($block['block_key']) ?></span>
                </span>
                <?php if (!(bool) $block['is_active']): ?>
                    <span class="pill pill--danger">Oculta</span>
                <?php endif; ?>
            </summary>

            <form method="post" action="<?= e(url('/panel/contenido/' . (int) $block['id'])) ?>"
                  enctype="multipart/form-data" class="mt-2">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="t-<?= (int) $block['id'] ?>">Titulo</label>
                    <input id="t-<?= (int) $block['id'] ?>" type="text" name="title" maxlength="200"
                           value="<?= e($block['title']) ?>">
                </div>

                <div class="field">
                    <label for="s-<?= (int) $block['id'] ?>">Subtitulo</label>
                    <input id="s-<?= (int) $block['id'] ?>" type="text" name="subtitle" maxlength="300"
                           value="<?= e($block['subtitle']) ?>">
                </div>

                <div class="field">
                    <label for="b-<?= (int) $block['id'] ?>">Texto</label>
                    <textarea id="b-<?= (int) $block['id'] ?>" name="body" rows="4" maxlength="8000"><?= e($block['body'] ?? '') ?></textarea>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="cl-<?= (int) $block['id'] ?>">Boton principal</label>
                        <input id="cl-<?= (int) $block['id'] ?>" type="text" name="cta_label" maxlength="80"
                               value="<?= e($block['cta_label']) ?>">
                    </div>
                    <div class="field">
                        <label for="cu-<?= (int) $block['id'] ?>">Enlace del boton</label>
                        <input id="cu-<?= (int) $block['id'] ?>" type="text" name="cta_url" maxlength="500"
                               value="<?= e($block['cta_url']) ?>" placeholder="/agendar">
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="c2l-<?= (int) $block['id'] ?>">Boton secundario</label>
                        <input id="c2l-<?= (int) $block['id'] ?>" type="text" name="cta_secondary_label" maxlength="80"
                               value="<?= e($block['cta_secondary_label']) ?>">
                    </div>
                    <div class="field">
                        <label for="c2u-<?= (int) $block['id'] ?>">Enlace secundario</label>
                        <input id="c2u-<?= (int) $block['id'] ?>" type="text" name="cta_secondary_url" maxlength="500"
                               value="<?= e($block['cta_secondary_url']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="i-<?= (int) $block['id'] ?>">Imagen</label>
                        <?php if ((string) $block['image_path'] !== ''): ?>
                            <img src="<?= e(media_url((string) $block['image_path'])) ?>" alt=""
                                 style="max-height:90px;border-radius:8px;margin-bottom:8px">
                        <?php endif; ?>
                        <input id="i-<?= (int) $block['id'] ?>" type="file" name="image"
                               accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div class="field">
                        <label for="bg-<?= (int) $block['id'] ?>">Imagen de fondo</label>
                        <?php if ((string) $block['background_path'] !== ''): ?>
                            <img src="<?= e(media_url((string) $block['background_path'])) ?>" alt=""
                                 style="max-height:90px;border-radius:8px;margin-bottom:8px">
                        <?php endif; ?>
                        <input id="bg-<?= (int) $block['id'] ?>" type="file" name="background"
                               accept="image/jpeg,image/png,image/webp">
                    </div>
                </div>

                <div class="flex gap-2 items-center flex-wrap">
                    <label class="checkbox" style="margin:0">
                        <input type="checkbox" name="is_active" value="1" <?= (bool) $block['is_active'] ? 'checked' : '' ?>>
                        <span>Mostrar esta seccion</span>
                    </label>

                    <div class="field" style="margin:0;max-width:120px">
                        <label for="o-<?= (int) $block['id'] ?>">Orden</label>
                        <input id="o-<?= (int) $block['id'] ?>" type="number" name="sort_order" min="0"
                               value="<?= (int) $block['sort_order'] ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn--primary btn--sm mt-2">Guardar seccion</button>
            </form>

            <form method="post" class="mt-2"
                  action="<?= e(url('/panel/contenido/' . (int) $block['id'] . '/eliminar')) ?>"
                  data-confirm="Se quitara esta seccion de la web. Continuar?">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--danger btn--sm">Eliminar seccion</button>
            </form>
        </details>
    </div>
<?php endforeach; ?>

<div class="card mt-3">
    <details class="collapsible">
        <summary class="btn btn--primary btn--sm">Anadir una seccion nueva</summary>

        <p class="text-muted text-small mt-2">
            Un bloque de texto libre para contar algo mas en tu pagina: promociones,
            horarios especiales, formas de llegar o lo que necesites.
        </p>

        <form method="post" enctype="multipart/form-data" class="mt-2"
              action="<?= e(url('/panel/contenido')) ?>">
            <?= csrf_field() ?>

            <div class="field">
                <label for="nueva-seccion-titulo">Titulo</label>
                <input id="nueva-seccion-titulo" type="text" name="title" required maxlength="200"
                       placeholder="Ej: Promocion de temporada">
            </div>

            <div class="field">
                <label for="nueva-seccion-subtitulo">Subtitulo</label>
                <input id="nueva-seccion-subtitulo" type="text" name="subtitle" maxlength="300">
            </div>

            <div class="field">
                <label for="nueva-seccion-texto">Texto</label>
                <textarea id="nueva-seccion-texto" name="body" rows="4" maxlength="8000"></textarea>
            </div>

            <div class="grid grid--2">
                <div class="field">
                    <label for="nueva-seccion-boton">Texto del boton</label>
                    <input id="nueva-seccion-boton" type="text" name="cta_label" maxlength="80">
                </div>
                <div class="field">
                    <label for="nueva-seccion-enlace">Enlace del boton</label>
                    <input id="nueva-seccion-enlace" type="text" name="cta_url" maxlength="500"
                           placeholder="https://...">
                </div>
            </div>

            <div class="field">
                <label for="nueva-seccion-imagen">Imagen</label>
                <input id="nueva-seccion-imagen" type="file" name="image"
                       accept="image/jpeg,image/png,image/webp">
            </div>

            <label class="checkbox">
                <input type="checkbox" name="is_active" value="1" checked>
                <span>Mostrar esta seccion</span>
            </label>

            <button type="submit" class="btn btn--primary mt-2">Crear seccion</button>
        </form>
    </details>
</div>
<?php View::stop(); ?>
