<?php
/**
 * @var array<string,mixed>|null $user
 * @var array<string,string> $roles
 */

use App\Core\View;

View::extend('layouts.admin');

$esNuevo = $user === null;
$accion = $esNuevo ? '/panel/usuarios' : '/panel/usuarios/' . (int) $user['id'];
?>
<?php View::start('title'); ?><?= $esNuevo ? 'Nuevo usuario' : e((string) $user['first_name']) ?><?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/usuarios')) ?>">&larr; Usuarios</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<?php if ($roles === []): ?>
    <div class="help-box">
        Tu rol no permite crear ni cambiar usuarios del panel.
    </div>
<?php else: ?>
    <form method="post" action="<?= e(url($accion)) ?>">
        <?= csrf_field() ?>

        <div class="card">
            <h2>Datos de la cuenta</h2>

            <div class="grid grid--2">
                <div class="field">
                    <label for="first_name">Nombre</label>
                    <input type="text" id="first_name" name="first_name" required maxlength="80"
                           value="<?= e(old('first_name', $user['first_name'] ?? '')) ?>">
                    <?= field_error('first_name') ?>
                </div>
                <div class="field">
                    <label for="last_name">Apellido</label>
                    <input type="text" id="last_name" name="last_name" maxlength="80"
                           value="<?= e(old('last_name', $user['last_name'] ?? '')) ?>">
                    <?= field_error('last_name') ?>
                </div>
            </div>

            <div class="grid grid--2">
                <div class="field">
                    <label for="email">Correo</label>
                    <input type="email" id="email" name="email" required maxlength="190"
                           value="<?= e(old('email', $user['email'] ?? '')) ?>">
                    <p class="field__help">Con este correo entra al panel.</p>
                    <?= field_error('email') ?>
                </div>
                <div class="field">
                    <label for="phone">Telefono</label>
                    <input type="text" id="phone" name="phone" maxlength="20"
                           value="<?= e(old('phone', $user['phone'] ?? '')) ?>">
                    <?= field_error('phone') ?>
                </div>
            </div>

            <div class="field">
                <label for="role">Rol</label>
                <select id="role" name="role" required>
                    <?php $rolActual = (string) old('role', $user['role'] ?? 'manager'); ?>
                    <?php foreach ($roles as $clave => $texto): ?>
                        <option value="<?= e($clave) ?>" <?= $rolActual === $clave ? 'selected' : '' ?>>
                            <?= e($texto) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field__help">Solo puedes asignar roles iguales o menores al tuyo.</p>
                <?= field_error('role') ?>
            </div>

            <div class="field">
                <label for="password">
                    <?= $esNuevo ? 'Contrasena' : 'Contrasena nueva' ?>
                    <?= $esNuevo ? '' : '<span class="text-muted">(dejala vacia para no cambiarla)</span>' ?>
                </label>
                <input type="password" id="password" name="password" autocomplete="new-password"
                       <?= $esNuevo ? 'required' : '' ?>>
                <p class="field__help">
                    Minimo 10 caracteres, con mayusculas, minusculas, numeros y algun simbolo.
                    <?= $esNuevo ? '' : 'Si la cambias, se cerraran sus sesiones abiertas.' ?>
                </p>
                <?= field_error('password') ?>
            </div>

            <label class="checkbox">
                <input type="checkbox" name="is_active" value="1"
                    <?= old('is_active', ($user['status'] ?? 'active') === 'active') ? 'checked' : '' ?>>
                <span>Puede entrar al panel</span>
            </label>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary">
                    <?= $esNuevo ? 'Crear usuario' : 'Guardar cambios' ?>
                </button>
                <a class="btn btn--ghost" href="<?= e(url('/panel/usuarios')) ?>">Cancelar</a>
            </div>
        </div>
    </form>
<?php endif; ?>
<?php View::stop(); ?>
