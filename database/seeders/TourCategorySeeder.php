<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TourCategory;
use Illuminate\Database\Seeder;

final class TourCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['historical', 'Historical', 'Исторические', 'Պատմական'],
            ['nature', 'Nature', 'Природа', 'Բնություն'],
            ['wine', 'Wine', 'Винные', 'Գինու'],
            ['adventure', 'Adventure', 'Приключения', 'Արկածային'],
            ['city-tours', 'City Tours', 'Городские туры', 'Քաղաքային տուրեր'],
            ['winter', 'Winter', 'Зимние', 'Ձմեռային'],
            ['family', 'Family', 'Семейные', 'Ընտանեկան'],
            ['multi-day', 'Multi-day', 'Многодневные', 'Բազմօրյա'],
            ['religious', 'Religious', 'Религиозные', 'Կրոնական'],
            ['food-culture', 'Food & Culture', 'Еда и культура', 'Սնունդ և մշակույթ'],
        ];

        foreach ($categories as $index => [$slug, $english, $russian, $armenian]) {
            $category = TourCategory::query()->updateOrCreate(
                ['slug' => $slug],
                ['active' => true, 'sort_order' => $index + 1],
            );

            foreach (['en' => $english, 'ru' => $russian, 'hy' => $armenian] as $locale => $name) {
                $category->translations()->updateOrCreate(
                    ['locale' => $locale],
                    [
                        'name' => $name,
                        'description' => "{$name} private tours with flexible routes and professional drivers.",
                        'seo_title' => "{$name} Tours in Armenia",
                        'seo_description' => "Explore {$name} tours across Armenia by private car.",
                    ],
                );
            }
        }
    }
}
