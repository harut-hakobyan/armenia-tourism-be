<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Destination;
use App\Models\Driver;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        return response()->json(Car::query()->orderBy('brand')->paginate($this->perPage($request)));
    }

    public function drivers(Request $request): JsonResponse
    {
        return response()->json(Driver::query()->with('cars:id,brand,model')->orderBy('first_name')->paginate($this->perPage($request)));
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $model = $this->model($type, $id);
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
        $model->update(array_intersect_key($validated, array_flip($allowed)));

        return response()->json(['data' => $model->refresh()]);
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
