<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
    public function carModel() { return $this->belongsTo(CarModel::class); }
    public function user() { return $this->belongsTo(User::class); }
}
