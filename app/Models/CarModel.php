<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarModel extends Model
{
    protected $guarded = [];
    public function make() { return $this->belongsTo(Make::class); }
    public function fitments() { return $this->hasMany(PartFitment::class); }

    public function getFullNameAttribute(): string
    {
        return trim(($this->make->name_ar ?? '').' '.$this->name_ar);
    }
}
