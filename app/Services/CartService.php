<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Part;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CartService
{
    /** سلة المستخدم المسجّل أو سلة الضيف عبر الهيدر X-Cart-Token */
    public function resolve(Request $request, bool $create = true): ?Cart
    {
        $user = $request->user('sanctum');
        if ($user) {
            return Cart::firstOrCreate(['user_id' => $user->id]);
        }
        $token = $request->header('X-Cart-Token');
        if ($token && ($cart = Cart::where('token', $token)->whereNull('user_id')->first())) {
            return $cart;
        }
        return $create ? Cart::create(['token' => Str::random(40)]) : null;
    }

    /** دمج سلة الضيف في سلة الحساب بعد الدخول */
    public function merge(User $user, ?string $guestToken): void
    {
        if (!$guestToken) {
            return;
        }
        $guest = Cart::where('token', $guestToken)->whereNull('user_id')->first();
        if (!$guest) {
            return;
        }
        $mine = Cart::firstOrCreate(['user_id' => $user->id]);
        foreach ($guest->items as $it) {
            $row = $mine->items()->where('part_id', $it->part_id)->first();
            $row ? $row->update(['qty' => min(99, $row->qty + $it->qty)]) : $mine->items()->create(['part_id' => $it->part_id, 'qty' => $it->qty]);
        }
        $guest->delete();
    }

    /** إجماليات السلة. الأسعار شاملة الضريبة */
    public function totals(iterable $lines, string $fulfillment = 'delivery'): array
    {
        $subtotal = 0.0;
        $ship = 0.0;
        $count = 0;
        foreach ($lines as [$part, $qty]) {
            $subtotal += $part->price * $qty;
            $ship += ($part->is_bulky ? $part->shipping_fee : 0) * $qty;
            $count += $qty;
        }
        $shipping = $count && $fulfillment !== 'pickup' ? Money::round((float) config('dp.base_shipping') + $ship) : 0.0;
        $subtotal = Money::round($subtotal);
        $total = Money::round($subtotal + $shipping);
        return [
            'items_count' => $count,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'vat' => Money::vatIn($total),
            'net' => Money::round($total - Money::vatIn($total)),
            'total' => $total,
        ];
    }

    /** ملخص السلة. التوفر لفرع الطلب الحالي (X-Branch-Id)، و fulfillment=pickup يلغي الشحن */
    public function summary(Cart $cart, string $fulfillment = 'delivery'): array
    {
        $cart->load('items.part.inventory');
        $lines = [];
        $items = [];
        foreach ($cart->items as $it) {
            if (!$it->part || !$it->part->active) {
                continue;
            }
            $lines[] = [$it->part, $it->qty];
            $p = $it->part;
            $items[] = [
                'id' => $it->id,
                'part_id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name_ar,
                'grade' => $p->grade,
                'side' => $p->side,
                'image' => $p->image ? asset('storage/'.$p->image) : null,
                'unit_price' => $p->price,
                'qty' => $it->qty,
                'line_total' => Money::round($p->price * $it->qty),
                'available' => $p->available,
                'in_stock' => $p->available >= $it->qty,
                'shipping_fee' => $p->is_bulky ? $p->shipping_fee : 0,
            ];
        }
        return ['token' => $cart->token, 'branch_id' => \App\Support\BranchContext::current(), 'fulfillment' => $fulfillment,
            'items' => $items, 'totals' => $this->totals($lines, $fulfillment), 'has_issues' => collect($items)->contains(fn ($i) => !$i['in_stock'])];
    }
}
