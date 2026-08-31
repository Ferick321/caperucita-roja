<?php
/**
 * Plantilla base del panel.
 *
 * @var string $cspNonce
 * @var array<string,list<string>> $flash
 * @var array<string,mixed>|null $adminUser
 * @var string $currentPath
 */

use App\Core\QueryBuilder;
use App\Core\View;
use App\Security\Auth;
use App\Services\SettingsService;

$businessName = SettingsService::string('business.name', 'Panel');
$logo = SettingsService::string('business.logo', '');

/** Avisos numericos en el menu: lo que requiere atencion inmediata. */
$badges = [
    'pagos' => QueryBuilder::table('payments')->where('status', 'awaiting_verification')->whereNull('deleted_at')->count(),
    'citas' => QueryBuilder::table('appointments')->where('status', 'pending')->whereNull('deleted_at')->count(),
    'resenas' => QueryBuilder::table('reviews')->where('is_approved', 0)->whereNull('deleted_at')->count(),
    'mensajes' => QueryBuilder::table('contact_messages')->where('is_read', 0)->whereNull('deleted_at')->count(),
];

/**
 * Menu: cada entrada declara el permiso que exige, de modo que un usuario
 * solo ve lo que realmente puede usar.
 *
 * @var array<string,list<array{label:string,url:string,icon:string,permission:string,badge?:string}>> $menu
 */
