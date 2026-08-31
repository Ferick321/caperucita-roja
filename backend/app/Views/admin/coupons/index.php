<?php
/**
 * @var array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int} $result
 * @var array<string,mixed> $filters
 */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Cupones<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--primary btn--sm" href="<?= e(url('/panel/cupones/nuevo')) ?>">Nuevo cupon</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="help-box">
    Codigos de descuento que tus clientes escriben al reservar.
    Puedes limitarlos por fecha, por numero de usos, a un solo servicio
    o dejarlos solo para quien viene por primera vez.
</div>

<form method="get" class="filters mb-3">
    <input type="search" name="q" placeholder="Buscar por codigo" value="<?= e((string) $filters['q']) ?>">
    <select name="estado">
        <option value="">Todos</option>
        <option value="activos" <?= $filters['estado'] === 'activos' ? 'selected' : '' ?>>Activos</option>
        <option value="apagados" <?= $filters['estado'] === 'apagados' ? 'selected' : '' ?>>Apagados</option>
    </select>
    <button type="submit" class="btn btn--ghost btn--sm">Filtrar</button>
</form>

<div class="card card--flush">
    <?php if ($result['data'] === []): ?>
        <div class="empty-state">
            <p>No hay cupones todavia.</p>
            <a class="btn btn--primary" href="<?= e(url('/panel/cupones/nuevo')) ?>">Crear el primero</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Codigo</th><th>Descuento</th><th>Aplica a</th>
                        <th class="text-right">Usos</th><th>Vigencia</th><th>Estado</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['data'] as $cupon): ?>
                        <tr>
                            <td>
                                <strong><code><?= e((string) $cupon['code']) ?></code></strong>
                                <?php if ((string) $cupon['description'] !== ''): ?>
                                    <div class="text-small text-muted mt-1">
                                        <?= e(str_limit((string) $cupon['description'], 60)) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((string) $cupon['discount_type'] === 'percent'): ?>
                                    <span class="pill pill--success"><?= e((string) (float) $cupon['discount_value']) ?>%</span>
                                <?php else: ?>
                                    <span class="pill pill--success"><?= e(money((float) $cupon['discount_value'])) ?></span>
                                <?php endif; ?>
                                <?php if ((float) $cupon['min_amount'] > 0): ?>
                                    <div class="text-small text-muted mt-1">
                                        desde <?= e(money((float) $cupon['min_amount'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-small">
                                <?php if ($cupon['service_name'] !== null): ?>
                                    <?= e((string) $cupon['service_name']) ?>
                                <?php else: ?>
                                    <span class="text-muted">Todos los servicios</span>
                                <?php endif; ?>
                                <?php if ((bool) $cupon['first_visit_only']): ?>
                                    <div><span class="pill">Solo primera visita</span></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?= (int) $cupon['times_used'] ?><?php
                                    if ((int) $cupon['usage_limit'] > 0) {
                                        echo ' / ' . (int) $cupon['usage_limit'];
                                    }
                                ?>
                            </td>
                            <td class="text-small text-muted">
                                <?php if ($cupon['starts_at'] !== null || $cupon['ends_at'] !== null): ?>
                                    <?= $cupon['starts_at'] !== null ? e(local_datetime((string) $cupon['starts_at'], 'd/m/Y')) : '...' ?>
                                    &rarr;
                                    <?= $cupon['ends_at'] !== null ? e(local_datetime((string) $cupon['ends_at'], 'd/m/Y')) : '...' ?>
                                <?php else: ?>
                                    Sin limite de fecha
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((bool) $cupon['is_active']): ?>
                                    <span class="pill pill--success">Activo</span>
                                <?php else: ?>
                                    <span class="pill pill--danger">Apagado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <a class="btn btn--ghost btn--sm"
                                       href="<?= e(url('/panel/cupones/' . (int) $cupon['id'] . '/editar')) ?>">Editar</a>
                                    <form method="post" action="<?= e(url('/panel/cupones/' . (int) $cupon['id'] . '/activar')) ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn--ghost btn--sm">
                                            <?= (bool) $cupon['is_active'] ? 'Apagar' : 'Activar' ?>
                                        </button>
                                    </form>
                                    <form method="post" action="<?= e(url('/panel/cupones/' . (int) $cupon['id'] . '/eliminar')) ?>"
                                          data-confirm="El cupon dejara de poder canjearse. Continuar?">
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

<?= View::partial('partials.pagination', [
    'result' => $result, 'baseUrl' => url('/panel/cupones'), 'query' => $filters,
]) ?>
<?php View::stop(); ?>
