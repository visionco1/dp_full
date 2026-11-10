<?php

namespace Database\Seeders;

use App\Http\Presenter;
use App\Models\AppNotification;
use App\Models\Category;
use App\Models\CarModel;
use App\Models\Make;
use App\Models\Part;
use App\Models\StockAlert;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * بيانات عرض إضافية: ماركات وقطع أكثر، وصف وصور لكل القطع، صور الأقسام، وإشعارات للعميل التجريبي.
 * آمن للتشغيل أكثر من مرة على قاعدة موجودة: php artisan db:seed --class=DemoCatalogSeeder
 */
class DemoCatalogSeeder extends Seeder
{
    private const CATEGORY_COLORS = ['brakes' => '#FF6B00', 'filters' => '#0EA5E9', 'lighting' => '#FACC15', 'body' => '#EF4444',
        'suspension' => '#8B5CF6', 'engine' => '#10B981', 'electrical' => '#F59E0B', 'fluids' => '#14B8A6'];

    public function run(): void
    {
        $models = $this->makes();
        $this->parts($models);
        $this->subcategories();
        $this->describeAndIllustrate();
        $this->categoryImages();
        $this->customerExtras();
    }

    /** ماركات وموديلات إضافية → خريطة 'Make Model' => CarModel لكل الموديلات */
    private function makes(): array
    {
        foreach ([
            ['هوندا', 'Honda', [['أكورد', 'Accord', 2008, 2024], ['سيفيك', 'Civic', 2008, 2024]]],
            ['فورد', 'Ford', [['تورس', 'Taurus', 2010, 2024], ['إكسبلورر', 'Explorer', 2011, 2024]]],
            ['لكزس', 'Lexus', [['ES', 'ES', 2013, 2024]]],
        ] as [$ar, $en, $list]) {
            $make = Make::firstOrCreate(['name_en' => $en], ['name_ar' => $ar]);
            foreach ($list as [$mar, $men, $f, $t]) {
                CarModel::firstOrCreate(['make_id' => $make->id, 'name_en' => $men], ['name_ar' => $mar, 'year_from' => $f, 'year_to' => $t]);
            }
        }
        return CarModel::with('make')->get()->keyBy(fn ($m) => $m->make->name_en.' '.$m->name_en)->all();
    }

