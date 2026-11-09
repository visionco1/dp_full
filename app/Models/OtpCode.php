<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $guarded = [];
    protected $casts = ['payload' => 'array', 'expires_at' => 'datetime', 'consumed_at' => 'datetime'];
}
