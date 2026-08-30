<?php
/**
 * @var array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int} $result
 * @var array<string,mixed> $filters
 * @var array<string,int> $counts
 */

use App\Core\View;
use App\Security\Auth;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Clientes<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <?php if (Auth::can('clientes.exportar')): ?>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/clientes/exportar')) ?>">Exportar CSV</a>
    <?php endif; ?>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="grid grid--2 mb-3">
    <div class="stat stat--primary">
        <p class="stat__label">Clientes registrados</p>
        <p class="stat__value"><?= (int) $counts['total'] ?></p>
    </div>
    <div class="stat stat--success">
        <p class="stat__label">Aceptan recibir publicidad</p>
        <p class="stat__value"><?= (int) $counts['marketing'] ?></p>
        <p class="stat__meta">Publico disponible para campanas</p>
    </div>
</div>

<div class="grid grid--sidebar">
    <div>
        <form method="get" action="<?= e(url('/panel/clientes')) ?>" class="card">
            <div class="filters">
                <div class="field">
                    <label for="q">Buscar</label>
                    <input id="q" type="search" name="q" value="<?= e((string) $filters['q']) ?>"
                           placeholder="Nombre, correo o telefono">
                </div>
                <div class="field">
                    <label for="filtro">Segmento</label>
                    <select id="filtro" name="filtro" data-auto-submit>
                        <option value="">Todos</option>
                        <?php foreach ([
                            'nuevos' => 'Nuevos (0-1 visitas)',
                            'frecuentes' => 'Frecuentes (5+)',
                            'inactivos' => 'Inactivos',
                            'marketing' => 'Aceptan publicidad',
                            'bloqueados' => 'Bloqueados',
                        ] as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $filters['filtro'] === $key ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn--primary">Filtrar</button>
            </div>
        </form>

        <div class="card card--flush">
            <?php if ($result['data'] === []): ?>
                <div class="empty-state">
                    <div class="empty-state__icon">&#128101;</div>
                    <p>No hay clientes con esos criterios.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                            <tr><th>Cliente</th><th>Contacto</th><th>Visitas</th>
                                <th class="text-right">Gastado</th><th>Puntos</th><th>Publicidad</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['data'] as $client): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($client['first_name'] . ' ' . $client['last_name']) ?></strong>
                                        <?php if ((string) $client['status'] === 'blocked'): ?>
                                            <span class="pill pill--danger">Bloqueado</span>
                                        <?php endif; ?>
                                        <div class="text-small text-muted">
                                            Alta: <?= e(local_datetime((string) $client['created_at'], 'd/m/Y')) ?>
                                        </div>
                                    </td>
                                    <td class="text-small">
                                        <?php if (!str_ends_with((string) $client['email'], '@local.invalid')): ?>
                                            <div><?= e($client['email']) ?></div>
                                        <?php endif; ?>
                                        <?php if ((string) $client['phone'] !== ''): ?>
                                            <div class="text-muted"><?= e($client['phone']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= (int) $client['total_visits'] ?></td>
                                    <td class="text-right nowrap"><?= e(money((float) $client['total_spent'])) ?></td>
                                    <td><?= (int) $client['loyalty_points'] ?></td>
                                    <td>
                                        <?php if ((bool) $client['accepts_marketing']): ?>
                                            <span class="pill pill--success">Si</span>
                                        <?php else: ?>
                                            <span class="pill">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right">
                                        <a class="btn btn--ghost btn--sm"
                                           href="<?= e(url('/panel/clientes/' . (int) $client['id'])) ?>">Ficha</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?= View::partial('partials.pagination', [
            'result' => $result, 'baseUrl' => url('/panel/clientes'), 'query' => $filters,
        ]) ?>
    </div>

    <?php if (Auth::can('clientes.crear')): ?>
        <div class="card">
            <h3>Registrar un cliente</h3>
            <p class="text-muted text-small">Para altas en el mostrador o por telefono.</p>

            <form method="post" action="<?= e(url('/panel/clientes')) ?>">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="first_name">Nombre *</label>
                    <input id="first_name" type="text" name="first_name" required maxlength="80">
                    <?= field_error('first_name') ?>
                </div>

                <div class="field">
                    <label for="last_name">Apellido</label>
                    <input id="last_name" type="text" name="last_name" maxlength="80">
                </div>

                <div class="field">
                    <label for="phone">Telefono *</label>
                    <input id="phone" type="tel" name="phone" required>
                    <?= field_error('phone') ?>
                </div>

                <div class="field">
                    <label for="email">Correo (opcional)</label>
                    <input id="email" type="email" name="email">
                    <span class="field__hint">Si lo dejas vacio, el cliente no recibira correos.</span>
                </div>

                <label class="checkbox">
                    <input type="checkbox" name="accepts_marketing" value="1">
                    <span>Acepta recibir promociones</span>
                </label>

                <button type="submit" class="btn btn--primary btn--block">Registrar cliente</button>
            </form>
        </div>
    <?php endif; ?>
</div>
<?php View::stop(); ?>
