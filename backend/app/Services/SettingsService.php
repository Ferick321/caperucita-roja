<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Database;
use App\Core\Logger;
use App\Core\QueryBuilder;
use App\Security\Crypto;

/**
 * Motor de configuracion del sistema.
 *
 * Es la pieza que permite cambiar la marca, los colores, los textos, los
 * horarios, las reglas de reserva y el comportamiento de la app movil sin
 * tocar una sola linea de codigo. Los valores se cachean en memoria durante
 * la peticion y en disco entre peticiones.
 */
final class SettingsService
{
    private const TABLE = 'settings';

    /** @var array<string,mixed>|null */
    private static ?array $cache = null;

    private static bool $available = true;

    /** Valores por defecto: garantizan que el sistema funcione recien instalado. */
    private const DEFAULTS = [
        'business.name' => 'Mi Barberia & Estilo',
        'business.tagline' => 'Tu mejor version empieza aqui',
        'business.timezone' => 'America/Guayaquil',
        'business.currency' => 'USD',
        'business.currency_symbol' => '$',
        'business.currency_decimals' => 2,
        'business.currency_position' => 'before',
        'business.decimal_separator' => '.',
        'business.thousand_separator' => ',',
        'business.phone' => '',
        'business.whatsapp' => '',
        'business.email' => '',
        'business.address' => '',
        'business.logo' => '',
        'business.favicon' => '',

        'theme.primary_color' => '#c9a227',
        'theme.secondary_color' => '#111827',
        'theme.accent_color' => '#e11d48',
        'theme.background_color' => '#0b0f19',
        'theme.surface_color' => '#141b2d',
        'theme.text_color' => '#e5e7eb',
        'theme.font_heading' => 'Poppins',
        'theme.font_body' => 'Inter',
        'theme.dark_mode' => true,
        'theme.rounded_corners' => 16,

        'booking.enabled' => true,
        'booking.require_login' => false,
        'booking.slot_interval_minutes' => 15,
        'booking.min_hours_before' => 2,
        'booking.max_days_ahead' => 60,
        'booking.allow_multiple_services' => true,
        'booking.max_services_per_appointment' => 4,
        'booking.auto_confirm' => false,
        'booking.allow_staff_choice' => true,
        'booking.allow_no_preference' => true,
        'booking.cancellation_hours' => 4,
        'booking.allow_client_cancel' => true,
        'booking.allow_client_reschedule' => true,
        'booking.max_active_per_client' => 3,
        'booking.custom_request_enabled' => true,
        'booking.custom_request_label' => 'Otro (especifica lo que necesitas)',
        'booking.terms_text' => '',

        'payments.enabled' => true,
        'payments.require_deposit' => false,
        'payments.deposit_percent' => 30,
        'payments.proof_required_for_transfer' => true,
        'payments.transfer_instructions' => 'Realiza la transferencia y sube el comprobante para confirmar tu cita.',

        'ads.enabled' => true,
        'ads.show_on_login' => true,
        'ads.show_while_browsing' => true,
        'ads.show_on_exit' => true,
        'ads.browsing_delay_seconds' => 45,
        'ads.max_popups_per_session' => 2,
        'ads.respect_do_not_track' => true,

        'app.download_url_android' => '',
        'app.download_url_ios' => '',
        'app.apk_direct_url' => '',
        'app.latest_version' => '1.0.0',
        'app.min_supported_version' => '1.0.0',
        'app.force_update' => false,
        'app.show_splash_ad' => true,
        'app.promo_text' => 'Descarga la app y agenda en segundos',

        'notifications.confirm_email' => true,
        'notifications.reminder_enabled' => true,
        'notifications.reminder_hours_before' => 24,
        'notifications.followup_enabled' => true,
        'notifications.review_request_enabled' => true,
        'notifications.review_request_hours_after' => 3,

        'loyalty.enabled' => true,
        'loyalty.points_per_currency' => 1,
        'loyalty.points_to_currency' => 100,
        'loyalty.welcome_points' => 50,
        'loyalty.referral_points' => 100,

        'seo.meta_title' => '',
        'seo.meta_description' => '',
        'seo.og_image' => '',
        'seo.google_analytics_id' => '',
        'seo.facebook_pixel_id' => '',

        'social.facebook' => '',
        'social.instagram' => '',
        'social.tiktok' => '',
        'social.youtube' => '',

        'legal.privacy_policy' => '',
        'legal.terms' => '',
        'legal.show_cookie_banner' => true,

        'system.maintenance_mode' => false,
        'system.maintenance_message' => 'Estamos realizando mejoras. Volvemos en unos minutos.',
        'system.auto_purge_enabled' => true,
        'system.installed_at' => '',
    ];