    private function parts(array $m): void
    {
        $cats = Category::pluck('id', 'slug');
        $camry = [['Toyota Camry', 2018, 2024]];
        $camryCorolla = [['Toyota Camry', 2012, 2024], ['Toyota Corolla', 2014, 2024]];
        $hk = [['Hyundai Sonata', 2011, 2024], ['Hyundai Elantra', 2011, 2024], ['Hyundai Accent', 2011, 2024], ['Kia Cerato', 2013, 2024], ['Kia Sportage', 2011, 2024]];
        $nissan = [['Nissan Sunny', 2012, 2024], ['Nissan Patrol', 2010, 2024]];
        $honda = [['Honda Accord', 2013, 2024], ['Honda Civic', 2012, 2024]];
        $ford = [['Ford Taurus', 2013, 2024], ['Ford Explorer', 2011, 2024]];
        $land = [['Toyota Land Cruiser', 2008, 2024], ['Lexus ES', 2013, 2024]];

        // [sku, عربي, إنجليزي, قسم, درجة, جهة, سعر, تكلفة, مجموعة, كمية, شحن قطعة كبيرة, توافق, ماركة]
        $rows = [
            // الفرامل
            ['43512-06190', 'هوب فرامل أمامي', 'Front brake disc', 'brakes', 'oem', null, 420, 290, 'disc-front-camry', 14, 0, $camry, 'تويوتا الأصلي'],
            ['AM-43512-F', 'هوب فرامل أمامي', 'Front brake disc', 'brakes', 'cert', null, 230, 150, 'disc-front-camry', 20, 0, $camry, 'Brembo'],
            ['04466-06200', 'فحمات فرامل خلفية (طقم)', 'Rear brake pads set', 'brakes', 'oem', null, 260, 175, 'pads-rear-camry', 11, 0, $camry, 'تويوتا الأصلي'],
            ['58101-F2A00', 'فحمات فرامل أمامية إلنترا', 'Front brake pads Elantra', 'brakes', 'oem', null, 240, 160, 'pads-elantra', 16, 0, [['Hyundai Elantra', 2016, 2024]], 'هيونداي موبيس'],
            ['41060-4BA0A', 'فحمات فرامل أمامية صني', 'Front brake pads Sunny', 'brakes', 'cert', null, 135, 85, 'pads-sunny', 22, 0, [['Nissan Sunny', 2012, 2024]], 'Akebono'],
            ['45022-TVA-A00', 'فحمات فرامل أمامية أكورد', 'Front brake pads Accord', 'brakes', 'oem', null, 295, 200, 'pads-accord', 7, 0, [['Honda Accord', 2018, 2024]], 'هوندا الأصلي'],
            // الفلاتر
            ['87139-06080', 'فلتر مكيف (كابينة)', 'Cabin air filter', 'filters', 'oem', null, 85, 52, 'cabin-filter', 45, 0, $camryCorolla, 'تويوتا الأصلي'],
            ['AM-87139-C', 'فلتر مكيف (كابينة)', 'Cabin air filter', 'filters', 'cert', null, 45, 24, 'cabin-filter', 70, 0, $camryCorolla, 'Mann'],
            ['23300-0V010', 'فلتر بنزين', 'Fuel filter', 'filters', 'oem', null, 160, 105, 'fuel-filter', 9, 0, $camry, 'تويوتا الأصلي'],
            ['26300-35505', 'فلتر زيت هيونداي وكيا', 'Oil filter Hyundai/Kia', 'filters', 'oem', null, 32, 18, 'oil-filter-hk', 95, 0, $hk, 'هيونداي موبيس'],
            ['15208-65F0E', 'فلتر زيت نيسان', 'Oil filter Nissan', 'filters', 'oem', null, 30, 17, 'oil-filter-nissan', 80, 0, $nissan, 'نيسان الأصلي'],
            ['15400-RTA-003', 'فلتر زيت هوندا', 'Oil filter Honda', 'filters', 'oem', null, 34, 19, 'oil-filter-honda', 60, 0, $honda, 'هوندا الأصلي'],
            ['FL-500S', 'فلتر زيت فورد', 'Oil filter Ford', 'filters', 'cert', null, 36, 20, 'oil-filter-ford', 40, 0, $ford, 'Motorcraft'],
            // الإضاءة
            ['81170-06A60', 'كشاف أمامي يسار', 'Headlamp left', 'lighting', 'oem', 'L', 780, 560, 'headlamp', 2, 40, $camry, 'تويوتا الأصلي'],
            ['AM-81150-R', 'كشاف أمامي يمين', 'Headlamp right', 'lighting', 'cert', 'R', 390, 260, 'headlamp', 5, 40, $camry, 'Depo'],
            ['AM-81170-L', 'كشاف أمامي يسار', 'Headlamp left', 'lighting', 'cert', 'L', 390, 260, 'headlamp', 4, 40, $camry, 'Depo'],
            ['81551-06A60', 'سطب خلفي يمين', 'Tail lamp right', 'lighting', 'oem', 'R', 520, 370, 'taillamp', 3, 0, $camry, 'تويوتا الأصلي'],
            ['H11-LED-PR', 'لمبة LED H11 (زوج)', 'LED bulb H11 (pair)', 'lighting', 'first', null, 120, 60, 'led-h11', 50, 0, array_merge($camryCorolla, $hk), 'Philips'],
            // البودي
            ['52119-06C90', 'صدام أمامي', 'Front bumper', 'body', 'oem', null, 1650, 1200, 'bumper-front-camry', 2, 120, $camry, 'تويوتا الأصلي'],
            ['AM-52119-F', 'صدام أمامي', 'Front bumper', 'body', 'cert', null, 790, 540, 'bumper-front-camry', 4, 120, $camry, 'معتمد'],
            ['87910-06C90', 'مراية جانبية يمين', 'Side mirror right', 'body', 'oem', 'R', 920, 650, 'mirror-camry', 3, 0, $camry, 'تويوتا الأصلي'],
            ['53301-06D90', 'كبوت', 'Hood', 'body', 'oem', null, 2100, 1550, 'hood-camry', 1, 150, $camry, 'تويوتا الأصلي'],
            // التعليق
            ['48510-80784', 'مساعد أمامي يمين', 'Front shock absorber right', 'suspension', 'oem', 'R', 690, 480, 'shock-front-camry', 6, 0, $camry, 'تويوتا الأصلي'],
            ['AM-48510-R', 'مساعد أمامي يمين', 'Front shock absorber right', 'suspension', 'cert', 'R', 360, 235, 'shock-front-camry', 10, 0, $camry, 'KYB'],
            ['48068-06180', 'مقص أمامي سفلي يمين', 'Lower control arm right', 'suspension', 'oem', 'R', 540, 380, 'arm-lower-camry', 5, 0, $camry, 'تويوتا الأصلي'],
            ['54651-3S000', 'مساعد أمامي سوناتا', 'Front shock absorber Sonata', 'suspension', 'cert', null, 410, 270, 'shock-sonata', 8, 0, [['Hyundai Sonata', 2011, 2019]], 'Mando'],
            ['48510-69675', 'مساعد أمامي لاندكروزر', 'Front shock absorber Land Cruiser', 'suspension', 'oem', null, 980, 700, 'shock-lc', 4, 0, [['Toyota Land Cruiser', 2008, 2021]], 'تويوتا الأصلي'],
            // المحرك
            ['90919-01275', 'بواجي إيريديوم (حبة)', 'Iridium spark plug', 'engine', 'oem', null, 65, 38, 'spark-plug', 120, 0, array_merge($camryCorolla, $land), 'Denso'],
            ['FG-SPARK-4', 'بواجي (طقم 4 حبات)', 'Spark plugs (set of 4)', 'engine', 'first', null, 120, 65, 'spark-plug', 35, 0, array_merge($camryCorolla, $hk), 'NGK'],
            ['16100-09511', 'طرمبة ماء', 'Water pump', 'engine', 'oem', null, 480, 340, 'water-pump', 6, 0, $camry, 'Aisin'],
            ['90916-02717', 'سير المحرك (الدينمو)', 'Drive belt', 'engine', 'oem', null, 140, 90, 'drive-belt', 18, 0, $camryCorolla, 'تويوتا الأصلي'],
            ['16400-0V160', 'رديتر ماء', 'Radiator', 'engine', 'oem', null, 1150, 820, 'radiator-camry', 3, 60, $camry, 'تويوتا الأصلي'],
            // الكهرباء
            ['AGM-80AH', 'بطارية 80 أمبير AGM', 'AGM battery 80Ah', 'electrical', 'cert', null, 690, 510, 'battery-80', 7, 0, array_merge($land, $ford), 'Varta'],
            ['27060-0V160', 'دينمو', 'Alternator', 'electrical', 'oem', null, 1450, 1050, 'alternator-camry', 2, 0, $camry, 'Denso'],
            ['28100-0V060', 'سلف', 'Starter motor', 'electrical', 'oem', null, 1100, 790, 'starter-camry', 0, 0, $camry, 'Denso'],
            ['89467-06150', 'حساس أكسجين', 'Oxygen sensor', 'electrical', 'oem', null, 520, 360, 'o2-sensor', 4, 0, $camry, 'Denso'],
            // الزيوت والسوائل
            ['08880-83388', 'زيت محرك 5W-30 (4 لتر)', 'Engine oil 5W-30 (4L)', 'fluids', 'oem', null, 165, 112, 'oil-5w30', 55, 0, array_merge($land, $hk), 'تويوتا الأصلي'],
            ['FG-5W30-4L', 'زيت محرك 5W-30 (4 لتر)', 'Engine oil 5W-30 (4L)', 'fluids', 'first', null, 95, 58, 'oil-5w30', 90, 0, array_merge($land, $hk, $nissan), 'Total'],
            ['COOL-TY-4L', 'ماء رديتر (4 لتر)', 'Coolant (4L)', 'fluids', 'oem', null, 75, 45, 'coolant', 40, 0, $camryCorolla, 'تويوتا الأصلي'],
            ['DOT4-1L', 'زيت فرامل DOT4 (1 لتر)', 'Brake fluid DOT4 (1L)', 'fluids', 'cert', null, 35, 18, 'brake-fluid', 65, 0, array_merge($camryCorolla, $hk, $honda), 'Bosch'],
            ['ATF-WS-4L', 'زيت قير أوتوماتيك WS (4 لتر)', 'ATF WS (4L)', 'fluids', 'oem', null, 240, 165, 'atf-ws', 20, 0, array_merge($camryCorolla, $land), 'تويوتا الأصلي'],
        ];

        foreach ($rows as [$sku, $ar, $en, $cat, $grade, $side, $price, $cost, $group, $qty, $ship, $fits, $brand]) {
            $p = Part::updateOrCreate(['sku' => $sku], [
                'name_ar' => $ar, 'name_en' => $en, 'category_id' => $cats[$cat] ?? null, 'grade' => $grade, 'side' => $side,
                'price' => $price, 'cost' => $cost, 'group_key' => $group, 'is_bulky' => $ship > 0, 'shipping_fee' => $ship, 'brand' => $brand,
            ]);
            $p->inventory()->firstOrCreate([], ['on_hand' => $qty, 'reserved' => 0, 'location' => strtoupper(substr($cat, 0, 2)).'-'.random_int(1, 9).'-'.random_int(10, 40)]);
            foreach ($fits as [$car, $f, $t]) {
                if (isset($m[$car])) {
                    $p->fitments()->firstOrCreate(['car_model_id' => $m[$car]->id], ['year_from' => $f, 'year_to' => $t]);
                }
            }
        }
    }

