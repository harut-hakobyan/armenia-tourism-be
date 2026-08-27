<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\PublicApi;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaqResource;
use App\Models\ContactInquiry;
use App\Models\Faq;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ContentController extends Controller
{
    public function faqs(): AnonymousResourceCollection
    {
        return FaqResource::collection(
            Faq::query()->where('active', true)->with('translations')->orderBy('category')->orderBy('sort_order')->get(),
        );
    }

    public function settings(): JsonResponse
    {
        return response()->json(['data' => Setting::query()->where('is_public', true)->pluck('value', 'key')]);
    }

    public function contact(Request $request): JsonResponse
    {
        $inquiry = ContactInquiry::query()->create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]));

        $inquiry->refresh();

        return response()->json(['data' => ['id' => $inquiry->id, 'status' => $inquiry->status]], 201);
    }
}
