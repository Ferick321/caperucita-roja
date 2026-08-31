<?php
/**
 * @var array<string,mixed>|null $branch
 * @var array<int,string> $weekdays
 * @var array<int,array<string,mixed>> $hours
 * @var list<array<string,mixed>> $closures
 * @var list<string> $timezones
 */

use App\Core\View;

View::extend('layouts.admin');

$esNueva = $branch === null;
$id = $esNueva ? 0 : (int) $branch['id'];
$accion = $esNueva ? '/panel/sucursales' : '/panel/sucursales/' . $id;

/** Devuelve la hora en formato HH:MM para el input, o cadena vacia. */
$hhmm = static function (mixed $valor): string {
    $valor = (string) ($valor ?? '');

    return $valor === '' ? '' : mb_substr($valor, 0, 5);
};
?>
<?php View::start('title'); ?><?= $esNueva ? 'Nueva sucursal' : e((string) $branch['name']) ?><?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/sucursales')) ?>">&larr; Sucursales</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>

<form method="post" enctype="multipart/form-data" action="<?= e(url($accion)) ?>">
    <?= csrf_field() ?>

    <div class="card mb-3">
        <h2>Datos del local</h2>

        <div class="field">
            <label for="name">Nombre</label>
            <input type="text" id="name" name="name" required maxlength="120"
                   placeholder="Ej: Barberia Centro"
                   value="<?= e(old('name', $branch['name'] ?? '')) ?>">
            <?= field_error('name') ?>
        </div>

        <div class="grid grid--2">
            <div class="field">
                <label for="address">Direccion</label>
                <input type="text" id="address" name="address" maxlength="255"
                       value="<?= e(old('address', $branch['address'] ?? '')) ?>">
                <?= field_error('address') ?>
            </div>
            <div class="field">
                <label for="city">Ciudad</label>
                <input type="text" id="city" name="city" maxlength="100"
                       value="<?= e(old('city', $branch['city'] ?? '')) ?>">
                <?= field_error('city') ?>
            </div>
        </div>

        <div class="grid grid--3">
            <div class="field">
                <label for="phone">Telefono</label>
                <input type="text" id="phone" name="phone" maxlength="30"
                       value="<?= e(old('phone', $branch['phone'] ?? '')) ?>">
                <?= field_error('phone') ?>
            </div>
            <div class="field">
                <label for="whatsapp">WhatsApp</label>
                <input type="text" id="whatsapp" name="whatsapp" maxlength="30"
                       value="<?= e(old('whatsapp', $branch['whatsapp'] ?? '')) ?>">
                <?= field_error('whatsapp') ?>
            </div>
            <div class="field">
                <label for="email">Correo</label>
                <input type="email" id="email" name="email" maxlength="190"
                       value="<?= e(old('email', $branch['email'] ?? '')) ?>">
                <?= field_error('email') ?>
            </div>
        </div>

        <div class="field">
            <label for="maps_url">Enlace de Google Maps <span class="text-muted">(opcional)</span></label>
            <input type="url" id="maps_url" name="maps_url" maxlength="500"
                   placeholder="https://maps.google.com/..."
                   value="<?= e(old('maps_url', $branch['maps_url'] ?? '')) ?>">
            <?= field_error('maps_url') ?>
        </div>

        <div class="grid grid--3">
            <div class="field">
                <label for="timezone">Zona horaria</label>
                <select id="timezone" name="timezone">
                    <?php $tzActual = (string) old('timezone', $branch['timezone'] ?? 'America/Guayaquil'); ?>
                    <?php foreach ($timezones as $tz): ?>
                        <option value="<?= e($tz) ?>" <?= $tzActual === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error('timezone') ?>
            </div>
            <div class="field">
                <label for="sort_order">Orden</label>
                <input type="number" id="sort_order" name="sort_order" min="0" max="9999"
                       value="<?= e(old('sort_order', $branch['sort_order'] ?? 0)) ?>">
                <?= field_error('sort_order') ?>
            </div>
            <div class="field">
                <label for="photo">Foto del local</label>
                <input type="file" id="photo" name="photo" accept="image/*">
                <?php if (!$esNueva && (string) $branch['photo_path'] !== ''): ?>
                    <p class="field__help">Ya hay una foto cargada. Sube otra para reemplazarla.</p>
                <?php endif; ?>
            </div>
        </div>

        <label class="checkbox">
            <input type="checkbox" name="is_active" value="1"
                <?= old('is_active', $branch['is_active'] ?? 1) ? 'checked' : '' ?>>
            <span>Sucursal abierta <span class="text-muted">(aparece en la web y acepta citas)</span></span>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Guardar sucursal</button>
            <a class="btn btn--ghost" href="<?= e(url('/panel/sucursales')) ?>">Cancelar</a>
        </div>
    </div>
