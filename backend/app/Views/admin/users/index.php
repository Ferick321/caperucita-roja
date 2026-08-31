<?php
/**
 * @var array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int} $result
 * @var array<string,mixed> $filters
 * @var array<string,string> $roles
 * @var int $yoId
 * @var string $miRol
 */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Usuarios del panel<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--primary btn--sm" href="<?= e(url('/panel/usuarios/nuevo')) ?>">Nuevo usuario</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="help-box">
    Quien puede entrar a administrar el sistema y hasta donde llega.
    <strong>Recepcion</strong> maneja el dia a dia (citas, pagos, clientes);
    <strong>Profesional</strong> solo ve su propia agenda;
    <strong>Administrador</strong> puede con todo.
</div>

<form method="get" class="filters mb-3">
    <input type="search" name="q" placeholder="Buscar por nombre o correo" value="<?= e((string) $filters['q']) ?>">
    <button type="submit" class="btn btn--ghost btn--sm">Buscar</button>
</form>

<div class="card card--flush">
    <?php if ($result['data'] === []): ?>
        <div class="empty-state"><p>No hay usuarios con ese filtro.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Ultimo acceso</th><th>Estado</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($result['data'] as $usuario): ?>
                        <?php
                        $esYo = (int) $usuario['id'] === $yoId;
                        $esSuper = (string) $usuario['role'] === 'super_admin';
                        // Un administrador no puede tocar a un super administrador.
                        $puedoTocar = $miRol === 'super_admin' || !$esSuper;
                        ?>
                        <tr>
                            <td>
                                <strong><?= e(trim((string) $usuario['first_name'] . ' ' . (string) $usuario['last_name'])) ?></strong>
                                <?php if ($esYo): ?>
                                    <span class="pill">Eres tu</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-small"><?= e((string) $usuario['email']) ?></td>
                            <td class="text-small"><?= e($roles[(string) $usuario['role']] ?? (string) $usuario['role']) ?></td>
                            <td class="text-small text-muted">
                                <?= $usuario['last_login_at'] !== null
                                    ? e(local_datetime((string) $usuario['last_login_at']))
                                    : 'Nunca entro' ?>
                            </td>
                            <td>
                                <?php if ((string) $usuario['status'] === 'active'): ?>
                                    <span class="pill pill--success">Activo</span>
                                <?php else: ?>
                                    <span class="pill pill--danger">Suspendido</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <?php if ($puedoTocar): ?>
                                        <a class="btn btn--ghost btn--sm"
                                           href="<?= e(url('/panel/usuarios/' . (int) $usuario['id'] . '/editar')) ?>">Editar</a>

                                        <?php if (!$esYo): ?>
                                            <form method="post"
                                                  action="<?= e(url('/panel/usuarios/' . (int) $usuario['id'] . '/estado')) ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn--ghost btn--sm">
                                                    <?= (string) $usuario['status'] === 'active' ? 'Suspender' : 'Reactivar' ?>
                                                </button>
                                            </form>
                                            <form method="post"
                                                  action="<?= e(url('/panel/usuarios/' . (int) $usuario['id'] . '/eliminar')) ?>"
                                                  data-confirm="Esta persona dejara de tener acceso al panel. Continuar?">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn--danger btn--sm">Eliminar</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-small text-muted">Solo un super administrador puede cambiarlo</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?= View::partial('partials.pagination', [
    'result' => $result, 'baseUrl' => url('/panel/usuarios'), 'query' => $filters,
]) ?>
<?php View::stop(); ?>
