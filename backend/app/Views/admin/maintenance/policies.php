<?php
/**
 * @var list<array<string,mixed>> $policies
 * @var list<string> $tables
 */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Limpieza automatica<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--primary btn--sm" href="<?= e(url('/panel/mantenimiento/retencion/nueva')) ?>">Nueva regla</a>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/mantenimiento')) ?>">&larr; Mantenimiento</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="help-box">
    Cada regla dice cuanto tiempo se guarda un tipo de dato antes de borrarse solo.
    Por ejemplo: "los intentos de acceso se guardan 90 dias". Asi el sistema se limpia
    solo y no crece sin control. Puedes crear las tuyas, cambiarlas o apagarlas.
</div>

<div class="card card--flush">
    <?php if ($policies === []): ?>
        <div class="empty-state"><p>No hay reglas de limpieza. Nada se borrara automaticamente.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Regla</th>
                        <th>Datos</th>
                        <th class="text-right">Se guardan</th>
                        <th>Ultima vez</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($policies as $regla): ?>
                        <tr>
                            <td>
                                <strong><?= e((string) $regla['label']) ?></strong>
                                <?php if ((string) $regla['description'] !== ''): ?>
                                    <div class="text-small text-muted mt-1">
                                        <?= e(str_limit((string) $regla['description'], 80)) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-small"><code><?= e((string) $regla['target_table']) ?></code></td>
                            <td class="text-right"><?= (int) $regla['retention_days'] ?> dias</td>
                            <td class="text-small text-muted">
                                <?php if ($regla['last_run_at'] !== null): ?>
                                    <?= e(local_datetime((string) $regla['last_run_at'])) ?>
                                    <div><?= (int) $regla['last_deleted_count'] ?> borrados</div>
                                <?php else: ?>
                                    Nunca
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((bool) $regla['is_active']): ?>
                                    <span class="pill pill--success">Activa</span>
                                <?php else: ?>
                                    <span class="pill pill--danger">Apagada</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <a class="btn btn--ghost btn--sm"
                                       href="<?= e(url('/panel/mantenimiento/retencion/' . (int) $regla['id'] . '/editar')) ?>">
                                        Editar
                                    </a>
                                    <form method="post"
                                          action="<?= e(url('/panel/mantenimiento/retencion/' . (int) $regla['id'] . '/eliminar')) ?>"
                                          data-confirm="Se elimina la regla. Esos datos dejaran de limpiarse solos. Continuar?">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn--danger btn--sm">Eliminar</button>
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
