<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    public const PLACEMENTS = [
        'hero' => 'سلايدر رئيسي (أعلى الصفحة)',
        'strip' => 'إعلان عريض بين المنتجات',
        'side' => 'إعلان جانبي تحت الفلاتر',
        'topbar' => 'شريط إعلان نصي أعلى الموقع',
    ];

    protected $guarded = [];

    protected $casts = ['active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];

    /** البانرات المفعّلة وضمن فترة العرض */
    public function scopeLive(Builder $q, ?string $placement = null): Builder
    {
        return $q->where('active', true)
            ->when($placement, fn ($w) => $w->where('placement', $placement))
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('sort')->orderByDesc('id');
    }

    public function imageUrl(): ?string
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }

    public function isLive(): bool
    {
        return $this->active && (!$this->starts_at || $this->starts_at->isPast()) && (!$this->ends_at || $this->ends_at->isFuture());
    }
}
