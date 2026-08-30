<?php
/**
 * @var array<string,mixed> $user
 * @var list<array<string,mixed>> $loyaltyHistory
 */

use App\Core\View;
use App\Services\SettingsService;

View::extend('layouts.public');
?>
<?php View::start('title'); ?>Mi perfil<?php View::stop(); ?>

<?php View::start('content'); ?>
<section class="section">
    <div class="container" style="max-width:820px">
        <h1>Mi perfil</h1>

        <div class="card" style="padding:26px">
            <h2 style="font-size:1.15rem">Datos personales</h2>

            <form method="post" action="<?= e(url('/mi-perfil')) ?>" enctype="multipart/form-data" class="form-grid mt-2">
                <?= csrf_field() ?>

                <div class="flex items-center gap-2 mb-2">
                    <?php if ((string) $user['avatar_path'] !== ''): ?>
                        <img src="<?= e(media_url((string) $user['avatar_path'])) ?>" alt=""
                             style="width:72px;height:72px;border-radius:50%;object-fit:cover">
                    <?php else: ?>
                        <div class="staff-card__photo" style="width:72px;height:72px;margin:0;font-size:1.4rem">
                            <?= e(initials((string) $user['first_name'] . ' ' . (string) $user['last_name'])) ?>
                        </div>
                    <?php endif; ?>
                    <div class="field" style="flex:1">
                        <label for="avatar">Foto de perfil</label>
                        <input id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp">
                    </div>
                </div>

                <div class="form-grid form-grid--2">
                    <div class="field">
                        <label for="p-first">Nombre</label>
                        <input id="p-first" type="text" name="first_name" required maxlength="80"
                               value="<?= e($user['first_name']) ?>">
                        <?= field_error('first_name') ?>
                    </div>
                    <div class="field">
                        <label for="p-last">Apellido</label>
                        <input id="p-last" type="text" name="last_name" maxlength="80"
                               value="<?= e($user['last_name']) ?>">
                    </div>
                </div>

                <div class="form-grid form-grid--2">
                    <div class="field">
                        <label for="p-phone">Telefono</label>
                        <input id="p-phone" type="tel" name="phone" required value="<?= e($user['phone']) ?>">
                        <?= field_error('phone') ?>
                    </div>
                    <div class="field">
                        <label for="p-birth">Fecha de nacimiento</label>
                        <input id="p-birth" type="date" name="birth_date" value="<?= e($user['birth_date'] ?? '') ?>">
                        <span class="field__hint">Para enviarte un detalle en tu cumpleanos.</span>
                    </div>
                </div>

                <div class="field">
                    <label>Correo electronico</label>
                    <input type="email" value="<?= e($user['email']) ?>" disabled>
                    <span class="field__hint">Escribenos si necesitas cambiarlo.</span>
                </div>

                <fieldset style="border:1px solid var(--color-border);border-radius:var(--radius-sm);padding:16px">
                    <legend class="text-small text-muted">Como quieres que te contactemos</legend>

                    <label class="checkbox mb-2">
                        <input type="checkbox" name="accepts_marketing" value="1"
                               <?= (bool) $user['accepts_marketing'] ? 'checked' : '' ?>>
                        <span>Quiero recibir promociones y novedades</span>
                    </label>
                    <label class="checkbox mb-2">
                        <input type="checkbox" name="accepts_email" value="1"
                               <?= (bool) $user['accepts_email'] ? 'checked' : '' ?>>
                        <span>Avisos por correo electronico</span>
                    </label>
                    <label class="checkbox mb-2">
                        <input type="checkbox" name="accepts_push" value="1"
                               <?= (bool) $user['accepts_push'] ? 'checked' : '' ?>>
                        <span>Notificaciones en la app</span>
                    </label>
                    <label class="checkbox">
                        <input type="checkbox" name="accepts_whatsapp" value="1"
                               <?= (bool) $user['accepts_whatsapp'] ? 'checked' : '' ?>>
                        <span>Mensajes por WhatsApp</span>
                    </label>
                </fieldset>

                <button type="submit" class="btn btn--primary">Guardar cambios</button>
            </form>
        </div>

        <?php if (SettingsService::bool('loyalty.enabled', true)): ?>
            <div class="card mt-3" style="padding:26px">
                <h2 style="font-size:1.15rem">Mis puntos</h2>
                <p style="font-size:2rem;font-weight:700;color:var(--color-primary);margin:0">
                    <?= (int) $user['loyalty_points'] ?>
                </p>
                <p class="text-muted text-small">
                    Equivalen a <?= e(money(\App\Services\LoyaltyService::pointsToMoney((int) $user['loyalty_points']))) ?>
                    en tu proxima visita.
                </p>

                <?php if ($loyaltyHistory !== []): ?>
                    <div class="table-wrap mt-2">
                        <table class="data">
                            <thead>
                                <tr><th>Fecha</th><th>Motivo</th><th>Puntos</th><th>Saldo</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($loyaltyHistory as $entry): ?>
                                    <tr>
                                        <td><?= e(local_datetime((string) $entry['created_at'], 'd/m/Y')) ?></td>
                                        <td><?= e($entry['reason']) ?></td>
                                        <td style="color:<?= (int) $entry['points'] >= 0 ? 'var(--color-success)' : 'var(--color-danger)' ?>">
                                            <?= (int) $entry['points'] >= 0 ? '+' : '' ?><?= (int) $entry['points'] ?>
                                        </td>
                                        <td><?= (int) $entry['balance_after'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card mt-3" style="padding:26px">
            <h2 style="font-size:1.15rem">Cambiar contrasena</h2>

            <form method="post" action="<?= e(url('/mi-perfil/clave')) ?>" class="form-grid mt-2">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="cp-current">Contrasena actual</label>
                    <input id="cp-current" type="password" name="current_password" required autocomplete="current-password">
                </div>

                <div class="form-grid form-grid--2">
                    <div class="field">
                        <label for="cp-new">Nueva contrasena</label>
                        <input id="cp-new" type="password" name="password" required autocomplete="new-password"
                               minlength="<?= (int) config('security.password.min_length', 10) ?>">
                        <?= field_error('password') ?>
                    </div>
                    <div class="field">
                        <label for="cp-new2">Repetir contrasena</label>
                        <input id="cp-new2" type="password" name="password_confirmation" required autocomplete="new-password">
                    </div>
                </div>

                <button type="submit" class="btn btn--primary">Actualizar contrasena</button>
            </form>
        </div>

        <div class="card mt-3" style="padding:26px;border-color:rgba(239,68,68,.3)">
            <h2 style="font-size:1.15rem;color:var(--color-danger)">Eliminar mi cuenta</h2>
            <p class="text-muted text-small">
                Se eliminaran tus datos personales, tu foto y tus comprobantes de forma definitiva.
                Tu historial de citas se conserva de forma anonima por motivos contables.
                Esta accion no se puede deshacer.
            </p>

            <form method="post" action="<?= e(url('/mi-perfil/eliminar')) ?>" class="form-grid mt-2"
                  data-confirm="Esta accion es irreversible. Continuar?">
                <?= csrf_field() ?>

                <div class="form-grid form-grid--2">
                    <div class="field">
                        <label for="del-password">Tu contrasena</label>
                        <input id="del-password" type="password" name="password" required autocomplete="current-password">
                    </div>
                    <div class="field">
                        <label for="del-confirm">Escribe ELIMINAR para confirmar</label>
                        <input id="del-confirm" type="text" name="confirm" required placeholder="ELIMINAR">
                    </div>
                </div>

                <button type="submit" class="btn btn--ghost" style="color:var(--color-danger);border-color:var(--color-danger)">
                    Eliminar mi cuenta definitivamente
                </button>
            </form>
        </div>
    </div>
</section>
<?php View::stop(); ?>