</form>

<?php if (!$esNueva): ?>
    <div class="card mb-3">
        <h2>Horario de atencion</h2>
        <p class="text-muted text-small">
            Marca <strong>Cerrado</strong> en los dias que no abres. El descanso es opcional:
            si lo llenas, a esa hora no se ofrecen citas.
        </p>

        <form method="post" action="<?= e(url('/panel/sucursales/' . $id . '/horario')) ?>">
            <?= csrf_field() ?>

            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Dia</th><th>Abre</th><th>Cierra</th>
                            <th>Descanso desde</th><th>Descanso hasta</th><th>Cerrado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($weekdays as $numero => $nombre): ?>
                            <?php $dia = $hours[$numero] ?? null; ?>
                            <tr>
                                <td><strong><?= e($nombre) ?></strong></td>
                                <td>
                                    <input type="time" name="abre_<?= $numero ?>"
                                           value="<?= e($hhmm($dia['opens_at'] ?? '09:00:00')) ?>">
                                </td>
                                <td>
                                    <input type="time" name="cierra_<?= $numero ?>"
                                           value="<?= e($hhmm($dia['closes_at'] ?? '19:00:00')) ?>">
                                </td>
                                <td>
                                    <input type="time" name="descanso_ini_<?= $numero ?>"
                                           value="<?= e($hhmm($dia['break_start'] ?? '')) ?>">
                                </td>
                                <td>
                                    <input type="time" name="descanso_fin_<?= $numero ?>"
                                           value="<?= e($hhmm($dia['break_end'] ?? '')) ?>">
                                </td>
                                <td>
                                    <label class="checkbox">
                                        <input type="checkbox" name="cerrado_<?= $numero ?>" value="1"
                                            <?= ($dia !== null && (bool) $dia['is_closed']) ? 'checked' : '' ?>>
                                        <span class="sr-only">Cerrado el <?= e($nombre) ?></span>
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary">Guardar horario</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Feriados y dias cerrados</h2>
        <p class="text-muted text-small">
            Vacaciones, feriados o cualquier dia suelto en que no atiendes.
            Esas fechas dejan de aceptar citas al momento.
        </p>

        <form method="post" action="<?= e(url('/panel/sucursales/' . $id . '/cierres')) ?>">
            <?= csrf_field() ?>
            <div class="grid grid--3">
                <div class="field">
                    <label for="starts_on">Desde</label>
                    <input type="date" id="starts_on" name="starts_on" required>
                    <?= field_error('starts_on') ?>
                </div>
                <div class="field">
                    <label for="ends_on">Hasta</label>
                    <input type="date" id="ends_on" name="ends_on" required>
                    <?= field_error('ends_on') ?>
                </div>
                <div class="field">
                    <label for="reason">Motivo</label>
                    <input type="text" id="reason" name="reason" maxlength="160" placeholder="Ej: Feriado nacional">
                    <?= field_error('reason') ?>
                </div>
            </div>
            <button type="submit" class="btn btn--primary">Agregar</button>
        </form>

        <?php if ($closures !== []): ?>
            <div class="table-wrap mt-3">
                <table class="data">
                    <thead><tr><th>Desde</th><th>Hasta</th><th>Motivo</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($closures as $cierre): ?>
                            <tr>
                                <td><?= e((string) $cierre['starts_on']) ?></td>
                                <td><?= e((string) $cierre['ends_on']) ?></td>
                                <td class="text-small text-muted"><?= e((string) $cierre['reason']) ?></td>
                                <td>
                                    <form method="post"
                                          action="<?= e(url('/panel/sucursales/' . $id . '/cierres/' . (int) $cierre['id'] . '/eliminar')) ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn--danger btn--sm">Quitar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="help-box">
        Guarda la sucursal primero. Despues podras ponerle el horario de cada dia
        y los feriados.
    </div>
<?php endif; ?>
<?php View::stop(); ?>
