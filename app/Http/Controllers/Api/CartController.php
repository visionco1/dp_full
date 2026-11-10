<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Part;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(private CartService $carts) {}

    public function show(Request $r)
    {
        return $this->carts->summary($this->carts->resolve($r), $r->query('fulfillment') === 'pickup' ? 'pickup' : 'delivery');
    }

    public function add(Request $r)
    {
        $d = $r->validate(['part_id' => 'required|exists:parts,id', 'qty' => 'nullable|integer|min:1|max:99']);
        $part = Part::with('inventory')->where('active', true)->findOrFail($d['part_id']);
        $cart = $this->carts->resolve($r);
        $row = $cart->items()->where('part_id', $part->id)->first();
        $qty = ($row?->qty ?? 0) + ($d['qty'] ?? 1);
        if ($part->available < $qty) {
            throw ValidationException::withMessages(['qty' => $part->available > 0 ? "المتاح من هذه القطعة {$part->available} فقط" : 'القطعة غير متوفرة حالياً']);
        }
        $row ? $row->update(['qty' => $qty]) : $cart->items()->create(['part_id' => $part->id, 'qty' => $qty]);
        return response()->json($this->carts->summary($cart), 201);
    }

    public function update(Request $r, int $item)
    {
        $d = $r->validate(['qty' => 'required|integer|min:1|max:99']);
        $cart = $this->carts->resolve($r, false);
        $row = $cart?->items()->with('part.inventory')->findOrFail($item);
        if ($row->part->available < $d['qty']) {
            throw ValidationException::withMessages(['qty' => "المتاح {$row->part->available} فقط"]);
        }
        $row->update(['qty' => $d['qty']]);
        return $this->carts->summary($cart);
    }

    public function remove(Request $r, int $item)
    {
        $cart = $this->carts->resolve($r, false);
        abort_unless($cart, 404);
        $cart->items()->where('id', $item)->delete();
        return $this->carts->summary($cart);
    }

    public function clear(Request $r)
    {
        $cart = $this->carts->resolve($r, false);
        $cart?->items()->delete();
        return $cart ? $this->carts->summary($cart) : response()->json(['items' => [], 'totals' => $this->carts->totals([])]);
    }
}
