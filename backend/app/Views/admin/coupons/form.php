<?php
/**
 * @var array<string,mixed>|null $coupon
 * @var list<array<string,mixed>> $services
 * @var list<array<string,mixed>> $redemptions
 */

use App\Core\View;

View::extend('layouts.admin');

$esNuevo = $coupon === null;
$accion = $esNuevo ? '/panel/cupones' : '/panel/cupones/' . (int) $coupon['id'];

/** Fecha en formato AAAA-MM-DD para el input, desde un valor UTC. */
$fecha = static function (mixed $utc): string {
    $utc = (string) ($utc ?? '');

    return $utc === '' ? '' : local_datetime($utc, 'Y-m-d');
};
?>
<?php View::start('title'); ?><?= $esNuevo ? 'Nuevo cupon' : e((string) $coupon['code']) ?><?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/cupones')) ?>">&larr; Cupones</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<form method="post" action="<?= e(url($accion)) ?>">
    <?= csrf_field() ?>

    <div class="card mb-3">
        <h2>Datos del cupon</h2>

        <div class="grid grid--2">
            <div class="field">
                <label for="code">Codigo</label>
                <input type="text" id="code" name="code" required maxlength="40"
                       placeholder="Ej: BIENVENIDA10" style="text-transform: uppercase"
                       value="<?= e(old('code', $coupon['code'] ?? '')) ?>">
                <p class="field__help">Es lo que el cliente escribe al reservar. Se guarda en mayusculas.</p>
                <?= field_error('code') ?>
            </div>
            <div class="field">
                <label for="description">Descripcion</label>
                <input type="text" id="description" name="description" maxlength="255"
                       placeholder="Ej: 10% para clientes nuevos"
                       value="<?= e(old('description', $coupon['description'] ?? '')) ?>">
                <?= field_error('description') ?>
            </div>
        </div>

        <div class="grid grid--3">
            <div class="field">
                <label for="discount_type">Tipo de descuento</label>
                <select id="discount_type" name="discount_type" required>
                    <?php $tipo = (string) old('discount_type', $coupon['discount_type'] ?? 'percent'); ?>
                    <option value="percent" <?= $tipo === 'percent' ? 'selected' : '' ?>>Porcentaje (%)</option>
                    <option value="fixed" <?= $tipo === 'fixed' ? 'selected' : '' ?>>Monto fijo</option>
                </select>
                <?= field_error('discount_type') ?>
            </div>
            <div class="field">
                <label for="discount_value">Valor</label>
                <input type="number" id="discount_value" name="discount_value" required step="0.01" min="0"
                       value="<?= e(old('discount_value', $coupon['discount_value'] ?? '')) ?>">
                <p class="field__help">En porcentaje, como maximo 100.</p>
                <?= field_error('discount_value') ?>
            </div>
            <div class="field">
                <label for="max_discount">Descuento maximo <span class="text-muted">(opcional)</span></label>
                <input type="number" id="max_discount" name="max_discount" step="0.01" min="0"
                       value="<?= e(old('max_discount', $coupon['max_discount'] ?? '')) ?>">
                <p class="field__help">Tope en dinero para un porcentaje alto.</p>
                <?= field_error('max_discount') ?>
            </div>
        </div>

        <div class="grid grid--2">
            <div class="field">
                <label for="min_amount">Monto minimo de la cita</label>
                <input type="number" id="min_amount" name="min_amount" step="0.01" min="0"
                       value="<?= e(old('min_amount', $coupon['min_amount'] ?? 0)) ?>">
                <p class="field__help">Deja 0 para que valga con cualquier importe.</p>
                <?= field_error('min_amount') ?>
            </div>
            <div class="field">
                <label for="service_id">Solo para un servicio <span class="text-muted">(opcional)</span></label>
                <select id="service_id" name="service_id">
                    <?php $sid = (int) old('service_id', $coupon['service_id'] ?? 0); ?>
                    <option value="0">Todos los servicios</option>
                    <?php foreach ($services as $servicio): ?>
                        <option value="<?= (int) $servicio['id'] ?>" <?= $sid === (int) $servicio['id'] ? 'selected' : '' ?>>
                            <?= e((string) $servicio['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= field_error('service_id') ?>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <h2>Limites</h2>

        <div class="grid grid--2">
            <div class="field">
                <label for="usage_limit">Cuantas veces se puede usar en total</label>
                <input type="number" id="usage_limit" name="usage_limit" min="0" max="1000000"
                       value="<?= e(old('usage_limit', $coupon['usage_limit'] ?? 0)) ?>">
                <p class="field__help">0 = sin limite.</p>
                <?= field_error('usage_limit') ?>
            </div>
            <div class="field">
                <label for="usage_limit_per_user">Cuantas veces por cliente</label>
                <input type="number" id="usage_limit_per_user" name="usage_limit_per_user" min="0" max="1000"
                       value="<?= e(old('usage_limit_per_user', $coupon['usage_limit_per_user'] ?? 1)) ?>">
                <p class="field__help">0 = sin limite.</p>
                <?= field_error('usage_limit_per_user') ?>
            </div>
        </div>

        <div class="grid grid--2">
            <div class="field">
                <label for="starts_at">Valido desde <span class="text-muted">(opcional)</span></label>
                <input type="date" id="starts_at" name="starts_at"
                       value="<?= e(old('starts_at', $fecha($coupon['starts_at'] ?? ''))) ?>">
                <?= field_error('starts_at') ?>
            </div>
            <div class="field">
                <label for="ends_at">Valido hasta <span class="text-muted">(opcional)</span></label>
                <input type="date" id="ends_at" name="ends_at"
                       value="<?= e(old('ends_at', $fecha($coupon['ends_at'] ?? ''))) ?>">
                <?= field_error('ends_at') ?>
            </div>
        </div>

        <label class="checkbox">
            <input type="checkbox" name="first_visit_only" value="1"
                <?= old('first_visit_only', $coupon['first_visit_only'] ?? 0) ? 'checked' : '' ?>>
            <span>Solo para clientes que vienen por primera vez</span>
        </label>

        <label class="checkbox">
            <input type="checkbox" name="is_active" value="1"
                <?= old('is_active', $coupon['is_active'] ?? 1) ? 'checked' : '' ?>>
            <span>Cupon activo</span>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Guardar cupon</button>
            <a class="btn btn--ghost" href="<?= e(url('/panel/cupones')) ?>">Cancelar</a>
        </div>
    </div>
</form>

<?php if (!$esNuevo && $redemptions !== []): ?>
    <div class="card card--flush">
        <div class="card__head">
            <h2>Ultimos canjes</h2>
            <span class="pill"><?= (int) $coupon['times_used'] ?> uso(s) en total</span>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Cliente</th><th>Descuento</th><th>Fecha</th></tr></thead>
                <tbody>
                    <?php foreach ($redemptions as $canje): ?>
                        <tr>
                            <td class="text-small">
                                <?php if ($canje['email'] !== null): ?>
                                    <?= e(trim((string) $canje['first_name'] . ' ' . (string) $canje['last_name'])) ?>
                                    <div class="text-muted"><?= e((string) $canje['email']) ?></div>
                                <?php else: ?>
                                    <span class="text-muted">Cliente sin cuenta</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e(money((float) $canje['discount_applied'])) ?></td>
                            <td class="text-small text-muted"><?= e(local_datetime((string) $canje['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php View::stop(); ?>
