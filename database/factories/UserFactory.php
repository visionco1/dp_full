<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => 'عميل تجريبي',
            'phone' => '9665'.fake()->unique()->numerify('########'),
            'phone_verified_at' => now(),
        ];
    }
}
