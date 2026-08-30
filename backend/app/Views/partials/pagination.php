<?php
/**
 * Paginador reutilizable.
 *
 * @var array{total:int,page:int,per_page:int,pages:int} $result
 * @var string $baseUrl
 * @var array<string,scalar|null> $query
 */

use App\Core\Url;

$query = $query ?? [];
$pages = (int) $result['pages'];
$current = (int) $result['page'];

if ($pages <= 1) {
    return;
}

$link = static function (int $page) use ($baseUrl, $query): string {
    return e(Url::withQuery($baseUrl, array_merge($query, ['pagina' => $page])));
};

$from = max(1, $current - 2);
$to = min($pages, $current + 2);
?>
<nav class="pagination" aria-label="Paginacion">
    <?php if ($current > 1): ?>
        <a href="<?= $link($current - 1) ?>" rel="prev">&larr; Anterior</a>
    <?php endif; ?>

    <?php if ($from > 1): ?>
        <a href="<?= $link(1) ?>">1</a>
        <?php if ($from > 2): ?><span>&hellip;</span><?php endif; ?>
    <?php endif; ?>

    <?php for ($page = $from; $page <= $to; $page++): ?>
        <?php if ($page === $current): ?>
            <span class="is-current" aria-current="page"><?= e((string) $page) ?></span>
        <?php else: ?>
            <a href="<?= $link($page) ?>"><?= e((string) $page) ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($to < $pages): ?>
        <?php if ($to < $pages - 1): ?><span>&hellip;</span><?php endif; ?>
        <a href="<?= $link($pages) ?>"><?= e((string) $pages) ?></a>
    <?php endif; ?>

    <?php if ($current < $pages): ?>
        <a href="<?= $link($current + 1) ?>" rel="next">Siguiente &rarr;</a>
    <?php endif; ?>

    <span class="text-muted text-small">
        <?= e((string) $result['total']) ?> registro(s)
    </span>
</nav>
