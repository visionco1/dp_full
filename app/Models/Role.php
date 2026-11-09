<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $guarded = [];
    protected $casts = ['permissions' => 'array', 'locked' => 'boolean'];

    public function users() { return $this->hasMany(User::class); }

    public function allows(string $perm): bool
    {
        $p = $this->permissions ?? [];
        return in_array('*', $p, true) || in_array($perm, $p, true);
    }
}
