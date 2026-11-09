<?php

namespace App\Support;

class Money
{
    public static function round(float $v): float
    {
        return round($v + 0.0000001, 2);
    }

    public static function fmt(float|string|null $v): string
    {
        return number_format((float) $v, 2);
    }

    /** الضريبة المضمّنة في مبلغ شامل 15% */
    public static function vatIn(float $gross): float
    {
        $r = (float) config('dp.vat_rate');
        return self::round($gross * $r / (1 + $r));
    }
}
