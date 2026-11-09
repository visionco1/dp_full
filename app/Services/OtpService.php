<?php

namespace App\Services;

use App\Contracts\SmsGateway;
use App\Models\OtpCode;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function __construct(private SmsGateway $sms) {}

    private function hash(string $phone, string $code): string
    {
        return hash_hmac('sha256', $phone.'|'.$code, config('app.key'));
    }

    /** @return string|null الرمز (فقط عند تفعيل وضع التجربة) */
    public function request(string $phone, ?array $payload = null): ?string
    {
        $wait = (int) config('dp.otp.resend_seconds');
        $last = OtpCode::where('phone', $phone)->whereNull('consumed_at')->latest('id')->first();
        if ($last && $last->created_at->diffInSeconds(now(), false) < $wait) {
            $retry = $wait - (int) $last->created_at->diffInSeconds(now(), false);
            throw new HttpResponseException(response()->json([
                'message' => "يمكنك طلب رمز جديد بعد {$retry} ثانية",
                'retry_after' => $retry,
            ], 429));
        }
        OtpCode::where('phone', $phone)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $len = (int) config('dp.otp.length');
        $code = str_pad((string) random_int(0, (10 ** $len) - 1), $len, '0', STR_PAD_LEFT);
        OtpCode::create([
            'phone' => $phone,
            'code_hash' => $this->hash($phone, $code),
            'expires_at' => now()->addMinutes((int) config('dp.otp.ttl_minutes')),
            'payload' => $payload,
        ]);
        $this->sms->send($phone, "رمز التحقق الخاص بك في دفع المحركات: {$code}");

        return config('dp.otp.debug') ? $code : null;
    }

    /** يتحقق من الرمز ويرجّع الـ payload المعلّق (إن وُجد). يرمي ValidationException عند الخطأ */
    public function verify(string $phone, string $code): array
    {
        $max = (int) config('dp.otp.max_attempts');
        $row = OtpCode::where('phone', $phone)->whereNull('consumed_at')->latest('id')->first();
        if (!$row || $row->expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => 'انتهت صلاحية الرمز — اطلب رمزاً جديداً']);
        }
        if ($row->attempts >= $max) {
            throw ValidationException::withMessages(['code' => 'تجاوزت عدد المحاولات — اطلب رمزاً جديداً']);
        }
        $row->increment('attempts');
        if (!hash_equals($row->code_hash, $this->hash($phone, $code))) {
            $left = $max - $row->attempts;
            throw ValidationException::withMessages(['code' => $left > 0
                ? 'الرمز غير صحيح — تبقّت '.($left === 1 ? 'محاولة واحدة' : 'محاولتان')
                : 'تجاوزت عدد المحاولات — اطلب رمزاً جديداً']);
        }
        $row->update(['consumed_at' => now()]);
        return $row->payload ?? [];
    }
}
