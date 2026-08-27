<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CurrencyCode;
use App\Enums\PromoCodeType;
use App\Http\Controllers\Controller;
use App\Models\ContactInquiry;
use App\Models\Customer;
use App\Models\Faq;
use App\Models\PromoCode;
use App\Models\Review;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CmsController extends Controller
{
    public function customers(Request $request): JsonResponse
    {
        return response()->json(Customer::query()->withCount('bookings')->latest()->paginate($this->perPage($request)));
    }

    public function reviews(Request $request): JsonResponse
    {
        return response()->json(Review::query()->with('booking:id,booking_number')->latest()->paginate($this->perPage($request)));
    }

    public function updateReview(Request $request, Review $review, AuditLogger $audit): JsonResponse
    {
        $old = $review->only(['active']);
        $review->update($request->validate(['active' => ['required', 'boolean']]));
        $audit->record($request->user(), 'review.moderated', $review, $old, $review->only(['active']), $request->ip());

        return response()->json(['data' => $review->refresh()]);
    }

    public function promoCodes(Request $request): JsonResponse
    {
        return response()->json(PromoCode::query()->latest()->paginate($this->perPage($request)));
    }

    public function storePromoCode(Request $request, AuditLogger $audit): JsonResponse
    {
        $promo = PromoCode::query()->create($this->promoData($request));
        $audit->record($request->user(), 'promo_code.created', $promo, [], $promo->toArray(), $request->ip());

        return response()->json(['data' => $promo], 201);
    }

    public function updatePromoCode(Request $request, PromoCode $promoCode, AuditLogger $audit): JsonResponse
    {
        $old = $promoCode->toArray();
        $promoCode->update($this->promoData($request, true, $promoCode));
        $audit->record($request->user(), 'promo_code.updated', $promoCode, $old, $promoCode->refresh()->toArray(), $request->ip());

        return response()->json(['data' => $promoCode]);
    }

    public function faqs(Request $request): JsonResponse
    {
        return response()->json(Faq::query()->with('translations')->orderBy('sort_order')->paginate($this->perPage($request)));
    }

    public function storeFaq(Request $request, AuditLogger $audit): JsonResponse
    {
        $validated = $this->faqData($request);
        $faq = Faq::query()->create($validated);
        $faq->translations()->createMany($validated['translations']);
        $audit->record($request->user(), 'faq.created', $faq, [], $validated, $request->ip());

        return response()->json(['data' => $faq->load('translations')], 201);
    }

    public function updateFaq(Request $request, Faq $faq, AuditLogger $audit): JsonResponse
    {
        $old = $faq->load('translations')->toArray();
        $validated = $this->faqData($request);
        $faq->update($validated);
        foreach ($validated['translations'] as $translation) {
            $faq->translations()->updateOrCreate(['locale' => $translation['locale']], $translation);
        }
        $audit->record($request->user(), 'faq.updated', $faq, $old, $faq->load('translations')->toArray(), $request->ip());

        return response()->json(['data' => $faq]);
    }

    public function inquiries(Request $request): JsonResponse
    {
        return response()->json(ContactInquiry::query()->with('handler:id,name')->latest()->paginate($this->perPage($request)));
    }

    public function updateInquiry(Request $request, ContactInquiry $inquiry, AuditLogger $audit): JsonResponse
    {
        $old = $inquiry->only(['status', 'handled_at', 'handled_by']);
        $status = $request->validate(['status' => ['required', Rule::in(['new', 'in_progress', 'resolved'])]])['status'];
        $inquiry->update(['status' => $status, 'handled_at' => $status === 'resolved' ? now() : null, 'handled_by' => $request->user()->id]);
        $audit->record($request->user(), 'inquiry.status_changed', $inquiry, $old, $inquiry->only(['status', 'handled_at', 'handled_by']), $request->ip());

        return response()->json(['data' => $inquiry->refresh()]);
    }

    private function perPage(Request $request): int
    {
        $validated = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        return (int) ($validated['per_page'] ?? 25);
    }

    /** @return array<string, mixed> */
    private function promoData(Request $request, bool $partial = false, ?PromoCode $promo = null): array
    {
        $sometimes = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'code' => [$sometimes, 'string', 'max:50', Rule::unique('promo_codes')->ignore($promo)],
            'type' => [$sometimes, Rule::enum(PromoCodeType::class)],
            'value' => [$sometimes, 'integer', 'min:1'],
            'currency' => [$sometimes, Rule::enum(CurrencyCode::class)],
            'min_order_minor' => ['sometimes', 'integer', 'min:0'],
            'max_discount_minor' => ['nullable', 'integer', 'min:0'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_per_customer' => ['nullable', 'integer', 'min:1'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function faqData(Request $request): array
    {
        return $request->validate([
            'category' => ['required', 'string', 'max:50'],
            'active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.locale' => ['required', Rule::in(config('tourism.locales'))],
            'translations.*.question' => ['required', 'string', 'max:255'],
            'translations.*.answer' => ['required', 'string', 'max:5000'],
        ]);
    }
}
