<?php
/**
 * @var list<array{table:string,rows:int,size_mb:float,free_mb:float,emptyable:bool,label:string}> $inventory
 * @var float $totalSizeMb
 * @var float $totalFreeMb
 */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Tablas de la base de datos<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/mantenimiento')) ?>">&larr; Mantenimiento</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="help-box">
    Aqui ves cuanto ocupa cada parte del sistema. <strong>Compactar</strong> devuelve al servidor
    el espacio que quedo libre despues de borrar, sin tocar tus datos.
    <strong>Vaciar</strong> borra todo el contenido de esa tabla y no tiene vuelta atras.
</div>

<div class="grid grid--3 mb-3">
    <div class="stat stat--primary">
        <p class="stat__label">Espacio ocupado</p>
        <p class="stat__value"><?= e((string) $totalSizeMb) ?> MB</p>
        <p class="stat__meta"><?= count($inventory) ?> tablas</p>
    </div>
    <div class="stat stat--<?= $totalFreeMb > 5 ? 'warning' : 'success' ?>">
        <p class="stat__label">Recuperable</p>
        <p class="stat__value"><?= e((string) $totalFreeMb) ?> MB</p>
        <p class="stat__meta">Se libera al compactar</p>
    </div>
    <div class="stat">
        <p class="stat__label">Se pueden vaciar</p>
        <p class="stat__value"><?= count(array_filter($inventory, static fn (array $r): bool => $r['emptyable'])) ?></p>
        <p class="stat__meta">Solo datos de registro</p>
    </div>
</div>

<form method="post" action="<?= e(url('/panel/mantenimiento/tablas/optimizar-todo')) ?>" class="mb-3"
      data-confirm="Se van a compactar todas las tablas. Puede tardar un momento. Continuar?">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn--primary">Compactar todas las tablas</button>
    <span class="text-small text-muted">Recupera los <?= e((string) $totalFreeMb) ?> MB libres. No borra nada.</span>
</form>

<div class="card card--flush">
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Tabla</th>
                    <th>Que guarda</th>
                    <th class="text-right">Filas</th>
                    <th class="text-right">Tamano</th>
                    <th class="text-right">Libre</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inventory as $row): ?>
                    <tr>
                        <td><code><?= e($row['table']) ?></code></td>
                        <td class="text-small text-muted">
                            <?= $row['label'] !== '' ? e($row['label']) : '<span class="text-muted">Datos del sistema</span>' ?>
                        </td>
                        <td class="text-right"><?= number_format($row['rows']) ?></td>
                        <td class="text-right"><?= e((string) $row['size_mb']) ?> MB</td>
                        <td class="text-right">
                            <?php if ($row['free_mb'] > 0): ?>
                                <span class="pill pill--warning"><?= e((string) $row['free_mb']) ?> MB</span>
                            <?php else: ?>
                                <span class="text-muted">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <form method="post" action="<?= e(url('/panel/mantenimiento/tablas/optimizar')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="tabla" value="<?= e($row['table']) ?>">
                                    <button type="submit" class="btn btn--ghost btn--sm">Compactar</button>
                                </form>

                                <?php if ($row['emptyable'] && $row['rows'] > 0): ?>
                                    <details class="collapsible">
                                        <summary class="btn btn--danger btn--sm">Vaciar</summary>
                                        <form method="post" class="mt-2"
                                              action="<?= e(url('/panel/mantenimiento/tablas/vaciar')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="tabla" value="<?= e($row['table']) ?>">
                                            <p class="text-small">
                                                Se borraran <strong><?= number_format($row['rows']) ?></strong>
                                                fila(s) de forma definitiva.
                                            </p>
                                            <div class="field">
                                                <label for="cf-<?= e($row['table']) ?>">
                                                    Escribe <code><?= e($row['table']) ?></code> para confirmar
                                                </label>
                                                <input type="text" id="cf-<?= e($row['table']) ?>" name="confirm"
                                                       autocomplete="off" required>
                                            </div>
                                            <button type="submit" class="btn btn--danger btn--sm">
                                                Vaciar definitivamente
                                            </button>
                                        </form>
                                    </details>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php View::stop(); ?>
