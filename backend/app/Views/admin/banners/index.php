<?php
/**
 * @var list<array<string,mixed>> $banners
 * @var array<string,string> $placements
 * @var list<array<string,mixed>> $performance
 */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Publicidad<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--primary btn--sm" href="<?= e(url('/panel/publicidad/nuevo')) ?>">+ Nuevo anuncio</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="help-box">
    Crea anuncios y decide donde aparecen: en la portada, como ventana al iniciar sesion,
    mientras el visitante navega, al intentar salir de la web o dentro de la app movil.
    Todo se programa por fechas, dias y horas, con limite de vistas por persona.
</div>

<?php if ($banners === []): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state__icon">&#128226;</div>
            <p>Aun no has creado ningun anuncio.</p>
            <a class="btn btn--primary" href="<?= e(url('/panel/publicidad/nuevo')) ?>">Crear el primero</a>
        </div>
    </div>
<?php else: ?>
    <div class="grid grid--auto">
        <?php foreach ($banners as $banner): ?>
            <div class="card">
                <div class="card__head">
                    <h3 style="margin:0"><?= e($banner['name']) ?></h3>
                    <?php if ((bool) $banner['is_active']): ?>
                        <span class="pill pill--success">Activo</span>
                    <?php else: ?>
                        <span class="pill pill--danger">Pausado</span>
                    <?php endif; ?>
                </div>

                <div style="border-radius:10px;overflow:hidden;margin-bottom:12px;
                            background: <?= e($banner['background_color']) ?>;
                            color: <?= e($banner['text_color']) ?>; padding:16px">
                    <?php if ((string) $banner['image_path'] !== ''): ?>
                        <img src="<?= e(media_url((string) $banner['image_path'])) ?>" alt=""
                             style="border-radius:8px;margin-bottom:10px;max-height:130px;width:100%;object-fit:cover">
                    <?php endif; ?>
                    <strong style="display:block"><?= e($banner['title']) ?></strong>
                    <?php if ((string) $banner['subtitle'] !== ''): ?>
                        <span class="text-small"><?= e($banner['subtitle']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="flex gap-1 flex-wrap mb-2">
                    <?php foreach ($banner['placements'] as $placement): ?>
                        <span class="pill pill--info"><?= e($placements[$placement] ?? $placement) ?></span>
                    <?php endforeach; ?>
                    <?php if ($banner['placements'] === []): ?>
                        <span class="pill pill--warning">Sin ubicacion asignada</span>
                    <?php endif; ?>
                </div>

                <div class="switch-row">
                    <div class="switch-row__text">
                        <strong><?= (int) $banner['stats']['impressions'] ?></strong>
                        <span>vistas (30 dias)</span>
                    </div>
                    <div class="text-right">
                        <strong><?= (int) $banner['stats']['clicks'] ?></strong>
                        <div class="text-small text-muted"><?= e((string) $banner['stats']['ctr']) ?>% de clics</div>
                    </div>
                </div>

                <?php if ($banner['starts_at'] !== null || $banner['ends_at'] !== null): ?>
                    <p class="text-small text-muted mt-1 mb-0">
                        Programado:
                        <?= e($banner['starts_at'] !== null ? local_datetime((string) $banner['starts_at'], 'd/m/Y') : 'desde ya') ?>
                        &rarr;
                        <?= e($banner['ends_at'] !== null ? local_datetime((string) $banner['ends_at'], 'd/m/Y') : 'sin fin') ?>
                    </p>
                <?php endif; ?>

                <div class="btn-row mt-2">
                    <a class="btn btn--ghost btn--sm"
                       href="<?= e(url('/panel/publicidad/' . (int) $banner['id'] . '/editar')) ?>">Editar</a>

                    <form method="post" action="<?= e(url('/panel/publicidad/' . (int) $banner['id'] . '/activar')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--ghost btn--sm">
                            <?= (bool) $banner['is_active'] ? 'Pausar' : 'Activar' ?>
                        </button>
                    </form>

                    <form method="post" data-confirm="Reiniciar las metricas y volver a mostrarlo a todos?"
                          action="<?= e(url('/panel/publicidad/' . (int) $banner['id'] . '/reiniciar')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--ghost btn--sm">Reiniciar vistas</button>
                    </form>

                    <form method="post" data-confirm="Eliminar este anuncio?"
                          action="<?= e(url('/panel/publicidad/' . (int) $banner['id'] . '/eliminar')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn--ghost btn--sm">Eliminar</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($performance !== []): ?>
    <div class="card card--flush mt-3">
        <div class="card__head" style="padding:20px 20px 0"><h2>Rendimiento (30 dias)</h2></div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Anuncio</th><th>Vistas</th><th>Clics</th><th>Cierres</th><th>Efectividad</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($performance as $row): ?>
                        <?php
                        $impressions = (int) ($row['impressions'] ?? 0);
                        $clicks = (int) ($row['clicks'] ?? 0);
                        $ctr = $impressions > 0 ? round($clicks / $impressions * 100, 2) : 0.0;
                        ?>
                        <tr>
                            <td><?= e($row['name']) ?></td>
                            <td><?= e((string) $impressions) ?></td>
                            <td><?= e((string) $clicks) ?></td>
                            <td><?= e((string) (int) ($row['dismissals'] ?? 0)) ?></td>
                            <td>
                                <div class="flex items-center gap-1">
                                    <div class="progress" style="width:80px">
                                        <div class="progress__fill" style="width: <?= e((string) min(100, $ctr * 5)) ?>%"></div>
                                    </div>
                                    <span class="text-small"><?= e((string) $ctr) ?>%</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php View::stop(); ?>
