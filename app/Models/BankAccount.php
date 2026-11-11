<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $guarded = [];
    protected $casts = ['active' => 'boolean'];

    public function toApi(): array
    {
        return $this->only(['id', 'bank_name', 'beneficiary_name', 'iban', 'account_number']);
    }
}
