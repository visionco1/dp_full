<?php

namespace App\Http;

use App\Models\Address;
use App\Models\AppNotification;
use App\Models\Banner;
use App\Models\Order;
use App\Models\Part;
use App\Models\User;
use App\Models\Vehicle;

/** تحويل الموديلات إلى JSON ثابت للتطبيق */
class Presenter
{
    public const GRADES = ['oem' => 'أصلي وكالة', 'cert' => 'تجاري بديل معتمد', 'first' => 'درجة أولى'];

    /** تفاصيل الدرجات لـ GET /grades */
    public const GRADE_DETAILS = [
        'oem' => ['label_en' => 'Genuine OEM', 'description_ar' => 'قطع أصلية من الوكيل بنفس مواصفات المصنع', 'warranty_months' => 12, 'color' => '#059669'],
        'cert' => ['label_en' => 'Certified Aftermarket', 'description_ar' => 'بديل تجاري معتمد بجودة مطابقة وسعر أقل', 'warranty_months' => 6, 'color' => '#0284C7'],
        'first' => ['label_en' => 'First Grade', 'description_ar' => 'درجة أولى اقتصادية للاستخدام اليومي', 'warranty_months' => 3, 'color' => '#D97706'],
    ];

    /** أسماء بديلة يقبلها فلتر grade (التطبيق يستخدم aftermarket لدرجة أولى) */
    public const GRADE_ALIASES = ['aftermarket' => 'first', 'genuine' => 'oem', 'certified' => 'cert'];

    public static function branch(\App\Models\Branch $b): array
    {
        return [
            'id' => $b->id, 'name' => $b->name, 'code' => $b->code, 'city' => $b->city, 'address' => $b->address,
            'phone' => $b->phone, 'hours' => $b->hours, 'lat' => $b->lat, 'lng' => $b->lng,
            'pickup_enabled' => $b->pickup_enabled, 'delivery_enabled' => $b->delivery_enabled, 'is_default' => $b->is_default,
        ];
    }

    public static function category(\App\Models\Category $c, bool $withChildren = false): array
    {
        $row = [
            'id' => $c->id, 'parent_id' => $c->parent_id, 'slug' => $c->slug, 'name_ar' => $c->name_ar, 'name_en' => $c->name_en,
            'image' => $c->imageUrl(), 'parts_count' => $c->parts_count ?? null,
        ];
        if ($withChildren) {
            $row['subcategories'] = $c->children->map(fn ($s) => self::category($s))->values();
        }
        return $row;
    }
    public const PAYMENT_LABELS = ['cod' => 'الدفع عند الاستلام', 'bank' => 'تحويل بنكي مباشر', 'cash' => 'نقداً', 'card' => 'بطاقة'];

    /**
     * بانر للتطبيق. action_type يُستنتج من الرابط:
     * /?category=slug → category (action_id = id الفئة) · /parts/15 → product · /?q=.. → search · https://.. → url · بدون رابط → none
     */
    public static function banner(Banner $b, ?\Illuminate\Support\Collection $categories = null): array
    {
        [$type, $id] = ['none', null];
        $url = (string) $b->link_url;
        if ($url !== '') {
            $path = parse_url($url, PHP_URL_PATH) ?: '/';
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            if (preg_match('#^https?://#i', $url)) {
                [$type, $id] = ['url', $url];
            } elseif (preg_match('#^/parts/(\d+)#', $path, $m)) {
                [$type, $id] = ['product', $m[1]];
            } elseif (!empty($q['category'])) {
                $cat = ($categories ?? \App\Models\Category::all(['id', 'slug']))->firstWhere('slug', $q['category']);
                [$type, $id] = ['category', (string) ($cat->id ?? $q['category'])];
            } elseif (!empty($q['q'])) {
                [$type, $id] = ['search', $q['q']];
            } else {
                [$type, $id] = ['catalog', $url];   // صفحة الكتالوج مع فلاتر (grade, sort, in_stock...)
            }
        }
        return [
            'id' => $b->id, 'placement' => $b->placement, 'title' => $b->title, 'subtitle' => $b->subtitle,
            'image_url' => $b->imageUrl(), 'bg_color' => $b->bg_color, 'button_text' => $b->button_text,
            'action_type' => $type, 'action_id' => $id,
            'link_url' => $b->link_url ?: null, 'click_url' => $b->link_url ? route('shop.banner', $b) : null,
        ];
    }

