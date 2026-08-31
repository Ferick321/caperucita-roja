<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\HttpException;

/** Base de los controladores de la API: respuestas y validacion uniformes. */
abstract class ApiController extends Controller
{
    /**
     * En la API la validacion nunca redirige: siempre devuelve 422 con el
     * detalle de los campos, para que la app pueda marcarlos en pantalla.
     *
     * @param array<string,string> $rules
     * @param array<string,string> $labels
     * @return array<string,mixed>
     */
    protected function validate(Request $request, array $rules, array $labels = []): array
    {
        return Validator::make($request->all(), $rules, $labels)->validateOrFail();
    }

    protected function ok(mixed $data = null, array $meta = []): Response
    {
        return Response::apiOk($data, $meta);
    }

    /** Paginacion homogenea para todos los listados. */
    protected function paginated(array $result): Response
    {
        return Response::apiOk($result['data'], [
            'total' => $result['total'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'pages' => $result['pages'],
        ]);
    }

    protected function page(Request $request): int
    {
        return max(1, $request->int('page', 1));
    }

    /** Convierte una fila de servicio al formato que espera la app. */
    protected function serviceResource(array $service): array
    {
        return [
            'id' => (int) $service['id'],
            'category_id' => (int) $service['category_id'],
            'name' => (string) $service['name'],
            'slug' => (string) $service['slug'],
            'short_description' => (string) $service['short_description'],
            'description' => (string) ($service['description'] ?? ''),
            'image_url' => (string) $service['image_path'] !== ''
                ? media_url((string) $service['image_path'])
                : null,
            'duration_minutes' => (int) $service['duration_minutes'],
            'price' => round(\App\Services\BookingService::currentPrice($service), 2),
            'base_price' => round((float) $service['price'], 2),
            'has_promo' => \App\Services\BookingService::currentPrice($service) < (float) $service['price'],
            'deposit_required' => (bool) $service['deposit_required'],
            'loyalty_points' => (int) $service['loyalty_points'],
            'is_featured' => (bool) $service['is_featured'],
        ];
    }

    /** @param array<string,mixed> $staff */
    protected function staffResource(array $staff): array
    {
        return [
            'id' => (int) $staff['id'],
            'name' => (string) $staff['display_name'],
            'title' => (string) ($staff['title'] ?? ''),
            'bio' => (string) ($staff['bio'] ?? ''),
            'photo_url' => (string) ($staff['photo_path'] ?? '') !== ''
                ? media_url((string) $staff['photo_path'])
                : null,
            'color' => (string) ($staff['color'] ?? '#0ea5e9'),
            'rating' => round((float) ($staff['rating_average'] ?? 0), 2),
            'rating_count' => (int) ($staff['rating_count'] ?? 0),
        ];
    }

    /** @param array<string,mixed> $appointment */
    protected function appointmentResource(array $appointment, array $services = []): array
    {
        return [
            'id' => (int) $appointment['id'],
            'code' => (string) $appointment['code'],
            'status' => (string) $appointment['status'],
            'status_label' => $this->statusLabel((string) $appointment['status']),
            'payment_status' => (string) $appointment['payment_status'],
            'starts_at' => (string) $appointment['starts_at'],
            'starts_at_local' => local_datetime((string) $appointment['starts_at'], 'Y-m-d H:i'),
            'ends_at' => (string) $appointment['ends_at'],
            'duration_minutes' => (int) $appointment['duration_minutes'],
            'staff' => [
                'id' => $appointment['staff_id'] === null ? null : (int) $appointment['staff_id'],
                'name' => (string) ($appointment['staff_name'] ?? ''),
                'photo_url' => (string) ($appointment['staff_photo'] ?? '') !== ''
                    ? media_url((string) $appointment['staff_photo'])
                    : null,
            ],
            'branch' => [
                'name' => (string) ($appointment['branch_name'] ?? ''),
                'address' => (string) ($appointment['branch_address'] ?? ''),
            ],
            'services' => array_map(static fn (array $s): array => [
                'name' => (string) $s['service_name'],
                'price' => round((float) $s['price'], 2),
                'duration_minutes' => (int) $s['duration_minutes'],
            ], $services),
            'custom_request' => (string) $appointment['custom_request'],
            'notes' => (string) ($appointment['client_notes'] ?? ''),
            'subtotal' => round((float) $appointment['subtotal'], 2),
            'discount' => round((float) $appointment['discount_amount'], 2),
            'total' => round((float) $appointment['total'], 2),
            'paid_amount' => round((float) $appointment['paid_amount'], 2),
            'currency' => (string) $appointment['currency'],
            'can_cancel' => $this->canCancel($appointment),
        ];
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente de confirmacion',
            'confirmed' => 'Confirmada',
            'in_progress' => 'En curso',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
            'no_show' => 'No asististe',
            default => $status,
        };
    }

    /** @param array<string,mixed> $appointment */
    private function canCancel(array $appointment): bool
    {
        if (!\App\Services\SettingsService::bool('booking.allow_client_cancel', true)) {
            return false;
        }

        if (!in_array((string) $appointment['status'], ['pending', 'confirmed'], true)) {
            return false;
        }

        $minHours = \App\Services\SettingsService::int('booking.cancellation_hours', 4);

        return (strtotime((string) $appointment['starts_at']) - time()) / 3600 >= $minHours;
    }

    /** Exige que la peticion venga de una version de app soportada. */
    protected function assertAppVersion(Request $request): void
    {
        $version = (string) $request->header('x-app-version', '');
        $minimum = (string) \App\Services\SettingsService::string('app.min_supported_version', '1.0.0');

        if ($version === '' || $minimum === '') {
            return;
        }

        if (version_compare($version, $minimum, '<')) {
            throw new HttpException(426, 'Necesitas actualizar la aplicacion para continuar.', [
                'min_version' => $minimum,
                'download_url' => \App\Services\SettingsService::string('app.download_url_android', ''),
            ]);
        }
    }
}
