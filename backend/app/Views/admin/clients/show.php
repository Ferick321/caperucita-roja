<?php
/**
 * @var array<string,mixed> $client
 * @var list<array<string,mixed>> $appointments
 * @var list<array<string,mixed>> $payments
 * @var list<array<string,mixed>> $loyalty
 */

use App\Core\View;
use App\Security\Auth;

View::extend('layouts.admin');

$id = (int) $client['id'];
?>
<?php View::start('title'); ?><?= e($client['first_name'] . ' ' . $client['last_name']) ?><?php View::stop(); ?>

<?php View::start('actions'); ?>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/panel/clientes')) ?>">&larr; Clientes</a>
<?php View::stop(); ?>

<?php View::start('content'); ?>
<div class="grid grid--4 mb-3">
    <div class="stat"><p class="stat__label">Visitas</p><p class="stat__value"><?= (int) $client['total_visits'] ?></p></div>
    <div class="stat stat--success">
        <p class="stat__label">Total gastado</p>
        <p class="stat__value"><?= e(money((float) $client['total_spent'])) ?></p>
    </div>
    <div class="stat stat--primary">
        <p class="stat__label">Puntos</p>
        <p class="stat__value"><?= (int) $client['loyalty_points'] ?></p>
    </div>
    <div class="stat">
        <p class="stat__label">Ultima visita</p>
        <p class="stat__value" style="font-size:1.1rem">
            <?= e($client['last_visit_at'] === null ? 'Nunca' : local_datetime((string) $client['last_visit_at'], 'd/m/Y')) ?>
        </p>
    </div>
</div>

<div class="grid grid--sidebar">
    <div>
        <div class="card card--flush">
            <div class="card__head" style="padding:20px 20px 0"><h2>Historial de citas</h2></div>

            <?php if ($appointments === []): ?>
                <div class="empty-state"><p>Sin citas registradas.</p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Codigo</th><th>Fecha</th><th>Profesional</th><th>Estado</th><th class="text-right">Total</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td class="mono"><?= e($appointment['code']) ?></td>
                                    <td class="nowrap"><?= e(local_datetime((string) $appointment['starts_at'])) ?></td>
                                    <td class="text-muted"><?= e($appointment['staff_name'] ?? '-') ?></td>
                                    <td><?= View::partial('partials.status_pill', ['status' => (string) $appointment['status']]) ?></td>
                                    <td class="text-right"><?= e(money((float) $appointment['total'])) ?></td>
                                    <td class="text-right">
                                        <a class="btn btn--ghost btn--sm"
                                           href="<?= e(url('/panel/citas/' . (int) $appointment['id'])) ?>">Ver</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($loyalty !== []): ?>
            <div class="card card--flush">
                <div class="card__head" style="padding:20px 20px 0"><h2>Movimientos de puntos</h2></div>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Fecha</th><th>Motivo</th><th>Puntos</th><th>Saldo</th></tr></thead>
                        <tbody>
                            <?php foreach ($loyalty as $entry): ?>
                                <tr>
                                    <td class="nowrap"><?= e(local_datetime((string) $entry['created_at'], 'd/m/Y')) ?></td>
                                    <td><?= e($entry['reason']) ?></td>
                                    <td style="color: <?= (int) $entry['points'] >= 0 ? 'var(--a-success)' : 'var(--a-danger)' ?>">
                                        <?= (int) $entry['points'] >= 0 ? '+' : '' ?><?= (int) $entry['points'] ?>
                                    </td>
                                    <td><?= (int) $entry['balance_after'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div>
        <?php if (Auth::can('clientes.editar')): ?>
            <div class="card">
                <h3>Ficha</h3>

                <form method="post" action="<?= e(url('/panel/clientes/' . $id)) ?>">
                    <?= csrf_field() ?>

                    <div class="field">
                        <label for="first_name">Nombre</label>
                        <input id="first_name" type="text" name="first_name" required maxlength="80"
                               value="<?= e($client['first_name']) ?>">
                    </div>

                    <div class="field">
                        <label for="last_name">Apellido</label>
                        <input id="last_name" type="text" name="last_name" maxlength="80"
                               value="<?= e($client['last_name']) ?>">
                    </div>

                    <div class="field">
                        <label for="phone">Telefono</label>
                        <input id="phone" type="tel" name="phone" value="<?= e($client['phone']) ?>">
                    </div>

                    <div class="field">
                        <label>Correo</label>
                        <input type="email" value="<?= e($client['email']) ?>" disabled>
                    </div>

                    <div class="field">
                        <label for="birth_date">Cumpleanos</label>
                        <input id="birth_date" type="date" name="birth_date" value="<?= e($client['birth_date'] ?? '') ?>">
                    </div>

                    <div class="field">
                        <label for="status">Estado</label>
                        <select id="status" name="status">
                            <?php foreach (['active' => 'Activo', 'pending' => 'Pendiente', 'blocked' => 'Bloqueado'] as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= (string) $client['status'] === $key ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="notes">Notas internas</label>
                        <textarea id="notes" name="notes" rows="3" maxlength="2000"
                                  placeholder="Preferencias, alergias, observaciones..."><?= e($client['notes'] ?? '') ?></textarea>
                        <span class="field__hint">Solo visible para el personal.</span>
                    </div>

                    <label class="checkbox">
                        <input type="checkbox" name="accepts_marketing" value="1"
                               <?= (bool) $client['accepts_marketing'] ? 'checked' : '' ?>>
                        <span>Acepta recibir publicidad</span>
                    </label>

                    <button type="submit" class="btn btn--primary btn--block">Guardar ficha</button>
                </form>
            </div>

            <div class="card">
                <h3>Ajustar puntos</h3>
                <form method="post" action="<?= e(url('/panel/clientes/' . $id . '/puntos')) ?>">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="points">Puntos (negativo para restar)</label>
                        <input id="points" type="number" name="points" required placeholder="Ej. 100 o -50">
                    </div>
                    <div class="field">
                        <label for="reason">Motivo</label>
                        <input id="reason" type="text" name="reason" maxlength="160" placeholder="Ej. cortesia">
                    </div>
                    <button type="submit" class="btn btn--ghost btn--block">Aplicar ajuste</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if (Auth::can('clientes.eliminar')): ?>
            <div class="card card--danger">
                <h3>Zona de riesgo</h3>

                <form method="post" data-confirm="Desactivar a este cliente?"
                      action="<?= e(url('/panel/clientes/' . $id . '/eliminar')) ?>" class="mb-2">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--ghost btn--sm btn--block">Desactivar cliente</button>
                </form>

                <p class="text-muted text-small">
                    La eliminacion definitiva borra sus datos personales, su foto y sus comprobantes,
                    y anonimiza el historico. No se puede deshacer.
                </p>

                <form method="post" action="<?= e(url('/panel/clientes/' . $id . '/olvidar')) ?>"
                      data-confirm="Esta accion es irreversible. Continuar?">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="confirm">Escribe ELIMINAR</label>
                        <input id="confirm" type="text" name="confirm" placeholder="ELIMINAR">
                    </div>
                    <button type="submit" class="btn btn--danger btn--sm btn--block">
                        Eliminar datos personales
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php View::stop(); ?>
