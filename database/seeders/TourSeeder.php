<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CarCategory;
use App\Enums\CurrencyCode;
use App\Enums\PricingType;
use App\Enums\TourFormat;
use App\Models\Destination;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Models\TourDay;
use App\Models\TourPrice;
use App\Models\TourStop;
use Illuminate\Database\Seeder;

final class TourSeeder extends Seeder
{
    public function run(): void
    {
        $tours = [
            [
                'slug' => 'garni-geghard', 'category' => 'historical', 'duration' => 480,
                'distance' => 95, 'price' => 7000, 'featured' => true,
                'titles' => ['Garni & Geghard Private Tour', 'Гарни и Гегард', 'Գառնի և Գեղարդ'],
                'days' => [['yerevan', 'garni', 'symphony-of-stones', 'geghard', 'yerevan']],
            ],
            [
                'slug' => 'sevan-dilijan', 'category' => 'nature', 'duration' => 600,
                'distance' => 220, 'price' => 9500, 'featured' => true,
                'titles' => ['Lake Sevan & Dilijan Private Tour', 'Севан и Дилижан', 'Սևան և Դիլիջան'],
                'days' => [['yerevan', 'lake-sevan', 'dilijan', 'yerevan']],
            ],
            [
                'slug' => 'khor-virap-noravank', 'category' => 'historical', 'duration' => 600,
                'distance' => 240, 'price' => 10000, 'featured' => true,
                'titles' => ['Khor Virap, Areni & Noravank', 'Хор Вирап, Арени и Нораванк', 'Խոր Վիրապ, Արենի և Նորավանք'],
                'days' => [['yerevan', 'khor-virap', 'areni', 'noravank', 'yerevan']],
            ],
            [
                'slug' => 'tatev-day-tour', 'category' => 'historical', 'duration' => 780,
                'distance' => 520, 'price' => 18000, 'featured' => true,
                'titles' => ['Tatev Monastery Private Tour', 'Частный тур в Татев', 'Մասնավոր տուր դեպի Տաթև'],
                'days' => [['yerevan', 'areni', 'tatev', 'yerevan']],
            ],
            [
                'slug' => 'gyumri-city-tour', 'category' => 'city-tours', 'duration' => 600,
                'distance' => 250, 'price' => 11000, 'featured' => false,
                'titles' => ['Gyumri Culture & Architecture', 'Культура и архитектура Гюмри', 'Գյումրիի մշակույթ և ճարտարապետություն'],
                'days' => [['yerevan', 'gyumri', 'yerevan']],
            ],
            [
                'slug' => 'tsaghkadzor-sevan', 'category' => 'nature', 'duration' => 540,
                'distance' => 180, 'price' => 9000, 'featured' => false,
                'titles' => ['Tsaghkadzor & Lake Sevan', 'Цахкадзор и озеро Севан', 'Ծաղկաձոր և Սևանա լիճ'],
                'days' => [['yerevan', 'tsaghkadzor', 'lake-sevan', 'yerevan']],
            ],
            [
                'slug' => 'echmiadzin-spiritual-tour', 'category' => 'religious', 'duration' => 360,
                'distance' => 60, 'price' => 6000, 'featured' => false,
                'titles' => ['Echmiadzin Spiritual Heritage', 'Духовное наследие Эчмиадзина', 'Էջմիածնի հոգևոր ժառանգություն'],
                'days' => [['yerevan', 'echmiadzin', 'yerevan']],
            ],
            [
                'slug' => 'wine-road-jermuk-two-day', 'category' => 'multi-day', 'duration' => 2160,
                'distance' => 430, 'price' => 28000, 'featured' => true,
                'titles' => ['Wine Road & Jermuk — 2 Days', 'Винная дорога и Джермук — 2 дня', 'Գինու ճանապարհ և Ջերմուկ՝ 2 օր'],
                'days' => [
                    ['yerevan', 'khor-virap', 'areni', 'noravank', 'jermuk'],
                    ['jermuk', 'yerevan'],
                ],
            ],
            [
                'slug' => 'garni-geghard-group-tour', 'category' => 'historical', 'duration' => 480,
                'distance' => 95, 'price' => 2500, 'featured' => true, 'format' => TourFormat::Group,
                'titles' => ['Garni & Geghard Group Tour', 'Групповой тур Гарни и Гегард', 'Գառնի և Գեղարդ խմբային տուր'],
                'days' => [['yerevan', 'garni', 'symphony-of-stones', 'geghard', 'yerevan']],
            ],
            [
                'slug' => 'sevan-dilijan-group-tour', 'category' => 'nature', 'duration' => 600,
                'distance' => 220, 'price' => 3500, 'featured' => true, 'format' => TourFormat::Group,
                'titles' => ['Lake Sevan & Dilijan Group Tour', 'Групповой тур Севан и Дилижан', 'Սևան և Դիլիջան խմբային տուր'],
                'days' => [['yerevan', 'lake-sevan', 'dilijan', 'yerevan']],
            ],
        ];

        $destinationIds = Destination::query()->pluck('id', 'slug');
        $categories = TourCategory::query()->pluck('id', 'slug');

        foreach ($tours as $index => $data) {
            $tour = Tour::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'category_id' => $categories->get($data['category']),
                    'duration_minutes' => $data['duration'],
                    'approximate_distance_km' => $data['distance'],
                    'starting_price_minor' => $data['price'],
                    'currency' => CurrencyCode::Eur,
                    'pricing_type' => isset($data['format']) ? PricingType::PerPerson : PricingType::PerCar,
                    'format' => $data['format'] ?? TourFormat::Private,
                    'start_time' => isset($data['format']) ? '09:00' : null,
                    'meeting_point' => isset($data['format']) ? 'Republic Square, Yerevan' : null,
                    'active' => true,
                    'featured' => $data['featured'],
                    'max_passengers' => isset($data['format']) ? 7 : 7,
                    'pickup_available' => true,
                    'dropoff_available' => true,
                    'free_cancellation_hours' => 24,
                    'sort_order' => $index + 1,
                ],
            );

