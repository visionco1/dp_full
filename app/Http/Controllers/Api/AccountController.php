<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Presenter;
use App\Models\Address;
use App\Models\AppNotification;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    // ---- العناوين ----
    public function addresses(Request $r)
    {
        return ['addresses' => $r->user()->addresses()->orderByDesc('is_default')->orderBy('id')->get()->map(fn ($a) => Presenter::address($a))];
    }

    private function addressData(Request $r): array
    {
        $d = $r->validate([
            'label' => 'required|string|max:30', 'name' => 'required|string|max:80', 'phone' => 'required|string',
            'city' => 'required|string|max:60', 'line' => 'required|string|max:200',
            'national_address' => ['nullable', 'regex:/^[A-Za-z]{4}\d{4}$/'], 'is_default' => 'nullable|boolean',
        ], ['national_address.regex' => 'العنوان الوطني 4 حروف و4 أرقام مثل RNAA4021']);
        $d['phone'] = Phone::normalize($d['phone']) ?? throw ValidationException::withMessages(['phone' => 'برجاء إدخال رقم جوال صحيح (10 أرقام)']);
        return $d;
    }

    public function addAddress(Request $r)
    {
        $d = $this->addressData($r);
        $u = $r->user();
        $d['is_default'] = ($d['is_default'] ?? false) || !$u->addresses()->exists();
        if ($d['is_default']) {
            $u->addresses()->update(['is_default' => false]);
        }
        return response()->json(['address' => Presenter::address($u->addresses()->create($d))], 201);
    }

    public function updateAddress(Request $r, Address $address)
    {
        abort_unless($address->user_id === $r->user()->id, 404);
        $d = $this->addressData($r);
        if (!empty($d['is_default'])) {
            $r->user()->addresses()->update(['is_default' => false]);
        }
        $address->update($d);
        return ['address' => Presenter::address($address)];
    }

    public function deleteAddress(Request $r, Address $address)
    {
        abort_unless($address->user_id === $r->user()->id, 404);
        $was = $address->is_default;
        $address->delete();
        if ($was && ($n = $r->user()->addresses()->first())) {
            $n->update(['is_default' => true]);
        }
        return response()->json(['message' => 'تم حذف العنوان']);
    }

    // ---- الإشعارات ----
    public function notifications(Request $r)
    {
        $q = $r->user()->appNotifications()->latest('id');
        $page = $q->paginate(20);
        return [
            'data' => $page->getCollection()->map(fn ($n) => Presenter::notification($n))->values(),
            'unread' => $r->user()->appNotifications()->whereNull('read_at')->count(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ];
    }

    public function readNotification(Request $r, AppNotification $notification)
    {
        abort_unless($notification->user_id === $r->user()->id, 404);
        $notification->update(['read_at' => now()]);
        return ['ok' => true];
    }

    public function readAllNotifications(Request $r)
    {
        $r->user()->appNotifications()->whereNull('read_at')->update(['read_at' => now()]);
        return ['ok' => true];
    }

    // ---- الإعدادات ----
    public function settings(Request $r)
    {
        $u = $r->user();
        return ['settings' => ['locale' => $u->locale, 'theme' => $u->theme, 'notifications' => $u->prefs()]];
    }

    public function updateSettings(Request $r)
    {
        $d = $r->validate([
            'locale' => 'sometimes|in:ar,en', 'theme' => 'sometimes|in:light,dark,system',
            'notifications' => 'sometimes|array',
            'notifications.orders' => 'boolean', 'notifications.price' => 'boolean', 'notifications.stock' => 'boolean',
            'notifications.offers' => 'boolean', 'notifications.push' => 'boolean',
        ]);
        $u = $r->user();
        if (isset($d['notifications'])) {
            $d['notif_prefs'] = array_merge($u->prefs(), $d['notifications']);
            unset($d['notifications']);
        }
        $u->update($d);
        return $this->settings($r);
    }
}
