<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\TabularIO;
use App\Support\Phone;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private const TYPES = [
        'sales' => ['المبيعات', 'كل الطلبات بحالتها وقناتها وإجمالياتها'],
        'inventory' => ['المخزون', 'الكميات المتاحة والمحجوزة والأسعار'],
        'customers' => ['العملاء', 'الحسابات وعدد الطلبات وإجمالي الشراء'],
        'movements' => ['حركة المخزون', 'كل الإضافات والخصومات مع المرجع والموظف'],
    ];

    public function index()
    {
        return view('admin.reports.index', ['types' => self::TYPES, 'unmask' => auth()->user()->hasPermission('reports.customers')]);
    }

    public function export(Request $r, string $type, TabularIO $io)
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        $r->validate(['format' => 'nullable|in:xlsx,csv', 'from' => 'nullable|date', 'to' => 'nullable|date']);
        $fmt = $r->query('format', 'xlsx');
        $from = $r->query('from') ? $r->date('from')->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $r->query('to') ? $r->date('to')->endOfDay() : now()->endOfDay();
        $unmask = $r->user()->hasPermission('reports.customers');
        $cost = $r->user()->hasPermission('inventory.cost');
        $ph = fn (?string $p) => $unmask ? (string) $p : Phone::mask($p);
        $labels = config('dp.order_statuses');
        $channels = ['app' => 'التطبيق', 'web' => 'الموقع', 'pos' => 'المعرض'];
        $name = "{$type}-".now()->format('Ymd-Hi');

        switch ($type) {
            case 'sales':
                $rows = Order::whereBetween('placed_at', [$from, $to])->orderBy('placed_at')->cursor()->map(fn ($o) => [
                    $o->number, $o->placed_at->format('Y-m-d H:i'), $channels[$o->channel] ?? $o->channel, $labels[$o->status] ?? $o->status,
                    $o->payment_method, $o->customer_name, $ph($o->customer_phone), $o->subtotal, $o->shipping, $o->vat, $o->total]);
                return $io->download($name, ['رقم الطلب', 'التاريخ', 'القناة', 'الحالة', 'الدفع', 'العميل', 'الجوال', 'المنتجات', 'الشحن', 'الضريبة المضمّنة', 'الإجمالي'], $rows, $fmt);
            case 'inventory':
                $head = ['رقم القطعة', 'الاسم', 'الكمية الفعلية', 'المحجوز', 'المتاح', 'السعر'] + ($cost ? [6 => 'التكلفة'] : []);
                $rows = Part::with('inventory')->orderBy('sku')->get()->map(fn ($p) => array_merge(
                    [$p->sku, $p->name_ar, $p->inventory->on_hand ?? 0, $p->inventory->reserved ?? 0, $p->available, $p->price], $cost ? [$p->cost] : []));
                return $io->download($name, $head, $rows, $fmt);
            case 'customers':
                $rows = User::where('is_staff', false)->withCount('orders')->withSum('orders', 'total')->orderBy('id')->get()->map(fn ($u) => [
                    $u->name, $ph($u->phone), $u->type === 'business' ? 'منشأة: '.$u->business_name : 'فرد', $u->city, $u->orders_count, (float) $u->orders_sum_total, $u->created_at->format('Y-m-d')]);
                return $io->download($name, ['الاسم', 'الجوال', 'النوع', 'المدينة', 'عدد الطلبات', 'إجمالي الشراء', 'تاريخ التسجيل'], $rows, $fmt);
            default:
                $rows = StockMovement::with('part', 'user')->whereBetween('created_at', [$from, $to])->orderBy('id')->cursor()->map(fn ($m) => [
                    $m->created_at->format('Y-m-d H:i'), $m->part?->sku, $m->part?->name_ar, $m->qty, $m->type, $m->reference, $m->user?->name]);
                return $io->download($name, ['التاريخ', 'رقم القطعة', 'الاسم', 'الكمية', 'النوع', 'المرجع', 'الموظف'], $rows, $fmt);
        }
    }
}
