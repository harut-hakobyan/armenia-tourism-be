<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Setting;
use Illuminate\Database\Seeder;

final class CmsSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            ['booking', 'How is the tour price calculated?', 'Most tours are priced per private car, not per passenger.'],
            ['booking', 'Can you pick me up from my hotel?', 'Yes. Hotel, Airbnb, airport, and custom-address pickup are supported.'],
            ['tours', 'Can I change the route?', 'Yes. Build a custom trip or ask our team to adapt a private itinerary.'],
            ['payments', 'When do I pay?', 'You can choose an available payment method during booking.'],
            ['cancellation', 'Can I cancel?', 'Each tour shows its free-cancellation window. Contact support as early as possible.'],
        ];

        foreach ($faqs as $index => [$category, $question, $answer]) {
            $faq = Faq::query()->updateOrCreate(
                ['category' => $category, 'sort_order' => $index + 1],
                ['active' => true],
            );
            foreach (['en', 'ru', 'hy'] as $locale) {
                $faq->translations()->updateOrCreate(
                    ['locale' => $locale],
                    ['question' => $question, 'answer' => $answer],
                );
            }
        }

        $settings = [
            'company_name' => ['Armenia Tourism', true],
            'company_email' => ['hello@armeniatourism.local', true],
            'company_phone' => ['+374 99 123456', true],
            'whatsapp_number' => ['37499123456', true],
            'telegram' => ['', true],
            'instagram' => ['', true],
            'facebook' => ['', true],
            'office_address' => ['Yerevan, Armenia', true],
            'default_currency' => ['EUR', true],
            'cancellation_policy' => ['Free cancellation is governed by the selected tour cancellation window.', true],
        ];
        foreach ($settings as $key => [$value, $public]) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'is_public' => $public]);
        }
    }
}
