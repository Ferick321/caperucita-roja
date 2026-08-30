<?php
/** @var string $currentPath @var array<string,mixed>|null $authUser */

use App\Services\SettingsService;

$logo = SettingsService::string('business.logo', '');
$businessName = SettingsService::string('business.name', 'Estilo');

$links = [
    '/' => 'Inicio',
    '/servicios' => 'Servicios',
    '/equipo' => 'Equipo',
    '/galeria' => 'Galeria',
    '/app' => 'App movil',
    '/contacto' => 'Contacto',
];
?>
<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="<?= e(url('/')) ?>">
            <?php if ($logo !== ''): ?>
                <img src="<?= e(media_url($logo)) ?>" alt="<?= e($businessName) ?>">
            <?php endif; ?>
            <span class="brand__name">
                <?= e($businessName) ?>
                <?php if (SettingsService::string('business.tagline', '') !== ''): ?>
                    <span class="brand__tagline"><?= e(SettingsService::string('business.tagline')) ?></span>
                <?php endif; ?>
            </span>
        </a>

        <button class="nav-toggle" type="button" aria-label="Abrir el menu" aria-expanded="false" aria-controls="menu-principal">
            <span></span>
        </button>

        <nav class="nav" id="menu-principal" aria-label="Menu principal">
            <?php foreach ($links as $path => $label): ?>
                <a href="<?= e(url($path)) ?>"
                   class="<?= $currentPath === $path ? 'is-active' : '' ?>"
                   <?= $currentPath === $path ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>

            <?php if ($authUser !== null): ?>
                <a href="<?= e(url('/mi-cuenta')) ?>" class="<?= str_starts_with($currentPath, '/mi') ? 'is-active' : '' ?>">
                    Mi cuenta
                </a>
                <?php if (in_array((string) $authUser['role'], ['super_admin', 'admin', 'manager', 'staff'], true)): ?>
                    <a href="<?= e(url('/panel')) ?>">Panel</a>
                <?php endif; ?>
                <form method="post" action="<?= e(url('/salir')) ?>" style="display:inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--ghost btn--sm">Salir</button>
                </form>
            <?php else: ?>
                <a href="<?= e(url('/ingresar')) ?>">Ingresar</a>
            <?php endif; ?>

            <a class="btn btn--primary btn--sm" href="<?= e(url('/agendar')) ?>">Agendar cita</a>
        </nav>
    </div>
</header>
