<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\BannerService;
use App\Services\SettingsService;

/** Publicidad dentro de la app movil. */
final class AdController extends ApiController
{
    /**
     * Anuncios de una ubicacion de la app.
     *
     * Ubicaciones validas: app_splash (bienvenida), app_home_card (tarjeta en
     * el inicio) y app_interstitial (pantalla completa entre secciones).
     */
    public function index(Request $request): Response
    {
        if (!SettingsService::bool('ads.enabled', true)) {
            return $this->ok(['banners' => []]);
        }

        $placement = $request->string('placement', 'app_home_card');

        if (!in_array($placement, ['app_splash', 'app_home_card', 'app_interstitial'], true)) {
            return $this->ok(['banners' => []]);
        }

        if ($placement === 'app_splash' && !SettingsService::bool('app.show_splash_ad', true)) {
            return $this->ok(['banners' => []]);
        }

        $limit = max(1, min(5, $request->int('limit', 1)));
        $banners = BannerService::forPlacement($placement, $request, $limit, 'app');

        return $this->ok([
            'banners' => array_map(static fn (array $banner): array => [
                'id' => (int) $banner['id'],
                'title' => (string) $banner['title'],
                'subtitle' => (string) $banner['subtitle'],
                'body' => (string) $banner['body'],
                'image_url' => (string) $banner['mobile_image_url'] !== ''
                    ? (string) $banner['mobile_image_url']
                    : (string) $banner['image_url'],
                'cta_label' => (string) $banner['cta_label'],
                'cta_url' => (string) $banner['cta_url'],
                'background_color' => (string) $banner['background_color'],
                'text_color' => (string) $banner['text_color'],
                'delay_seconds' => (int) $banner['delay_seconds'],
                'auto_close_seconds' => (int) $banner['auto_close_seconds'],
                'is_dismissible' => (bool) $banner['is_dismissible'],
                'placement' => $placement,
            ], $banners),
        ]);
    }

    /** Registra vista, clic o cierre desde la app. */
    public function track(Request $request): Response
    {
        $bannerId = $request->int('banner_id');
        $event = $request->string('event');

        if ($bannerId > 0) {
            BannerService::trackEvent($bannerId, $event, $request, $request->string('placement'), 'app');
        }

        return Response::noContent();
    }
}
