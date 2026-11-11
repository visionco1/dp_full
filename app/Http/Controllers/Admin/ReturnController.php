<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Services\InventoryService;
use App\Services\NotificationService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** مراجعة طلبات الإرجاع والاستبدال */
class ReturnController extends Controller
{
    public function index(Request $r)
    {
        $branch = $r->user()->branch_id;
        $q = ReturnRequest::with('order.branch', 'item')
            ->when($branch, fn ($w) => $w->whereHas('order', fn ($o) => $o->where('branch_id', $branch)))
            ->when($r->filled('status'), fn ($w) => $w->where('status', $r->query('status')), fn ($w) => $w->whereIn('status', ['pending', 'approved']));
        return view('admin.returns.index', ['returns' => $q->latest('id')->paginate(25)->withQueryString()]);
    }

    /** approve | reject | complete */
    public function update(Request $r, ReturnRequest $return, string $to, OrderService $orders, InventoryService $stock, NotificationService $notify)
    {
        $order = $return->order;
        abort_if($r->user()->branch_id && $order->branch_id !== $r->user()->branch_id, 403, 'هذا الطلب يخص فرعاً آخر');
        $note = $r->validate(['note' => ($to === 'reject' ? 'required' : 'nullable').'|string|max:300'], ['note.required' => 'اكتب سبب الرفض للعميل'])['note'] ?? null;

        $allowed = ['approve' => ['pending'], 'reject' => ['pending', 'approved'], 'complete' => ['approved']][$to] ?? abort(404);
        if (!in_array($return->status, $allowed, true)) {
            throw ValidationException::withMessages(['return' => 'لا يمكن تنفيذ هذا الإجراء في الحالة الحالية']);
        }
        $status = ['approve' => 'approved', 'reject' => 'rejected', 'complete' => 'completed'][$to];

        DB::transaction(function () use ($return, $status, $note, $r, $to, $stock, $order) {
            // اكتمال الإرجاع: القطعة رجعت للفرع → تعود للمخزون. الاستبدال: خروج قطعة بديلة ودخول المرتجعة (صافي صفر)
            if ($to === 'complete' && $return->action === 'return' && $return->item?->part_id) {
                $stock->adjust($return->item->part_id, $return->qty, 'return', $order->number, $r->user()->id, $order->branch_id);
            }
            $return->update(['status' => $status, 'admin_note' => $note ?? $return->admin_note, 'handled_by' => $r->user()->id]);
        });

        $label = \App\Models\ReturnRequest::ACTIONS[$return->action];
        $msg = [
            'approved' => "تمت الموافقة على طلب {$label} — أرسل القطعة أو سلّمها للفرع".($note ? ": {$note}" : ''),
            'rejected' => "تعذّر قبول طلب {$label}: {$note}",
            'completed' => $return->action === 'return' ? 'تم استلام القطعة المرتجعة وسيُعاد المبلغ خلال 7 أيام عمل' : 'تم الاستبدال بنجاح',
        ][$status];
        $orders->event($order, $order->status, "{$label} ({$return->item?->name}): {$msg}", $r->user());
        if ($order->user) {
            $notify->push($order->user, 'order', "طلب {$label} — {$order->number}", $msg, ['order' => $order->number, 'return_id' => $return->id]);
        }
        $notify->sms($order->customer_phone, "{$order->number}: {$msg}");
        return back()->with('ok', 'تم تحديث طلب '.$label);
    }
}
