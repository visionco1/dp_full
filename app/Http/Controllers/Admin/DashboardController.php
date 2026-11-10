<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /** الصفحة الرئيسية: تتحول حسب صلاحيات الموظف */
    public function home()
    {
        $u = auth()->user();
        foreach ([['dashboard.view', 'admin.dashboard'], ['pos.use', 'admin.pos.index'], ['orders.view', 'admin.orders.index'], ['inventory.view', 'admin.inventory.index']] as [$p, $route]) {
            if ($u->hasPermission($p)) {
                return redirect()->route($route);
            }
        }
        abort(403, 'لا توجد صلاحيات مفعّلة لحسابك');
    }

    public function index()
    {
        $money = auth()->user()->hasPermission('dashboard.financials');
        $live = Order::where('status', '!=', 'cancelled');
        $todayStart = now()->startOfDay();

        $days = collect(range(6, 0))->map(function ($i) use ($live) {
            $d = Carbon::today()->subDays($i);
            $q = (clone $live)->whereBetween('placed_at', [$d->copy()->startOfDay(), $d->copy()->endOfDay()]);
            return [
                'label' => $d->locale('ar')->translatedFormat('D j'),
                'online' => (float) (clone $q)->where('channel', '!=', 'pos')->sum('total'),
                'showroom' => (float) (clone $q)->where('channel', 'pos')->sum('total'),
            ];
        });
        $max = max(1, $days->map(fn ($d) => $d['online'] + $d['showroom'])->max());

        $today = (clone $live)->where('placed_at', '>=', $todayStart);
        $profit = $money ? (float) OrderItem::whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled')->where('placed_at', '>=', $todayStart))
            ->selectRaw('COALESCE(SUM((unit_price - unit_cost) * qty),0) as p')->value('p') : null;

        $branchId = \App\Support\BranchContext::current();   // المخزون المنخفض لفرع العمل الحالي
        $low = Inventory::with('part')->where('branch_id', $branchId)->whereRaw('on_hand - reserved <= low_stock_at')->orderByRaw('on_hand - reserved')->limit(8)->get();

        return view('admin.dashboard', [
            'money' => $money,
            'todayTotal' => (float) (clone $today)->sum('total'),
            'todayCount' => (clone $today)->count(),
            'profit' => $profit,
            'pendingTransfers' => Order::where('status', 'pending_transfer')->count(),
            'newOrders' => Order::where('status', 'new')->count(),
            'outOfStock' => Inventory::where('branch_id', $branchId)->whereRaw('on_hand - reserved <= 0')->count(),
            'statusCounts' => Order::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'days' => $days, 'max' => $max, 'low' => $low,
            'recent' => Order::latest('placed_at')->limit(8)->get(),
        ]);
    }
}
