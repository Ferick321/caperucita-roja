<?php
/** @var list<array{name:string,bytes:int,human:string,created:string}> $backups */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Copias de seguridad<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/mantenimiento')) ?>">&larr; Mantenimiento</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="help-box">
    Antes de vaciar o limpiar cualquier cosa, crea una copia. Es un archivo con toda tu
    informacion que puedes volver a importar si algo sale mal.
    <strong>Descargala y guardala en tu computadora</strong>: si el servidor falla, las copias
    que esten dentro de el se pierden igual.
</div>

<div class="card mb-3">
    <h2>Crear una copia ahora</h2>
    <form method="post" action="<?= e(url('/panel/mantenimiento/copias')) ?>">
        <?= csrf_field() ?>
        <label class="checkbox">
            <input type="checkbox" name="solo_estructura" value="1">
            <span>Solo la estructura, sin los datos <span class="text-muted">(para montar un sistema vacio)</span></span>
        </label>
        <button type="submit" class="btn btn--primary mt-2">Crear copia</button>
    </form>
</div>

<div class="card card--flush">
    <div class="card__head"><h2>Copias guardadas</h2></div>
    <?php if ($backups === []): ?>
        <div class="empty-state"><p>Aun no has creado ninguna copia.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Archivo</th><th>Creada</th><th class="text-right">Tamano</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($backups as $copia): ?>
                        <tr>
                            <td><code><?= e($copia['name']) ?></code></td>
                            <td class="text-small text-muted"><?= e($copia['created']) ?> UTC</td>
                            <td class="text-right"><?= e($copia['human']) ?></td>
                            <td>
                                <div class="actions">
                                    <a class="btn btn--primary btn--sm"
                                       href="<?= e(url('/panel/mantenimiento/copias/descargar?archivo=' . urlencode($copia['name']))) ?>">
                                        Descargar
                                    </a>
                                    <form method="post" action="<?= e(url('/panel/mantenimiento/copias/eliminar')) ?>"
                                          data-confirm="Se borrara esta copia del servidor. Continuar?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="archivo" value="<?= e($copia['name']) ?>">
                                        <button type="submit" class="btn btn--danger btn--sm">Borrar</button>
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
<?php View::stop(); ?>
