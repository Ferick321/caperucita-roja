<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Clock;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Security\Audit;
use App\Security\Auth;
use App\Services\BannerService;
use App\Services\MediaService;
use App\Services\StatsService;

/**
 * Publicidad: crear anuncios, decidir donde y cuando aparecen y ver su
 * rendimiento. Todo sin escribir una linea de codigo.
 */
final class BannerController extends AdminController
{
    public function index(Request $request): Response
    {
        $this->authorize('publicidad.ver');

        $banners = QueryBuilder::table('banners')
            ->whereNull('deleted_at')
            ->orderBy('is_active', 'DESC')
            ->orderBy('priority', 'DESC')
            ->orderBy('id', 'DESC')
            ->get();

        foreach ($banners as $index => $banner) {
            $banners[$index]['placements'] = QueryBuilder::table('banner_placements')
                ->where('banner_id', (int) $banner['id'])
                ->pluck('placement');

            $banners[$index]['stats'] = BannerService::stats((int) $banner['id'], 30);
        }

        return $this->view('admin.banners.index', [
            'banners' => $banners,
            'placements' => BannerService::PLACEMENTS,
            'performance' => StatsService::bannerPerformance(30),
        ]);
    }

    public function form(Request $request): Response
    {
        $this->authorize('publicidad.editar');

        $id = $request->paramInt('id');
        $banner = $id > 0 ? QueryBuilder::table('banners')->where('id', $id)->first() : null;

        if ($id > 0 && $banner === null) {
            throw new HttpException(404, 'El anuncio no existe.');
        }

        return $this->view('admin.banners.form', [
            'banner' => $banner,
            'placements' => BannerService::PLACEMENTS,
            'selectedPlacements' => $id > 0
                ? QueryBuilder::table('banner_placements')->where('banner_id', $id)->pluck('placement')
                : [],
            'pagePattern' => $id > 0
                ? (string) (QueryBuilder::table('banner_placements')->where('banner_id', $id)->value('page_pattern') ?? '*')
                : '*',
            'stats' => $id > 0 ? BannerService::stats($id, 30) : null,
        ]);
    }

