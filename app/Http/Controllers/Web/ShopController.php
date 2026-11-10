<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Branch;
use App\Models\Cart;
use App\Models\CarModel;
use App\Models\Category;
use App\Models\Make;
use App\Models\Order;
use App\Models\Part;
use App\Services\CartService;
use App\Services\OrderService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** واجهة العملاء (الويب): كتالوج، سلة، شراء كضيف، تتبّع الطلب */
class ShopController extends Controller
{
    public function __construct(private CartService $carts, private OrderService $orders) {}

    private function cart(Request $r, bool $create = true): ?Cart
    {
        $token = $r->session()->get('cart_token');
        if ($token && ($c = Cart::where('token', $token)->whereNull('user_id')->first())) {
            return $c;
        }
        if (!$create) {
            return null;
        }
        $c = Cart::create(['token' => Str::random(40)]);
        $r->session()->put('cart_token', $c->token);
        return $c;
    }

    public function index(Request $r)
    {
        $r->validate(['grade' => 'nullable|in:oem,cert,first', 'sort' => 'nullable|in:price_asc,price_desc,newest']);
        $q = Part::with(['inventory', 'category'])->where('active', true)->search($r->input('q'))
            ->inCategory($r->input('category'));   // القسم الرئيسي يشمل الفرعية
        if ($r->filled('grade')) {
            $q->where('grade', $r->input('grade'));
        }
        if ($r->boolean('in_stock')) {
            $q->whereHas('inventory', fn ($w) => $w->whereRaw('on_hand - reserved > 0'));
        }
        if ($r->filled('model')) {
            $m = CarModel::find($r->integer('model'));
            $m && $q->fitting($m->id, $r->integer('year') ?: null);
        }
        match ($r->input('sort')) {
            'price_asc' => $q->orderBy('price'),
            'price_desc' => $q->orderByDesc('price'),
            'newest' => $q->orderByDesc('id'),
            default => $q->orderBy('name_ar')->orderBy('grade'),
        };
        return view('shop.index', [
            'parts' => $q->paginate(12)->withQueryString(),
            'categories' => Category::roots()->with('children')->get(),
            'current' => $r->filled('category') ? Category::with('children', 'parent.children')->where('slug', $r->input('category'))->first() : null,
            'makes' => Make::with('models')->orderBy('name_ar')->get(),
            'cartCount' => $this->cart($r, false)?->items()->sum('qty') ?? 0,
        ]);
    }

    public function part(Request $r, Part $part)
    {
        abort_unless($part->active, 404);
        $part->load('inventory', 'category', 'fitments.carModel.make');
        $variants = $part->group_key ? Part::with('inventory')->where('group_key', $part->group_key)->where('active', true)->get() : collect();
        return view('shop.part', ['part' => $part, 'variants' => $variants, 'cartCount' => $this->cart($r, false)?->items()->sum('qty') ?? 0]);
    }

    public function cartShow(Request $r)
    {
        $c = $this->cart($r, false);
        $s = $c ? $this->carts->summary($c) : ['items' => [], 'totals' => $this->carts->totals([]), 'has_issues' => false];
        return view('shop.cart', ['cart' => $s, 'cartCount' => $s['totals']['items_count']]);
    }

    public function cartAdd(Request $r)
    {
        $d = $r->validate(['part_id' => 'required|exists:parts,id', 'qty' => 'nullable|integer|min:1|max:99']);
        $part = Part::with('inventory')->where('active', true)->findOrFail($d['part_id']);
        $c = $this->cart($r);
        $row = $c->items()->where('part_id', $part->id)->first();
        $qty = ($row?->qty ?? 0) + ($d['qty'] ?? 1);
        if ($part->available < $qty) {
            return back()->withErrors(['qty' => $part->available > 0 ? "المتاح من هذه القطعة {$part->available} فقط" : 'القطعة غير متوفرة حالياً']);
        }
        $row ? $row->update(['qty' => $qty]) : $c->items()->create(['part_id' => $part->id, 'qty' => $qty]);
        return $r->boolean('buy_now') ? redirect()->route('shop.cart') : back()->with('ok', 'تمت الإضافة إلى السلة');
    }

    public function cartUpdate(Request $r, int $item)
    {
        $d = $r->validate(['qty' => 'required|integer|min:0|max:99']);
        $c = $this->cart($r, false);
        abort_unless($c, 404);
        $row = $c->items()->with('part.inventory')->findOrFail($item);
        if ($d['qty'] === 0) {
            $row->delete();
        } elseif ($row->part->available < $d['qty']) {
            return back()->withErrors(['qty' => "المتاح {$row->part->available} فقط"]);
        } else {
            $row->update(['qty' => $d['qty']]);
        }
        return back();
    }

    /** اختيار الفرع من الهيدر — التوفر في الموقع يُعرض لهذا الفرع */
    public function setBranch(Request $r)
    {
        $id = $r->validate(['branch_id' => 'required|integer'])['branch_id'];
        abort_unless(Branch::whereKey($id)->where('active', true)->exists(), 404);
        $r->session()->put('shop_branch', (int) $id);
        return back();
    }

