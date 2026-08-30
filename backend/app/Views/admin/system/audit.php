<?php
/**
 * @var array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int} $result
 * @var array<string,mixed> $filters
 * @var list<string> $entities
 */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Auditoria<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/sistema/accesos')) ?>">Historial de accesos</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="help-box">
    Registro de todas las acciones sensibles del panel: quien hizo que, cuando y desde que
    direccion. Es la herramienta para investigar cualquier incidente.
</div>

<form method="get" action="<?= e(url('/panel/sistema/auditoria')) ?>" class="card">
    <div class="filters">
        <div class="field">
            <label for="q">Buscar</label>
            <input id="q" type="search" name="q" value="<?= e((string) $filters['q']) ?>"
                   placeholder="Accion o usuario">
        </div>
        <div class="field">
            <label for="entidad">Entidad</label>
            <select id="entidad" name="entidad" data-auto-submit>
                <option value="">Todas</option>
                <?php foreach ($entities as $entity): ?>
                    <option value="<?= e($entity) ?>" <?= $filters['entidad'] === $entity ? 'selected' : '' ?>>
                        <?= e($entity) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn--primary">Filtrar</button>
    </div>
</form>

<div class="card card--flush">
    <?php if ($result['data'] === []): ?>
        <div class="empty-state"><p>Sin registros.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Fecha</th><th>Usuario</th><th>Accion</th><th>Entidad</th><th>Cambios</th><th>IP</th></tr></thead>
                <tbody>
                    <?php foreach ($result['data'] as $entry): ?>
                        <tr>
                            <td class="nowrap text-small"><?= e(local_datetime((string) $entry['created_at'])) ?></td>
                            <td class="text-small">
                                <?php if (($entry['email'] ?? null) !== null): ?>
                                    <?= e($entry['first_name'] . ' ' . $entry['last_name']) ?>
                                    <div class="text-muted"><?= e($entry['email']) ?></div>
                                <?php else: ?>
                                    <span class="text-muted">Sistema</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="pill"><?= e($entry['action']) ?></span></td>
                            <td class="text-small text-muted">
                                <?= e($entry['entity_type']) ?>
                                <?php if ($entry['entity_id'] !== null): ?>
                                    #<?= (int) $entry['entity_id'] ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-small">
                                <?php if (($entry['changes_after'] ?? null) !== null): ?>
                                    <details>
                                        <summary class="text-muted">Ver</summary>
                                        <pre class="mono" style="white-space:pre-wrap;font-size:.72rem;margin:6px 0 0"><?= e(
                                            (string) $entry['changes_before'] !== ''
                                                ? "antes: " . $entry['changes_before'] . "\n"
                                                : ''
                                        ) ?><?= e('despues: ' . $entry['changes_after']) ?></pre>
                                    </details>
                                <?php endif; ?>
                            </td>
                            <td class="mono text-small text-muted"><?= e($entry['ip_address']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?= View::partial('partials.pagination', [
    'result' => $result, 'baseUrl' => url('/panel/sistema/auditoria'), 'query' => $filters,
]) ?>
<?php View::stop(); ?>
