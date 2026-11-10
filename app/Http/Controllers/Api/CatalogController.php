<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Presenter;
use App\Models\CarModel;
use App\Models\Category;
use App\Models\Make;
use App\Models\Part;
use App\Models\StockAlert;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    /** الأقسام الرئيسية ومع كل قسم subcategories. parts_count للرئيسي يشمل قطع أقسامه الفرعية (القطع المفعّلة فقط) */
    public static function categoryTree(): \Illuminate\Support\Collection
    {
        $active = fn ($q) => $q->where('active', true);
        $roots = Category::roots()->withCount(['parts' => $active])
            ->with(['children' => fn ($q) => $q->withCount(['parts' => $active])])->get();
        $roots->each(fn ($c) => $c->parts_count += $c->children->sum('parts_count'));
        return $roots->map(fn ($c) => Presenter::category($c, true))->values();
    }

    public function categories()
    {
        return ['categories' => self::categoryTree()];
    }

    public function subcategories(Category $category)
    {
        $category->load(['children' => fn ($q) => $q->withCount(['parts' => fn ($w) => $w->where('active', true)])]);
        return [
            'category' => Presenter::category($category),
            'subcategories' => $category->children->map(fn ($s) => Presenter::category($s))->values(),
        ];
    }

    /** درجات القطع (الجودة) لفلاتر التطبيق */
    public function grades()
    {
        $counts = Part::where('active', true)->selectRaw('grade, COUNT(*) as n')->groupBy('grade')->pluck('n', 'grade');
        return ['grades' => collect(Presenter::GRADES)->map(fn ($label, $code) => [
            'code' => $code,
            'label_ar' => $label,
            'label_en' => Presenter::GRADE_DETAILS[$code]['label_en'] ?? $code,
            'description_ar' => Presenter::GRADE_DETAILS[$code]['description_ar'] ?? null,
            'warranty_months' => Presenter::GRADE_DETAILS[$code]['warranty_months'] ?? null,
            'color' => Presenter::GRADE_DETAILS[$code]['color'] ?? null,
            'aliases' => array_keys(array_filter(Presenter::GRADE_ALIASES, fn ($v) => $v === $code)),
            'parts_count' => (int) ($counts[$code] ?? 0),
        ])->values()];
    }

    /** البانرات والإعلانات النشطة للتطبيق — ?placement=hero|strip|side|topbar */
    public function banners(Request $r)
    {
        $r->validate(['placement' => 'nullable|in:'.implode(',', array_keys(\App\Models\Banner::PLACEMENTS))]);
        $cats = Category::all(['id', 'slug']);
        return ['banners' => \App\Models\Banner::live($r->input('placement'))->get()
            ->map(fn ($b) => \App\Http\Presenter::banner($b, $cats) + ['image' => $b->imageUrl()])->values()];
    }

    public function makes()
    {
        return ['makes' => Make::orderBy('name_ar')->get(['id', 'name_ar', 'name_en'])];
    }

    public function models(Make $make)
    {
        return ['models' => $make->models()->orderBy('name_ar')->get(['id', 'name_ar', 'name_en', 'year_from', 'year_to'])];
    }

    /** السيارة المعتمدة للتوافق: من الطلب (car_model_id + year) أو السيارة النشطة في كراج المستخدم */
    private function vehicleFor(Request $r): ?Vehicle
    {
        if ($r->filled('car_model_id')) {
            return new Vehicle(['car_model_id' => (int) $r->input('car_model_id'), 'year' => (int) $r->input('year', 0)]);
        }
        return $r->user('sanctum')?->vehicles()->where('is_active', true)->first();
    }

    public function parts(Request $r)
    {
        if (isset(Presenter::GRADE_ALIASES[$r->input('grade')])) {   // aftermarket → first ...
            $r->merge(['grade' => Presenter::GRADE_ALIASES[$r->input('grade')]]);
        }
        $r->validate(['per_page' => 'nullable|integer|min:1|max:50', 'grade' => 'nullable|in:'.implode(',', array_keys(Presenter::GRADES)),
            'sort' => 'nullable|in:relevance,price_asc,price_desc,newest']);
        $vehicle = $this->vehicleFor($r);

        // category = قسم رئيسي (يشمل فروعه) أو فرعي — id أو slug. subcategory يضيّق داخله
        $q = Part::with(['inventory', 'category', 'fitments'])->where('active', true)->search($r->input('q'))
            ->inCategory($r->input('subcategory') ?: $r->input('category'));
        if ($r->filled('grade')) {
            $q->where('grade', $r->input('grade'));
        }
        if ($r->boolean('in_stock')) {
            $q->whereHas('inventory', fn ($w) => $w->whereRaw('on_hand - reserved > 0'));
        }
        if ($r->boolean('fit_only') && $vehicle) {
            $q->fitting($vehicle->car_model_id, $vehicle->year ?: null);
        }
        match ($r->input('sort')) {
            'price_asc' => $q->orderBy('price'),
            'price_desc' => $q->orderByDesc('price'),
            'newest' => $q->orderByDesc('id'),
            default => $q->orderBy('name_ar')->orderBy('grade'),
        };

        $page = $q->paginate((int) $r->input('per_page', 20))->withQueryString();
        return [
            'data' => $page->getCollection()->map(fn ($p) => Presenter::part($p, $vehicle))->values(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage()],
            'vehicle' => $vehicle && $vehicle->carModel ? Presenter::vehicle($vehicle) : null,
        ];
    }

    public function part(Request $r, Part $part)
    {
        abort_unless($part->active, 404);
        $part->load(['inventory', 'category', 'fitments.carModel.make']);
        $p = Presenter::part($part, $this->vehicleFor($r), true);
        $u = $r->user('sanctum');
        $p['notify_me'] = $u ? StockAlert::where(['user_id' => $u->id, 'part_id' => $part->id])->exists() : false;
        return ['part' => $p];
    }

    /** «أعلمني عند التوفر» */
    public function notifyMe(Request $r, Part $part)
    {
        StockAlert::firstOrCreate(['user_id' => $r->user()->id, 'part_id' => $part->id]);
        return response()->json(['message' => 'سنخبرك فور توفر القطعة']);
    }

    public function cancelNotifyMe(Request $r, Part $part)
    {
        StockAlert::where(['user_id' => $r->user()->id, 'part_id' => $part->id])->delete();
        return response()->json(['message' => 'تم إلغاء التنبيه']);
    }
}
