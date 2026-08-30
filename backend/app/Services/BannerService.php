<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Clock;
use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Session;
use App\Security\Auth;

/**
 * Motor de publicidad.
 *
 * Decide que anuncio mostrar, a quien y cuando, aplicando programacion,
 * segmentacion, prioridad y control de frecuencia. Todo se configura desde
 * el panel: aqui no hay ningun anuncio escrito en el codigo.
 *
 * Ubicaciones disponibles:
 *   web_hero          franja principal de la portada
 *   web_strip         banda delgada bajo el menu
 *   web_sidebar       columna lateral
 *   on_login          ventana al iniciar sesion
 *   while_browsing    ventana tras N segundos navegando
 *   on_exit           aviso al mover el cursor fuera de la pagina
 *   app_splash        pantalla de bienvenida de la app
 *   app_home_card     tarjeta dentro del inicio de la app
 *   app_interstitial  pantalla completa entre secciones de la app
 */
final class BannerService
{
    public const PLACEMENTS = [
        'web_hero' => 'Portada principal (web)',
        'web_strip' => 'Franja bajo el menu (web)',
        'web_sidebar' => 'Columna lateral (web)',
        'on_login' => 'Ventana al iniciar sesion',
        'while_browsing' => 'Ventana mientras navega',
        'on_exit' => 'Aviso al intentar salir',
        'app_splash' => 'Bienvenida de la app movil',
        'app_home_card' => 'Tarjeta en el inicio de la app',
        'app_interstitial' => 'Pantalla completa en la app',
    ];

