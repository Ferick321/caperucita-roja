<?php
/** @var array<string,mixed> $appointment */

$statusLabels = [
    'pending' => ['Pendiente', 'warning'],
    'confirmed' => ['Confirmada', 'success'],
    'in_progress' => ['En curso', 'primary'],
    'completed' => ['Completada', 'success'],
    'cancelled' => ['Cancelada', 'danger'],
    'no_show' => ['No asististe', 'danger'],
];

$paymentLabels = [
    'unpaid' => ['Sin pagar', 'warning'],
    'deposit_paid' => ['Abono pagado', 'primary'],
    'awaiting_verification' => ['Verificando pago', 'warning'],
    'paid' => ['Pagada', 'success'],
    'refunded' => ['Reembolsada', 'danger'],
];

[$statusLabel, $statusColor] = $statusLabels[(string) $appointment['status']] ?? ['Pendiente', 'warning'];
[$payLabel, $payColor] = $paymentLabels[(string) $appointment['payment_status']] ?? ['Sin pagar', 'warning'];

$isUpcoming = in_array((string) $appointment['status'], ['pending', 'confirmed', 'in_progress'], true);
?>
<article class="card" style="padding:20px">
    <div class="flex justify-between items-center gap-2 flex-wrap">
        <span class="pill pill--<?= e($statusColor) ?>"><?= e($statusLabel) ?></span>
        <span class="text-small text-muted"><?= e($appointment['code']) ?></span>
    </div>

    <h3 class="mt-2" style="font-size:1.1rem">
        <?= e(local_datetime((string) $appointment['starts_at'], 'd/m/Y \a \l\a\s H:i')) ?>
    </h3>

    <?php if (!empty($appointment['staff_name'])): ?>
        <p class="text-muted text-small mb-1">Con <?= e($appointment['staff_name']) ?></p>
    <?php endif; ?>

    <?php if (!empty($appointment['services'])): ?>
        <p class="text-small mb-1">
            <?= e(implode(', ', array_map(
                static fn (array $s): string => (string) $s['service_name'],
                $appointment['services']
            ))) ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($appointment['branch_name'])): ?>
        <p class="text-muted text-small mb-2"><?= e($appointment['branch_name']) ?></p>
    <?php endif; ?>

    <div class="flex justify-between items-center gap-2 flex-wrap mt-2">
        <strong style="color:var(--color-primary)"><?= e(money((float) $appointment['total'])) ?></strong>
        <span class="pill pill--<?= e($payColor) ?>"><?= e($payLabel) ?></span>
    </div>

    <?php if ($isUpcoming): ?>
        <div class="flex gap-1 mt-2 flex-wrap">
            <?php if ((string) $appointment['payment_status'] !== 'paid'): ?>
                <a class="btn btn--ghost btn--sm"
                   href="<?= e(url('/mis-citas/' . (int) $appointment['id'] . '/pago')) ?>">Pagar / comprobante</a>
            <?php endif; ?>

            <form method="post" action="<?= e(url('/mis-citas/' . (int) $appointment['id'] . '/cancelar')) ?>"
                  data-confirm="Seguro que quieres cancelar esta cita?" style="display:inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--ghost btn--sm">Cancelar</button>
            </form>
        </div>
    <?php endif; ?>
</article>
