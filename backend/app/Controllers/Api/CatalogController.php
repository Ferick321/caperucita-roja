<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\HttpException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Services\AvailabilityService;
use App\Services\SettingsService;

/** Catalogo publico que consume la app: categorias, servicios y equipo. */
final class CatalogController extends ApiController
{
    public function categories(Request $request): Response
    {
        $categories = QueryBuilder::table('service_categories')
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->get();

        return $this->ok(array_map(static fn (array $category): array => [
            'id' => (int) $category['id'],
            'name' => (string) $category['name'],
            'slug' => (string) $category['slug'],
            'description' => (string) $category['description'],
            'color' => (string) $category['color'],
            'icon' => (string) $category['icon'],
            'image_url' => (string) $category['image_path'] !== ''
                ? media_url((string) $category['image_path'])
                : null,
        ], $categories));
    }

    public function services(Request $request): Response
    {
        $query = QueryBuilder::table('services')
            ->where('is_active', 1)
            ->where('bookable_online', 1)
            ->whereNull('deleted_at');

        $categoryId = $request->int('category_id');

        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }

        $search = $request->string('q');

        if ($search !== '') {
            $query->search($search, ['name', 'short_description']);
        }

        if ($request->bool('featured')) {
            $query->where('is_featured', 1);
        }

        $services = $query->orderBy('sort_order')->limit(200)->get();

        return $this->ok(array_map(fn (array $s): array => $this->serviceResource($s), $services));
    }

    public function service(Request $request): Response
    {
        $service = QueryBuilder::table('services')
            ->where('id', $request->paramInt('id'))
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->first();

        if ($service === null) {
            throw new HttpException(404, 'El servicio no esta disponible.');
        }

        $staff = QueryBuilder::table('staff')
            ->select(['staff.*'])
            ->join('staff_services', 'staff_services.staff_id', '=', 'staff.id')
            ->where('staff_services.service_id', (int) $service['id'])
            ->where('staff.is_active', 1)
            ->where('staff.accepts_online', 1)
            ->whereNull('staff.deleted_at')
            ->get();

        return $this->ok(array_merge($this->serviceResource($service), [
            'staff' => array_map(fn (array $s): array => $this->staffResource($s), $staff),
        ]));
    }

    public function staff(Request $request): Response
    {
        $query = QueryBuilder::table('staff')
            ->where('is_active', 1)
            ->where('show_on_web', 1)
            ->whereNull('deleted_at');

        $branchId = $request->int('branch_id');

        if ($branchId > 0) {
            $query->where('branch_id', $branchId);
        }

        $staff = $query->orderBy('sort_order')->get();

        foreach ($staff as $index => $member) {
            $staff[$index] = $this->staffResource($member);
            $staff[$index]['service_ids'] = array_map(
                'intval',
                QueryBuilder::table('staff_services')->where('staff_id', (int) $member['id'])->pluck('service_id')
            );
        }

        return $this->ok($staff);
    }

    public function gallery(Request $request): Response
    {
        $items = QueryBuilder::table('gallery_items')
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->limit(120)
            ->get();

        return $this->ok(array_map(static fn (array $item): array => [
            'id' => (int) $item['id'],
            'title' => (string) $item['title'],
            'description' => (string) $item['description'],
            'image_url' => media_url((string) $item['image_path']),
            'before_url' => (string) $item['before_path'] !== ''
                ? media_url((string) $item['before_path'])
                : null,
            'is_featured' => (bool) $item['is_featured'],
        ], $items));
    }

    public function reviews(Request $request): Response
    {
        $reviews = QueryBuilder::table('reviews')
            ->select(['reviews.*', 'staff.display_name AS staff_name'])
            ->leftJoin('staff', 'staff.id', '=', 'reviews.staff_id')
            ->where('reviews.is_approved', 1)
            ->whereNull('reviews.deleted_at')
            ->orderBy('reviews.is_featured', 'DESC')
            ->orderBy('reviews.created_at', 'DESC')
            ->limit(50)
            ->get();

        return $this->ok(array_map(static fn (array $review): array => [
            'id' => (int) $review['id'],
            'author' => (string) $review['author_name'],
            'rating' => (int) $review['rating'],
            'comment' => (string) ($review['comment'] ?? ''),
            'reply' => (string) ($review['reply'] ?? ''),
            'staff_name' => (string) ($review['staff_name'] ?? ''),
            'created_at' => local_datetime((string) $review['created_at'], 'Y-m-d'),
        ], $reviews));
    }

    public function faqs(Request $request): Response
    {
        $faqs = QueryBuilder::table('faqs')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get();

        return $this->ok(array_map(static fn (array $faq): array => [
            'question' => (string) $faq['question'],
            'answer' => (string) $faq['answer'],
        ], $faqs));
    }

    /**
     * Disponibilidad.
     *
     * Sin "date" devuelve los dias con hueco; con "date" devuelve los horarios
     * concretos de ese dia.
     */
    public function availability(Request $request): Response
    {
        $serviceIds = $request->intArray('service_ids');
        $staffId = $request->int('staff_id');
        $date = $request->string('date');

        $branchId = $request->int('branch_id');

        if ($branchId <= 0) {
            $branchId = (int) (QueryBuilder::table('branches')
                ->where('is_active', 1)
                ->orderBy('is_default', 'DESC')
                ->value('id') ?? 0);
        }

        if ($branchId === 0) {
            throw new HttpException(422, 'No hay sucursales configuradas.');
        }

        $duration = $serviceIds === []
            ? SettingsService::int('booking.custom_request_minutes', 30)
            : AvailabilityService::totalDuration($serviceIds, $staffId > 0 ? $staffId : null);

        if ($date === '') {
            return $this->ok([
                'duration_minutes' => $duration,
                'days' => AvailabilityService::availableDays(
                    $branchId,
                    $duration,
                    $staffId > 0 ? $staffId : null,
                    $serviceIds
                ),
            ]);
        }

        $slots = AvailabilityService::slotsForDate(
            $date,
            $branchId,
            $duration,
            $staffId > 0 ? $staffId : null,
            $serviceIds
        );

        return $this->ok([
            'date' => $date,
            'duration_minutes' => $duration,
            'slots' => array_map(static fn (array $slot): array => [
                'time' => $slot['time'],
                'label' => $slot['label'],
                'staff_ids' => $slot['staff'],
            ], $slots),
        ]);
    }
}
