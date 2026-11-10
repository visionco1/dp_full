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
    public function categories()
    {
        return ['categories' => Category::orderBy('sort')->get(['id', 'slug', 'name_ar', 'name_en', 'image'])
            ->map(fn ($c) => ['id' => $c->id, 'slug' => $c->slug, 'name_ar' => $c->name_ar, 'name_en' => $c->name_en, 'image' => $c->imageUrl()])];
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
        $r->validate(['per_page' => 'nullable|integer|min:1|max:50', 'grade' => 'nullable|in:oem,cert,first',
            'sort' => 'nullable|in:relevance,price_asc,price_desc,newest']);
        $vehicle = $this->vehicleFor($r);

        $q = Part::with(['inventory', 'category', 'fitments'])->where('active', true)->search($r->input('q'));
        if ($r->filled('category')) {
            $c = $r->input('category');
            $q->whereHas('category', fn ($w) => $w->where('slug', $c)->orWhere('id', is_numeric($c) ? $c : 0));
        }
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
