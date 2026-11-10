<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

/**
 * بانرات وإعلانات تجريبية مع صور مولّدة — php artisan db:seed --class=BannerSeeder
 * آمن للتشغيل أكثر من مرة: يحدّث البانر بنفس (المكان + العنوان) ولا يكرره، ولا يغيّر صورة رفعتها من لوحة الإدارة.
 */
class BannerSeeder extends Seeder
{
    public function run(): void
    {
        $size = ['hero' => [1600, 600], 'strip' => [1600, 400], 'side' => [600, 800]];

        // [مكان, عنوان, نص فرعي, رابط, زر, لون1, لون2, رسمة, شارة, ترتيب, أيام العرض]
        $rows = [
            ['topbar', 'شحن مجاني للطلبات فوق 500 ر.س', 'لكل مدن المملكة', null, null, '#A84400', null, null, null, 1, null],
            ['topbar', 'الدفع عند الاستلام متاح', 'ادفع لما توصلك القطعة', null, null, '#111111', null, null, null, 2, null],
            ['topbar', 'عروض نهاية العام بدأت', 'خصومات حتى 30% على قطع مختارة', '/?sort=price_asc', 'تسوّق', '#7C2D12', null, null, null, 3, 45],

            ['hero', 'قطع غيار أصلية لسيارتك', 'اختر الماركة والموديل والسنة وشوف القطع المتوافقة فقط', '/?in_stock=1', 'تسوّق الآن', '#1F2937', '#0B0F14', 'disc', null, 1, null],
            ['hero', 'خصم 20% على الفلاتر', 'فلاتر الزيت والهواء والمكيف — لفترة محدودة', '/?category=filters', 'شوف العروض', '#7C2D12', '#1C0A04', 'filter', '-20%', 2, 30],
            ['hero', 'بديل معتمد بنفس الجودة', 'وفّر حتى 40% مع قطع تجارية معتمدة', '/?grade=cert', 'تصفّح البديل', '#0F3D3E', '#061A1B', 'shock', '-40%', 3, null],
            ['hero', 'بطاريات بضمان سنة', 'تركيب وفحص مجاني مع كل بطارية', '/?category=electrical', 'اختر بطاريتك', '#1E1B4B', '#0B0A1F', 'battery', null, 4, null],

            ['strip', 'عروض الصيانة الدورية', 'زيت + فلتر + فحص — كل اللي تحتاجه في سلة وحدة', '/?category=fluids', 'اطلب الآن', '#FF6B00', '#A84400', 'bottle', null, 1, null],
            ['strip', 'فرامل آمنة لكل مشوار', 'فحمات وهوبات أصلية وبديلة معتمدة', '/?category=brakes', 'تسوّق الفرامل', '#111827', '#374151', 'disc', null, 2, null],

            ['side', 'أصحاب الورش', 'سجّل كحساب تجاري واحصل على أسعار الجملة', '/track', 'اعرف أكثر', '#111111', '#3F1D0B', 'gear', null, 1, null],
            ['side', 'إضاءة LED', 'ضعف الإضاءة واستهلاك أقل', '/?category=lighting', 'شوف الإضاءة', '#0C4A6E', '#082F49', 'lamp', null, 2, null],
        ];

        foreach ($rows as $i => [$placement, $title, $subtitle, $link, $btn, $c1, $c2, $kind, $badge, $sort, $days]) {
            $banner = Banner::firstOrNew(['placement' => $placement, 'title' => $title]);
            $banner->fill([
                'subtitle' => $subtitle, 'link_url' => $link, 'button_text' => $btn, 'bg_color' => $c1, 'sort' => $sort,
                'active' => $banner->exists ? $banner->active : true,
                'ends_at' => $days ? ($banner->ends_at ?? now()->addDays($days)) : null,
            ]);
            if ($kind && !$banner->image && isset($size[$placement])) {
                [$w, $h] = $size[$placement];
                $banner->image = SeedArt::banner($placement.'-'.($i + 1), $w, $h, $c1, $c2 ?? $c1, $kind, $badge);
            }
            $banner->save();
        }
    }
}
