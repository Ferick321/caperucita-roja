<?php
/** @var list<array<string,mixed>> $branches */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Sucursales<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--primary btn--sm" href="<?= e(url('/panel/sucursales/nueva')) ?>">Nueva sucursal</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="help-box">
    Cada sucursal es un local con su propia direccion y su propio horario.
    <strong>El horario que pongas aqui manda sobre la agenda</strong>: si el local esta
    cerrado, no se ofrece ninguna cita a esa hora aunque el profesional este libre.
</div>

<div class="card card--flush">
    <?php if ($branches === []): ?>
        <div class="empty-state">
            <p>Aun no has creado ninguna sucursal.</p>
            <a class="btn btn--primary" href="<?= e(url('/panel/sucursales/nueva')) ?>">Crear la primera</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Sucursal</th>
                        <th>Contacto</th>
                        <th class="text-right">Equipo</th>
                        <th class="text-right">Dias abierto</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($branches as $sucursal): ?>
                        <tr>
                            <td>
                                <strong><?= e((string) $sucursal['name']) ?></strong>
                                <?php if ((bool) $sucursal['is_default']): ?>
                                    <span class="pill pill--success">Principal</span>
                                <?php endif; ?>
                                <?php if ((string) $sucursal['address'] !== ''): ?>
                                    <div class="text-small text-muted mt-1">
                                        <?= e((string) $sucursal['address']) ?>
                                        <?= (string) $sucursal['city'] !== '' ? ', ' . e((string) $sucursal['city']) : '' ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-small text-muted">
                                <?php if ((string) $sucursal['phone'] !== ''): ?>
                                    <div><?= e((string) $sucursal['phone']) ?></div>
                                <?php endif; ?>
                                <?php if ((string) $sucursal['email'] !== ''): ?>
                                    <div><?= e((string) $sucursal['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-right"><?= (int) $sucursal['staff_count'] ?></td>
                            <td class="text-right">
                                <?php if ((int) $sucursal['open_days'] === 0): ?>
                                    <span class="pill pill--danger">Sin horario</span>
                                <?php else: ?>
                                    <?= (int) $sucursal['open_days'] ?> de 7
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((bool) $sucursal['is_active']): ?>
                                    <span class="pill pill--success">Abierta</span>
                                <?php else: ?>
                                    <span class="pill pill--danger">Cerrada</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <a class="btn btn--ghost btn--sm"
                                       href="<?= e(url('/panel/sucursales/' . (int) $sucursal['id'] . '/editar')) ?>">
                                        Editar
                                    </a>
                                    <?php if (!(bool) $sucursal['is_default']): ?>
                                        <form method="post"
                                              action="<?= e(url('/panel/sucursales/' . (int) $sucursal['id'] . '/principal')) ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn--ghost btn--sm">Hacer principal</button>
                                        </form>
                                        <form method="post"
                                              action="<?= e(url('/panel/sucursales/' . (int) $sucursal['id'] . '/eliminar')) ?>"
                                              data-confirm="Se eliminara la sucursal. Continuar?">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn--danger btn--sm">Eliminar</button>
                                        </form>
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
<?php View::stop(); ?>
