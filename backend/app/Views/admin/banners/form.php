<?php
/**
 * @var array<string,mixed>|null $banner
 * @var array<string,string> $placements
 * @var list<mixed> $selectedPlacements
 * @var string $pagePattern
 * @var array<string,mixed>|null $stats
 */

use App\Core\View;

View::extend('layouts.admin');

$id = $banner === null ? 0 : (int) $banner['id'];
$action = $id > 0 ? url('/panel/publicidad/' . $id) : url('/panel/publicidad');
$value = static fn (string $key, mixed $default = ''): mixed => $banner[$key] ?? $default;
$selected = array_map('strval', $selectedPlacements);
$selectedWeekdays = array_filter(explode(',', (string) $value('weekdays')));

$weekdayNames = ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'];
?>
<?php View::start('title'); ?><?= $id > 0 ? 'Editar anuncio' : 'Nuevo anuncio' ?><?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/publicidad')) ?>">&larr; Publicidad</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="grid grid--sidebar">
        <div>
            <div class="card">
                <h2>Contenido del anuncio</h2>

                <div class="field">
                    <label for="name">Nombre interno *</label>
                    <input id="name" type="text" name="name" required maxlength="140"
                           value="<?= e($value('name', old('name'))) ?>"
                           placeholder="Solo para identificarlo en el panel">
                    <?= field_error('name') ?>
                </div>

                <div class="field">
                    <label for="title">Titulo</label>
                    <input id="title" type="text" name="title" maxlength="200" value="<?= e($value('title')) ?>"
                           placeholder="Ej. 20% de descuento en color esta semana">
                </div>

                <div class="field">
                    <label for="subtitle">Subtitulo</label>
                    <input id="subtitle" type="text" name="subtitle" maxlength="300" value="<?= e($value('subtitle')) ?>">
                </div>

                <div class="field">
                    <label for="body">Texto adicional</label>
                    <textarea id="body" name="body" rows="3" maxlength="2000"><?= e($value('body')) ?></textarea>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="cta_label">Texto del boton</label>
                        <input id="cta_label" type="text" name="cta_label" maxlength="80"
                               value="<?= e($value('cta_label')) ?>" placeholder="Ej. Reservar ahora">
                    </div>
                    <div class="field">
                        <label for="cta_url">Enlace del boton</label>
                        <input id="cta_url" type="text" name="cta_url" maxlength="500"
                               value="<?= e($value('cta_url')) ?>" placeholder="/agendar o https://...">
                        <span class="field__hint">Usa "/agendar" para llevar a la reserva.</span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="image">Imagen (escritorio)</label>
                        <?php if ((string) $value('image_path') !== ''): ?>
                            <img src="<?= e(media_url((string) $value('image_path'))) ?>" alt=""
                                 style="max-height:100px;border-radius:8px;margin-bottom:8px">
                        <?php endif; ?>
                        <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp"
                               data-preview="#pv-image">
                        <img id="pv-image" class="hidden mt-1" alt="" style="max-height:100px;border-radius:8px">
                    </div>
                    <div class="field">
                        <label for="mobile_image">Imagen (movil)</label>
                        <?php if ((string) $value('mobile_image_path') !== ''): ?>
                            <img src="<?= e(media_url((string) $value('mobile_image_path'))) ?>" alt=""
                                 style="max-height:100px;border-radius:8px;margin-bottom:8px">
                        <?php endif; ?>
                        <input id="mobile_image" type="file" name="mobile_image" accept="image/jpeg,image/png,image/webp"
                               data-preview="#pv-mobile">
                        <img id="pv-mobile" class="hidden mt-1" alt="" style="max-height:100px;border-radius:8px">
                        <span class="field__hint">Opcional. Si no la cargas se usa la de escritorio.</span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="background_color">Color de fondo</label>
                        <div class="flex gap-1 items-center">
                            <input type="color" value="<?= e($value('background_color', '#111827')) ?>"
                                   data-color-sync="#background_color">
                            <input id="background_color" type="text" name="background_color" class="mono"
                                   value="<?= e($value('background_color', '#111827')) ?>">
                        </div>
                    </div>
                    <div class="field">
                        <label for="text_color">Color del texto</label>
                        <div class="flex gap-1 items-center">
                            <input type="color" value="<?= e($value('text_color', '#ffffff')) ?>"
                                   data-color-sync="#text_color">
                            <input id="text_color" type="text" name="text_color" class="mono"
                                   value="<?= e($value('text_color', '#ffffff')) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>Donde se muestra</h2>
                <p class="text-muted text-small">
                    Puedes elegir varias ubicaciones a la vez. Si no marcas ninguna, el anuncio
                    queda guardado pero no aparece en ningun sitio.
                </p>

                <?php foreach ($placements as $key => $label): ?>
                    <label class="checkbox">
                        <input type="checkbox" name="placements[]" value="<?= e($key) ?>"
                               <?= in_array($key, $selected, true) ? 'checked' : '' ?>>
                        <span><?= e($label) ?></span>
                    </label>
                <?php endforeach; ?>

                <div class="field mt-2">
                    <label for="page_pattern">Paginas donde aplica</label>
                    <input id="page_pattern" type="text" name="page_pattern" maxlength="120"
                           value="<?= e($pagePattern) ?>" placeholder="*">
                    <span class="field__hint">
                        Usa <span class="mono">*</span> para todas, o rutas separadas por coma:
                        <span class="mono">/servicios*, /agendar</span>
                    </span>
                </div>
            </div>

            <div class="card">
                <h2>Cuando se muestra</h2>

                <div class="form-row">
                    <div class="field">
                        <label for="starts_at">Desde el dia</label>
                        <input id="starts_at" type="date" name="starts_at"
                               value="<?= e($value('starts_at') !== null && $value('starts_at') !== ''
                                   ? local_datetime((string) $value('starts_at'), 'Y-m-d') : '') ?>">
                    </div>
                    <div class="field">
                        <label for="ends_at">Hasta el dia</label>
                        <input id="ends_at" type="date" name="ends_at"
                               value="<?= e($value('ends_at') !== null && $value('ends_at') !== ''
                                   ? local_datetime((string) $value('ends_at'), 'Y-m-d') : '') ?>">
                    </div>
                </div>

                <div class="field">
                    <label>Dias de la semana</label>
                    <div class="flex gap-2 flex-wrap">
                        <?php foreach ($weekdayNames as $index => $name): ?>
                            <label class="checkbox" style="margin:0">
                                <input type="checkbox" name="weekdays[]" value="<?= $index ?>"
                                       <?= in_array((string) $index, $selectedWeekdays, true) ? 'checked' : '' ?>>
                                <span><?= e($name) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <span class="field__hint">Sin marcar ninguno = todos los dias.</span>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="daily_from">Cada dia desde</label>
                        <input id="daily_from" type="time" name="daily_from"
                               value="<?= e($value('daily_from') !== null ? substr((string) $value('daily_from'), 0, 5) : '') ?>">
                    </div>
                    <div class="field">
                        <label for="daily_to">Cada dia hasta</label>
                        <input id="daily_to" type="time" name="daily_to"
                               value="<?= e($value('daily_to') !== null ? substr((string) $value('daily_to'), 0, 5) : '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <h3>A quien</h3>

                <div class="field">
                    <label for="audience">Publico</label>
                    <select id="audience" name="audience">
                        <?php foreach ([
                            'all' => 'Todo el mundo',
                            'guests' => 'Visitantes sin cuenta',
                            'clients' => 'Clientes registrados',
                            'new_clients' => 'Clientes nuevos (sin visitas)',
                            'inactive_clients' => 'Clientes inactivos',
                        ] as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= (string) $value('audience', 'all') === $key ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="device_target">Dispositivo</label>
                    <select id="device_target" name="device_target">
                        <?php foreach ([
                            'all' => 'Todos', 'desktop' => 'Solo computadora',
                            'mobile' => 'Solo navegador movil', 'app' => 'Solo app movil',
                        ] as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= (string) $value('device_target', 'all') === $key ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="card">
                <h3>Frecuencia</h3>
                <p class="text-muted text-small">Evita que el mismo anuncio moleste al visitante.</p>

                <div class="field">
                    <label for="max_views_per_user">Vistas maximas por persona</label>
                    <input id="max_views_per_user" type="number" name="max_views_per_user" min="0" max="999"
                           value="<?= e((string) $value('max_views_per_user', 0)) ?>">
                    <span class="field__hint">0 = sin limite</span>
                </div>

                <div class="field">
                    <label for="cooldown_hours">Horas entre apariciones</label>
                    <input id="cooldown_hours" type="number" name="cooldown_hours" min="0" max="8760"
                           value="<?= e((string) $value('cooldown_hours', 24)) ?>">
                </div>

                <div class="field">
                    <label for="delay_seconds">Retraso antes de aparecer (s)</label>
                    <input id="delay_seconds" type="number" name="delay_seconds" min="0" max="600"
                           value="<?= e((string) $value('delay_seconds', 0)) ?>">
                </div>

                <div class="field">
                    <label for="auto_close_seconds">Cerrarse solo tras (s)</label>
                    <input id="auto_close_seconds" type="number" name="auto_close_seconds" min="0" max="120"
                           value="<?= e((string) $value('auto_close_seconds', 0)) ?>">
                    <span class="field__hint">0 = no se cierra solo</span>
                </div>

                <div class="field">
                    <label for="priority">Prioridad</label>
                    <input id="priority" type="number" name="priority" min="-100" max="100"
                           value="<?= e((string) $value('priority', 0)) ?>">
                    <span class="field__hint">Si varios compiten, gana el de mayor valor.</span>
                </div>

                <label class="checkbox">
                    <input type="checkbox" name="is_dismissible" value="1"
                           <?= $banner === null || (bool) $value('is_dismissible') ? 'checked' : '' ?>>
                    <span>El visitante puede cerrarlo</span>
                </label>

                <label class="checkbox">
                    <input type="checkbox" name="is_active" value="1"
                           <?= $banner === null || (bool) $value('is_active') ? 'checked' : '' ?>>
                    <span>Anuncio activo</span>
                </label>

                <button type="submit" class="btn btn--primary btn--block mt-2">
                    <?= $id > 0 ? 'Guardar anuncio' : 'Crear anuncio' ?>
                </button>
            </div>

            <?php if ($stats !== null): ?>
                <div class="card">
                    <h3>Metricas (30 dias)</h3>
                    <div class="switch-row">
                        <div class="switch-row__text"><strong>Vistas</strong></div>
                        <strong><?= (int) $stats['impressions'] ?></strong>
                    </div>
                    <div class="switch-row">
                        <div class="switch-row__text"><strong>Clics</strong></div>
                        <strong><?= (int) $stats['clicks'] ?></strong>
                    </div>
                    <div class="switch-row">
                        <div class="switch-row__text"><strong>Cierres</strong></div>
                        <strong><?= (int) $stats['dismissals'] ?></strong>
                    </div>
                    <div class="switch-row">
                        <div class="switch-row__text"><strong>Efectividad</strong></div>
                        <strong><?= e((string) $stats['ctr']) ?>%</strong>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>
<?php View::stop(); ?>
