<?php
/**
 * Flujo de agendamiento en cuatro pasos.
 *
 * @var list<array<string,mixed>> $branches
 * @var list<array<string,mixed>> $categories
 * @var list<array<string,mixed>> $staff
 * @var list<array<string,mixed>> $paymentMethods
 * @var int $preselectedService
 * @var int $preselectedStaff
 * @var array<string,mixed>|null $authUser
 * @var string $cspNonce
 */

use App\Core\View;
use App\Services\BookingService;
use App\Services\SettingsService;

View::extend('layouts.public');

$allowStaffChoice = SettingsService::bool('booking.allow_staff_choice', true);
$allowNoPreference = SettingsService::bool('booking.allow_no_preference', true);
$allowMultiple = SettingsService::bool('booking.allow_multiple_services', true);
$customEnabled = SettingsService::bool('booking.custom_request_enabled', true);
$defaultBranch = $branches[0]['id'] ?? 0;
?>

<?php View::start('title'); ?>Agendar cita<?php View::stop(); ?>

<?php View::start('content'); ?>
<section class="section">
    <div class="container">
        <div class="section__head">
            <span class="section__eyebrow">Reserva en linea</span>
            <h1>Agenda tu cita</h1>
            <p>Elige lo que necesitas, cuando quieres venir y como prefieres pagar.</p>
        </div>

        <div class="booking-steps" role="list">
            <div class="booking-step is-active" data-step-indicator="1" role="listitem">
                <span class="booking-step__num">1</span> Servicios
            </div>
            <div class="booking-step" data-step-indicator="2" role="listitem">
                <span class="booking-step__num">2</span> Profesional
            </div>
            <div class="booking-step" data-step-indicator="3" role="listitem">
                <span class="booking-step__num">3</span> Fecha y hora
            </div>
            <div class="booking-step" data-step-indicator="4" role="listitem">
                <span class="booking-step__num">4</span> Datos y pago
            </div>
        </div>

        <form method="post" action="<?= e(url('/agendar')) ?>" id="booking-form" data-allow-resubmit>
            <?= csrf_field() ?>
            <?= honeypot_field() ?>
            <input type="hidden" name="branch_id" id="branch_id" value="<?= (int) $defaultBranch ?>">
            <input type="hidden" name="staff_id" id="staff_id" value="0">
            <input type="hidden" name="date" id="selected_date" value="">
            <input type="hidden" name="time" id="selected_time" value="">

            <div class="grid" style="grid-template-columns: 1.55fr .95fr; gap:28px" id="booking-layout">
                <div>
                    <!-- Paso 1: servicios -->
                    <div class="card" style="padding:24px" data-step="1">
                        <h2 style="font-size:1.2rem">1. Que servicio necesitas?</h2>

                        <?php if (count($branches) > 1): ?>
                            <div class="field mt-2">
                                <label for="branch-select">Sucursal</label>
                                <select id="branch-select">
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?= (int) $branch['id'] ?>"><?= e($branch['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($categories as $category): ?>
                            <?php if (empty($category['services'])) { continue; } ?>
                            <h3 class="mt-3" style="font-size:1rem;color:var(--color-primary)">
                                <?= e($category['name']) ?>
                            </h3>

                            <div class="service-picker">
                                <?php foreach ($category['services'] as $service): ?>
                                    <?php $price = BookingService::currentPrice($service); ?>
                                    <label class="service-option">
                                        <input type="<?= $allowMultiple ? 'checkbox' : 'radio' ?>"
                                               name="service_ids[]"
                                               value="<?= (int) $service['id'] ?>"
                                               data-price="<?= e((string) $price) ?>"
                                               data-name="<?= e($service['name']) ?>"
                                               data-duration="<?= (int) $service['duration_minutes'] ?>"
                                               <?= $preselectedService === (int) $service['id'] ? 'checked' : '' ?>>
                                        <span class="service-option__info">
                                            <span class="service-option__name"><?= e($service['name']) ?></span>
                                            <span class="service-option__meta">
                                                <?= e(minutes_to_human((int) $service['duration_minutes'])) ?>
                                                <?php if ((string) $service['short_description'] !== ''): ?>
                                                    &middot; <?= e(str_limit((string) $service['short_description'], 70)) ?>
                                                <?php endif; ?>
                                            </span>
                                        </span>
                                        <span class="service-option__price"><?= e(money($price)) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($customEnabled): ?>
                            <div class="field mt-3">
                                <label for="custom_request">
                                    <?= e(SettingsService::string('booking.custom_request_label', 'Otro (especifica lo que necesitas)')) ?>
                                </label>
                                <input id="custom_request" type="text" name="custom_request" maxlength="255"
                                       placeholder="Ej. peinado para matrimonio, tratamiento capilar...">
                                <span class="field__hint">
                                    Si tu servicio no esta en la lista, describelo y lo coordinamos contigo.
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Paso 2: profesional -->
                    <?php if ($allowStaffChoice): ?>
                        <div class="card mt-3" style="padding:24px" data-step="2">
                            <h2 style="font-size:1.2rem">2. Con quien quieres atenderte?</h2>

                            <div class="grid grid--3 mt-2" id="staff-picker">
                                <?php if ($allowNoPreference): ?>
                                    <label class="service-option" style="flex-direction:column;text-align:center">
                                        <input type="radio" name="staff_choice" value="0" checked>
                                        <span class="staff-card__photo" style="margin:8px auto">?</span>
                                        <span class="service-option__name">Sin preferencia</span>
                                        <span class="service-option__meta">El primero disponible</span>
                                    </label>
                                <?php endif; ?>

                                <?php foreach ($staff as $member): ?>
                                    <label class="service-option" style="flex-direction:column;text-align:center">
                                        <input type="radio" name="staff_choice" value="<?= (int) $member['id'] ?>"
                                               <?= $preselectedStaff === (int) $member['id'] ? 'checked' : '' ?>>
                                        <?php if ((string) $member['photo_path'] !== ''): ?>
                                            <img class="staff-card__photo" style="margin:8px auto;width:78px;height:78px"
                                                 src="<?= e(media_url($member['photo_path'])) ?>"
                                                 alt="<?= e($member['display_name']) ?>" loading="lazy">
                                        <?php else: ?>
                                            <span class="staff-card__photo" style="margin:8px auto;width:78px;height:78px;font-size:1.4rem">
                                                <?= e(initials((string) $member['display_name'])) ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="service-option__name"><?= e($member['display_name']) ?></span>
                                        <span class="service-option__meta"><?= e($member['title']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Paso 3: fecha y hora -->
                    <div class="card mt-3" style="padding:24px" data-step="3">
                        <h2 style="font-size:1.2rem">3. Cuando te viene bien?</h2>
                        <p class="text-muted text-small">Selecciona un servicio para ver los horarios disponibles.</p>

                        <div id="day-strip" class="day-strip mt-2"></div>
                        <div id="slot-area" class="mt-3"></div>
                    </div>

                    <!-- Paso 4: datos y pago -->
                    <div class="card mt-3" style="padding:24px" data-step="4">
                        <h2 style="font-size:1.2rem">4. Tus datos y forma de pago</h2>

                        <?php if ($authUser === null): ?>
                            <div class="form-grid mt-2">
                                <div class="field">
                                    <label for="b-name">Nombre completo *</label>
                                    <input id="b-name" type="text" name="client_name" required maxlength="160"
                                           value="<?= e(old('client_name')) ?>" autocomplete="name">
                                    <?= field_error('client_name') ?>
                                </div>

                                <div class="form-grid form-grid--2">
                                    <div class="field">
                                        <label for="b-phone">Telefono / WhatsApp *</label>
                                        <input id="b-phone" type="tel" name="client_phone" required
                                               value="<?= e(old('client_phone')) ?>" autocomplete="tel">
                                        <?= field_error('client_phone') ?>
                                    </div>
                                    <div class="field">
                                        <label for="b-email">Correo (opcional)</label>
                                        <input id="b-email" type="email" name="client_email"
                                               value="<?= e(old('client_email')) ?>" autocomplete="email">
                                        <span class="field__hint">Para enviarte la confirmacion.</span>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert--info mt-2">
                                Reservando como <strong><?= e($authUser['first_name'] . ' ' . $authUser['last_name']) ?></strong>
                                (<?= e($authUser['email']) ?>).
                            </div>
                        <?php endif; ?>

                        <div class="field mt-2">
                            <label for="b-notes">Algo que debamos saber? (opcional)</label>
                            <textarea id="b-notes" name="notes" maxlength="1000"
                                      placeholder="Alergias, preferencias, referencia de corte..."><?= e(old('notes')) ?></textarea>
                        </div>

                        <?php if (SettingsService::bool('payments.enabled', true) && $paymentMethods !== []): ?>
                            <h3 class="mt-3" style="font-size:1rem">Como prefieres pagar?</h3>
                            <div class="method-list mt-2">
                                <?php foreach ($paymentMethods as $index => $method): ?>
                                    <label class="method-option">
                                        <input type="radio" name="payment_method_id" value="<?= (int) $method['id'] ?>"
                                               <?= $index === 0 ? 'checked' : '' ?>
                                               data-shows-bank="<?= (bool) $method['shows_bank_accounts'] ? '1' : '0' ?>">
                                        <span>
                                            <strong><?= e($method['name']) ?></strong>
                                            <span class="service-option__meta" style="display:block">
                                                <?= e($method['description']) ?>
                                            </span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div class="alert alert--info mt-2 hidden" id="transfer-note">
                                Al confirmar te mostraremos los datos bancarios y podras subir tu comprobante
                                (o tomarle una foto) para que validemos el pago.
                            </div>
                        <?php endif; ?>

                        <div class="field mt-3">
                            <label for="b-coupon">Tienes un cupon?</label>
                            <input id="b-coupon" type="text" name="coupon_code" maxlength="40"
                                   placeholder="Codigo de descuento" style="text-transform:uppercase">
                        </div>

                        <?php if (SettingsService::string('booking.terms_text', '') !== ''): ?>
                            <p class="text-small text-muted mt-3">
                                <?= e(SettingsService::string('booking.terms_text')) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Resumen -->
                <aside>
                    <div class="summary-box">
                        <h3 style="font-size:1.1rem">Resumen de tu cita</h3>

                        <div id="summary-services" class="mt-2">
                            <p class="text-muted text-small">Aun no has elegido ningun servicio.</p>
                        </div>

                        <div class="summary-row" id="summary-duration-row" hidden>
                            <span>Duracion estimada</span>
                            <strong id="summary-duration">-</strong>
                        </div>

                        <div class="summary-row" id="summary-staff-row" hidden>
                            <span>Profesional</span>
                            <strong id="summary-staff">Sin preferencia</strong>
                        </div>

                        <div class="summary-row" id="summary-when-row" hidden>
                            <span>Fecha y hora</span>
                            <strong id="summary-when">-</strong>
                        </div>

                        <div class="summary-total">
                            <span>Total</span>
                            <strong id="summary-total"><?= e(money(0)) ?></strong>
                        </div>

                        <button type="submit" class="btn btn--primary btn--block mt-3" id="submit-booking" disabled>
                            Confirmar mi cita
                        </button>

                        <p class="text-small text-muted text-center mt-2 mb-0">
                            Podras cancelar con al menos
                            <?= (int) SettingsService::int('booking.cancellation_hours', 4) ?> horas de antelacion.
                        </p>
                    </div>
                </aside>
            </div>
        </form>
    </div>
</section>
<?php View::stop(); ?>

<?php View::start('scripts'); ?>
<script nonce="<?= e($cspNonce) ?>" src="<?= e(asset('js/booking.js')) ?>" defer></script>
<script nonce="<?= e($cspNonce) ?>">
    window.BOOKING_CONFIG = <?= e_js([
        'currencySymbol' => SettingsService::string('business.currency_symbol', '$'),
        'currencyPosition' => SettingsService::string('business.currency_position', 'before'),
        'decimals' => SettingsService::int('business.currency_decimals', 2),
        'customMinutes' => SettingsService::int('booking.custom_request_minutes', 30),
        'maxServices' => SettingsService::int('booking.max_services_per_appointment', 4),
    ]) ?>;
</script>
<?php View::stop(); ?>
