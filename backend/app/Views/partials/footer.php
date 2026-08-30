<?php

use App\Services\SettingsService;

$businessName = SettingsService::string('business.name', 'Estilo');
$phone = SettingsService::string('business.phone', '');
$email = SettingsService::string('business.email', '');
$address = SettingsService::string('business.address', '');

$socials = [
    'Facebook' => SettingsService::string('social.facebook', ''),
    'Instagram' => SettingsService::string('social.instagram', ''),
    'TikTok' => SettingsService::string('social.tiktok', ''),
    'YouTube' => SettingsService::string('social.youtube', ''),
];
?>
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <h4><?= e($businessName) ?></h4>
                <p class="text-muted text-small">
                    <?= e(str_limit(SettingsService::string('business.description', ''), 190)) ?>
                </p>

                <?php if (array_filter($socials) !== []): ?>
                    <div class="social-links">
                        <?php foreach ($socials as $name => $link): ?>
                            <?php if ($link !== ''): ?>
                                <a href="<?= e_url($link) ?>" target="_blank" rel="noopener noreferrer"
                                   aria-label="<?= e($name) ?>"><?= e(mb_substr($name, 0, 1)) ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <h4>Navegacion</h4>
                <ul class="footer-links">
                    <li><a href="<?= e(url('/servicios')) ?>">Servicios</a></li>
                    <li><a href="<?= e(url('/equipo')) ?>">Nuestro equipo</a></li>
                    <li><a href="<?= e(url('/galeria')) ?>">Galeria</a></li>
                    <li><a href="<?= e(url('/agendar')) ?>">Agendar cita</a></li>
                </ul>
            </div>

            <div>
                <h4>Contacto</h4>
                <ul class="footer-links">
                    <?php if ($phone !== ''): ?>
                        <li><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone) ?? '') ?>"><?= e($phone) ?></a></li>
                    <?php endif; ?>
                    <?php if ($email !== ''): ?>
                        <li><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li>
                    <?php endif; ?>
                    <?php if ($address !== ''): ?>
                        <li class="text-muted text-small"><?= e($address) ?></li>
                    <?php endif; ?>
                    <li><a href="<?= e(url('/contacto')) ?>">Formulario de contacto</a></li>
                </ul>
            </div>

            <div>
                <h4>Novedades y promociones</h4>
                <p class="text-muted text-small">Dejanos tu correo y te avisamos de nuestras ofertas.</p>
                <form method="post" action="<?= e(url('/boletin')) ?>" class="form-grid">
                    <?= csrf_field() ?>
                    <?= honeypot_field() ?>
                    <label class="sr-only" for="boletin-email">Correo electronico</label>
                    <input id="boletin-email" type="email" name="email" placeholder="tucorreo@ejemplo.com" required>
                    <button type="submit" class="btn btn--primary btn--sm">Suscribirme</button>
                </form>
            </div>
        </div>

        <div class="footer-bottom">
            <span>&copy; <?= e(date('Y')) ?> <?= e($businessName) ?>. Todos los derechos reservados.</span>
            <span>
                <a href="<?= e(url('/legal/privacidad')) ?>">Privacidad</a> &middot;
                <a href="<?= e(url('/legal/terminos')) ?>">Terminos</a>
            </span>
        </div>
    </div>
</footer>
