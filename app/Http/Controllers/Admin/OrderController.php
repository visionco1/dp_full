<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $r)
    {
        // الموظف المربوط بفرع يرى طلبات فرعه فقط
        $branch = $r->user()->branch_id ?: ($r->filled('branch') ? $r->integer('branch') : null);
        $q = Order::query()->with('branch:id,name,code')->withCount('items')
            ->when($branch, fn ($w) => $w->where('branch_id', $branch))
            ->when(in_array($r->query('fulfillment'), ['delivery', 'pickup'], true), fn ($w) => $w->where('fulfillment', $r->query('fulfillment')));
        if ($r->filled('status')) {
            $q->where('status', $r->query('status'));
        }
        if ($r->filled('channel')) {
            $q->where('channel', $r->query('channel'));
        }
        if ($s = trim((string) $r->query('q'))) {
            $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")->orWhere('customer_phone', 'like', "%{$s}%")->orWhere('customer_name', 'like', "%{$s}%"));
        }
        $counts = Order::when($branch, fn ($w) => $w->where('branch_id', $branch))->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
        return view('admin.orders.index', [
            'orders' => $q->latest('placed_at')->paginate(20)->withQueryString(),
            'counts' => $counts, 'total' => $counts->sum(),
            'unmask' => $r->user()->hasPermission('reports.customers'),
            'branches' => $r->user()->branch_id ? collect() : \App\Models\Branch::orderBy('sort')->get(['id', 'name']),
        ]);
    }

    public function show(Request $r, Order $order)
    {
        abort_if($r->user()->branch_id && $order->branch_id !== $r->user()->branch_id, 403, 'هذا الطلب يخص فرعاً آخر');
        return view('admin.orders.show', ['order' => $order->load('items.part', 'events.user', 'creator', 'branch')]);
    }

    public function approve(Request $r, Order $order)
    {
        abort_if($r->user()->branch_id && $order->branch_id !== $r->user()->branch_id, 403, 'هذا الطلب يخص فرعاً آخر');
        $this->orders->approvePayment($order, $r->user());
        return back()->with('ok', 'تم اعتماد الدفع — الطلب قيد التجهيز');
    }

    public function reject(Request $r, Order $order)
    {
        abort_if($r->user()->branch_id && $order->branch_id !== $r->user()->branch_id, 403, 'هذا الطلب يخص فرعاً آخر');
        $d = $r->validate(['reason' => 'required|string|max:200', 'cancel' => 'nullable|boolean']);
        $this->orders->rejectPayment($order, $r->user(), $d['reason'], $r->boolean('cancel'));
        return back()->with('ok', $r->boolean('cancel') ? 'تم رفض التحويل وإلغاء الطلب' : 'تم رفض التحويل وإبلاغ العميل');
    }

    public function transition(Request $r, Order $order, string $to)
    {
        abort_if($r->user()->branch_id && $order->branch_id !== $r->user()->branch_id, 403, 'هذا الطلب يخص فرعاً آخر');
        match ($to) {
            'ready' => $this->orders->markReady($order, $r->user()),
            'processing' => $this->orders->startProcessing($order, $r->user()),
            'ship' => $this->orders->ship($order, $r->user(), $r->input('waybill_no')),
            'complete' => $this->orders->complete($order, $r->user()),
            'cancel' => $this->orders->cancel($order, $r->user(), $r->validate(['reason' => 'required|string|max:200'])['reason']),
            default => abort(404),
        };
        return back()->with('ok', 'تم تحديث حالة الطلب');
    }

    /** طباعة مجمّعة أو منفردة: type=waybill|invoice و ids[]= */
    public function print(Request $r)
    {
        $d = $r->validate(['type' => 'required|in:waybill,invoice', 'ids' => 'required|array|min:1|max:100', 'ids.*' => 'integer']);
        $orders = Order::with('items')->whereIn('id', $d['ids'])->orderBy('id')->get();
        if ($d['type'] === 'waybill') {
            // رقم البوليصة يُولَّد عند الطباعة إن لم يوجد
            foreach ($orders as $o) {
                if (!$o->waybill_no && $o->channel !== 'pos') {
                    $o->update(['waybill_no' => (string) random_int(2900000000, 2999999999)]);
                }
            }
        }
        return view('admin.print.'.$d['type'], ['orders' => $orders, 'auto' => true]);
    }
}
