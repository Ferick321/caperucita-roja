<?php
/**
 * @var array<string,mixed> $stats
 * @var list<array{date:string,label:string,appointments:int,revenue:float}> $series
 * @var array<string,int> $statusBreakdown
 * @var list<array<string,mixed>> $topServices
 * @var list<array<string,mixed>> $staffPerformance
 * @var list<array<string,mixed>> $todayAgenda
 * @var list<array<string,mixed>> $pendingPayments
 * @var list<array{label:string,done:bool,url:string}> $setupPending
 */

use App\Core\View;

View::extend('layouts.admin');

$maxAppointments = max(1, max(array_column($series, 'appointments')));
?>

<?php View::start('title'); ?>Resumen<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/citas/agenda')) ?>">Agenda del dia</a>
    <a class="btn btn--primary btn--sm" href="<?= e(url('/panel/citas/nueva')) ?>">+ Nueva cita</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>

<?php if ($setupPending !== []): ?>
    <div class="card">
        <div class="card__head">
            <h2>Termina de configurar tu sistema</h2>
            <span class="pill pill--warning"><?= (int) count($setupPending) ?> pendiente(s)</span>
        </div>
        <p class="text-muted text-small">
            Estas tareas hacen que la web y la app se vean completas para tus clientes.
        </p>
        <?php foreach ($setupPending as $item): ?>
            <div class="switch-row">
                <div class="switch-row__text"><strong><?= e($item['label']) ?></strong></div>
                <a class="btn btn--ghost btn--sm" href="<?= e(url($item['url'])) ?>">Configurar</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="grid grid--4 mb-3">
    <div class="stat stat--primary">
        <p class="stat__label">Citas de hoy</p>
        <p class="stat__value"><?= (int) $stats['today_appointments'] ?></p>
        <p class="stat__meta"><?= (int) $stats['today_pending'] ?> por confirmar</p>
    </div>
    <div class="stat">
        <p class="stat__label">Proximas citas</p>
        <p class="stat__value"><?= (int) $stats['upcoming'] ?></p>
        <p class="stat__meta">Pendientes y confirmadas</p>
    </div>
    <div class="stat stat--<?= (int) $stats['payments_to_verify'] > 0 ? 'warning' : 'success' ?>">
        <p class="stat__label">Comprobantes por verificar</p>
        <p class="stat__value"><?= (int) $stats['payments_to_verify'] ?></p>
        <p class="stat__meta">
            <?php if ((int) $stats['payments_to_verify'] > 0): ?>
                <a href="<?= e(url('/panel/pagos')) ?>">Revisar ahora</a>
            <?php else: ?>
                Todo al dia
            <?php endif; ?>
        </p>
    </div>
    <div class="stat stat--success">
        <p class="stat__label">Facturado este mes</p>
        <p class="stat__value"><?= e(money((float) $stats['month_revenue'])) ?></p>
        <p class="stat__meta"><?= (int) $stats['month_appointments'] ?> citas registradas</p>
    </div>
</div>

<div class="grid grid--4 mb-3">
    <div class="stat">
        <p class="stat__label">Clientes registrados</p>
        <p class="stat__value"><?= (int) $stats['total_clients'] ?></p>
        <p class="stat__meta">+<?= (int) $stats['new_clients_month'] ?> este mes</p>
    </div>
    <div class="stat">
        <p class="stat__label">Aceptan publicidad</p>
        <p class="stat__value"><?= (int) $stats['marketing_subscribers'] ?></p>
        <p class="stat__meta"><a href="<?= e(url('/panel/campanas')) ?>">Crear campana</a></p>
    </div>
    <div class="stat stat--<?= (int) $stats['pending_reviews'] > 0 ? 'warning' : 'success' ?>">
        <p class="stat__label">Resenas por moderar</p>
        <p class="stat__value"><?= (int) $stats['pending_reviews'] ?></p>
        <p class="stat__meta"><a href="<?= e(url('/panel/contenido/resenas')) ?>">Moderar</a></p>
    </div>
    <div class="stat stat--<?= (int) $stats['unread_messages'] > 0 ? 'warning' : 'success' ?>">
        <p class="stat__label">Mensajes sin leer</p>
        <p class="stat__value"><?= (int) $stats['unread_messages'] ?></p>
        <p class="stat__meta"><a href="<?= e(url('/panel/contenido/mensajes')) ?>">Ver bandeja</a></p>
    </div>
</div>

