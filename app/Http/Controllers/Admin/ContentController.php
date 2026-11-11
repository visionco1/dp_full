<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Page;
use Illuminate\Http\Request;

/** صفحات المحتوى (الشروط، الخصوصية، سياسة الإرجاع) + الحسابات البنكية للتحويل */
class ContentController extends Controller
{
    public function index()
    {
        return view('admin.content.index', [
            'pages' => Page::orderBy('id')->get(),
            'accounts' => BankAccount::orderBy('sort')->orderBy('id')->get(),
        ]);
    }

    public function updatePage(Request $r, Page $page)
    {
        $page->update($r->validate(['title' => 'required|string|max:150', 'content' => 'required|string|max:60000']));
        return back()->with('ok', 'تم حفظ «'.$page->title.'»');
    }

    private function account(Request $r): array
    {
        $d = $r->validate([
            'bank_name' => 'required|string|max:80',
            'beneficiary_name' => 'required|string|max:120',
            'iban' => ['required', 'string', 'regex:/^SA\d{22}$/'],
            'account_number' => 'nullable|string|max:30',
            'sort' => 'nullable|integer|min:0|max:999',
        ], ['iban.regex' => 'الآيبان السعودي يبدأ بـ SA ويليه 22 رقماً']);
        $d['iban'] = strtoupper(str_replace(' ', '', $d['iban']));
        return $d + ['active' => $r->boolean('active'), 'sort' => (int) ($d['sort'] ?? 0)];
    }

    public function storeAccount(Request $r)
    {
        $r->merge(['iban' => strtoupper(str_replace(' ', '', (string) $r->input('iban')))]);
        BankAccount::create($this->account($r));
        return back()->with('ok', 'تمت إضافة الحساب البنكي');
    }

    public function updateAccount(Request $r, BankAccount $account)
    {
        $r->merge(['iban' => strtoupper(str_replace(' ', '', (string) $r->input('iban')))]);
        $account->update($this->account($r));
        return back()->with('ok', 'تم حفظ الحساب البنكي');
    }

    public function destroyAccount(BankAccount $account)
    {
        $account->delete();
        return back()->with('ok', 'تم حذف الحساب البنكي');
    }
}
