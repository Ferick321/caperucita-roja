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
use App\Core\Url;
use App\Security\Audit;
use App\Security\Auth;
use App\Services\MediaService;

/** Catalogo: categorias y servicios, editables sin tocar codigo. */
final class CatalogController extends AdminController
{
    // ---- Servicios -------------------------------------------------------

    public function services(Request $request): Response
    {
        $this->authorize('servicios.ver');

        $query = QueryBuilder::table('services')
            ->select(['services.*', 'service_categories.name AS category_name', 'service_categories.color AS category_color'])
            ->join('service_categories', 'service_categories.id', '=', 'services.category_id')
            ->whereNull('services.deleted_at');

        $categoryId = $request->int('categoria');
        if ($categoryId > 0) {
            $query->where('services.category_id', $categoryId);
        }

        $search = $request->string('q');
        if ($search !== '') {
            $query->search($search, ['services.name', 'services.short_description']);
        }

        $query->orderBy('service_categories.sort_order')->orderBy('services.sort_order');

        return $this->view('admin.services.index', [
            'result' => Model::paginate($query, $this->page($request), 30),
            'categories' => $this->categoriesList(),
            'filters' => ['categoria' => $categoryId, 'q' => $search],
        ]);
    }

    public function serviceForm(Request $request): Response
    {
        $this->authorize('servicios.editar');

        $id = $request->paramInt('id');
        $service = $id > 0 ? QueryBuilder::table('services')->where('id', $id)->first() : null;

        if ($id > 0 && $service === null) {
            throw new HttpException(404, 'El servicio no existe.');
        }

        return $this->view('admin.services.form', [
            'service' => $service,
            'categories' => $this->categoriesList(),
            'staffList' => QueryBuilder::table('staff')->whereNull('deleted_at')->orderBy('display_name')->get(),
            'assignedStaff' => $service === null
                ? []
                : QueryBuilder::table('staff_services')->where('service_id', $id)->pluck('staff_id'),
        ]);
    }

