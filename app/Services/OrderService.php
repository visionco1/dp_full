<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Part;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private InventoryService $stock,
        private CartService $carts,
        private NotificationService $notify,
    ) {}

    public function nextNumber(string $prefix = 'DM'): string
    {
        $day = now()->format('ymd');
        $n = Order::where('number', 'like', "{$prefix}-{$day}-%")->count() + 1;
        do {
            $no = sprintf('%s-%s-%04d', $prefix, $day, $n++);
        } while (Order::where('number', $no)->exists());
        return $no;
    }

    /**
     * @param array<int,array{part_id:int,qty:int}> $lines
     * $opts: channel(app|web|pos), payment(cod|bank|cash|card), customer{name,phone}, address?, user?, staff?,
     *        branch_id? (الافتراضي: الفرع الحالي), fulfillment? (delivery|pickup — نقطة البيع دائماً pickup)
     */
    public function place(array $lines, array $opts): Order
    {
        $channel = $opts['channel'] ?? 'app';
        $pay = $opts['payment'];
        $isPos = $channel === 'pos';
        $user = $opts['user'] ?? null;
        $staff = $opts['staff'] ?? null;
        $fulfillment = $isPos ? 'pickup' : ($opts['fulfillment'] ?? 'delivery');
        $branch = Branch::find($opts['branch_id'] ?? BranchContext::current());
        if (!$branch || !$branch->active) {
            throw ValidationException::withMessages(['branch_id' => 'الفرع غير متاح']);
        }
        if (!$isPos && !$branch->supports($fulfillment)) {
            throw ValidationException::withMessages(['fulfillment' => $fulfillment === 'pickup' ? "فرع {$branch->name} لا يدعم الاستلام من الفرع" : "فرع {$branch->name} لا يدعم التوصيل"]);
        }
        if (!$isPos && $fulfillment === 'delivery' && empty($opts['address'])) {
            throw ValidationException::withMessages(['address' => 'أدخل عنوان التوصيل']);
        }

        return DB::transaction(function () use ($lines, $opts, $channel, $pay, $isPos, $user, $staff, $fulfillment, $branch) {
            $parts = Part::with('inventory')->whereIn('id', collect($lines)->pluck('part_id'))->where('active', true)->get()->keyBy('id');
            $pairs = [];
            foreach ($lines as $l) {
                $part = $parts[$l['part_id']] ?? throw ValidationException::withMessages(['items' => 'إحدى القطع غير متاحة']);
                $pairs[] = [$part, (int) $l['qty']];
            }
            if (!$pairs) {
                throw ValidationException::withMessages(['items' => 'السلة فارغة']);
            }
            $t = $this->carts->totals($pairs);
            if ($fulfillment === 'pickup') {   // البيع المباشر والاستلام من الفرع بدون شحن
                $t['shipping'] = 0.0;
                $t['total'] = $t['subtotal'];
                $t['vat'] = Money::vatIn($t['total']);
            }

            $order = Order::create([
                'number' => $this->nextNumber($isPos ? 'POS' : 'DM'),
                'user_id' => $user?->id,
                'branch_id' => $branch->id,
                'fulfillment' => $fulfillment,
                'customer_name' => $opts['customer']['name'] ?? 'عميل المعرض',
                'customer_phone' => $opts['customer']['phone'] ?? '',
                'channel' => $channel,
                'status' => $isPos ? 'completed' : ($pay === 'bank' ? 'pending_transfer' : 'new'),
                'payment_method' => $pay,
                'payment_status' => $isPos ? 'paid' : ($pay === 'bank' ? 'pending' : 'unpaid'),
                'subtotal' => $t['subtotal'], 'vat' => $t['vat'], 'shipping' => $t['shipping'], 'total' => $t['total'],
                'address' => $fulfillment === 'delivery' ? ($opts['address'] ?? null) : null,
                'created_by' => $staff?->id,
                'placed_at' => now(),
            ]);

            foreach ($pairs as [$part, $qty]) {
                $order->items()->create([
                    'part_id' => $part->id, 'sku' => $part->sku, 'name' => $part->name_ar,
                    'unit_price' => $part->price, 'unit_cost' => $part->cost,
                    'shipping_fee' => $part->is_bulky ? $part->shipping_fee : 0,
                    'qty' => $qty, 'line_total' => Money::round($part->price * $qty),
                ]);
                $isPos ? $this->stock->sellNow($part, $qty, $order->number, $staff?->id, $branch->id) : $this->stock->reserve($part, $qty, $branch->id);
            }
            $this->event($order, $order->status, $isPos ? 'بيع من المعرض' : 'تم استلام الطلب', $staff);

            if ($user) {
                $this->notify->push($user, 'order', 'تم استلام طلبك '.$order->number,
                    $pay === 'bank' ? 'حوّل المبلغ وأرفق الإيصال ليراجعه فريقنا.'
                        : ($fulfillment === 'pickup' ? "سنجهّز طلبك في {$branch->name} ونبلغك عندما يكون جاهزاً للاستلام." : 'سنجهّز طلبك ونشحنه قريباً.'),
                    ['order' => $order->number]);
            }
            return $order->load('items');
        });
    }

    public function event(Order $o, string $status, ?string $note = null, ?User $by = null): void
    {
        $o->events()->create(['status' => $status, 'note' => $note, 'user_id' => $by?->id]);
    }

    private function tell(Order $o, string $title, string $body): void
    {
        if ($o->user) {
            $this->notify->push($o->user, 'order', $title, $body, ['order' => $o->number]);
        }
        $this->notify->sms($o->customer_phone, "{$title} — {$o->number}");
    }

    public function attachReceipt(Order $o, string $path): Order
    {
        if ($o->payment_method !== 'bank' || !in_array($o->status, ['pending_transfer'], true)) {
            throw ValidationException::withMessages(['receipt' => 'هذا الطلب لا يقبل إيصال تحويل']);
        }
        $o->update(['receipt_path' => $path, 'payment_status' => 'pending', 'reject_reason' => null]);
        $this->event($o, 'pending_transfer', 'تم رفع إيصال التحويل');
        return $o;
    }

    public function approvePayment(Order $o, User $by): Order
    {
        if ($o->status !== 'pending_transfer') {
            throw ValidationException::withMessages(['order' => 'الطلب ليس بانتظار التحقق']);
        }
        if (!$o->receipt_path) {
            throw ValidationException::withMessages(['order' => 'لا يوجد إيصال مرفق']);
        }
        $o->update(['status' => 'processing', 'payment_status' => 'paid', 'reject_reason' => null]);
        $this->event($o, 'processing', 'تم اعتماد التحويل البنكي', $by);
        $this->tell($o, 'تم اعتماد الدفع', 'نجهّز طلبك الآن.');
        return $o;
    }

    public function rejectPayment(Order $o, User $by, string $reason, bool $cancel = false): Order
    {
        if ($o->status !== 'pending_transfer') {
            throw ValidationException::withMessages(['order' => 'الطلب ليس بانتظار التحقق']);
        }
        $o->update(['payment_status' => 'rejected', 'reject_reason' => $reason, 'receipt_path' => $cancel ? $o->receipt_path : null]);
        $this->event($o, 'pending_transfer', 'رُفض التحويل: '.$reason, $by);
        $this->tell($o, 'تعذّر التحقق من التحويل', $reason.' — ارفع إيصالاً جديداً.');
        return $cancel ? $this->cancel($o, $by, $reason) : $o;
    }

    public function startProcessing(Order $o, User $by): Order
    {
        if ($o->status !== 'new') {
            throw ValidationException::withMessages(['order' => 'الطلب ليس جديداً']);
        }
        $o->update(['status' => 'processing']);
        $this->event($o, 'processing', 'بدأ التجهيز', $by);
        $this->tell($o, 'طلبك قيد التجهيز', 'نجهّز قطعك الآن.');
        return $o;
    }

    public function ship(Order $o, User $by, ?string $waybill = null): Order
    {
        if ($o->isPickup()) {
            throw ValidationException::withMessages(['order' => 'طلب استلام من الفرع — استخدم "جاهز للاستلام"']);
        }
        if ($o->status !== 'processing') {
            throw ValidationException::withMessages(['order' => 'يجب أن يكون الطلب قيد التجهيز قبل الشحن']);
        }
        DB::transaction(function () use ($o, $by, $waybill) {
            $this->commitItems($o, $by);
            $o->update(['status' => 'shipped', 'waybill_no' => $waybill ?: $o->waybill_no ?: (string) random_int(2900000000, 2999999999)]);
            $this->event($o, 'shipped', 'تم تسليم الشحنة لشركة الشحن', $by);
        });
        $this->tell($o, 'تم شحن طلبك', 'رقم بوليصة الشحن: '.$o->waybill_no);
        return $o;
    }

    /** طلب الاستلام: جاهز في الفرع (القطع تبقى محجوزة حتى يستلمها العميل) */
    public function markReady(Order $o, User $by): Order
    {
        if (!$o->isPickup() || $o->status !== 'processing') {
            throw ValidationException::withMessages(['order' => 'الطلب ليس طلب استلام قيد التجهيز']);
        }
        $o->update(['status' => 'ready']);
        $branch = $o->branch;
        $this->event($o, 'ready', 'جاهز للاستلام من '.($branch->name ?? 'الفرع'), $by);
        $this->tell($o, 'طلبك جاهز للاستلام', 'من '.($branch ? trim($branch->name.' — '.$branch->address, ' —') : 'الفرع').'. أحضر رقم الطلب عند الاستلام.');
        return $o;
    }

    public function complete(Order $o, User $by): Order
    {
        $expected = $o->isPickup() ? 'ready' : 'shipped';
        if ($o->status !== $expected) {
            throw ValidationException::withMessages(['order' => $o->isPickup() ? 'الطلب ليس جاهزاً للاستلام بعد' : 'الطلب لم يُشحن بعد']);
        }
        DB::transaction(function () use ($o, $by) {
            if ($o->isPickup()) {   // القطع تخرج من مخزون الفرع عند تسليمها للعميل
                $this->commitItems($o, $by);
            }
            $o->update(['status' => 'completed', 'payment_status' => 'paid']);
        });
        $this->event($o, 'completed', $o->isPickup() ? 'استلمه العميل من الفرع' : 'تم التسليم', $by);
        $this->tell($o, 'تم تسليم طلبك', 'شكراً لتسوقك من دفع المحركات.');
        return $o;
    }

    public function cancel(Order $o, ?User $by, string $reason): Order
    {
        if (!in_array($o->status, ['new', 'pending_transfer', 'processing', 'ready'], true)) {
            throw ValidationException::withMessages(['order' => 'لا يمكن إلغاء الطلب في حالته الحالية']);
        }
        DB::transaction(function () use ($o, $by, $reason) {
            foreach ($o->items as $it) {
                if ($it->part_id) {
                    $this->stock->release($it->part_id, $it->qty, $o->branch_id);
                }
            }
            $o->update(['status' => 'cancelled', 'cancel_reason' => $reason,
                'payment_status' => $o->payment_status === 'paid' ? 'paid' : 'unpaid']);
            $this->event($o, 'cancelled', $reason, $by);
        });
        $this->tell($o, 'تم إلغاء طلبك', $reason);
        return $o;
    }

    private function commitItems(Order $o, User $by): void
    {
        foreach ($o->items as $it) {
            if ($it->part_id) {
                $this->stock->commitReserved($it->part_id, $it->qty, $o->number, $by->id, $o->branch_id);
            }
        }
    }
}
