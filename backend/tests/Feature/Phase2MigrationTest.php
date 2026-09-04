<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Store;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\StockBatch;
use App\Models\StockAdjustmentReason;
use App\Models\StocktakeSession;
use App\Models\StocktakeItem;
use App\Models\TransferRequest;
use App\Models\TransferRequestItem;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Services\InventoryService;

class Phase2MigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        $this->seed(\Database\Seeders\BusinessTypeSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    public function test_warehouses_table_exists(): void
    {
        $this->assertTrue(\Schema::hasTable('warehouses'));
        $this->assertTrue(\Schema::hasColumns('warehouses', ['id', 'tenant_id', 'name', 'address', 'phone', 'is_active']));
    }

    public function test_warehouse_stocks_table_exists(): void
    {
        $this->assertTrue(\Schema::hasTable('warehouse_stocks'));
        $this->assertTrue(\Schema::hasColumns('warehouse_stocks', ['id', 'tenant_id', 'warehouse_id', 'product_id', 'quantity', 'batch_id', 'expiry_date']));
    }

    public function test_stock_batches_table_exists(): void
    {
        $this->assertTrue(\Schema::hasTable('stock_batches'));
        $this->assertTrue(\Schema::hasColumns('stock_batches', ['id', 'tenant_id', 'product_id', 'batch_number', 'quantity', 'received_date', 'expiry_date', 'cost_price']));
    }

    public function test_stock_adjustment_reasons_table_exists(): void
    {
        $this->assertTrue(\Schema::hasTable('stock_adjustment_reasons'));
        $this->assertTrue(\Schema::hasColumns('stock_adjustment_reasons', ['id', 'tenant_id', 'name', 'category', 'is_system', 'is_active']));
    }

    public function test_stocktake_sessions_table_exists(): void
    {
        $this->assertTrue(\Schema::hasTable('stocktake_sessions'));
        $this->assertTrue(\Schema::hasColumns('stocktake_sessions', ['id', 'tenant_id', 'store_id', 'session_number', 'status', 'created_by', 'started_at', 'completed_at']));
    }

    public function test_stocktake_items_table_exists(): void
    {
        $this->assertTrue(\Schema::hasTable('stocktake_items'));
        $this->assertTrue(\Schema::hasColumns('stocktake_items', ['id', 'tenant_id', 'stocktake_session_id', 'product_id', 'system_quantity', 'counted_quantity', 'variance']));
    }

    public function test_transfer_requests_table_exists(): void
    {
        $this->assertTrue(\Schema::hasTable('transfer_requests'));
        $this->assertTrue(\Schema::hasColumns('transfer_requests', ['id', 'tenant_id', 'request_number', 'from_store_id', 'from_warehouse_id', 'to_store_id', 'to_warehouse_id', 'status', 'requested_by', 'approved_by', 'approved_at']));
    }

    public function test_transfer_request_items_table_exists(): void
    {
        $this->assertTrue(\Schema::hasTable('transfer_request_items'));
        $this->assertTrue(\Schema::hasColumns('transfer_request_items', ['id', 'tenant_id', 'transfer_request_id', 'product_id', 'quantity', 'batch_id']));
    }

    public function test_inventories_has_batch_and_expiry_columns(): void
    {
        $this->assertTrue(\Schema::hasColumn('inventories', 'batch_id'));
        $this->assertTrue(\Schema::hasColumn('inventories', 'expiry_date'));
        $this->assertTrue(\Schema::hasColumn('inventories', 'maximum_quantity'));
    }

    public function test_inventory_movements_has_batch_and_reason_columns(): void
    {
        $this->assertTrue(\Schema::hasColumn('inventory_movements', 'batch_id'));
        $this->assertTrue(\Schema::hasColumn('inventory_movements', 'reason_id'));
    }

    public function test_phase2_permissions_seeded(): void
    {
        $this->assertDatabaseHas('permissions', ['slug' => 'warehouse.view']);
        $this->assertDatabaseHas('permissions', ['slug' => 'warehouse.manage']);
        $this->assertDatabaseHas('permissions', ['slug' => 'inventory.stocktake']);
        $this->assertDatabaseHas('permissions', ['slug' => 'inventory.valuation']);
    }

    public function test_phase2_features_seeded(): void
    {
        $this->assertDatabaseHas('features', ['slug' => 'inventory.transfer_request']);
        $this->assertDatabaseHas('features', ['slug' => 'inventory.valuation']);
    }
}
