<?php
/** @var string $title @var string $content */

use App\Core\View;

View::extend('layouts.public');
?>
<?php View::start('title'); ?><?= e($title) ?><?php View::stop(); ?>

<?php View::start('content'); ?>
<section class="section">
    <div class="container" style="max-width:820px">
        <h1><?= e($title) ?></h1>

        <?php if (trim($content) === ''): ?>
            <p class="text-muted">Este documento aun no ha sido publicado. Escribenos si necesitas informacion.</p>
        <?php else: ?>
            <?php
            /*
             * El contenido lo redacta el administrador desde el panel.
             * Se limpia con una lista blanca de etiquetas: se admite formato,
             * pero no scripts, iframes ni atributos de evento.
             */
            $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><a><blockquote><hr><table><thead><tbody><tr><th><td>';
            $clean = strip_tags($content, $allowed);
            $clean = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? '';
            $clean = preg_replace('/(href|src)\s*=\s*("|\')\s*(javascript|data|vbscript):/i', '$1=$2#', $clean) ?? '';
            ?>
            <div class="text-muted"><?= $clean ?></div>
        <?php endif; ?>
    </div>
</section>
<?php View::stop(); ?>
