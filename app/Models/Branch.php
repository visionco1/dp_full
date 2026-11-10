<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $guarded = [];
    protected $casts = ['pickup_enabled' => 'boolean', 'delivery_enabled' => 'boolean', 'is_default' => 'boolean', 'active' => 'boolean', 'lat' => 'float', 'lng' => 'float'];

    public function inventories() { return $this->hasMany(Inventory::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function staff() { return $this->hasMany(User::class); }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('active', true)->orderBy('sort')->orderBy('id');
    }

    public static function defaultId(): ?int
    {
        return static::where('active', true)->orderByDesc('is_default')->orderBy('sort')->orderBy('id')->value('id');
    }

    public function supports(string $fulfillment): bool
    {
        return $this->active && ($fulfillment === 'pickup' ? $this->pickup_enabled : $this->delivery_enabled);
    }
}
