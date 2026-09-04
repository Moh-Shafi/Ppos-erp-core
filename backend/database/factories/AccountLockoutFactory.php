<?php

namespace Database\Factories;

use App\Models\AccountLockout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountLockoutFactory extends Factory
{
    protected $model = AccountLockout::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'username' => $this->faker->email(),
            'failed_attempts' => 0,
            'locked_until' => null,
            'last_attempt_at' => now(),
            'ip_address' => $this->faker->ipv4(),
        ];
    }
}