    /** Carga todos los ajustes de una vez (una consulta por peticion). */
    public static function warmUp(): void
    {
        if (self::$cache !== null) {
            return;
        }

        self::$cache = self::DEFAULTS;

        try {
            $rows = Database::instance()->select(
                'SELECT setting_key, setting_value, value_type, is_encrypted FROM ' . self::TABLE
            );
        } catch (\Throwable $e) {
            // Antes de instalar la base de datos el sistema debe seguir en pie.
            self::$available = false;
            Logger::warning('No se pudieron leer los ajustes; se usan los valores por defecto.', [
                'error' => $e->getMessage(),
            ]);

            return;
        }

        foreach ($rows as $row) {
            $key = (string) $row['setting_key'];
            $raw = $row['setting_value'];

            if ((bool) $row['is_encrypted'] && is_string($raw) && $raw !== '') {
                $raw = Crypto::decrypt($raw);
            }

            self::$cache[$key] = self::cast($raw, (string) $row['value_type']);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::warmUp();

        if (array_key_exists($key, self::$cache ?? [])) {
            $value = self::$cache[$key];

            return $value === null || $value === '' ? ($default ?? $value) : $value;
        }

        return $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);

        return in_array($value, [true, 1, '1', 'true', 'on', 'si'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        $value = self::get($key, $default);

        return is_numeric($value) ? (float) $value : $default;
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    /** @return array<mixed> */
    public static function array(string $key, array $default = []): array
    {
        $value = self::get($key, $default);

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : $default;
        }

        return $default;
    }

    /** Guarda un ajuste; crea la fila si no existia. */
    public static function set(string $key, mixed $value, ?int $userId = null): void
    {
        $existing = QueryBuilder::table(self::TABLE)->where('setting_key', $key)->first();

        $type = (string) ($existing['value_type'] ?? self::inferType($value));
        $encrypt = (bool) ($existing['is_encrypted'] ?? false);

        $serialized = self::serialize($value, $type);

        if ($encrypt && $serialized !== '') {
            $serialized = Crypto::encrypt($serialized);
        }

        $now = Clock::nowUtc();

        if ($existing === null) {
            QueryBuilder::table(self::TABLE)->insert([
                'group_name' => explode('.', $key)[0] ?? 'general',
                'setting_key' => $key,
                'setting_value' => $serialized,
                'value_type' => $type,
                'label' => ucfirst(str_replace(['.', '_'], ' ', $key)),
                'is_public' => 1,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            QueryBuilder::table(self::TABLE)->where('setting_key', $key)->update([
                'setting_value' => $serialized,
                'updated_by' => $userId,
                'updated_at' => $now,
            ]);
        }

        // Invalida la cache para que el cambio se vea de inmediato.
        self::$cache = null;
    }

    /**
     * Guarda varios ajustes en una transaccion.
     *
     * @param array<string,mixed> $values
     */
    public static function setMany(array $values, ?int $userId = null): void
    {
        Database::instance()->transaction(static function () use ($values, $userId): void {
            foreach ($values as $key => $value) {
                self::set($key, $value, $userId);
            }
        });
    }

    /**
     * Ajustes de un grupo, con sus metadatos, para pintar el formulario.
     *
     * @return list<array<string,mixed>>
     */
    public static function group(string $group): array
    {
        $rows = QueryBuilder::table(self::TABLE)
            ->where('group_name', $group)
            ->orderBy('sort_order')
            ->orderBy('setting_key')
            ->get();

        foreach ($rows as $index => $row) {
            if ((bool) $row['is_encrypted'] && is_string($row['setting_value']) && $row['setting_value'] !== '') {
                $rows[$index]['setting_value'] = Crypto::decrypt((string) $row['setting_value']);
            }
        }

        return $rows;
    }

    /** @return list<string> */
    public static function groups(): array
    {
        return array_map(
            static fn (array $r): string => (string) $r['group_name'],
            Database::instance()->select(
                'SELECT DISTINCT group_name FROM ' . self::TABLE . ' ORDER BY group_name'
            )
        );
    }

    /**
     * Ajustes publicos: los que consumen la web y la app movil.
     *
     * @return array<string,mixed>
     */
    public static function publicSettings(): array
    {
        self::warmUp();

        if (!self::$available) {
            return self::DEFAULTS;
        }

        $rows = Database::instance()->select(
            'SELECT setting_key FROM ' . self::TABLE . ' WHERE is_public = 1'
        );

        $publicKeys = array_map(static fn (array $r): string => (string) $r['setting_key'], $rows);
        $result = [];

        foreach (self::$cache ?? [] as $key => $value) {
            // Nunca se exponen credenciales aunque alguien marque la casilla.
            if (str_contains($key, 'password') || str_contains($key, 'secret') || str_contains($key, 'api_key')) {
                continue;
            }

            if (in_array($key, $publicKeys, true) || array_key_exists($key, self::DEFAULTS)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public static function flushCache(): void
    {
        self::$cache = null;
    }

    private static function cast(mixed $raw, string $type): mixed
    {
        if ($raw === null) {
            return null;
        }

        return match ($type) {
            'int' => (int) $raw,
            'float' => (float) $raw,
            'bool' => in_array((string) $raw, ['1', 'true', 'on', 'si'], true),
            'json' => json_decode((string) $raw, true) ?? [],
            default => (string) $raw,
        };
    }

    private static function serialize(mixed $value, string $type): string
    {
        if ($type === 'json' || is_array($value)) {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $json === false ? '[]' : $json;
        }

        if ($type === 'bool' || is_bool($value)) {
            return in_array($value, [true, 1, '1', 'true', 'on', 'si'], true) ? '1' : '0';
        }

        return (string) $value;
    }

    private static function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'bool',
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_array($value) => 'json',
            default => 'string',
        };
    }
}
