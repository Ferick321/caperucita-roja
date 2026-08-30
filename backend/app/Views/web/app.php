<?php
/** @var string $android @var string $ios @var string $apk @var string $version */

use App\Core\View;
use App\Services\SettingsService;

View::extend('layouts.public');
$hasLinks = $android !== '' || $ios !== '' || $apk !== '';
?>
<?php View::start('title'); ?>Descarga nuestra app<?php View::stop(); ?>

<?php View::start('content'); ?>
<section class="section">
    <div class="container">
        <div class="app-promo">
            <div class="app-promo__grid">
                <div>
                    <span class="section__eyebrow">Version <?= e($version) ?></span>
                    <h1><?= e(SettingsService::string('app.promo_text', 'Descarga la app y agenda en segundos')) ?></h1>
                    <p class="text-muted">
                        Reserva, reprograma y paga desde tu celular. Recibe recordatorios de tus citas,
                        acumula puntos y entérate primero de nuestras promociones.
                    </p>

                    <div class="mt-3">
                        <?php
                        $features = [
                            'Agenda en tiempo real' => 'Ve los horarios libres de cada profesional al instante.',
                            'Pago flexible' => 'Efectivo o transferencia con carga de comprobante desde la camara.',
                            'Recordatorios' => 'Te avisamos antes de tu cita para que no se te pase.',
                            'Puntos y promociones' => 'Gana beneficios cada vez que nos visitas.',
                        ];
                        ?>
                        <?php foreach ($features as $titleText => $text): ?>
                            <div class="app-feature">
                                <div class="app-feature__icon">&#10003;</div>
                                <div>
                                    <strong><?= e($titleText) ?></strong>
                                    <p class="text-muted text-small mb-0"><?= e($text) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="app-badges">
                        <?php if ($android !== ''): ?>
                            <a class="btn btn--primary" href="<?= e_url($android) ?>" target="_blank" rel="noopener noreferrer">
                                Descargar para Android
                            </a>
                        <?php endif; ?>
                        <?php if ($ios !== ''): ?>
                            <a class="btn btn--ghost" href="<?= e_url($ios) ?>" target="_blank" rel="noopener noreferrer">
                                Descargar para iPhone
                            </a>
                        <?php endif; ?>
                        <?php if ($apk !== ''): ?>
                            <a class="btn btn--ghost" href="<?= e_url($apk) ?>">Descargar APK directo</a>
                        <?php endif; ?>
                    </div>

                    <?php if (!$hasLinks): ?>
                        <div class="alert alert--info mt-3">
                            Nuestra app esta a punto de publicarse. Mientras tanto puedes
                            <a href="<?= e(url('/agendar')) ?>">reservar desde la web</a>.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="text-center">
                    <img src="<?= e(asset('img/app-preview.svg')) ?>" alt="Vista previa de la aplicacion"
                         style="max-width:280px;margin:0 auto">
                </div>
            </div>
        </div>
    </div>
</section>
<?php View::stop(); ?>
