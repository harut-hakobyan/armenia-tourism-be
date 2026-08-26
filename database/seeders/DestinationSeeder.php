<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Destination;
use Illuminate\Database\Seeder;

final class DestinationSeeder extends Seeder
{
    public function run(): void
    {
        $destinations = [
            ['yerevan', 40.1872023, 44.5152090, 'Yerevan, Armenia', true, ['Yerevan', 'Ереван', 'Երևան']],
            ['garni', 40.1128610, 44.7299850, 'Garni, Kotayk, Armenia', true, ['Garni', 'Гарни', 'Գառնի']],
            ['symphony-of-stones', 40.1147960, 44.7419340, 'Garni Gorge, Armenia', true, ['Symphony of Stones', 'Симфония камней', 'Քարերի սիմֆոնիա']],
            ['geghard', 40.1404410, 44.8185290, 'Geghard, Kotayk, Armenia', true, ['Geghard', 'Гегард', 'Գեղարդ']],
            ['lake-sevan', 40.5649780, 45.0106550, 'Lake Sevan, Armenia', true, ['Lake Sevan', 'Озеро Севан', 'Սևանա լիճ']],
            ['dilijan', 40.7403910, 44.8634070, 'Dilijan, Tavush, Armenia', true, ['Dilijan', 'Дилижан', 'Դիլիջան']],
            ['khor-virap', 39.8789970, 44.5761410, 'Khor Virap, Ararat, Armenia', true, ['Khor Virap', 'Хор Вирап', 'Խոր Վիրապ']],
            ['areni', 39.7202820, 45.1847390, 'Areni, Vayots Dzor, Armenia', true, ['Areni', 'Арени', 'Արենի']],
            ['noravank', 39.6847350, 45.2325870, 'Noravank, Vayots Dzor, Armenia', true, ['Noravank', 'Нораванк', 'Նորավանք']],
            ['tatev', 39.3792010, 46.2500190, 'Tatev, Syunik, Armenia', true, ['Tatev', 'Татев', 'Տաթև']],
            ['gyumri', 40.7894330, 43.8474290, 'Gyumri, Shirak, Armenia', false, ['Gyumri', 'Гюмри', 'Գյումրի']],
            ['tsaghkadzor', 40.5325870, 44.7203090, 'Tsaghkadzor, Kotayk, Armenia', false, ['Tsaghkadzor', 'Цахкадзор', 'Ծաղկաձոր']],
            ['jermuk', 39.8417330, 45.6694040, 'Jermuk, Vayots Dzor, Armenia', false, ['Jermuk', 'Джермук', 'Ջերմուկ']],
            ['amberd', 40.3883510, 44.2258060, 'Amberd, Aragatsotn, Armenia', false, ['Amberd', 'Амберд', 'Ամբերդ']],
            ['echmiadzin', 40.1619960, 44.2916760, 'Vagharshapat, Armavir, Armenia', false, ['Echmiadzin', 'Эчмиадзин', 'Էջմիածին']],
        ];

        foreach ($destinations as $index => [$slug, $latitude, $longitude, $address, $featured, $names]) {
            $destination = Destination::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'address' => $address,
                    'active' => true,
                    'featured' => $featured,
                    'sort_order' => $index + 1,
                ],
            );

            foreach (['en', 'ru', 'hy'] as $localeIndex => $locale) {
                $name = $names[$localeIndex];
                $shortDescription = match ($locale) {
                    'ru' => "Откройте для себя {$name} во время комфортного частного путешествия по Армении.",
                    'hy' => "Բացահայտեք {$name}-ը Հայաստանում հարմարավետ մասնավոր ուղևորության ընթացքում։",
                    default => "Discover {$name} on a comfortable private journey through Armenia.",
                };

                $destination->translations()->updateOrCreate(
                    ['locale' => $locale],
                    [
                        'name' => $name,
                        'short_description' => $shortDescription,
                        'description' => $shortDescription.' Enjoy flexible timing, hotel pickup, and a professional local driver.',
                        'seo_title' => "{$name} Private Tours in Armenia",
                        'seo_description' => $shortDescription,
                    ],
                );
            }
        }
    }
}
