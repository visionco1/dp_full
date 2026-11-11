<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnRequest extends Model
{
    public const ACTIONS = ['return' => 'إرجاع', 'exchange' => 'استبدال'];
    public const STATUSES = ['pending' => 'قيد المراجعة', 'approved' => 'مقبول — أرسل القطعة', 'rejected' => 'مرفوض', 'completed' => 'مكتمل'];

    protected $guarded = [];

    public function order() { return $this->belongsTo(Order::class); }
    public function item() { return $this->belongsTo(OrderItem::class, 'order_item_id'); }
    public function user() { return $this->belongsTo(User::class); }

    public function toApi(): array
    {
        return [
            'id' => $this->id, 'order_item_id' => $this->order_item_id, 'sku' => $this->item?->sku, 'name' => $this->item?->name,
            'action' => $this->action, 'action_label' => self::ACTIONS[$this->action] ?? $this->action, 'qty' => $this->qty,
            'reason' => $this->reason, 'image' => $this->image ? asset('storage/'.$this->image) : null,
            'status' => $this->status, 'status_label' => self::STATUSES[$this->status] ?? $this->status,
            'admin_note' => $this->admin_note, 'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
