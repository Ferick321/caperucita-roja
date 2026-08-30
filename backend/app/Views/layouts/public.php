<?php
/**
 * Plantilla base del sitio publico.
 *
 * Toda la marca (nombre, logo, colores, tipografias, redes, textos legales)
 * proviene de los ajustes editables desde el panel.
 *
 * @var string $cspNonce
 * @var array<string,list<string>> $flash
 * @var array<string,mixed>|null $authUser
 * @var string $currentPath
 */

use App\Core\View;
use App\Services\SettingsService;

$businessName = SettingsService::string('business.name', 'Estilo');
$tagline = SettingsService::string('business.tagline', '');
$logo = SettingsService::string('business.logo', '');
$pageTitle = View::section('title') !== '' ? View::section('title') : $businessName;
$metaDescription = View::section('description') !== ''
    ? View::section('description')
    : SettingsService::string('seo.meta_description', $tagline);
$whatsapp = SettingsService::string('business.whatsapp', '');
$adsEnabled = SettingsService::bool('ads.enabled', true);
$showLoginAd = \App\Core\Session::get('__show_login_ad') === true;
\App\Core\Session::forget('__show_login_ad');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta name="theme-color" content="<?= e(SettingsService::string('theme.primary_color', '#c9a227')) ?>">
    <title><?= e($pageTitle) ?> &middot; <?= e($businessName) ?></title>

    <?php if (SettingsService::string('business.favicon', '') !== ''): ?>
        <link rel="icon" href="<?= e(media_url(SettingsService::string('business.favicon'))) ?>">
    <?php else: ?>
        <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>">
    <?php endif; ?>

    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:site_name" content="<?= e($businessName) ?>">
    <?php if (SettingsService::string('seo.og_image', '') !== ''): ?>
        <meta property="og:image" content="<?= e(media_url(SettingsService::string('seo.og_image'))) ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">

    <?php /* Los colores y tipografias del panel se aplican como variables CSS. */ ?>
    <style nonce="<?= e($cspNonce) ?>">
        :root {
            --color-primary: <?= e(SettingsService::string('theme.primary_color', '#c9a227')) ?>;
            --color-secondary: <?= e(SettingsService::string('theme.secondary_color', '#111827')) ?>;
            --color-accent: <?= e(SettingsService::string('theme.accent_color', '#e11d48')) ?>;
            --color-bg: <?= e(SettingsService::string('theme.background_color', '#0b0f19')) ?>;
            --color-surface: <?= e(SettingsService::string('theme.surface_color', '#141b2d')) ?>;
            --color-text: <?= e(SettingsService::string('theme.text_color', '#e5e7eb')) ?>;
            --radius: <?= (int) SettingsService::int('theme.rounded_corners', 16) ?>px;
            --font-heading: "<?= e(SettingsService::string('theme.font_heading', 'Poppins')) ?>", system-ui, sans-serif;
            --font-body: "<?= e(SettingsService::string('theme.font_body', 'Inter')) ?>", system-ui, sans-serif;
        }
    </style>
    <?= View::section('head') ?>
</head>
<body
    data-base-url="<?= e(url('')) ?>"
    <?php if ($adsEnabled): ?>
        <?php if ($showLoginAd && SettingsService::bool('ads.show_on_login', true)): ?>data-ad-on-login="1"<?php endif; ?>
        <?php if (SettingsService::bool('ads.show_while_browsing', true)): ?>
            data-ad-browsing="1"
            data-ad-browsing-delay="<?= (int) SettingsService::int('ads.browsing_delay_seconds', 45) ?>"
        <?php endif; ?>
        <?php if (SettingsService::bool('ads.show_on_exit', true)): ?>data-ad-on-exit="1"<?php endif; ?>
    <?php endif; ?>
>
<a class="skip-link" href="#contenido">Saltar al contenido</a>

<?= View::partial('partials.header', ['currentPath' => $currentPath, 'authUser' => $authUser]) ?>

<?php if (!empty($stripBanner)): ?>
    <?= View::partial('partials.ad_strip', ['banner' => $stripBanner]) ?>
<?php endif; ?>

<main id="contenido">
    <?php if ($flash !== []): ?>
        <div class="container mt-3">
            <?php foreach ($flash as $type => $messages): ?>
                <?php foreach ($messages as $message): ?>
                    <div class="alert alert--<?= e($type === 'success' ? 'success' : ($type === 'error' ? 'error' : 'info')) ?>" role="status">
                        <span><?= e($message) ?></span>
                        <button type="button" class="copy-btn" data-dismiss-alert aria-label="Cerrar aviso">&times;</button>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?= View::section('content') ?>
</main>

<?= View::partial('partials.footer') ?>

<?php if ($whatsapp !== ''): ?>
    <a class="whatsapp-fab"
       href="https://wa.me/<?= e(preg_replace('/\D/', '', $whatsapp) ?? '') ?>"
       target="_blank" rel="noopener noreferrer"
       aria-label="Escribenos por WhatsApp">&#9993;</a>
<?php endif; ?>

<?php if ($adsEnabled): ?>
    <div class="ad-modal" data-ad-modal aria-hidden="true" role="dialog" aria-label="Promocion">
        <div class="ad-modal__box"></div>
    </div>
<?php endif; ?>

<?php if (SettingsService::bool('legal.show_cookie_banner', true)): ?>
    <div class="cookie-banner" data-cookie-banner role="region" aria-label="Aviso de cookies">
        <p class="mb-0" style="flex:1;min-width:240px">
            Usamos cookies propias para el funcionamiento del sitio y para recordar tus preferencias.
            <a href="<?= e(url('/legal/privacidad')) ?>">Mas informacion</a>.
        </p>
        <button type="button" class="btn btn--primary btn--sm" data-cookie-accept>Entendido</button>
    </div>
<?php endif; ?>

<script src="<?= e(asset('js/app.js')) ?>" nonce="<?= e($cspNonce) ?>" defer></script>
<?= View::section('scripts') ?>
</body>
</html>
