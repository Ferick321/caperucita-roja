<?php
/** @var string $status */

$map = [
    'unpaid' => ['Sin pagar', 'warning'],
    'deposit_paid' => ['Abono', 'info'],
    'awaiting_verification' => ['Verificando', 'warning'],
    'paid' => ['Pagada', 'success'],
    'refunded' => ['Reembolsada', 'danger'],
];

[$label, $color] = $map[$status] ?? [$status, ''];
?>
<span class="pill pill--<?= e($color) ?>"><?= e($label) ?></span>
