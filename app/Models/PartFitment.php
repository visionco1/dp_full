<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartFitment extends Model
{
    protected $guarded = [];
    public function part() { return $this->belongsTo(Part::class); }
    public function carModel() { return $this->belongsTo(CarModel::class); }
}
