<?php

namespace App\Support;

use App\Models\Order;

/** رمز QR للفاتورة الضريبية المبسّطة حسب متطلبات هيئة الزكاة (المرحلة الأولى): TLV ثم Base64 */
class Zatca
{
    public static function tlv(string $seller, string $vatNumber, string $timestamp, string $total, string $vat): string
    {
        $out = '';
        foreach ([1 => $seller, 2 => $vatNumber, 3 => $timestamp, 4 => $total, 5 => $vat] as $tag => $value) {
            $out .= chr($tag).chr(strlen($value)).$value;   // الطول بالبايت (UTF-8)
        }
        return base64_encode($out);
    }

    public static function forOrder(Order $o): string
    {
        return self::tlv(
            (string) config('dp.seller_name'),
            (string) config('dp.seller_vat_number'),
            $o->placed_at->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            number_format($o->total, 2, '.', ''),
            number_format($o->vat, 2, '.', ''),
        );
    }
}
