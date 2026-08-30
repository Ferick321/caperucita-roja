<?php
/**
 * Editor de ajustes.
 *
 * Pinta el control adecuado segun el tipo declarado de cada ajuste, de modo
 * que anadir un ajuste nuevo no requiere tocar esta vista.
 *
 * @var string $group
 * @var array<string,array{0:string,1:string}> $groups
 * @var string $groupLabel
 * @var string $groupHelp
 * @var list<array<string,mixed>> $settings
 * @var list<string> $timezones
 */

use App\Core\View;

View::extend('layouts.admin');
?>

<?php View::start('title'); ?>Ajustes: <?= e($groupLabel) ?><?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="tabs">
    <?php foreach ($groups as $key => [$label, $help]): ?>
        <a class="tab <?= $group === $key ? 'is-active' : '' ?>" href="<?= e(url('/panel/ajustes/' . $key)) ?>">
            <?= e($label) ?>
        </a>
    <?php endforeach; ?>
    <a class="tab" href="<?= e(url('/panel/ajustes/plantillas')) ?>">Plantillas de correo</a>
</div>

<div class="help-box">
    <strong><?= e($groupLabel) ?>.</strong> <?= e($groupHelp) ?>
    Los cambios se aplican al instante en la web y en la app movil, sin necesidad de programar nada.
</div>

<form method="post" action="<?= e(url('/panel/ajustes/' . $group)) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="card">
        <?php if ($settings === []): ?>
            <div class="empty-state"><p>Este grupo no tiene ajustes.</p></div>
        <?php endif; ?>

        <?php foreach ($settings as $setting): ?>
            <?php
            $key = (string) $setting['setting_key'];
            $field = str_replace('.', '__', $key);
            $type = (string) $setting['value_type'];
            $value = (string) ($setting['setting_value'] ?? '');
            $label = (string) $setting['label'] !== '' ? (string) $setting['label'] : $key;
            $help = (string) $setting['help_text'];
            $options = $setting['options_json'] !== null
                ? (json_decode((string) $setting['options_json'], true) ?: [])
                : [];
            ?>

            <?php if ($type === 'bool'): ?>
                <div class="switch-row">
                    <div class="switch-row__text">
                        <strong><?= e($label) ?></strong>
                        <?php if ($help !== ''): ?><span><?= e($help) ?></span><?php endif; ?>
                    </div>
                    <label class="checkbox" style="margin:0">
                        <input type="checkbox" name="<?= e($field) ?>" value="1"
                               <?= in_array($value, ['1', 'true', 'on'], true) ? 'checked' : '' ?>>
                        <span class="sr-only"><?= e($label) ?></span>
                    </label>
                </div>

            <?php elseif ($type === 'image'): ?>
                <div class="field">
                    <label for="<?= e($field) ?>"><?= e($label) ?></label>

                    <?php if ($value !== ''): ?>
                        <div class="flex items-center gap-2 mb-1">
                            <img src="<?= e(media_url($value)) ?>" alt=""
                                 style="max-height:70px;border-radius:9px;border:1px solid var(--a-border)">
                            <label class="checkbox" style="margin:0">
                                <input type="checkbox" name="<?= e($field) ?>__remove" value="1">
                                <span class="text-small">Quitar esta imagen</span>
                            </label>
                        </div>
                    <?php endif; ?>

                    <input id="<?= e($field) ?>" type="file" name="<?= e($field) ?>"
                           accept="image/jpeg,image/png,image/webp"
                           data-preview="#preview-<?= e($field) ?>">
                    <img id="preview-<?= e($field) ?>" class="hidden mt-1" alt=""
                         style="max-height:70px;border-radius:9px">
                    <?php if ($help !== ''): ?><span class="field__hint"><?= e($help) ?></span><?php endif; ?>
                </div>

            <?php elseif ($type === 'color'): ?>
                <div class="field">
                    <label for="<?= e($field) ?>"><?= e($label) ?></label>
                    <div class="flex items-center gap-1">
                        <input type="color" value="<?= e($value !== '' ? $value : '#000000') ?>"
                               data-color-sync="#<?= e($field) ?>" aria-label="Selector de color">
                        <input id="<?= e($field) ?>" type="text" name="<?= e($field) ?>"
                               value="<?= e($value) ?>" pattern="#[0-9a-fA-F]{6}"
                               placeholder="#000000" style="max-width:150px" class="mono">
                    </div>
                    <?php if ($help !== ''): ?><span class="field__hint"><?= e($help) ?></span><?php endif; ?>
                </div>

            <?php elseif ($type === 'select' && $options !== []): ?>
                <div class="field">
                    <label for="<?= e($field) ?>"><?= e($label) ?></label>
                    <select id="<?= e($field) ?>" name="<?= e($field) ?>">
                        <?php foreach ($options as $optionValue => $optionLabel): ?>
                            <option value="<?= e((string) $optionValue) ?>"
                                    <?= $value === (string) $optionValue ? 'selected' : '' ?>>
                                <?= e((string) $optionLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($help !== ''): ?><span class="field__hint"><?= e($help) ?></span><?php endif; ?>
                </div>

            <?php elseif ($key === 'business.timezone'): ?>
                <div class="field">
                    <label for="<?= e($field) ?>"><?= e($label) ?></label>
                    <select id="<?= e($field) ?>" name="<?= e($field) ?>">
                        <?php foreach ($timezones as $timezone): ?>
                            <option value="<?= e($timezone) ?>" <?= $value === $timezone ? 'selected' : '' ?>>
                                <?= e($timezone) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field__hint"><?= e($help) ?></span>
                </div>

            <?php elseif (in_array($type, ['text', 'html'], true)): ?>
                <div class="field">
                    <label for="<?= e($field) ?>"><?= e($label) ?></label>
                    <textarea id="<?= e($field) ?>" name="<?= e($field) ?>"
                              rows="<?= $type === 'html' ? 10 : 4 ?>"><?= e($value) ?></textarea>
                    <?php if ($help !== ''): ?><span class="field__hint"><?= e($help) ?></span><?php endif; ?>
                    <?php if ($type === 'html'): ?>
                        <span class="field__hint">
                            Admite formato basico (negrita, listas, enlaces). Por seguridad se eliminan
                            los scripts y los marcos incrustados.
                        </span>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <?php
                $inputType = match ($type) {
                    'int', 'float' => 'number',
                    'email' => 'email',
                    'url' => 'url',
                    'time' => 'time',
                    default => 'text',
                };
                ?>
                <div class="field">
                    <label for="<?= e($field) ?>"><?= e($label) ?></label>
                    <input id="<?= e($field) ?>" type="<?= e($inputType) ?>" name="<?= e($field) ?>"
                           value="<?= e($value) ?>"
                           <?= $type === 'float' ? 'step="0.01"' : '' ?>
                           <?= $type === 'int' ? 'step="1"' : '' ?>>
                    <?php if ($help !== ''): ?><span class="field__hint"><?= e($help) ?></span><?php endif; ?>
                    <?php if (str_contains($key, 'download_url') || str_contains($key, 'apk_direct')): ?>
                        <span class="field__hint">
                            Pega aqui el enlace de Google Play o del archivo APK. Aparecera en el boton
                            "Descargar la app" de la web.
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <div class="btn-row">
        <button type="submit" class="btn btn--primary">Guardar cambios</button>
        <a class="btn btn--ghost" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">Ver el sitio</a>
    </div>
</form>
<?php View::stop(); ?>