    public function saveService(Request $request): Response
    {
        $this->authorize('servicios.editar');

        $id = $request->paramInt('id');

        $data = $this->validate($request, [
            'name' => 'required|string|min:2|max:140|no_html',
            'category_id' => 'required|int|min:1',
            'short_description' => 'optional|string|max:255|no_html',
            'description' => 'optional|string|max:5000',
            'duration_minutes' => 'required|int|between:5,600',
            'buffer_before_minutes' => 'optional|int|between:0,120',
            'buffer_after_minutes' => 'optional|int|between:0,120',
            'price' => 'required|numeric|min:0|max:999999',
            'promo_price' => 'optional|numeric|min:0|max:999999',
            'promo_starts_at' => 'optional|date',
            'promo_ends_at' => 'optional|date',
            'deposit_amount' => 'optional|numeric|min:0',
            'max_per_day' => 'optional|int|between:0,500',
            'loyalty_points' => 'optional|int|between:0,10000',
            'gender_target' => 'optional|in:all,male,female,kids',
            'sort_order' => 'optional|int|between:0,9999',
        ], [
            'name' => 'nombre', 'category_id' => 'categoria', 'duration_minutes' => 'duracion',
            'price' => 'precio', 'promo_price' => 'precio promocional',
        ]);

        $existing = $id > 0 ? QueryBuilder::table('services')->where('id', $id)->first() : null;

        if ($id > 0 && $existing === null) {
            throw new HttpException(404, 'El servicio no existe.');
        }

        $payload = [
            'category_id' => (int) $data['category_id'],
            'name' => $data['name'],
            'slug' => $this->uniqueSlug('services', Url::slug((string) $data['name']), $id),
            'short_description' => (string) ($data['short_description'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'duration_minutes' => (int) $data['duration_minutes'],
            'buffer_before_minutes' => (int) ($data['buffer_before_minutes'] ?? 0),
            'buffer_after_minutes' => (int) ($data['buffer_after_minutes'] ?? 0),
            'price' => (float) $data['price'],
            'promo_price' => isset($data['promo_price']) && $data['promo_price'] !== null
                ? (float) $data['promo_price'] : null,
            'promo_starts_at' => !empty($data['promo_starts_at'])
                ? Clock::localToUtc((string) $data['promo_starts_at'] . ' 00:00:00') : null,
            'promo_ends_at' => !empty($data['promo_ends_at'])
                ? Clock::localToUtc((string) $data['promo_ends_at'] . ' 23:59:59') : null,
            'deposit_required' => $request->bool('deposit_required') ? 1 : 0,
            'deposit_amount' => (float) ($data['deposit_amount'] ?? 0),
            'deposit_is_percentage' => $request->bool('deposit_is_percentage') ? 1 : 0,
            'requires_consultation' => $request->bool('requires_consultation') ? 1 : 0,
            'max_per_day' => (int) ($data['max_per_day'] ?? 0),
            'loyalty_points' => (int) ($data['loyalty_points'] ?? 0),
            'is_active' => $request->bool('is_active') ? 1 : 0,
            'is_featured' => $request->bool('is_featured') ? 1 : 0,
            'bookable_online' => $request->bool('bookable_online') ? 1 : 0,
            'gender_target' => (string) ($data['gender_target'] ?? 'all'),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'updated_at' => Clock::nowUtc(),
        ];

        if ($request->hasFile('image')) {
            $payload['image_path'] = MediaService::replace(
                (string) ($existing['image_path'] ?? ''),
                (array) $request->file('image'),
                'servicios',
                Auth::id(),
                1200
            );
        }

        if ($id > 0) {
            QueryBuilder::table('services')->where('id', $id)->update($payload);
            Audit::record('servicio.actualizado', 'service', $id, $existing, $payload, $request);
        } else {
            $payload['created_at'] = Clock::nowUtc();
            $id = QueryBuilder::table('services')->insert($payload);
            Audit::record('servicio.creado', 'service', $id, null, $payload, $request);
        }

        // Profesionales que prestan el servicio.
        $staffIds = $request->intArray('staff_ids');
        QueryBuilder::table('staff_services')->where('service_id', $id)->delete();

        foreach ($staffIds as $staffId) {
            \App\Core\Database::instance()->statement(
                'INSERT IGNORE INTO staff_services (staff_id, service_id) VALUES (:s, :v)',
                ['s' => $staffId, 'v' => $id]
            );
        }

        Session::success('Servicio guardado.');

        return $this->redirect('/panel/servicios');
    }

    public function deleteService(Request $request): Response
    {
        $this->authorize('servicios.editar');

        $id = $request->paramInt('id');

        QueryBuilder::table('services')->where('id', $id)->update([
            'deleted_at' => Clock::nowUtc(),
            'is_active' => 0,
            'updated_at' => Clock::nowUtc(),
        ]);

        Audit::record('servicio.eliminado', 'service', $id, null, null, $request);
        Session::success('Servicio eliminado. Las citas ya registradas conservan su historial.');

        return $this->redirect('/panel/servicios');
    }

    // ---- Categorias ------------------------------------------------------

    public function categories(Request $request): Response
    {
        $this->authorize('servicios.ver');

        $categories = $this->categoriesList();

        foreach ($categories as $index => $category) {
            $categories[$index]['service_count'] = QueryBuilder::table('services')
                ->where('category_id', (int) $category['id'])
                ->whereNull('deleted_at')
                ->count();
        }

        return $this->view('admin.services.categories', ['categories' => $categories]);
    }

    public function saveCategory(Request $request): Response
    {
        $this->authorize('servicios.editar');

        $id = $request->paramInt('id');

        $data = $this->validate($request, [
            'name' => 'required|string|min:2|max:100|no_html',
            'description' => 'optional|string|max:500|no_html',
            'color' => 'optional|hex_color',
            'icon' => 'optional|string|max:60',
            'sort_order' => 'optional|int|between:0,9999',
        ], ['name' => 'nombre', 'color' => 'color']);

        $existing = $id > 0 ? QueryBuilder::table('service_categories')->where('id', $id)->first() : null;

        $payload = [
            'name' => $data['name'],
            'slug' => $this->uniqueSlug('service_categories', Url::slug((string) $data['name']), $id),
            'description' => (string) ($data['description'] ?? ''),
            'color' => (string) ($data['color'] ?? '#8b5cf6'),
            'icon' => (string) ($data['icon'] ?? ''),
            'is_active' => $request->bool('is_active') ? 1 : 0,
            'show_on_home' => $request->bool('show_on_home') ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'updated_at' => Clock::nowUtc(),
        ];

        if ($request->hasFile('image')) {
            $payload['image_path'] = MediaService::replace(
                (string) ($existing['image_path'] ?? ''),
                (array) $request->file('image'),
                'categorias',
                Auth::id(),
                1000
            );
        }

        if ($id > 0) {
            QueryBuilder::table('service_categories')->where('id', $id)->update($payload);
        } else {
            $payload['created_at'] = Clock::nowUtc();
            $id = QueryBuilder::table('service_categories')->insert($payload);
        }

        Audit::record('categoria.guardada', 'service_category', $id, $existing, $payload, $request);
        Session::success('Categoria guardada.');

        return $this->redirect('/panel/servicios/categorias');
    }

    public function deleteCategory(Request $request): Response
    {
        $this->authorize('servicios.editar');

        $id = $request->paramInt('id');

        $inUse = QueryBuilder::table('services')
            ->where('category_id', $id)
            ->whereNull('deleted_at')
            ->count();

        if ($inUse > 0) {
            Session::error("No se puede eliminar: la categoria tiene {$inUse} servicio(s). Muevelos o eliminalos primero.");

            return $this->redirect('/panel/servicios/categorias');
        }

        QueryBuilder::table('service_categories')->where('id', $id)->update([
            'deleted_at' => Clock::nowUtc(),
            'is_active' => 0,
            'updated_at' => Clock::nowUtc(),
        ]);

        Audit::record('categoria.eliminada', 'service_category', $id, null, null, $request);
        Session::success('Categoria eliminada.');

        return $this->redirect('/panel/servicios/categorias');
    }

    /** @return list<array<string,mixed>> */
    private function categoriesList(): array
    {
        return QueryBuilder::table('service_categories')
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->get();
    }

    /** Genera un identificador de URL unico dentro de la tabla. */
    private function uniqueSlug(string $table, string $base, int $ignoreId): string
    {
        $slug = $base === '' ? 'item' : $base;
        $candidate = $slug;
        $suffix = 1;

        while (true) {
            $query = QueryBuilder::table($table)->where('slug', $candidate);

            if ($ignoreId > 0) {
                $query->where('id', '!=', $ignoreId);
            }

            if (!$query->exists()) {
                return $candidate;
            }

            $candidate = $slug . '-' . (++$suffix);
        }
    }
}
