<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\BannerService;
use App\Services\CampaignService;
use App\Services\SettingsService;

/** Entrega y medicion de la publicidad en la web. */
final class AdController extends Controller
{
    /**
     * Devuelve el anuncio que corresponde a una ubicacion.
     * La pagina lo pide de forma asincrona para no retrasar la carga inicial.
     */
    public function fetch(Request $request): Response
    {
        $placement = $request->string('placement');
        $device = BannerService::detectDevice($request);

        // Tope de ventanas emergentes por visita, configurable en el panel.
        $popupPlacements = ['on_login', 'while_browsing', 'on_exit'];

        if (in_array($placement, $popupPlacements, true)) {
            $shown = (int) Session::get('__popups_shown', 0);
            $max = SettingsService::int('ads.max_popups_per_session', 2);

            if ($max > 0 && $shown >= $max) {
                return Response::apiOk(['banner' => null]);
            }
        }

        $enabledKey = match ($placement) {
            'on_login' => 'ads.show_on_login',
            'while_browsing' => 'ads.show_while_browsing',
            'on_exit' => 'ads.show_on_exit',
            default => '',
        };

        if ($enabledKey !== '' && !SettingsService::bool($enabledKey, true)) {
            return Response::apiOk(['banner' => null]);
        }

        $banners = BannerService::forPlacement($placement, $request, 1, $device);

        if ($banners === []) {
            return Response::apiOk(['banner' => null]);
        }

        if (in_array($placement, $popupPlacements, true)) {
            Session::put('__popups_shown', (int) Session::get('__popups_shown', 0) + 1);
        }

        $banner = $banners[0];

        return Response::apiOk([
            'banner' => [
                'id' => (int) $banner['id'],
                'title' => (string) $banner['title'],
                'subtitle' => (string) $banner['subtitle'],
                'body' => (string) $banner['body'],
                'image_url' => (string) $banner['image_url'],
                'mobile_image_url' => (string) $banner['mobile_image_url'],
                'cta_label' => (string) $banner['cta_label'],
                'cta_url' => (string) $banner['cta_url'],
                'background_color' => (string) $banner['background_color'],
                'text_color' => (string) $banner['text_color'],
                'delay_seconds' => (int) $banner['delay_seconds'],
                'auto_close_seconds' => (int) $banner['auto_close_seconds'],
                'is_dismissible' => (bool) $banner['is_dismissible'],
                'placement' => $placement,
            ],
        ]);
    }

    /** Registra impresion, clic o cierre. */
    public function track(Request $request): Response
    {
        $bannerId = $request->int('banner_id');
        $event = $request->string('event');

        if ($bannerId > 0) {
            BannerService::trackEvent(
                $bannerId,
                $event,
                $request,
                $request->string('placement'),
                BannerService::detectDevice($request)
            );
        }

        return Response::noContent();
    }

    /** Baja de las comunicaciones comerciales desde el enlace del correo. */
    public function unsubscribe(Request $request): Response
    {
        $token = (string) $request->param('token');
        $done = CampaignService::unsubscribe($token);

        if (!$done) {
            // Puede tratarse de un suscriptor del boletin y no de un cliente.
            $subscriber = \App\Core\QueryBuilder::table('subscribers')
                ->where('unsubscribe_token', $token)
                ->first();

            if ($subscriber !== null) {
                \App\Core\QueryBuilder::table('subscribers')
                    ->where('id', (int) $subscriber['id'])
                    ->update(['unsubscribed_at' => \App\Core\Clock::nowUtc()]);
                $done = true;
            }
        }

        return $this->view('web.unsubscribe', ['done' => $done]);
    }

    /** Pixel de apertura de los correos de campana. */
    public function trackOpen(Request $request): Response
    {
        CampaignService::trackOpen((string) $request->param('token'));

        // GIF transparente de 1x1.
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', true);

        return Response::make($gif === false ? '' : $gif)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
