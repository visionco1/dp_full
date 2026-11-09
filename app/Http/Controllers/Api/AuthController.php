<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Presenter;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\CartService;
use App\Services\OtpService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private OtpService $otp, private CartService $carts) {}

    private function phoneOrFail(Request $r): string
    {
        $raw = Phone::latinDigits((string) $r->input('phone'));
        $p = Phone::normalize($raw, (string) $r->input('country_code', '966'));
        if (!$p) {
            throw ValidationException::withMessages(['phone' => 'برجاء إدخال رقم جوال صحيح (10 أرقام)']);
        }
        return $p;
    }

    /** تسجيل حساب جديد: يحفظ البيانات مؤقتاً ويرسل رمز التحقق. الحساب لا يُنشأ إلا بعد التحقق */
    public function register(Request $r)
    {
        $r->validate([
            'type' => 'required|in:individual,business',
            'name' => 'required|string|max:80',
            'city' => 'required|string|max:60',
            'email' => 'nullable|email|max:120',
            'terms' => 'accepted',
            'business_name' => 'required_if:type,business|nullable|string|max:120',
            'activity' => 'nullable|in:workshop,center,shop',
            'cr_number' => ['required_if:type,business', 'nullable', 'regex:/^\d{10}$/'],
            'vat_number' => ['required_if:type,business', 'nullable', 'regex:/^3\d{13}3$/'],
        ], [
            'cr_number.regex' => 'رقم السجل التجاري 10 أرقام',
            'vat_number.regex' => 'الرقم الضريبي 15 رقماً يبدأ وينتهي بالرقم 3',
        ]);
        $phone = $this->phoneOrFail($r);
        if (User::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => 'هذا الرقم لديه حساب — سجّل الدخول']);
        }
        $payload = $r->only(['type', 'name', 'city', 'email', 'business_name', 'activity', 'cr_number', 'vat_number']);
        $code = $this->otp->request($phone, $payload);
        return response()->json(['message' => 'أرسلنا رمز التحقق', 'resend_after' => config('dp.otp.resend_seconds')] + ($code ? ['debug_code' => $code] : []));
    }

    public function requestOtp(Request $r)
    {
        $r->validate(['phone' => 'required']);
        $phone = $this->phoneOrFail($r);
        $user = User::where('phone', $phone)->first();
        if ($user && ($user->is_staff || !$user->active)) {
            throw ValidationException::withMessages(['phone' => 'لا يمكن تسجيل الدخول بهذا الرقم']);
        }
        $code = $this->otp->request($phone);   // نفس الاستجابة سواء كان الحساب موجوداً أم لا
        return response()->json(['message' => 'أرسلنا رمز التحقق', 'resend_after' => config('dp.otp.resend_seconds')] + ($code ? ['debug_code' => $code] : []));
    }

    public function verifyOtp(Request $r)
    {
        $r->validate(['phone' => 'required', 'code' => 'required|digits:4', 'device_name' => 'nullable|string|max:60']);
        $phone = $this->phoneOrFail($r);
        $payload = $this->otp->verify($phone, Phone::latinDigits((string) $r->input('code')));

        $user = User::where('phone', $phone)->first();
        if (!$user) {
            if (!$payload) {
                throw ValidationException::withMessages(['phone' => 'لا يوجد حساب بهذا الرقم — أنشئ حساباً جديداً']);
            }
            $user = User::create([
                'phone' => $phone, 'name' => $payload['name'], 'city' => $payload['city'] ?? null, 'email' => $payload['email'] ?? null,
                'type' => $payload['type'], 'business_name' => $payload['business_name'] ?? null, 'activity' => $payload['activity'] ?? null,
                'cr_number' => $payload['cr_number'] ?? null, 'vat_number' => $payload['vat_number'] ?? null,
                'business_status' => $payload['type'] === 'business' ? 'pending' : null,
            ]);
        }
        if ($user->is_staff || !$user->active) {
            throw ValidationException::withMessages(['phone' => 'لا يمكن تسجيل الدخول بهذا الرقم']);
        }
        $user->forceFill(['phone_verified_at' => $user->phone_verified_at ?? now(), 'deletion_requested_at' => null])->save();

        // ربط الطلبات السابقة التي أنشأها نفس الرقم كضيف + دمج سلة الضيف
        \App\Models\Order::whereNull('user_id')->where('customer_phone', $phone)->update(['user_id' => $user->id]);
        $this->carts->merge($user, $r->header('X-Cart-Token'));

        $token = $user->createToken($r->input('device_name', 'mobile'))->plainTextToken;
        return response()->json(['token' => $token, 'token_type' => 'Bearer', 'user' => Presenter::user($user)]);
    }

    public function me(Request $r)
    {
        return ['user' => Presenter::user($r->user())];
    }

    public function update(Request $r)
    {
        $d = $r->validate([
            'name' => 'sometimes|string|max:80',
            'email' => 'sometimes|nullable|email|max:120|unique:users,email,'.$r->user()->id,
            'city' => 'sometimes|nullable|string|max:60',
        ]);
        $r->user()->update($d);
        return ['user' => Presenter::user($r->user()->fresh())];
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'تم تسجيل الخروج']);   // السلة تبقى محفوظة في حساب المستخدم
    }

    public function logoutOthers(Request $r)
    {
        $r->user()->tokens()->where('id', '!=', $r->user()->currentAccessToken()->id)->delete();
        return response()->json(['message' => 'تم تسجيل الخروج من الأجهزة الأخرى']);
    }

    public function devices(Request $r)
    {
        $cur = $r->user()->currentAccessToken()->id;
        return ['devices' => $r->user()->tokens->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'last_used_at' => $t->last_used_at?->toIso8601String(), 'current' => $t->id === $cur])->values()];
    }

    /** طلب حذف الحساب: يُسجَّل الطلب وتُنفَّذ المراجعة من الإدارة. الطلبات الجارية لا تتأثر */
    public function destroy(Request $r)
    {
        $r->user()->update(['deletion_requested_at' => now()]);
        return response()->json(['message' => 'استلمنا طلب حذف الحساب وسنؤكده برسالة نصية']);
    }

    public function deviceToken(Request $r)
    {
        $d = $r->validate(['token' => 'required|string|max:400', 'platform' => 'nullable|in:android,ios']);
        DeviceToken::updateOrCreate(['token' => $d['token']], ['user_id' => $r->user()->id, 'platform' => $d['platform'] ?? 'android']);
        return response()->json(['message' => 'تم']);
    }
}
