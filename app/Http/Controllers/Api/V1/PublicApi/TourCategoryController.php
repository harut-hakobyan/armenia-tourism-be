<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\PublicApi;

use App\Http\Controllers\Controller;
use App\Http\Resources\TourCategoryResource;
use App\Models\TourCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

final class TourCategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['locale' => ['nullable', Rule::in(config('tourism.locales'))]]);

        return TourCategoryResource::collection(
            TourCategory::query()->active()->with('translations')->orderBy('sort_order')->orderBy('id')->get(),
        );
    }

    public function show(Request $request, TourCategory $category): TourCategoryResource
    {
        $request->validate(['locale' => ['nullable', Rule::in(config('tourism.locales'))]]);
        abort_unless($category->active, 404);

        return new TourCategoryResource($category->load('translations'));
    }
}
