<?php

namespace Database\Seeders;

use App\Models\BusinessType;
use App\Models\Feature;
use App\Models\Module;
use Illuminate\Database\Seeder;

class BusinessTypeSeeder extends Seeder
{
    public function run(): void
    {
        $businessTypes = [
            ['slug' => 'restaurant', 'name' => 'Restaurant', 'description' => 'Full restaurant with POS, tables, kitchen', 'icon' => 'utensils', 'sort_order' => 1],
            ['slug' => 'cafe', 'name' => 'Café', 'description' => 'Café with POS and menu management', 'icon' => 'coffee', 'sort_order' => 2],
            ['slug' => 'retail', 'name' => 'Retail Shop', 'description' => 'Retail store with POS and barcode', 'icon' => 'shopping-bag', 'sort_order' => 3],
            ['slug' => 'grocery', 'name' => 'Grocery', 'description' => 'Grocery store with inventory and suppliers', 'icon' => 'shopping-cart', 'sort_order' => 4],
            ['slug' => 'pharmacy', 'name' => 'Pharmacy', 'description' => 'Pharmacy with batch/expiry tracking', 'icon' => 'pill', 'sort_order' => 5],
            ['slug' => 'clinic', 'name' => 'Clinic', 'description' => 'Clinic with appointments and invoices', 'icon' => 'stethoscope', 'sort_order' => 6],
            ['slug' => 'salon', 'name' => 'Salon', 'description' => 'Salon with appointments and POS', 'icon' => 'scissors', 'sort_order' => 7],
            ['slug' => 'hotel', 'name' => 'Hotel', 'description' => 'Hotel with reservations and POS', 'icon' => 'bed', 'sort_order' => 8],
            ['slug' => 'service', 'name' => 'Service Business', 'description' => 'Service business with appointments and invoices', 'icon' => 'wrench', 'sort_order' => 9],
            ['slug' => 'wholesale', 'name' => 'Wholesale', 'description' => 'Wholesale with price lists and bulk sales', 'icon' => 'truck', 'sort_order' => 10],
            ['slug' => 'manufacturing', 'name' => 'Manufacturing', 'description' => 'Manufacturing with production and inventory', 'icon' => 'factory', 'sort_order' => 11],
            ['slug' => 'general', 'name' => 'General', 'description' => 'General business with basic POS and inventory', 'icon' => 'building', 'sort_order' => 99],
        ];

        foreach ($businessTypes as $btData) {
            BusinessType::firstOrCreate(['slug' => $btData['slug']], $btData);
        }

        $moduleDefaults = [
            'restaurant' => ['core', 'pos', 'sales', 'inventory', 'purchasing', 'customers', 'suppliers', 'tables', 'kitchen', 'kds', 'reports', 'payments', 'settings', 'audit'],
            'cafe' => ['core', 'pos', 'sales', 'inventory', 'purchasing', 'customers', 'suppliers', 'reports', 'payments', 'settings', 'audit'],
            'retail' => ['core', 'pos', 'sales', 'inventory', 'purchasing', 'customers', 'suppliers', 'barcode', 'reports', 'payments', 'settings', 'audit'],
            'grocery' => ['core', 'pos', 'sales', 'inventory', 'purchasing', 'customers', 'suppliers', 'barcode', 'reports', 'payments', 'settings', 'audit'],
            'pharmacy' => ['core', 'pos', 'sales', 'inventory', 'purchasing', 'customers', 'suppliers', 'reports', 'payments', 'settings', 'audit'],
            'clinic' => ['core', 'customers', 'appointments', 'sales', 'inventory', 'reports', 'payments', 'settings', 'audit'],
            'salon' => ['core', 'customers', 'appointments', 'pos', 'sales', 'inventory', 'reports', 'payments', 'settings', 'audit'],
            'hotel' => ['core', 'reservations', 'pos', 'sales', 'inventory', 'customers', 'reports', 'payments', 'settings', 'audit'],
            'service' => ['core', 'customers', 'appointments', 'sales', 'reports', 'payments', 'settings', 'audit'],
            'wholesale' => ['core', 'sales', 'purchasing', 'inventory', 'customers', 'suppliers', 'reports', 'payments', 'settings', 'audit'],
            'manufacturing' => ['core', 'inventory', 'purchasing', 'manufacturing', 'sales', 'reports', 'payments', 'settings', 'audit'],
            'general' => ['core', 'pos', 'sales', 'inventory', 'purchasing', 'customers', 'suppliers', 'reports', 'payments', 'settings', 'audit'],
        ];

        foreach ($moduleDefaults as $btSlug => $moduleSlugs) {
            $bt = BusinessType::where('slug', $btSlug)->first();
            if (!$bt) continue;

            foreach ($moduleSlugs as $moduleSlug) {
                $module = Module::where('slug', $moduleSlug)->first();
                if (!$module) continue;

                $bt->modules()->syncWithoutDetaching([
                    $module->id => ['is_default_enabled' => true],
                ]);
            }
        }

        $featureDefaults = [
            'restaurant' => ['pos.split_payment', 'pos.multi_payment', 'pos.discount_presets', 'inventory.transfer', 'payments.qris', 'payments.cash', 'reports.sales', 'reports.inventory', 'audit.view'],
            'cafe' => ['pos.split_payment', 'pos.multi_payment', 'pos.discount_presets', 'inventory.transfer', 'payments.qris', 'payments.cash', 'reports.sales', 'reports.inventory', 'audit.view'],
            'retail' => ['pos.split_payment', 'pos.multi_payment', 'pos.discount_presets', 'inventory.transfer', 'payments.qris', 'payments.cash', 'reports.sales', 'reports.inventory', 'audit.view'],
            'grocery' => ['pos.split_payment', 'pos.multi_payment', 'pos.discount_presets', 'inventory.transfer', 'payments.qris', 'payments.cash', 'reports.sales', 'reports.inventory', 'audit.view'],
            'pharmacy' => ['pos.split_payment', 'pos.multi_payment', 'pos.discount_presets', 'inventory.transfer', 'inventory.batch_tracking', 'inventory.expiry_tracking', 'payments.qris', 'payments.cash', 'reports.sales', 'reports.inventory', 'audit.view'],
            'clinic' => ['payments.cash', 'payments.qris', 'reports.sales', 'audit.view'],
            'salon' => ['pos.split_payment', 'pos.discount_presets', 'payments.cash', 'payments.qris', 'reports.sales', 'audit.view'],
            'hotel' => ['pos.split_payment', 'pos.multi_payment', 'payments.cash', 'payments.qris', 'reports.sales', 'audit.view'],
            'service' => ['payments.cash', 'payments.qris', 'reports.sales', 'audit.view'],
            'wholesale' => ['inventory.transfer', 'payments.cash', 'payments.bank_transfer', 'reports.sales', 'reports.inventory', 'audit.view'],
            'manufacturing' => ['inventory.transfer', 'inventory.batch_tracking', 'payments.cash', 'reports.sales', 'reports.inventory', 'audit.view'],
            'general' => ['pos.split_payment', 'pos.multi_payment', 'pos.discount_presets', 'inventory.transfer', 'payments.qris', 'payments.cash', 'reports.sales', 'reports.inventory', 'audit.view'],
        ];

        foreach ($featureDefaults as $btSlug => $featureSlugs) {
            $bt = BusinessType::where('slug', $btSlug)->first();
            if (!$bt) continue;

            foreach ($featureSlugs as $featureSlug) {
                $feature = Feature::where('slug', $featureSlug)->first();
                if (!$feature) continue;

                $bt->features()->syncWithoutDetaching([
                    $feature->id => ['is_default_enabled' => true],
                ]);
            }
        }
    }
}
