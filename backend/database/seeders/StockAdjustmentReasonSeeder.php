<?php

namespace Database\Seeders;

use App\Models\StockAdjustmentReason;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class StockAdjustmentReasonSeeder extends Seeder
{
    public function run(): void
    {
        $systemReasons = [
            ['name' => 'Damaged Goods', 'category' => 'damaged'],
            ['name' => 'Lost/Missing', 'category' => 'lost'],
            ['name' => 'Found/Surplus', 'category' => 'found'],
            ['name' => 'Recount Adjustment', 'category' => 'recount'],
            ['name' => 'Initial Stock', 'category' => 'initial'],
            ['name' => 'Other', 'category' => 'other'],
        ];

        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            foreach ($systemReasons as $reason) {
                StockAdjustmentReason::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'name' => $reason['name'],
                        'category' => $reason['category'],
                    ],
                    [
                        'is_system' => true,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
