<?php
/**
 * @var array{data:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int} $result
 * @var list<array<string,mixed>> $categories
 * @var array<string,mixed> $filters
 */

use App\Core\View;
use App\Services\BookingService;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Servicios<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/servicios/categorias')) ?>">Categorias</a>
    <a class="btn btn--primary btn--sm" href="<?= e(url('/panel/servicios/nuevo')) ?>">+ Nuevo servicio</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="help-box">
    Todo lo que ves aqui aparece en la web y en la app movil. Cambia precios, duraciones o
    fotos y se actualiza al instante para tus clientes.
</div>

<form method="get" action="<?= e(url('/panel/servicios')) ?>" class="card">
    <div class="filters">
        <div class="field">
            <label for="q">Buscar</label>
            <input id="q" type="search" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Nombre del servicio">
        </div>
        <div class="field">
            <label for="cat">Categoria</label>
            <select id="cat" name="categoria" data-auto-submit>
                <option value="">Todas</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"
                            <?= (int) $filters['categoria'] === (int) $category['id'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
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
            <div class="empty-state__icon">&#9986;</div>
            <p>Aun no hay servicios que coincidan.</p>
            <a class="btn btn--primary" href="<?= e(url('/panel/servicios/nuevo')) ?>">Crear el primero</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th></th><th>Servicio</th><th>Categoria</th><th>Duracion</th>
                        <th class="text-right">Precio</th><th>Estado</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['data'] as $service): ?>
                        <?php $price = BookingService::currentPrice($service); ?>
                        <tr>
                            <td style="width:56px">
                                <?php if ((string) $service['image_path'] !== ''): ?>
                                    <img src="<?= e(media_url((string) $service['image_path'])) ?>" alt=""
                                         style="width:44px;height:44px;object-fit:cover;border-radius:8px">
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= e($service['name']) ?></strong>
                                <?php if ((bool) $service['is_featured']): ?>
                                    <span class="pill pill--primary">Destacado</span>
                                <?php endif; ?>
                                <?php if ((string) $service['short_description'] !== ''): ?>
                                    <div class="text-small text-muted"><?= e(str_limit((string) $service['short_description'], 70)) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="pill" style="background: <?= e($service['category_color']) ?>22; color: <?= e($service['category_color']) ?>">
                                    <?= e($service['category_name']) ?>
                                </span>
                            </td>
                            <td class="nowrap"><?= e(minutes_to_human((int) $service['duration_minutes'])) ?></td>
                            <td class="text-right nowrap">
                                <?php if ($price < (float) $service['price']): ?>
                                    <del class="text-muted text-small"><?= e(money((float) $service['price'])) ?></del><br>
                                <?php endif; ?>
                                <strong><?= e(money($price)) ?></strong>
                            </td>
                            <td>
                                <?php if ((bool) $service['is_active']): ?>
                                    <span class="pill pill--success">Activo</span>
                                <?php else: ?>
                                    <span class="pill pill--danger">Oculto</span>
                                <?php endif; ?>
                                <?php if (!(bool) $service['bookable_online']): ?>
                                    <span class="pill pill--warning">Solo local</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <a class="btn btn--ghost btn--sm"
                                       href="<?= e(url('/panel/servicios/' . (int) $service['id'] . '/editar')) ?>">Editar</a>
                                    <form method="post" data-confirm="Eliminar este servicio?"
                                          action="<?= e(url('/panel/servicios/' . (int) $service['id'] . '/eliminar')) ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn--ghost btn--sm">Eliminar</button>
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
    'result' => $result, 'baseUrl' => url('/panel/servicios'), 'query' => $filters,
]) ?>
<?php View::stop(); ?>
