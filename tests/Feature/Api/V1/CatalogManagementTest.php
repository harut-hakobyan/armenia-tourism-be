<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_list_and_delete_a_tour(): void
    {
        $this->seed();
        $admin = User::query()->where('role', UserRole::Admin)->firstOrFail();
        $category = TourCategory::query()->firstOrFail();
        $payload = [
            'category_id' => $category->id,
            'slug' => 'armenia-highlights-test',
            'duration_minutes' => 480,
            'approximate_distance_km' => 150,
            'starting_price_minor' => 9500,
            'currency' => 'EUR',
            'pricing_type' => 'per_car',
            'format' => 'private',
            'active' => true,
            'featured' => false,
            'max_passengers' => 4,
            'pickup_available' => true,
            'dropoff_available' => true,
            'free_cancellation_hours' => 24,
            'sort_order' => 20,
            'translations' => [[
                'locale' => 'en',
                'title' => 'Armenia Highlights',
                'short_description' => 'A private Armenia highlights tour.',
                'description' => 'A full description of this Armenia journey.',
                'seo_title' => 'Armenia Highlights Tour',
                'seo_description' => 'Book an Armenia highlights tour.',
            ]],
        ];

        $created = $this->actingAs($admin)->postJson('/api/v1/admin/directory/tours', $payload)
            ->assertCreated()
            ->assertJsonPath('data.slug', 'armenia-highlights-test')
            ->assertJsonPath('data.translations.0.title', 'Armenia Highlights');
        $id = $created->json('data.id');

        $this->actingAs($admin)->patchJson("/api/v1/admin/directory/tours/{$id}", [
            'featured' => true,
            'translations' => [[
                'locale' => 'en',
                'title' => 'Updated Armenia Highlights',
            ]],
        ])->assertOk()
            ->assertJsonPath('data.featured', true)
            ->assertJsonPath('data.translations.0.title', 'Updated Armenia Highlights');

        $this->actingAs($admin)->getJson('/api/v1/admin/directory/tours?per_page=100')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'armenia-highlights-test']);
        $this->actingAs($admin)->deleteJson("/api/v1/admin/directory/tours/{$id}")->assertNoContent();
        $this->assertSoftDeleted('tours', ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tours.deleted', 'subject_id' => $id]);
    }

    public function test_manager_can_create_edit_and_delete_a_destination(): void
    {
        $this->seed();
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $payload = [
            'slug' => 'lastiver-test',
            'latitude' => 40.9167,
            'longitude' => 45.1000,
            'address' => 'Tavush Province',
            'active' => true,
            'featured' => false,
            'sort_order' => 30,
            'translations' => [[
                'locale' => 'en',
                'name' => 'Lastiver',
                'short_description' => 'Forests, caves, and waterfalls.',
                'description' => 'A scenic destination in Tavush.',
                'seo_title' => 'Visit Lastiver',
                'seo_description' => 'Discover Lastiver in Armenia.',
            ]],
        ];

        $created = $this->actingAs($manager)->postJson('/api/v1/admin/directory/destinations', $payload)
            ->assertCreated()
            ->assertJsonPath('data.translations.0.name', 'Lastiver');
        $id = $created->json('data.id');

        $this->actingAs($manager)->patchJson("/api/v1/admin/directory/destinations/{$id}", [
            'address' => 'Yenokavan, Tavush',
            'featured' => true,
        ])->assertOk()
            ->assertJsonPath('data.address', 'Yenokavan, Tavush')
            ->assertJsonPath('data.featured', true);

        $this->actingAs($manager)->deleteJson("/api/v1/admin/directory/destinations/{$id}")->assertNoContent();
        $this->assertSoftDeleted('destinations', ['id' => $id]);
    }

    public function test_admin_can_update_a_group_tour_schedule(): void
    {
        $this->seed();
        $admin = User::query()->where('role', UserRole::Admin)->firstOrFail();
        $tour = Tour::query()->where('slug', 'garni-geghard-group-tour')->firstOrFail();

        $this->actingAs($admin)->patchJson("/api/v1/admin/directory/tours/{$tour->id}", [
            'format' => 'group',
            'start_time' => '08:30',
            'meeting_point' => 'Cascade Complex, Yerevan',
        ])->assertOk()
            ->assertJsonPath('data.start_time', '08:30')
            ->assertJsonPath('data.meeting_point', 'Cascade Complex, Yerevan');

        $this->assertDatabaseHas('tours', [
            'id' => $tour->id,
            'start_time' => '08:30',
            'meeting_point' => 'Cascade Complex, Yerevan',
        ]);
    }
}
