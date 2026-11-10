<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Inventory;
use App\Support\BranchContext;
use App\Models\Part;
use App\Services\InventoryService;
use App\Services\TabularIO;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class InventoryController extends Controller
{
    public function index(Request $r)
    {
        $branchId = BranchContext::current();
        $q = Part::with(['inventory', 'category', 'inventories'])->search($r->query('q'))->inCategory($r->query('category'));
        match ($r->query('status')) {
            'low' => $q->whereHas('inventory', fn ($w) => $w->whereRaw('on_hand - reserved > 0 AND on_hand - reserved <= low_stock_at')),
            'out' => $q->where(fn ($w) => $w->whereDoesntHave('inventory')->orWhereHas('inventory', fn ($i) => $i->whereRaw('on_hand - reserved <= 0'))),
            default => null,
        };
        return view('admin.inventory.index', [
            'parts' => $q->orderBy('sku')->paginate(25)->withQueryString(),
            'categories' => Category::roots()->with('children')->get(),
            'branch' => Branch::find($branchId),
            'branches' => Branch::active()->get(['id', 'name', 'code']),
            'canTransfer' => auth()->user()->hasPermission('branches.manage'),
            'canCost' => auth()->user()->hasPermission('inventory.cost'),
            'canEdit' => auth()->user()->hasPermission('inventory.edit'),
            'canImport' => auth()->user()->hasPermission('inventory.import'),
            'stats' => [
                'total' => Part::count(),
                'out' => Part::count() - Inventory::where('branch_id', $branchId)->whereRaw('on_hand - reserved > 0')->count(),
                'low' => Inventory::where('branch_id', $branchId)->whereRaw('on_hand - reserved > 0 AND on_hand - reserved <= low_stock_at')->count(),
            ],
        ]);
    }

    public function update(Request $r, Part $part, InventoryService $stock)
    {
        $u = $r->user();
        $d = $r->validate(['on_hand' => 'required|integer|min:0|max:100000', 'price' => 'nullable|numeric|min:0', 'cost' => 'nullable|numeric|min:0',
            'low_stock_at' => 'nullable|integer|min:0', 'location' => 'nullable|string|max:40']);
        $stock->setOnHand($part->id, (int) $d['on_hand'], 'adjust', 'يدوي', $u->id);
        $part->inventory()->update(array_filter(['low_stock_at' => $d['low_stock_at'] ?? null, 'location' => $d['location'] ?? null], fn ($v) => $v !== null));
        $upd = [];
        if (isset($d['price'])) {
            $upd['price'] = $d['price'];
        }
        if (isset($d['cost']) && $u->hasPermission('inventory.cost')) {
            $upd['cost'] = $d['cost'];
        }
        $upd && $part->update($upd);
        return back()->with('ok', "تم تحديث {$part->sku}");
    }

    public function export(Request $r, TabularIO $io)
    {
        $cost = $r->user()->hasPermission('inventory.cost');
        // الفرع الحالي + عمود متاح لكل فرع
        $branches = Branch::active()->get(['id', 'name', 'code']);
        $current = Branch::find(BranchContext::current());
        $head = array_merge(['رقم القطعة', 'الاسم', 'القسم', 'الدرجة', 'الكمية الفعلية', 'المحجوز', 'المتاح', 'السعر شامل الضريبة'], $cost ? ['التكلفة'] : [],
            $branches->map(fn ($b) => 'متاح: '.$b->name)->all());
        $rows = Part::with('inventory', 'category', 'inventories')->orderBy('sku')->get()->map(function ($p) use ($cost, $branches) {
            $i = $p->inventory;
            $row = [$p->sku, $p->name_ar.($p->side ? ($p->side === 'R' ? ' يمين' : ' يسار') : ''), $p->category?->name_ar, \App\Http\Presenter::GRADES[$p->grade] ?? $p->grade, $i->on_hand ?? 0, $i->reserved ?? 0, $p->available, $p->price];
            $byBranch = $p->inventories->keyBy('branch_id');
            return array_merge($row, $cost ? [$p->cost] : [], $branches->map(fn ($b) => $byBranch[$b->id]?->available ?? 0)->all());
        });
        return $io->download('inventory-'.strtolower($current->code ?? 'all').'-'.now()->format('Ymd'), $head, $rows, $r->query('format') === 'csv' ? 'csv' : 'xlsx');
    }

    public function template(TabularIO $io)
    {
        return $io->download('inventory-template', ['sku', 'on_hand', 'price', 'cost'], [['53812-06D90', 4, 1240, 930], ['AM-53812-L', 8, 585, 420]], 'csv');
    }

    /** الخطوة 1: قراءة الملف ومعاينة التغييرات والأخطاء قبل التطبيق */
    public function importPreview(Request $r, TabularIO $io)
    {
        $r->validate(['file' => 'required|file|mimes:csv,txt,xlsx|max:10240'], ['file.mimes' => 'الملف يجب أن يكون CSV أو XLSX']);
        $read = $io->read($r->file('file'));
        if ($read['missing']) {
            return back()->withErrors(['file' => 'أعمدة ناقصة: '.implode('، ', $read['missing']).' — حمّل القالب النموذجي']);
        }
        $skus = collect($read['rows'])->pluck('sku')->filter()->unique();
        $parts = Part::with('inventory')->whereIn('sku', $skus)->get()->keyBy('sku');
        $changes = [];
        $errors = [];
        $seen = [];
        foreach ($read['rows'] as $row) {
            $line = $row['_line'];
            $sku = $row['sku'] ?? '';
            $p = $parts[$sku] ?? null;
            if ($sku === '' || !$p) {
                $errors[] = ['line' => $line, 'sku' => $sku, 'msg' => 'رقم القطعة غير موجود في الكتالوج'];
                continue;
            }
            if (isset($seen[$sku])) {
                $errors[] = ['line' => $line, 'sku' => $sku, 'msg' => "مكرر (السطر {$seen[$sku]})"];
                continue;
            }
            $seen[$sku] = $line;
            $qty = $row['on_hand'] ?? '';
            if (!preg_match('/^\d+$/', $qty)) {
                $errors[] = ['line' => $line, 'sku' => $sku, 'msg' => 'الكمية يجب أن تكون رقماً صحيحاً غير سالب'];
                continue;
            }
            $price = $row['price'] ?? '';
            if ($price !== '' && (!is_numeric($price) || (float) $price < 0)) {
                $errors[] = ['line' => $line, 'sku' => $sku, 'msg' => 'السعر غير صالح'];
                continue;
            }
            $cost = $row['cost'] ?? '';
            if ($cost !== '' && (!is_numeric($cost) || (float) $cost < 0)) {
                $errors[] = ['line' => $line, 'sku' => $sku, 'msg' => 'التكلفة غير صالحة'];
                continue;
            }
            $old = $p->inventory->on_hand ?? 0;
            $changes[] = ['part_id' => $p->id, 'sku' => $sku, 'name' => $p->name_ar, 'old' => $old, 'new' => (int) $qty,
                'price' => $price === '' ? null : (float) $price, 'old_price' => $p->price, 'cost' => $cost === '' ? null : (float) $cost,
                'restock' => ($p->available <= 0 && (int) $qty - ($p->inventory->reserved ?? 0) > 0)];
        }
        $changes = array_values(array_filter($changes, fn ($c) => $c['old'] !== $c['new'] || ($c['price'] !== null && $c['price'] != $c['old_price']) || $c['cost'] !== null));
        $id = Str::random(24);
        Cache::put('import:'.$id, ['user' => $r->user()->id, 'branch' => BranchContext::current(), 'changes' => $changes], now()->addMinutes(30));
        $alerts = \App\Models\StockAlert::whereNull('notified_at')->whereIn('part_id', collect($changes)->where('restock', true)->pluck('part_id'))->count();
        return view('admin.inventory.import', ['id' => $id, 'changes' => $changes, 'errors' => $errors, 'alerts' => $alerts, 'file' => $r->file('file')->getClientOriginalName(), 'branch' => Branch::find(BranchContext::current()), 'canCost' => $r->user()->hasPermission('inventory.cost')]);
    }

    /** الخطوة 2: التطبيق (تخطّي الأسطر الخاطئة تلقائياً) */
    public function importApply(Request $r, InventoryService $stock)
    {
        $data = Cache::pull('import:'.$r->input('id'));
        abort_unless($data && $data['user'] === $r->user()->id, 419, 'انتهت صلاحية المعاينة — ارفع الملف من جديد');
        $notified = 0;
        foreach ($data['changes'] as $c) {
            $stock->setOnHand($c['part_id'], $c['new'], 'import', 'استيراد ملف', $r->user()->id, $data['branch'] ?? null);
            $upd = array_filter(['price' => $c['price'], 'cost' => $r->user()->hasPermission('inventory.cost') ? $c['cost'] : null], fn ($v) => $v !== null);
            $upd && Part::whereKey($c['part_id'])->update($upd);
        }
        $notified = \App\Models\StockAlert::whereNotNull('notified_at')->where('notified_at', '>=', now()->subMinute())->count();
        return redirect()->route('admin.inventory.index')->with('ok', 'تم تطبيق '.count($data['changes']).' تغيير'.($notified ? " وإرسال {$notified} تنبيه توفر للعملاء" : ''));
    }
}
