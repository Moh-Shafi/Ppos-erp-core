<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            ModuleSeeder::class,
            BusinessTypeSeeder::class,
            RbacSeeder::class,
            DefaultAccountsSeeder::class,
            IntegrationProviderSeeder::class,
            WebhookEventSeeder::class,
        ]);
    }
}
