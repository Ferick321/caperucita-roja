<?php
/**
 * @var array<string,mixed>|null $campaign
 * @var array<string,int> $audienceSizes
 * @var list<array<string,mixed>> $recipients
 */

use App\Core\View;
use App\Security\Auth;

View::extend('layouts.admin');

$id = $campaign === null ? 0 : (int) $campaign['id'];
$action = $id > 0 ? url('/panel/campanas/' . $id) : url('/panel/campanas');
$value = static fn (string $key, mixed $default = ''): mixed => $campaign[$key] ?? $default;
$status = (string) $value('status', 'draft');
$isEditable = !in_array($status, ['sending', 'sent'], true);
?>
<?php View::start('title'); ?><?= $id > 0 ? 'Campana' : 'Nueva campana' ?><?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/campanas')) ?>">&larr; Campanas</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="grid grid--sidebar">
    <div>
        <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="card">
                <h2>Mensaje</h2>

                <div class="field">
                    <label for="name">Nombre de la campana *</label>
                    <input id="name" type="text" name="name" required maxlength="160"
                           value="<?= e($value('name', old('name'))) ?>" <?= $isEditable ? '' : 'disabled' ?>>
                    <?= field_error('name') ?>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="channel">Canal *</label>
                        <select id="channel" name="channel" required <?= $isEditable ? '' : 'disabled' ?>>
                            <?php foreach ([
                                'email' => 'Correo electronico',
                                'push' => 'Notificacion en la app',
                                'sms' => 'SMS (requiere proveedor)',
                                'whatsapp' => 'WhatsApp (requiere proveedor)',
                            ] as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= (string) $value('channel', 'email') === $key ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="audience">Publico *</label>
                        <select id="audience" name="audience" required <?= $isEditable ? '' : 'disabled' ?>>
                            <?php foreach ([
                                'all' => 'Todos los que aceptan publicidad',
                                'new_clients' => 'Clientes nuevos',
                                'inactive_clients' => 'Clientes inactivos',
                                'frequent_clients' => 'Clientes frecuentes',
                                'birthday' => 'Cumpleaneros de hoy',
                            ] as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= (string) $value('audience', 'all') === $key ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                    <?php if (isset($audienceSizes[$key])): ?>
                                        (<?= (int) $audienceSizes[$key] ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="field">
                    <label for="inactive_days">Dias sin visitar para considerar inactivo</label>
                    <input id="inactive_days" type="number" name="inactive_days" min="1" max="3650"
                           value="<?= e((string) $value('inactive_days', 60)) ?>" <?= $isEditable ? '' : 'disabled' ?>>
                </div>

                <div class="field">
                    <label for="subject">Asunto</label>
                    <input id="subject" type="text" name="subject" maxlength="200"
                           value="<?= e($value('subject')) ?>" <?= $isEditable ? '' : 'disabled' ?>>
                </div>

                <div class="field">
                    <label for="body">Mensaje *</label>
                    <textarea id="body" name="body" rows="9" required <?= $isEditable ? '' : 'disabled' ?>><?= e($value('body')) ?></textarea>
                    <span class="field__hint">
                        Variables: <span class="mono">{cliente}</span>, <span class="mono">{negocio}</span>,
                        <span class="mono">{url_sitio}</span>, <span class="mono">{url_app}</span>.
                        El enlace de baja se anade automaticamente.
                    </span>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="cta_label">Texto del boton</label>
                        <input id="cta_label" type="text" name="cta_label" maxlength="80"
                               value="<?= e($value('cta_label')) ?>" <?= $isEditable ? '' : 'disabled' ?>>
                    </div>
                    <div class="field">
                        <label for="cta_url">Enlace del boton</label>
                        <input id="cta_url" type="url" name="cta_url" maxlength="500"
                               value="<?= e($value('cta_url')) ?>" <?= $isEditable ? '' : 'disabled' ?>>
                    </div>
                </div>

                <div class="field">
                    <label for="image">Imagen destacada</label>
                    <?php if ((string) $value('image_path') !== ''): ?>
                        <img src="<?= e(media_url((string) $value('image_path'))) ?>" alt=""
                             style="max-height:120px;border-radius:8px;margin-bottom:8px">
                    <?php endif; ?>
                    <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp"
                           <?= $isEditable ? '' : 'disabled' ?>>
                </div>

                <div class="field">
                    <label for="scheduled_at">Programar envio (opcional)</label>
                    <input id="scheduled_at" type="datetime-local" name="scheduled_at"
                           value="<?= e($value('scheduled_at') !== null && $value('scheduled_at') !== ''
                               ? local_datetime((string) $value('scheduled_at'), 'Y-m-d\TH:i') : '') ?>"
                           <?= $isEditable ? '' : 'disabled' ?>>
                    <span class="field__hint">Si lo dejas vacio, la campana queda como borrador.</span>
                </div>

                <?php if ($isEditable): ?>
                    <button type="submit" class="btn btn--primary">
                        <?= $id > 0 ? 'Guardar y recalcular publico' : 'Crear campana' ?>
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div>
        <div class="card">
            <h3>Estado</h3>
            <div class="switch-row">
                <div class="switch-row__text"><strong>Situacion</strong></div>
                <span class="pill pill--<?= e(match ($status) {
                    'sent' => 'success', 'sending' => 'info', 'scheduled' => 'warning',
                    'cancelled' => 'danger', default => '',
                }) ?>"><?= e($status) ?></span>
            </div>
            <div class="switch-row">
                <div class="switch-row__text"><strong>Destinatarios</strong></div>
                <strong><?= (int) $value('total_recipients', 0) ?></strong>
            </div>
            <div class="switch-row">
                <div class="switch-row__text"><strong>Enviados</strong></div>
                <strong><?= (int) $value('total_sent', 0) ?></strong>
            </div>
            <div class="switch-row">
                <div class="switch-row__text"><strong>Aperturas</strong></div>
                <strong><?= (int) $value('total_opened', 0) ?></strong>
            </div>
        </div>

        <?php if ($id > 0 && $isEditable && Auth::can('campanas.enviar')): ?>
            <div class="card">
                <h3>Enviar ahora</h3>
                <p class="text-muted text-small">
                    Los mensajes entran en cola y salen progresivamente. Esta accion no se puede deshacer.
                </p>

                <form method="post" action="<?= e(url('/panel/campanas/' . $id . '/enviar')) ?>">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="confirm">Escribe ENVIAR para confirmar</label>
                        <input id="confirm" type="text" name="confirm" placeholder="ENVIAR" required>
                    </div>
                    <button type="submit" class="btn btn--primary btn--block">Enviar campana</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($id > 0 && Auth::can('campanas.enviar')): ?>
            <div class="card card--danger">
                <h3>Acciones</h3>
                <?php if (in_array($status, ['scheduled', 'sending'], true)): ?>
                    <form method="post" data-confirm="Cancelar el envio pendiente?"
                          action="<?= e(url('/panel/campanas/' . $id . '/cancelar')) ?>" class="mb-1">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--ghost btn--sm btn--block">Cancelar envio</button>
                    </form>
                <?php endif; ?>

                <form method="post" data-confirm="Eliminar esta campana?"
                      action="<?= e(url('/panel/campanas/' . $id . '/eliminar')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--danger btn--sm btn--block">Eliminar campana</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($recipients !== []): ?>
            <div class="card">
                <h3>Primeros destinatarios</h3>
                <?php foreach (array_slice($recipients, 0, 12) as $recipient): ?>
                    <div class="switch-row">
                        <div class="switch-row__text">
                            <strong><?= e(str_limit((string) $recipient['destination'], 30)) ?></strong>
                        </div>
                        <span class="pill"><?= e($recipient['status']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php View::stop(); ?>
