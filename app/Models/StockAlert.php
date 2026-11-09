<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAlert extends Model
{
    protected $guarded = [];
    protected $casts = ['notified_at' => 'datetime'];
    public function part() { return $this->belongsTo(Part::class); }
    public function user() { return $this->belongsTo(User::class); }
}
