<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CarCategory;
use App\Enums\CurrencyCode;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Destination;
use App\Models\Driver;
use App\Models\Tour;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class DirectoryController extends Controller
{
    public function tours(Request $request): JsonResponse
    {
        return response()->json(Tour::query()->with('translations')->orderBy('sort_order')->paginate($this->perPage($request)));
    }

    public function destinations(Request $request): JsonResponse
    {
        return response()->json(Destination::query()->with('translations')->orderBy('sort_order')->paginate($this->perPage($request)));
    }

    public function cars(Request $request): JsonResponse
    {
        return response()->json(Car::query()->with('media')->orderBy('brand')->paginate($this->perPage($request)));
    }

    public function storeCar(Request $request, AuditLogger $audit): JsonResponse
    {
        $car = Car::query()->create($this->validateCar($request));
        $audit->record($request->user(), 'cars.created', $car, [], $car->toArray(), $request->ip());

        return response()->json(['data' => $car->load('media')], 201);
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

            return response()->json(['data' => $model->refresh()->load('media')]);
        }

        $validated = $request->validate([
            'active' => ['sometimes', 'boolean'],
            'featured' => ['sometimes', 'boolean'],
            'available_for_booking' => ['sometimes', 'boolean'],
        ]);
        $allowed = match ($type) {
            'tours', 'destinations' => ['active', 'featured'],
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

    private function model(string $type, int $id): Model
    {
        $class = match ($type) {
            'tours' => Tour::class,
            'destinations' => Destination::class,
            'cars' => Car::class,
            'drivers' => Driver::class,
            default => abort(404),
        };

        return $class::query()->findOrFail($id);
    }
}
