<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\PublicApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicApi\ListDestinationsRequest;
use App\Http\Resources\DestinationResource;
use App\Models\Destination;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class DestinationController extends Controller
{
    public function index(ListDestinationsRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $destinations = Destination::query()
            ->active()
            ->with(['translations', 'media'])
            ->when(array_key_exists('featured', $filters), fn ($query) => $query->where('featured', $request->boolean('featured')))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->whereHas('translations', fn ($translations) => $translations
                    ->whereIn('locale', array_unique([app()->getLocale(), config('app.fallback_locale', 'en')]))
                    ->where('name', 'like', "%{$search}%"));
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 12))
            ->withQueryString();

        return DestinationResource::collection($destinations);
    }

    public function show(Destination $destination): DestinationResource
    {
        abort_unless($destination->active, 404);

        return new DestinationResource($destination->load(['translations', 'media']));
    }
}
