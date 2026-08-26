<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CarCategory;
use App\Enums\CurrencyCode;
use App\Enums\PricingType;
use App\Models\Car;
use App\Models\Destination;
use App\Models\Driver;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CatalogFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_seeders_create_complete_multilingual_sample_data_idempotently(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('destinations', 15);
        $this->assertDatabaseCount('destination_translations', 45);
        $this->assertDatabaseCount('tour_categories', 10);
        $this->assertDatabaseCount('tour_category_translations', 30);
        $this->assertDatabaseCount('cars', 6);
        $this->assertDatabaseCount('drivers', 2);
        $this->assertDatabaseCount('driver_cars', 2);
        $this->assertDatabaseCount('tours', 8);
        $this->assertDatabaseCount('tour_translations', 24);
        $this->assertDatabaseCount('tour_days', 9);
        $this->assertDatabaseCount('tour_stops', 35);
        $this->assertDatabaseCount('tour_prices', 24);
        $this->assertDatabaseCount('promo_codes', 2);
    }

    public function test_tour_itinerary_is_ordered_and_supports_multiple_days(): void
    {
        $this->seed();

        $tour = Tour::query()->where('slug', 'wine-road-jermuk-two-day')->firstOrFail();

        $this->assertSame(PricingType::PerCar, $tour->pricing_type);
        $this->assertSame(CurrencyCode::Eur, $tour->currency);
        $this->assertCount(2, $tour->days);
        $this->assertSame([1, 2], $tour->days->pluck('day_number')->all());
        $this->assertSame(
            ['yerevan', 'khor-virap', 'areni', 'noravank', 'jermuk', 'jermuk', 'yerevan'],
            $tour->stops->load('destination')->pluck('destination.slug')->all(),
        );
    }

    public function test_fleet_relations_and_enum_casts_are_configured(): void
    {
        $this->seed();

        $car = Car::query()->where('plate_number', 'AMT-201')->firstOrFail();
        $driver = Driver::query()->where('preferred_car_id', $car->id)->firstOrFail();

        $this->assertSame(CarCategory::Comfort, $car->category);
        $this->assertSame(CurrencyCode::Eur, $car->currency);
        $this->assertTrue($car->air_conditioning);
        $this->assertTrue($driver->cars->contains($car));
        $this->assertSame(['hy', 'en', 'ru'], $driver->languages);
    }

    public function test_media_can_be_attached_to_catalog_entities(): void
    {
        $this->seed();

        $destination = Destination::query()->where('slug', 'lake-sevan')->firstOrFail();
        $media = $destination->media()->create([
            'collection' => 'cover',
            'disk' => 'public',
            'path' => 'destinations/lake-sevan/cover.jpg',
            'file_name' => 'cover.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'alt_text' => 'Lake Sevan at sunrise',
        ]);

        $this->assertNotEmpty($media->uuid);
        $this->assertTrue($media->mediable->is($destination));
    }
}
