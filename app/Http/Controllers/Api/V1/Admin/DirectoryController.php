<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CarCategory;
use App\Enums\CurrencyCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertDestinationRequest;
use App\Http\Requests\Admin\UpsertTourRequest;
use App\Http\Resources\Admin\AdminCarResource;
use App\Http\Resources\Admin\AdminDestinationResource;
use App\Http\Resources\Admin\AdminTourResource;
use App\Models\Car;
use App\Models\Destination;
use App\Models\Driver;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class DirectoryController extends Controller
{
    public function tours(Request $request): JsonResponse
    {
        $tours = Tour::query()->with(['translations', 'media'])->orderBy('sort_order')->paginate($this->perPage($request));

        return response()->json($tours->through(fn (Tour $tour): array => (new AdminTourResource($tour))->resolve($request)));
    }

    public function destinations(Request $request): JsonResponse
    {
        $destinations = Destination::query()->with(['translations', 'media'])->orderBy('sort_order')->paginate($this->perPage($request));

        return response()->json($destinations->through(fn (Destination $destination): array => (new AdminDestinationResource($destination))->resolve($request)));
    }

    public function tourCategories(Request $request): JsonResponse
    {
        $categories = TourCategory::query()->with('translations')->orderBy('sort_order')->get()->map(fn (TourCategory $category): array => [
            'id' => $category->id,
            'slug' => $category->slug,
            'active' => $category->active,
            'translations' => $category->translations->map->only(['locale', 'name'])->values(),
        ]);

        return response()->json(['data' => $categories]);
    }

    public function storeTour(UpsertTourRequest $request, AuditLogger $audit): JsonResponse
    {
        $tour = DB::transaction(function () use ($request): Tour {
            $validated = $request->validated();
            $tour = Tour::query()->create(Arr::except($validated, 'translations'));
            $this->syncTranslations($tour, $validated['translations']);

            return $tour;
        });
        $audit->record($request->user(), 'tours.created', $tour, [], $tour->toArray(), $request->ip());

        return response()->json(['data' => (new AdminTourResource($tour->load(['translations', 'media'])))->resolve($request)], 201);
    }

    public function updateTour(UpsertTourRequest $request, Tour $tour, AuditLogger $audit): JsonResponse
    {
        $old = $tour->load('translations')->toArray();
        DB::transaction(function () use ($request, $tour): void {
            $validated = $request->validated();
            $tour->update(Arr::except($validated, 'translations'));
            if (isset($validated['translations'])) {
                $this->syncTranslations($tour, $validated['translations']);
            }
        });
        $tour->refresh()->load(['translations', 'media']);
        $audit->record($request->user(), 'tours.updated', $tour, $old, $tour->toArray(), $request->ip());

        return response()->json(['data' => (new AdminTourResource($tour))->resolve($request)]);
    }

    public function destroyTour(Request $request, Tour $tour, AuditLogger $audit): JsonResponse
    {
        $old = $tour->load('translations')->toArray();
        $tour->delete();
        $audit->record($request->user(), 'tours.deleted', $tour, $old, [], $request->ip());

        return response()->json([], 204);
    }

    public function storeDestination(UpsertDestinationRequest $request, AuditLogger $audit): JsonResponse
    {
        $destination = DB::transaction(function () use ($request): Destination {
            $validated = $request->validated();
            $destination = Destination::query()->create(Arr::except($validated, 'translations'));
            $this->syncTranslations($destination, $validated['translations']);

            return $destination;
        });
        $audit->record($request->user(), 'destinations.created', $destination, [], $destination->toArray(), $request->ip());

        return response()->json(['data' => (new AdminDestinationResource($destination->load(['translations', 'media'])))->resolve($request)], 201);
    }

    public function updateDestination(UpsertDestinationRequest $request, Destination $destination, AuditLogger $audit): JsonResponse
    {
        $old = $destination->load('translations')->toArray();
        DB::transaction(function () use ($request, $destination): void {
            $validated = $request->validated();
            $destination->update(Arr::except($validated, 'translations'));
            if (isset($validated['translations'])) {
                $this->syncTranslations($destination, $validated['translations']);
            }
        });
        $destination->refresh()->load(['translations', 'media']);
        $audit->record($request->user(), 'destinations.updated', $destination, $old, $destination->toArray(), $request->ip());

        return response()->json(['data' => (new AdminDestinationResource($destination))->resolve($request)]);
    }

    public function destroyDestination(Request $request, Destination $destination, AuditLogger $audit): JsonResponse
    {
        $old = $destination->load('translations')->toArray();
        $destination->delete();
        $audit->record($request->user(), 'destinations.deleted', $destination, $old, [], $request->ip());

        return response()->json([], 204);
    }

    public function cars(Request $request): JsonResponse
    {
        $cars = Car::query()->with('media')->orderBy('brand')->paginate($this->perPage($request));

        return response()->json($cars->through(fn (Car $car): array => (new AdminCarResource($car))->resolve($request)));
    }

    public function storeCar(Request $request, AuditLogger $audit): JsonResponse
    {
        $car = Car::query()->create($this->validateCar($request));
        $audit->record($request->user(), 'cars.created', $car, [], $car->toArray(), $request->ip());

        return response()->json(['data' => (new AdminCarResource($car->load('media')))->resolve($request)], 201);
    }

    public function drivers(Request $request): JsonResponse
    {
        return response()->json(Driver::query()->with('cars:id,brand,model')->orderBy('first_name')->paginate($this->perPage($request)));
    }

    public function update(Request $request, string $type, int $id, AuditLogger $audit): JsonResponse
    {
        $model = $this->model($type, $id);
        if ($model instanceof Car) {
            $changes = $this->validateCar($request, $model);
            $old = $model->only(array_keys($changes));
            $model->update($changes);
            $audit->record($request->user(), 'cars.updated', $model, $old, $model->only(array_keys($changes)), $request->ip());

            return response()->json(['data' => (new AdminCarResource($model->refresh()->load('media')))->resolve($request)]);
        }

        $validated = $request->validate([
            'active' => ['sometimes', 'boolean'],
            'featured' => ['sometimes', 'boolean'],
            'available_for_booking' => ['sometimes', 'boolean'],
        ]);
        $allowed = match ($type) {
            'cars' => ['active', 'available_for_booking'],
            'drivers' => ['active'],
            default => [],
        };
        $changes = array_intersect_key($validated, array_flip($allowed));
        $old = $model->only(array_keys($changes));
        $model->update($changes);
        $audit->record($request->user(), "{$type}.visibility_updated", $model, $old, $model->only(array_keys($changes)), $request->ip());

        return response()->json(['data' => $model->refresh()]);
    }

    public function destroyCar(Request $request, Car $car, AuditLogger $audit): JsonResponse
    {
        $old = $car->toArray();
        $car->delete();
        $audit->record($request->user(), 'cars.deleted', $car, $old, [], $request->ip());

        return response()->json([], 204);
    }

    /** @return array<string, mixed> */
    private function validateCar(Request $request, ?Car $car = null): array
    {
        $presence = $car === null ? 'required' : 'sometimes';

        return $request->validate([
            'brand' => [$presence, 'string', 'max:100'],
            'model' => [$presence, 'string', 'max:100'],
            'year' => [$presence, 'integer', 'min:1980', 'max:'.(now()->year + 1)],
            'plate_number' => [$presence, 'string', 'max:32', Rule::unique('cars', 'plate_number')->ignore($car)],
            'color' => ['nullable', 'string', 'max:50'],
            'category' => [$presence, Rule::enum(CarCategory::class)],
            'passenger_capacity' => [$presence, 'integer', 'min:1', 'max:50'],
            'luggage_capacity' => [$presence, 'integer', 'min:0', 'max:50'],
            'transmission' => ['nullable', 'string', 'max:20'],
            'air_conditioning' => [$presence, 'boolean'],
            'wifi' => [$presence, 'boolean'],
            'child_seat_available' => [$presence, 'boolean'],
            'base_price_minor' => [$presence, 'integer', 'min:0'],
            'price_per_km_minor' => [$presence, 'integer', 'min:0'],
            'price_per_hour_minor' => [$presence, 'integer', 'min:0'],
            'currency' => [$presence, Rule::enum(CurrencyCode::class)],
            'active' => [$presence, 'boolean'],
            'available_for_booking' => [$presence, 'boolean'],
        ]);
    }

    private function perPage(Request $request): int
    {
        $validated = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        return (int) ($validated['per_page'] ?? 25);
    }

    /** @param array<int, array<string, mixed>> $translations */
    private function syncTranslations(Tour|Destination $model, array $translations): void
    {
        foreach ($translations as $translation) {
            $locale = (string) $translation['locale'];
            $model->translations()->updateOrCreate(['locale' => $locale], Arr::except($translation, 'locale'));
        }
    }

    private function model(string $type, int $id): Model
    {
        $class = match ($type) {
            'cars' => Car::class,
            'drivers' => Driver::class,
            default => abort(404),
        };

        return $class::query()->findOrFail($id);
    }
}
