<?php
/** @var list<array<string,mixed>> $staff */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Equipo<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--primary btn--sm" href="<?= e(url('/panel/personal/nuevo')) ?>">+ Nuevo profesional</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="help-box">
    Cada profesional tiene su propia agenda. Sin horario configurado no aparecen huecos libres,
    asi que recuerda definir su jornada tras crearlo.
</div>

<?php if ($staff === []): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state__icon">&#128100;</div>
            <p>Aun no hay profesionales registrados.</p>
            <a class="btn btn--primary" href="<?= e(url('/panel/personal/nuevo')) ?>">Registrar al primero</a>
        </div>
    </div>
<?php else: ?>
    <div class="grid grid--auto">
        <?php foreach ($staff as $member): ?>
            <div class="card">
                <div class="flex gap-2 items-center mb-2">
                    <?php if ((string) $member['photo_path'] !== ''): ?>
                        <img src="<?= e(media_url((string) $member['photo_path'])) ?>" alt=""
                             style="width:58px;height:58px;border-radius:50%;object-fit:cover">
                    <?php else: ?>
                        <div style="width:58px;height:58px;border-radius:50%;display:grid;place-items:center;
                                    background: <?= e($member['color']) ?>22; color: <?= e($member['color']) ?>;
                                    font-weight:700;font-size:1.15rem">
                            <?= e(initials((string) $member['display_name'])) ?>
                        </div>
                    <?php endif; ?>

                    <div style="flex:1;min-width:0">
                        <strong><?= e($member['display_name']) ?></strong>
                        <div class="text-small text-muted"><?= e($member['title']) ?></div>
                        <div class="text-small text-muted"><?= e($member['branch_name'] ?? '') ?></div>
                    </div>
                </div>

                <div class="flex gap-1 flex-wrap mb-2">
                    <?php if ((bool) $member['is_active']): ?>
                        <span class="pill pill--success">Activo</span>
                    <?php else: ?>
                        <span class="pill pill--danger">Inactivo</span>
                    <?php endif; ?>

                    <?php if (!(bool) $member['accepts_online']): ?>
                        <span class="pill pill--warning">Sin reservas en linea</span>
                    <?php endif; ?>

                    <?php if ($member['user_id'] !== null): ?>
                        <span class="pill pill--info">Con acceso al panel</span>
                    <?php endif; ?>
                </div>

                <div class="switch-row">
                    <div class="switch-row__text">
                        <strong><?= (int) $member['service_count'] ?></strong>
                        <span>servicios asignados</span>
                    </div>
                    <div class="text-right">
                        <strong><?= (int) $member['upcoming'] ?></strong>
                        <div class="text-small text-muted">citas proximas</div>
                    </div>
                </div>

                <?php if ((int) $member['rating_count'] > 0): ?>
                    <p class="text-small mt-1 mb-0" style="color:var(--a-warning)">
                        <?= e(str_repeat('*', (int) round((float) $member['rating_average']))) ?>
                        <span class="text-muted">(<?= (int) $member['rating_count'] ?> opiniones)</span>
                    </p>
                <?php endif; ?>

                <div class="btn-row mt-2">
                    <a class="btn btn--ghost btn--sm"
                       href="<?= e(url('/panel/personal/' . (int) $member['id'] . '/editar')) ?>">Editar y horario</a>
                    <form method="post" data-confirm="Eliminar a este profesional?"
                          action="<?= e(url('/panel/personal/' . (int) $member['id'] . '/eliminar')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--ghost btn--sm">Eliminar</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php View::stop(); ?>
