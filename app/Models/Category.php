<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $guarded = [];

    public function parts() { return $this->hasMany(Part::class); }
    public function parent() { return $this->belongsTo(Category::class, 'parent_id'); }
    public function children() { return $this->hasMany(Category::class, 'parent_id')->orderBy('sort')->orderBy('id'); }

    /** الأقسام الرئيسية فقط */
    public function scopeRoots(Builder $q): Builder
    {
        return $q->whereNull('parent_id')->orderBy('sort')->orderBy('id');
    }

    /** id القسم + ids أقسامه الفرعية — من id رقمي أو slug */
    public static function idsWithChildren(int|string $idOrSlug): array
    {
        $c = static::query()->where(fn ($q) => $q->where('slug', (string) $idOrSlug)->when(is_numeric($idOrSlug), fn ($w) => $w->orWhere('id', (int) $idOrSlug)))->first();
        return $c ? [$c->id, ...static::where('parent_id', $c->id)->pluck('id')->all()] : [0];
    }

    /** المسارات تقبل id أو slug: /categories/7/subcategories أو /categories/electrical/subcategories */
    public function resolveRouteBinding($value, $field = null)
    {
        return $field ? parent::resolveRouteBinding($value, $field)
            : static::where('slug', $value)->when(ctype_digit((string) $value), fn ($q) => $q->orWhere('id', (int) $value))->firstOrFail();
    }

    public function imageUrl(): ?string
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }
}
