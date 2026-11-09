<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Part extends Model
{
    protected $guarded = [];
    protected $casts = ['is_bulky' => 'boolean', 'active' => 'boolean', 'price' => 'float', 'cost' => 'float', 'shipping_fee' => 'float'];

    public function category() { return $this->belongsTo(Category::class); }
    public function inventory() { return $this->hasOne(Inventory::class); }
    public function fitments() { return $this->hasMany(PartFitment::class); }
    public function orderItemsExist(): bool { return OrderItem::where("part_id", $this->id)->exists(); }

    public function getAvailableAttribute(): int
    {
        $i = $this->inventory;
        return $i ? max(0, $i->on_hand - $i->reserved) : 0;
    }

    /** قطع تناسب موديل وسنة */
    public function scopeFitting(Builder $q, int $modelId, ?int $year = null): Builder
    {
        return $q->whereHas('fitments', function ($f) use ($modelId, $year) {
            $f->where('car_model_id', $modelId);
            if ($year) {
                $f->where('year_from', '<=', $year)->where('year_to', '>=', $year);
            }
        });
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (!$term) {
            return $q;
        }
        $t = \App\Support\Phone::latinDigits(trim($term));
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $t).'%';
        return $q->where(fn ($w) => $w->where('sku', 'like', $like)->orWhere('name_ar', 'like', $like)
            ->orWhere('name_en', 'like', $like)->orWhere('brand', 'like', $like));
    }

    public function fitsVehicle(?Vehicle $v): ?bool
    {
        if (!$v) {
            return null;
        }
        return $this->fitments->contains(fn ($f) => $f->car_model_id === $v->car_model_id && $f->year_from <= $v->year && $f->year_to >= $v->year);
    }
}
