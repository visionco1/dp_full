<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** إدارة البانرات والإعلانات */
class BannerController extends Controller
{
    public function index(Request $r)
    {
        $banners = Banner::when($r->filled('placement'), fn ($q) => $q->where('placement', $r->input('placement')))
            ->orderBy('placement')->orderBy('sort')->orderByDesc('id')->get();
        return view('admin.banners.index', ['banners' => $banners, 'placements' => Banner::PLACEMENTS]);
    }

    private function data(Request $r, ?Banner $banner = null): array
    {
        $d = $r->validate([
            'placement' => ['required', Rule::in(array_keys(Banner::PLACEMENTS))],
            'title' => 'required|string|max:120',
            'subtitle' => 'nullable|string|max:250',
            'image' => 'nullable|image|max:4096',
            'link_url' => ['nullable', 'string', 'max:255', 'regex:#^(/|https?://)#i'],
            'button_text' => 'nullable|string|max:40',
            'bg_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort' => 'nullable|integer|min:0|max:999',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'remove_image' => 'nullable|boolean',
        ], ['ends_at.after_or_equal' => 'تاريخ الانتهاء يجب أن يكون بعد تاريخ البداية', 'bg_color.regex' => 'لون الخلفية بصيغة #RRGGBB',
            'link_url.regex' => 'الرابط يبدأ بـ / (صفحة داخلية) أو https://']);

        $d['active'] = $r->boolean('active');
        $d['sort'] = $d['sort'] ?? 0;
        $d['bg_color'] = $d['bg_color'] ?? '#111111';
        unset($d['image'], $d['remove_image']);

        if ($r->hasFile('image')) {
            $banner?->image && Storage::disk('public')->delete($banner->image);
            $d['image'] = $r->file('image')->store('banners', 'public');
        } elseif ($banner && $r->boolean('remove_image') && $banner->image) {
            Storage::disk('public')->delete($banner->image);
            $d['image'] = null;
        }
        return $d;
    }

    public function store(Request $r)
    {
        Banner::create($this->data($r));
        return back()->with('ok', 'تمت إضافة البانر');
    }

    public function update(Request $r, Banner $banner)
    {
        $banner->update($this->data($r, $banner));
        return back()->with('ok', 'تم حفظ البانر');
    }

    public function toggle(Banner $banner)
    {
        $banner->update(['active' => !$banner->active]);
        return back()->with('ok', $banner->active ? 'تم تفعيل البانر' : 'تم إيقاف البانر');
    }

    public function destroy(Banner $banner)
    {
        $banner->image && Storage::disk('public')->delete($banner->image);
        $banner->delete();
        return back()->with('ok', 'تم حذف البانر');
    }
}
