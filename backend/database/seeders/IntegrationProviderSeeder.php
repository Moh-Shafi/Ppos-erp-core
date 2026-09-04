<?php

namespace Database\Seeders;

use App\Models\IntegrationProvider;
use Illuminate\Database\Seeder;

class IntegrationProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'slug' => 'generic_http',
                'name' => 'Generic HTTP',
                'description' => 'Generic HTTP REST API integration for custom external systems',
                'config_schema' => [
                    'base_url' => ['type' => 'string', 'required' => true],
                    'timeout' => ['type' => 'integer', 'default' => 30],
                ],
                'is_active' => true,
                'is_system' => true,
            ],
            [
                'slug' => 'xendit',
                'name' => 'Xendit Payment Gateway',
                'description' => 'Xendit xenPlatform payment gateway integration',
                'config_schema' => [
                    'base_url' => ['type' => 'string', 'required' => true, 'default' => 'https://api.xendit.co'],
                ],
                'is_active' => true,
                'is_system' => true,
            ],
        ];

        foreach ($providers as $provider) {
            IntegrationProvider::firstOrCreate(['slug' => $provider['slug']], $provider);
        }
    }
}
