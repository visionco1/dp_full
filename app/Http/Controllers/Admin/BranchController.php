<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Part;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index()
    {
        $stock = Inventory::selectRaw('branch_id, SUM(on_hand) on_hand, SUM(reserved) reserved, SUM(CASE WHEN on_hand - reserved > 0 THEN 1 ELSE 0 END) skus')
            ->groupBy('branch_id')->get()->keyBy('branch_id');
        $open = Order::whereIn('status', Order::OPEN)->selectRaw('branch_id, COUNT(*) n')->groupBy('branch_id')->pluck('n', 'branch_id');
        return view('admin.branches.index', [
            'branches' => Branch::withCount('staff')->orderBy('sort')->orderBy('id')->get(),
            'stock' => $stock, 'open' => $open,
        ]);
    }

    private function data(Request $r, ?Branch $b = null): array
    {
        $d = $r->validate([
            'name' => 'required|string|max:80',
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('branches', 'code')->ignore($b?->id)],
            'city' => 'required|string|max:60',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'hours' => 'nullable|string|max:120',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'sort' => 'nullable|integer|min:0|max:999',
        ], ['code.regex' => 'الكود حروف إنجليزية وأرقام فقط مثل JED']);
        $d['code'] = strtoupper($d['code']);
        $d['sort'] = (int) ($d['sort'] ?? 0);
        $d['pickup_enabled'] = $r->boolean('pickup_enabled');
        $d['delivery_enabled'] = $r->boolean('delivery_enabled');
        $d['active'] = $r->boolean('active');
        return $d;
    }

    public function store(Request $r)
    {
        Branch::create($this->data($r));
        return back()->with('ok', 'تمت إضافة الفرع — أضف مخزونه من صفحة المخزون أو بالتحويل من فرع آخر');
    }

    public function update(Request $r, Branch $branch)
    {
        $d = $this->data($r, $branch);
        if ($branch->is_default && !$d['active']) {
            return back()->withErrors(['branch' => 'لا يمكن إيقاف الفرع الافتراضي — اجعل فرعاً آخر افتراضياً أولاً']);
        }
        $branch->update($d);
        return back()->with('ok', 'تم حفظ الفرع');
    }

    public function makeDefault(Branch $branch)
    {
        abort_unless($branch->active, 422, 'فعّل الفرع أولاً');
        DB::transaction(function () use ($branch) {
            Branch::where('is_default', true)->update(['is_default' => false]);
            $branch->update(['is_default' => true]);
        });
        return back()->with('ok', "أصبح {$branch->name} الفرع الافتراضي");
    }

    public function destroy(Branch $branch)
    {
        if ($branch->is_default) {
            return back()->withErrors(['branch' => 'لا يمكن حذف الفرع الافتراضي']);
        }
        if ($branch->orders()->exists() || $branch->inventories()->where('on_hand', '>', 0)->exists()) {
            return back()->withErrors(['branch' => "لا يمكن حذف {$branch->name}: عليه طلبات أو مخزون — أوقفه بدلاً من الحذف"]);
        }
        DB::transaction(function () use ($branch) {
            $branch->inventories()->delete();
            $branch->delete();
        });
        return back()->with('ok', 'تم حذف الفرع');
    }

    /** تبديل فرع العمل في لوحة الإدارة (للمستخدم غير المربوط بفرع) */
    public function switch(Request $r)
    {
        $id = $r->validate(['branch_id' => 'required|integer|exists:branches,id'])['branch_id'];
        abort_if($r->user()->branch_id && $r->user()->branch_id !== (int) $id, 403, 'حسابك مربوط بفرع محدد');
        $r->session()->put('admin_branch', (int) $id);
        return back()->with('ok', 'تم التبديل إلى '.Branch::find($id)->name);
    }

    /** تحويل مخزون قطعة بين فرعين */
    public function transfer(Request $r, InventoryService $stock)
    {
        $d = $r->validate([
            'part_id' => 'required|exists:parts,id',
            'from_branch' => 'required|exists:branches,id',
            'to_branch' => 'required|exists:branches,id|different:from_branch',
            'qty' => 'required|integer|min:1|max:100000',
        ], ['to_branch.different' => 'اختر فرعين مختلفين']);
        $stock->transfer((int) $d['part_id'], (int) $d['from_branch'], (int) $d['to_branch'], (int) $d['qty'], $r->user()->id);
        $p = Part::find($d['part_id']);
        return back()->with('ok', "تم تحويل {$d['qty']} من {$p->sku}");
    }
}
