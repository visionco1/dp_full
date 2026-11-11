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

    /**
     * تعديل سطر السلة: الكمية و/أو التبديل لبديل من نفس القطعة (نفس group_key) بدرجة أو جانب مختلف.
     * PATCH { qty?, grade?: oem|cert|first, side?: R|L }
     */
    public function update(Request $r, int $item)
    {
        if (isset(\App\Http\Presenter::GRADE_ALIASES[$r->input('grade')])) {
            $r->merge(['grade' => \App\Http\Presenter::GRADE_ALIASES[$r->input('grade')]]);
        }
        $d = $r->validate([
            'qty' => 'required_without_all:grade,side|integer|min:1|max:99',
            'grade' => 'nullable|in:'.implode(',', array_keys(\App\Http\Presenter::GRADES)),
            'side' => 'nullable|in:R,L',
        ]);
        $cart = $this->carts->resolve($r, false);
        abort_unless($cart, 404);
        $row = $cart->items()->with('part.inventory')->findOrFail($item);
        $part = $row->part;
        $qty = (int) ($d['qty'] ?? $row->qty);

        $grade = $d['grade'] ?? $part->grade;
        $side = array_key_exists('side', $d) && $d['side'] !== null ? $d['side'] : $part->side;
        if ($grade !== $part->grade || $side !== $part->side) {
            $target = $part->group_key ? Part::with('inventory')->where('group_key', $part->group_key)->where('active', true)
                ->where('grade', $grade)->where(fn ($q) => $side ? $q->where('side', $side) : $q->whereNull('side'))->first() : null;
            if (!$target) {
                throw ValidationException::withMessages(['grade' => 'هذا البديل غير متوفر لهذه القطعة']);
            }
            // لو البديل موجود أصلاً في السلة ندمج الكمية
            $existing = $cart->items()->where('part_id', $target->id)->where('id', '!=', $row->id)->first();
            $total = $qty + ($existing?->qty ?? 0);
            if ($target->available < $total) {
                throw ValidationException::withMessages(['qty' => $target->available > 0 ? "المتاح من البديل {$target->available} فقط" : 'البديل غير متوفر حالياً في هذا الفرع']);
            }
            if ($existing) {
                $existing->update(['qty' => min(99, $total)]);
                $row->delete();
            } else {
                $row->update(['part_id' => $target->id, 'qty' => $qty]);
            }
            return $this->carts->summary($cart);
        }

        if ($part->available < $qty) {
            throw ValidationException::withMessages(['qty' => "المتاح {$part->available} فقط"]);
        }
        $row->update(['qty' => $qty]);
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
