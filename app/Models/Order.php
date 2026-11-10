<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];
    protected $casts = ['address' => 'array', 'placed_at' => 'datetime', 'subtotal' => 'float', 'vat' => 'float', 'shipping' => 'float', 'total' => 'float'];

    public function items() { return $this->hasMany(OrderItem::class); }
    public function events() { return $this->hasMany(OrderEvent::class)->orderBy('id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function branch() { return $this->belongsTo(Branch::class); }

    public const FULFILLMENT = ['delivery' => 'توصيل', 'pickup' => 'استلام من الفرع'];
    public const OPEN = ['new', 'pending_transfer', 'processing', 'ready', 'shipped'];
    public const CUSTOMER_CANCELLABLE = ['new', 'pending_transfer'];

    public function isPickup(): bool
    {
        return $this->fulfillment === 'pickup';
    }

    public function getStatusLabelAttribute(): string
    {
        return config('dp.order_statuses.'.$this->status, $this->status);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }
}
