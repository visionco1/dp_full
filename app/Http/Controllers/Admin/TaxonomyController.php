<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarModel;
use App\Models\Category;
use App\Models\Make;
use App\Models\PartFitment;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** الأقسام + الماركات والموديلات (تصنيفات الكتالوج) */
class TaxonomyController extends Controller
{
    // ------------------------------------------------------------ الأقسام

    public function categories()
    {
        return view('admin.catalog.categories', [
            'categories' => Category::roots()->withCount('parts')->with(['children' => fn ($q) => $q->withCount('parts')])->get(),
        ]);
    }

    private function categoryData(Request $r, ?Category $c = null): array
    {
        $d = $r->validate([
            'name_ar' => 'required|string|max:60',
            'name_en' => 'nullable|string|max:60',
            'slug' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/', Rule::unique('categories', 'slug')->ignore($c?->id)],
            'sort' => 'nullable|integer|min:0|max:999',
            'image' => 'nullable|image|max:2048',
            'remove_image' => 'nullable|boolean',
            // الأب لازم يكون قسماً رئيسياً (مستوى واحد) وليس القسم نفسه
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id'), Rule::notIn([$c?->id])],
        ], [
            'parent_id.exists' => 'القسم الرئيسي غير صالح — الأقسام الفرعية لا تحتوي أقساماً فرعية',
            'parent_id.not_in' => 'لا يمكن أن يكون القسم فرعياً لنفسه','slug.regex' => 'المعرّف (slug) حروف إنجليزية صغيرة وأرقام وشرطة فقط مثل air-filters', 'slug.unique' => 'هذا المعرّف مستخدم لقسم آخر']);

        // المعرّف يُولَّد من الاسم الإنجليزي لو تُرك فارغاً
        $slug = ($d['slug'] ?? null) ?: Str::slug($d['name_en'] ?? '') ?: ($c?->slug ?? 'cat-'.Str::lower(Str::random(6)));
        $base = $slug;
        for ($i = 2; Category::where('slug', $slug)->when($c, fn ($q) => $q->whereKeyNot($c->id))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }
        $parent = $d['parent_id'] ?? null;
        if ($parent && $c && $c->children()->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['parent_id' => "«{$c->name_ar}» فيه أقسام فرعية، فلا يمكن جعله فرعياً — انقل أقسامه الفرعية أولاً"]);
        }
        $out = ['name_ar' => $d['name_ar'], 'name_en' => $d['name_en'] ?? null, 'slug' => $slug, 'sort' => (int) ($d['sort'] ?? 0), 'parent_id' => $parent];

        if ($r->hasFile('image')) {
            $c?->image && Storage::disk('public')->delete($c->image);
            $out['image'] = $r->file('image')->store('categories', 'public');
        } elseif ($c?->image && $r->boolean('remove_image')) {
            Storage::disk('public')->delete($c->image);
            $out['image'] = null;
        }
        return $out;
    }

    public function storeCategory(Request $r)
    {
        $d = $this->categoryData($r);
        $d['sort'] = $d['sort'] ?: (int) Category::where('parent_id', $d['parent_id'])->max('sort') + 1;
        Category::create($d);
        return back()->with('ok', 'تمت إضافة القسم');
    }

    public function updateCategory(Request $r, Category $category)
    {
        $category->update($this->categoryData($r, $category));
        return back()->with('ok', 'تم حفظ القسم');
    }

    public function destroyCategory(Category $category)
    {
        if ($n = $category->children()->count()) {
            return back()->withErrors(['category' => "لا يمكن حذف «{$category->name_ar}» لأن فيه {$n} قسم فرعي — احذفها أو انقلها أولاً"]);
        }
        if ($n = $category->parts()->count()) {
            return back()->withErrors(['category' => "لا يمكن حذف قسم «{$category->name_ar}» لأن فيه {$n} قطعة — انقل القطع لقسم آخر أولاً"]);
        }
        $category->image && Storage::disk('public')->delete($category->image);
        $category->delete();
        return back()->with('ok', 'تم حذف القسم');
    }

    // ------------------------------------------------------------ الماركات والموديلات

    public function makes(Request $r)
    {
        $makes = Make::with(['models' => fn ($q) => $q->withCount(['fitments'])->orderBy('name_ar')])->orderBy('name_ar')->get();
        $vehicles = Vehicle::selectRaw('car_model_id, COUNT(*) as n')->groupBy('car_model_id')->pluck('n', 'car_model_id');
        return view('admin.catalog.makes', ['makes' => $makes, 'vehicles' => $vehicles, 'open' => $r->integer('make')]);
    }

    public function storeMake(Request $r)
    {
        $d = $r->validate(['name_ar' => 'required|string|max:60|unique:makes,name_ar', 'name_en' => 'required|string|max:60|unique:makes,name_en'],
            ['name_ar.unique' => 'الماركة موجودة', 'name_en.unique' => 'الماركة موجودة']);
        $make = Make::create($d);
        return redirect()->route('admin.catalog.makes.index', ['make' => $make->id])->with('ok', 'تمت إضافة الماركة — أضف موديلاتها');
    }

    public function updateMake(Request $r, Make $make)
    {
        $make->update($r->validate([
            'name_ar' => ['required', 'string', 'max:60', Rule::unique('makes', 'name_ar')->ignore($make->id)],
            'name_en' => ['required', 'string', 'max:60', Rule::unique('makes', 'name_en')->ignore($make->id)],
        ]));
        return back()->with('ok', 'تم حفظ الماركة');
    }

    public function destroyMake(Make $make)
    {
        $ids = $make->models()->pluck('id');
        if ($ids->isNotEmpty() && (PartFitment::whereIn('car_model_id', $ids)->exists() || Vehicle::whereIn('car_model_id', $ids)->exists())) {
            return back()->withErrors(['make' => "لا يمكن حذف «{$make->name_ar}» لأن موديلاتها مرتبطة بقطع أو بسيارات عملاء"]);
        }
        $make->delete();
        return back()->with('ok', 'تم حذف الماركة');
    }

    private function modelData(Request $r): array
    {
        $max = now()->year + 1;
        return $r->validate([
            'name_ar' => 'required|string|max:60',
            'name_en' => 'required|string|max:60',
            'year_from' => "required|integer|between:1970,$max",
            'year_to' => "nullable|integer|between:1970,$max|gte:year_from",
        ], ['year_to.gte' => 'سنة النهاية يجب أن تكون بعد سنة البداية']);
    }

    public function storeModel(Request $r, Make $make)
    {
        $make->models()->create($this->modelData($r));
        return redirect()->route('admin.catalog.makes.index', ['make' => $make->id])->with('ok', 'تمت إضافة الموديل');
    }

    public function updateModel(Request $r, CarModel $model)
    {
        $model->update($this->modelData($r));
        return redirect()->route('admin.catalog.makes.index', ['make' => $model->make_id])->with('ok', 'تم حفظ الموديل');
    }

    public function destroyModel(CarModel $model)
    {
        $fits = $model->fitments()->count();
        $cars = Vehicle::where('car_model_id', $model->id)->count();
        if ($fits || $cars) {
            return back()->withErrors(['model' => "لا يمكن حذف الموديل: مرتبط بـ {$fits} قطعة و{$cars} سيارة عميل"]);
        }
        $makeId = $model->make_id;
        $model->delete();
        return redirect()->route('admin.catalog.makes.index', ['make' => $makeId])->with('ok', 'تم حذف الموديل');
    }
}
