<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Presenter;
use App\Models\Branch;
use App\Services\CartService;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /**
     * الفروع المفعّلة. مع سلة (X-Cart-Token أو مستخدم مسجّل) يرجع لكل فرع هل يقدر يغطي السلة كاملة والنواقص.
     * ?fulfillment=pickup|delivery يرجّع الفروع الداعمة لهذه الطريقة فقط.
     */
    public function index(Request $r, CartService $carts, InventoryService $stock)
    {
        $r->validate(['fulfillment' => 'nullable|in:delivery,pickup']);
        $branches = Branch::active()->get();
        if ($f = $r->query('fulfillment')) {
            $branches = $branches->filter(fn ($b) => $b->supports($f))->values();
        }

        $cart = $carts->resolve($r, false);
        $items = $cart ? $cart->items()->with('part:id,name_ar,sku,active')->get()->filter(fn ($i) => $i->part?->active) : collect();

        return ['branches' => $branches->map(function (Branch $b) use ($items, $stock) {
            $row = Presenter::branch($b);
            if ($items->isNotEmpty()) {
                $avail = $stock->availableAt($b->id, $items->pluck('part_id')->all());
                $missing = $items->filter(fn ($i) => ($avail[$i->part_id] ?? 0) < $i->qty)
                    ->map(fn ($i) => ['part_id' => $i->part_id, 'sku' => $i->part->sku, 'name' => $i->part->name_ar, 'qty' => $i->qty, 'available' => $avail[$i->part_id] ?? 0])->values();
                $row['cart'] = ['can_fulfill' => $missing->isEmpty(), 'missing' => $missing];
            }
            return $row;
        })->values()];
    }

    public function show(Branch $branch)
    {
        abort_unless($branch->active, 404);
        return ['branch' => Presenter::branch($branch)];
    }
}
