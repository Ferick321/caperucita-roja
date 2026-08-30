<?php
/**
 * @var array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int} $result
 * @var bool $onlyFailed
 */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Historial de accesos<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/sistema/auditoria')) ?>">&larr; Auditoria</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="tabs">
    <a class="tab <?= $onlyFailed ? '' : 'is-active' ?>" href="<?= e(url('/panel/sistema/accesos')) ?>">Todos</a>
    <a class="tab <?= $onlyFailed ? 'is-active' : '' ?>"
       href="<?= e(url('/panel/sistema/accesos?solo=fallidos')) ?>">Solo fallidos</a>
</div>

<div class="card card--flush">
    <?php if ($result['data'] === []): ?>
        <div class="empty-state"><p>Sin intentos registrados.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Fecha</th><th>Correo</th><th>Resultado</th><th>Motivo</th><th>IP</th><th>Navegador</th></tr></thead>
                <tbody>
                    <?php foreach ($result['data'] as $attempt): ?>
                        <tr>
                            <td class="nowrap text-small"><?= e(local_datetime((string) $attempt['created_at'])) ?></td>
                            <td class="text-small"><?= e($attempt['email']) ?></td>
                            <td>
                                <?php if ((bool) $attempt['successful']): ?>
                                    <span class="pill pill--success">Correcto</span>
                                <?php else: ?>
                                    <span class="pill pill--danger">Fallido</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-small text-muted"><?= e($attempt['failure_reason']) ?></td>
                            <td class="mono text-small"><?= e($attempt['ip_address']) ?></td>
                            <td class="text-small text-muted"><?= e(str_limit((string) $attempt['user_agent'], 50)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?= View::partial('partials.pagination', [
    'result' => $result, 'baseUrl' => url('/panel/sistema/accesos'),
    'query' => ['solo' => $onlyFailed ? 'fallidos' : ''],
]) ?>
<?php View::stop(); ?>