    /** وصف وصورة لكل قطعة ناقصة (يشمل قطع DatabaseSeeder) */
    private function describeAndIllustrate(): void
    {
        $warranty = ['oem' => 'ضمان سنة من الوكيل', 'cert' => 'ضمان 6 أشهر', 'first' => 'ضمان 3 أشهر'];
        Part::with('category.parent', 'fitments.carModel.make')->get()->each(function (Part $p) use ($warranty) {
            $changes = [];
            if (!$p->description) {
                $cars = $p->fitments->map(fn ($f) => $f->carModel ? $f->carModel->make->name_ar.' '.$f->carModel->name_ar.' '.$f->year_from.'–'.$f->year_to : null)->filter()->take(4)->implode('، ');
                $changes['description'] = trim(implode("\n", array_filter([
                    $p->name_ar.' — '.(Presenter::GRADES[$p->grade] ?? $p->grade).($p->brand ? ' من '.$p->brand : '').'.',
                    $cars ? 'متوافق مع: '.$cars.'.' : null,
                    'رقم القطعة: '.$p->sku.' · '.($warranty[$p->grade] ?? 'ضمان المتجر').'.',
                    $p->is_bulky ? 'قطعة كبيرة — تُضاف رسوم شحن خاصة.' : 'تأكد من التوافق بإضافة سيارتك في الكراج.',
                ])));
            }
            if (!$p->image && ($img = SeedArt::part(SeedArt::KINDS[$p->category?->parent?->slug ?? $p->category?->slug] ?? 'gear', $p->sku, $p->grade))) {
                $changes['image'] = $img;
            }
            $changes && $p->update($changes);
        });
    }

