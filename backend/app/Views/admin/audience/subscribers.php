<?php
/**
 * @var array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int} $result
 * @var array<string,mixed> $filters
 * @var array<string,int> $stats
 */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Suscriptores<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/suscriptores/exportar')) ?>">Descargar CSV</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="help-box">
    Quien acepto recibir tu publicidad, desde la web, la app o apuntado a mano en el local.
    Esta es la lista a la que llegan tus campanas.
</div>

<div class="grid grid--4 mb-3">
    <div class="stat stat--primary">
        <p class="stat__label">Reciben publicidad</p>
        <p class="stat__value"><?= (int) $stats['activos'] ?></p>
        <p class="stat__meta">de <?= (int) $stats['total'] ?> en total</p>
    </div>
    <div class="stat stat--success">
        <p class="stat__label">Confirmados</p>
        <p class="stat__value"><?= (int) $stats['confirmados'] ?></p>
        <p class="stat__meta">Verificaron su correo</p>
    </div>
    <div class="stat">
        <p class="stat__label">Sin confirmar</p>
        <p class="stat__value"><?= (int) $stats['activos'] - (int) $stats['confirmados'] ?></p>
        <p class="stat__meta">Aun no verifican</p>
    </div>
    <div class="stat stat--warning">
        <p class="stat__label">Se dieron de baja</p>
        <p class="stat__value"><?= (int) $stats['bajas'] ?></p>
        <p class="stat__meta">No se les escribe</p>
    </div>
</div>

<div class="grid grid--sidebar">
    <div>
        <form method="get" class="filters mb-3">
            <input type="search" name="q" placeholder="Buscar correo o nombre" value="<?= e((string) $filters['q']) ?>">
            <select name="estado">
                <option value="">Todos</option>
                <option value="activos" <?= $filters['estado'] === 'activos' ? 'selected' : '' ?>>Reciben publicidad</option>
                <option value="sin_confirmar" <?= $filters['estado'] === 'sin_confirmar' ? 'selected' : '' ?>>Sin confirmar</option>
                <option value="bajas" <?= $filters['estado'] === 'bajas' ? 'selected' : '' ?>>Dados de baja</option>
            </select>
            <button type="submit" class="btn btn--ghost btn--sm">Filtrar</button>
        </form>

        <div class="card card--flush">
            <?php if ($result['data'] === []): ?>
                <div class="empty-state"><p>No hay suscriptores con ese filtro.</p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr><th>Correo</th><th>Nombre</th><th>Origen</th><th>Alta</th><th>Estado</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['data'] as $sub): ?>
                                <tr>
                                    <td class="text-small"><?= e((string) $sub['email']) ?></td>
                                    <td class="text-small">
                                        <?= (string) $sub['name'] !== '' ? e((string) $sub['name']) : '<span class="text-muted">&mdash;</span>' ?>
                                    </td>
                                    <td class="text-small text-muted"><?= e((string) $sub['source']) ?></td>
                                    <td class="text-small text-muted">
                                        <?= e(local_datetime((string) $sub['created_at'], 'd/m/Y')) ?>
                                    </td>
                                    <td>
                                        <?php if ($sub['unsubscribed_at'] !== null): ?>
                                            <span class="pill pill--danger">De baja</span>
                                        <?php elseif ((bool) $sub['is_confirmed']): ?>
                                            <span class="pill pill--success">Confirmado</span>
                                        <?php else: ?>
                                            <span class="pill pill--warning">Sin confirmar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <?php if ($sub['unsubscribed_at'] === null): ?>
                                                <form method="post"
                                                      action="<?= e(url('/panel/suscriptores/' . (int) $sub['id'] . '/baja')) ?>"
                                                      data-confirm="Dejara de recibir publicidad. Continuar?">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn--ghost btn--sm">Dar de baja</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="post"
                                                  action="<?= e(url('/panel/suscriptores/' . (int) $sub['id'] . '/eliminar')) ?>"
                                                  data-confirm="Se borrara de la base de datos y no se puede recuperar. Continuar?">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn--danger btn--sm">Borrar</button>
                                            </form>
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
            'result' => $result, 'baseUrl' => url('/panel/suscriptores'), 'query' => $filters,
        ]) ?>
    </div>

    <div>
        <div class="card">
            <h2>Anadir a mano</h2>
            <p class="text-muted text-small">
                Para el cliente que te deja su correo en el local.
                Asegurate de que te dio permiso para escribirle.
            </p>

            <form method="post" action="<?= e(url('/panel/suscriptores')) ?>">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="sub-email">Correo</label>
                    <input id="sub-email" type="email" name="email" required maxlength="190">
                    <?= field_error('email') ?>
                </div>

                <div class="field">
                    <label for="sub-name">Nombre</label>
                    <input id="sub-name" type="text" name="name" maxlength="120">
                    <?= field_error('name') ?>
                </div>

                <div class="field">
                    <label for="sub-phone">Telefono</label>
                    <input id="sub-phone" type="text" name="phone" maxlength="20">
                    <?= field_error('phone') ?>
                </div>

                <button type="submit" class="btn btn--primary btn--block">Anadir</button>
            </form>
        </div>
    </div>
</div>
<?php View::stop(); ?>