    /**
     * Anuncios activos para una ubicacion.
     *
     * @return list<array<string,mixed>>
     */
    public static function forPlacement(
        string $placement,
        ?Request $request = null,
        int $limit = 1,
        string $device = 'desktop'
    ): array {
        if (!SettingsService::bool('ads.enabled', true)) {
            return [];
        }

        if (!isset(self::PLACEMENTS[$placement])) {
            return [];
        }

        // Respeta la senal "no rastrear" del navegador si asi se configuro.
        if ($request !== null
            && SettingsService::bool('ads.respect_do_not_track', true)
            && $request->header('dnt') === '1'
            && in_array($placement, ['while_browsing', 'on_exit', 'app_interstitial'], true)
        ) {
            return [];
        }

        $nowUtc = Clock::nowUtc();
        $local = Clock::nowLocal();
        $weekday = (int) $local->format('w');
        $timeNow = $local->format('H:i:s');

        $banners = QueryBuilder::table('banners')
            ->select([
                'banners.id', 'banners.name', 'banners.title', 'banners.subtitle', 'banners.body',
                'banners.image_path', 'banners.mobile_image_path', 'banners.video_url',
                'banners.cta_label', 'banners.cta_url', 'banners.background_color', 'banners.text_color',
                'banners.audience', 'banners.device_target', 'banners.max_views_per_user',
                'banners.cooldown_hours', 'banners.delay_seconds', 'banners.auto_close_seconds',
                'banners.is_dismissible', 'banners.priority', 'banners.weekdays',
                'banners.daily_from', 'banners.daily_to',
                'banner_placements.placement', 'banner_placements.page_pattern',
            ])
            ->join('banner_placements', 'banner_placements.banner_id', '=', 'banners.id')
            ->where('banner_placements.placement', $placement)
            ->where('banners.is_active', 1)
            ->whereNull('banners.deleted_at')
            ->whereGroup(static function (QueryBuilder $q) use ($nowUtc): void {
                $q->whereNull('banners.starts_at')->orWhere('banners.starts_at', '<=', $nowUtc);
            })
            ->whereGroup(static function (QueryBuilder $q) use ($nowUtc): void {
                $q->whereNull('banners.ends_at')->orWhere('banners.ends_at', '>=', $nowUtc);
            })
            ->orderBy('banners.priority', 'DESC')
            ->orderBy('banner_placements.sort_order')
            ->limit(20)
            ->get();

        $path = $request?->path() ?? '/';
        $userId = Auth::id();
        $visitorKey = self::visitorKey($request);
        $result = [];

        foreach ($banners as $banner) {
            if (!self::matchesWeekday((string) $banner['weekdays'], $weekday)) {
                continue;
            }

            if (!self::matchesDailyWindow($banner, $timeNow)) {
                continue;
            }

            if (!self::matchesDevice((string) $banner['device_target'], $device)) {
                continue;
            }

            if (!self::matchesPage((string) $banner['page_pattern'], $path)) {
                continue;
            }

            if (!self::matchesAudience((string) $banner['audience'], $userId)) {
                continue;
            }

            if (!self::withinFrequencyCap($banner, $userId, $visitorKey)) {
                continue;
            }

            $banner['image_url'] = $banner['image_path'] !== '' ? media_url((string) $banner['image_path']) : '';
            $banner['mobile_image_url'] = $banner['mobile_image_path'] !== ''
                ? media_url((string) $banner['mobile_image_path'])
                : $banner['image_url'];

            $result[] = $banner;

            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    /** Registra una impresion, clic o cierre para las metricas del panel. */
    public static function trackEvent(
        int $bannerId,
        string $eventType,
        ?Request $request = null,
        string $placement = '',
        string $device = ''
    ): void {
        if (!in_array($eventType, ['impression', 'click', 'dismiss'], true)) {
            return;
        }

        if (!QueryBuilder::table('banners')->where('id', $bannerId)->whereNull('deleted_at')->exists()) {
            return;
        }

        QueryBuilder::table('banner_events')->insert([
            'banner_id' => $bannerId,
            'user_id' => Auth::id(),
            'visitor_key' => self::visitorKey($request),
            'event_type' => $eventType,
            'placement' => mb_substr($placement, 0, 40),
            'device' => mb_substr($device, 0, 20),
            'created_at' => Clock::nowUtc(),
        ]);

        $column = match ($eventType) {
            'click' => 'clicks',
            'dismiss' => 'dismissals',
            default => 'impressions',
        };

        Database::instance()->statement(
            'UPDATE banners SET ' . $column . ' = ' . $column . ' + 1 WHERE id = :id',
            ['id' => $bannerId]
        );
    }

    /**
     * Identificador anonimo y estable del visitante.
     *
     * No guarda IP ni datos personales: solo un valor aleatorio en la sesion,
     * suficiente para no repetir el mismo anuncio y sin rastrear a nadie.
     */
    public static function visitorKey(?Request $request = null): string
    {
        $key = Session::get('__visitor_key');

        if (is_string($key) && strlen($key) === 64) {
            return $key;
        }

        $key = hash('sha256', bin2hex(random_bytes(16)) . '|' . microtime(true));
        Session::put('__visitor_key', $key);

        return $key;
    }

    private static function matchesWeekday(string $weekdays, int $today): bool
    {
        $weekdays = trim($weekdays);

        if ($weekdays === '') {
            return true;
        }

        $allowed = array_map('intval', array_filter(explode(',', $weekdays), 'is_numeric'));

        return $allowed === [] || in_array($today, $allowed, true);
    }

    /** @param array<string,mixed> $banner */
    private static function matchesDailyWindow(array $banner, string $now): bool
    {
        $from = $banner['daily_from'] ?? null;
        $to = $banner['daily_to'] ?? null;

        if ($from === null || $to === null) {
            return true;
        }

        return $now >= (string) $from && $now <= (string) $to;
    }

    private static function matchesDevice(string $target, string $device): bool
    {
        return $target === 'all' || $target === $device;
    }

    /** Coincidencia de ruta admitiendo el comodin final: "/servicios*". */
    private static function matchesPage(string $pattern, string $path): bool
    {
        $pattern = trim($pattern);

        if ($pattern === '' || $pattern === '*') {
            return true;
        }

        foreach (explode(',', $pattern) as $candidate) {
            $candidate = trim($candidate);

            if ($candidate === '') {
                continue;
            }

            if (str_ends_with($candidate, '*')) {
                if (str_starts_with($path, rtrim($candidate, '*'))) {
                    return true;
                }
            } elseif ($candidate === $path) {
                return true;
            }
        }

        return false;
    }

    private static function matchesAudience(string $audience, ?int $userId): bool
    {
        switch ($audience) {
            case 'all':
                return true;

            case 'guests':
                return $userId === null;

            case 'clients':
                return $userId !== null;

            case 'new_clients':
                if ($userId === null) {
                    return false;
                }

                return (int) (QueryBuilder::table('users')->where('id', $userId)->value('total_visits') ?? 0) === 0;

            case 'inactive_clients':
                if ($userId === null) {
                    return false;
                }

                $lastVisit = QueryBuilder::table('users')->where('id', $userId)->value('last_visit_at');

                if ($lastVisit === null) {
                    return false;
                }

                $days = SettingsService::int('ads.inactive_days', 60);

                return strtotime((string) $lastVisit) < strtotime("-{$days} days");

            default:
                return true;
        }
    }

    /**
     * Control de frecuencia: limita cuantas veces ve cada persona el anuncio
     * y cuanto debe esperar entre apariciones.
     *
     * @param array<string,mixed> $banner
     */
    private static function withinFrequencyCap(array $banner, ?int $userId, string $visitorKey): bool
    {
        $bannerId = (int) $banner['id'];
        $maxViews = (int) $banner['max_views_per_user'];
        $cooldownHours = (int) $banner['cooldown_hours'];

        if ($maxViews <= 0 && $cooldownHours <= 0) {
            return true;
        }

        $query = QueryBuilder::table('banner_events')
            ->where('banner_id', $bannerId)
            ->where('event_type', 'impression');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        } else {
            $query->where('visitor_key', $visitorKey);
        }

        if ($maxViews > 0 && $query->count() >= $maxViews) {
            return false;
        }

        if ($cooldownHours > 0) {
            $recent = QueryBuilder::table('banner_events')
                ->where('banner_id', $bannerId)
                ->where('event_type', 'impression')
                ->where('created_at', '>', gmdate('Y-m-d H:i:s', time() - $cooldownHours * 3600));

            if ($userId !== null) {
                $recent->where('user_id', $userId);
            } else {
                $recent->where('visitor_key', $visitorKey);
            }

            if ($recent->exists()) {
                return false;
            }
        }

        // Si la persona ya cerro el anuncio, no se le vuelve a mostrar hoy.
        $dismissed = QueryBuilder::table('banner_events')
            ->where('banner_id', $bannerId)
            ->where('event_type', 'dismiss')
            ->where('created_at', '>', gmdate('Y-m-d H:i:s', time() - 86400));

        if ($userId !== null) {
            $dismissed->where('user_id', $userId);
        } else {
            $dismissed->where('visitor_key', $visitorKey);
        }

        return !$dismissed->exists();
    }

    /**
     * Metricas para el informe del panel.
     *
     * @return array<string,mixed>
     */
    public static function stats(int $bannerId, int $days = 30): array
    {
        $since = gmdate('Y-m-d H:i:s', time() - $days * 86400);

        $rows = Database::instance()->select(
            'SELECT event_type, COUNT(*) AS total
               FROM banner_events
              WHERE banner_id = :id AND created_at >= :since
              GROUP BY event_type',
            ['id' => $bannerId, 'since' => $since]
        );

        $counts = ['impression' => 0, 'click' => 0, 'dismiss' => 0];

        foreach ($rows as $row) {
            $counts[(string) $row['event_type']] = (int) $row['total'];
        }

        $ctr = $counts['impression'] > 0
            ? round($counts['click'] / $counts['impression'] * 100, 2)
            : 0.0;

        return [
            'impressions' => $counts['impression'],
            'clicks' => $counts['click'],
            'dismissals' => $counts['dismiss'],
            'ctr' => $ctr,
            'days' => $days,
        ];
    }

    public static function detectDevice(?Request $request): string
    {
        $agent = strtolower($request?->userAgent() ?? '');

        if ($agent === '') {
            return 'desktop';
        }

        if (str_contains($agent, 'estiloapp')) {
            return 'app';
        }

        return preg_match('/android|iphone|ipad|ipod|mobile|windows phone/', $agent) === 1
            ? 'mobile'
            : 'desktop';
    }
}
