<?php
/** @var list<array<string,mixed>> $branches @var list<array<string,mixed>> $faqs */

use App\Core\View;
use App\Services\SettingsService;

View::extend('layouts.public');
?>
<?php View::start('title'); ?>Contacto<?php View::stop(); ?>

<?php View::start('content'); ?>
<section class="section">
    <div class="container">
        <div class="section__head">
            <span class="section__eyebrow">Hablemos</span>
            <h1>Contacto</h1>
            <p>Escribenos y te respondemos lo antes posible.</p>
        </div>

        <div class="grid grid--2">
            <div class="card" style="padding:26px">
                <h2 style="font-size:1.2rem">Envianos un mensaje</h2>

                <form method="post" action="<?= e(url('/contacto')) ?>" class="form-grid mt-2">
                    <?= csrf_field() ?>
                    <?= honeypot_field() ?>

                    <div class="field">
                        <label for="c-name">Nombre *</label>
                        <input id="c-name" type="text" name="name" required maxlength="120"
                               value="<?= e(old('name')) ?>">
                        <?= field_error('name') ?>
                    </div>

                    <div class="form-grid form-grid--2">
                        <div class="field">
                            <label for="c-email">Correo</label>
                            <input id="c-email" type="email" name="email" value="<?= e(old('email')) ?>">
                            <?= field_error('email') ?>
                        </div>
                        <div class="field">
                            <label for="c-phone">Telefono</label>
                            <input id="c-phone" type="tel" name="phone" value="<?= e(old('phone')) ?>">
                            <?= field_error('phone') ?>
                        </div>
                    </div>

                    <div class="field">
                        <label for="c-subject">Asunto</label>
                        <input id="c-subject" type="text" name="subject" maxlength="200" value="<?= e(old('subject')) ?>">
                    </div>

                    <div class="field">
                        <label for="c-message">Mensaje *</label>
                        <textarea id="c-message" name="message" required minlength="10" maxlength="2000"><?= e(old('message')) ?></textarea>
                        <?= field_error('message') ?>
                    </div>

                    <button type="submit" class="btn btn--primary">Enviar mensaje</button>
                </form>
            </div>

            <div>
                <?php foreach ($branches as $branch): ?>
                    <div class="card mb-2" style="padding:22px">
                        <h3 style="font-size:1.05rem"><?= e($branch['name']) ?></h3>
                        <p class="text-muted text-small mb-2"><?= e($branch['address']) ?> <?= e($branch['city']) ?></p>

                        <?php if ((string) $branch['phone'] !== ''): ?>
                            <p class="mb-1"><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $branch['phone']) ?? '') ?>"><?= e($branch['phone']) ?></a></p>
                        <?php endif; ?>
                        <?php if ((string) $branch['email'] !== ''): ?>
                            <p class="mb-1"><a href="mailto:<?= e($branch['email']) ?>"><?= e($branch['email']) ?></a></p>
                        <?php endif; ?>
                        <?php if ((string) $branch['maps_url'] !== ''): ?>
                            <a class="btn btn--ghost btn--sm mt-2" target="_blank" rel="noopener noreferrer"
                               href="<?= e_url($branch['maps_url']) ?>">Ver en el mapa</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if (SettingsService::string('business.whatsapp', '') !== ''): ?>
                    <div class="card" style="padding:22px">
                        <h3 style="font-size:1.05rem">Prefieres WhatsApp?</h3>
                        <p class="text-muted text-small">Escribenos directo y agendamos por chat.</p>
                        <a class="btn btn--primary btn--sm"
                           href="https://wa.me/<?= e(preg_replace('/\D/', '', SettingsService::string('business.whatsapp')) ?? '') ?>"
                           target="_blank" rel="noopener noreferrer">Abrir WhatsApp</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php View::stop(); ?>
