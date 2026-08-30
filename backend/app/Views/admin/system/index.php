<?php
/**
 * @var list<array<string,mixed>> $policies
 * @var list<array{table:string,rows:int,data_mb:float,index_mb:float,free_mb:float}> $databaseUsage
 * @var float $totalDataMb
 * @var float $totalFreeMb
 * @var array{files:int,bytes:int} $storage
 * @var string $storageHuman
 * @var list<array<string,mixed>> $runs
 * @var array<string,int> $queue
 * @var array<string,int> $softDeleted
 * @var array<string,mixed> $environment
 */

use App\Core\View;
use App\Services\MaintenanceService;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Mantenimiento<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/sistema/simular-limpieza')) ?>">Simular limpieza</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="help-box">
    Aqui liberas espacio de verdad: los datos marcados como eliminados se borran de la base,
    los archivos sin duenio desaparecen del disco y las tablas se compactan para devolver el
    espacio al servidor. Puedes simular antes de ejecutar.
</div>

<div class="grid grid--4 mb-3">
    <div class="stat stat--primary">
        <p class="stat__label">Base de datos</p>
        <p class="stat__value"><?= e((string) $totalDataMb) ?> MB</p>
        <p class="stat__meta"><?= e((string) $totalFreeMb) ?> MB recuperables</p>
    </div>
    <div class="stat">
        <p class="stat__label">Archivos subidos</p>
        <p class="stat__value"><?= e($storageHuman) ?></p>
        <p class="stat__meta"><?= (int) $storage['files'] ?> archivos</p>
    </div>
    <div class="stat stat--<?= (int) $queue['pending'] > 50 ? 'warning' : 'success' ?>">
        <p class="stat__label">Avisos en cola</p>
        <p class="stat__value"><?= (int) $queue['pending'] ?></p>
        <p class="stat__meta"><?= (int) $queue['failed'] ?> con error</p>
    </div>
    <div class="stat stat--<?= array_sum($softDeleted) > 0 ? 'warning' : 'success' ?>">
        <p class="stat__label">Registros por purgar</p>
        <p class="stat__value"><?= (int) array_sum($softDeleted) ?></p>
        <p class="stat__meta">Marcados como eliminados</p>
    </div>
</div>

