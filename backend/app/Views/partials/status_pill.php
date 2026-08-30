<?php
/**
 * Etiqueta de estado de una cita.
 *
 * @var string $status
 */

$map = [
    'pending' => ['Pendiente', 'warning'],
    'confirmed' => ['Confirmada', 'info'],
    'in_progress' => ['En curso', 'primary'],
    'completed' => ['Completada', 'success'],
    'cancelled' => ['Cancelada', 'danger'],
    'no_show' => ['No asistio', 'danger'],
];

[$label, $color] = $map[$status] ?? [$status, ''];
?>
<span class="pill pill--<?= e($color) ?>"><?= e($label) ?></span>
