<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['slug' => 'core', 'name' => 'Core', 'is_core' => true, 'dependencies' => [], 'sort_order' => 0, 'icon' => 'layout'],
            ['slug' => 'pos', 'name' => 'POS / Kasir', 'is_core' => false, 'dependencies' => ['core', 'inventory'], 'sort_order' => 10, 'icon' => 'shopping-cart'],
            ['slug' => 'sales', 'name' => 'Sales', 'is_core' => false, 'dependencies' => ['core'], 'sort_order' => 20, 'icon' => 'receipt'],
            ['slug' => 'inventory', 'name' => 'Inventory', 'is_core' => false, 'dependencies' => ['core'], 'sort_order' => 30, 'icon' => 'package'],
            ['slug' => 'warehouse', 'name' => 'Warehouse', 'is_core' => false, 'dependencies' => ['core', 'inventory'], 'sort_order' => 35, 'icon' => 'warehouse'],
            ['slug' => 'purchasing', 'name' => 'Purchasing', 'is_core' => false, 'dependencies' => ['core', 'inventory'], 'sort_order' => 40, 'icon' => 'truck'],
            ['slug' => 'customers', 'name' => 'Customers', 'is_core' => false, 'dependencies' => ['core'], 'sort_order' => 50, 'icon' => 'users'],
            ['slug' => 'crm', 'name' => 'CRM', 'is_core' => false, 'dependencies' => ['core', 'customers'], 'sort_order' => 55, 'icon' => 'heart'],
            ['slug' => 'suppliers', 'name' => 'Suppliers', 'is_core' => false, 'dependencies' => ['core'], 'sort_order' => 60, 'icon' => 'building'],
            ['slug' => 'accounting', 'name' => 'Accounting', 'is_core' => false, 'dependencies' => ['core'], 'sort_order' => 70, 'icon' => 'calculator'],
            ['slug' => 'finance', 'name' => 'Finance', 'is_core' => false, 'dependencies' => ['core', 'accounting'], 'sort_order' => 75, 'icon' => 'dollar-sign'],
            ['slug' => 'payments', 'name' => 'Payments', 'is_core' => false, 'dependencies' => ['core'], 'sort_order' => 80, 'icon' => 'credit-card'],
            ['slug' => 'expenses', 'name' => 'Expenses', 'is_core' => false, 'dependencies' => ['core', 'accounting'], 'sort_order' => 85, 'icon' => 'trending-down'],
            ['slug' => 'reports', 'name' => 'Reports', 'is_core' => false, 'dependencies' => ['core'], 'sort_order' => 90, 'icon' => 'bar-chart'],
            ['slug' => 'hr', 'name' => 'HR', 'is_core' => false, 'dependencies' => ['core'], 'sort_order' => 100, 'icon' => 'briefcase'],
            ['slug' => 'payroll', 'name' => 'Payroll', 'is_core' => false, 'dependencies' => ['core', 'hr', 'accounting'], 'sort_order' => 105, 'icon' => 'wallet'],
            ['slug' => 'tables', 'name' => 'Tables', 'is_core' => false, 'dependencies' => ['pos'], 'sort_order' => 110, 'icon' => 'table'],
            ['slug' => 'reservations', 'name' => 'Reservations', 'is_core' => false, 'dependencies' => ['tables', 'customers'], 'sort_order' => 115, 'icon' => 'calendar'],
            ['slug' => 'kitchen', 'name' => 'Kitchen', 'is_core' => false, 'dependencies' => ['pos', 'inventory'], 'sort_order' => 120, 'icon' => 'chef-hat'],
            ['slug' => 'kds', 'name' => 'Kitchen Display System', 'is_core' => false, 'dependencies' => ['kitchen'], 'sort_order' => 125, 'icon' => 'monitor'],
            ['slug' => 'manufacturing', 'name' => 'Manufacturing', 'is_core' => false, 'dependencies' => ['core', 'inventory'], 'sort_order' => 130, 'icon' => 'settings'],
            ['slug' => 'assets', 'name' => 'Assets', 'is_core' => false, 'dependencies' => ['core', 'accounting'], 'sort_order' => 135, 'icon' => 'archive'],
            ['slug' => 'barcode', 'name' => 'Barcode', 'is_core' => false, 'dependencies' => ['pos', 'inventory'], 'sort_order' => 140, 'icon' => 'scan'],
            ['slug' => 'appointments', 'name' => 'Appointments', 'is_core' => false, 'dependencies' => ['customers'], 'sort_order' => 145, 'icon' => 'clock'],
            ['slug' => 'promotions', 'name' => 'Promotions', 'is_core' => false, 'dependencies' => ['pos'], 'sort_order' => 146, 'icon' => 'tag'],
            ['slug' => 'loyalty', 'name' => 'Loyalty Program', 'is_core' => false, 'dependencies' => ['customers'], 'sort_order' => 147, 'icon' => 'award'],
            ['slug' => 'pricetags', 'name' => 'Price Tags', 'is_core' => false, 'dependencies' => ['inventory'], 'sort_order' => 148, 'icon' => 'printer'],
            ['slug' => 'subscriptions', 'name' => 'Subscriptions', 'is_core' => false, 'dependencies' => ['core'], 'sort_order' => 150, 'icon' => 'repeat'],
            ['slug' => 'notifications', 'name' => 'Notifications', 'is_core' => false, 'dependencies' => ['core'], 'sort_order' => 155, 'icon' => 'bell'],
            ['slug' => 'audit', 'name' => 'Audit', 'is_core' => false, 'dependencies' => ['core'], 'sort_order' => 160, 'icon' => 'shield'],
            ['slug' => 'users', 'name' => 'User Management', 'is_core' => false, 'dependencies' => ['core'], 'sort_order' => 165, 'icon' => 'user-cog'],
            ['slug' => 'settings', 'name' => 'Settings', 'is_core' => false, 'dependencies' => ['core'], 'sort_order' => 170, 'icon' => 'settings'],
            ['slug' => 'integrations', 'name' => 'Integrations & Webhooks', 'is_core' => false, 'dependencies' => ['core'], 'sort_order' => 175, 'icon' => 'plug'],
            ['slug' => 'security', 'name' => 'Security', 'is_core' => true, 'dependencies' => ['core'], 'sort_order' => 180, 'icon' => 'shield'],
        ];

        foreach ($modules as $moduleData) {
            Module::firstOrCreate(['slug' => $moduleData['slug']], $moduleData);
        }

        $features = [
            // Core features (Phase 1)
            ['module_slug' => 'core', 'slug' => 'core.variants', 'name' => 'Product Variants', 'is_default_enabled' => false],
            ['module_slug' => 'core', 'slug' => 'core.price_lists', 'name' => 'Price Lists', 'is_default_enabled' => false],
            ['module_slug' => 'core', 'slug' => 'core.units', 'name' => 'Units of Measure', 'is_default_enabled' => true],
            ['module_slug' => 'core', 'slug' => 'core.import_export', 'name' => 'CSV Import/Export', 'is_default_enabled' => true],
            // POS features
            ['module_slug' => 'pos', 'slug' => 'pos.split_payment', 'name' => 'Split Payment', 'is_default_enabled' => true],
            ['module_slug' => 'pos', 'slug' => 'pos.multi_payment', 'name' => 'Multi Payment', 'is_default_enabled' => true],
            ['module_slug' => 'pos', 'slug' => 'pos.hold_sale', 'name' => 'Hold / Recall Sale', 'is_default_enabled' => false],
            ['module_slug' => 'pos', 'slug' => 'pos.refund', 'name' => 'Refund Processing', 'is_default_enabled' => false],
            ['module_slug' => 'pos', 'slug' => 'pos.discount_presets', 'name' => 'Discount Presets', 'is_default_enabled' => true],
            // Inventory features
            ['module_slug' => 'inventory', 'slug' => 'inventory.transfer', 'name' => 'Stock Transfer', 'is_default_enabled' => true],
            ['module_slug' => 'inventory', 'slug' => 'inventory.transfer_request', 'name' => 'Transfer Request Approval', 'is_default_enabled' => true],
            ['module_slug' => 'inventory', 'slug' => 'inventory.batch_tracking', 'name' => 'Batch Tracking', 'is_default_enabled' => false],
            ['module_slug' => 'inventory', 'slug' => 'inventory.expiry_tracking', 'name' => 'Expiry Tracking', 'is_default_enabled' => false],
            ['module_slug' => 'inventory', 'slug' => 'inventory.stocktake', 'name' => 'Stocktake', 'is_default_enabled' => false],
            ['module_slug' => 'inventory', 'slug' => 'inventory.valuation', 'name' => 'Stock Valuation', 'is_default_enabled' => true],
            // Customers features
            ['module_slug' => 'customers', 'slug' => 'customers.loyalty_points', 'name' => 'Loyalty Points', 'is_default_enabled' => false],
            // Sales features
            ['module_slug' => 'sales', 'slug' => 'sales.customer_credit', 'name' => 'Customer Credit', 'is_default_enabled' => false],
            // Purchasing features
            ['module_slug' => 'purchasing', 'slug' => 'purchasing.requisition', 'name' => 'Purchase Requisition', 'is_default_enabled' => true],
            ['module_slug' => 'purchasing', 'slug' => 'purchasing.invoice_matching', 'name' => 'Invoice 3-Way Matching', 'is_default_enabled' => false],
            // Payments features
            ['module_slug' => 'payments', 'slug' => 'payments.qris', 'name' => 'QRIS Payment', 'is_default_enabled' => true],
            ['module_slug' => 'payments', 'slug' => 'payments.cash', 'name' => 'Cash Payment', 'is_default_enabled' => true],
            ['module_slug' => 'payments', 'slug' => 'payments.bank_transfer', 'name' => 'Bank Transfer', 'is_default_enabled' => false],
            ['module_slug' => 'payments', 'slug' => 'payments.card', 'name' => 'Card Payment', 'is_default_enabled' => false],
            ['module_slug' => 'payments', 'slug' => 'payment.gateway_qris', 'name' => 'Payment Gateway QRIS', 'is_default_enabled' => false],
            ['module_slug' => 'payments', 'slug' => 'payment.cash_drawer', 'name' => 'Cash Drawer', 'is_default_enabled' => false],
            // Finance / Accounting features
            ['module_slug' => 'finance', 'slug' => 'finance.chart_of_accounts', 'name' => 'Chart of Accounts', 'is_default_enabled' => true],
            ['module_slug' => 'finance', 'slug' => 'finance.journal_entries', 'name' => 'Journal Entries', 'is_default_enabled' => true],
            ['module_slug' => 'finance', 'slug' => 'finance.financial_reports', 'name' => 'Financial Reports', 'is_default_enabled' => true],
            ['module_slug' => 'finance', 'slug' => 'finance.fiscal_periods', 'name' => 'Fiscal Periods', 'is_default_enabled' => true],
            // Reports features (Phase 7)
            ['module_slug' => 'reports', 'slug' => 'reports.dashboard', 'name' => 'Dashboard', 'is_default_enabled' => true],
            ['module_slug' => 'reports', 'slug' => 'reports.sales', 'name' => 'Sales Reports', 'is_default_enabled' => true],
            ['module_slug' => 'reports', 'slug' => 'reports.profit', 'name' => 'Profit Reports', 'is_default_enabled' => false],
            ['module_slug' => 'reports', 'slug' => 'reports.inventory', 'name' => 'Inventory Reports', 'is_default_enabled' => true],
            ['module_slug' => 'reports', 'slug' => 'reports.purchasing', 'name' => 'Purchasing Reports', 'is_default_enabled' => true],
            ['module_slug' => 'reports', 'slug' => 'reports.customers', 'name' => 'Customer Reports', 'is_default_enabled' => true],
            ['module_slug' => 'reports', 'slug' => 'reports.payments', 'name' => 'Payment Reports', 'is_default_enabled' => true],
            ['module_slug' => 'reports', 'slug' => 'reports.financial', 'name' => 'Financial Reports', 'is_default_enabled' => false],
            ['module_slug' => 'reports', 'slug' => 'reports.cash_flow', 'name' => 'Cash Flow Reports', 'is_default_enabled' => false],
            ['module_slug' => 'reports', 'slug' => 'reports.ar_aging', 'name' => 'AR Aging Reports', 'is_default_enabled' => false],
            ['module_slug' => 'reports', 'slug' => 'reports.ap_aging', 'name' => 'AP Aging Reports', 'is_default_enabled' => false],
            ['module_slug' => 'reports', 'slug' => 'reports.export_csv', 'name' => 'CSV Export', 'is_default_enabled' => true],
            ['module_slug' => 'reports', 'slug' => 'reports.export_xlsx', 'name' => 'XLSX Export', 'is_default_enabled' => true],
            ['module_slug' => 'reports', 'slug' => 'reports.export_pdf', 'name' => 'PDF Export', 'is_default_enabled' => true],
            // Audit features
            ['module_slug' => 'audit', 'slug' => 'audit.view', 'name' => 'View Audit Logs', 'is_default_enabled' => true, 'is_owner_toggleable' => false],
            // Phase 8A — Restaurant features
            ['module_slug' => 'tables', 'slug' => 'tables.qr_ordering', 'name' => 'QR Code Ordering', 'is_default_enabled' => false],
            ['module_slug' => 'kitchen', 'slug' => 'recipes.auto_deduct', 'name' => 'Auto Recipe Deduction', 'is_default_enabled' => false],
            // Phase 8B — Retail features
            ['module_slug' => 'promotions', 'slug' => 'promotions.buy_x_get_y', 'name' => 'Buy X Get Y Promotions', 'is_default_enabled' => true],
            ['module_slug' => 'loyalty', 'slug' => 'loyalty.tiers', 'name' => 'Loyalty Tiers', 'is_default_enabled' => true],
            // Phase 8C — Service features
            ['module_slug' => 'appointments', 'slug' => 'appointments.recurring', 'name' => 'Recurring Appointments', 'is_default_enabled' => false],
            // Phase 9 — Integration & Webhooks
            ['module_slug' => 'integrations', 'slug' => 'integrations.outbound', 'name' => 'Outbound Integration Sync', 'is_default_enabled' => true],
            ['module_slug' => 'integrations', 'slug' => 'integrations.inbound', 'name' => 'Inbound Webhook Receiver', 'is_default_enabled' => true],
            ['module_slug' => 'integrations', 'slug' => 'webhooks.custom_events', 'name' => 'Custom Event Filters', 'is_default_enabled' => false],
            ['module_slug' => 'integrations', 'slug' => 'apikeys.scoped', 'name' => 'Per-Key Permission Scopes', 'is_default_enabled' => true],
            // Phase 10 — Security
            ['module_slug' => 'security', 'slug' => '2fa', 'name' => 'Two-Factor Authentication', 'is_default_enabled' => false],
        ];

        foreach ($features as $featureData) {
            $module = Module::where('slug', $featureData['module_slug'])->first();
            if ($module) {
                Feature::firstOrCreate(
                    ['slug' => $featureData['slug']],
                    [
                        'module_id' => $module->id,
                        'name' => $featureData['name'],
                        'is_default_enabled' => $featureData['is_default_enabled'] ?? true,
                        'is_owner_toggleable' => $featureData['is_owner_toggleable'] ?? true,
                    ]
                );
            }
        }
    }
}
