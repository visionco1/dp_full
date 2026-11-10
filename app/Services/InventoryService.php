<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Part;
use App\Models\StockAlert;
use App\Models\StockMovement;
use App\Support\BranchContext;
use Illuminate\Validation\ValidationException;

/**
 * المخزون لكل فرع. كل دالة تأخذ branchId اختيارياً؛ بدونه يُستخدم الفرع الحالي (BranchContext).
 */
class InventoryService
{
    public function __construct(private NotificationService $notify) {}

    private function branch(?int $branchId): int
    {
        return $branchId ?? BranchContext::current() ?? throw ValidationException::withMessages(['branch' => 'لا يوجد فرع مفعّل']);
    }

    public function row(int $partId, ?int $branchId = null, bool $lock = false): Inventory
    {
        $b = $this->branch($branchId);
        $q = Inventory::where('part_id', $partId)->where('branch_id', $b);
        if ($lock) {
            $q->lockForUpdate();
        }
        return $q->first() ?? Inventory::create(['part_id' => $partId, 'branch_id' => $b]);
    }

    public function log(int $partId, int $qty, string $type, ?string $ref = null, ?int $userId = null, ?int $branchId = null): void
    {
        StockMovement::create(['part_id' => $partId, 'branch_id' => $this->branch($branchId), 'qty' => $qty, 'type' => $type, 'reference' => $ref, 'user_id' => $userId]);
    }

    /** تعديل الكمية الفعلية (جرد/استيراد/مرتجع/تحويل) */
    public function adjust(int $partId, int $delta, string $type = 'adjust', ?string $ref = null, ?int $userId = null, ?int $branchId = null): Inventory
    {
        $b = $this->branch($branchId);
        $inv = $this->row($partId, $b, true);
        $new = $inv->on_hand + $delta;
        if ($new < 0) {
            throw ValidationException::withMessages(['qty' => 'الكمية لا يمكن أن تكون أقل من صفر']);
        }
        $was = $inv->available;
        $inv->update(['on_hand' => $new]);
        $this->log($partId, $delta, $type, $ref, $userId, $b);
        if ($was === 0 && $inv->fresh()->available > 0) {
            $this->notifyRestock($partId);
        }
        return $inv;
    }

    public function setOnHand(int $partId, int $qty, string $type = 'adjust', ?string $ref = null, ?int $userId = null, ?int $branchId = null): Inventory
    {
        $b = $this->branch($branchId);
        return $this->adjust($partId, $qty - $this->row($partId, $b)->on_hand, $type, $ref, $userId, $b);
    }

    /** تحويل كمية بين فرعين */
    public function transfer(int $partId, int $fromBranch, int $toBranch, int $qty, ?int $userId = null): void
    {
        if ($fromBranch === $toBranch || $qty < 1) {
            throw ValidationException::withMessages(['qty' => 'اختر فرعين مختلفين وكمية صحيحة']);
        }
        $from = $this->row($partId, $fromBranch, true);
        if ($from->available < $qty) {
            throw ValidationException::withMessages(['qty' => "المتاح في فرع المصدر {$from->available} فقط"]);
        }
        $this->adjust($partId, -$qty, 'transfer_out', 'تحويل إلى فرع #'.$toBranch, $userId, $fromBranch);
        $this->adjust($partId, $qty, 'transfer_in', 'تحويل من فرع #'.$fromBranch, $userId, $toBranch);
    }

    /** حجز للطلبات الأونلاين (يُنقص المتاح دون المساس بالفعلي) */
    public function reserve(Part $part, int $qty, ?int $branchId = null): void
    {
        $inv = $this->row($part->id, $branchId, true);
        if ($inv->available < $qty) {
            throw ValidationException::withMessages(['items' => "الكمية المطلوبة من «{$part->name_ar}» غير متوفرة في هذا الفرع (المتاح {$inv->available})"]);
        }
        $inv->increment('reserved', $qty);
    }

    public function release(int $partId, int $qty, ?int $branchId = null): void
    {
        $inv = $this->row($partId, $branchId, true);
        $was = $inv->available;
        $inv->update(['reserved' => max(0, $inv->reserved - $qty)]);
        if ($was === 0 && $inv->fresh()->available > 0) {
            $this->notifyRestock($partId);
        }
    }

    /** عند الشحن أو التسليم من الفرع: تخرج القطعة فعلياً من المخزن */
    public function commitReserved(int $partId, int $qty, ?string $ref = null, ?int $userId = null, ?int $branchId = null): void
    {
        $b = $this->branch($branchId);
        $inv = $this->row($partId, $b, true);
        $inv->update([
            'on_hand' => max(0, $inv->on_hand - $qty),
            'reserved' => max(0, $inv->reserved - $qty),
        ]);
        $this->log($partId, -$qty, 'sale_online', $ref, $userId, $b);
    }

    /** بيع مباشر من المعرض يخصم فوراً من مخزون الفرع */
    public function sellNow(Part $part, int $qty, ?string $ref = null, ?int $userId = null, ?int $branchId = null): void
    {
        $b = $this->branch($branchId);
        $inv = $this->row($part->id, $b, true);
        if ($inv->available < $qty) {
            throw ValidationException::withMessages(['items' => "«{$part->name_ar}»: المتاح {$inv->available} فقط"]);
        }
        $inv->update(['on_hand' => $inv->on_hand - $qty]);
        $this->log($part->id, -$qty, 'sale_pos', $ref, $userId, $b);
    }

    /** كم متاح من كل سطر في فرع معيّن: [part_id => available] */
    public function availableAt(int $branchId, array $partIds): array
    {
        return Inventory::where('branch_id', $branchId)->whereIn('part_id', $partIds)->get()
            ->mapWithKeys(fn ($i) => [$i->part_id => $i->available])->all();
    }

    public function notifyRestock(int $partId): int
    {
        $part = Part::find($partId);
        if (!$part) {
            return 0;
        }
        $n = 0;
        StockAlert::where('part_id', $partId)->whereNull('notified_at')->with('user')->get()->each(function ($a) use ($part, &$n) {
            if ($a->user) {
                $this->notify->push($a->user, 'stock', 'عادت القطعة للمخزون', "«{$part->name_ar}» متوفرة الآن — اطلبها قبل نفادها.", ['part_id' => $part->id]);
            }
            $a->update(['notified_at' => now()]);
            $n++;
        });
        return $n;
    }
}
