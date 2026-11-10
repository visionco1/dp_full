<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Presenter;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Faq;
use App\Models\OrderItem;
use App\Models\Part;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /** شاشة الـ Home في التطبيق — عامة، ومع token تتخصص للمستخدم (العنوان، الإشعارات، السيارة النشطة) */
    public function home(Request $r)
    {
        $user = $r->user('sanctum');
        $vehicle = $user?->vehicles()->with('carModel.make')->where('is_active', true)->first();
        $address = $user?->addresses()->orderByDesc('is_default')->orderBy('id')->first();
        $categories = Category::orderBy('sort')->orderBy('id')->get(['id', 'slug', 'name_ar', 'name_en', 'image']);

        // قطع مقترحة: المتوافقة مع السيارة النشطة أولاً، وإلا الأكثر طلباً، والمتوفرة فقط
        $popularIds = OrderItem::whereNotNull('part_id')->where('created_at', '>=', now()->subDays(90))
            ->groupBy('part_id')->orderByRaw('SUM(qty) DESC')->limit(30)->pluck('part_id');
        $q = Part::with(['inventory', 'category', 'fitments'])->where('active', true)
            ->whereHas('inventory', fn ($w) => $w->whereRaw('on_hand - reserved > 0'));
        $vehicle && $q->fitting($vehicle->car_model_id, $vehicle->year ?: null);
        if ($popularIds->isNotEmpty()) {   // ترتيب حسب الأكثر طلباً (CASE يعمل على MySQL و SQLite)
            $case = $popularIds->map(fn ($id, $i) => 'WHEN '.(int) $id.' THEN '.$i)->implode(' ');
            $q->orderByRaw("CASE parts.id $case ELSE 9999 END");
        }
        $suggested = $q->orderByDesc('id')->limit(10)->get();
        if ($suggested->isEmpty() && $vehicle) {   // لا يوجد متوافق متوفر → اقترح العام
            $suggested = Part::with(['inventory', 'category', 'fitments'])->where('active', true)
                ->whereHas('inventory', fn ($w) => $w->whereRaw('on_hand - reserved > 0'))->orderByDesc('id')->limit(10)->get();
        }

        $suggestions = collect(config('dp.search_suggestions', []))
            ->merge(Part::whereIn('id', $popularIds->take(5))->pluck('name_ar'))
            ->unique()->take(8)->values();

        return [
            'delivery_address' => $address ? collect([$address->city, trim(\Illuminate\Support\Str::before((string) $address->line, '،'))])->filter()->implode('، ') : null,
            'address_id' => $address?->id,
            'notification_count' => $user ? $user->appNotifications()->whereNull('read_at')->count() : 0,
            'active_vehicle' => $vehicle ? Presenter::vehicle($vehicle) : null,
            'banners' => Banner::live('hero')->get()->map(fn ($b) => Presenter::banner($b, $categories))->values(),
            'ads' => Banner::live()->whereIn('placement', ['strip', 'side', 'topbar'])->get()->map(fn ($b) => Presenter::banner($b, $categories))->values(),
            'search_suggestions' => $suggestions,
            'categories' => CatalogController::categoryTree(),
            'suggested_products' => $suggested->map(fn ($p) => Presenter::part($p, $vehicle))->values(),
        ];
    }

    /** الأسئلة الشائعة + بيانات التواصل لشاشة الدعم */
    public function faqs()
    {
        return [
            'faqs' => Faq::where('active', true)->orderBy('sort')->orderBy('id')->get(['id', 'category', 'question', 'answer']),
            'contact' => config('dp.support'),
        ];
    }
}
