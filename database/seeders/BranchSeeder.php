<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Part;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * فروع تجريبية ومخزون موزّع عليها — php artisan db:seed --class=BranchSeeder
 * آمن للتكرار: لا يغيّر كميات فرع موجودة مسبقاً.
 */
class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $main = Branch::where('is_default', true)->first() ?? Branch::first();
        $main?->update(array_filter([
            'address' => $main->address ?: 'طريق الملك فهد، حي العليا',
            'phone' => $main->phone ?: '920000001',
            'lat' => $main->lat ?: 24.7136, 'lng' => $main->lng ?: 46.6753,
        ]));

        $rows = [
            // [code, name, city, address, phone, hours, lat, lng, pickup, delivery, نسبة المخزون]
            ['JED', 'فرع جدة', 'جدة', 'طريق المدينة، حي الروضة', '920000002', 'السبت – الخميس 9 ص – 11 م', 21.5433, 39.1728, true, true, 0.6],
            ['DMM', 'فرع الدمام', 'الدمام', 'طريق الملك سعود، حي الفيصلية', '920000003', 'السبت – الخميس 4 م – 11 م', 26.4207, 50.0888, true, false, 0.35],
        ];
        foreach ($rows as $i => [$code, $name, $city, $address, $phone, $hours, $lat, $lng, $pickup, $delivery, $ratio]) {
            $b = Branch::firstOrCreate(['code' => $code], compact('name', 'city', 'address', 'phone', 'hours', 'lat', 'lng') + [
                'pickup_enabled' => $pickup, 'delivery_enabled' => $delivery, 'sort' => $i + 1, 'active' => true,
            ]);
            if (!$b->wasRecentlyCreated) {
                continue;
            }
            // توزيع ثابت (ليس عشوائياً) حتى تبقى البيانات متكررة: بعض القطع غير موجودة في الفرع
            foreach (Part::with(['inventories' => fn ($q) => $q->where('branch_id', $main?->id)])->get() as $p) {
                $base = (int) ($p->inventories->first()->on_hand ?? 0);
                $seed = crc32($code.$p->sku) % 100;
                $qty = $seed < 25 ? 0 : (int) round($base * $ratio * (0.5 + $seed / 100));
                Inventory::firstOrCreate(['part_id' => $p->id, 'branch_id' => $b->id], ['on_hand' => $qty, 'reserved' => 0, 'location' => $code.'-'.(1 + $seed % 6).'-'.(10 + $seed % 30)]);
            }
        }

        // موظف المعرض مربوط بالفرع الرئيسي
        User::where('email', 'omar.pos@store.example')->whereNull('branch_id')->update(['branch_id' => $main?->id]);
    }
}
