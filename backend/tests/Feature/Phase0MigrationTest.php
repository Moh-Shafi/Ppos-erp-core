<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase0MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('modules'));
        $this->assertTrue(Schema::hasTable('features'));
        $this->assertTrue(Schema::hasTable('business_types'));
        $this->assertTrue(Schema::hasTable('business_type_modules'));
        $this->assertTrue(Schema::hasTable('business_type_features'));
        $this->assertTrue(Schema::hasTable('business_profiles'));
        $this->assertTrue(Schema::hasTable('tenant_modules'));
        $this->assertTrue(Schema::hasTable('tenant_features'));
        $this->assertTrue(Schema::hasTable('user_roles'));
        $this->assertTrue(Schema::hasTable('audit_logs'));
    }

    public function test_existing_tables_still_exist(): void
    {
        $this->assertTrue(Schema::hasTable('plans'));
        $this->assertTrue(Schema::hasTable('tenants'));
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('stores'));
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasTable('role_permissions'));
        $this->assertTrue(Schema::hasTable('categories'));
        $this->assertTrue(Schema::hasTable('products'));
        $this->assertTrue(Schema::hasTable('inventories'));
        $this->assertTrue(Schema::hasTable('inventory_movements'));
        $this->assertTrue(Schema::hasTable('customers'));
        $this->assertTrue(Schema::hasTable('suppliers'));
        $this->assertTrue(Schema::hasTable('purchases'));
        $this->assertTrue(Schema::hasTable('purchase_items'));
        $this->assertTrue(Schema::hasTable('purchase_returns'));
        $this->assertTrue(Schema::hasTable('purchase_return_items'));
        $this->assertTrue(Schema::hasTable('sales'));
        $this->assertTrue(Schema::hasTable('sale_items'));
        $this->assertTrue(Schema::hasTable('payments'));
    }

    public function test_roles_table_has_new_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('roles', 'tenant_id'));
        $this->assertTrue(Schema::hasColumn('roles', 'is_system'));
        $this->assertTrue(Schema::hasColumn('roles', 'sort_order'));
    }

    public function test_permissions_table_has_module_id(): void
    {
        $this->assertTrue(Schema::hasColumn('permissions', 'module_id'));
    }

    public function test_stores_table_has_new_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('stores', 'city'));
        $this->assertTrue(Schema::hasColumn('stores', 'province'));
        $this->assertTrue(Schema::hasColumn('stores', 'postal_code'));
        $this->assertTrue(Schema::hasColumn('stores', 'email'));
        $this->assertTrue(Schema::hasColumn('stores', 'is_headquarters'));
        $this->assertTrue(Schema::hasColumn('stores', 'settings'));
    }

    public function test_audit_logs_table_structure(): void
    {
        $this->assertTrue(Schema::hasColumn('audit_logs', 'tenant_id'));
        $this->assertTrue(Schema::hasColumn('audit_logs', 'user_id'));
        $this->assertTrue(Schema::hasColumn('audit_logs', 'action'));
        $this->assertTrue(Schema::hasColumn('audit_logs', 'entity_type'));
        $this->assertTrue(Schema::hasColumn('audit_logs', 'entity_id'));
        $this->assertTrue(Schema::hasColumn('audit_logs', 'old_values'));
        $this->assertTrue(Schema::hasColumn('audit_logs', 'new_values'));
        $this->assertTrue(Schema::hasColumn('audit_logs', 'ip_address'));
        $this->assertTrue(Schema::hasColumn('audit_logs', 'user_agent'));
    }

    public function test_business_profiles_table_structure(): void
    {
        $this->assertTrue(Schema::hasColumn('business_profiles', 'tenant_id'));
        $this->assertTrue(Schema::hasColumn('business_profiles', 'business_type_id'));
        $this->assertTrue(Schema::hasColumn('business_profiles', 'business_name'));
        $this->assertTrue(Schema::hasColumn('business_profiles', 'timezone'));
        $this->assertTrue(Schema::hasColumn('business_profiles', 'currency'));
        $this->assertTrue(Schema::hasColumn('business_profiles', 'locale'));
    }

    public function test_tenant_modules_unique_constraint(): void
    {
        $this->assertTrue(Schema::hasTable('tenant_modules'));
        $indexes = Schema::getIndexes('tenant_modules');
        $hasUnique = false;
        foreach ($indexes as $index) {
            if ($index['unique'] && in_array('tenant_id', $index['columns']) && in_array('module_id', $index['columns'])) {
                $hasUnique = true;
                break;
            }
        }
        $this->assertTrue($hasUnique, 'tenant_modules should have unique constraint on [tenant_id, module_id]');
    }

    public function test_tenant_features_unique_constraint(): void
    {
        $indexes = Schema::getIndexes('tenant_features');
        $hasUnique = false;
        foreach ($indexes as $index) {
            if ($index['unique'] && in_array('tenant_id', $index['columns']) && in_array('feature_id', $index['columns'])) {
                $hasUnique = true;
                break;
            }
        }
        $this->assertTrue($hasUnique, 'tenant_features should have unique constraint on [tenant_id, feature_id]');
    }
}
