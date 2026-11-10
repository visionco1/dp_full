<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;

/** إدارة الأسئلة الشائعة (تظهر في شاشة الدعم بالتطبيق) */
class FaqController extends Controller
{
    public function index()
    {
        return view('admin.faqs.index', ['faqs' => Faq::orderBy('sort')->orderBy('id')->get()]);
    }

    private function data(Request $r): array
    {
        return $r->validate([
            'category' => 'nullable|string|max:40',
            'question' => 'required|string|max:250',
            'answer' => 'required|string|max:3000',
            'sort' => 'nullable|integer|min:0|max:999',
        ]) + ['active' => $r->boolean('active'), 'sort' => (int) $r->input('sort', 0)];
    }

    public function store(Request $r)
    {
        Faq::create($this->data($r));
        return back()->with('ok', 'تمت إضافة السؤال');
    }

    public function update(Request $r, Faq $faq)
    {
        $faq->update($this->data($r));
        return back()->with('ok', 'تم حفظ السؤال');
    }

    public function destroy(Faq $faq)
    {
        $faq->delete();
        return back()->with('ok', 'تم حذف السؤال');
    }
}