    public function save(Request $request): Response
    {
        $this->authorize('publicidad.editar');

        $id = $request->paramInt('id');

        $data = $this->validate($request, [
            'name' => 'required|string|min:2|max:140|no_html',
            'title' => 'optional|string|max:200|no_html',
            'subtitle' => 'optional|string|max:300|no_html',
            'body' => 'optional|string|max:2000|no_html',
            'cta_label' => 'optional|string|max:80|no_html',
            'cta_url' => 'optional|string|max:500',
            'background_color' => 'optional|hex_color',
            'text_color' => 'optional|hex_color',
            'starts_at' => 'optional|date',
            'ends_at' => 'optional|date',
            'daily_from' => 'optional|time',
            'daily_to' => 'optional|time',
            'audience' => 'optional|in:all,guests,clients,new_clients,inactive_clients',
            'device_target' => 'optional|in:all,desktop,mobile,app',
            'max_views_per_user' => 'optional|int|between:0,999',
            'cooldown_hours' => 'optional|int|between:0,8760',
            'delay_seconds' => 'optional|int|between:0,600',
            'auto_close_seconds' => 'optional|int|between:0,120',
            'priority' => 'optional|int|between:-100,100',
            'page_pattern' => 'optional|string|max:120',
        ], ['name' => 'nombre interno', 'cta_url' => 'enlace del boton']);

        $existing = $id > 0 ? QueryBuilder::table('banners')->where('id', $id)->first() : null;

        // El enlace del boton solo admite rutas internas o direcciones http(s).
        $ctaUrl = trim((string) ($data['cta_url'] ?? ''));

        if ($ctaUrl !== '' && !str_starts_with($ctaUrl, '/')) {
            $scheme = strtolower((string) parse_url($ctaUrl, PHP_URL_SCHEME));

            if (!in_array($scheme, ['http', 'https'], true)) {
                Session::error('El enlace del boton debe empezar por "/" o por https://');

                return $this->back($request, '/panel/publicidad');
            }
        }

        $weekdays = implode(',', array_filter(
            array_map('intval', $request->array('weekdays')),
            static fn (int $d): bool => $d >= 0 && $d <= 6
        ));

        $payload = [
            'name' => $data['name'],
            'title' => (string) ($data['title'] ?? ''),
            'subtitle' => (string) ($data['subtitle'] ?? ''),
            'body' => (string) ($data['body'] ?? ''),
            'cta_label' => (string) ($data['cta_label'] ?? ''),
            'cta_url' => $ctaUrl,
            'background_color' => (string) ($data['background_color'] ?? '#111827'),
            'text_color' => (string) ($data['text_color'] ?? '#ffffff'),
            'starts_at' => !empty($data['starts_at']) ? Clock::localToUtc((string) $data['starts_at'] . ' 00:00:00') : null,
            'ends_at' => !empty($data['ends_at']) ? Clock::localToUtc((string) $data['ends_at'] . ' 23:59:59') : null,
            'weekdays' => $weekdays,
            'daily_from' => !empty($data['daily_from']) ? (string) $data['daily_from'] : null,
            'daily_to' => !empty($data['daily_to']) ? (string) $data['daily_to'] : null,
            'audience' => (string) ($data['audience'] ?? 'all'),
            'device_target' => (string) ($data['device_target'] ?? 'all'),
            'max_views_per_user' => (int) ($data['max_views_per_user'] ?? 0),
            'cooldown_hours' => (int) ($data['cooldown_hours'] ?? 24),
            'delay_seconds' => (int) ($data['delay_seconds'] ?? 0),
            'auto_close_seconds' => (int) ($data['auto_close_seconds'] ?? 0),
            'is_dismissible' => $request->bool('is_dismissible') ? 1 : 0,
            'priority' => (int) ($data['priority'] ?? 0),
            'is_active' => $request->bool('is_active') ? 1 : 0,
            'updated_at' => Clock::nowUtc(),
        ];

        if ($request->hasFile('image')) {
            $payload['image_path'] = MediaService::replace(
                (string) ($existing['image_path'] ?? ''),
                (array) $request->file('image'),
                'publicidad',
                Auth::id(),
                1600
            );
        }

        if ($request->hasFile('mobile_image')) {
            $payload['mobile_image_path'] = MediaService::replace(
                (string) ($existing['mobile_image_path'] ?? ''),
                (array) $request->file('mobile_image'),
                'publicidad',
                Auth::id(),
                800
            );
        }

        if ($id > 0) {
            QueryBuilder::table('banners')->where('id', $id)->update($payload);
        } else {
            $payload['created_by'] = Auth::id();
            $payload['created_at'] = Clock::nowUtc();
            $id = QueryBuilder::table('banners')->insert($payload);
        }

        // Ubicaciones donde aparece.
        $selected = array_values(array_intersect(
            array_map('strval', $request->array('placements')),
            array_keys(BannerService::PLACEMENTS)
        ));

        $pattern = trim((string) ($data['page_pattern'] ?? '*')) ?: '*';

        Database::instance()->transaction(static function () use ($id, $selected, $pattern): void {
            QueryBuilder::table('banner_placements')->where('banner_id', $id)->delete();

            foreach ($selected as $order => $placement) {
                QueryBuilder::table('banner_placements')->insert([
                    'banner_id' => $id,
                    'placement' => $placement,
                    'page_pattern' => $pattern,
                    'sort_order' => $order,
                ]);
            }
        });

        Audit::record('publicidad.guardada', 'banner', $id, $existing, $payload, $request);
        Session::success($selected === []
            ? 'Anuncio guardado, pero no se mostrara hasta que elijas al menos una ubicacion.'
            : 'Anuncio guardado y publicado en ' . count($selected) . ' ubicacion(es).');

        return $this->redirect('/panel/publicidad');
    }

    public function toggle(Request $request): Response
    {
        $this->authorize('publicidad.editar');

        $id = $request->paramInt('id');
        $banner = QueryBuilder::table('banners')->where('id', $id)->first();

        if ($banner === null) {
            throw new HttpException(404, 'El anuncio no existe.');
        }

        $newState = (bool) $banner['is_active'] ? 0 : 1;

        QueryBuilder::table('banners')->where('id', $id)->update([
            'is_active' => $newState,
            'updated_at' => Clock::nowUtc(),
        ]);

        Session::success($newState === 1 ? 'Anuncio activado.' : 'Anuncio pausado.');

        return $this->redirect('/panel/publicidad');
    }

    public function delete(Request $request): Response
    {
        $this->authorize('publicidad.editar');

        $id = $request->paramInt('id');

        QueryBuilder::table('banners')->where('id', $id)->update([
            'deleted_at' => Clock::nowUtc(),
            'is_active' => 0,
            'updated_at' => Clock::nowUtc(),
        ]);

        Audit::record('publicidad.eliminada', 'banner', $id, null, null, $request);
        Session::success('Anuncio eliminado.');

        return $this->redirect('/panel/publicidad');
    }

    /** Reinicia el contador de vistas para volver a mostrarlo a todos. */
    public function resetStats(Request $request): Response
    {
        $this->authorize('publicidad.editar');

        $id = $request->paramInt('id');

        QueryBuilder::table('banner_events')->where('banner_id', $id)->delete();
        QueryBuilder::table('banners')->where('id', $id)->update([
            'impressions' => 0,
            'clicks' => 0,
            'dismissals' => 0,
            'updated_at' => Clock::nowUtc(),
        ]);

        Audit::record('publicidad.metricas_reiniciadas', 'banner', $id, null, null, $request);
        Session::success('Metricas reiniciadas. El anuncio volvera a mostrarse a todos los visitantes.');

        return $this->redirect('/panel/publicidad');
    }
}