            foreach (['en', 'ru', 'hy'] as $localeIndex => $locale) {
                $title = $data['titles'][$localeIndex];
                $shortDescription = isset($data['format'])
                    ? match ($locale) {
                        'ru' => 'Присоединяйтесь к небольшой группе с профессиональным водителем и запланированным маршрутом.',
                        'hy' => 'Միացեք փոքր խմբին՝ պրոֆեսիոնալ վարորդով և նախապես պլանավորված երթուղով։',
                        default => 'Join a small group with a professional driver, a scheduled departure, and a carefully planned route.',
                    }
                : match ($locale) {
                    'ru' => 'Комфортный частный тур с профессиональным водителем, гибким маршрутом и трансфером из отеля.',
                    'hy' => 'Հարմարավետ մասնավոր տուր՝ փորձառու վարորդով, ճկուն երթուղով և հյուրանոցից տեղափոխմամբ։',
                    default => 'A comfortable private tour with a professional driver, flexible timing, and hotel pickup.',
                };

                $tour->translations()->updateOrCreate(
                    ['locale' => $locale],
                    [
                        'title' => $title,
                        'short_description' => $shortDescription,
                        'description' => $shortDescription.(isset($data['format']) ? ' The displayed price is per person.' : ' The price is for the selected car, not per passenger.'),
                        'seo_title' => "{$title} | Armenia Tour",
                        'seo_description' => $shortDescription,
                    ],
                );
            }

            foreach ($data['days'] as $dayIndex => $stopSlugs) {
                $dayNumber = $dayIndex + 1;
                $day = TourDay::query()->updateOrCreate(
                    ['tour_id' => $tour->id, 'day_number' => $dayNumber],
                    [
                        'title' => count($data['days']) > 1 ? "Day {$dayNumber}" : null,
                        'description' => null,
                        'overnight_location' => $dayNumber < count($data['days']) ? 'Jermuk' : null,
                    ],
                );

                foreach ($stopSlugs as $stopIndex => $destinationSlug) {
                    TourStop::query()->updateOrCreate(
                        [
                            'tour_id' => $tour->id,
                            'day_number' => $dayNumber,
                            'stop_order' => $stopIndex + 1,
                        ],
                        [
                            'tour_day_id' => $day->id,
                            'destination_id' => $destinationIds->get($destinationSlug),
                            'duration_minutes' => $stopIndex === 0 || $stopIndex === count($stopSlugs) - 1 ? null : 60,
                            'optional' => false,
                            'notes' => null,
                        ],
                    );
                }
            }

            if (($data['format'] ?? TourFormat::Private) === TourFormat::Private) {
                $this->seedPrice($tour, CarCategory::Comfort, $data['price'], 0);
                $this->seedPrice($tour, CarCategory::Suv, null, 4000);
                $this->seedPrice($tour, CarCategory::Minivan, null, 6000);
            }
        }
    }

    private function seedPrice(Tour $tour, CarCategory $category, ?int $fixedPrice, int $adjustment): void
    {
        TourPrice::query()->updateOrCreate(
            ['tour_id' => $tour->id, 'car_category' => $category->value],
            [
                'min_passengers' => 1,
                'max_passengers' => $category === CarCategory::Minivan ? 7 : 4,
                'valid_from' => null,
                'valid_until' => null,
                'fixed_price_minor' => $fixedPrice,
                'adjustment_minor' => $adjustment,
                'currency' => CurrencyCode::Eur,
                'active' => true,
            ],
        );
    }

}
