<?php
/**
 * @var array<string,mixed> $appointment
 * @var list<array<string,mixed>> $services
 * @var array<string,mixed>|null $staff
 * @var array<string,mixed>|null $branch
 * @var array<string,mixed>|null $payment
 * @var array<string,mixed>|null $method
 * @var list<array<string,mixed>> $bankAccounts
 * @var array<string,mixed>|null $authUser
 */

use App\Core\View;
use App\Services\SettingsService;

View::extend('layouts.public');

$statusLabels = [
    'pending' => ['Pendiente de confirmacion', 'warning'],
    'confirmed' => ['Confirmada', 'success'],
    'in_progress' => ['En curso', 'primary'],
    'completed' => ['Completada', 'success'],
    'cancelled' => ['Cancelada', 'danger'],
    'no_show' => ['No asistio', 'danger'],
];

[$statusLabel, $statusColor] = $statusLabels[(string) $appointment['status']] ?? ['Pendiente', 'warning'];
$needsTransfer = $method !== null && (bool) $method['shows_bank_accounts'];
?>

<?php View::start('title'); ?>Cita <?= e($appointment['code']) ?><?php View::stop(); ?>

<?php View::start('content'); ?>
<section class="section">
    <div class="container" style="max-width:760px">
        <div class="text-center mb-3">
            <div style="font-size:3.4rem">&#10003;</div>
            <h1>Tu cita esta registrada</h1>
            <p class="text-muted">
                Guarda tu codigo <strong><?= e($appointment['code']) ?></strong>.
                <?php if ((string) $appointment['client_email'] !== ''): ?>
                    Tambien te enviamos los detalles a tu correo.
                <?php endif; ?>
            </p>
            <span class="pill pill--<?= e($statusColor) ?>"><?= e($statusLabel) ?></span>
        </div>

        <div class="card" style="padding:26px">
            <h2 style="font-size:1.15rem">Detalle de la cita</h2>

            <div class="summary-row"><span>Codigo</span><strong><?= e($appointment['code']) ?></strong></div>
            <div class="summary-row">
                <span>Fecha y hora</span>
                <strong><?= e(local_datetime((string) $appointment['starts_at'], 'd/m/Y \a \l\a\s H:i')) ?></strong>
            </div>
            <div class="summary-row">
                <span>Duracion</span>
                <strong><?= e(minutes_to_human((int) $appointment['duration_minutes'])) ?></strong>
            </div>
            <?php if ($staff !== null): ?>
                <div class="summary-row"><span>Profesional</span><strong><?= e($staff['display_name']) ?></strong></div>
            <?php endif; ?>
            <?php if ($branch !== null): ?>
                <div class="summary-row">
                    <span>Local</span>
                    <strong><?= e($branch['name']) ?><?= (string) $branch['address'] !== '' ? ' - ' . e($branch['address']) : '' ?></strong>
                </div>
            <?php endif; ?>

            <h3 class="mt-3" style="font-size:1rem">Servicios</h3>
            <?php foreach ($services as $service): ?>
                <div class="summary-row">
                    <span><?= e($service['service_name']) ?></span>
                    <strong><?= e(money((float) $service['price'])) ?></strong>
                </div>
            <?php endforeach; ?>

            <?php if ((string) $appointment['custom_request'] !== ''): ?>
                <div class="summary-row">
                    <span>Peticion especial</span>
                    <strong><?= e($appointment['custom_request']) ?></strong>
                </div>
            <?php endif; ?>

            <?php if ((float) $appointment['discount_amount'] > 0): ?>
                <div class="summary-row">
                    <span>Descuento</span>
                    <strong>- <?= e(money((float) $appointment['discount_amount'])) ?></strong>
                </div>
            <?php endif; ?>

            <div class="summary-total">
                <span>Total</span>
                <strong><?= e(money((float) $appointment['total'])) ?></strong>
            </div>
        </div>

        <?php if ($needsTransfer): ?>
            <div class="card mt-3" style="padding:26px">
                <h2 style="font-size:1.15rem">Datos para la transferencia</h2>
                <p class="text-muted text-small">
                    <?= e(SettingsService::string('payments.transfer_instructions', '')) ?>
                </p>

                <?php if ($bankAccounts === []): ?>
                    <div class="alert alert--warning">
                        Aun no hay cuentas bancarias publicadas. Comunicate con nosotros para coordinar el pago.
                    </div>
                <?php else: ?>
                    <?php foreach ($bankAccounts as $account): ?>
                        <div class="bank-card">
                            <div class="bank-row">
                                <span>Banco</span>
                                <strong><?= e($account['bank_name']) ?></strong>
                            </div>
                            <div class="bank-row">
                                <span>Tipo de cuenta</span>
                                <strong><?= e($account['account_type']) ?></strong>
                            </div>
                            <div class="bank-row">
                                <span>Numero de cuenta</span>
                                <strong>
                                    <?= e($account['account_number']) ?>
                                    <button type="button" class="copy-btn"
                                            data-copy="<?= e($account['account_number']) ?>">Copiar</button>
                                </strong>
                            </div>
                            <div class="bank-row">
                                <span>Titular</span>
                                <strong><?= e($account['holder_name']) ?></strong>
                            </div>
                            <?php if ((string) $account['holder_document'] !== ''): ?>
                                <div class="bank-row">
                                    <span>Identificacion</span>
                                    <strong>
                                        <?= e($account['holder_document']) ?>
                                        <button type="button" class="copy-btn"
                                                data-copy="<?= e($account['holder_document']) ?>">Copiar</button>
                                    </strong>
                                </div>
                            <?php endif; ?>
                            <?php if ((string) $account['holder_email'] !== ''): ?>
                                <div class="bank-row">
                                    <span>Correo del titular</span>
                                    <strong><?= e($account['holder_email']) ?></strong>
                                </div>
                            <?php endif; ?>
                            <?php if ((string) ($account['instructions'] ?? '') !== ''): ?>
                                <p class="text-small text-muted mt-2 mb-0"><?= e($account['instructions']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="alert alert--info mt-2">
                        Importe a transferir: <strong><?= e(money((float) $appointment['total'])) ?></strong>
                    </div>
                <?php endif; ?>

                <?php if ($authUser !== null): ?>
                    <a class="btn btn--primary btn--block mt-2"
                       href="<?= e(url('/mis-citas/' . (int) $appointment['id'] . '/pago')) ?>">
                        Subir mi comprobante de pago
                    </a>
                <?php else: ?>
                    <div class="alert alert--warning mt-2">
                        Para subir tu comprobante desde la web necesitas una cuenta.
                        <a href="<?= e(url('/registro')) ?>">Crear cuenta</a> o envianoslo por WhatsApp.
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card mt-3" style="padding:26px">
            <h2 style="font-size:1.15rem">Y ahora?</h2>
            <ul class="text-muted" style="padding-left:20px">
                <li>Te avisaremos cuando confirmemos tu cita.</li>
                <li>Recibiras un recordatorio
                    <?= (int) SettingsService::int('notifications.reminder_hours_before', 24) ?> horas antes.</li>
                <li>Puedes cancelar con al menos
                    <?= (int) SettingsService::int('booking.cancellation_hours', 4) ?> horas de antelacion.</li>
            </ul>

            <div class="flex gap-2 flex-wrap mt-3">
                <a class="btn btn--primary" href="<?= e(url('/app')) ?>">Descargar la app</a>
                <?php if ($authUser !== null): ?>
                    <a class="btn btn--ghost" href="<?= e(url('/mis-citas')) ?>">Ver mis citas</a>
                <?php else: ?>
                    <a class="btn btn--ghost" href="<?= e(url('/registro')) ?>">Crear mi cuenta</a>
                <?php endif; ?>
                <a class="btn btn--ghost" href="<?= e(url('/')) ?>">Volver al inicio</a>
            </div>
        </div>
    </div>
</section>
<?php View::stop(); ?>
