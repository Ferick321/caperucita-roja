<?php
/**
 * @var array{policies:list<array<string,mixed>>,total_rows:int,total_files:int,bytes_freed:int} $retention
 * @var array{files:int,bytes:int,paths:list<string>} $orphans
 * @var string $orphanSize
 * @var array<string,int> $softDeleted
 */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Simulacion de limpieza<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/mantenimiento')) ?>">&larr; Mantenimiento</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="alert alert--info">
    <span>
        <strong>Esto es una simulacion.</strong> No se ha eliminado nada todavia. Revisa lo que
        se borraria y, si te parece bien, ejecuta la limpieza desde la pantalla anterior.
    </span>
</div>

<div class="grid grid--3 mb-3">
    <div class="stat stat--warning">
        <p class="stat__label">Filas que se borrarian</p>
        <p class="stat__value"><?= (int) $retention['total_rows'] ?></p>
    </div>
    <div class="stat stat--warning">
        <p class="stat__label">Archivos huerfanos</p>
        <p class="stat__value"><?= (int) $orphans['files'] ?></p>
        <p class="stat__meta"><?= e($orphanSize) ?></p>
    </div>
    <div class="stat">
        <p class="stat__label">Registros marcados como eliminados</p>
        <p class="stat__value"><?= (int) array_sum($softDeleted) ?></p>
    </div>
</div>

<div class="card card--flush">
    <div class="card__head" style="padding:20px 20px 0"><h2>Detalle por politica</h2></div>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Politica</th><th>Tabla</th><th>Retencion</th><th>Filas</th><th>Archivos</th></tr></thead>
            <tbody>
                <?php foreach ($retention['policies'] as $policy): ?>
                    <tr>
                        <td>
                            <strong><?= e($policy['label']) ?></strong>
                            <?php if ($policy['error'] !== null): ?>
                                <div class="text-small" style="color:var(--a-danger)"><?= e($policy['error']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="mono text-small"><?= e($policy['table']) ?></td>
                        <td><?= (int) $policy['retention_days'] ?> dias</td>
                        <td><?= (int) $policy['rows_deleted'] > 0
                            ? '<span class="pill pill--warning">' . (int) $policy['rows_deleted'] . '</span>'
                            : '0' ?></td>
                        <td><?= (int) $policy['files_deleted'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($orphans['paths'] !== []): ?>
    <div class="card">
        <h2>Archivos sin duenio (muestra)</h2>
        <p class="text-muted text-small">
            Son archivos en el disco que ya no referencia ningun registro. Se conservan los subidos
            en la ultima hora por si estan en uso.
        </p>
        <ul class="text-small mono">
            <?php foreach (array_slice($orphans['paths'], 0, 40) as $path): ?>
                <li><?= e($path) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php if (count($orphans['paths']) > 40): ?>
            <p class="text-muted text-small">... y <?= (int) (count($orphans['paths']) - 40) ?> mas.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<a class="btn btn--primary" href="<?= e(url('/panel/mantenimiento')) ?>">Volver y ejecutar la limpieza</a>
<?php View::stop(); ?>
