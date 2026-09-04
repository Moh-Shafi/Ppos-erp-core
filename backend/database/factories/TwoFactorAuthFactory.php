<?php

namespace Database\Factories;

use App\Models\TwoFactorAuth;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TwoFactorAuthFactory extends Factory
{
    protected $model = TwoFactorAuth::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'backup_codes' => null,
            'enabled_at' => now(),
            'last_used_at' => null,
        ];
    }
}
