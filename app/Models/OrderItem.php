<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $guarded = [];
    protected $casts = ['unit_price' => 'float', 'unit_cost' => 'float', 'line_total' => 'float', 'shipping_fee' => 'float'];
    public function order() { return $this->belongsTo(Order::class); }
    public function part() { return $this->belongsTo(Part::class); }
}