<div class="grid grid--sidebar">
    <div>
        <div class="card">
            <h2>Ejecutar limpieza</h2>
            <p class="text-muted text-small">
                Selecciona que quieres limpiar. Esta accion elimina datos de forma definitiva.
            </p>

            <form method="post" action="<?= e(url('/panel/sistema/limpiar')) ?>"
                  data-confirm="Los datos se eliminaran de forma definitiva. Continuar?">
                <?= csrf_field() ?>

                <label class="checkbox">
                    <input type="checkbox" name="tasks[]" value="retencion" checked>
                    <span><strong>Aplicar politicas de retencion</strong>
                        <span class="text-muted text-small" style="display:block">
                            Borra registros antiguos segun los dias configurados abajo.</span></span>
                </label>

                <label class="checkbox">
                    <input type="checkbox" name="tasks[]" value="borrados" checked>
                    <span><strong>Purgar lo marcado como eliminado</strong>
                        <span class="text-muted text-small" style="display:block">
                            Citas, clientes, servicios y demas que ya se borraron desde el panel.</span></span>
                </label>

                <div class="field" style="margin-left:26px;max-width:200px">
                    <label for="soft_delete_days">Antiguedad minima (dias)</label>
                    <input id="soft_delete_days" type="number" name="soft_delete_days" min="0" max="3650" value="30">
                </div>

                <label class="checkbox">
                    <input type="checkbox" name="tasks[]" value="archivos" checked>
                    <span><strong>Eliminar archivos huerfanos</strong>
                        <span class="text-muted text-small" style="display:block">
                            Imagenes en el disco que ya no usa ningun registro.</span></span>
                </label>

                <label class="checkbox">
                    <input type="checkbox" name="tasks[]" value="optimizar">
                    <span><strong>Compactar las tablas</strong>
                        <span class="text-muted text-small" style="display:block">
                            Devuelve al servidor el espacio liberado. Puede tardar en bases grandes.</span></span>
                </label>

                <label class="checkbox">
                    <input type="checkbox" name="tasks[]" value="registros">
                    <span><strong>Borrar registros de aplicacion antiguos</strong></span>
                </label>

                <div class="field" style="margin-left:26px;max-width:200px">
                    <label for="log_days">Conservar registros (dias)</label>
                    <input id="log_days" type="number" name="log_days" min="1" max="365" value="30">
                </div>

                <div class="field">
                    <label for="confirm">Escribe LIMPIAR para confirmar</label>
                    <input id="confirm" type="text" name="confirm" placeholder="LIMPIAR" required style="max-width:220px">
                </div>

                <button type="submit" class="btn btn--danger">Ejecutar limpieza</button>
            </form>
        </div>

        <div class="card">
            <h2>Politicas de retencion</h2>
            <p class="text-muted text-small">
                Define cuanto tiempo se conserva cada tipo de dato antes de eliminarlo de forma definitiva.
            </p>

            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Dato</th><th>Tabla</th><th>Dias</th><th>Activa</th><th>Ultima ejecucion</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($policies as $policy): ?>
                            <tr>
                                <form method="post" action="<?= e(url('/panel/sistema/retencion/' . (int) $policy['id'])) ?>">
                                    <td>
                                        <?= csrf_field() ?>
                                        <strong><?= e($policy['label']) ?></strong>
                                        <div class="text-small text-muted"><?= e($policy['description']) ?></div>
                                    </td>
                                    <td class="mono text-small"><?= e($policy['target_table']) ?></td>
                                    <td>
                                        <input type="number" name="retention_days" min="1" max="7300"
                                               value="<?= (int) $policy['retention_days'] ?>" style="max-width:90px">
                                    </td>
                                    <td>
                                        <label class="checkbox" style="margin:0">
                                            <input type="checkbox" name="is_active" value="1"
                                                   <?= (bool) $policy['is_active'] ? 'checked' : '' ?>>
                                        </label>
                                    </td>
                                    <td class="text-small text-muted nowrap">
                                        <?= e($policy['last_run_at'] === null
                                            ? 'Nunca'
                                            : local_datetime((string) $policy['last_run_at'], 'd/m/Y H:i')) ?>
                                        <?php if ((int) $policy['last_deleted_count'] > 0): ?>
                                            <div><?= (int) $policy['last_deleted_count'] ?> borrados</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="submit" class="btn btn--ghost btn--sm">Guardar</button>
                                    </td>
                                </form>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card card--flush">
            <div class="card__head" style="padding:20px 20px 0">
                <h2>Espacio por tabla</h2>
                <span class="text-small text-muted">25 mayores</span>
            </div>

            <?php if ($databaseUsage === []): ?>
                <div class="empty-state"><p>Esta informacion solo esta disponible en MySQL/MariaDB.</p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Tabla</th><th>Filas</th><th>Datos (MB)</th><th>Indices (MB)</th><th>Libre (MB)</th></tr></thead>
                        <tbody>
                            <?php foreach ($databaseUsage as $row): ?>
                                <tr>
                                    <td class="mono text-small"><?= e($row['table']) ?></td>
                                    <td><?= e((string) $row['rows']) ?></td>
                                    <td><?= e((string) $row['data_mb']) ?></td>
                                    <td><?= e((string) $row['index_mb']) ?></td>
                                    <td><?= $row['free_mb'] > 0
                                        ? '<span class="pill pill--warning">' . e((string) $row['free_mb']) . '</span>'
                                        : e((string) $row['free_mb']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="card">
            <h3>Estado del entorno</h3>
            <div class="switch-row">
                <div class="switch-row__text"><strong>PHP</strong></div>
                <span class="mono text-small"><?= e((string) $environment['php']) ?></span>
            </div>
            <div class="switch-row">
                <div class="switch-row__text"><strong>Modo depuracion</strong></div>
                <span class="pill pill--<?= $environment['debug'] ? 'danger' : 'success' ?>">
                    <?= $environment['debug'] ? 'Encendido' : 'Apagado' ?>
                </span>
            </div>
            <div class="switch-row">
                <div class="switch-row__text"><strong>HTTPS forzado</strong></div>
                <span class="pill pill--<?= $environment['https'] ? 'success' : 'warning' ?>">
                    <?= $environment['https'] ? 'Si' : 'No' ?>
                </span>
            </div>
            <div class="switch-row">
                <div class="switch-row__text"><strong>Envio de correo</strong></div>
                <span class="pill pill--<?= $environment['mail'] === 'log' ? 'warning' : 'success' ?>">
                    <?= e((string) $environment['mail']) ?>
                </span>
            </div>
            <div class="switch-row">
                <div class="switch-row__text"><strong>Limpieza automatica</strong></div>
                <span class="pill pill--<?= $environment['auto_purge'] ? 'success' : 'warning' ?>">
                    <?= $environment['auto_purge'] ? 'Activa' : 'Desactivada' ?>
                </span>
            </div>
        </div>

        <div class="card">
            <h3>Cola de avisos</h3>
            <div class="switch-row">
                <div class="switch-row__text"><strong>Pendientes</strong></div>
                <strong><?= (int) $queue['pending'] ?></strong>
            </div>
            <div class="switch-row">
                <div class="switch-row__text"><strong>Enviados</strong></div>
                <strong><?= (int) $queue['sent'] ?></strong>
            </div>
            <div class="switch-row">
                <div class="switch-row__text"><strong>Con error</strong></div>
                <strong><?= (int) $queue['failed'] ?></strong>
            </div>

            <form method="post" action="<?= e(url('/panel/sistema/cola')) ?>" class="mt-2">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--primary btn--sm btn--block">Procesar ahora</button>
            </form>

            <?php if ((int) $queue['failed'] > 0): ?>
                <form method="post" action="<?= e(url('/panel/sistema/cola/reintentar')) ?>" class="mt-1">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--ghost btn--sm btn--block">Reintentar los fallidos</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($softDeleted !== []): ?>
            <div class="card">
                <h3>Marcados como eliminados</h3>
                <?php foreach ($softDeleted as $label => $count): ?>
                    <div class="switch-row">
                        <div class="switch-row__text"><strong><?= e($label) ?></strong></div>
                        <span class="pill pill--warning"><?= (int) $count ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($runs !== []): ?>
            <div class="card">
                <h3>Ultimas limpiezas</h3>
                <?php foreach ($runs as $run): ?>
                    <div class="switch-row">
                        <div class="switch-row__text">
                            <strong><?= e($run['task']) ?></strong>
                            <span>
                                <?= e(local_datetime((string) $run['created_at'], 'd/m/Y H:i')) ?>
                                &middot; <?= (int) $run['rows_affected'] ?> filas
                                <?php if ((int) $run['files_removed'] > 0): ?>
                                    &middot; <?= (int) $run['files_removed'] ?> archivos
                                <?php endif; ?>
                                <?php if ((int) $run['bytes_freed'] > 0): ?>
                                    &middot; <?= e(MaintenanceService::formatBytes((int) $run['bytes_freed'])) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php View::stop(); ?>