    /**
     * الأقسام الفرعية: [قسم رئيسي => [[slug, عربي, إنجليزي, كلمات في اسم القطعة]]]
     * القطع في القسم الرئيسي تُنقل للفرعي المطابق لاسمها. آمن للتكرار.
     */
    private const SUBCATEGORIES = [
        'brakes' => [['brake-pads', 'فحمات فرامل', 'Brake pads', ['فحمات']], ['brake-discs', 'هوبات فرامل', 'Brake discs', ['هوب']]],
        'filters' => [['oil-filters', 'فلاتر زيت', 'Oil filters', ['فلتر زيت']], ['air-filters', 'فلاتر هواء', 'Air filters', ['فلتر هواء']],
            ['cabin-filters', 'فلاتر مكيف', 'Cabin filters', ['فلتر مكيف']], ['fuel-filters', 'فلاتر بنزين', 'Fuel filters', ['فلتر بنزين']]],
        'lighting' => [['headlights', 'كشافات', 'Headlights', ['كشاف']], ['tail-lights', 'سطوب خلفية', 'Tail lights', ['سطب']], ['bulbs', 'لمبات', 'Bulbs', ['لمبة']]],
        'body' => [['fenders', 'رفارف', 'Fenders', ['رفرف']], ['bumpers', 'صدامات', 'Bumpers', ['صدام']], ['mirrors', 'مرايات', 'Mirrors', ['مراية']], ['hoods', 'كبابيت', 'Hoods', ['كبوت']]],
        'suspension' => [['shock-absorbers', 'مساعدات', 'Shock absorbers', ['مساعد']], ['control-arms', 'مقصات وأذرع', 'Control arms', ['مقص', 'ذراع']]],
        'engine' => [['spark-plugs', 'بواجي', 'Spark plugs', ['بواجي']], ['engine-cooling', 'تبريد المحرك', 'Engine cooling', ['طرمبة ماء', 'رديتر']], ['belts', 'سيور', 'Belts', ['سير']]],
        'electrical' => [['batteries', 'بطاريات', 'Batteries', ['بطارية']], ['alternators-starters', 'دينمو وسلف', 'Alternators & starters', ['دينمو', 'سلف']], ['sensors', 'حساسات', 'Sensors', ['حساس']]],
        'fluids' => [['engine-oil', 'زيوت محرك', 'Engine oil', ['زيت محرك']], ['transmission-oil', 'زيوت قير', 'Transmission oil', ['زيت قير']], ['coolant-brake-fluid', 'ماء رديتر وزيت فرامل', 'Coolant & brake fluid', ['ماء رديتر', 'زيت فرامل']]],
    ];

