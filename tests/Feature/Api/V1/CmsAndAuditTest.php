<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\Car;
use App\Models\ContactInquiry;
use App\Models\Faq;
use App\Models\PromoCode;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CmsAndAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_content_and_contact_inquiry_are_persisted(): void
    {
        $this->seed();

        $this->getJson('/api/v1/faqs?locale=en')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.category', 'booking');
        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.default_currency', 'EUR');
        $this->postJson('/api/v1/contact-inquiries', [
            'name' => 'Ani Traveler',
            'email' => 'ani@example.com',
            'phone' => '+37499112233',
            'subject' => 'Custom route',
            'message' => 'Please help us arrange a private trip to Tatev.',
        ])->assertCreated()->assertJsonPath('data.status', 'new');

        $this->assertDatabaseHas('contact_inquiries', ['email' => 'ani@example.com', 'status' => 'new']);
    }

    public function test_manager_manages_operational_cms_but_cannot_manage_settings_or_audit(): void
    {
        $this->seed();
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $inquiry = ContactInquiry::query()->create([
            'name' => 'Guest', 'email' => 'guest@example.com', 'subject' => 'Help', 'message' => 'I need booking support please.',
        ]);

        $this->actingAs($manager)->getJson('/api/v1/admin/customers')->assertOk();
        $this->actingAs($manager)->getJson('/api/v1/admin/promo-codes')->assertOk();
        $this->actingAs($manager)->getJson('/api/v1/admin/faqs')->assertOk();
        $this->actingAs($manager)->patchJson("/api/v1/admin/inquiries/{$inquiry->id}", ['status' => 'resolved'])
            ->assertOk()->assertJsonPath('data.status', 'resolved');
        $this->actingAs($manager)->getJson('/api/v1/admin/settings')->assertForbidden();
        $this->actingAs($manager)->getJson('/api/v1/admin/audit-logs')->assertForbidden();

        $this->assertDatabaseHas('audit_logs', ['user_id' => $manager->id, 'action' => 'inquiry.status_changed']);
    }

    public function test_admin_can_update_settings_promotions_and_faqs_with_audit_history(): void
    {
        $this->seed();
        $admin = User::query()->where('role', UserRole::Admin)->firstOrFail();
        $setting = Setting::query()->where('key', 'company_email')->firstOrFail();
        $promo = PromoCode::query()->where('code', 'WELCOME10')->firstOrFail();
        $faq = Faq::query()->firstOrFail();

        $this->actingAs($admin)->patchJson("/api/v1/admin/settings/{$setting->id}", ['value' => 'travel@example.com'])
            ->assertOk()->assertJsonPath('data.value', 'travel@example.com');
        $this->actingAs($admin)->patchJson("/api/v1/admin/promo-codes/{$promo->id}", ['active' => false])
            ->assertOk()->assertJsonPath('data.active', false);
        $this->actingAs($admin)->patchJson("/api/v1/admin/faqs/{$faq->id}", [
            'category' => $faq->category,
            'active' => false,
            'sort_order' => $faq->sort_order,
            'translations' => $faq->translations->map(fn ($translation): array => [
                'locale' => $translation->locale,
                'question' => $translation->question,
                'answer' => $translation->answer,
            ])->all(),
        ])->assertOk()->assertJsonPath('data.active', false);

        $this->actingAs($admin)->getJson('/api/v1/admin/audit-logs')
            ->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_admin_can_upload_validated_public_media(): void
    {
        Storage::fake('public');
        config()->set('filesystems.default', 'public');
        $this->seed();
        $admin = User::query()->where('role', UserRole::Admin)->firstOrFail();
        $car = Car::query()->firstOrFail();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        $response = $this->actingAs($admin)->postJson("/api/v1/admin/media/cars/{$car->id}", [
            'file' => UploadedFile::fake()->createWithContent('car.png', $png),
            'collection' => 'cover',
            'alt_text' => 'Comfort car',
        ])->assertCreated()->assertJsonPath('data.alt_text', 'Comfort car');

        $mediaId = $response->json('data.id');
        $this->assertDatabaseHas('media', ['id' => $mediaId, 'mediable_type' => Car::class, 'mediable_id' => $car->id]);
        $this->actingAs($admin)->deleteJson("/api/v1/admin/media/{$mediaId}")->assertNoContent();
        $this->assertSoftDeleted('media', ['id' => $mediaId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'media.deleted', 'subject_id' => $mediaId]);
    }
}
