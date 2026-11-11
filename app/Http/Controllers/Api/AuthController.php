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
        if (isset(Presenter::ACTIVITY_ALIASES[$r->input('activity')])) {   // service_center → center ...
            $r->merge(['activity' => Presenter::ACTIVITY_ALIASES[$r->input('activity')]]);
        }
        $r->validate([
            'type' => 'required|in:individual,business',
            'name' => 'required|string|max:80',
            'city' => 'required|string|max:60',
            'email' => 'nullable|email|max:120',
            'terms' => 'accepted',
            'business_name' => 'required_if:type,business|nullable|string|max:120',
            'activity' => 'nullable|in:'.implode(',', array_keys(Presenter::ACTIVITIES)),
            // مستند السجل التجاري (اختياري) — multipart/form-data
            'cr_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'cr_number' => ['required_if:type,business', 'nullable', 'regex:/^\d{10}$/'],
            'vat_number' => ['required_if:type,business', 'nullable', 'regex:/^3\d{13}3$/'],
        ], [
            'cr_number.regex' => 'رقم السجل التجاري 10 أرقام',
            'vat_number.regex' => 'الرقم الضريبي 15 رقماً يبدأ وينتهي بالرقم 3',
            'activity.in' => 'نوع النشاط: workshop أو center أو shop',
            'cr_file.mimes' => 'مستند السجل التجاري PDF أو صورة', 'cr_file.max' => 'حجم المستند حتى 5 ميجابايت',
        ]);
        $phone = $this->phoneOrFail($r);
        if (User::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => 'هذا الرقم لديه حساب — سجّل الدخول']);
        }
        $payload = $r->only(['type', 'name', 'city', 'email', 'business_name', 'activity', 'cr_number', 'vat_number']) + ['purpose' => 'register'];
        if ($r->hasFile('cr_file') && $r->input('type') === 'business') {   // تخزين خاص (غير عام) حتى يراجعه الفريق
            $payload['cr_document'] = $r->file('cr_file')->store('cr-documents', 'local');
        }
        $code = $this->otp->request($phone, $payload);
        return response()->json(['message' => 'أرسلنا رمز التحقق', 'resend_after' => config('dp.otp.resend_seconds')] + ($code ? ['debug_code' => $code] : []));
    }

    public function requestOtp(Request $r)
    {
        $r->validate(['phone' => 'required', 'type' => 'nullable|in:individual,business']);
        $phone = $this->phoneOrFail($r);
        $user = User::where('phone', $phone)->first();
        if ($user && ($user->is_staff || !$user->active)) {
            throw ValidationException::withMessages(['phone' => 'لا يمكن تسجيل الدخول بهذا الرقم']);
        }
        // type اختياري: لو أرسله التطبيق ونوع الحساب مختلف نرفض مبكراً برسالة واضحة
        if ($user && $r->filled('type') && $user->type !== $r->input('type')) {
            throw ValidationException::withMessages(['type' => $user->type === 'business'
                ? 'هذا الرقم مسجّل كحساب منشأة — اختر تسجيل دخول المنشآت'
                : 'هذا الرقم مسجّل كحساب أفراد — اختر تسجيل دخول الأفراد']);
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
            if (($payload['purpose'] ?? 'register') !== 'register' || empty($payload['type'])) {
                throw ValidationException::withMessages(['phone' => 'لا يوجد حساب بهذا الرقم — أنشئ حساباً جديداً']);
            }
            $user = User::create([
                'phone' => $phone, 'name' => $payload['name'], 'city' => $payload['city'] ?? null, 'email' => $payload['email'] ?? null,
                'type' => $payload['type'], 'business_name' => $payload['business_name'] ?? null, 'activity' => $payload['activity'] ?? null,
                'cr_number' => $payload['cr_number'] ?? null, 'vat_number' => $payload['vat_number'] ?? null,
                'business_status' => $payload['type'] === 'business' ? 'pending' : null,
                'cr_document' => $payload['cr_document'] ?? null,
            ])->refresh();   // تحميل القيم الافتراضية من قاعدة البيانات (active...) قبل التحقق أدناه
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
        return ['user' => Presenter::user($r->user(), true)];
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

    /** إنهاء جلسة جهاز محدد (id من GET /me/devices). حذف الجهاز الحالي = تسجيل خروج */
    public function deleteDevice(Request $r, int $device)
    {
        $token = $r->user()->tokens()->whereKey($device)->firstOrFail();
        $current = $token->id === $r->user()->currentAccessToken()->id;
        $token->delete();
        return response()->json(['message' => $current ? 'تم تسجيل الخروج من هذا الجهاز' : 'تم إنهاء جلسة الجهاز', 'current' => $current]);
    }

    /** تغيير الجوال — الخطوة 1: رمز تحقق للرقم الجديد */
    public function phoneChangeRequest(Request $r)
    {
        $r->validate(['new_phone' => 'required']);
        $new = $this->normalizeOrFail((string) $r->input('new_phone'), 'new_phone', (string) $r->input('country_code', '966'));
        if ($new === $r->user()->phone) {
            throw ValidationException::withMessages(['new_phone' => 'هذا هو رقمك الحالي']);
        }
        if (User::where('phone', $new)->exists()) {
            throw ValidationException::withMessages(['new_phone' => 'هذا الرقم مستخدم في حساب آخر']);
        }
        $code = $this->otp->request($new, ['purpose' => 'change_phone', 'user_id' => $r->user()->id]);
        return response()->json(['message' => 'أرسلنا رمز التحقق إلى الرقم الجديد', 'resend_after' => config('dp.otp.resend_seconds')] + ($code ? ['debug_code' => $code] : []));
    }

    /** تغيير الجوال — الخطوة 2: تأكيد الرمز وتحديث الرقم */
    public function phoneChangeVerify(Request $r)
    {
        $r->validate(['new_phone' => 'required', 'code' => 'required|digits:4']);
        $new = $this->normalizeOrFail((string) $r->input('new_phone'), 'new_phone', (string) $r->input('country_code', '966'));
        $payload = $this->otp->verify($new, Phone::latinDigits((string) $r->input('code')));
        if (($payload['purpose'] ?? null) !== 'change_phone' || (int) ($payload['user_id'] ?? 0) !== $r->user()->id) {
            throw ValidationException::withMessages(['code' => 'الرمز غير صالح لهذا الطلب — اطلب رمزاً جديداً']);
        }
        if (User::where('phone', $new)->exists()) {
            throw ValidationException::withMessages(['new_phone' => 'هذا الرقم مستخدم في حساب آخر']);
        }
        $r->user()->forceFill(['phone' => $new, 'phone_verified_at' => now()])->save();
        return ['message' => 'تم تغيير رقم الجوال', 'user' => Presenter::user($r->user()->fresh(), true)];
    }

    private function normalizeOrFail(string $raw, string $field, string $cc = '966'): string
    {
        return Phone::normalize(Phone::latinDigits($raw), $cc) ?? throw ValidationException::withMessages([$field => 'برجاء إدخال رقم جوال صحيح (10 أرقام)']);
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
