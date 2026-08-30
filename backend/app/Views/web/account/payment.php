<?php
/**
 * Pago de una cita: elegir metodo, ver datos bancarios y subir el comprobante.
 *
 * @var array<string,mixed> $appointment
 * @var list<array<string,mixed>> $services
 * @var list<array<string,mixed>> $payments
 * @var list<array<string,mixed>> $methods
 * @var list<array<string,mixed>> $bankAccounts
 * @var string $instructions
 * @var string $cspNonce
 */

use App\Core\View;

View::extend('layouts.public');

$statusLabels = [
    'pending' => ['Registrado', 'warning'],
    'awaiting_verification' => ['En verificacion', 'warning'],
    'approved' => ['Aprobado', 'success'],
    'rejected' => ['Rechazado', 'danger'],
    'refunded' => ['Reembolsado', 'primary'],
];

$pending = max(0.0, (float) $appointment['total'] - (float) $appointment['paid_amount']);
?>

<?php View::start('title'); ?>Pago de la cita <?= e($appointment['code']) ?><?php View::stop(); ?>

<?php View::start('content'); ?>
<section class="section">
    <div class="container" style="max-width:780px">
        <p class="text-small text-muted">
            <a href="<?= e(url('/mis-citas')) ?>">Mis citas</a> / Pago
        </p>

        <h1>Pago de tu cita</h1>
        <p class="text-muted">
            Codigo <strong><?= e($appointment['code']) ?></strong> &middot;
            <?= e(local_datetime((string) $appointment['starts_at'])) ?>
        </p>

        <div class="card" style="padding:24px">
            <?php foreach ($services as $service): ?>
                <div class="summary-row">
                    <span><?= e($service['service_name']) ?></span>
                    <strong><?= e(money((float) $service['price'])) ?></strong>
                </div>
            <?php endforeach; ?>

            <?php if ((float) $appointment['paid_amount'] > 0): ?>
                <div class="summary-row">
                    <span>Ya pagado</span>
                    <strong>- <?= e(money((float) $appointment['paid_amount'])) ?></strong>
                </div>
            <?php endif; ?>

            <div class="summary-total">
                <span>Pendiente</span>
                <strong><?= e(money($pending)) ?></strong>
            </div>
        </div>

        <?php if ($payments !== []): ?>
            <h2 class="mt-4" style="font-size:1.15rem">Pagos registrados</h2>

            <?php foreach ($payments as $payment): ?>
                <?php [$label, $color] = $statusLabels[(string) $payment['status']] ?? ['Registrado', 'warning']; ?>
                <div class="card mb-2" style="padding:18px">
                    <div class="flex justify-between items-center flex-wrap gap-2">
                        <div>
                            <strong><?= e(money((float) $payment['amount'])) ?></strong>
                            <span class="text-muted text-small">
                                &middot; <?= e($payment['method_code']) ?>
                                &middot; <?= e(local_datetime((string) $payment['created_at'], 'd/m/Y H:i')) ?>
                            </span>
                        </div>
                        <span class="pill pill--<?= e($color) ?>"><?= e($label) ?></span>
                    </div>

                    <?php if ((string) $payment['rejection_reason'] !== ''): ?>
                        <div class="alert alert--error mt-2 mb-0">
                            <?= e($payment['rejection_reason']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($payment['proofs'])): ?>
                        <div class="flex gap-1 mt-2 flex-wrap">
                            <?php foreach ($payment['proofs'] as $proof): ?>
                                <a href="<?= e(media_url((string) $proof['file_path'])) ?>" target="_blank"
                                   rel="noopener noreferrer" class="pill">
                                    Ver comprobante (<?= e(local_datetime((string) $proof['created_at'], 'd/m/Y')) ?>)
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($pending > 0 && (string) $appointment['status'] !== 'cancelled'): ?>
            <h2 class="mt-4" style="font-size:1.15rem">Registrar un pago</h2>

            <form method="post" enctype="multipart/form-data"
                  action="<?= e(url('/mis-citas/' . (int) $appointment['id'] . '/pago')) ?>"
                  class="card" style="padding:24px">
                <?= csrf_field() ?>

                <div class="method-list">
                    <?php foreach ($methods as $index => $method): ?>
                        <label class="method-option">
                            <input type="radio" name="payment_method_id" value="<?= (int) $method['id'] ?>"
                                   <?= $index === 0 ? 'checked' : '' ?>
                                   data-bank="<?= (bool) $method['shows_bank_accounts'] ? '1' : '0' ?>"
                                   data-proof="<?= (bool) $method['requires_proof'] ? '1' : '0' ?>">
                            <span>
                                <strong><?= e($method['name']) ?></strong>
                                <span class="service-option__meta" style="display:block">
                                    <?= e($method['description']) ?>
                                </span>
                                <?php if ((string) ($method['instructions'] ?? '') !== ''): ?>
                                    <span class="text-small text-muted" style="display:block;margin-top:6px">
                                        <?= e($method['instructions']) ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <!-- Datos bancarios: solo se muestran al elegir transferencia -->
                <div id="bank-section" class="mt-3 hidden">
                    <h3 style="font-size:1rem">Transfiere a cualquiera de estas cuentas</h3>
                    <?php if ($instructions !== ''): ?>
                        <p class="text-small text-muted"><?= e($instructions) ?></p>
                    <?php endif; ?>

                    <?php if ($bankAccounts === []): ?>
                        <div class="alert alert--warning">
                            Todavia no hay cuentas publicadas. Escribenos para coordinar el pago.
                        </div>
                    <?php else: ?>
                        <?php foreach ($bankAccounts as $account): ?>
                            <div class="bank-card">
                                <div class="bank-row"><span>Banco</span><strong><?= e($account['bank_name']) ?></strong></div>
                                <div class="bank-row"><span>Tipo</span><strong><?= e($account['account_type']) ?></strong></div>
                                <div class="bank-row">
                                    <span>Numero de cuenta</span>
                                    <strong><?= e($account['account_number']) ?>
                                        <button type="button" class="copy-btn"
                                                data-copy="<?= e($account['account_number']) ?>">Copiar</button>
                                    </strong>
                                </div>
                                <div class="bank-row"><span>Titular</span><strong><?= e($account['holder_name']) ?></strong></div>
                                <?php if ((string) $account['holder_document'] !== ''): ?>
                                    <div class="bank-row">
                                        <span>Identificacion</span>
                                        <strong><?= e($account['holder_document']) ?>
                                            <button type="button" class="copy-btn"
                                                    data-copy="<?= e($account['holder_document']) ?>">Copiar</button>
                                        </strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <div class="field mt-2">
                            <label for="bank_account_id">A que cuenta transferiste?</label>
                            <select id="bank_account_id" name="bank_account_id">
                                <?php foreach ($bankAccounts as $account): ?>
                                    <option value="<?= (int) $account['id'] ?>">
                                        <?= e($account['bank_name']) ?> - <?= e($account['account_number']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-grid form-grid--2 mt-3">
                    <div class="field">
                        <label for="p-amount">Importe transferido</label>
                        <input id="p-amount" type="number" name="amount" step="0.01" min="0"
                               value="<?= e(number_format($pending, 2, '.', '')) ?>">
                    </div>
                    <div class="field">
                        <label for="p-date">Fecha del pago</label>
                        <input id="p-date" type="date" name="transferred_at" value="<?= e(date('Y-m-d')) ?>">
                    </div>
                </div>

                <div class="field mt-2">
                    <label for="p-reference">Numero de comprobante / referencia</label>
                    <input id="p-reference" type="text" name="reference" maxlength="120"
                           placeholder="Ej. 000123456">
                </div>

                <!-- Subida del comprobante: archivo o foto tomada con la camara -->
                <div id="proof-section" class="mt-3 hidden">
                    <h3 style="font-size:1rem">Sube tu comprobante</h3>
                    <p class="text-small text-muted">
                        Puedes adjuntar una captura, una foto o un PDF. Maximo
                        <?= e(number_format((int) config('uploads.max_bytes', 5242880) / 1048576, 1)) ?> MB.
                    </p>

                    <div class="upload-zone" data-upload>
                        <div class="upload-zone__icon">&#128247;</div>
                        <p class="mb-1"><strong>Toca para elegir un archivo o tomar una foto</strong></p>
                        <p class="text-small text-muted mb-0">JPG, PNG, WEBP o PDF</p>
                        <input type="file" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf"
                               capture="environment" class="sr-only">
                        <div class="upload-preview" data-upload-preview></div>
                    </div>
                </div>

                <button type="submit" class="btn btn--primary btn--block mt-3">Registrar el pago</button>
            </form>
        <?php endif; ?>
    </div>
</section>
<?php View::stop(); ?>

<?php View::start('scripts'); ?>
<script nonce="<?= e($cspNonce) ?>">
    (function () {
        var bankSection = document.getElementById('bank-section');
        var proofSection = document.getElementById('proof-section');
        var inputs = document.querySelectorAll('input[name="payment_method_id"]');

        function sync() {
            var selected = document.querySelector('input[name="payment_method_id"]:checked');
            if (!selected) { return; }

            if (bankSection) {
                bankSection.classList.toggle('hidden', selected.getAttribute('data-bank') !== '1');
            }
            if (proofSection) {
                proofSection.classList.toggle('hidden', selected.getAttribute('data-proof') !== '1');
            }
        }

        inputs.forEach(function (input) { input.addEventListener('change', sync); });
        sync();
    })();
</script>
<?php View::stop(); ?>
