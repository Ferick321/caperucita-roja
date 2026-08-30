<?php
/**
 * @var array<string,mixed>|null $member
 * @var list<array<string,mixed>> $branches
 * @var list<array<string,mixed>> $categories
 * @var list<int> $assignedServices
 * @var list<array<string,mixed>> $schedules
 * @var list<array<string,mixed>> $timeOff
 */

use App\Core\View;

View::extend('layouts.admin');

$id = $member === null ? 0 : (int) $member['id'];
$action = $id > 0 ? url('/panel/personal/' . $id) : url('/panel/personal');
$value = static fn (string $key, mixed $default = ''): mixed => $member[$key] ?? $default;

$weekdays = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];

$byWeekday = [];
foreach ($schedules as $schedule) {
    $byWeekday[(int) $schedule['weekday']] = $schedule;
}
?>
<?php View::start('title'); ?><?= $id > 0 ? e((string) $value('display_name')) : 'Nuevo profesional' ?><?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/personal')) ?>">&larr; Equipo</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="grid grid--sidebar">
    <div>
        <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="card">
                <h2>Ficha profesional</h2>

                <div class="form-row">
                    <div class="field">
                        <label for="display_name">Nombre visible *</label>
                        <input id="display_name" type="text" name="display_name" required maxlength="120"
                               value="<?= e($value('display_name', old('display_name'))) ?>">
                        <?= field_error('display_name') ?>
                    </div>
                    <div class="field">
                        <label for="title">Especialidad</label>
                        <input id="title" type="text" name="title" maxlength="100"
                               value="<?= e($value('title')) ?>" placeholder="Barbero, Estilista, Manicurista...">
                    </div>
                </div>

                <div class="field">
                    <label for="branch_id">Sucursal *</label>
                    <select id="branch_id" name="branch_id" required>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= (int) $branch['id'] ?>"
                                    <?= (int) $value('branch_id') === (int) $branch['id'] ? 'selected' : '' ?>>
                                <?= e($branch['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="bio">Presentacion</label>
                    <textarea id="bio" name="bio" rows="4" maxlength="2000"><?= e($value('bio')) ?></textarea>
                    <span class="field__hint">Se muestra en la pagina "Nuestro equipo".</span>
                </div>

                <div class="form-row--3 form-row">
                    <div class="field">
                        <label for="phone">Telefono</label>
                        <input id="phone" type="tel" name="phone" value="<?= e($value('phone')) ?>">
                    </div>
                    <div class="field">
                        <label for="email">Correo</label>
                        <input id="email" type="email" name="email" value="<?= e($value('email')) ?>">
                        <span class="field__hint">Necesario para darle acceso al panel.</span>
                    </div>
                    <div class="field">
                        <label for="instagram">Instagram</label>
                        <input id="instagram" type="text" name="instagram" value="<?= e($value('instagram')) ?>"
                               placeholder="@usuario">
                    </div>
                </div>

                <div class="form-row--3 form-row">
                    <div class="field">
                        <label for="color">Color en la agenda</label>
                        <div class="flex gap-1 items-center">
                            <input type="color" value="<?= e($value('color', '#0ea5e9')) ?>" data-color-sync="#color">
                            <input id="color" type="text" name="color" class="mono"
                                   value="<?= e($value('color', '#0ea5e9')) ?>">
                        </div>
                    </div>
                    <div class="field">
                        <label for="commission_percent">Comision (%)</label>
                        <input id="commission_percent" type="number" name="commission_percent"
                               step="0.01" min="0" max="100" value="<?= e((string) $value('commission_percent', 0)) ?>">
                    </div>
                    <div class="field">
                        <label for="sort_order">Orden</label>
                        <input id="sort_order" type="number" name="sort_order" min="0"
                               value="<?= e((string) $value('sort_order', 0)) ?>">
                    </div>
                </div>

                <div class="field">
                    <label for="photo">Fotografia</label>
                    <?php if ((string) $value('photo_path') !== ''): ?>
                        <img src="<?= e(media_url((string) $value('photo_path'))) ?>" alt=""
                             style="width:90px;height:90px;border-radius:50%;object-fit:cover;margin-bottom:8px">
                    <?php endif; ?>
                    <input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp"
                           data-preview="#preview-photo">
                    <img id="preview-photo" class="hidden mt-1" alt=""
                         style="width:90px;height:90px;border-radius:50%;object-fit:cover">
                </div>

                <div class="flex gap-2 flex-wrap mt-2">
                    <label class="checkbox">
                        <input type="checkbox" name="is_active" value="1"
                               <?= $member === null || (bool) $value('is_active') ? 'checked' : '' ?>>
                        <span>Activo</span>
                    </label>
                    <label class="checkbox">
                        <input type="checkbox" name="accepts_online" value="1"
                               <?= $member === null || (bool) $value('accepts_online') ? 'checked' : '' ?>>
                        <span>Acepta reservas en linea</span>
                    </label>
                    <label class="checkbox">
                        <input type="checkbox" name="show_on_web" value="1"
                               <?= $member === null || (bool) $value('show_on_web') ? 'checked' : '' ?>>
                        <span>Mostrar en la web</span>
                    </label>
                </div>
            </div>

            <div class="card">
                <h2>Servicios que presta</h2>
                <p class="text-muted text-small">
                    Solo se le podran reservar los servicios marcados.
                </p>

                <?php foreach ($categories as $category): ?>
                    <?php if (empty($category['services'])) { continue; } ?>
                    <h3 class="mt-2" style="font-size:.9rem;color:var(--a-primary)"><?= e($category['name']) ?></h3>

                    <?php foreach ($category['services'] as $service): ?>
                        <label class="checkbox">
                            <input type="checkbox" name="service_ids[]" value="<?= (int) $service['id'] ?>"
                                   <?= in_array((int) $service['id'], $assignedServices, true) ? 'checked' : '' ?>>
                            <span><?= e($service['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn btn--primary">
                <?= $id > 0 ? 'Guardar cambios' : 'Crear profesional' ?>
            </button>
        </form>
    </div>

    <div>
        <?php if ($id > 0): ?>
            <div class="card">
                <h3>Horario semanal</h3>
                <p class="text-muted text-small">
                    Sin horario no hay huecos disponibles para reservar.
                </p>

                <form method="post" action="<?= e(url('/panel/personal/' . $id . '/horario')) ?>">
                    <?= csrf_field() ?>

                    <?php foreach ($weekdays as $weekday => $label): ?>
                        <?php $schedule = $byWeekday[$weekday] ?? null; ?>
                        <fieldset>
                            <legend><?= e($label) ?></legend>

                            <label class="checkbox">
                                <input type="checkbox" name="day_<?= $weekday ?>" value="1"
                                       <?= $schedule !== null ? 'checked' : '' ?>>
                                <span>Trabaja este dia</span>
                            </label>

                            <div class="form-row">
                                <div class="field">
                                    <label for="start_<?= $weekday ?>">Entrada</label>
                                    <input id="start_<?= $weekday ?>" type="time" name="start_<?= $weekday ?>"
                                           value="<?= e($schedule !== null ? substr((string) $schedule['starts_at'], 0, 5) : '09:00') ?>">
                                </div>
                                <div class="field">
                                    <label for="end_<?= $weekday ?>">Salida</label>
                                    <input id="end_<?= $weekday ?>" type="time" name="end_<?= $weekday ?>"
                                           value="<?= e($schedule !== null ? substr((string) $schedule['ends_at'], 0, 5) : '19:00') ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="field">
                                    <label for="bs_<?= $weekday ?>">Pausa desde</label>
                                    <input id="bs_<?= $weekday ?>" type="time" name="break_start_<?= $weekday ?>"
                                           value="<?= e($schedule !== null && $schedule['break_start'] !== null
                                               ? substr((string) $schedule['break_start'], 0, 5) : '') ?>">
                                </div>
                                <div class="field">
                                    <label for="be_<?= $weekday ?>">Pausa hasta</label>
                                    <input id="be_<?= $weekday ?>" type="time" name="break_end_<?= $weekday ?>"
                                           value="<?= e($schedule !== null && $schedule['break_end'] !== null
                                               ? substr((string) $schedule['break_end'], 0, 5) : '') ?>">
                                </div>
                            </div>
                        </fieldset>
                    <?php endforeach; ?>

                    <button type="submit" class="btn btn--primary btn--block">Guardar horario</button>
                </form>
            </div>

            <div class="card">
                <h3>Vacaciones y ausencias</h3>

                <?php if ($timeOff !== []): ?>
                    <?php foreach ($timeOff as $absence): ?>
                        <div class="switch-row">
                            <div class="switch-row__text">
                                <strong><?= e(local_datetime((string) $absence['starts_at'], 'd/m/Y')) ?>
                                    &rarr; <?= e(local_datetime((string) $absence['ends_at'], 'd/m/Y')) ?></strong>
                                <span><?= e($absence['reason'] ?: 'Sin motivo indicado') ?></span>
                            </div>
                            <form method="post" data-confirm="Eliminar esta ausencia?"
                                  action="<?= e(url('/panel/personal/' . $id . '/ausencia/' . (int) $absence['id'] . '/eliminar')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn--ghost btn--sm">Quitar</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-small">Sin ausencias programadas.</p>
                <?php endif; ?>

                <form method="post" action="<?= e(url('/panel/personal/' . $id . '/ausencia')) ?>" class="mt-2">
                    <?= csrf_field() ?>

                    <div class="form-row">
                        <div class="field">
                            <label for="starts_on">Desde</label>
                            <input id="starts_on" type="date" name="starts_on" required>
                        </div>
                        <div class="field">
                            <label for="ends_on">Hasta</label>
                            <input id="ends_on" type="date" name="ends_on" required>
                        </div>
                    </div>

                    <label class="checkbox">
                        <input type="checkbox" name="is_full_day" value="1" checked>
                        <span>Dias completos</span>
                    </label>

                    <div class="form-row">
                        <div class="field">
                            <label for="start_time">Hora inicio</label>
                            <input id="start_time" type="time" name="start_time" value="09:00">
                        </div>
                        <div class="field">
                            <label for="end_time">Hora fin</label>
                            <input id="end_time" type="time" name="end_time" value="19:00">
                        </div>
                    </div>

                    <div class="field">
                        <label for="reason">Motivo</label>
                        <input id="reason" type="text" name="reason" maxlength="160" placeholder="Vacaciones, permiso...">
                    </div>

                    <button type="submit" class="btn btn--ghost btn--block">Bloquear agenda</button>
                </form>
            </div>

            <div class="card">
                <h3>Acceso al panel</h3>
                <?php if ($member['user_id'] !== null): ?>
                    <p class="text-small">Este profesional puede entrar al panel y ver su agenda.</p>
                    <form method="post" data-confirm="Revocar su acceso al panel?"
                          action="<?= e(url('/panel/personal/' . $id . '/acceso')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--danger btn--sm btn--block">Revocar acceso</button>
                    </form>
                <?php else: ?>
                    <p class="text-small text-muted">
                        Puedes crearle una cuenta para que vea su propia agenda. Se generara una
                        contrasena temporal que debera cambiar.
                    </p>
                    <form method="post" action="<?= e(url('/panel/personal/' . $id . '/acceso')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--ghost btn--sm btn--block">Crear acceso</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <h3>Siguiente paso</h3>
                <p class="text-muted text-small">
                    Al guardar podras definir su horario semanal, sus ausencias y darle acceso al panel.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php View::stop(); ?>
