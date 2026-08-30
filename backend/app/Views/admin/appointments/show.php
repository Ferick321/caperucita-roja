<?php
/**
 * @var array<string,mixed> $appointment
 * @var list<array<string,mixed>> $services
 * @var list<array<string,mixed>> $history
 * @var list<array<string,mixed>> $payments
 * @var list<array<string,mixed>> $staffList
 * @var array<string,mixed>|null $client
 */

use App\Core\Clock;
use App\Core\View;
use App\Security\Auth;
use App\Services\PaymentService;

View::extend('layouts.admin');

$id = (int) $appointment['id'];
$isClosed = in_array((string) $appointment['status'], ['completed', 'cancelled', 'no_show'], true);
$pending = max(0.0, (float) $appointment['total'] - (float) $appointment['paid_amount']);
?>
<?php View::start('title'); ?>Cita <?= e($appointment['code']) ?><?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/citas')) ?>">&larr; Volver</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="grid grid--sidebar">
    <div>
        <div class="card">
            <div class="card__head">
                <h2><?= e(local_datetime((string) $appointment['starts_at'], 'l d/m/Y \a \l\a\s H:i')) ?></h2>
                <div class="flex gap-1">
                    <?= View::partial('partials.status_pill', ['status' => (string) $appointment['status']]) ?>
                    <?= View::partial('partials.payment_pill', ['status' => (string) $appointment['payment_status']]) ?>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <p class="text-muted text-small mb-0">Cliente</p>
                    <p><strong><?= e($appointment['client_name']) ?></strong></p>

                    <?php if ((string) $appointment['client_phone'] !== ''): ?>
                        <p class="mb-1">
                            <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $appointment['client_phone']) ?? '') ?>">
                                <?= e($appointment['client_phone']) ?>
                            </a>
                        </p>
                    <?php endif; ?>

                    <?php if ((string) $appointment['client_email'] !== ''): ?>
                        <p class="mb-1"><a href="mailto:<?= e($appointment['client_email']) ?>"><?= e($appointment['client_email']) ?></a></p>
                    <?php endif; ?>

                    <?php if ($client !== null): ?>
                        <p class="text-small">
                            <a href="<?= e(url('/panel/clientes/' . (int) $client['id'])) ?>">
                                Ver ficha (<?= (int) $client['total_visits'] ?> visitas)
                            </a>
                        </p>
                    <?php endif; ?>
                </div>

                <div>
                    <p class="text-muted text-small mb-0">Profesional</p>
                    <p><strong><?= e($appointment['staff_name'] ?? 'Sin asignar') ?></strong></p>

                    <p class="text-muted text-small mb-0">Local</p>
                    <p><?= e($appointment['branch_name'] ?? '-') ?></p>

                    <p class="text-muted text-small mb-0">Origen</p>
                    <p><span class="pill"><?= e($appointment['source']) ?></span></p>
                </div>
            </div>

            <h3 class="mt-2">Servicios</h3>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th>Servicio</th><th>Duracion</th><th class="text-right">Precio</th></tr></thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td><?= e($service['service_name']) ?></td>
                                <td><?= e(minutes_to_human((int) $service['duration_minutes'])) ?></td>
                                <td class="text-right"><?= e(money((float) $service['price'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ((float) $appointment['discount_amount'] > 0): ?>
                            <tr>
                                <td colspan="2">Descuento</td>
                                <td class="text-right">- <?= e(money((float) $appointment['discount_amount'])) ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td colspan="2"><strong>Total</strong></td>
                            <td class="text-right"><strong><?= e(money((float) $appointment['total'])) ?></strong></td>
                        </tr>
                        <?php if ((float) $appointment['paid_amount'] > 0): ?>
                            <tr>
                                <td colspan="2">Pagado</td>
                                <td class="text-right"><?= e(money((float) $appointment['paid_amount'])) ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ((string) $appointment['custom_request'] !== ''): ?>
                <div class="alert alert--info mt-2">
                    <span><strong>Peticion del cliente:</strong> <?= e($appointment['custom_request']) ?></span>
                </div>
            <?php endif; ?>

            <?php if ((string) ($appointment['client_notes'] ?? '') !== ''): ?>
                <div class="mt-2">
                    <p class="text-muted text-small mb-0">Comentario del cliente</p>
                    <p><?= nl2br(e((string) $appointment['client_notes'])) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($payments !== []): ?>
            <div class="card">
                <h3>Pagos</h3>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Fecha</th><th>Metodo</th><th>Referencia</th><th>Estado</th><th class="text-right">Importe</th></tr></thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td class="nowrap"><?= e(local_datetime((string) $payment['created_at'], 'd/m/Y H:i')) ?></td>
                                    <td><?= e($payment['method_code']) ?></td>
                                    <td class="mono text-small"><?= e($payment['reference']) ?></td>
                                    <td>
                                        <span class="pill pill--<?= e(match ((string) $payment['status']) {
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                            'awaiting_verification' => 'warning',
                                            default => '',
                                        }) ?>"><?= e($payment['status']) ?></span>
                                    </td>
                                    <td class="text-right"><?= e(money((float) $payment['amount'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-small mt-1"><a href="<?= e(url('/panel/pagos')) ?>">Ir a verificacion de pagos</a></p>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3>Historial</h3>
            <?php foreach ($history as $entry): ?>
                <div class="switch-row">
                    <div class="switch-row__text">
                        <strong>
                            <?= e($entry['from_status'] !== '' ? $entry['from_status'] . ' &rarr; ' : '') ?><?= e($entry['to_status']) ?>
                        </strong>
                        <span>
                            <?= e(local_datetime((string) $entry['created_at'])) ?>
                            <?php if (($entry['first_name'] ?? null) !== null): ?>
                                &middot; <?= e($entry['first_name'] . ' ' . $entry['last_name']) ?>
                            <?php endif; ?>
                            <?php if ((string) $entry['note'] !== ''): ?>
                                &middot; <?= e($entry['note']) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div>
        <?php if (Auth::can('citas.editar') && !$isClosed): ?>
            <div class="card">
                <h3>Cambiar estado</h3>

                <?php
                $transitions = [
                    'confirmed' => ['Confirmar', 'primary'],
                    'in_progress' => ['Marcar en curso', 'ghost'],
                    'completed' => ['Completar', 'success'],
                    'no_show' => ['No asistio', 'ghost'],
                ];
                ?>
                <div class="btn-row">
                    <?php foreach ($transitions as $status => [$label, $style]): ?>
                        <?php if ((string) $appointment['status'] === $status) { continue; } ?>
                        <form method="post" action="<?= e(url('/panel/citas/' . $id . '/estado')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="status" value="<?= e($status) ?>">
                            <button type="submit" class="btn btn--<?= e($style) ?> btn--sm"><?= e($label) ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>

                <form method="post" action="<?= e(url('/panel/citas/' . $id . '/estado')) ?>" class="mt-2"
                      data-confirm="Cancelar esta cita?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="cancelled">
                    <div class="field">
                        <label for="cancel-note">Motivo de la cancelacion</label>
                        <input id="cancel-note" type="text" name="note" maxlength="255"
                               placeholder="Ej. el cliente aviso por telefono">
                    </div>
                    <button type="submit" class="btn btn--danger btn--sm btn--block">Cancelar la cita</button>
                </form>
            </div>

            <div class="card">
                <h3>Reprogramar</h3>
                <form method="post" action="<?= e(url('/panel/citas/' . $id . '/reprogramar')) ?>">
                    <?= csrf_field() ?>

                    <div class="form-row">
                        <div class="field">
                            <label for="new-date">Nueva fecha</label>
                            <input id="new-date" type="date" name="date" required
                                   value="<?= e(local_datetime((string) $appointment['starts_at'], 'Y-m-d')) ?>"
                                   min="<?= e(Clock::today()) ?>">
                        </div>
                        <div class="field">
                            <label for="new-time">Nueva hora</label>
                            <input id="new-time" type="time" name="time" required
                                   value="<?= e(local_datetime((string) $appointment['starts_at'], 'H:i')) ?>">
                        </div>
                    </div>

                    <div class="field">
                        <label for="new-staff">Profesional</label>
                        <select id="new-staff" name="staff_id">
                            <?php foreach ($staffList as $member): ?>
                                <option value="<?= (int) $member['id'] ?>"
                                        <?= (int) ($appointment['staff_id'] ?? 0) === (int) $member['id'] ? 'selected' : '' ?>>
                                    <?= e($member['display_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn--primary btn--sm btn--block">Guardar nuevo horario</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if (Auth::can('pagos.verificar') && $pending > 0): ?>
            <div class="card">
                <h3>Registrar cobro</h3>
                <p class="text-muted text-small">Pendiente: <strong><?= e(money($pending)) ?></strong></p>

                <form method="post" action="<?= e(url('/panel/pagos/manual')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="appointment_id" value="<?= $id ?>">

                    <div class="field">
                        <label for="pm">Metodo</label>
                        <select id="pm" name="payment_method_id" required>
                            <?php foreach (PaymentService::availableMethods(false) as $method): ?>
                                <option value="<?= (int) $method['id'] ?>"><?= e($method['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="pa">Importe</label>
                        <input id="pa" type="number" name="amount" step="0.01" min="0.01"
                               value="<?= e(number_format($pending, 2, '.', '')) ?>" required>
                    </div>

                    <div class="field">
                        <label for="pr">Referencia (opcional)</label>
                        <input id="pr" type="text" name="reference" maxlength="120">
                    </div>

                    <button type="submit" class="btn btn--success btn--sm btn--block">Registrar cobro</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if (Auth::can('citas.eliminar')): ?>
            <div class="card card--danger">
                <h3>Eliminar</h3>
                <p class="text-muted text-small">
                    La cita deja de aparecer pero se conserva para la contabilidad hasta que la
                    politica de retencion la purgue definitivamente.
                </p>
                <form method="post" action="<?= e(url('/panel/citas/' . $id . '/eliminar')) ?>"
                      data-confirm="Eliminar esta cita?">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--danger btn--sm btn--block">Eliminar cita</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php View::stop(); ?>
