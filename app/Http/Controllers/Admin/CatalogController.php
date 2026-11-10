<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarModel;
use App\Models\Category;
use App\Models\Make;
use App\Models\Part;
use App\Models\PartFitment;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function index(Request $r)
    {
        $parts = Part::with('category', 'inventory')->withCount('fitments')->search($r->query('q'))
            ->inCategory($r->input('category'))
            ->orderBy('sku')->paginate(25)->withQueryString();
        return view('admin.catalog.index', ['parts' => $parts, 'categories' => Category::roots()->with('children')->get()]);
    }

    public function create()
    {
        return view('admin.catalog.form', ['part' => new Part(['grade' => 'oem', 'active' => true]), 'categories' => Category::roots()->with('children')->get(), 'makes' => collect()]);
    }

    private function rules(?Part $part): array
    {
        return [
            'sku' => ['required', 'string', 'max:40', Rule::unique('parts', 'sku')->ignore($part?->id)],
            'name_ar' => 'required|string|max:150', 'name_en' => 'nullable|string|max:150',
            'category_id' => 'nullable|exists:categories,id', 'brand' => 'nullable|string|max:60',
            'grade' => 'required|in:oem,cert,first', 'side' => 'nullable|in:R,L', 'group_key' => 'nullable|string|max:60',
            'price' => 'required|numeric|min:0', 'cost' => 'nullable|numeric|min:0',
            'is_bulky' => 'nullable|boolean', 'shipping_fee' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:2000', 'active' => 'nullable|boolean',
            'image' => 'nullable|image|max:3072', 'on_hand' => 'nullable|integer|min:0',
        ];
    }

    public function store(Request $r, InventoryService $stock)
    {
        $d = $r->validate($this->rules(null));
        $part = Part::create($this->payload($r, $d));
        $stock->setOnHand($part->id, (int) ($d['on_hand'] ?? 0), 'adjust', 'إنشاء القطعة', $r->user()->id);
        return redirect()->route('admin.catalog.edit', $part)->with('ok', 'تمت إضافة القطعة — أضف التوافق مع السيارات');
    }

    public function edit(Part $part)
    {
        $part->load('fitments.carModel.make');
        return view('admin.catalog.form', ['part' => $part, 'categories' => Category::roots()->with('children')->get(), 'makes' => Make::with('models')->orderBy('name_ar')->get()]);
    }

    public function update(Request $r, Part $part)
    {
        $d = $r->validate($this->rules($part));
        $part->update($this->payload($r, $d));
        return back()->with('ok', 'تم حفظ القطعة');
    }

    private function payload(Request $r, array $d): array
    {
        unset($d['on_hand'], $d['image']);
        $d['is_bulky'] = $r->boolean('is_bulky');
        $d['active'] = $r->boolean('active');
        $d['cost'] = $d['cost'] ?? 0;
        $d['shipping_fee'] = $d['is_bulky'] ? ($d['shipping_fee'] ?? 0) : 0;
        if ($r->hasFile('image')) {
            $d['image'] = $r->file('image')->store('parts', 'public');
        }
        return $d;
    }

    public function destroy(Part $part)
    {
        if ($part->orderItemsExist()) {
            $part->update(['active' => false]);
            return redirect()->route('admin.catalog.index')->with('ok', 'للقطعة طلبات سابقة، فتم إخفاؤها بدلاً من الحذف');
        }
        $part->delete();
        return redirect()->route('admin.catalog.index')->with('ok', 'تم حذف القطعة');
    }

    /** التوافق: شركة > موديلات (متعدد) > من سنة إلى سنة */
    public function addFitment(Request $r, Part $part)
    {
        $d = $r->validate([
            'models' => 'required|array|min:1', 'models.*' => 'exists:car_models,id',
            'year_from' => 'required|integer|between:1980,2100', 'year_to' => 'required|integer|between:1980,2100|gte:year_from',
        ], ['models.required' => 'اختر موديلاً واحداً على الأقل', 'year_to.gte' => 'سنة النهاية يجب أن تكون بعد البداية']);
        foreach ($d['models'] as $id) {
            // دمج النطاقات المتداخلة بدل التكرار
            $from = (int) $d['year_from'];
            $to = (int) $d['year_to'];
            $overlap = $part->fitments()->where('car_model_id', $id)->where('year_from', '<=', $to + 1)->where('year_to', '>=', $from - 1)->get();
            foreach ($overlap as $o) {
                $from = min($from, $o->year_from);
                $to = max($to, $o->year_to);
                $o->delete();
            }
            $part->fitments()->create(['car_model_id' => $id, 'year_from' => $from, 'year_to' => $to]);
        }
        return back()->with('ok', 'تمت إضافة التوافق');
    }

    public function removeFitment(Part $part, PartFitment $fitment)
    {
        abort_unless($fitment->part_id === $part->id, 404);
        $fitment->delete();
        return back()->with('ok', 'تم حذف التوافق');
    }

    public function copyFitments(Request $r, Part $part)
    {
        $d = $r->validate(['from_sku' => 'required|exists:parts,sku'], ['from_sku.exists' => 'رقم القطعة المصدر غير موجود']);
        $src = Part::where('sku', $d['from_sku'])->firstOrFail();
        $part->fitments()->delete();
        foreach ($src->fitments as $f) {
            $part->fitments()->create($f->only(['car_model_id', 'year_from', 'year_to']));
        }
        return back()->with('ok', 'تم نسخ التوافق من '.$src->sku);
    }

    public function models(Make $make)
    {
        return $make->models()->orderBy('name_ar')->get(['id', 'name_ar', 'year_from', 'year_to']);
    }
}
