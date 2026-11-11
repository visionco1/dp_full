<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Presenter;
use App\Models\Address;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Services\CartService;
use App\Services\OrderService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders, private CartService $carts) {}

    /** الشراء كضيف أو كمستخدم مسجّل */
    public function checkout(Request $r)
    {
        $user = $r->user('sanctum');
        $pickup = $r->input('fulfillment') === 'pickup';
        $d = $r->validate([
            'fulfillment' => 'nullable|in:delivery,pickup',
            'branch_id' => 'nullable|integer|exists:branches,id',   // بدونه: X-Branch-Id أو الفرع الرئيسي
            'payment_method' => 'required|in:cod,bank',
            'save_address' => 'nullable|boolean',
        ] + ($pickup ? [
            // الاستلام من الفرع: لا عنوان — اسم وجوال المستلم (أو بيانات الحساب)
            'contact' => 'nullable|array',
            'contact.name' => [$user ? 'nullable' : 'required', 'string', 'max:80'],
            'contact.phone' => [$user ? 'nullable' : 'required', 'string'],
        ] : [
            'address_id' => 'nullable|integer',
            'address' => 'nullable|array',
            'address.name' => 'required_without:address_id|string|max:80',
            'address.phone' => 'required_without:address_id|string',
            'address.city' => 'required_without:address_id|string|max:60',
            'address.line' => 'required_without:address_id|string|max:200',
            'address.national_address' => ['nullable', 'regex:/^[A-Za-z]{4}\d{4}$/'],
        ]), ['address.national_address.regex' => 'العنوان الوطني 4 حروف و4 أرقام مثل RNAA4021', 'contact.name.required' => 'أدخل اسم المستلم', 'contact.phone.required' => 'أدخل جوال المستلم']);

        if ($pickup) {
            $phone = $d['contact']['phone'] ?? $user?->phone;
            $addr = null;
            $customer = [
                'name' => $d['contact']['name'] ?? $user?->name,
                'phone' => Phone::normalize((string) $phone) ?? throw ValidationException::withMessages(['contact.phone' => 'برجاء إدخال رقم جوال صحيح (10 أرقام)']),
            ];
        } elseif (!empty($d['address_id'])) {
            abort_unless($user, 422, 'سجّل الدخول لاستخدام عنوان محفوظ');
            $a = Address::where('user_id', $user->id)->findOrFail($d['address_id']);
            $addr = $a->only(['name', 'phone', 'city', 'line', 'national_address']);
        } else {
            $addr = $d['address'];
            $addr['phone'] = Phone::normalize($addr['phone']) ?? throw ValidationException::withMessages(['address.phone' => 'برجاء إدخال رقم جوال صحيح (10 أرقام)']);
        }
        $customer ??= ['name' => $addr['name'], 'phone' => $addr['phone']];

        $cart = $this->carts->resolve($r, false);
        if (!$cart || !$cart->items()->exists()) {
            throw ValidationException::withMessages(['items' => 'السلة فارغة']);
        }
        $lines = $cart->items->map(fn ($i) => ['part_id' => $i->part_id, 'qty' => $i->qty])->all();

        $order = $this->orders->place($lines, [
            'channel' => $r->header('X-Channel') === 'web' ? 'web' : 'app',
            'payment' => $d['payment_method'],
            'customer' => $customer,
            'address' => $addr,
            'user' => $user,
            'fulfillment' => $pickup ? 'pickup' : 'delivery',
            'branch_id' => $d['branch_id'] ?? null,
        ]);
        $cart->items()->delete();
        if ($addr && $user && $r->boolean('save_address') && empty($d['address_id'])) {
            $user->addresses()->create($addr + ['label' => 'عنوان جديد', 'is_default' => !$user->addresses()->exists()]);
        }

        return response()->json([
            'order' => Presenter::order($order),
            'bank_transfer' => $order->payment_method === 'bank' ? self::bankTransfer($order) : null,
        ], 201);
    }

    public function index(Request $r)
    {
        $q = $r->user()->orders()->with('items', 'events')->orderByDesc('placed_at');
        if ($r->input('scope') === 'active') {
            $q->whereIn('status', Order::OPEN);
        } elseif ($r->input('scope') === 'past') {
            $q->whereIn('status', ['completed', 'cancelled']);
        }
        $page = $q->paginate(15);
        return ['data' => $page->getCollection()->map(fn ($o) => Presenter::order($o, false))->values(), 'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]];
    }

    /** طلب يخص المستخدم المسجّل، أو ضيف بإرسال الرقم + الجوال (phone) */
    public function findOwned(Request $r, string $number): Order
    {
        $o = Order::where('number', strtoupper($number))->firstOrFail();
        $user = $r->user('sanctum');
        if ($user && $o->user_id === $user->id) {
            return $o;
        }
        $phone = Phone::normalize((string) $r->input('phone', $r->query('phone', '')));
        abort_unless($phone && $phone === $o->customer_phone, 404);
        return $o;
    }

    public function show(Request $r, string $number)
    {
        return ['order' => Presenter::order($this->findOwned($r, $number))];
    }

    /** بيانات التحويل البنكي للطلب من الحسابات البنكية المسجّلة في لوحة الإدارة */
    public static function bankTransfer(Order $o): array
    {
        $acc = ContentController::accounts()->first();
        return [
            'bank' => $acc['bank_name'] ?? null, 'account_name' => $acc['beneficiary_name'] ?? config('dp.seller_name'),
            'iban' => $acc['iban'] ?? null, 'account_number' => $acc['account_number'] ?? null,
            'amount' => $o->total, 'reference' => $o->number, 'accounts' => ContentController::accounts(),
        ];
    }

    /**
     * طلبات الضيف برقم الجوال — بخطوتين لحماية بيانات العملاء:
     * 1) { phone } → نرسل رمز تحقق للجوال (نفس الاستجابة سواء له طلبات أم لا)
     * 2) { phone, code } → قائمة طلبات هذا الرقم
     */
    public function guestLookup(Request $r, \App\Services\OtpService $otp)
    {
        $r->validate(['phone' => 'required|string', 'code' => 'nullable|digits:4']);
        $phone = Phone::normalize((string) $r->input('phone')) ?? throw ValidationException::withMessages(['phone' => 'برجاء إدخال رقم جوال صحيح (10 أرقام)']);

        if (!$r->filled('code')) {
            $code = $otp->request($phone, ['purpose' => 'guest_lookup']);
            return response()->json(['message' => 'أرسلنا رمز التحقق إلى جوالك', 'step' => 'verify', 'resend_after' => config('dp.otp.resend_seconds')]
                + ($code ? ['debug_code' => $code] : []), 202);
        }
        $otp->verify($phone, Phone::latinDigits((string) $r->input('code')));
        $orders = Order::with('items', 'events', 'branch')->where('customer_phone', $phone)->orderByDesc('placed_at')->limit(50)->get();
        return ['orders' => $orders->map(fn ($o) => Presenter::order($o, false))->values(), 'count' => $orders->count()];
    }

    /** طلبات الإرجاع/الاستبدال لطلب (المالك أو الضيف مع phone) */
    public function returns(Request $r, string $number)
    {
        $o = $this->findOwned($r, $number);
        return [
            'returns' => ReturnRequest::with('item')->where('order_id', $o->id)->latest('id')->get()->map->toApi()->values(),
            'eligible' => $this->returnEligibility($o),
        ];
    }

    /** POST multipart: order_item_id, action(return|exchange), reason, qty?, image? */
    public function requestReturn(Request $r, string $number)
    {
        $o = $this->findOwned($r, $number);
        $d = $r->validate([
            'order_item_id' => 'required|integer',
            'action' => 'required|in:'.implode(',', array_keys(ReturnRequest::ACTIONS)),
            'reason' => 'required|string|max:300',
            'qty' => 'nullable|integer|min:1|max:99',
            'image' => 'nullable|image|max:5120',
        ], ['image.image' => 'المرفق يجب أن يكون صورة', 'image.max' => 'حجم الصورة حتى 5 ميجابايت']);

        $eligible = $this->returnEligibility($o);
        if (!$eligible['allowed']) {
            throw ValidationException::withMessages(['order' => $eligible['reason']]);
        }
        $item = $o->items()->whereKey($d['order_item_id'])->first() ?? throw ValidationException::withMessages(['order_item_id' => 'القطعة ليست ضمن هذا الطلب']);
        $qty = (int) ($d['qty'] ?? $item->qty);
        $already = ReturnRequest::where('order_item_id', $item->id)->whereIn('status', ['pending', 'approved', 'completed'])->sum('qty');
        if ($qty + $already > $item->qty) {
            throw ValidationException::withMessages(['qty' => $already ? 'يوجد طلب إرجاع سابق لهذه القطعة' : "الكمية المطلوبة أكبر من المشتراة ({$item->qty})"]);
        }

        $ret = ReturnRequest::create([
            'order_id' => $o->id, 'order_item_id' => $item->id, 'user_id' => $r->user('sanctum')?->id,
            'action' => $d['action'], 'qty' => $qty, 'reason' => $d['reason'],
            'image' => $r->hasFile('image') ? $r->file('image')->store('returns', 'public') : null,
        ]);
        $this->orders->event($o, $o->status, 'طلب '.ReturnRequest::ACTIONS[$d['action']].': '.$item->name.' × '.$qty.' — '.$d['reason']);
        return response()->json(['message' => 'استلمنا طلبك وسنراجعه خلال يومي عمل', 'return' => $ret->refresh()->load('item')->toApi()], 201);
    }

    /** هل الطلب قابل للإرجاع؟ مكتمل وضمن المهلة */
    private function returnEligibility(Order $o): array
    {
        $days = (int) config('dp.return_days');
        if ($o->status !== 'completed') {
            return ['allowed' => false, 'reason' => 'الإرجاع والاستبدال متاحان للطلبات المكتملة فقط', 'until' => null];
        }
        $doneAt = $o->events()->where('status', 'completed')->latest('id')->value('created_at') ?? $o->updated_at;
        $until = \Illuminate\Support\Carbon::parse($doneAt)->addDays($days);
        return $until->isPast()
            ? ['allowed' => false, 'reason' => "انتهت مهلة الإرجاع ({$days} يوماً من الاستلام)", 'until' => $until->toIso8601String()]
            : ['allowed' => true, 'reason' => null, 'until' => $until->toIso8601String()];
    }

    /** تتبع طلبك: رقم الطلب + رقم الجوال بدون تسجيل */
    public function track(Request $r)
    {
        $r->validate(['number' => 'required|string', 'phone' => 'required|string']);
        $phone = Phone::normalize((string) $r->input('phone'));
        $no = strtoupper(trim(Phone::latinDigits((string) $r->input('number'))));
        if (!preg_match('/^DM-\d{6}-\d{4}$/', $no)) {
            throw ValidationException::withMessages(['number' => 'برجاء إدخال رقم طلب صحيح مثل DM-261009-0318']);
        }
        if (!$phone) {
            throw ValidationException::withMessages(['phone' => 'برجاء إدخال رقم جوال صحيح (10 أرقام)']);
        }
        $o = Order::where('number', $no)->where('customer_phone', $phone)->first();
        if (!$o) {
            return response()->json(['message' => 'لم نجد طلباً بهذه البيانات — تأكد من الرقم والجوال'], 404);
        }
        return ['order' => Presenter::order($o)];
    }

    public function receipt(Request $r, string $number)
    {
        $r->validate(['receipt' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120'], [
            'receipt.mimes' => 'الإيصال يجب أن يكون صورة أو PDF', 'receipt.max' => 'حجم الإيصال حتى 5 ميجابايت']);
        $o = $this->findOwned($r, $number);
        $path = $r->file('receipt')->store('receipts', 'public');
        return ['order' => Presenter::order($this->orders->attachReceipt($o, $path))];
    }

    public function cancel(Request $r, string $number)
    {
        $o = $this->findOwned($r, $number);
        abort_unless(in_array($o->status, Order::CUSTOMER_CANCELLABLE, true), 422, 'لا يمكن إلغاء الطلب بعد بدء التجهيز — تواصل مع الدعم');
        return ['order' => Presenter::order($this->orders->cancel($o, $r->user('sanctum'), 'ألغاه العميل'))];
    }

    /**
     * الفاتورة الضريبية.
     * JSON عند إرسال Accept: application/json أو ?format=json (لشاشة الفاتورة النيتف)،
     * و HTML جاهز للطباعة/WebView غير ذلك أو مع ?format=html.
     */
    public function invoice(Request $r, string $number)
    {
        $o = $this->findOwned($r, $number)->load('items', 'user');
        $format = $r->query('format');
        if ($format === 'json' || ($format !== 'html' && $r->wantsJson())) {
            return ['invoice' => self::invoiceData($o, $r->input('phone', $r->query('phone')))];
        }
        return response()->view('admin.print.invoice', ['orders' => collect([$o]), 'auto' => false]);
    }

    public static function invoiceData(Order $o, ?string $phone = null): array
    {
        $buyer = $o->user?->type === 'business' ? $o->user : null;
        $htmlQuery = array_filter(['format' => 'html', 'phone' => $phone]);
        return [
            'invoice_number' => 'INV-'.preg_replace('/^DM-/i', '', $o->number),
            'invoice_type' => $buyer?->vat_number ? 'tax_invoice' : 'simplified_tax_invoice',
            'issue_date' => $o->placed_at->format('Y-m-d'),
            'issue_datetime' => $o->placed_at->toIso8601String(),
            'order_number' => $o->number,
            'order_status' => $o->status,
            'payment_method' => Presenter::PAYMENT_LABELS[$o->payment_method] ?? $o->payment_method,
            'payment_method_code' => $o->payment_method,
            'payment_status' => $o->payment_status,
            'seller_name' => config('dp.seller_name'),
            'seller_tax_number' => config('dp.seller_vat_number'),
            'seller_cr_number' => config('dp.seller_cr_number'),
            'customer_name' => $buyer?->business_name ?: $o->customer_name,
            'customer_phone' => $o->customer_phone,
            'customer_tax_number' => $buyer?->vat_number,
            'customer_cr_number' => $buyer?->cr_number,
            'items' => $o->items->map(fn ($i) => [
                'name' => $i->name,
                'part_number' => $i->sku,
                'quantity' => (int) $i->qty,
                'unit_price' => (float) $i->unit_price,
                'total_price' => (float) $i->line_total,
            ])->values(),
            'shipping' => $o->shipping,
            'vat_rate' => config('dp.vat_rate'),
            'subtotal_before_vat' => round($o->total - $o->vat, 2),
            'vat_amount' => $o->vat,
            'grand_total' => $o->total,
            'currency' => config('dp.currency'),
            'prices_include_vat' => true,
            'qr_code_data' => \App\Support\Zatca::forOrder($o),
            'invoice_html_url' => url('/api/v1/orders/'.$o->number.'/invoice').'?'.http_build_query($htmlQuery),
        ];
    }
}
