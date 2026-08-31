<?php
/**
 * @var array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int} $result
 * @var array<string,mixed> $filters
 * @var array<string,int> $stats
 */

use App\Core\View;

View::extend('layouts.admin');

$etiquetas = [
    'waiting' => ['Esperando', 'pill--warning'],
    'notified' => ['Avisado', 'pill--success'],
    'converted' => ['Reservo', 'pill--success'],
    'expired' => ['Vencido', 'pill--danger'],
];
?>
<?php View::start('title'); ?>Lista de espera<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="help-box">
    Clientes que querian una hora que estaba ocupada y pidieron aviso si se libera.
    Cuando les llames o les escribas, marcalos como <strong>Avisado</strong>;
    si reservan, como <strong>Reservo</strong>.
</div>

<div class="grid grid--3 mb-3">
    <div class="stat stat--warning">
        <p class="stat__label">Esperando</p>
        <p class="stat__value"><?= (int) $stats['esperando'] ?></p>
        <p class="stat__meta">Sin avisar todavia</p>
    </div>
    <div class="stat">
        <p class="stat__label">Avisados</p>
        <p class="stat__value"><?= (int) $stats['avisados'] ?></p>
        <p class="stat__meta">Ya se les contacto</p>
    </div>
    <div class="stat stat--success">
        <p class="stat__label">Reservaron</p>
        <p class="stat__value"><?= (int) $stats['convertidos'] ?></p>
        <p class="stat__meta">Se convirtieron en cita</p>
    </div>
</div>

<form method="get" class="filters mb-3">
    <select name="estado">
        <option value="">Todos</option>
        <?php foreach ($etiquetas as $clave => [$texto, $_]): ?>
            <option value="<?= e($clave) ?>" <?= $filters['estado'] === $clave ? 'selected' : '' ?>><?= e($texto) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn--ghost btn--sm">Filtrar</button>
</form>

<div class="card card--flush">
    <?php if ($result['data'] === []): ?>
        <div class="empty-state"><p>No hay nadie en lista de espera.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Cliente</th><th>Servicio</th><th>Profesional</th>
                        <th>Cuando lo quiere</th><th>Estado</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['data'] as $fila): ?>
                        <?php [$texto, $clase] = $etiquetas[(string) $fila['status']] ?? ['?', '']; ?>
                        <tr>
                            <td>
                                <strong><?= e((string) $fila['client_name']) ?></strong>
                                <?php if ((string) $fila['client_phone'] !== ''): ?>
                                    <div class="text-small text-muted"><?= e((string) $fila['client_phone']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-small">
                                <?= $fila['service_name'] !== null ? e((string) $fila['service_name']) : '<span class="text-muted">Cualquiera</span>' ?>
                            </td>
                            <td class="text-small">
                                <?= $fila['staff_name'] !== null ? e((string) $fila['staff_name']) : '<span class="text-muted">Cualquiera</span>' ?>
                            </td>
                            <td class="text-small">
                                <?= e((string) $fila['desired_date']) ?>
                                <?php if ($fila['desired_from'] !== null): ?>
                                    <div class="text-muted">
                                        <?= e(mb_substr((string) $fila['desired_from'], 0, 5)) ?>
                                        a <?= e(mb_substr((string) $fila['desired_to'], 0, 5)) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><span class="pill <?= e($clase) ?>"><?= e($texto) ?></span></td>
                            <td>
                                <div class="actions">
                                    <form method="post"
                                          action="<?= e(url('/panel/espera/' . (int) $fila['id'])) ?>"
                                          class="flex gap-1">
                                        <?= csrf_field() ?>
                                        <select name="estado" class="btn--sm">
                                            <?php foreach ($etiquetas as $clave => [$t, $_]): ?>
                                                <option value="<?= e($clave) ?>"
                                                    <?= (string) $fila['status'] === $clave ? 'selected' : '' ?>>
                                                    <?= e($t) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn--ghost btn--sm">Guardar</button>
                                    </form>
                                    <form method="post"
                                          action="<?= e(url('/panel/espera/' . (int) $fila['id'] . '/eliminar')) ?>"
                                          data-confirm="Se borrara esta solicitud. Continuar?">
                                        <?= csrf_field() ?>
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

<?= View::partial('partials.pagination', [
    'result' => $result, 'baseUrl' => url('/panel/espera'), 'query' => $filters,
]) ?>
<?php View::stop(); ?>
