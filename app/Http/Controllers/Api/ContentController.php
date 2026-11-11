<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Presenter;
use App\Models\BankAccount;
use App\Models\Order;
use App\Models\Page;
use App\Models\ReturnRequest;

class ContentController extends Controller
{
    /** GET /pages/{terms|privacy|returns-policy} */
    public function page(string $slug)
    {
        $p = Page::where('slug', $slug)->firstOrFail();
        return ['slug' => $p->slug, 'title' => $p->title, 'content' => $p->content, 'updated_at' => $p->updated_at?->toIso8601String()];
    }

    /** GET /bank-accounts — الحساب الأساسي في الجذر (توافق مع الشكل المطلوب) + كل الحسابات في accounts */
    public function bankAccounts()
    {
        $accounts = self::accounts();
        return ($accounts->first() ?? ['bank_name' => null, 'beneficiary_name' => null, 'iban' => null, 'account_number' => null]) + ['accounts' => $accounts];
    }

    public static function accounts(): \Illuminate\Support\Collection
    {
        return BankAccount::where('active', true)->orderBy('sort')->orderBy('id')->get()->map->toApi()->values();
    }

    /** GET /lookups — القيم المعتمدة في السيرفر لكل القوائم في التطبيق */
    public function lookups()
    {
        $kv = fn (array $a) => collect($a)->map(fn ($label, $code) => ['code' => $code, 'label_ar' => $label])->values();
        return [
            'business_activities' => $kv(Presenter::ACTIVITIES),
            'account_types' => $kv(['individual' => 'أفراد', 'business' => 'منشأة']),
            'grades' => $kv(Presenter::GRADES),
            'sides' => $kv(['R' => 'يمين', 'L' => 'يسار']),
            'fulfillment' => $kv(Order::FULFILLMENT),
            'payment_methods' => $kv(['cod' => 'الدفع عند الاستلام', 'bank' => 'تحويل بنكي مباشر']),
            'return_actions' => $kv(ReturnRequest::ACTIONS),
            'return_reasons' => ['القطعة غير مطابقة لسيارتي', 'القطعة تالفة أو بها عيب', 'وصلت قطعة مختلفة عن المطلوبة', 'لم أعد بحاجة للقطعة', 'أخرى'],
            'return_window_days' => (int) config('dp.return_days'),
            'order_statuses' => $kv(config('dp.order_statuses')),
        ];
    }
}
