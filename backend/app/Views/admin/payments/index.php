<?php
/**
 * @var array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int} $result
 * @var string $status
 * @var string $search
 * @var array<string,int> $counts
 */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Pagos<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/pagos/cuentas')) ?>">Cuentas bancarias</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="help-box">
    Aqui verificas los comprobantes que suben tus clientes al pagar por transferencia.
    Al aprobar uno, la cita se confirma automaticamente y el cliente recibe el aviso.
</div>

<div class="tabs">
    <?php foreach ([
        'awaiting_verification' => 'Por verificar (' . (int) $counts['awaiting_verification'] . ')',
        'approved' => 'Aprobados (' . (int) $counts['approved'] . ')',
        'rejected' => 'Rechazados (' . (int) $counts['rejected'] . ')',
        'todos' => 'Todos',
    ] as $key => $label): ?>
        <a class="tab <?= $status === $key ? 'is-active' : '' ?>"
           href="<?= e(url('/panel/pagos?estado=' . $key)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<form method="get" action="<?= e(url('/panel/pagos')) ?>" class="card">
    <input type="hidden" name="estado" value="<?= e($status) ?>">
    <div class="filters">
        <div class="field">
            <label for="q">Buscar</label>
            <input id="q" type="search" name="q" value="<?= e($search) ?>"
                   placeholder="Codigo de cita, cliente o referencia">
        </div>
        <button type="submit" class="btn btn--primary">Buscar</button>
    </div>
</form>

<?php if ($result['data'] === []): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state__icon">&#128179;</div>
            <p>No hay pagos en este estado.</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($result['data'] as $payment): ?>
        <div class="card">
            <div class="card__head">
                <div>
                    <h3 style="margin-bottom:2px">
                        <?= e(money((float) $payment['amount'])) ?>
                        <span class="text-muted text-small">&middot; <?= e($payment['method_name'] ?? $payment['method_code']) ?></span>
                    </h3>
                    <p class="text-small text-muted mb-0">
                        <?= e($payment['client_name'] ?? 'Cliente') ?>
                        <?php if (($payment['appointment_code'] ?? null) !== null): ?>
                            &middot; cita <a href="<?= e(url('/panel/citas/' . (int) $payment['appointment_id'])) ?>">
                                <?= e($payment['appointment_code']) ?></a>
                        <?php endif; ?>
                        &middot; registrado el <?= e(local_datetime((string) $payment['created_at'])) ?>
                    </p>
                </div>

                <span class="pill pill--<?= e(match ((string) $payment['status']) {
                    'approved' => 'success', 'rejected' => 'danger',
                    'awaiting_verification' => 'warning', default => '',
                }) ?>"><?= e(match ((string) $payment['status']) {
                    'approved' => 'Aprobado', 'rejected' => 'Rechazado',
                    'awaiting_verification' => 'Por verificar', 'refunded' => 'Reembolsado',
                    default => 'Registrado',
                }) ?></span>
            </div>

            <div class="form-row">
                <div>
                    <?php if ((string) $payment['reference'] !== ''): ?>
                        <p class="text-small mb-1">
                            <span class="text-muted">Referencia:</span>
                            <span class="mono"><?= e($payment['reference']) ?></span>
                        </p>
                    <?php endif; ?>

                    <?php if (($payment['bank_name'] ?? null) !== null): ?>
                        <p class="text-small mb-1">
                            <span class="text-muted">Cuenta destino:</span> <?= e($payment['bank_name']) ?>
                        </p>
                    <?php endif; ?>

                    <?php if ($payment['transferred_at'] !== null): ?>
                        <p class="text-small mb-1">
                            <span class="text-muted">Fecha declarada:</span>
                            <?= e(local_datetime((string) $payment['transferred_at'], 'd/m/Y')) ?>
                        </p>
                    <?php endif; ?>

                    <?php if ((string) $payment['notes'] !== ''): ?>
                        <div class="alert alert--warning mt-1"><span><?= e($payment['notes']) ?></span></div>
                    <?php endif; ?>

                    <?php if ((string) $payment['rejection_reason'] !== ''): ?>
                        <div class="alert alert--error mt-1">
                            <span>Motivo del rechazo: <?= e($payment['rejection_reason']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <p class="text-muted text-small mb-1">Comprobantes</p>

                    <?php if (empty($payment['proofs'])): ?>
                        <p class="text-small text-muted">El cliente aun no subio ningun comprobante.</p>
                    <?php else: ?>
                        <div class="flex gap-1 flex-wrap">
                            <?php foreach ($payment['proofs'] as $proof): ?>
                                <?php $url = media_url((string) $proof['file_path']); ?>
                                <?php if (str_starts_with((string) $proof['file_mime'], 'image/')): ?>
                                    <img class="proof-thumb" src="<?= e($url) ?>" alt="Comprobante"
                                         data-lightbox-src="<?= e($url) ?>">
                                <?php else: ?>
                                    <a class="btn btn--ghost btn--sm" href="<?= e($url) ?>" target="_blank" rel="noopener">
                                        Ver PDF
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (in_array((string) $payment['status'], ['pending', 'awaiting_verification'], true)): ?>
                <div class="btn-row mt-2">
                    <form method="post" action="<?= e(url('/panel/pagos/' . (int) $payment['id'] . '/aprobar')) ?>"
                          data-confirm="Aprobar este pago y confirmar la cita?">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--success btn--sm">Aprobar pago</button>
                    </form>

                    <details class="collapsible" style="flex:1;min-width:240px">
                        <summary class="btn btn--ghost btn--sm">Rechazar</summary>
                        <form method="post" action="<?= e(url('/panel/pagos/' . (int) $payment['id'] . '/rechazar')) ?>" class="mt-1">
                            <?= csrf_field() ?>
                            <div class="field">
                                <label for="reason-<?= (int) $payment['id'] ?>">Motivo (lo vera el cliente)</label>
                                <input id="reason-<?= (int) $payment['id'] ?>" type="text" name="reason" required
                                       minlength="5" maxlength="255"
                                       placeholder="Ej. el comprobante no corresponde al importe">
                            </div>
                            <button type="submit" class="btn btn--danger btn--sm">Confirmar rechazo</button>
                        </form>
                    </details>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?= View::partial('partials.pagination', [
    'result' => $result, 'baseUrl' => url('/panel/pagos'),
    'query' => ['estado' => $status, 'q' => $search],
]) ?>
<?php View::stop(); ?>
