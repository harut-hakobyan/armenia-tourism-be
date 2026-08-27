<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\PublicApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicApi\ListCarsRequest;
use App\Http\Resources\CarResource;
use App\Models\Car;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CarController extends Controller
{
    public function index(ListCarsRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = Car::query()
            ->where('active', true)
            ->where('available_for_booking', true)
            ->with('media')
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters['passengers'] ?? null, fn (Builder $query, int $passengers) => $query
                ->where('passenger_capacity', '>=', $passengers))
            ->when(array_key_exists('luggage', $filters), fn (Builder $query) => $query
                ->where('luggage_capacity', '>=', (int) $filters['luggage']))
            ->when(array_key_exists('child_seat', $filters), fn (Builder $query) => $query
                ->where('child_seat_available', $request->boolean('child_seat')));

        match ($filters['sort'] ?? 'recommended') {
            'price_asc' => $query->orderBy('base_price_minor'),
            'capacity_desc' => $query->orderByDesc('passenger_capacity'),
            default => $query->orderBy('category')->orderBy('base_price_minor'),
        };

        return CarResource::collection(
            $query->orderBy('id')->paginate((int) ($filters['per_page'] ?? 12))->withQueryString(),
        );
    }

    public function show(Car $car): CarResource
    {
        abort_unless($car->active && $car->available_for_booking, 404);

        return new CarResource($car->load('media'));
    }
}
