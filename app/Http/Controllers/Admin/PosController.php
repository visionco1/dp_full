<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Presenter;
use App\Models\Order;
use App\Models\Part;
use App\Services\OrderService;
use App\Support\Phone;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function index()
    {
        return view('admin.pos.index');
    }

    public function search(Request $r)
    {
        $q = trim((string) $r->query('q', ''));
        if ($q === '') {
            return ['results' => []];
        }
        $term = Phone::latinDigits($q);
        $parts = Part::with('inventory')->where('active', true)->search($term)
            ->orderByRaw('CASE WHEN sku = ? THEN 0 WHEN sku LIKE ? THEN 1 ELSE 2 END', [$term, $term.'%'])->limit(12)->get();
        return ['results' => $parts->map(fn ($p) => [
            'id' => $p->id, 'sku' => $p->sku, 'name' => $p->name_ar, 'grade' => Presenter::GRADES[$p->grade] ?? $p->grade,
            'side' => $p->side, 'price' => $p->price, 'available' => $p->available,
        ])->values()];
    }

    public function checkout(Request $r, OrderService $orders)
    {
        $d = $r->validate([
            'items' => 'required|array|min:1', 'items.*.part_id' => 'required|exists:parts,id', 'items.*.qty' => 'required|integer|min:1|max:99',
            'payment' => 'required|in:cash,card', 'tendered' => 'nullable|numeric|min:0',
            'customer_name' => 'nullable|string|max:80', 'customer_phone' => 'nullable|string|max:20',
        ]);
        $phone = !empty($d['customer_phone']) ? (Phone::normalize($d['customer_phone']) ?? '') : '';
        $order = $orders->place($d['items'], [
            'channel' => 'pos', 'payment' => $d['payment'], 'staff' => $r->user(),
            'customer' => ['name' => $d['customer_name'] ?? 'عميل المعرض', 'phone' => $phone],
        ]);
        $order->load('items.part.inventory');
        if ($d['payment'] === 'cash' && isset($d['tendered']) && $d['tendered'] < $order->total) {
            // لا نمنع البيع بعد حدوثه، لكن نُبلغ الواجهة بالنقص
        }
        return response()->json([
            'order' => ['number' => $order->number, 'total' => $order->total, 'vat' => $order->vat],
            'change' => $d['payment'] === 'cash' ? max(0, round(($d['tendered'] ?? $order->total) - $order->total, 2)) : 0,
            'stock' => $order->items->map(fn ($i) => ['sku' => $i->sku, 'name' => $i->name, 'qty' => $i->qty, 'available_now' => $i->part?->available, 'online_unavailable' => ($i->part?->available ?? 0) <= 0])->values(),
            'receipt_url' => route('admin.pos.receipt', $order),
        ], 201);
    }

    public function receipt(Order $order)
    {
        abort_unless($order->channel === 'pos', 404);
        return view('admin.print.invoice', ['orders' => collect([$order->load('items')]), 'auto' => true]);
    }
}
