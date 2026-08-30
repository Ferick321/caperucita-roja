<?php
/**
 * @var int $days
 * @var list<array{date:string,label:string,appointments:int,revenue:float}> $series
 * @var array<string,int> $statusBreakdown
 * @var list<array<string,mixed>> $topServices
 * @var list<array<string,mixed>> $staffPerformance
 * @var list<array<string,mixed>> $bannerPerformance
 * @var list<array{hour:int,total:int}> $peakHours
 * @var array<string,float> $revenue
 * @var list<array{source:string,total:int}> $sources
 * @var int $newClients
 * @var float $repeatRate
 */

use App\Core\View;

View::extend('layouts.admin');

$maxRevenue = max(1.0, max(array_column($series, 'revenue') ?: [1.0]));
$maxPeak = max(1, max(array_column($peakHours, 'total') ?: [1]));
?>
<?php View::start('title'); ?>Informes<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/reportes/exportar?dias=' . $days)) ?>">Exportar CSV</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<form method="get" action="<?= e(url('/panel/reportes')) ?>" class="card">
    <div class="filters">
        <div class="field">
            <label for="dias">Periodo</label>
            <select id="dias" name="dias" data-auto-submit>
                <?php foreach ([7 => 'Ultimos 7 dias', 30 => 'Ultimos 30 dias',
                                90 => 'Ultimos 90 dias', 365 => 'Ultimo ano'] as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $days === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn--primary">Aplicar</button>
    </div>
</form>

<div class="grid grid--4 mb-3">
    <div class="stat stat--success">
        <p class="stat__label">Facturado</p>
        <p class="stat__value"><?= e(money($revenue['facturado'])) ?></p>
        <p class="stat__meta">Citas completadas</p>
    </div>
    <div class="stat stat--primary">
        <p class="stat__label">Cobrado</p>
        <p class="stat__value"><?= e(money($revenue['cobrado'])) ?></p>
    </div>
    <div class="stat">
        <p class="stat__label">Ticket medio</p>
        <p class="stat__value"><?= e(money($revenue['ticket_medio'])) ?></p>
    </div>
    <div class="stat stat--warning">
        <p class="stat__label">Descuentos aplicados</p>
        <p class="stat__value"><?= e(money($revenue['descuentos'])) ?></p>
    </div>
</div>

<div class="grid grid--3 mb-3">
    <div class="stat">
        <p class="stat__label">Clientes nuevos</p>
        <p class="stat__value"><?= (int) $newClients ?></p>
    </div>
    <div class="stat stat--primary">
        <p class="stat__label">Clientes que repiten</p>
        <p class="stat__value"><?= e((string) $repeatRate) ?>%</p>
        <p class="stat__meta">De los que ya visitaron</p>
    </div>
    <div class="stat">
        <p class="stat__label">Citas del periodo</p>
        <p class="stat__value"><?= (int) array_sum($statusBreakdown) ?></p>
    </div>
</div>

<div class="card">
    <h2>Ingresos por dia</h2>
    <div class="chart">
        <?php foreach ($series as $point): ?>
            <div class="chart__bar">
                <div class="chart__fill"
                     style="height: <?= e((string) round($point['revenue'] / $maxRevenue * 100)) ?>%"
                     data-value="<?= e($point['label'] . ': ' . money($point['revenue'])) ?>"></div>
                <span class="chart__label"><?= e($point['label']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="grid grid--2">
    <div class="card">
        <h2>Horas con mas demanda</h2>
        <p class="text-muted text-small">Util para ajustar los turnos de tu equipo.</p>

        <?php if ($peakHours === []): ?>
            <p class="text-muted">Sin datos suficientes.</p>
        <?php else: ?>
            <div class="chart" style="height:150px">
                <?php foreach ($peakHours as $hour): ?>
                    <div class="chart__bar">
                        <div class="chart__fill"
                             style="height: <?= e((string) round($hour['total'] / $maxPeak * 100)) ?>%"
                             data-value="<?= e(sprintf('%02d:00 - %d citas', $hour['hour'], $hour['total'])) ?>"></div>
                        <span class="chart__label"><?= e(sprintf('%02d', $hour['hour'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>De donde vienen las reservas</h2>
        <?php
        $totalSources = max(1, array_sum(array_column($sources, 'total')));
        $sourceLabels = [
            'web' => 'Pagina web', 'app' => 'App movil', 'panel' => 'Mostrador',
            'phone' => 'Telefono', 'walk_in' => 'Sin cita previa',
        ];
        ?>
        <?php foreach ($sources as $source): ?>
            <div style="margin-bottom:12px">
                <div class="flex justify-between text-small mb-1">
                    <span><?= e($sourceLabels[$source['source']] ?? $source['source']) ?></span>
                    <strong><?= (int) $source['total'] ?>
                        (<?= e((string) round($source['total'] / $totalSources * 100)) ?>%)</strong>
                </div>
                <div class="progress">
                    <div class="progress__fill" style="width: <?= e((string) round($source['total'] / $totalSources * 100)) ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="grid grid--2">
    <div class="card card--flush">
        <div class="card__head" style="padding:20px 20px 0"><h2>Servicios mas vendidos</h2></div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Servicio</th><th>Veces</th><th class="text-right">Facturado</th></tr></thead>
                <tbody>
                    <?php foreach ($topServices as $service): ?>
                        <tr>
                            <td><?= e($service['name']) ?></td>
                            <td><?= (int) $service['total'] ?></td>
                            <td class="text-right"><?= e(money((float) $service['revenue'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($topServices === []): ?>
                        <tr><td colspan="3" class="text-muted text-center">Sin datos.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card card--flush">
        <div class="card__head" style="padding:20px 20px 0"><h2>Rendimiento del equipo</h2></div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Profesional</th><th>Citas</th><th class="text-right">Facturado</th><th>Ausencias</th></tr></thead>
                <tbody>
                    <?php foreach ($staffPerformance as $member): ?>
                        <tr>
                            <td><?= e($member['name']) ?></td>
                            <td><?= (int) $member['appointments'] ?></td>
                            <td class="text-right"><?= e(money((float) $member['revenue'])) ?></td>
                            <td><?= (int) $member['no_shows'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($staffPerformance === []): ?>
                        <tr><td colspan="4" class="text-muted text-center">Sin datos.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($bannerPerformance !== []): ?>
    <div class="card card--flush">
        <div class="card__head" style="padding:20px 20px 0"><h2>Rendimiento publicitario</h2></div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Anuncio</th><th>Vistas</th><th>Clics</th><th>Cierres</th><th>Efectividad</th></tr></thead>
                <tbody>
                    <?php foreach ($bannerPerformance as $banner): ?>
                        <?php
                        $impressions = (int) ($banner['impressions'] ?? 0);
                        $clicks = (int) ($banner['clicks'] ?? 0);
                        ?>
                        <tr>
                            <td><?= e($banner['name']) ?></td>
                            <td><?= e((string) $impressions) ?></td>
                            <td><?= e((string) $clicks) ?></td>
                            <td><?= e((string) (int) ($banner['dismissals'] ?? 0)) ?></td>
                            <td><?= e($impressions > 0 ? (string) round($clicks / $impressions * 100, 2) : '0') ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php View::stop(); ?>
