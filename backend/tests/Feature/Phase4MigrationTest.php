<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase4MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_held_sales_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('held_sales'));
        $columns = Schema::getColumnListing('held_sales');
        $this->assertContains('id', $columns);
        $this->assertContains('tenant_id', $columns);
        $this->assertContains('store_id', $columns);
        $this->assertContains('cashier_id', $columns);
        $this->assertContains('customer_id', $columns);
        $this->assertContains('cart_data', $columns);
        $this->assertContains('hold_number', $columns);
        $this->assertContains('status', $columns);
        $this->assertContains('held_at', $columns);
        $this->assertContains('recalled_at', $columns);
        $this->assertContains('expires_at', $columns);
    }

    public function test_discount_presets_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('discount_presets'));
        $columns = Schema::getColumnListing('discount_presets');
        $this->assertContains('id', $columns);
        $this->assertContains('tenant_id', $columns);
        $this->assertContains('name', $columns);
        $this->assertContains('type', $columns);
        $this->assertContains('value', $columns);
        $this->assertContains('is_active', $columns);
        $this->assertContains('sort_order', $columns);
    }

    public function test_sale_refunds_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('sale_refunds'));
        $columns = Schema::getColumnListing('sale_refunds');
        $this->assertContains('id', $columns);
        $this->assertContains('tenant_id', $columns);
        $this->assertContains('sale_id', $columns);
        $this->assertContains('refunded_by', $columns);
        $this->assertContains('type', $columns);
        $this->assertContains('refund_reason', $columns);
        $this->assertContains('refund_amount', $columns);
        $this->assertContains('status', $columns);
        $this->assertContains('refunded_at', $columns);
    }

    public function test_sale_refund_items_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('sale_refund_items'));
        $columns = Schema::getColumnListing('sale_refund_items');
        $this->assertContains('id', $columns);
        $this->assertContains('sale_refund_id', $columns);
        $this->assertContains('sale_item_id', $columns);
        $this->assertContains('product_id', $columns);
        $this->assertContains('quantity', $columns);
        $this->assertContains('unit_price', $columns);
        $this->assertContains('refund_amount', $columns);
    }

    public function test_sales_table_has_phase4_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('sales', 'hold_status'));
        $this->assertTrue(Schema::hasColumn('sales', 'held_at'));
        $this->assertTrue(Schema::hasColumn('sales', 'refunded_amount'));
        $this->assertTrue(Schema::hasColumn('sales', 'refund_status'));
        $this->assertTrue(Schema::hasColumn('sales', 'price_list_id'));
    }

    public function test_sale_items_table_has_phase4_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('sale_items', 'variant_id'));
        $this->assertTrue(Schema::hasColumn('sale_items', 'original_price'));
    }

    public function test_stores_table_has_receipt_settings(): void
    {
        $this->assertTrue(Schema::hasColumn('stores', 'receipt_settings'));
    }

    public function test_payments_table_has_phase4_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('payments', 'refund_amount'));
        $this->assertTrue(Schema::hasColumn('payments', 'refund_status'));
    }

    public function test_held_sales_unique_hold_number_per_tenant(): void
    {
        $this->assertTrue(
            collect(Schema::getIndexes('held_sales'))
                ->contains(fn ($idx) => ($idx['name'] ?? '') === 'held_sales_tenant_id_hold_number_unique')
        );
    }
}
