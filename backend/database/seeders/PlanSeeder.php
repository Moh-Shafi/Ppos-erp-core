<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::firstOrCreate(['slug' => 'free'], [
            'name' => 'Free',
            'price' => 0,
            'description' => 'Basic plan for getting started',
            'is_active' => true,
        ]);

        Plan::firstOrCreate(['slug' => 'pro'], [
            'name' => 'Pro',
            'price' => 9.99,
            'description' => 'Professional plan with advanced features',
            'is_active' => true,
        ]);

        Plan::firstOrCreate(['slug' => 'enterprise'], [
            'name' => 'Enterprise',
            'price' => 49.99,
            'description' => 'Enterprise plan for multiple stores',
            'is_active' => true,
        ]);
    }
}
