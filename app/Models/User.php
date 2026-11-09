<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $guarded = [];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = [
        'is_staff' => 'boolean',
        'active' => 'boolean',
        'notif_prefs' => 'array',
        'phone_verified_at' => 'datetime',
        'deletion_requested_at' => 'datetime',
        'password' => 'hashed',
    ];

    public const DEFAULT_PREFS = ['orders' => true, 'price' => true, 'stock' => true, 'offers' => false, 'push' => true];

    public function role() { return $this->belongsTo(Role::class); }
    public function vehicles() { return $this->hasMany(Vehicle::class); }
    public function addresses() { return $this->hasMany(Address::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function appNotifications() { return $this->hasMany(AppNotification::class); }

    public function hasPermission(string $perm): bool
    {
        return $this->is_staff && $this->active && $this->role && $this->role->allows($perm);
    }

    public function prefs(): array
    {
        return array_merge(self::DEFAULT_PREFS, $this->notif_prefs ?? []);
    }
}
