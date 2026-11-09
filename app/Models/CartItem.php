<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $guarded = [];
    public function part() { return $this->belongsTo(Part::class); }
    public function cart() { return $this->belongsTo(Cart::class); }
}
