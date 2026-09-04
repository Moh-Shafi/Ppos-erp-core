<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Owner', 'slug' => 'owner', 'is_system' => true, 'sort_order' => 1],
            ['name' => 'Manager', 'slug' => 'manager', 'is_system' => true, 'sort_order' => 2],
            ['name' => 'Cashier', 'slug' => 'cashier', 'is_system' => true, 'sort_order' => 3],
            ['name' => 'Staff', 'slug' => 'staff', 'is_system' => true, 'sort_order' => 4],
            ['name' => 'Accountant', 'slug' => 'accountant', 'is_system' => true, 'sort_order' => 5],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['slug' => $role['slug']], $role);
        }

        $permissionModuleMap = [
            'products.manage' => 'core',
            'products.view' => 'core',
            'categories.manage' => 'core',
            'categories.view' => 'core',
            'sales.manage' => 'sales',
            'sales.view' => 'sales',
            'inventory.manage' => 'inventory',
            'inventory.view' => 'inventory',
            'inventory.stocktake' => 'inventory',
            'inventory.valuation' => 'inventory',
            'warehouse.manage' => 'warehouse',
            'warehouse.view' => 'warehouse',
            'customers.manage' => 'customers',
            'customers.view' => 'customers',
            'suppliers.manage' => 'suppliers',
            'suppliers.view' => 'suppliers',
            'purchases.manage' => 'purchasing',
            'purchases.view' => 'purchasing',
            'purchasing.requisition' => 'purchasing',
            'purchasing.grn' => 'purchasing',
            'purchasing.invoice_match' => 'purchasing',
            'crm.view' => 'customers',
            'crm.manage' => 'customers',
            'reports.view' => 'reports',
            'reports.export' => 'reports',
            'reports.dashboard.manage' => 'reports',
            'reports.comparison' => 'reports',
            'reports.financial' => 'reports',
            'settings.manage' => 'settings',
            'users.manage' => 'users',
            'pos.use' => 'pos',
            'pos.hold_sale' => 'pos',
            'pos.refund' => 'pos',
            'pos.discount_presets' => 'pos',
            'payments.view' => 'payments',
            'payments.manage' => 'payments',
            'payments.refund' => 'payments',
            'payments.gateway_config' => 'payments',
            'payments.reconcile' => 'payments',
            'payments.cash_drawer' => 'payments',
            'finance.view' => 'finance',
            'finance.manage' => 'finance',
            'finance.post_journals' => 'finance',
            'finance.close_period' => 'finance',
            'finance.reports' => 'finance',
            'audit.view' => 'audit',
            // Phase 8A — Restaurant
            'tables.view' => 'tables',
            'tables.manage' => 'tables',
            'reservations.view' => 'reservations',
            'reservations.manage' => 'reservations',
            'kitchen.view' => 'kitchen',
            'kitchen.manage' => 'kitchen',
            'kds.view' => 'kds',
            'kds.manage' => 'kds',
            'modifiers.view' => 'kitchen',
            'modifiers.manage' => 'kitchen',
            'recipes.view' => 'kitchen',
            'recipes.manage' => 'kitchen',
            'billsplit.view' => 'tables',
            'billsplit.manage' => 'tables',
            // Phase 8B — Retail
            'promotions.view' => 'promotions',
            'promotions.manage' => 'promotions',
            'loyalty.view' => 'loyalty',
            'loyalty.manage' => 'loyalty',
            'pricetags.view' => 'pricetags',
            'pricetags.manage' => 'pricetags',
            // Phase 8C — Service
            'appointments.view' => 'appointments',
            'appointments.manage' => 'appointments',
            'services.view' => 'appointments',
            'services.manage' => 'appointments',
            'staff.schedule.view' => 'appointments',
            'staff.schedule.manage' => 'appointments',
            // Phase 9 — Integration & Webhooks
            'integrations.view' => 'integrations',
            'integrations.manage' => 'integrations',
            'webhooks.view' => 'integrations',
            'webhooks.manage' => 'integrations',
            'apikeys.view' => 'integrations',
            'apikeys.manage' => 'integrations',
            // Phase 10 — Security
            'owner' => 'security',
            'security.audit.view' => 'audit',
            'security.account.unlock' => 'security',
            'security.2fa.reset' => 'security',
        ];

        $permissions = [
            ['name' => 'Manage Products', 'slug' => 'products.manage'],
            ['name' => 'View Products', 'slug' => 'products.view'],
            ['name' => 'Manage Categories', 'slug' => 'categories.manage'],
            ['name' => 'View Categories', 'slug' => 'categories.view'],
            ['name' => 'Manage Sales', 'slug' => 'sales.manage'],
            ['name' => 'View Sales', 'slug' => 'sales.view'],
            ['name' => 'Manage Inventory', 'slug' => 'inventory.manage'],
            ['name' => 'View Inventory', 'slug' => 'inventory.view'],
            ['name' => 'Stocktake', 'slug' => 'inventory.stocktake'],
            ['name' => 'Inventory Valuation', 'slug' => 'inventory.valuation'],
            ['name' => 'Manage Warehouses', 'slug' => 'warehouse.manage'],
            ['name' => 'View Warehouses', 'slug' => 'warehouse.view'],
            ['name' => 'Manage Customers', 'slug' => 'customers.manage'],
            ['name' => 'View Customers', 'slug' => 'customers.view'],
            ['name' => 'Manage Suppliers', 'slug' => 'suppliers.manage'],
            ['name' => 'View Suppliers', 'slug' => 'suppliers.view'],
            ['name' => 'Manage Purchases', 'slug' => 'purchases.manage'],
            ['name' => 'View Purchases', 'slug' => 'purchases.view'],
            ['name' => 'Approve Requisitions', 'slug' => 'purchasing.requisition'],
            ['name' => 'Manage GRNs', 'slug' => 'purchasing.grn'],
            ['name' => 'Invoice Matching', 'slug' => 'purchasing.invoice_match'],
            ['name' => 'View CRM', 'slug' => 'crm.view'],
            ['name' => 'Manage CRM', 'slug' => 'crm.manage'],
            ['name' => 'View Reports', 'slug' => 'reports.view'],
            ['name' => 'Export Reports', 'slug' => 'reports.export'],
            ['name' => 'Manage Dashboard', 'slug' => 'reports.dashboard.manage'],
            ['name' => 'Compare Stores / Periods', 'slug' => 'reports.comparison'],
            ['name' => 'View Financial Reports', 'slug' => 'reports.financial'],
            ['name' => 'Manage Settings', 'slug' => 'settings.manage'],
            ['name' => 'Manage Users', 'slug' => 'users.manage'],
            ['name' => 'Use POS', 'slug' => 'pos.use'],
            ['name' => 'Hold / Recall Sale', 'slug' => 'pos.hold_sale'],
            ['name' => 'Process Refund', 'slug' => 'pos.refund'],
            ['name' => 'Manage Discount Presets', 'slug' => 'pos.discount_presets'],
            ['name' => 'View Payments', 'slug' => 'payments.view'],
            ['name' => 'Manage Payments', 'slug' => 'payments.manage'],
            ['name' => 'Refund Payments', 'slug' => 'payments.refund'],
            ['name' => 'Configure Payment Gateway', 'slug' => 'payments.gateway_config'],
            ['name' => 'Reconcile Payments', 'slug' => 'payments.reconcile'],
            ['name' => 'Manage Cash Drawer', 'slug' => 'payments.cash_drawer'],
            ['name' => 'View Finance', 'slug' => 'finance.view'],
            ['name' => 'Manage Finance', 'slug' => 'finance.manage'],
            ['name' => 'Post Journal Entries', 'slug' => 'finance.post_journals'],
            ['name' => 'Close Fiscal Period', 'slug' => 'finance.close_period'],
            ['name' => 'View Financial Reports', 'slug' => 'finance.reports'],
            ['name' => 'View Audit Logs', 'slug' => 'audit.view'],
            // Phase 8A — Restaurant
            ['name' => 'View Tables', 'slug' => 'tables.view'],
            ['name' => 'Manage Tables', 'slug' => 'tables.manage'],
            ['name' => 'View Reservations', 'slug' => 'reservations.view'],
            ['name' => 'Manage Reservations', 'slug' => 'reservations.manage'],
            ['name' => 'View Kitchen', 'slug' => 'kitchen.view'],
            ['name' => 'Manage Kitchen', 'slug' => 'kitchen.manage'],
            ['name' => 'View KDS', 'slug' => 'kds.view'],
            ['name' => 'Manage KDS', 'slug' => 'kds.manage'],
            ['name' => 'View Modifiers', 'slug' => 'modifiers.view'],
            ['name' => 'Manage Modifiers', 'slug' => 'modifiers.manage'],
            ['name' => 'View Recipes', 'slug' => 'recipes.view'],
            ['name' => 'Manage Recipes', 'slug' => 'recipes.manage'],
            ['name' => 'View Bill Splits', 'slug' => 'billsplit.view'],
            ['name' => 'Manage Bill Splits', 'slug' => 'billsplit.manage'],
            // Phase 8B — Retail
            ['name' => 'View Promotions', 'slug' => 'promotions.view'],
            ['name' => 'Manage Promotions', 'slug' => 'promotions.manage'],
            ['name' => 'View Loyalty', 'slug' => 'loyalty.view'],
            ['name' => 'Manage Loyalty', 'slug' => 'loyalty.manage'],
            ['name' => 'View Price Tags', 'slug' => 'pricetags.view'],
            ['name' => 'Manage Price Tags', 'slug' => 'pricetags.manage'],
            // Phase 8C — Service
            ['name' => 'View Appointments', 'slug' => 'appointments.view'],
            ['name' => 'Manage Appointments', 'slug' => 'appointments.manage'],
            ['name' => 'View Services', 'slug' => 'services.view'],
            ['name' => 'Manage Services', 'slug' => 'services.manage'],
            ['name' => 'View Staff Schedules', 'slug' => 'staff.schedule.view'],
            ['name' => 'Manage Staff Schedules', 'slug' => 'staff.schedule.manage'],
            // Phase 9 — Integration & Webhooks
            ['name' => 'View Integrations', 'slug' => 'integrations.view'],
            ['name' => 'Manage Integrations', 'slug' => 'integrations.manage'],
            ['name' => 'View Webhooks', 'slug' => 'webhooks.view'],
            ['name' => 'Manage Webhooks', 'slug' => 'webhooks.manage'],
            ['name' => 'View API Keys', 'slug' => 'apikeys.view'],
            ['name' => 'Manage API Keys', 'slug' => 'apikeys.manage'],
            // Phase 10 — Security
            ['name' => 'Owner Access', 'slug' => 'owner'],
            ['name' => 'View Audit Logs', 'slug' => 'security.audit.view'],
            ['name' => 'Unlock User Account', 'slug' => 'security.account.unlock'],
            ['name' => 'Reset 2FA', 'slug' => 'security.2fa.reset'],
        ];

        foreach ($permissions as $permission) {
            $moduleSlug = $permissionModuleMap[$permission['slug']] ?? null;
            $moduleId = $moduleSlug ? Module::where('slug', $moduleSlug)->value('id') : null;

            Permission::firstOrCreate(
                ['slug' => $permission['slug']],
                array_merge($permission, ['module_id' => $moduleId])
            );
        }

        $owner = Role::where('slug', 'owner')->first();
        $owner->permissions()->sync(Permission::all()->pluck('id')->unique()->values());

        $manager = Role::where('slug', 'manager')->first();
        $manager->permissions()->sync(
            Permission::whereIn('slug', [
                'products.view', 'products.manage',
                'categories.view', 'categories.manage',
                'sales.view', 'sales.manage',
                'inventory.view', 'inventory.manage',
                'inventory.stocktake', 'inventory.valuation',
                'warehouse.view', 'warehouse.manage',
                'customers.view', 'customers.manage',
                'suppliers.view', 'suppliers.manage',
                'purchases.view', 'purchases.manage',
                'purchasing.requisition', 'purchasing.grn', 'purchasing.invoice_match',
                'crm.view', 'crm.manage',
                'reports.view', 'reports.export', 'reports.dashboard.manage', 'reports.comparison', 'reports.financial',
                'pos.use',
                'pos.hold_sale', 'pos.refund', 'pos.discount_presets',
                'payments.view', 'payments.manage', 'payments.cash_drawer',
                'finance.view', 'finance.manage', 'finance.post_journals', 'finance.reports',
                // Phase 8
                'tables.view', 'tables.manage',
                'reservations.view', 'reservations.manage',
                'kitchen.view', 'kitchen.manage',
                'kds.view', 'kds.manage',
                'modifiers.view', 'modifiers.manage',
                'recipes.view', 'recipes.manage',
                'billsplit.view', 'billsplit.manage',
                'promotions.view', 'promotions.manage',
                'loyalty.view', 'loyalty.manage',
                'pricetags.view', 'pricetags.manage',
                'appointments.view', 'appointments.manage',
                'services.view', 'services.manage',
                'staff.schedule.view', 'staff.schedule.manage',
                // Phase 9 — Integration & Webhooks
                'integrations.view',
                'webhooks.view',
                'apikeys.view',
            ])->pluck('id')->unique()->values()
        );

        $cashier = Role::where('slug', 'cashier')->first();
        $cashier->permissions()->sync(
            Permission::whereIn('slug', [
                'products.view',
                'categories.view',
                'inventory.view',
                'warehouse.view',
                'sales.view', 'sales.manage',
                'customers.view', 'customers.manage',
                'suppliers.view',
                'purchases.view',
                'crm.view',
                'pos.use',
                'pos.hold_sale',
                // Phase 8 — cashier can view/interact with restaurant modules
                'tables.view',
                'reservations.view',
                'kitchen.view',
                'kds.view',
                'modifiers.view',
                'recipes.view',
                'billsplit.view', 'billsplit.manage',
                'promotions.view',
                'loyalty.view',
                'appointments.view', 'appointments.manage',
                'services.view',
            ])->pluck('id')->unique()->values()
        );

        $staff = Role::where('slug', 'staff')->first();
        $staff->permissions()->sync(
            Permission::whereIn('slug', [
                'products.view',
                'categories.view',
                'inventory.view',
                'customers.view',
                // Phase 8 — staff can view kitchen/KDS
                'kitchen.view',
                'kds.view',
                'tables.view',
            ])->pluck('id')->unique()->values()
        );

        $accountant = Role::where('slug', 'accountant')->first();
        $accountant->permissions()->sync(
            Permission::whereIn('slug', [
                'finance.view', 'finance.manage', 'finance.post_journals', 'finance.reports',
                'reports.view', 'reports.export', 'reports.financial',
                'sales.view',
                'purchases.view',
                'purchasing.invoice_match',
                'audit.view',
                'products.view',
                'categories.view',
                'inventory.view',
                'inventory.valuation',
                'warehouse.view',
                'crm.view',
                'payments.view', 'payments.reconcile',
            ])->pluck('id')->unique()->values()
        );
    }
}
