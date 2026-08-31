<?php
/**
 * @var array{folders:array<string,list<array{path:string,bytes:int,human:string,modified:string,orphan:bool}>>,total_files:int,total_bytes:int,orphans:int} $inventory
 * @var string $totalHuman
 * @var string $folder
 */

use App\Core\View;

View::extend('layouts.admin');

$nombres = [
    'servicios' => 'Fotos de servicios',
    'comprobantes' => 'Comprobantes de pago',
    'galeria' => 'Galeria de trabajos',
    'personal' => 'Fotos del equipo',
    'publicidad' => 'Imagenes de publicidad',
    'contenido' => 'Imagenes de la web',
    'avatares' => 'Fotos de clientes',
    'sucursales' => 'Fotos de locales',
];
?>
<?php View::start('title'); ?>Archivos subidos<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/mantenimiento')) ?>">&larr; Mantenimiento</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="help-box">
    Todo lo que se ha subido al sistema: fotos, comprobantes e imagenes de la web.
    Los marcados como <strong>sin usar</strong> ya no aparecen en ninguna ficha, asi que
    puedes borrarlos para liberar espacio sin romper nada.
</div>

<div class="grid grid--3 mb-3">
    <div class="stat stat--primary">
        <p class="stat__label">Espacio en archivos</p>
        <p class="stat__value"><?= e($totalHuman) ?></p>
        <p class="stat__meta"><?= (int) $inventory['total_files'] ?> archivos</p>
    </div>
    <div class="stat stat--<?= $inventory['orphans'] > 0 ? 'warning' : 'success' ?>">
        <p class="stat__label">Sin usar</p>
        <p class="stat__value"><?= (int) $inventory['orphans'] ?></p>
        <p class="stat__meta">Se pueden borrar</p>
    </div>
    <div class="stat">
        <p class="stat__label">Carpetas</p>
        <p class="stat__value"><?= count($inventory['folders']) ?></p>
        <p class="stat__meta">Por tipo de contenido</p>
    </div>
</div>

<?php if ($inventory['folders'] === []): ?>
    <div class="card">
        <div class="empty-state"><p>Todavia no se ha subido ningun archivo.</p></div>
    </div>
<?php else: ?>
    <?php foreach ($inventory['folders'] as $nombre => $archivos): ?>
        <div class="card card--flush mb-3">
            <div class="card__head">
                <h2><?= e($nombres[$nombre] ?? ucfirst((string) $nombre)) ?></h2>
                <span class="pill"><?= count($archivos) ?> archivo(s)</span>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr><th>Archivo</th><th>Subido</th><th class="text-right">Tamano</th><th>Estado</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($archivos as $archivo): ?>
                            <tr>
                                <td class="text-small"><code><?= e(basename($archivo['path'])) ?></code></td>
                                <td class="text-small text-muted"><?= e($archivo['modified']) ?></td>
                                <td class="text-right"><?= e($archivo['human']) ?></td>
                                <td>
                                    <?php if ($archivo['orphan']): ?>
                                        <span class="pill pill--warning">Sin usar</span>
                                    <?php else: ?>
                                        <span class="pill pill--success">En uso</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a class="btn btn--ghost btn--sm" target="_blank" rel="noopener"
                                           href="<?= e(url('/media/' . $archivo['path'])) ?>">Ver</a>
                                        <form method="post" action="<?= e(url('/panel/mantenimiento/archivos/eliminar')) ?>"
                                              data-confirm="El archivo se borrara del servidor y no se puede recuperar. Continuar?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="archivo" value="<?= e($archivo['path']) ?>">
                                            <button type="submit" class="btn btn--danger btn--sm">Borrar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
<?php View::stop(); ?>
