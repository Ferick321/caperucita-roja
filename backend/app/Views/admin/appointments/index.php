<?php
/**
 * @var array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int} $result
 * @var list<array<string,mixed>> $staffList
 * @var array<string,mixed> $filters
 */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Citas<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/citas/agenda')) ?>">Vista de agenda</a>
    <a class="btn btn--primary btn--sm" href="<?= e(url('/panel/citas/nueva')) ?>">+ Nueva cita</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<form method="get" action="<?= e(url('/panel/citas')) ?>" class="card">
    <div class="filters">
        <div class="field">
            <label for="f-q">Buscar</label>
            <input id="f-q" type="search" name="q" value="<?= e((string) $filters['q']) ?>"
                   placeholder="Codigo, nombre o telefono">
        </div>

        <div class="field">
            <label for="f-estado">Estado</label>
            <select id="f-estado" name="estado" data-auto-submit>
                <option value="">Todos</option>
                <?php foreach ([
                    'pending' => 'Pendientes', 'confirmed' => 'Confirmadas', 'in_progress' => 'En curso',
                    'completed' => 'Completadas', 'cancelled' => 'Canceladas', 'no_show' => 'No asistieron',
                ] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $filters['estado'] === $value ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="f-prof">Profesional</label>
            <select id="f-prof" name="profesional" data-auto-submit>
                <option value="">Todos</option>
                <?php foreach ($staffList as $member): ?>
                    <option value="<?= (int) $member['id'] ?>"
                            <?= (int) $filters['profesional'] === (int) $member['id'] ? 'selected' : '' ?>>
                        <?= e($member['display_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="f-desde">Desde</label>
            <input id="f-desde" type="date" name="desde" value="<?= e((string) $filters['desde']) ?>">
        </div>

        <div class="field">
            <label for="f-hasta">Hasta</label>
            <input id="f-hasta" type="date" name="hasta" value="<?= e((string) $filters['hasta']) ?>">
        </div>

        <button type="submit" class="btn btn--primary">Filtrar</button>
        <a class="btn btn--ghost" href="<?= e(url('/panel/citas')) ?>">Limpiar</a>
    </div>
</form>

<div class="card card--flush">
    <?php if ($result['data'] === []): ?>
        <div class="empty-state">
            <div class="empty-state__icon">&#128197;</div>
            <p>No hay citas con esos criterios.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Codigo</th><th>Fecha y hora</th><th>Cliente</th><th>Servicios</th>
                        <th>Profesional</th><th>Estado</th><th>Pago</th>
                        <th class="text-right">Total</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['data'] as $appointment): ?>
                        <tr>
                            <td class="mono nowrap"><?= e($appointment['code']) ?></td>
                            <td class="nowrap"><?= e(local_datetime((string) $appointment['starts_at'])) ?></td>
                            <td>
                                <strong><?= e($appointment['client_name']) ?></strong>
                                <?php if ((string) $appointment['client_phone'] !== ''): ?>
                                    <div class="text-small text-muted"><?= e($appointment['client_phone']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-small">
                                <?php if (!empty($appointment['services'])): ?>
                                    <?= e(implode(', ', array_map(
                                        static fn (array $s): string => (string) $s['service_name'],
                                        $appointment['services']
                                    ))) ?>
                                <?php endif; ?>
                                <?php if ((string) $appointment['custom_request'] !== ''): ?>
                                    <div class="pill pill--info mt-1"><?= e(str_limit((string) $appointment['custom_request'], 40)) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?= e($appointment['staff_name'] ?? 'Sin asignar') ?></td>
                            <td><?= View::partial('partials.status_pill', ['status' => (string) $appointment['status']]) ?></td>
                            <td><?= View::partial('partials.payment_pill', ['status' => (string) $appointment['payment_status']]) ?></td>
                            <td class="text-right nowrap"><?= e(money((float) $appointment['total'])) ?></td>
                            <td class="text-right">
                                <a class="btn btn--ghost btn--sm"
                                   href="<?= e(url('/panel/citas/' . (int) $appointment['id'])) ?>">Abrir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?= View::partial('partials.pagination', [
    'result' => $result,
    'baseUrl' => url('/panel/citas'),
    'query' => $filters,
]) ?>
<?php View::stop(); ?>
