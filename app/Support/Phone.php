<?php

namespace App\Support;

class Phone
{
    /** يحوّل الأرقام العربية/الفارسية إلى لاتينية */
    public static function latinDigits(string $v): string
    {
        return strtr($v, [
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        ]);
    }

    /** يرجّع الصيغة الدولية بدون + (مثل 966512345678) أو null لو الرقم غير صالح */
    public static function normalize(string $raw, string $defaultCc = '966'): ?string
    {
        $x = preg_replace('/[^\d+]/', '', self::latinDigits($raw));
        $x = ltrim($x, '+');
        if (str_starts_with($x, '00')) {
            $x = substr($x, 2);
        }
        $countries = config('dp.countries');

        foreach ($countries as $cc => $rule) {
            if (str_starts_with($x, $cc) && strlen($x) === strlen($cc) + $rule['len']) {
                return self::valid($cc, substr($x, strlen($cc)), $rule) ? $x : null;
            }
        }
        $rule = $countries[$defaultCc] ?? null;
        if (!$rule) {
            return null;
        }
        if ($rule['start'] !== null && str_starts_with($x, '0')) {
            $x = substr($x, 1);
        }
        return self::valid($defaultCc, $x, $rule) ? $defaultCc.$x : null;
    }

    private static function valid(string $cc, string $national, array $rule): bool
    {
        if (!ctype_digit($national) || strlen($national) !== $rule['len']) {
            return false;
        }
        return $rule['start'] === null || $national[0] === $rule['start'];
    }

    /** 9665•••5678 — لإخفاء رقم العميل في التقارير */
    public static function mask(?string $phone): string
    {
        if (!$phone || strlen($phone) < 8) {
            return (string) $phone;
        }
        return substr($phone, 0, 5).str_repeat('•', max(strlen($phone) - 8, 3)).substr($phone, -3);
    }
}
