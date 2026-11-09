<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

/** بانرات تجريبية — php artisan db:seed --class=BannerSeeder */
class BannerSeeder extends Seeder
{
    public function run(): void
    {
        if (Banner::exists()) {
            return;
        }
        $rows = [
            ['topbar', 'شحن مجاني للطلبات فوق 500 ر.س', 'لكل مدن المملكة', null, null, '#A84400', 1],
            ['topbar', 'الدفع عند الاستلام متاح', 'ادفع لما توصلك القطعة', null, null, '#111111', 2],
            ['hero', 'قطع غيار أصلية لسيارتك', 'اختر الماركة والموديل والسنة وشوف القطع المتوافقة فقط', '/?in_stock=1', 'تسوّق الآن', '#1F2937', 1],
            ['hero', 'خصم 20% على الفلاتر', 'فلاتر الزيت والهواء والمكيف — لفترة محدودة', '/?sort=price_asc', 'شوف العروض', '#7C2D12', 2],
            ['hero', 'بديل معتمد بنفس الجودة', 'وفّر حتى 40% مع قطع تجارية معتمدة', '/?grade=cert', 'تصفّح البديل', '#0F3D3E', 3],
            ['strip', 'عروض الصيانة الدورية', 'زيت + فلتر + فحص — كل اللي تحتاجه في سلة وحدة', '/?sort=newest', 'اطلب الآن', '#FF6B00', 1],
            ['side', 'أصحاب الورش', 'سجّل كحساب تجاري واحصل على أسعار الجملة', '/track', 'اعرف أكثر', '#111111', 1],
        ];
        foreach ($rows as [$placement, $title, $subtitle, $link, $btn, $color, $sort]) {
            Banner::create(compact('placement', 'title', 'subtitle', 'sort') + ['link_url' => $link, 'button_text' => $btn, 'bg_color' => $color]);
        }
    }
}
