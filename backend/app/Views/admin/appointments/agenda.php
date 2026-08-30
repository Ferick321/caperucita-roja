<?php
/**
 * @var string $date
 * @var int $branchId
 * @var list<array<string,mixed>> $branches
 * @var list<array<string,mixed>> $staff
 * @var array<int,list<array<string,mixed>>> $byStaff
 * @var list<array<string,mixed>> $unassigned
 */

use App\Core\Clock;
use App\Core\View;

View::extend('layouts.admin');

$previous = (new DateTimeImmutable($date))->modify('-1 day')->format('Y-m-d');
$next = (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');
?>
<?php View::start('title'); ?>Agenda<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--primary btn--sm" href="<?= e(url('/panel/citas/nueva')) ?>">+ Nueva cita</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<form method="get" action="<?= e(url('/panel/citas/agenda')) ?>" class="card">
    <div class="filters">
        <a class="btn btn--ghost"
           href="<?= e(url('/panel/citas/agenda?fecha=' . $previous . '&sucursal=' . $branchId)) ?>">&larr;</a>

        <div class="field">
            <label for="fecha">Dia</label>
            <input id="fecha" type="date" name="fecha" value="<?= e($date) ?>" data-auto-submit>
        </div>

        <a class="btn btn--ghost"
           href="<?= e(url('/panel/citas/agenda?fecha=' . $next . '&sucursal=' . $branchId)) ?>">&rarr;</a>

        <?php if (count($branches) > 1): ?>
            <div class="field">
                <label for="sucursal">Sucursal</label>
                <select id="sucursal" name="sucursal" data-auto-submit>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?= (int) $branch['id'] ?>" <?= $branchId === (int) $branch['id'] ? 'selected' : '' ?>>
                            <?= e($branch['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <a class="btn btn--ghost" href="<?= e(url('/panel/citas/agenda?fecha=' . Clock::today())) ?>">Hoy</a>
    </div>
</form>

<?php if ($staff === []): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state__icon">&#128100;</div>
            <p>No hay profesionales activos en esta sucursal.</p>
            <a class="btn btn--primary" href="<?= e(url('/panel/personal/nuevo')) ?>">Registrar profesional</a>
        </div>
    </div>
<?php else: ?>
    <div class="agenda">
        <?php foreach ($staff as $member): ?>
            <?php $slots = $byStaff[(int) $member['id']] ?? []; ?>
            <section class="agenda__col">
                <header class="agenda__head">
                    <span class="agenda__dot" style="background: <?= e($member['color']) ?>"></span>
                    <div style="flex:1;min-width:0">
                        <strong><?= e($member['display_name']) ?></strong>
                        <div class="text-small text-muted"><?= e($member['title']) ?></div>
                    </div>
                    <span class="pill"><?= (int) count($slots) ?></span>
                </header>

                <div class="agenda__body">
                    <?php if ($slots === []): ?>
                        <p class="agenda__empty">Sin citas este dia</p>
                    <?php else: ?>
                        <?php foreach ($slots as $appointment): ?>
                            <a class="agenda__slot" style="border-left-color: <?= e($member['color']) ?>"
                               href="<?= e(url('/panel/citas/' . (int) $appointment['id'])) ?>">
                                <time><?= e($appointment['local_start']) ?> - <?= e($appointment['local_end']) ?></time>
                                <div><?= e($appointment['client_name']) ?></div>
                                <div class="mt-1">
                                    <?= View::partial('partials.status_pill', ['status' => (string) $appointment['status']]) ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <?php if ($unassigned !== []): ?>
            <section class="agenda__col">
                <header class="agenda__head">
                    <span class="agenda__dot" style="background: var(--a-muted)"></span>
                    <div style="flex:1"><strong>Sin asignar</strong></div>
                    <span class="pill pill--warning"><?= (int) count($unassigned) ?></span>
                </header>
                <div class="agenda__body">
                    <?php foreach ($unassigned as $appointment): ?>
                        <a class="agenda__slot" href="<?= e(url('/panel/citas/' . (int) $appointment['id'])) ?>">
                            <time><?= e($appointment['local_start']) ?></time>
                            <div><?= e($appointment['client_name']) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php View::stop(); ?>
