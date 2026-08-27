<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\PublicApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicApi\ListToursRequest;
use App\Http\Resources\TourResource;
use App\Models\Tour;
use App\Models\TourCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class TourController extends Controller
{
    public function index(ListToursRequest $request): AnonymousResourceCollection
    {
        return $this->collection($request, $request->validated());
    }

    public function category(ListToursRequest $request, TourCategory $category): AnonymousResourceCollection
    {
        abort_unless($category->active, 404);
        $filters = $request->validated();
        $filters['category'] = $category->slug;

        return $this->collection($request, $filters);
    }

    public function show(Tour $tour): TourResource
    {
        abort_unless($tour->active, 404);

        return new TourResource($tour->load([
            'translations', 'category.translations', 'media', 'days',
            'stops.destination.translations',
        ]));
    }

    /** @param array<string, mixed> $filters */
    private function collection(ListToursRequest $request, array $filters): AnonymousResourceCollection
    {
        $query = Tour::query()
            ->active()
            ->with(['translations', 'category.translations', 'media'])
            ->when($filters['category'] ?? null, fn (Builder $query, string $slug) => $query
                ->whereHas('category', fn (Builder $category) => $category->active()->where('slug', $slug)))
            ->when(array_key_exists('featured', $filters), fn (Builder $query) => $query
                ->where('featured', $request->boolean('featured')))
            ->when($filters['passengers'] ?? null, fn (Builder $query, int $passengers) => $query
                ->where(fn (Builder $capacity) => $capacity
                    ->whereNull('max_passengers')->orWhere('max_passengers', '>=', $passengers)))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->whereHas('translations', fn (Builder $translations) => $translations
                    ->whereIn('locale', array_unique([app()->getLocale(), config('app.fallback_locale', 'en')]))
                    ->where('title', 'like', "%{$search}%"));
            });

        match ($filters['sort'] ?? 'recommended') {
            'price_asc' => $query->orderBy('starting_price_minor'),
            'price_desc' => $query->orderByDesc('starting_price_minor'),
            'duration_asc' => $query->orderBy('duration_minutes'),
            default => $query->orderByDesc('featured')->orderBy('sort_order'),
        };

        return TourResource::collection(
            $query->orderBy('id')->paginate((int) ($filters['per_page'] ?? 12))->withQueryString(),
        );
    }
}
