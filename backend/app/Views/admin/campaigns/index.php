<?php
/**
 * @var array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int} $result
 * @var array<string,int> $audienceSizes
 */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Campanas<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--primary btn--sm" href="<?= e(url('/panel/campanas/nueva')) ?>">+ Nueva campana</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="help-box">
    Envia promociones a tus clientes registrados. Solo se contacta a quienes dieron su
    consentimiento, y todo mensaje incluye un enlace de baja, como exige la normativa.
</div>

<div class="grid grid--4 mb-3">
    <?php foreach ([
        'all' => 'Todos los que aceptan',
        'new_clients' => 'Clientes nuevos',
        'inactive_clients' => 'Clientes inactivos',
        'frequent_clients' => 'Clientes frecuentes',
    ] as $key => $label): ?>
        <div class="stat">
            <p class="stat__label"><?= e($label) ?></p>
            <p class="stat__value"><?= (int) ($audienceSizes[$key] ?? 0) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<div class="card card--flush">
    <?php if ($result['data'] === []): ?>
        <div class="empty-state">
            <div class="empty-state__icon">&#9993;</div>
            <p>Aun no has creado campanas.</p>
            <a class="btn btn--primary" href="<?= e(url('/panel/campanas/nueva')) ?>">Crear la primera</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Campana</th><th>Canal</th><th>Publico</th><th>Estado</th>
                        <th>Enviados</th><th>Aperturas</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($result['data'] as $campaign): ?>
                        <tr>
                            <td>
                                <strong><?= e($campaign['name']) ?></strong>
                                <div class="text-small text-muted"><?= e(str_limit((string) $campaign['subject'], 60)) ?></div>
                            </td>
                            <td><span class="pill"><?= e($campaign['channel']) ?></span></td>
                            <td class="text-small"><?= e($campaign['audience']) ?></td>
                            <td>
                                <span class="pill pill--<?= e(match ((string) $campaign['status']) {
                                    'sent' => 'success', 'sending' => 'info',
                                    'scheduled' => 'warning', 'cancelled' => 'danger', default => '',
                                }) ?>"><?= e(match ((string) $campaign['status']) {
                                    'draft' => 'Borrador', 'scheduled' => 'Programada',
                                    'sending' => 'Enviando', 'sent' => 'Enviada',
                                    'cancelled' => 'Cancelada', default => $campaign['status'],
                                }) ?></span>
                            </td>
                            <td><?= (int) $campaign['total_sent'] ?> / <?= (int) $campaign['total_recipients'] ?></td>
                            <td><?= (int) $campaign['total_opened'] ?></td>
                            <td class="text-right">
                                <a class="btn btn--ghost btn--sm"
                                   href="<?= e(url('/panel/campanas/' . (int) $campaign['id'] . '/editar')) ?>">Abrir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?= View::partial('partials.pagination', [
    'result' => $result, 'baseUrl' => url('/panel/campanas'), 'query' => [],
]) ?>
<?php View::stop(); ?>
