<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\Controller;
use App\Core\Clock;
use App\Core\HttpException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Security\RateLimiter;
use App\Services\BannerService;
use App\Services\SettingsService;

/** Sitio publico: portada, catalogo, equipo, galeria y contacto. */
final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $device = BannerService::detectDevice($request);

        return $this->view('web.home', [
            'blocks' => $this->contentBlocks(),
            'categories' => $this->activeCategories(),
            'featuredServices' => $this->featuredServices(),
            'team' => $this->team(6),
            'gallery' => $this->galleryItems(8),
            'reviews' => $this->approvedReviews(6),
            'faqs' => QueryBuilder::table('faqs')->where('is_active', 1)->orderBy('sort_order')->get(),
            'branches' => $this->branches(),
            'stats' => $this->publicStats(),
            'heroBanners' => BannerService::forPlacement('web_hero', $request, 3, $device),
            'stripBanner' => BannerService::forPlacement('web_strip', $request, 1, $device)[0] ?? null,
            'sidebarBanner' => BannerService::forPlacement('web_sidebar', $request, 1, $device)[0] ?? null,
        ]);
    }

    public function services(Request $request): Response
    {
        $categorySlug = $request->string('categoria');

        $query = QueryBuilder::table('services')
            ->select([
                'services.*',
                'service_categories.name AS category_name',
                'service_categories.slug AS category_slug',
                'service_categories.color AS category_color',
            ])
            ->join('service_categories', 'service_categories.id', '=', 'services.category_id')
            ->where('services.is_active', 1)
            ->whereNull('services.deleted_at')
            ->where('service_categories.is_active', 1);

        if ($categorySlug !== '') {
            $query->where('service_categories.slug', $categorySlug);
        }

        $search = $request->string('q');

        if ($search !== '') {
            $query->search($search, ['services.name', 'services.short_description']);
        }

        return $this->view('web.services', [
            'services' => $query->orderBy('service_categories.sort_order')->orderBy('services.sort_order')->get(),
            'categories' => $this->activeCategories(),
            'activeCategory' => $categorySlug,
            'search' => $search,
            'blocks' => $this->contentBlocks(),
        ]);
    }

    public function serviceDetail(Request $request): Response
    {
        $slug = (string) $request->param('slug');

        $service = QueryBuilder::table('services')
            ->select(['services.*', 'service_categories.name AS category_name', 'service_categories.slug AS category_slug'])
            ->join('service_categories', 'service_categories.id', '=', 'services.category_id')
            ->where('services.slug', $slug)
            ->where('services.is_active', 1)
            ->whereNull('services.deleted_at')
            ->first();

        if ($service === null) {
            throw new HttpException(404, 'No encontramos ese servicio.');
        }

        $staff = QueryBuilder::table('staff')
            ->select(['staff.*'])
            ->join('staff_services', 'staff_services.staff_id', '=', 'staff.id')
            ->where('staff_services.service_id', (int) $service['id'])
            ->where('staff.is_active', 1)
            ->where('staff.show_on_web', 1)
            ->whereNull('staff.deleted_at')
            ->get();

        return $this->view('web.service_detail', [
            'service' => $service,
            'staff' => $staff,
            'related' => QueryBuilder::table('services')
                ->where('category_id', (int) $service['category_id'])
                ->where('id', '!=', (int) $service['id'])
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->limit(4)
                ->get(),
        ]);
    }

    public function team(int $limit = 0): array
    {
        $query = QueryBuilder::table('staff')
            ->where('is_active', 1)
            ->where('show_on_web', 1)
            ->whereNull('deleted_at')
            ->orderBy('sort_order');

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function teamPage(Request $request): Response
    {
        $team = $this->team();

        foreach ($team as $index => $member) {
            $team[$index]['services'] = QueryBuilder::table('services')
                ->select(['services.name'])
                ->join('staff_services', 'staff_services.service_id', '=', 'services.id')
                ->where('staff_services.staff_id', (int) $member['id'])
                ->where('services.is_active', 1)
                ->limit(6)
                ->pluck('name');
        }

        return $this->view('web.team', ['team' => $team, 'blocks' => $this->contentBlocks()]);
    }

    public function gallery(Request $request): Response
    {
        return $this->view('web.gallery', [
            'items' => $this->galleryItems(60),
            'categories' => $this->activeCategories(),
            'blocks' => $this->contentBlocks(),
        ]);
    }

    public function contact(Request $request): Response
    {
        return $this->view('web.contact', [
            'branches' => $this->branches(),
            'blocks' => $this->contentBlocks(),
            'faqs' => QueryBuilder::table('faqs')->where('is_active', 1)->orderBy('sort_order')->get(),
        ]);
    }

    public function submitContact(Request $request): Response
    {
        $this->assertNotBot($request);

        $limit = RateLimiter::hit('contacto:' . $request->ip(), 5, 3600);

        if (!$limit['allowed']) {
            throw new HttpException(429, 'Ya enviaste varios mensajes. Intentalo mas tarde.');
        }

        $data = $this->validate($request, [
            'name' => 'required|string|min:2|max:120|no_html',
            'email' => 'optional|email',
            'phone' => 'optional|phone',
            'subject' => 'optional|string|max:200|no_html',
            'message' => 'required|string|min:10|max:2000|no_html',
        ], [
            'name' => 'nombre',
            'email' => 'correo',
            'phone' => 'telefono',
            'subject' => 'asunto',
            'message' => 'mensaje',
        ]);

        if (($data['email'] ?? '') === '' && ($data['phone'] ?? '') === '') {
            Session::error('Dejanos un correo o un telefono para poder responderte.');

            return $this->back($request, '/contacto');
        }

        QueryBuilder::table('contact_messages')->insert([
            'name' => $data['name'],
            'email' => (string) ($data['email'] ?? ''),
            'phone' => (string) ($data['phone'] ?? ''),
            'subject' => (string) ($data['subject'] ?? 'Consulta desde la web'),
            'message' => $data['message'],
            'ip_address' => $request->ip(),
            'created_at' => Clock::nowUtc(),
        ]);

        Session::success('Recibimos tu mensaje. Te responderemos muy pronto.');

        return $this->redirect('/contacto');
    }

    public function appDownload(Request $request): Response
    {
        return $this->view('web.app', [
            'blocks' => $this->contentBlocks(),
            'android' => SettingsService::string('app.download_url_android', ''),
            'ios' => SettingsService::string('app.download_url_ios', ''),
            'apk' => SettingsService::string('app.apk_direct_url', ''),
            'version' => SettingsService::string('app.latest_version', '1.0.0'),
        ]);
    }

    public function legal(Request $request): Response
    {
        $page = (string) $request->param('page');

        $content = match ($page) {
            'privacidad' => ['Politica de privacidad', SettingsService::string('legal.privacy_policy', '')],
            'terminos' => ['Terminos y condiciones', SettingsService::string('legal.terms', '')],
            default => throw new HttpException(404, 'Pagina no encontrada.'),
        };

        return $this->view('web.legal', ['title' => $content[0], 'content' => $content[1]]);
    }

    /** Registro al boletin desde el pie de pagina. */
    public function subscribe(Request $request): Response
    {
        $this->assertNotBot($request);

        $limit = RateLimiter::hit('boletin:' . $request->ip(), 5, 3600);

        if (!$limit['allowed']) {
            throw new HttpException(429, 'Demasiados intentos. Espera un momento.');
        }

        $data = $this->validate($request, [
            'email' => 'required|email',
            'name' => 'optional|string|max:120|no_html',
        ], ['email' => 'correo', 'name' => 'nombre']);

        $existing = QueryBuilder::table('subscribers')->where('email', $data['email'])->first();

        if ($existing === null) {
            QueryBuilder::table('subscribers')->insert([
                'email' => $data['email'],
                'name' => (string) ($data['name'] ?? ''),
                'source' => 'web',
                'is_confirmed' => 1,
                'confirmed_at' => Clock::nowUtc(),
                'unsubscribe_token' => bin2hex(random_bytes(16)),
                'consent_ip' => $request->ip(),
                'created_at' => Clock::nowUtc(),
            ]);
        } else {
            QueryBuilder::table('subscribers')->where('id', (int) $existing['id'])->update([
                'unsubscribed_at' => null,
                'is_confirmed' => 1,
            ]);
        }

        Session::success('Listo! Te avisaremos de nuestras promociones.');

        return $this->back($request, '/');
    }

    // ---- Consultas compartidas -----------------------------------------

    /** @return array<string,array<string,mixed>> */
    public static function sharedContentBlocks(): array
    {
        $blocks = QueryBuilder::table('content_blocks')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get();

        $indexed = [];

        foreach ($blocks as $block) {
            $indexed[(string) $block['block_key']] = $block;
        }

        return $indexed;
    }

    /** @return array<string,array<string,mixed>> */
    private function contentBlocks(): array
    {
        return self::sharedContentBlocks();
    }

    /** @return list<array<string,mixed>> */
    private function activeCategories(): array
    {
        return QueryBuilder::table('service_categories')
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->get();
    }

    /** @return list<array<string,mixed>> */
    private function featuredServices(): array
    {
        return QueryBuilder::table('services')
            ->select(['services.*', 'service_categories.name AS category_name', 'service_categories.color AS category_color'])
            ->join('service_categories', 'service_categories.id', '=', 'services.category_id')
            ->where('services.is_active', 1)
            ->where('services.is_featured', 1)
            ->whereNull('services.deleted_at')
            ->orderBy('services.sort_order')
            ->limit(8)
            ->get();
    }

    /** @return list<array<string,mixed>> */
    private function galleryItems(int $limit): array
    {
        return QueryBuilder::table('gallery_items')
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->limit($limit)
            ->get();
    }

    /** @return list<array<string,mixed>> */
    private function approvedReviews(int $limit): array
    {
        return QueryBuilder::table('reviews')
            ->where('is_approved', 1)
            ->whereNull('deleted_at')
            ->orderBy('is_featured', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Cifras reales para la portada. Se ocultan las que esten en cero para
     * que un negocio recien instalado no muestre "0 profesionales".
     *
     * @return array<string,int>
     */
    private function publicStats(): array
    {
        return [
            'services' => QueryBuilder::table('services')
                ->where('is_active', 1)->whereNull('deleted_at')->count(),
            'staff' => QueryBuilder::table('staff')
                ->where('is_active', 1)->where('show_on_web', 1)->whereNull('deleted_at')->count(),
            'completed' => QueryBuilder::table('appointments')
                ->where('status', 'completed')->whereNull('deleted_at')->count(),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function branches(): array
    {
        return QueryBuilder::table('branches')
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->get();
    }
}
