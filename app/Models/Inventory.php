<?php

namespace App\Models;

use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Model;

/** مخزون قطعة في فرع واحد */
class Inventory extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(fn (Inventory $i) => $i->branch_id ??= BranchContext::current());
    }

    public function part() { return $this->belongsTo(Part::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function getAvailableAttribute(): int { return max(0, $this->on_hand - $this->reserved); }
}
