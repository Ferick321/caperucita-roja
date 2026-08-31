<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Services\SettingsService;

/**
 * Configuracion remota de la aplicacion.
 *
 * La app pide este endpoint al arrancar: de aqui salen el nombre del negocio,
 * los colores, los textos, las reglas de reserva y los avisos de version.
 * Cambiar un ajuste en el panel cambia la app sin publicar una version nueva.
 */
final class ConfigController extends ApiController
{
    public function index(Request $request): Response
    {
        $appVersion = (string) $request->header('x-app-version', '');
        $minVersion = SettingsService::string('app.min_supported_version', '1.0.0');
        $latest = SettingsService::string('app.latest_version', '1.0.0');

        $needsUpdate = $appVersion !== '' && version_compare($appVersion, $minVersion, '<');
        $hasUpdate = $appVersion !== '' && version_compare($appVersion, $latest, '<');

        return $this->ok([
            'business' => [
                'name' => SettingsService::string('business.name'),
                'tagline' => SettingsService::string('business.tagline'),
                'description' => SettingsService::string('business.description'),
                'phone' => SettingsService::string('business.phone'),
                'whatsapp' => SettingsService::string('business.whatsapp'),
                'email' => SettingsService::string('business.email'),
                'address' => SettingsService::string('business.address'),
                'maps_url' => SettingsService::string('business.maps_url'),
                'logo_url' => SettingsService::string('business.logo') !== ''
                    ? media_url(SettingsService::string('business.logo'))
                    : null,
                'timezone' => SettingsService::string('business.timezone'),
                'currency' => SettingsService::string('business.currency'),
                'currency_symbol' => SettingsService::string('business.currency_symbol'),
                'currency_decimals' => SettingsService::int('business.currency_decimals', 2),
                'currency_position' => SettingsService::string('business.currency_position', 'before'),
            ],

            // El tema visual de la app se controla desde el panel.
            'theme' => [
                'primary_color' => SettingsService::string('theme.primary_color'),
                'secondary_color' => SettingsService::string('theme.secondary_color'),
                'accent_color' => SettingsService::string('theme.accent_color'),
                'background_color' => SettingsService::string('theme.background_color'),
                'surface_color' => SettingsService::string('theme.surface_color'),
                'text_color' => SettingsService::string('theme.text_color'),
                'dark_mode' => SettingsService::bool('theme.dark_mode', true),
                'rounded_corners' => SettingsService::int('theme.rounded_corners', 16),
                'font_heading' => SettingsService::string('theme.font_heading'),
                'font_body' => SettingsService::string('theme.font_body'),
            ],

            'booking' => [
                'enabled' => SettingsService::bool('booking.enabled', true),
                'require_login' => SettingsService::bool('booking.require_login', false),
                'slot_interval_minutes' => SettingsService::int('booking.slot_interval_minutes', 15),
                'min_hours_before' => SettingsService::int('booking.min_hours_before', 2),
                'max_days_ahead' => SettingsService::int('booking.max_days_ahead', 60),
                'allow_multiple_services' => SettingsService::bool('booking.allow_multiple_services', true),
                'max_services' => SettingsService::int('booking.max_services_per_appointment', 4),
                'allow_staff_choice' => SettingsService::bool('booking.allow_staff_choice', true),
                'allow_no_preference' => SettingsService::bool('booking.allow_no_preference', true),
                'cancellation_hours' => SettingsService::int('booking.cancellation_hours', 4),
                'allow_client_cancel' => SettingsService::bool('booking.allow_client_cancel', true),
                'custom_request_enabled' => SettingsService::bool('booking.custom_request_enabled', true),
                'custom_request_label' => SettingsService::string('booking.custom_request_label'),
                'terms_text' => SettingsService::string('booking.terms_text'),
            ],

            'payments' => [
                'enabled' => SettingsService::bool('payments.enabled', true),
                'transfer_instructions' => SettingsService::string('payments.transfer_instructions'),
                'require_deposit' => SettingsService::bool('payments.require_deposit', false),
                'deposit_percent' => SettingsService::float('payments.deposit_percent', 30),
            ],

            'loyalty' => [
                'enabled' => SettingsService::bool('loyalty.enabled', true),
                'points_per_currency' => SettingsService::float('loyalty.points_per_currency', 1),
                'points_to_currency' => SettingsService::float('loyalty.points_to_currency', 100),
            ],

            'ads' => [
                'enabled' => SettingsService::bool('ads.enabled', true),
                'show_splash' => SettingsService::bool('app.show_splash_ad', true),
            ],

            'social' => [
                'facebook' => SettingsService::string('social.facebook'),
                'instagram' => SettingsService::string('social.instagram'),
                'tiktok' => SettingsService::string('social.tiktok'),
                'youtube' => SettingsService::string('social.youtube'),
            ],

            'legal' => [
                'privacy_url' => url('/legal/privacidad'),
                'terms_url' => url('/legal/terminos'),
            ],

            'app' => [
                'latest_version' => $latest,
                'min_supported_version' => $minVersion,
                'update_required' => $needsUpdate || SettingsService::bool('app.force_update', false),
                'update_available' => $hasUpdate,
                'download_url' => SettingsService::string('app.download_url_android'),
                'promo_text' => SettingsService::string('app.promo_text'),
            ],

            'maintenance' => [
                'active' => SettingsService::bool('system.maintenance_mode', false),
                'message' => SettingsService::string('system.maintenance_message'),
            ],

            'branches' => array_map(static fn (array $branch): array => [
                'id' => (int) $branch['id'],
                'name' => (string) $branch['name'],
                'address' => (string) $branch['address'],
                'city' => (string) $branch['city'],
                'phone' => (string) $branch['phone'],
                'latitude' => $branch['latitude'] === null ? null : (float) $branch['latitude'],
                'longitude' => $branch['longitude'] === null ? null : (float) $branch['longitude'],
                'maps_url' => (string) $branch['maps_url'],
                'is_default' => (bool) $branch['is_default'],
            ], QueryBuilder::table('branches')
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->orderBy('sort_order')
                ->get()),
        ]);
    }

    /** Horario de atencion de una sucursal, para mostrarlo en la app. */
    public function branchHours(Request $request): Response
    {
        $branchId = $request->paramInt('id');
        $names = ['Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado'];

        $rows = QueryBuilder::table('branch_hours')
            ->where('branch_id', $branchId)
            ->orderBy('weekday')
            ->get();

        return $this->ok(array_map(static fn (array $row): array => [
            'weekday' => (int) $row['weekday'],
            'weekday_name' => $names[(int) $row['weekday']] ?? '',
            'opens_at' => substr((string) $row['opens_at'], 0, 5),
            'closes_at' => substr((string) $row['closes_at'], 0, 5),
            'is_closed' => (bool) $row['is_closed'],
        ], $rows));
    }
}
