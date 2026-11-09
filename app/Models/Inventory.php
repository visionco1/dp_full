<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $guarded = [];
    public function part() { return $this->belongsTo(Part::class); }
    public function getAvailableAttribute(): int { return max(0, $this->on_hand - $this->reserved); }
}
