<?php
/**
 * @var list<array<string,mixed>> $branches
 * @var list<array<string,mixed>> $categories
 * @var list<array<string,mixed>> $staffList
 * @var string $cspNonce
 */

use App\Core\Clock;
use App\Core\View;
use App\Services\BookingService;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Nueva cita<?php View::stop(); ?>

<?php View::start('content'); ?>
<form method="post" action="<?= e(url('/panel/citas')) ?>">
    <?= csrf_field() ?>

    <div class="grid grid--sidebar">
        <div>
            <div class="card">
                <h2>Datos del cliente</h2>

                <div class="form-row">
                    <div class="field">
                        <label for="client_name">Nombre completo *</label>
                        <input id="client_name" type="text" name="client_name" required maxlength="160"
                               value="<?= e(old('client_name')) ?>">
                        <?= field_error('client_name') ?>
                    </div>
                    <div class="field">
                        <label for="client_phone">Telefono</label>
                        <input id="client_phone" type="tel" name="client_phone" value="<?= e(old('client_phone')) ?>">
                        <?= field_error('client_phone') ?>
                    </div>
                </div>

                <div class="field">
                    <label for="client_email">Correo (opcional)</label>
                    <input id="client_email" type="email" name="client_email" value="<?= e(old('client_email')) ?>">
                    <span class="field__hint">Si lo indicas, el cliente recibira la confirmacion por correo.</span>
                </div>

                <div class="field">
                    <label for="source">Origen</label>
                    <select id="source" name="source">
                        <option value="panel">Mostrador</option>
                        <option value="phone">Telefono</option>
                        <option value="walk_in">Sin cita previa</option>
                    </select>
                </div>
            </div>

            <div class="card">
                <h2>Servicios</h2>

                <?php foreach ($categories as $category): ?>
                    <?php if (empty($category['services'])) { continue; } ?>
                    <h3 class="mt-2" style="font-size:.92rem;color:var(--a-primary)"><?= e($category['name']) ?></h3>

                    <?php foreach ($category['services'] as $service): ?>
                        <?php $price = BookingService::currentPrice($service); ?>
                        <label class="checkbox">
                            <input type="checkbox" name="service_ids[]" value="<?= (int) $service['id'] ?>"
                                   data-duration="<?= (int) $service['duration_minutes'] ?>"
                                   data-price="<?= e((string) $price) ?>"
                                   data-name="<?= e($service['name']) ?>">
                            <span>
                                <?= e($service['name']) ?>
                                <span class="text-muted text-small">
                                    &middot; <?= e(minutes_to_human((int) $service['duration_minutes'])) ?>
                                    &middot; <?= e(money($price)) ?>
                                </span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                <?php endforeach; ?>

                <div class="field mt-2">
                    <label for="custom_request">Peticion especial (si no esta en la lista)</label>
                    <input id="custom_request" type="text" name="custom_request" maxlength="255"
                           placeholder="Ej. peinado de novia, tratamiento a medida...">
                </div>

                <div class="field">
                    <label for="notes">Notas</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="1000"></textarea>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <h2>Fecha y profesional</h2>

                <?php if (count($branches) > 1): ?>
                    <div class="field">
                        <label for="branch_id">Sucursal</label>
                        <select id="branch_id" name="branch_id" required>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= (int) $branch['id'] ?>"><?= e($branch['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="branch_id" id="branch_id" value="<?= (int) ($branches[0]['id'] ?? 0) ?>">
                <?php endif; ?>

                <div class="field">
                    <label for="staff_id">Profesional</label>
                    <select id="staff_id" name="staff_id">
                        <option value="0">Sin preferencia (asignar automatico)</option>
                        <?php foreach ($staffList as $member): ?>
                            <option value="<?= (int) $member['id'] ?>"><?= e($member['display_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="date">Fecha *</label>
                        <input id="date" type="date" name="date" required
                               value="<?= e(Clock::today()) ?>" min="<?= e(Clock::today()) ?>">
                        <?= field_error('date') ?>
                    </div>
                    <div class="field">
                        <label for="time">Hora *</label>
                        <input id="time" type="time" name="time" required value="09:00">
                        <?= field_error('time') ?>
                    </div>
                </div>

                <div class="field">
                    <label>Horarios libres</label>
                    <div id="slots"><p class="text-muted text-small">Elige servicios y fecha para consultarlos.</p></div>
                </div>

                <div class="switch-row">
                    <div class="switch-row__text">
                        <strong>Duracion estimada</strong>
                        <span id="total-duration">-</span>
                    </div>
                    <strong id="total-price"><?= e(money(0)) ?></strong>
                </div>

                <button type="submit" class="btn btn--primary btn--block mt-2">Crear cita</button>
                <a class="btn btn--ghost btn--block mt-1" href="<?= e(url('/panel/citas')) ?>">Cancelar</a>
            </div>
        </div>
    </div>
</form>
<?php View::stop(); ?>

<?php View::start('scripts'); ?>
<script nonce="<?= e($cspNonce) ?>">
(function () {
    'use strict';

    var form = document.querySelector('form');
    var slotsBox = document.getElementById('slots');
    var dateInput = document.getElementById('date');
    var timeInput = document.getElementById('time');
    var staffSelect = document.getElementById('staff_id');
    var branchInput = document.getElementById('branch_id');
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var symbol = <?= e_js(\App\Services\SettingsService::string('business.currency_symbol', '$')) ?>;

    function selectedServices() {
        return Array.prototype.slice.call(
            form.querySelectorAll('input[name="service_ids[]"]:checked')
        );
    }

    function updateTotals() {
        var services = selectedServices();
        var minutes = 0;
        var price = 0;

        services.forEach(function (input) {
            minutes += parseInt(input.getAttribute('data-duration') || '0', 10);
            price += parseFloat(input.getAttribute('data-price') || '0');
        });

        document.getElementById('total-duration').textContent = minutes > 0 ? minutes + ' min' : '-';
        document.getElementById('total-price').textContent = symbol + ' ' + price.toFixed(2);
    }

    function loadSlots() {
        var ids = selectedServices().map(function (i) { return parseInt(i.value, 10); });

        if (ids.length === 0 || !dateInput.value) {
            slotsBox.innerHTML = '<p class="text-muted text-small">Elige servicios y fecha para consultarlos.</p>';
            return;
        }

        slotsBox.innerHTML = '<p class="text-muted text-small">Consultando...</p>';

        fetch(<?= e_js(url('/panel/citas/disponibilidad')) ?>, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
            credentials: 'same-origin',
            body: JSON.stringify({
                service_ids: ids,
                branch_id: parseInt(branchInput.value, 10),
                staff_id: parseInt(staffSelect.value, 10),
                date: dateInput.value
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (payload) {
            var slots = (payload.data && payload.data.slots) || [];
            slotsBox.innerHTML = '';

            if (slots.length === 0) {
                slotsBox.innerHTML = '<p class="text-muted text-small">Sin horarios libres ese dia. '
                    + 'Puedes escribir la hora manualmente para sobrecargar la agenda.</p>';
                return;
            }

            var wrap = document.createElement('div');
            wrap.className = 'btn-row';

            slots.forEach(function (slot) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn--ghost btn--sm';
                button.textContent = slot.label;
                button.addEventListener('click', function () {
                    timeInput.value = slot.time.substring(0, 5);
                    wrap.querySelectorAll('.btn').forEach(function (b) {
                        b.classList.remove('btn--primary');
                        b.classList.add('btn--ghost');
                    });
                    button.classList.remove('btn--ghost');
                    button.classList.add('btn--primary');
                });
                wrap.appendChild(button);
            });

            slotsBox.appendChild(wrap);
        })
        .catch(function () {
            slotsBox.innerHTML = '<p class="text-muted text-small">No se pudo consultar la disponibilidad.</p>';
        });
    }

    form.querySelectorAll('input[name="service_ids[]"]').forEach(function (input) {
        input.addEventListener('change', function () { updateTotals(); loadSlots(); });
    });

    [dateInput, staffSelect, branchInput].forEach(function (element) {
        if (element) { element.addEventListener('change', loadSlots); }
    });

    updateTotals();
})();
</script>
<?php View::stop(); ?>
