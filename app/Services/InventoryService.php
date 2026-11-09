<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Part;
use App\Models\StockAlert;
use App\Models\StockMovement;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(private NotificationService $notify) {}

    public function row(int $partId, bool $lock = false): Inventory
    {
        $q = Inventory::where('part_id', $partId);
        if ($lock) {
            $q->lockForUpdate();
        }
        return $q->first() ?? Inventory::create(['part_id' => $partId]);
    }

    public function log(int $partId, int $qty, string $type, ?string $ref = null, ?int $userId = null): void
    {
        StockMovement::create(['part_id' => $partId, 'qty' => $qty, 'type' => $type, 'reference' => $ref, 'user_id' => $userId]);
    }

    /** تعديل الكمية الفعلية (جرد/استيراد/مرتجع) */
    public function adjust(int $partId, int $delta, string $type = 'adjust', ?string $ref = null, ?int $userId = null): Inventory
    {
        $inv = $this->row($partId, true);
        $new = $inv->on_hand + $delta;
        if ($new < 0) {
            throw ValidationException::withMessages(['qty' => 'الكمية لا يمكن أن تكون أقل من صفر']);
        }
        $was = $inv->available;
        $inv->update(['on_hand' => $new]);
        $this->log($partId, $delta, $type, $ref, $userId);
        if ($was === 0 && $inv->fresh()->available > 0) {
            $this->notifyRestock($partId);
        }
        return $inv;
    }

    public function setOnHand(int $partId, int $qty, string $type = 'adjust', ?string $ref = null, ?int $userId = null): Inventory
    {
        $inv = $this->row($partId);
        return $this->adjust($partId, $qty - $inv->on_hand, $type, $ref, $userId);
    }

    /** حجز للطلبات الأونلاين (يُنقص المتاح دون المساس بالفعلي) */
    public function reserve(Part $part, int $qty): void
    {
        $inv = $this->row($part->id, true);
        if ($inv->available < $qty) {
            throw ValidationException::withMessages(['items' => "الكمية المطلوبة من «{$part->name_ar}» غير متوفرة (المتاح {$inv->available})"]);
        }
        $inv->increment('reserved', $qty);
    }

    public function release(int $partId, int $qty): void
    {
        $inv = $this->row($partId, true);
        $was = $inv->available;
        $inv->update(['reserved' => max(0, $inv->reserved - $qty)]);
        if ($was === 0 && $inv->fresh()->available > 0) {
            $this->notifyRestock($partId);
        }
    }

    /** عند الشحن: تخرج القطعة فعلياً من المخزن */
    public function commitReserved(int $partId, int $qty, ?string $ref = null, ?int $userId = null): void
    {
        $inv = $this->row($partId, true);
        $inv->update([
            'on_hand' => max(0, $inv->on_hand - $qty),
            'reserved' => max(0, $inv->reserved - $qty),
        ]);
        $this->log($partId, -$qty, 'sale_online', $ref, $userId);
    }

    /** بيع مباشر من المعرض يخصم فوراً من المخزن المركزي */
    public function sellNow(Part $part, int $qty, ?string $ref = null, ?int $userId = null): void
    {
        $inv = $this->row($part->id, true);
        if ($inv->available < $qty) {
            throw ValidationException::withMessages(['items' => "«{$part->name_ar}»: المتاح {$inv->available} فقط"]);
        }
        $inv->update(['on_hand' => $inv->on_hand - $qty]);
        $this->log($part->id, -$qty, 'sale_pos', $ref, $userId);
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
