<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Destination;
use App\Models\Faq;
use App\Models\Tour;
use App\Models\TourCategory;
use Illuminate\Database\Seeder;

final class PersianContentSeeder extends Seeder
{
    public function run(): void
    {
        $tourTitles = [
            'garni-geghard' => 'تور خصوصی گارنی و گغارد',
            'sevan-dilijan' => 'تور خصوصی دریاچه سوان و دیلیجان',
            'khor-virap-noravank' => 'خور ویراپ، آرنی و نوراوانک',
            'tatev-day-tour' => 'تور خصوصی صومعه تاتو',
            'gyumri-city-tour' => 'فرهنگ و معماری گیومری',
            'tsaghkadzor-sevan' => 'تساغکادزور و دریاچه سوان',
            'echmiadzin-spiritual-tour' => 'میراث معنوی اچمیادزین',
            'wine-road-jermuk-two-day' => 'جاده شراب و جرموک — ۲ روز',
            'garni-geghard-group-tour' => 'تور گروهی گارنی و گغارد',
            'sevan-dilijan-group-tour' => 'تور گروهی دریاچه سوان و دیلیجان',
            'garni-geghard-copy-group' => 'تور گروهی گارنی و گغارد',
        ];

        foreach ($tourTitles as $slug => $title) {
            $tour = Tour::query()->where('slug', $slug)->first();
            if (! $tour) {
                continue;
            }

            $isGroup = $tour->format->value === 'group';
            $short = $isGroup
                ? 'با یک گروه کوچک، برنامه حرکت مشخص و مسیری دقیق، جاذبه‌های ارمنستان را کشف کنید.'
                : 'یک سفر خصوصی و راحت با راننده حرفه‌ای، زمان‌بندی انعطاف‌پذیر و ترانسفر از هتل.';
            $description = $isGroup
                ? $short.' این تور با برنامه‌ریزی روشن و قیمت‌گذاری برای هر نفر، تجربه‌ای راحت و به‌یادماندنی فراهم می‌کند.'
                : $short.' قیمت برای خودروی انتخابی محاسبه می‌شود و به تعداد مسافران وابسته نیست.';

            $tour->translations()->updateOrCreate(['locale' => 'fa'], [
                'title' => $title,
                'short_description' => $short,
                'description' => $description,
                'inclusions' => [],
                'exclusions' => [],
                'seo_title' => $title.' | تور ارمنستان',
                'seo_description' => $short,
            ]);
        }

        $destinations = [
            'yerevan' => 'ایروان', 'garni' => 'گارنی', 'symphony-of-stones' => 'سمفونی سنگ‌ها',
            'geghard' => 'گغارد', 'lake-sevan' => 'دریاچه سوان', 'dilijan' => 'دیلیجان',
            'khor-virap' => 'خور ویراپ', 'areni' => 'آرنی', 'noravank' => 'نوراوانک',
            'tatev' => 'تاتو', 'gyumri' => 'گیومری', 'tsaghkadzor' => 'تساغکادزور',
            'jermuk' => 'جرموک', 'amberd' => 'آمبرد', 'echmiadzin' => 'اچمیادزین',
        ];

        foreach ($destinations as $slug => $name) {
            $destination = Destination::query()->where('slug', $slug)->first();
            if (! $destination) {
                continue;
            }

            $short = $name.' یکی از دیدنی‌ترین مقصدهای ارمنستان است که تاریخ، فرهنگ و طبیعت این کشور را به نمایش می‌گذارد.';
            $destination->translations()->updateOrCreate(['locale' => 'fa'], [
                'name' => $name,
                'short_description' => $short,
                'description' => 'در سفر به '.$name.' می‌توانید با میراث محلی، مناظر زیبا و داستان‌های ارمنستان آشنا شوید. این مقصد را می‌توان در یک تور خصوصی یا برنامه سفر اختصاصی قرار داد.',
                'seo_title' => $name.'؛ راهنمای سفر و تور | تور ارمنستان',
                'seo_description' => $short,
            ]);
        }

        $categories = [
            'historical' => 'تاریخی', 'nature' => 'طبیعت', 'wine' => 'شراب',
            'adventure' => 'ماجراجویی', 'city-tours' => 'تورهای شهری', 'winter' => 'زمستانی',
            'family' => 'خانوادگی', 'multi-day' => 'چندروزه', 'religious' => 'مذهبی',
            'food-culture' => 'غذا و فرهنگ',
        ];

        foreach ($categories as $slug => $name) {
            $category = TourCategory::query()->where('slug', $slug)->first();
            if (! $category) {
                continue;
            }

            $description = 'بهترین تورهای '.$name.' ارمنستان را با برنامه روشن، رانندگان حرفه‌ای و امکان رزرو آسان مقایسه کنید.';
            $category->translations()->updateOrCreate(['locale' => 'fa'], [
                'name' => $name,
                'description' => $description,
                'seo_title' => 'تورهای '.$name.' ارمنستان | تور ارمنستان',
                'seo_description' => $description,
            ]);
        }

        $faqs = [
            1 => ['قیمت تور چگونه محاسبه می‌شود؟', 'قیمت بیشتر تورها برای خودروی خصوصی محاسبه می‌شود، نه برای هر مسافر.'],
            2 => ['آیا از هتل من ترانسفر دارید؟', 'بله. ترانسفر از هتل، اقامتگاه، فرودگاه یا نشانی دلخواه امکان‌پذیر است.'],
            3 => ['آیا می‌توانم مسیر را تغییر دهم؟', 'بله. می‌توانید سفر اختصاصی بسازید یا از تیم ما بخواهید مسیر تور خصوصی را متناسب با خواسته شما تنظیم کند.'],
            4 => ['چه زمانی باید پرداخت کنم؟', 'هنگام رزرو می‌توانید یکی از روش‌های پرداخت موجود را انتخاب کنید.'],
            5 => ['آیا می‌توانم رزرو را لغو کنم؟', 'مهلت لغو رایگان در صفحه هر تور نمایش داده می‌شود. لطفاً در صورت نیاز هرچه زودتر با پشتیبانی تماس بگیرید.'],
        ];

        foreach ($faqs as $id => [$question, $answer]) {
            $faq = Faq::query()->find($id);
            $faq?->translations()->updateOrCreate(['locale' => 'fa'], compact('question', 'answer'));
        }
    }
}
