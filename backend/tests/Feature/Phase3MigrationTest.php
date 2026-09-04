<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class Phase3MigrationTest extends Phase3TestHelper
{
    public function test_customers_table_has_new_columns(): void
    {
        $this->setupPhase3();

        $this->assertTrue(Schema::hasColumn('customers', 'credit_limit'));
        $this->assertTrue(Schema::hasColumn('customers', 'outstanding_balance'));
        $this->assertTrue(Schema::hasColumn('customers', 'price_list_id'));
    }

    public function test_purchases_table_has_new_columns(): void
    {
        $this->setupPhase3();

        $this->assertTrue(Schema::hasColumn('purchases', 'requisition_id'));
        $this->assertTrue(Schema::hasColumn('purchases', 'grn_id'));
    }

    public function test_customer_loyalty_points_table_exists(): void
    {
        $this->setupPhase3();

        $this->assertTrue(Schema::hasTable('customer_loyalty_points'));
        $this->assertTrue(Schema::hasColumn('customer_loyalty_points', 'points_balance'));
        $this->assertTrue(Schema::hasColumn('customer_loyalty_points', 'total_earned'));
        $this->assertTrue(Schema::hasColumn('customer_loyalty_points', 'total_redeemed'));
    }

    public function test_customer_loyalty_transactions_table_exists(): void
    {
        $this->setupPhase3();

        $this->assertTrue(Schema::hasTable('customer_loyalty_transactions'));
        $this->assertTrue(Schema::hasColumn('customer_loyalty_transactions', 'type'));
        $this->assertTrue(Schema::hasColumn('customer_loyalty_transactions', 'balance_after'));
    }

    public function test_customer_credit_transactions_table_exists(): void
    {
        $this->setupPhase3();

        $this->assertTrue(Schema::hasTable('customer_credit_transactions'));
        $this->assertTrue(Schema::hasColumn('customer_credit_transactions', 'amount'));
        $this->assertTrue(Schema::hasColumn('customer_credit_transactions', 'balance_after'));
    }

    public function test_supplier_ratings_table_exists(): void
    {
        $this->setupPhase3();

        $this->assertTrue(Schema::hasTable('supplier_ratings'));
        $this->assertTrue(Schema::hasColumn('supplier_ratings', 'rating'));
        $this->assertTrue(Schema::hasColumn('supplier_ratings', 'criteria'));
    }

    public function test_purchase_requisitions_table_exists(): void
    {
        $this->setupPhase3();

        $this->assertTrue(Schema::hasTable('purchase_requisitions'));
        $this->assertTrue(Schema::hasColumn('purchase_requisitions', 'request_number'));
        $this->assertTrue(Schema::hasColumn('purchase_requisitions', 'status'));
    }

    public function test_purchase_requisition_items_table_exists(): void
    {
        $this->setupPhase3();

        $this->assertTrue(Schema::hasTable('purchase_requisition_items'));
        $this->assertTrue(Schema::hasColumn('purchase_requisition_items', 'quantity'));
    }

    public function test_goods_receipt_notes_table_exists(): void
    {
        $this->setupPhase3();

        $this->assertTrue(Schema::hasTable('goods_receipt_notes'));
        $this->assertTrue(Schema::hasColumn('goods_receipt_notes', 'grn_number'));
        $this->assertTrue(Schema::hasColumn('goods_receipt_notes', 'status'));
    }

    public function test_grn_items_table_exists(): void
    {
        $this->setupPhase3();

        $this->assertTrue(Schema::hasTable('grn_items'));
        $this->assertTrue(Schema::hasColumn('grn_items', 'quantity_ordered'));
        $this->assertTrue(Schema::hasColumn('grn_items', 'quantity_received'));
        $this->assertTrue(Schema::hasColumn('grn_items', 'quantity_rejected'));
    }

    public function test_supplier_invoices_table_exists(): void
    {
        $this->setupPhase3();

        $this->assertTrue(Schema::hasTable('supplier_invoices'));
        $this->assertTrue(Schema::hasColumn('supplier_invoices', 'match_result'));
        $this->assertTrue(Schema::hasColumn('supplier_invoices', 'status'));
    }

    public function test_all_new_tables_have_tenant_id(): void
    {
        $this->setupPhase3();

        $tables = [
            'customer_loyalty_points',
            'customer_loyalty_transactions',
            'customer_credit_transactions',
            'supplier_ratings',
            'purchase_requisitions',
            'purchase_requisition_items',
            'goods_receipt_notes',
            'grn_items',
            'supplier_invoices',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'tenant_id'), "Table {$table} missing tenant_id");
        }
    }
}
