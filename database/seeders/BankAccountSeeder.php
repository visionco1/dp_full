<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use Illuminate\Database\Seeder;

/** حساب بنكي تجريبي — استبدله ببيانات حسابكم الحقيقي من لوحة الإدارة ← الصفحات والبنوك */
class BankAccountSeeder extends Seeder
{
    public function run(): void
    {
        if (!BankAccount::exists()) {
            BankAccount::create([
                'bank_name' => 'مصرف الإنماء', 'beneficiary_name' => 'شركة دفع المحركات لقطع الغيار',
                'iban' => 'SA0000000000000000000000', 'account_number' => '00000000', 'active' => true,
            ]);
        }
    }
}
