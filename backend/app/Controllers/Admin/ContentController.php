<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Clock;
use App\Core\HttpException;
use App\Core\Model;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Security\Audit;
use App\Security\Auth;
use App\Services\MediaService;

/** Contenido de la pagina web: secciones, galeria, resenas y preguntas. */
final class ContentController extends AdminController
{
    public function index(Request $request): Response
    {
        $this->authorize('contenido.ver');

        return $this->view('admin.content.index', [
            'blocks' => QueryBuilder::table('content_blocks')->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Secciones que vienen con el sistema. Las plantillas publicas las
     * buscan por esta clave, asi que se ocultan pero nunca se borran.
     *
     * @var list<string>
     */
    private const BLOQUES_DEL_SISTEMA = [
        'hero', 'about', 'services_intro', 'team_intro',
        'gallery_intro', 'reviews_intro', 'app_promo', 'contact',
    ];

    public function saveBlock(Request $request): Response
    {
        $this->authorize('contenido.editar');

        $id = $request->paramInt('id');
        $block = $id > 0 ? QueryBuilder::table('content_blocks')->where('id', $id)->first() : null;

        if ($id > 0 && $block === null) {
            throw new HttpException(404, 'La seccion no existe.');
        }

        $data = $this->validate($request, [
            'section_type' => 'optional|string|max:40|no_html',
            'title' => 'optional|string|max:200|no_html',
            'subtitle' => 'optional|string|max:300|no_html',
            'body' => 'optional|string|max:8000|no_html',
            'cta_label' => 'optional|string|max:80|no_html',
            'cta_url' => 'optional|string|max:500',
            'cta_secondary_label' => 'optional|string|max:80|no_html',
            'cta_secondary_url' => 'optional|string|max:500',
            'sort_order' => 'optional|int|between:0,999',
        ], ['title' => 'titulo', 'subtitle' => 'subtitulo', 'body' => 'texto']);

        $payload = [
            'title' => (string) ($data['title'] ?? ''),
            'subtitle' => (string) ($data['subtitle'] ?? ''),
            'body' => (string) ($data['body'] ?? ''),
            'cta_label' => (string) ($data['cta_label'] ?? ''),
            'cta_url' => $this->safeLink((string) ($data['cta_url'] ?? '')),
            'cta_secondary_label' => (string) ($data['cta_secondary_label'] ?? ''),
            'cta_secondary_url' => $this->safeLink((string) ($data['cta_secondary_url'] ?? '')),
            'is_active' => $request->bool('is_active') ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'updated_at' => Clock::nowUtc(),
        ];

        if ($request->hasFile('image')) {
            $payload['image_path'] = MediaService::replace(
                (string) ($block['image_path'] ?? ''),
                (array) $request->file('image'),
                'contenido',
                Auth::id(),
                1600
            );
        }

        if ($request->hasFile('background')) {
            $payload['background_path'] = MediaService::replace(
                (string) ($block['background_path'] ?? ''),
                (array) $request->file('background'),
                'contenido',
                Auth::id(),
                1920
            );
        }

        if ($id > 0) {
            QueryBuilder::table('content_blocks')->where('id', $id)->update($payload);
            Audit::record('contenido.actualizado', 'content_block', $id, $block, $payload, $request);
            Session::success('Seccion actualizada. Ya se ve en la web.');
        } else {
            $payload['section_type'] = (string) ($data['section_type'] ?? 'texto');
            $payload['block_key'] = $this->uniqueBlockKey((string) ($data['title'] ?? 'seccion'));
            $payload['created_at'] = Clock::nowUtc();
            $id = QueryBuilder::table('content_blocks')->insert($payload);
            Audit::record('contenido.creado', 'content_block', $id, null, $payload, $request);
            Session::success('Seccion creada. Ya se ve en la web.');
        }

        return $this->redirect('/panel/contenido');
    }

    /**
     * Elimina una seccion de la web.
     *
     * Las secciones que trae el sistema de fabrica no se borran: se apagan.
     * Las plantillas publicas las buscan por su clave, y si desaparecieran
     * la pagina quedaria rota.
     */
    public function deleteBlock(Request $request): Response
    {
        $this->authorize('contenido.editar');

        $id = $request->paramInt('id');
        $block = QueryBuilder::table('content_blocks')->where('id', $id)->first();

        if ($block === null) {
            throw new HttpException(404, 'La seccion no existe.');
        }

        if (in_array((string) $block['block_key'], self::BLOQUES_DEL_SISTEMA, true)) {
            QueryBuilder::table('content_blocks')->where('id', $id)->update([
                'is_active' => 0,
                'updated_at' => Clock::nowUtc(),
            ]);

            Audit::record('contenido.apagado', 'content_block', $id, $block, null, $request);
            Session::success('Esa seccion es parte del disenio de la web, asi que se oculto en vez de borrarse.');

            return $this->redirect('/panel/contenido');
        }

        QueryBuilder::table('content_blocks')->where('id', $id)->delete();
        Audit::record('contenido.eliminado', 'content_block', $id, $block, null, $request);
        Session::success('Seccion eliminada.');

        return $this->redirect('/panel/contenido');
    }

    /** Clave interna unica para una seccion nueva. */
    private function uniqueBlockKey(string $title): string
    {
        $base = \App\Core\Url::slug($title);
        $base = $base === '' ? 'seccion' : mb_substr(str_replace('-', '_', $base), 0, 40);
        $candidate = $base;
        $suffix = 1;

        while (QueryBuilder::table('content_blocks')->where('block_key', $candidate)->exists()) {
            $candidate = $base . '_' . (++$suffix);
        }

        return $candidate;
    }

    // ---- Galeria ---------------------------------------------------------

    public function gallery(Request $request): Response
    {
        $this->authorize('contenido.ver');

        $query = QueryBuilder::table('gallery_items')
            ->select(['gallery_items.*', 'service_categories.name AS category_name', 'staff.display_name AS staff_name'])
            ->leftJoin('service_categories', 'service_categories.id', '=', 'gallery_items.category_id')
            ->leftJoin('staff', 'staff.id', '=', 'gallery_items.staff_id')
            ->whereNull('gallery_items.deleted_at')
            ->orderBy('gallery_items.sort_order')
            ->orderBy('gallery_items.id', 'DESC');

        return $this->view('admin.gallery.index', [
            'result' => Model::paginate($query, $this->page($request), 24),
            'categories' => QueryBuilder::table('service_categories')->whereNull('deleted_at')->orderBy('sort_order')->get(),
            'staffList' => QueryBuilder::table('staff')->whereNull('deleted_at')->orderBy('display_name')->get(),
        ]);
    }

    public function saveGalleryItem(Request $request): Response
    {
        $this->authorize('contenido.editar');

        $id = $request->paramInt('id');
        $item = $id > 0 ? QueryBuilder::table('gallery_items')->where('id', $id)->first() : null;

        $data = $this->validate($request, [
            'title' => 'optional|string|max:160|no_html',
            'description' => 'optional|string|max:500|no_html',
            'sort_order' => 'optional|int|between:0,9999',
        ], ['title' => 'titulo']);

        if ($id === 0 && !$request->hasFile('image')) {
            Session::error('Selecciona una imagen para publicar en la galeria.');

            return $this->redirect('/panel/contenido/galeria');
        }

        $payload = [
            'title' => (string) ($data['title'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'category_id' => $request->int('category_id') ?: null,
            'staff_id' => $request->int('staff_id') ?: null,
            'is_active' => $request->bool('is_active') ? 1 : 0,
            'is_featured' => $request->bool('is_featured') ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'updated_at' => Clock::nowUtc(),
        ];

        if ($request->hasFile('image')) {
            $payload['image_path'] = MediaService::replace(
                (string) ($item['image_path'] ?? ''),
                (array) $request->file('image'),
                'galeria',
                Auth::id(),
                1400
            );
        }

        if ($request->hasFile('before_image')) {
            $payload['before_path'] = MediaService::replace(
                (string) ($item['before_path'] ?? ''),
                (array) $request->file('before_image'),
                'galeria',
                Auth::id(),
                1400
            );
        }

        if ($id > 0) {
            QueryBuilder::table('gallery_items')->where('id', $id)->update($payload);
        } else {
            $payload['created_at'] = Clock::nowUtc();
            $id = QueryBuilder::table('gallery_items')->insert($payload);
        }

        Audit::record('galeria.guardada', 'gallery_item', $id, $item, $payload, $request);
        Session::success('Galeria actualizada.');

        return $this->redirect('/panel/contenido/galeria');
    }

    public function deleteGalleryItem(Request $request): Response
    {
        $this->authorize('contenido.editar');

        $id = $request->paramInt('id');

        QueryBuilder::table('gallery_items')->where('id', $id)->update([
            'deleted_at' => Clock::nowUtc(),
            'is_active' => 0,
            'updated_at' => Clock::nowUtc(),
        ]);

        Audit::record('galeria.eliminada', 'gallery_item', $id, null, null, $request);
        Session::success('Imagen retirada de la galeria. El archivo se borrara en la proxima limpieza.');

        return $this->redirect('/panel/contenido/galeria');
    }

    // ---- Resenas ---------------------------------------------------------

    public function reviews(Request $request): Response
    {
        $this->authorize('contenido.ver');

        $query = QueryBuilder::table('reviews')
            ->select(['reviews.*', 'staff.display_name AS staff_name', 'appointments.code AS appointment_code'])
            ->leftJoin('staff', 'staff.id', '=', 'reviews.staff_id')
            ->leftJoin('appointments', 'appointments.id', '=', 'reviews.appointment_id')
            ->whereNull('reviews.deleted_at');

        $filter = $request->string('estado', 'pendientes');

        if ($filter === 'pendientes') {
            $query->where('reviews.is_approved', 0);
        } elseif ($filter === 'publicadas') {
            $query->where('reviews.is_approved', 1);
        }

        $query->orderBy('reviews.created_at', 'DESC');

        return $this->view('admin.content.reviews', [
            'result' => Model::paginate($query, $this->page($request), 20),
            'filter' => $filter,
        ]);
    }

    /** Elimina una resena de forma definitiva. */
    public function deleteReview(Request $request): Response
    {
        $this->authorize('contenido.editar');

        $id = $request->paramInt('id');
        $review = QueryBuilder::table('reviews')->where('id', $id)->first();

        if ($review === null) {
            throw new HttpException(404, 'La resena no existe.');
        }

        QueryBuilder::table('reviews')->where('id', $id)->delete();

        // La nota del profesional se calcula con las resenas publicadas, asi
        // que hay que recalcularla despues de quitar una.
        $this->refreshStaffRating($review['staff_id'] === null ? null : (int) $review['staff_id']);

        Audit::record('resena.eliminada', 'review', $id, $review, null, $request);
        Session::success('Resena eliminada.');

        return $this->redirect('/panel/contenido/resenas');
    }

    public function moderateReview(Request $request): Response
    {
        $this->authorize('contenido.editar');

        $id = $request->paramInt('id');
        $action = $request->string('action');

        $review = QueryBuilder::table('reviews')->where('id', $id)->first();

        if ($review === null) {
            throw new HttpException(404, 'La resena no existe.');
        }

        switch ($action) {
            case 'aprobar':
                QueryBuilder::table('reviews')->where('id', $id)->update([
                    'is_approved' => 1,
                    'updated_at' => Clock::nowUtc(),
                ]);
                $this->refreshStaffRating($review['staff_id'] === null ? null : (int) $review['staff_id']);
                Session::success('Resena publicada.');
                break;

            case 'destacar':
                QueryBuilder::table('reviews')->where('id', $id)->update([
                    'is_featured' => (bool) $review['is_featured'] ? 0 : 1,
                    'updated_at' => Clock::nowUtc(),
                ]);
                Session::success('Destacado actualizado.');
                break;

            case 'responder':
                $reply = $request->string('reply');

                QueryBuilder::table('reviews')->where('id', $id)->update([
                    'reply' => mb_substr(strip_tags($reply), 0, 2000),
                    'replied_at' => Clock::nowUtc(),
                    'updated_at' => Clock::nowUtc(),
                ]);
                Session::success('Respuesta publicada.');
                break;

            case 'eliminar':
                QueryBuilder::table('reviews')->where('id', $id)->update([
                    'deleted_at' => Clock::nowUtc(),
                    'is_approved' => 0,
                    'updated_at' => Clock::nowUtc(),
                ]);
                $this->refreshStaffRating($review['staff_id'] === null ? null : (int) $review['staff_id']);
                Session::success('Resena eliminada.');
                break;

            default:
                Session::error('Accion no reconocida.');
        }

        Audit::record('resena.' . $action, 'review', $id, null, null, $request);

        return $this->back($request, '/panel/contenido/resenas');
    }

    /** Recalcula la valoracion media del profesional tras moderar. */
    private function refreshStaffRating(?int $staffId): void
    {
        if ($staffId === null) {
            return;
        }

        $row = \App\Core\Database::instance()->selectOne(
            'SELECT COUNT(*) AS total, COALESCE(AVG(rating), 0) AS media
               FROM reviews
              WHERE staff_id = :id AND is_approved = 1 AND deleted_at IS NULL',
            ['id' => $staffId]
        );

        QueryBuilder::table('staff')->where('id', $staffId)->update([
            'rating_count' => (int) ($row['total'] ?? 0),
            'rating_average' => round((float) ($row['media'] ?? 0), 2),
            'updated_at' => Clock::nowUtc(),
        ]);
    }

    // ---- Preguntas frecuentes --------------------------------------------

    public function faqs(Request $request): Response
    {
        $this->authorize('contenido.ver');

        return $this->view('admin.content.faqs', [
            'faqs' => QueryBuilder::table('faqs')->orderBy('sort_order')->get(),
        ]);
    }

    public function saveFaq(Request $request): Response
    {
        $this->authorize('contenido.editar');

        $id = $request->paramInt('id');

        $data = $this->validate($request, [
            'question' => 'required|string|min:5|max:300|no_html',
            'answer' => 'required|string|min:5|max:4000|no_html',
            'sort_order' => 'optional|int|between:0,999',
        ], ['question' => 'pregunta', 'answer' => 'respuesta']);

        $payload = [
            'question' => $data['question'],
            'answer' => $data['answer'],
            'is_active' => $request->bool('is_active') ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'updated_at' => Clock::nowUtc(),
        ];

        if ($id > 0) {
            QueryBuilder::table('faqs')->where('id', $id)->update($payload);
        } else {
            $payload['created_at'] = Clock::nowUtc();
            QueryBuilder::table('faqs')->insert($payload);
        }

        Session::success('Pregunta guardada.');

        return $this->redirect('/panel/contenido/preguntas');
    }

    public function deleteFaq(Request $request): Response
    {
        $this->authorize('contenido.editar');

        QueryBuilder::table('faqs')->where('id', $request->paramInt('id'))->delete();
        Session::success('Pregunta eliminada.');

        return $this->redirect('/panel/contenido/preguntas');
    }

    // ---- Mensajes de contacto --------------------------------------------

    public function messages(Request $request): Response
    {
        $this->authorize('contenido.ver');

        $query = QueryBuilder::table('contact_messages')
            ->whereNull('deleted_at')
            ->orderBy('is_read')
            ->orderBy('created_at', 'DESC');

        return $this->view('admin.content.messages', [
            'result' => Model::paginate($query, $this->page($request), 25),
        ]);
    }

    public function markMessageRead(Request $request): Response
    {
        $this->authorize('contenido.ver');

        QueryBuilder::table('contact_messages')->where('id', $request->paramInt('id'))->update([
            'is_read' => 1,
            'replied_at' => Clock::nowUtc(),
        ]);

        return $this->back($request, '/panel/contenido/mensajes');
    }

    public function deleteMessage(Request $request): Response
    {
        $this->authorize('contenido.editar');

        QueryBuilder::table('contact_messages')->where('id', $request->paramInt('id'))->update([
            'deleted_at' => Clock::nowUtc(),
        ]);

        Session::success('Mensaje eliminado.');

        return $this->redirect('/panel/contenido/mensajes');
    }

    /** Un enlace solo puede ser interno o http(s). */
    private function safeLink(string $url): string
    {
        $url = trim($url);

        if ($url === '' || str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true) ? $url : '';
    }
}