    public function checkout(Request $r, \App\Services\InventoryService $stock)
    {
        $c = $this->cart($r, false);
        if (!$c || !$c->items()->exists()) {
            return redirect()->route('shop.cart');
        }
        $s = $this->carts->summary($c);
        // لكل فرع: هل يغطي السلة كاملة؟
        $items = collect($s['items']);
        $branches = Branch::active()->get()->map(function ($b) use ($items, $stock) {
            $avail = $stock->availableAt($b->id, $items->pluck('part_id')->all());
            $b->missing = $items->filter(fn ($i) => ($avail[$i['part_id']] ?? 0) < $i['qty'])->pluck('name')->values();
            return $b;
        });
        return view('shop.checkout', [
            'cart' => $s, 'cartCount' => $s['totals']['items_count'], 'branches' => $branches,
            'pickupTotals' => $this->carts->totals($c->items->map(fn ($i) => [$i->part, $i->qty])->filter(fn ($l) => $l[0]?->active)->all(), 'pickup'),
            'selectedBranch' => old('branch_id', $r->session()->get('shop_branch') ?? $branches->firstWhere(fn ($b) => $b->missing->isEmpty())?->id),
        ]);
    }

    public function place(Request $r)
    {
        $delivery = $r->input('fulfillment', 'delivery') !== 'pickup';
        $d = $r->validate([
            'fulfillment' => 'nullable|in:delivery,pickup', 'branch_id' => 'nullable|integer|exists:branches,id',
            'name' => 'required|string|max:80', 'phone' => 'required|string',
            'city' => [$delivery ? 'required' : 'nullable', 'string', 'max:60'], 'line' => [$delivery ? 'required' : 'nullable', 'string', 'max:200'],
            'national_address' => ['nullable', 'regex:/^[A-Za-z]{4}\d{4}$/'], 'payment_method' => 'required|in:cod,bank',
        ], ['national_address.regex' => 'العنوان الوطني 4 حروف و4 أرقام مثل RNAA4021', 'branch_id.required' => 'اختر الفرع',
            'city.required' => 'أدخل المدينة للتوصيل', 'line.required' => 'أدخل العنوان للتوصيل']);
        $phone = Phone::normalize($d['phone']) ?? throw ValidationException::withMessages(['phone' => 'برجاء إدخال رقم جوال صحيح (10 أرقام)']);
        $c = $this->cart($r, false);
        if (!$c || !$c->items()->exists()) {
            return redirect()->route('shop.cart');
        }
        $addr = $delivery ? ['name' => $d['name'], 'phone' => $phone, 'city' => $d['city'], 'line' => $d['line'], 'national_address' => $d['national_address'] ?? null] : null;
        $order = $this->orders->place($c->items->map(fn ($i) => ['part_id' => $i->part_id, 'qty' => $i->qty])->all(), [
            'channel' => 'web', 'payment' => $d['payment_method'], 'customer' => ['name' => $d['name'], 'phone' => $phone], 'address' => $addr,
            'fulfillment' => $delivery ? 'delivery' : 'pickup', 'branch_id' => $d['branch_id'] ?? null,
        ]);
        $r->session()->put('shop_branch', $order->branch_id);
        $c->items()->delete();
        $r->session()->push('my_orders', $order->number);
        return redirect()->route('shop.order', $order->number);
    }

    private function owned(Request $r, string $number): Order
    {
        $o = Order::with('items', 'events', 'branch')->where('number', strtoupper($number))->firstOrFail();
        abort_unless(in_array($o->number, (array) $r->session()->get('my_orders', []), true), 404);
        return $o;
    }

    public function order(Request $r, string $number)
    {
        return view('shop.order', ['order' => $this->owned($r, $number), 'cartCount' => $this->cart($r, false)?->items()->sum('qty') ?? 0]);
    }

    public function receipt(Request $r, string $number)
    {
        $r->validate(['receipt' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120'], ['receipt.mimes' => 'الإيصال يجب أن يكون صورة أو PDF', 'receipt.max' => 'حجم الإيصال حتى 5 ميجابايت']);
        $o = $this->owned($r, $number);
        $this->orders->attachReceipt($o, $r->file('receipt')->store('receipts', 'public'));
        return back()->with('ok', 'تم رفع الإيصال وسنراجعه قريباً');
    }

    public function cancel(Request $r, string $number)
    {
        $o = $this->owned($r, $number);
        abort_unless(in_array($o->status, Order::CUSTOMER_CANCELLABLE, true), 422, 'لا يمكن إلغاء الطلب بعد بدء التجهيز — تواصل مع الدعم');
        $this->orders->cancel($o, null, 'ألغاه العميل');
        return back()->with('ok', 'تم إلغاء الطلب');
    }

    /** نقرة على بانر: تُحسب ثم تحويل للرابط */
    public function bannerClick(Banner $banner)
    {
        abort_unless($banner->isLive() && $banner->link_url, 404);
        $banner->increment('clicks');
        $url = $banner->link_url;
        return str_starts_with($url, '/') ? redirect($url) : redirect()->away($url);
    }

    public function track(Request $r)
    {
        return view('shop.track', ['cartCount' => $this->cart($r, false)?->items()->sum('qty') ?? 0]);
    }

    public function trackFind(Request $r)
    {
        $d = $r->validate(['number' => 'required|string', 'phone' => 'required|string']);
        $no = strtoupper(trim(Phone::latinDigits($d['number'])));
        $phone = Phone::normalize($d['phone']);
        $o = $phone ? Order::where('number', $no)->where('customer_phone', $phone)->first() : null;
        if (!$o) {
            return back()->withInput()->withErrors(['number' => 'لم نجد طلباً بهذه البيانات — تأكد من الرقم والجوال']);
        }
        $r->session()->push('my_orders', $o->number);
        return redirect()->route('shop.order', $o->number);
    }
}
