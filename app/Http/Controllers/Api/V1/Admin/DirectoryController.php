<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CarCategory;
use App\Enums\CurrencyCode;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertDestinationRequest;
use App\Http\Requests\Admin\UpsertDriverRequest;
use App\Http\Requests\Admin\UpsertTourRequest;
use App\Http\Resources\Admin\AdminCarResource;
use App\Http\Resources\Admin\AdminDestinationResource;
use App\Http\Resources\Admin\AdminDriverResource;
use App\Http\Resources\Admin\AdminTourResource;
use App\Models\Car;
use App\Models\Destination;
use App\Models\Driver;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Models\User;
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
        $tours = Tour::query()->with(['translations', 'media', 'stops.destination.translations'])->orderBy('sort_order')->paginate($this->perPage($request));

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
            $tour = Tour::query()->create(Arr::except($validated, ['translations', 'itinerary']));
            $this->syncTranslations($tour, $validated['translations']);
            if (isset($validated['itinerary'])) {
                $this->syncTourItinerary($tour, $validated['itinerary']);
            }

            return $tour;
        });
        $audit->record($request->user(), 'tours.created', $tour, [], $tour->toArray(), $request->ip());

        return response()->json(['data' => (new AdminTourResource($tour->load(['translations', 'media', 'stops.destination.translations'])))->resolve($request)], 201);
    }

    public function updateTour(UpsertTourRequest $request, Tour $tour, AuditLogger $audit): JsonResponse
    {
        $old = $tour->load(['translations', 'stops'])->toArray();
        DB::transaction(function () use ($request, $tour): void {
            $validated = $request->validated();
            $tour->update(Arr::except($validated, ['translations', 'itinerary']));
            if (isset($validated['translations'])) {
                $this->syncTranslations($tour, $validated['translations']);
            }
            if (isset($validated['itinerary'])) {
                $this->syncTourItinerary($tour, $validated['itinerary']);
            }
        });
        $tour->refresh()->load(['translations', 'media', 'stops.destination.translations']);
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

    /** @param array<int, array<string, mixed>> $itinerary */
    private function syncTourItinerary(Tour $tour, array $itinerary): void
    {
        $tour->stops()->delete();

        $dayNumbers = collect($itinerary)->pluck('day_number')->unique()->values();
        if ($dayNumbers->isEmpty()) {
            $tour->days()->delete();

            return;
        }

        $tour->days()->whereNotIn('day_number', $dayNumbers)->delete();
        $days = $dayNumbers->mapWithKeys(fn (int $dayNumber): array => [
            $dayNumber => $tour->days()->firstOrCreate(['day_number' => $dayNumber])->id,
        ]);
        $orders = [];

        foreach ($itinerary as $stop) {
            $dayNumber = (int) $stop['day_number'];
            $orders[$dayNumber] = ($orders[$dayNumber] ?? 0) + 1;
            $tour->stops()->create([
                'tour_day_id' => $days->get($dayNumber),
                'destination_id' => $stop['destination_id'],
                'day_number' => $dayNumber,
                'stop_order' => $orders[$dayNumber],
                'duration_minutes' => $stop['duration_minutes'] ?? null,
                'optional' => $stop['optional'],
                'notes' => $stop['notes'] ?? null,
            ]);
        }
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
        $drivers = Driver::query()->with(['user', 'cars', 'media'])->orderBy('first_name')->paginate($this->perPage($request));

        return response()->json($drivers->through(fn (Driver $driver): array => (new AdminDriverResource($driver))->resolve($request)));
    }

    public function storeDriver(UpsertDriverRequest $request, AuditLogger $audit): JsonResponse
    {
        $driver = DB::transaction(function () use ($request): Driver {
            $data = $request->validated();
            $active = (bool) ($data['active'] ?? true);
            $user = User::query()->create([
                'name' => trim("{$data['first_name']} {$data['last_name']}"),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => UserRole::Driver,
                'locale' => $data['locale'] ?? 'en',
                'is_active' => $active,
            ]);
            $driver = Driver::query()->create([
                'user_id' => $user->id,
                'preferred_car_id' => $data['preferred_car_id'] ?? null,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'languages' => $data['languages'] ?? [],
                'experience_years' => $data['experience_years'] ?? 0,
                'license_number' => $data['license_number'],
                'active' => $active,
                'rating' => $data['rating'] ?? null,
            ]);
            $carIds = collect($data['car_ids'] ?? [])->when(
                isset($data['preferred_car_id']),
                fn ($ids) => $ids->push($data['preferred_car_id']),
            )->unique()->values()->all();
            $driver->cars()->sync($carIds);

            return $driver;
        });
        $audit->record($request->user(), 'drivers.created', $driver, [], $driver->toArray(), $request->ip());

        return response()->json(['data' => (new AdminDriverResource($driver->load(['user', 'cars', 'media'])))->resolve($request)], 201);
    }

    public function updateDriver(UpsertDriverRequest $request, Driver $driver, AuditLogger $audit): JsonResponse
    {
        $old = $driver->load(['user', 'cars'])->toArray();
        DB::transaction(function () use ($request, $driver): void {
            $data = $request->validated();
            $firstName = $data['first_name'] ?? $driver->first_name;
            $lastName = $data['last_name'] ?? $driver->last_name;
            $phone = $data['phone'] ?? $driver->phone;
            $email = $data['email'] ?? $driver->email;
            $active = (bool) ($data['active'] ?? $driver->active);
            $userChanges = [
                'name' => trim("{$firstName} {$lastName}"),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'email' => $email,
                'is_active' => $active,
            ];
            if (isset($data['locale'])) {
                $userChanges['locale'] = $data['locale'];
            }
            if (filled($data['password'] ?? null)) {
                $userChanges['password'] = $data['password'];
            }
            $driver->user()->update($userChanges);
            $driver->update(Arr::only($data, [
                'preferred_car_id', 'first_name', 'last_name', 'phone', 'email', 'languages',
                'experience_years', 'license_number', 'active', 'rating',
            ]));
            if (array_key_exists('car_ids', $data)) {
                $carIds = collect($data['car_ids']);
                if (isset($data['preferred_car_id'])) {
                    $carIds->push($data['preferred_car_id']);
                }
                $driver->cars()->sync($carIds->unique()->values()->all());
            } elseif (isset($data['preferred_car_id'])) {
                $driver->cars()->syncWithoutDetaching([$data['preferred_car_id']]);
            }
        });
        $driver->refresh()->load(['user', 'cars', 'media']);
        $audit->record($request->user(), 'drivers.updated', $driver, $old, $driver->toArray(), $request->ip());

        return response()->json(['data' => (new AdminDriverResource($driver))->resolve($request)]);
    }

    public function destroyDriver(Request $request, Driver $driver, AuditLogger $audit): JsonResponse
    {
        if ($driver->bookings()->blockingAvailability()->where('planned_end_at', '>', now())->exists()) {
            return response()->json(['message' => 'This driver has upcoming assigned trips. Reassign them before deleting the driver.'], 422);
        }
        $old = $driver->load('user')->toArray();
        DB::transaction(function () use ($driver): void {
            $driver->user?->tokens()->delete();
            $driver->user?->update(['is_active' => false]);
            $driver->delete();
        });
        $audit->record($request->user(), 'drivers.deleted', $driver, $old, [], $request->ip());

        return response()->json([], 204);
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
        if ($car->groupTourDepartures()->where('active', true)->where('starts_at', '>', now())->exists()) {
            return response()->json([
                'message' => 'This car is assigned to future group tour departures. Reassign those departures before deleting it.',
            ], 422);
        }
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
