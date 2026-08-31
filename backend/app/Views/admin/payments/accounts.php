<?php
/**
 * @var list<array<string,mixed>> $accounts
 * @var list<array<string,mixed>> $methods
 */

use App\Core\View;

View::extend('layouts.admin');
?>
<?php View::start('title'); ?>Cuentas y metodos de cobro<?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/pagos')) ?>">&larr; Pagos</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="help-box">
    Estos son los datos que ve el cliente cuando elige <strong>Transferencia</strong> al reservar,
    tanto en la web como en la app. El numero de cuenta se guarda cifrado en la base de datos.
</div>

<div class="grid grid--sidebar">
    <div>
        <h2>Cuentas bancarias</h2>

        <?php if ($accounts === []): ?>
            <div class="card">
                <div class="empty-state">
                    <div class="empty-state__icon">&#127974;</div>
                    <p>Aun no has cargado ninguna cuenta.</p>
                    <p class="text-small">Sin cuentas, tus clientes no podran pagar por transferencia.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php foreach ($accounts as $account): ?>
            <div class="card" id="cuenta-<?= (int) $account['id'] ?>">
                <div class="card__head">
                    <h3><?= e($account['bank_name']) ?>
                        <span class="text-muted text-small">&middot; <?= e($account['account_type']) ?></span>
                    </h3>
                    <?php if ((bool) $account['is_active']): ?>
                        <span class="pill pill--success">Visible</span>
                    <?php else: ?>
                        <span class="pill pill--danger">Oculta</span>
                    <?php endif; ?>
                </div>

                <form method="post" action="<?= e(url('/panel/pagos/cuentas/' . (int) $account['id'])) ?>">
                    <?= csrf_field() ?>

                    <div class="form-row">
                        <div class="field">
                            <label for="bn-<?= (int) $account['id'] ?>">Banco *</label>
                            <input id="bn-<?= (int) $account['id'] ?>" type="text" name="bank_name" required
                                   maxlength="120" value="<?= e($account['bank_name']) ?>">
                        </div>
                        <div class="field">
                            <label for="at-<?= (int) $account['id'] ?>">Tipo de cuenta</label>
                            <input id="at-<?= (int) $account['id'] ?>" type="text" name="account_type"
                                   maxlength="60" value="<?= e($account['account_type']) ?>"
                                   placeholder="Ahorros / Corriente">
                        </div>
                    </div>

                    <div class="field">
                        <label for="an-<?= (int) $account['id'] ?>">Numero de cuenta</label>
                        <input id="an-<?= (int) $account['id'] ?>" type="text" name="account_number" class="mono"
                               value="<?= e($account['account_number']) ?>" maxlength="60">
                        <span class="field__hint">
                            Se guarda cifrado. Dejalo tal cual si no quieres cambiarlo.
                        </span>
                    </div>

                    <div class="form-row">
                        <div class="field">
                            <label for="hn-<?= (int) $account['id'] ?>">Titular *</label>
                            <input id="hn-<?= (int) $account['id'] ?>" type="text" name="holder_name" required
                                   maxlength="160" value="<?= e($account['holder_name']) ?>">
                        </div>
                        <div class="field">
                            <label for="hd-<?= (int) $account['id'] ?>">Identificacion</label>
                            <input id="hd-<?= (int) $account['id'] ?>" type="text" name="holder_document"
                                   maxlength="60" value="<?= e($account['holder_document']) ?>"
                                   placeholder="Cedula / RUC / NIT">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="field">
                            <label for="he-<?= (int) $account['id'] ?>">Correo del titular</label>
                            <input id="he-<?= (int) $account['id'] ?>" type="email" name="holder_email"
                                   maxlength="190" value="<?= e($account['holder_email']) ?>">
                        </div>
                        <div class="field">
                            <label for="hp-<?= (int) $account['id'] ?>">Telefono del titular</label>
                            <input id="hp-<?= (int) $account['id'] ?>" type="tel" name="holder_phone"
                                   maxlength="30" value="<?= e($account['holder_phone']) ?>">
                        </div>
                    </div>

                    <div class="field">
                        <label for="ai-<?= (int) $account['id'] ?>">Aviso adicional para el cliente</label>
                        <textarea id="ai-<?= (int) $account['id'] ?>" name="instructions" rows="2"
                                  maxlength="1000"><?= e($account['instructions'] ?? '') ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="field">
                            <label for="cu-<?= (int) $account['id'] ?>">Moneda</label>
                            <input id="cu-<?= (int) $account['id'] ?>" type="text" name="currency" maxlength="3"
                                   value="<?= e($account['currency']) ?>">
                        </div>
                        <div class="field">
                            <label for="so-<?= (int) $account['id'] ?>">Orden</label>
                            <input id="so-<?= (int) $account['id'] ?>" type="number" name="sort_order" min="0"
                                   value="<?= (int) $account['sort_order'] ?>">
                        </div>
                    </div>

                    <label class="checkbox">
                        <input type="checkbox" name="is_active" value="1" <?= (bool) $account['is_active'] ? 'checked' : '' ?>>
                        <span>Mostrar esta cuenta a los clientes</span>
                    </label>

                    <div class="btn-row">
                        <button type="submit" class="btn btn--primary btn--sm">Guardar</button>
                        <button type="button" class="btn btn--ghost btn--sm"
                                data-copy="<?= e($account['account_number']) ?>">Copiar numero</button>
                    </div>
                </form>

                <form method="post" class="mt-1" data-confirm="Eliminar esta cuenta bancaria?"
                      action="<?= e(url('/panel/pagos/cuentas/' . (int) $account['id'] . '/eliminar')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--ghost btn--sm">Eliminar cuenta</button>
                </form>
            </div>
        <?php endforeach; ?>

        <div class="card">
            <h3>Anadir una cuenta</h3>

            <form method="post" action="<?= e(url('/panel/pagos/cuentas')) ?>">
                <?= csrf_field() ?>

                <div class="form-row">
                    <div class="field">
                        <label for="new-bank">Banco *</label>
                        <input id="new-bank" type="text" name="bank_name" required maxlength="120"
                               placeholder="Ej. Banco Pichincha">
                        <?= field_error('bank_name') ?>
                    </div>
                    <div class="field">
                        <label for="new-type">Tipo de cuenta</label>
                        <input id="new-type" type="text" name="account_type" maxlength="60" value="Ahorros">
                    </div>
                </div>

                <div class="field">
                    <label for="new-number">Numero de cuenta *</label>
                    <input id="new-number" type="text" name="account_number" required maxlength="60" class="mono">
                    <?= field_error('account_number') ?>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="new-holder">Titular *</label>
                        <input id="new-holder" type="text" name="holder_name" required maxlength="160">
                        <?= field_error('holder_name') ?>
                    </div>
                    <div class="field">
                        <label for="new-doc">Identificacion</label>
                        <input id="new-doc" type="text" name="holder_document" maxlength="60">
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="new-email">Correo del titular</label>
                        <input id="new-email" type="email" name="holder_email" maxlength="190">
                    </div>
                    <div class="field">
                        <label for="new-phone">Telefono del titular</label>
                        <input id="new-phone" type="tel" name="holder_phone" maxlength="30">
                    </div>
                </div>

                <div class="field">
                    <label for="new-instructions">Aviso para el cliente</label>
                    <textarea id="new-instructions" name="instructions" rows="2" maxlength="1000"
                              placeholder="Ej. envia el comprobante indicando el codigo de tu cita"></textarea>
                </div>

                <label class="checkbox">
                    <input type="checkbox" name="is_active" value="1" checked>
                    <span>Mostrar esta cuenta a los clientes</span>
                </label>

                <button type="submit" class="btn btn--primary btn--block">Anadir cuenta</button>
            </form>
        </div>
    </div>

    <div>
        <h2>Metodos de pago</h2>

        <?php foreach ($methods as $method): ?>
            <div class="card">
                <div class="card__head">
                    <h3><?= e($method['name']) ?></h3>
                    <?php if ((bool) $method['is_active']): ?>
                        <span class="pill pill--success">Activo</span>
                    <?php else: ?>
                        <span class="pill pill--danger">Inactivo</span>
                    <?php endif; ?>
                </div>

                <form method="post" action="<?= e(url('/panel/pagos/metodos/' . (int) $method['id'])) ?>">
                    <?= csrf_field() ?>

                    <div class="field">
                        <label for="mn-<?= (int) $method['id'] ?>">Nombre visible</label>
                        <input id="mn-<?= (int) $method['id'] ?>" type="text" name="name" required
                               maxlength="100" value="<?= e($method['name']) ?>">
                    </div>

                    <div class="field">
                        <label for="md-<?= (int) $method['id'] ?>">Descripcion corta</label>
                        <input id="md-<?= (int) $method['id'] ?>" type="text" name="description"
                               maxlength="500" value="<?= e($method['description']) ?>">
                    </div>

                    <div class="field">
                        <label for="mi-<?= (int) $method['id'] ?>">Instrucciones</label>
                        <textarea id="mi-<?= (int) $method['id'] ?>" name="instructions" rows="3"
                                  maxlength="2000"><?= e($method['instructions'] ?? '') ?></textarea>
                    </div>

                    <label class="checkbox">
                        <input type="checkbox" name="is_active" value="1" <?= (bool) $method['is_active'] ? 'checked' : '' ?>>
                        <span>Disponible</span>
                    </label>
                    <label class="checkbox">
                        <input type="checkbox" name="is_online" value="1" <?= (bool) $method['is_online'] ? 'checked' : '' ?>>
                        <span>Se puede elegir al reservar por web/app</span>
                    </label>
                    <label class="checkbox">
                        <input type="checkbox" name="shows_bank_accounts" value="1"
                               <?= (bool) $method['shows_bank_accounts'] ? 'checked' : '' ?>>
                        <span>Mostrar los datos bancarios</span>
                    </label>
                    <label class="checkbox">
                        <input type="checkbox" name="requires_proof" value="1"
                               <?= (bool) $method['requires_proof'] ? 'checked' : '' ?>>
                        <span>Exigir comprobante</span>
                    </label>
                    <label class="checkbox">
                        <input type="checkbox" name="requires_verification" value="1"
                               <?= (bool) $method['requires_verification'] ? 'checked' : '' ?>>
                        <span>El personal debe aprobarlo</span>
                    </label>

                    <div class="field">
                        <label for="mo-<?= (int) $method['id'] ?>">Orden</label>
                        <input id="mo-<?= (int) $method['id'] ?>" type="number" name="sort_order" min="0"
                               value="<?= (int) $method['sort_order'] ?>">
                    </div>

                    <button type="submit" class="btn btn--primary btn--sm btn--block">Guardar</button>
                </form>

                <form method="post" class="mt-2"
                      action="<?= e(url('/panel/pagos/metodos/' . (int) $method['id'] . '/eliminar')) ?>"
                      data-confirm="Se quitara este metodo de pago. Continuar?">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--danger btn--sm btn--block">Eliminar metodo</button>
                </form>
            </div>
        <?php endforeach; ?>

        <div class="card">
            <div class="card__head"><h3>Anadir un metodo de pago</h3></div>
            <p class="text-muted text-small">
                Por ejemplo: Deuna, PayPhone, tarjeta en el local o cualquier otra
                forma de cobro que aceptes.
            </p>

            <form method="post" action="<?= e(url('/panel/pagos/metodos')) ?>">
                <?= csrf_field() ?>

                <div class="field">
                    <label for="nuevo-metodo-nombre">Nombre visible</label>
                    <input id="nuevo-metodo-nombre" type="text" name="name" required maxlength="100"
                           placeholder="Ej: Deuna">
                </div>

                <div class="field">
                    <label for="nuevo-metodo-desc">Descripcion corta</label>
                    <input id="nuevo-metodo-desc" type="text" name="description" maxlength="500">
                </div>

                <div class="field">
                    <label for="nuevo-metodo-inst">Instrucciones para el cliente</label>
                    <textarea id="nuevo-metodo-inst" name="instructions" rows="3" maxlength="2000"></textarea>
                </div>

                <label class="checkbox">
                    <input type="checkbox" name="is_active" value="1" checked>
                    <span>Disponible</span>
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="is_online" value="1" checked>
                    <span>Se puede elegir al reservar por web/app</span>
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="shows_bank_accounts" value="1">
                    <span>Mostrar los datos bancarios</span>
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="requires_proof" value="1">
                    <span>Exigir comprobante</span>
                </label>
                <label class="checkbox">
                    <input type="checkbox" name="requires_verification" value="1">
                    <span>El personal debe aprobarlo</span>
                </label>

                <button type="submit" class="btn btn--primary btn--block mt-2">Anadir metodo</button>
            </form>
        </div>
    </div>
</div>
<?php View::stop(); ?>
