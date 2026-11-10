<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Category;
use App\Models\CarModel;
use App\Models\Make;
use App\Models\Order;
use App\Models\Part;
use App\Models\Role;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---- الأدوار والموظفون ----
        $admin = Role::create(['slug' => 'admin', 'name' => 'مدير النظام', 'permissions' => ['*'], 'locked' => true]);
        $pos = Role::create(['slug' => 'pos', 'name' => 'موظف المعرض', 'permissions' => ['pos.use', 'inventory.view']]);
        $ops = Role::create(['slug' => 'orders', 'name' => 'مسؤول الطلبات', 'permissions' => ['dashboard.view', 'inventory.view', 'orders.view', 'orders.manage', 'orders.verify', 'orders.print']]);

        User::create(['name' => 'سلطان الحربي', 'email' => 'admin@store.example', 'password' => 'password', 'is_staff' => true, 'role_id' => $admin->id, 'phone_verified_at' => now()]);
        User::create(['name' => 'عمر الشمري', 'email' => 'omar.pos@store.example', 'password' => 'password', 'is_staff' => true, 'role_id' => $pos->id, 'phone_verified_at' => now()]);
        User::create(['name' => 'نوره العتيبي', 'email' => 'orders@store.example', 'password' => 'password', 'is_staff' => true, 'role_id' => $ops->id, 'phone_verified_at' => now()]);

        // ---- الأقسام ----
        $cats = [];
        foreach ([['brakes', 'الفرامل', 'Brakes'], ['filters', 'الفلاتر', 'Filters'], ['lighting', 'الإضاءة', 'Lighting'], ['body', 'البودي والصاج', 'Body'],
            ['suspension', 'التعليق والمقصات', 'Suspension'], ['engine', 'المحرك', 'Engine'], ['electrical', 'الكهرباء والبطاريات', 'Electrical'], ['fluids', 'الزيوت والسوائل', 'Fluids']] as $i => [$slug, $ar, $en]) {
            $cats[$slug] = Category::create(['slug' => $slug, 'name_ar' => $ar, 'name_en' => $en, 'sort' => $i]);
        }

        // ---- الشركات والموديلات ----
        $m = [];
        foreach ([
            ['تويوتا', 'Toyota', [['كامري', 'Camry', 2002, 2024], ['كورولا', 'Corolla', 2002, 2024], ['هايلكس', 'Hilux', 2005, 2024], ['لاندكروزر', 'Land Cruiser', 2008, 2024]]],
            ['هيونداي', 'Hyundai', [['سوناتا', 'Sonata', 2005, 2024], ['إلنترا', 'Elantra', 2005, 2024], ['أكسنت', 'Accent', 2005, 2024]]],
            ['كيا', 'Kia', [['سيراتو', 'Cerato', 2008, 2024], ['سبورتاج', 'Sportage', 2008, 2024]]],
            ['نيسان', 'Nissan', [['صني', 'Sunny', 2008, 2024], ['باترول', 'Patrol', 2008, 2024]]],
        ] as [$ar, $en, $models]) {
            $make = Make::create(['name_ar' => $ar, 'name_en' => $en]);
            foreach ($models as [$mar, $men, $f, $t]) {
                $m[$en.' '.$men] = CarModel::create(['make_id' => $make->id, 'name_ar' => $mar, 'name_en' => $men, 'year_from' => $f, 'year_to' => $t]);
            }
        }
        $camry = $m['Toyota Camry'];

        // ---- القطع: [sku, اسم, قسم, درجة, جهة, سعر, تكلفة, مجموعة, كمية, ضخم؟ شحن, [سيارات => [من, إلى]]] ----
        $fitCamryBody = [['Toyota Camry', 2018, 2024]];
        $fitCamry = [['Toyota Camry', 2012, 2024], ['Toyota Corolla', 2014, 2024]];
        $rows = [
            ['53811-06D90', 'رفرف أمامي يمين', 'body', 'oem', 'R', 1240, 930, 'fender-camry', 2, 85, $fitCamryBody],
            ['53812-06D90', 'رفرف أمامي يسار', 'body', 'oem', 'L', 1240, 930, 'fender-camry', 4, 85, $fitCamryBody],
            ['AM-53811-R', 'رفرف أمامي يمين', 'body', 'cert', 'R', 585, 420, 'fender-camry', 6, 85, $fitCamryBody],
            ['AM-53812-L', 'رفرف أمامي يسار', 'body', 'cert', 'L', 585, 420, 'fender-camry', 0, 85, $fitCamryBody],
            ['FG-53811-R', 'رفرف أمامي يمين', 'body', 'first', 'R', 390, 270, 'fender-camry', 9, 85, $fitCamryBody],
            ['FG-53812-L', 'رفرف أمامي يسار', 'body', 'first', 'L', 390, 270, 'fender-camry', 5, 85, $fitCamryBody],
            ['04152-YZZA6', 'فلتر زيت المحرك', 'filters', 'oem', null, 38, 22, 'oil-filter', 40, 0, $fitCamry],
            ['AM-04152-C', 'فلتر زيت المحرك', 'filters', 'cert', null, 22, 12, 'oil-filter', 80, 0, $fitCamry],
            ['FG-04152-C', 'فلتر زيت المحرك', 'filters', 'first', null, 18, 9, 'oil-filter', 120, 0, $fitCamry],
            ['04465-33480', 'فحمات فرامل أمامية (طقم)', 'brakes', 'oem', null, 310, 215, 'pads-front', 12, 0, [['Toyota Camry', 2018, 2024]]],
            ['AM-04465-F', 'فحمات فرامل أمامية (طقم)', 'brakes', 'cert', null, 165, 105, 'pads-front', 25, 0, [['Toyota Camry', 2018, 2024]]],
            ['FG-04465-F', 'فحمات فرامل أمامية (طقم)', 'brakes', 'first', null, 120, 74, 'pads-front', 30, 0, [['Toyota Camry', 2018, 2024]]],
            ['81150-06A60', 'كشاف أمامي يمين', 'lighting', 'oem', 'R', 780, 560, 'headlamp', 3, 40, [['Toyota Camry', 2018, 2024]]],
            ['17801-0V010', 'فلتر هواء المحرك', 'filters', 'oem', null, 195, 128, 'air-filter', 18, 0, [['Toyota Camry', 2018, 2024], ['Toyota Corolla', 2019, 2024]]],
            ['31500-5X00A', 'بطارية 70 أمبير', 'electrical', 'cert', null, 445, 330, 'battery-70', 8, 0, [['Toyota Camry', 2012, 2024], ['Hyundai Sonata', 2012, 2024]]],
            ['58101-C1A00', 'فحمات فرامل أمامية سوناتا', 'brakes', 'oem', null, 285, 190, 'pads-sonata', 10, 0, [['Hyundai Sonata', 2015, 2024]]],
            ['0W20-4L', 'زيت محرك 0W-20 (4 لتر)', 'fluids', 'oem', null, 175, 118, 'oil-0w20', 60, 0, [['Toyota Camry', 2012, 2024], ['Toyota Corolla', 2014, 2024], ['Hyundai Sonata', 2012, 2024]]],
        ];
        $parts = [];
        foreach ($rows as [$sku, $name, $cat, $grade, $side, $price, $cost, $group, $qty, $ship, $fits]) {
            $p = Part::create([
                'sku' => $sku, 'name_ar' => $name, 'category_id' => $cats[$cat]->id, 'grade' => $grade, 'side' => $side,
                'price' => $price, 'cost' => $cost, 'group_key' => $group, 'is_bulky' => $ship > 0, 'shipping_fee' => $ship,
                'brand' => $grade === 'oem' ? 'تويوتا الأصلي' : ($grade === 'cert' ? 'معتمد' : 'درجة أولى'),
            ]);
            $p->inventory()->create(['on_hand' => $qty, 'reserved' => 0]);
            foreach ($fits as [$car, $f, $t]) {
                $p->fitments()->create(['car_model_id' => $m[$car]->id, 'year_from' => $f, 'year_to' => $t]);
            }
            $parts[$sku] = $p;
        }

        // ---- عميل تجريبي مع كراج وعناوين وطلبات ----
        $c = User::create(['name' => 'عبدالله محمد السالم', 'phone' => '966512345678', 'city' => 'الرياض', 'phone_verified_at' => now()]);
        $c->vehicles()->create(['car_model_id' => $camry->id, 'year' => 2022, 'engine' => '2.5L', 'vin' => '4T1B11HK8NU004821', 'is_active' => true]);
        $c->vehicles()->create(['car_model_id' => $m['Hyundai Sonata']->id, 'year' => 2019, 'engine' => '2.4L']);
        $c->vehicles()->create(['car_model_id' => $m['Toyota Hilux']->id, 'year' => 2020, 'engine' => '2.7L', 'vin' => 'MR0HA3CD5L0100937']);
        Address::create(['user_id' => $c->id, 'label' => 'المنزل', 'name' => $c->name, 'phone' => $c->phone, 'city' => 'الرياض', 'line' => 'حي النرجس، شارع الأمير محمد بن سعد، مبنى 4021، شقة 3', 'national_address' => 'RNAA4021', 'is_default' => true]);
        Address::create(['user_id' => $c->id, 'label' => 'الورشة', 'name' => $c->name, 'phone' => $c->phone, 'city' => 'الرياض', 'line' => 'حي الصناعية، شارع 15']);

        $svc = app(OrderService::class);
        $o1 = $svc->place([['part_id' => $parts['53811-06D90']->id, 'qty' => 1], ['part_id' => $parts['04152-YZZA6']->id, 'qty' => 1], ['part_id' => $parts['AM-04465-F']->id, 'qty' => 1]], [
            'channel' => 'app', 'payment' => 'bank', 'user' => $c, 'customer' => ['name' => $c->name, 'phone' => $c->phone],
            'address' => ['name' => $c->name, 'phone' => $c->phone, 'city' => 'الرياض', 'line' => 'حي النرجس، شارع الأمير محمد بن سعد، مبنى 4021، شقة 3', 'national_address' => 'RNAA4021'],
        ]);
        $o1->update(['number' => 'DM-261014-0472', 'placed_at' => '2026-10-14 09:20:00']);

        foreach ([['DM-261009-0318', 'shipped', 'bank', 973.00, '2900447188', '2026-10-09 11:05:00'], ['DM-260921-0187', 'completed', 'cod', 445.00, null, '2026-09-21 16:40:00']] as [$no, $st, $pm, $tot, $wb, $at]) {
            $o = Order::create(['number' => $no, 'user_id' => $c->id, 'customer_name' => $c->name, 'customer_phone' => $c->phone, 'channel' => 'app',
                'status' => $st, 'payment_method' => $pm, 'payment_status' => 'paid', 'subtotal' => $tot - 25, 'vat' => round($tot * 15 / 115, 2), 'shipping' => 25, 'total' => $tot,
                'waybill_no' => $wb, 'placed_at' => $at, 'address' => ['name' => $c->name, 'phone' => $c->phone, 'city' => 'الرياض', 'line' => 'حي النرجس']]);
            $o->items()->create(['part_id' => $parts['31500-5X00A']->id, 'sku' => '31500-5X00A', 'name' => 'بطارية 70 أمبير', 'unit_price' => $tot - 25, 'qty' => 1, 'line_total' => $tot - 25]);
            $o->events()->create(['status' => $st, 'note' => 'بيانات تجريبية']);
        }

        $this->call([BannerSeeder::class, FaqSeeder::class]);
    }
}