    public static function part(Part $p, ?Vehicle $vehicle = null, bool $detail = false): array
    {
        $avail = $p->available;
        $row = [
            'id' => $p->id,
            'sku' => $p->sku,
            'name' => $p->name_ar,
            'name_en' => $p->name_en,
            'brand' => $p->brand,
            'category' => $p->category?->name_ar,
            'category_id' => $p->category_id,
            'main_category_id' => $p->category?->parent_id ?? $p->category_id,
            'grade' => $p->grade,
            'grade_label' => self::GRADES[$p->grade] ?? $p->grade,
            'side' => $p->side,
            'price' => $p->price,
            'image' => $p->image ? asset('storage/'.$p->image) : null,
            'available' => $avail,
            'in_stock' => $avail > 0,
            'low_stock' => $avail > 0 && $avail <= ($p->inventory->low_stock_at ?? 3),
            'shipping_fee' => $p->is_bulky ? $p->shipping_fee : 0,
            'fits_vehicle' => $p->fitsVehicle($vehicle),
        ];
        if ($detail) {
            $row['description'] = $p->description;
            $row['branches_stock'] = $p->stockByBranch();
            $row['fitments'] = $p->fitments->map(fn ($f) => [
                'car_model_id' => $f->car_model_id,
                'model' => $f->carModel ? $f->carModel->make->name_ar.' '.$f->carModel->name_ar : null,
                'year_from' => $f->year_from, 'year_to' => $f->year_to,
            ])->values();
            $row['variants'] = $p->group_key
                ? Part::with('inventory')->where('group_key', $p->group_key)->where('active', true)->get()
                    ->map(fn ($v) => ['id' => $v->id, 'sku' => $v->sku, 'grade' => $v->grade, 'side' => $v->side, 'price' => $v->price, 'in_stock' => $v->available > 0])->values()
                : [];
        }
        return $row;
    }

    public static function user(User $u): array
    {
        return [
            'id' => $u->id, 'name' => $u->name, 'phone' => $u->phone, 'email' => $u->email,
            'type' => $u->type, 'city' => $u->city,
            'business' => $u->type === 'business' ? [
                'name' => $u->business_name, 'activity' => $u->activity, 'cr_number' => $u->cr_number,
                'vat_number' => $u->vat_number, 'status' => $u->business_status,
            ] : null,
            'locale' => $u->locale, 'theme' => $u->theme, 'notifications' => $u->prefs(),
            'phone_verified' => (bool) $u->phone_verified_at,
        ];
    }

    public static function vehicle(Vehicle $v): array
    {
        $m = $v->carModel;
        return [
            'id' => $v->id, 'car_model_id' => $v->car_model_id,
            'make' => $m?->make?->name_ar, 'model' => $m?->name_ar, 'name' => $m ? $m->make->name_ar.' '.$m->name_ar : null,
            'year' => $v->year, 'engine' => $v->engine, 'vin' => $v->vin, 'is_active' => $v->is_active,
        ];
    }

    public static function address(Address $a): array
    {
        return $a->only(['id', 'label', 'name', 'phone', 'city', 'line', 'national_address', 'is_default']);
    }

    public static function notification(AppNotification $n): array
    {
        return ['id' => $n->id, 'type' => $n->type, 'title' => $n->title, 'body' => $n->body, 'data' => $n->data, 'read' => (bool) $n->read_at, 'created_at' => $n->created_at->toIso8601String()];
    }

    public static function order(Order $o, bool $withItems = true): array
    {
        $o->loadMissing('items', 'events', 'branch');
        $stages = array_values(array_filter([
            'new', $o->payment_method === 'bank' ? 'pending_transfer' : null, 'processing',
            $o->isPickup() ? 'ready' : 'shipped', 'completed',
        ]));
        $labels = config('dp.order_statuses');
        $idx = array_search($o->status, $stages, true);
        $timeline = collect($stages)->map(function ($s, $i) use ($idx, $o, $labels) {
            $ev = $o->events->where('status', $s)->last();
            return [
                'status' => $s, 'label' => $labels[$s],
                'state' => $o->status === 'cancelled' ? ($ev ? 'done' : 'todo') : ($idx !== false && $i < $idx ? 'done' : ($i === $idx ? 'current' : 'todo')),
                'at' => $ev?->created_at?->toIso8601String(),
            ];
        })->values();

        $row = [
            'number' => $o->number,
            'status' => $o->status,
            'status_label' => $o->status_label,
            'channel' => $o->channel,
            'fulfillment' => $o->fulfillment,
            'fulfillment_label' => Order::FULFILLMENT[$o->fulfillment] ?? $o->fulfillment,
            'branch' => $o->branch ? self::branch($o->branch) : null,
            'placed_at' => $o->placed_at?->toIso8601String(),
            'payment' => [
                'method' => $o->payment_method,
                'method_label' => self::PAYMENT_LABELS[$o->payment_method] ?? $o->payment_method,
                'status' => $o->payment_status,
                'reject_reason' => $o->reject_reason,
            ],
            'totals' => ['subtotal' => $o->subtotal, 'shipping' => $o->shipping, 'vat' => $o->vat, 'net' => round($o->total - $o->vat, 2), 'total' => $o->total],
            'address' => $o->address,
            'waybill_no' => $o->waybill_no,
            'receipt_url' => $o->receipt_path ? asset('storage/'.$o->receipt_path) : null,
            'can_upload_receipt' => $o->payment_method === 'bank' && $o->status === 'pending_transfer' && $o->payment_status !== 'paid',
            'can_cancel' => in_array($o->status, Order::CUSTOMER_CANCELLABLE, true),
            'cancel_reason' => $o->cancel_reason,
            'timeline' => $timeline,
            'items_count' => $o->items->sum('qty'),
        ];
        if ($withItems) {
            $row['items'] = $o->items->map(fn ($i) => [
                'part_id' => $i->part_id, 'sku' => $i->sku, 'name' => $i->name, 'unit_price' => $i->unit_price, 'qty' => $i->qty, 'line_total' => $i->line_total,
            ])->values();
        }
        return $row;
    }
}