    private function subcategories(): void
    {
        foreach (self::SUBCATEGORIES as $parentSlug => $subs) {
            $parent = Category::where('slug', $parentSlug)->whereNull('parent_id')->first();
            if (!$parent) {
                continue;
            }
            foreach ($subs as $i => [$slug, $ar, $en, $words]) {
                $sub = Category::firstOrCreate(['slug' => $slug], ['parent_id' => $parent->id, 'name_ar' => $ar, 'name_en' => $en, 'sort' => $i + 1]);
                if ($sub->parent_id !== $parent->id) {
                    continue;   // slug مستخدم لقسم آخر عدّله المدير — لا نلمسه
                }
                Part::where('category_id', $parent->id)
                    ->where(fn ($q) => collect($words)->each(fn ($w) => $q->orWhere('name_ar', 'like', "%$w%")))
                    ->update(['category_id' => $sub->id]);
            }
        }
    }

    private function categoryImages(): void
    {
        $alt = ['#111827', '#475569', '#0F766E', '#7C2D12'];
        foreach (Category::with('parent')->whereNull('image')->get() as $c) {
            $rootSlug = $c->parent?->slug ?? $c->slug;
            $color = $c->parent ? $alt[($c->sort ?: 1) % count($alt)] : (self::CATEGORY_COLORS[$c->slug] ?? '#FF6B00');
            if ($img = SeedArt::category($c->slug, $color, SeedArt::KINDS[$rootSlug] ?? null)) {
                $c->update(['image' => $img]);
            }
        }
    }

    /** إشعارات وتنبيه توفر للعميل التجريبي (يظهر عداد الإشعارات في الـ Home) */
    private function customerExtras(): void
    {
        $c = User::where('phone', '966512345678')->first();
        if (!$c) {
            return;
        }
        $rows = [
            ['order', 'تم شحن طلبك DM-261009-0318', 'رقم البوليصة 2900447188 — يصلك خلال يومين.', ['order_number' => 'DM-261009-0318'], now()->subDays(3)],
            ['offer', 'خصم 20% على الفلاتر', 'العرض ساري حتى نهاية الأسبوع على كل فلاتر الزيت والهواء.', ['category' => 'filters'], null],
            ['price', 'انخفض سعر فحمات الفرامل', 'فحمات فرامل أمامية (طقم) لكامري صارت أرخص.', ['sku' => 'AM-04465-F'], null],
        ];
        foreach ($rows as [$type, $title, $body, $data, $read]) {
            AppNotification::firstOrCreate(['user_id' => $c->id, 'title' => $title], ['type' => $type, 'body' => $body, 'data' => $data, 'read_at' => $read]);
        }
        if ($starter = Part::where('sku', '28100-0V060')->first()) {   // قطعة غير متوفرة → تنبيه "أخبرني عند التوفر"
            StockAlert::firstOrCreate(['user_id' => $c->id, 'part_id' => $starter->id]);
        }
    }
}