<div class="grid grid--sidebar">
    <div>
        <div class="card">
            <div class="card__head">
                <h2>Citas de los ultimos 14 dias</h2>
            </div>

            <div class="chart">
                <?php foreach ($series as $point): ?>
                    <div class="chart__bar">
                        <div class="chart__fill"
                             style="height: <?= e((string) round($point['appointments'] / $maxAppointments * 100)) ?>%"
                             data-value="<?= e($point['label'] . ': ' . $point['appointments'] . ' citas') ?>"></div>
                        <span class="chart__label"><?= e($point['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card card--flush">
            <div class="card__head" style="padding:20px 20px 0">
                <h2>Agenda de hoy</h2>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/citas/agenda')) ?>">Ver completa</a>
            </div>

            <?php if ($todayAgenda === []): ?>
                <div class="empty-state">
                    <div class="empty-state__icon">&#128197;</div>
                    <p>No hay citas registradas para hoy.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr>
                                <th>Hora</th><th>Cliente</th><th>Profesional</th>
                                <th>Estado</th><th class="text-right">Total</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($todayAgenda as $appointment): ?>
                                <tr>
                                    <td class="nowrap"><strong><?= e(local_datetime((string) $appointment['starts_at'], 'H:i')) ?></strong></td>
                                    <td><?= e($appointment['client_name']) ?></td>
                                    <td class="text-muted"><?= e($appointment['staff_name'] ?? 'Sin asignar') ?></td>
                                    <td><?= View::partial('partials.status_pill', ['status' => (string) $appointment['status']]) ?></td>
                                    <td class="text-right nowrap"><?= e(money((float) $appointment['total'])) ?></td>
                                    <td class="text-right">
                                        <a class="btn btn--ghost btn--sm"
                                           href="<?= e(url('/panel/citas/' . (int) $appointment['id'])) ?>">Ver</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <?php if ($pendingPayments !== []): ?>
            <div class="card">
                <div class="card__head">
                    <h3>Pagos por verificar</h3>
                    <span class="pill pill--warning"><?= (int) count($pendingPayments) ?></span>
                </div>

                <?php foreach ($pendingPayments as $payment): ?>
                    <div class="switch-row">
                        <div class="switch-row__text">
                            <strong><?= e(money((float) $payment['amount'])) ?></strong>
                            <span><?= e($payment['client_name'] ?? 'Cliente') ?>
                                &middot; <?= e($payment['appointment_code'] ?? '') ?></span>
                        </div>
                        <a class="btn btn--primary btn--sm" href="<?= e(url('/panel/pagos')) ?>">Revisar</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3>Estados (30 dias)</h3>
            <?php
            $labels = [
                'completed' => ['Completadas', 'success'],
                'confirmed' => ['Confirmadas', 'info'],
                'pending' => ['Pendientes', 'warning'],
                'cancelled' => ['Canceladas', 'danger'],
                'no_show' => ['No asistieron', 'danger'],
                'in_progress' => ['En curso', 'primary'],
            ];
            $totalStatus = max(1, array_sum($statusBreakdown));
            ?>
            <?php foreach ($labels as $key => [$label, $color]): ?>
                <?php $value = (int) ($statusBreakdown[$key] ?? 0); ?>
                <div style="margin-bottom:12px">
                    <div class="flex justify-between text-small mb-1">
                        <span><?= e($label) ?></span>
                        <strong><?= e((string) $value) ?></strong>
                    </div>
                    <div class="progress">
                        <div class="progress__fill" style="width: <?= e((string) round($value / $totalStatus * 100)) ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($topServices !== []): ?>
            <div class="card">
                <h3>Servicios mas pedidos</h3>
                <?php foreach ($topServices as $service): ?>
                    <div class="switch-row">
                        <div class="switch-row__text">
                            <strong><?= e($service['name']) ?></strong>
                            <span><?= e(money((float) $service['revenue'])) ?> facturados</span>
                        </div>
                        <span class="pill pill--primary"><?= (int) $service['total'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($staffPerformance !== []): ?>
            <div class="card">
                <h3>Rendimiento del equipo (30 dias)</h3>
                <?php foreach ($staffPerformance as $member): ?>
                    <div class="switch-row">
                        <div class="switch-row__text">
                            <strong><?= e($member['name']) ?></strong>
                            <span><?= (int) $member['appointments'] ?> citas
                                &middot; <?= e(money((float) $member['revenue'])) ?></span>
                        </div>
                        <?php if ((int) $member['no_shows'] > 0): ?>
                            <span class="pill pill--danger"><?= (int) $member['no_shows'] ?> ausencias</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php View::stop(); ?>