$menu = [
    'Operacion' => [
        ['label' => 'Resumen', 'url' => '/panel', 'icon' => '&#9632;', 'permission' => 'panel.ver'],
        ['label' => 'Agenda del dia', 'url' => '/panel/citas/agenda', 'icon' => '&#9200;', 'permission' => 'citas.ver'],
        ['label' => 'Citas', 'url' => '/panel/citas', 'icon' => '&#128197;', 'permission' => 'citas.ver', 'badge' => 'citas'],
        ['label' => 'Pagos', 'url' => '/panel/pagos', 'icon' => '&#128179;', 'permission' => 'pagos.ver', 'badge' => 'pagos'],
        ['label' => 'Clientes', 'url' => '/panel/clientes', 'icon' => '&#128101;', 'permission' => 'clientes.ver'],
        ['label' => 'Lista de espera', 'url' => '/panel/espera', 'icon' => '&#8987;', 'permission' => 'espera.ver'],
    ],
    'Negocio' => [
        ['label' => 'Servicios', 'url' => '/panel/servicios', 'icon' => '&#9986;', 'permission' => 'servicios.ver'],
        ['label' => 'Equipo', 'url' => '/panel/personal', 'icon' => '&#128100;', 'permission' => 'personal.ver'],
        ['label' => 'Sucursales', 'url' => '/panel/sucursales', 'icon' => '&#127970;', 'permission' => 'sucursales.ver'],
        ['label' => 'Cuentas y cobros', 'url' => '/panel/pagos/cuentas', 'icon' => '&#127974;', 'permission' => 'pagos.cuentas'],
    ],
    'Marketing' => [
        ['label' => 'Publicidad', 'url' => '/panel/publicidad', 'icon' => '&#128226;', 'permission' => 'publicidad.ver'],
        ['label' => 'Campanas', 'url' => '/panel/campanas', 'icon' => '&#9993;', 'permission' => 'campanas.ver'],
        ['label' => 'Cupones', 'url' => '/panel/cupones', 'icon' => '&#127991;', 'permission' => 'cupones.ver'],
        ['label' => 'Suscriptores', 'url' => '/panel/suscriptores', 'icon' => '&#128231;', 'permission' => 'suscriptores.ver'],
        ['label' => 'Pagina web', 'url' => '/panel/contenido', 'icon' => '&#127760;', 'permission' => 'contenido.ver'],
        ['label' => 'Galeria', 'url' => '/panel/contenido/galeria', 'icon' => '&#128247;', 'permission' => 'contenido.ver'],
        ['label' => 'Resenas', 'url' => '/panel/contenido/resenas', 'icon' => '&#9733;', 'permission' => 'contenido.ver', 'badge' => 'resenas'],
        ['label' => 'Mensajes', 'url' => '/panel/contenido/mensajes', 'icon' => '&#128172;', 'permission' => 'contenido.ver', 'badge' => 'mensajes'],
    ],
    'Administracion' => [
        ['label' => 'Informes', 'url' => '/panel/reportes', 'icon' => '&#128200;', 'permission' => 'reportes.ver'],
        ['label' => 'Ajustes', 'url' => '/panel/ajustes', 'icon' => '&#9881;', 'permission' => 'ajustes.ver'],
        ['label' => 'Usuarios del panel', 'url' => '/panel/usuarios', 'icon' => '&#128272;', 'permission' => 'usuarios.ver'],
        ['label' => 'Mantenimiento', 'url' => '/panel/mantenimiento', 'icon' => '&#128736;', 'permission' => 'sistema.mantenimiento'],
        ['label' => 'Auditoria', 'url' => '/panel/mantenimiento/auditoria', 'icon' => '&#128269;', 'permission' => 'sistema.auditoria'],
    ],
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(View::section('title', 'Panel')) ?> &middot; <?= e($businessName) ?></title>
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
    <?= View::section('head') ?>
</head>
<body>
<div class="layout">
    <div class="sidebar-backdrop"></div>

    <aside class="sidebar" id="menu-lateral">
        <div class="sidebar__brand">
            <?php if ($logo !== ''): ?>
                <img src="<?= e(media_url($logo)) ?>" alt="">
            <?php endif; ?>
            <strong>
                <?= e(str_limit($businessName, 22)) ?>
                <span>Panel de gestion</span>
            </strong>
        </div>

        <nav class="sidebar__nav" aria-label="Menu del panel">
            <?php foreach ($menu as $groupLabel => $items): ?>
                <?php
                $visible = array_values(array_filter(
                    $items,
                    static fn (array $item): bool => Auth::can($item['permission'])
                ));
                ?>
                <?php if ($visible === []) { continue; } ?>

                <div class="sidebar__group"><?= e($groupLabel) ?></div>

                <?php foreach ($visible as $item): ?>
                    <?php
                    $isActive = $item['url'] === '/panel'
                        ? $currentPath === '/panel'
                        : str_starts_with($currentPath, $item['url']);
                    $badgeCount = isset($item['badge']) ? (int) ($badges[$item['badge']] ?? 0) : 0;
                    ?>
                    <a class="sidebar__link <?= $isActive ? 'is-active' : '' ?>"
                       href="<?= e(url($item['url'])) ?>"
                       <?= $isActive ? 'aria-current="page"' : '' ?>>
                        <span class="sidebar__icon" aria-hidden="true"><?= $item['icon'] ?></span>
                        <span><?= e($item['label']) ?></span>
                        <?php if ($badgeCount > 0): ?>
                            <span class="sidebar__badge"><?= e((string) min(99, $badgeCount)) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar__footer">
            <?php if ($adminUser !== null): ?>
                <div class="text-small">
                    <strong><?= e($adminUser['first_name'] . ' ' . $adminUser['last_name']) ?></strong>
                    <div class="text-muted"><?= e($adminUser['role']) ?></div>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('/panel/salir')) ?>" class="mt-1">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--ghost btn--sm btn--block">Cerrar sesion</button>
            </form>

            <a class="text-small text-muted" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">
                Ver el sitio publico &rarr;
            </a>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <div class="flex items-center gap-1">
                <button class="menu-toggle" type="button" aria-label="Abrir el menu"
                        aria-expanded="false" aria-controls="menu-lateral">&#9776;</button>
                <h1 class="topbar__title"><?= e(View::section('title', 'Panel')) ?></h1>
            </div>

            <div class="topbar__actions">
                <?= View::section('actions') ?>
            </div>
        </header>

        <main class="content">
            <?php foreach ($flash as $type => $messages): ?>
                <?php foreach ($messages as $message): ?>
                    <div class="alert alert--<?= e($type === 'success' ? 'success' : ($type === 'error' ? 'error' : 'info')) ?>" role="status">
                        <span><?= e($message) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <?php if (SettingsService::bool('system.maintenance_mode', false)): ?>
                <div class="alert alert--warning">
                    <span>
                        <strong>El sitio publico esta en modo mantenimiento.</strong>
                        Los visitantes ven un aviso en lugar de la web.
                        <a href="<?= e(url('/panel/ajustes/system')) ?>">Desactivarlo</a>
                    </span>
                </div>
            <?php endif; ?>

            <?= View::section('content') ?>
        </main>
    </div>
</div>

<div class="lightbox" data-lightbox aria-hidden="true">
    <button type="button" class="lightbox__close" data-lightbox-close aria-label="Cerrar">&times;</button>
    <img src="" alt="Comprobante ampliado">
</div>

<script src="<?= e(asset('js/admin.js')) ?>" nonce="<?= e($cspNonce) ?>" defer></script>
<?= View::section('scripts') ?>
</body>
</html>
