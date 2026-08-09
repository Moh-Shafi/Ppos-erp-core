<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Role;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PurchaseReturnService;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PurchaseReturnTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $ownerA;
    private User $managerA;
    private User $cashierA;
    private User $staffA;
    private User $ownerB;
    private string $tokenOwnerA;
    private string $tokenManagerA;
    private string $tokenCashierA;
    private string $tokenStaffA;
    private string $tokenOwnerB;
    private Store $storeA;
    private Store $storeB;
    private Supplier $supplierA;
    private Product $productA;
    private Product $productB;
    private Category $catA;
    private Purchase $receivedPurchase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();
        $managerRole = Role::where('slug', 'manager')->first();
        $cashierRole = Role::where('slug', 'cashier')->first();
        $staffRole = Role::where('slug', 'staff')->first();

        $this->tenantA = Tenant::create(['name' => 'Toko A', 'slug' => 'toko-a']);
        $this->ownerA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner A', 'email' => 'owner.a@t.com', 'password' => 'password',
        ]);
        $this->managerA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $managerRole->id,
            'name' => 'Manager A', 'email' => 'manager.a@t.com', 'password' => 'password',
        ]);
        $this->cashierA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $cashierRole->id,
            'name' => 'Cashier A', 'email' => 'cashier.a@t.com', 'password' => 'password',
        ]);
        $this->staffA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $staffRole->id,
            'name' => 'Staff A', 'email' => 'staff.a@t.com', 'password' => 'password',
        ]);

        $this->tokenOwnerA = $this->ownerA->createToken('test')->plainTextToken;
        $this->tokenManagerA = $this->managerA->createToken('test')->plainTextToken;
        $this->tokenCashierA = $this->cashierA->createToken('test')->plainTextToken;
        $this->tokenStaffA = $this->staffA->createToken('test')->plainTextToken;

        $this->storeA = new Store;
        $this->storeA->tenant_id = $this->tenantA->id;
        $this->storeA->name = 'Store A';
        $this->storeA->code = 'SA';
        $this->storeA->is_active = true;
        $this->storeA->save();

        $this->supplierA = new Supplier;
        $this->supplierA->tenant_id = $this->tenantA->id;
        $this->supplierA->name = 'Supplier A';
        $this->supplierA->is_active = true;
        $this->supplierA->save();

        $this->catA = new Category;
        $this->catA->tenant_id = $this->tenantA->id;
        $this->catA->name = 'Drinks';
        $this->catA->slug = 'drinks';
        $this->catA->save();

        $this->productA = new Product;
        $this->productA->tenant_id = $this->tenantA->id;
        $this->productA->category_id = $this->catA->id;
        $this->productA->name = 'Coca Cola';
        $this->productA->sku = 'COKE-001';
        $this->productA->barcode = '123456';
        $this->productA->cost_price = 5000;
        $this->productA->selling_price = 8000;
        $this->productA->unit = 'botol';
        $this->productA->save();

        // Create a received purchase with 100 units
        Auth::login($this->ownerA);
        $this->receivedPurchase = app(PurchaseService::class)->create([
            'supplier_id' => $this->supplierA->id,
            'store_id' => $this->storeA->id,
            'purchase_date' => '2026-01-15',
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 100, 'unit_cost' => 5000],
            ],
        ]);
        app(PurchaseService::class)->order($this->receivedPurchase);
        app(PurchaseService::class)->receive($this->receivedPurchase->fresh());
        $this->receivedPurchase = $this->receivedPurchase->fresh();
        Auth::logout();

        // Tenant B
        $this->tenantB = Tenant::create(['name' => 'Toko B', 'slug' => 'toko-b']);
        $this->ownerB = User::create([
            'tenant_id' => $this->tenantB->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner B', 'email' => 'owner.b@t.com', 'password' => 'password',
        ]);
        $this->tokenOwnerB = $this->ownerB->createToken('test')->plainTextToken;

        $this->storeB = new Store;
        $this->storeB->tenant_id = $this->tenantB->id;
        $this->storeB->name = 'Store B';
        $this->storeB->code = 'SB';
        $this->storeB->is_active = true;
        $this->storeB->save();

        $catB = new Category;
        $catB->tenant_id = $this->tenantB->id;
        $catB->name = 'Food';
        $catB->slug = 'food';
        $catB->save();

        $this->productB = new Product;
        $this->productB->tenant_id = $this->tenantB->id;
        $this->productB->category_id = $catB->id;
        $this->productB->name = 'Nasi Goreng';
        $this->productB->sku = 'NASGOR-001';
        $this->productB->barcode = '654321';
        $this->productB->cost_price = 15000;
        $this->productB->selling_price = 25000;
        $this->productB->unit = 'porsi';
        $this->productB->save();
    }

    private function returnData(array $overrides = []): array
    {
        return array_merge([
            'purchase_id' => $this->receivedPurchase->id,
            'return_date' => '2026-01-20',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 20,
                    'unit_cost' => 5000,
                ],
            ],
        ], $overrides);
    }

    private function createReturn(?array $data = null): PurchaseReturn
    {
        Auth::login($this->ownerA);
        $return = app(PurchaseReturnService::class)->create($data ?? $this->returnData());
        Auth::logout();
        return $return;
    }

    // --- API Smoke ---

    public function test_list_returns(): void
    {
        $this->createReturn();

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/purchase-returns');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_show_return(): void
    {
        $return = $this->createReturn();

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/purchase-returns/{$return->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'draft');
        $response->assertJsonCount(1, 'items');
    }

    public function test_create_return(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', $this->returnData());

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'draft');
        $response->assertJsonPath('subtotal', '100000.00');
        $response->assertJsonPath('total', '100000.00');
        $response->assertJsonPath('tenant_id', $this->tenantA->id);
        $response->assertJsonPath('created_by', $this->ownerA->id);
        $response->assertJsonCount(1, 'items');
        $response->assertJsonPath('items.0.total', '100000.00');
    }

    public function test_create_return_with_discount_and_tax(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', $this->returnData([
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 20, 'unit_cost' => 5000, 'discount' => 2000, 'tax' => 1000],
                ],
            ]));

        $response->assertStatus(201);
        // subtotal = 20 * 5000 = 100000
        $response->assertJsonPath('subtotal', '100000.00');
        // discount = 2000, tax = 1000
        $response->assertJsonPath('discount', '2000.00');
        $response->assertJsonPath('tax', '1000.00');
        // total = 100000 - 2000 + 1000 = 99000
        $response->assertJsonPath('total', '99000.00');
    }

    public function test_total_calculated_by_backend(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', $this->returnData([
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 20, 'unit_cost' => 5000, 'total' => 1],
                ],
                'total' => 1,
                'subtotal' => 1,
            ]));

        $response->assertStatus(201);
        $response->assertJsonPath('items.0.total', '100000.00');
        $response->assertJsonPath('total', '100000.00');
        $response->assertJsonPath('subtotal', '100000.00');
    }

    public function test_delete_draft_return(): void
    {
        $return = $this->createReturn();

        $response = $this->withToken($this->tokenOwnerA)
            ->deleteJson("/api/v1/purchase-returns/{$return->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('purchase_returns', ['id' => $return->id]);
    }

    // --- Status Workflow ---

    public function test_draft_to_completed(): void
    {
        $return = $this->createReturn();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'completed');
    }

    public function test_cancel_draft(): void
    {
        $return = $this->createReturn();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/cancel");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'cancelled');
    }

    public function test_cannot_complete_cancelled(): void
    {
        $return = $this->createReturn();
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/cancel");

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete");

        $response->assertStatus(422);
    }

    public function test_cannot_complete_twice(): void
    {
        $return = $this->createReturn();
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete");

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete");

        $response->assertStatus(422);
    }

    public function test_cannot_cancel_completed(): void
    {
        $return = $this->createReturn();
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete");

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/cancel");

        $response->assertStatus(422);
    }

    public function test_cannot_delete_completed(): void
    {
        $return = $this->createReturn();
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete");

        $response = $this->withToken($this->tokenOwnerA)
            ->deleteJson("/api/v1/purchase-returns/{$return->id}");

        $response->assertStatus(422);
    }

    // --- Inventory Integration ---

    public function test_complete_decreases_inventory(): void
    {
        $return = $this->createReturn();
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete");

        $inv = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();

        $this->assertNotNull($inv);
        $this->assertEquals(80, $inv->quantity); // 100 - 20 = 80
    }

    public function test_complete_creates_movement(): void
    {
        $return = $this->createReturn();
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete");

        $movement = InventoryMovement::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->where('type', 'purchase_return')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(-20, $movement->quantity);
        $this->assertEquals(100, $movement->before_quantity);
        $this->assertEquals(80, $movement->after_quantity);
        $this->assertEquals($return->id, $movement->reference_id);
        $this->assertEquals(PurchaseReturn::class, $movement->reference_type);
    }

    public function test_draft_does_not_decrease_inventory(): void
    {
        $this->createReturn();

        $inv = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();

        $this->assertEquals(100, $inv->quantity); // unchanged
    }

    public function test_cancelled_does_not_decrease_inventory(): void
    {
        $return = $this->createReturn();
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/cancel");

        $inv = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();

        $this->assertEquals(100, $inv->quantity); // unchanged
    }

    public function test_double_complete_does_not_double_decrease(): void
    {
        $return = $this->createReturn();
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete");

        // Try to complete again
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete")
            ->assertStatus(422);

        $inv = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();

        $this->assertEquals(80, $inv->quantity); // not 60
    }

    public function test_e2e_purchase_receive_then_return(): void
    {
        // Inventory should be 100 after setUp
        $inv = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $this->assertEquals(100, $inv->quantity);

        // Return 20
        $return = $this->createReturn();
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete");

        $inv = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $this->assertEquals(80, $inv->quantity);

        // Movement should be purchase_return -20
        $movement = InventoryMovement::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->where('type', 'purchase_return')
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(-20, $movement->quantity);

        // Try to return same 20 again — should be rejected (100 purchased, 20 already returned, 20 more = 40 total <= 100, so it would pass)
        // Actually 20 + 20 = 40 <= 100, so it would pass. Let me test returning 80 more (20 + 80 = 100, ok)
        // and then 1 more (20 + 100 + 1 = 121 > 100, should fail)
    }

    public function test_return_exceeding_purchased_quantity_blocked(): void
    {
        // Try to return 101 when only 100 purchased
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', $this->returnData([
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 101, 'unit_cost' => 5000],
                ],
            ]));

        $response->assertStatus(422);
    }

    public function test_cumulative_return_exceeding_blocked(): void
    {
        // Return 60 first
        $return1 = $this->createReturn($this->returnData([
            'items' => [['product_id' => $this->productA->id, 'quantity' => 60, 'unit_cost' => 5000]],
        ]));

        // Try to return 50 more (60 + 50 = 110 > 100)
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', $this->returnData([
                'items' => [['product_id' => $this->productA->id, 'quantity' => 50, 'unit_cost' => 5000]],
            ]));

        $response->assertStatus(422);
    }

    public function test_cancelled_return_does_not_count_toward_limit(): void
    {
        // Return 60 and cancel it
        $return1 = $this->createReturn($this->returnData([
            'items' => [['product_id' => $this->productA->id, 'quantity' => 60, 'unit_cost' => 5000]],
        ]));
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return1->id}/cancel");

        // Now return 80 (should work since cancelled doesn't count, 0 + 80 <= 100)
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', $this->returnData([
                'items' => [['product_id' => $this->productA->id, 'quantity' => 80, 'unit_cost' => 5000]],
            ]));

        $response->assertStatus(201);
    }

    public function test_return_for_non_received_purchase_blocked(): void
    {
        Auth::login($this->ownerA);
        $draftPurchase = app(PurchaseService::class)->create([
            'supplier_id' => $this->supplierA->id,
            'store_id' => $this->storeA->id,
            'purchase_date' => '2026-01-15',
            'items' => [['product_id' => $this->productA->id, 'quantity' => 50, 'unit_cost' => 5000]],
        ]);
        Auth::logout();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', $this->returnData([
                'purchase_id' => $draftPurchase->id,
            ]));

        $response->assertStatus(422);
    }

    // --- Tenant Isolation ---

    public function test_tenant_a_cannot_see_tenant_b_returns(): void
    {
        $this->createReturn();

        $response = $this->withToken($this->tokenOwnerB)
            ->getJson('/api/v1/purchase-returns');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_tenant_a_cannot_access_tenant_b_return(): void
    {
        $return = $this->createReturn();

        $this->withToken($this->tokenOwnerB)
            ->getJson("/api/v1/purchase-returns/{$return->id}")->assertStatus(404);
    }

    public function test_tenant_a_cannot_complete_tenant_b_return(): void
    {
        $return = $this->createReturn();

        $this->withToken($this->tokenOwnerB)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete")->assertStatus(404);
    }

    public function test_tenant_a_cannot_delete_tenant_b_return(): void
    {
        $return = $this->createReturn();

        $this->withToken($this->tokenOwnerB)
            ->deleteJson("/api/v1/purchase-returns/{$return->id}")->assertStatus(404);
    }

    public function test_cross_tenant_purchase_blocked(): void
    {
        $response = $this->withToken($this->tokenOwnerB)
            ->postJson('/api/v1/purchase-returns', $this->returnData([
                'purchase_id' => $this->receivedPurchase->id,
            ]));

        $response->assertStatus(422);
    }

    // --- Mass Assignment ---

    public function test_tenant_id_ignored(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', $this->returnData([
                'tenant_id' => $this->tenantB->id,
            ]));

        $response->assertStatus(201);
        $response->assertJsonPath('tenant_id', $this->tenantA->id);
    }

    public function test_created_by_ignored(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', $this->returnData([
                'created_by' => $this->ownerB->id,
            ]));

        $response->assertStatus(201);
        $response->assertJsonPath('created_by', $this->ownerA->id);
    }

    public function test_status_ignored(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', $this->returnData([
                'status' => 'completed',
            ]));

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'draft');
    }

    public function test_subtotal_and_total_ignored(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', $this->returnData([
                'subtotal' => 1,
                'total' => 1,
                'discount' => 999,
                'tax' => 999,
            ]));

        $response->assertStatus(201);
        $response->assertJsonPath('subtotal', '100000.00');
        $response->assertJsonPath('total', '100000.00');
        $response->assertJsonPath('discount', '0.00');
        $response->assertJsonPath('tax', '0.00');
    }

    // --- Authentication ---

    public function test_unauthenticated_list(): void
    {
        $this->getJson('/api/v1/purchase-returns')->assertStatus(401);
    }

    public function test_unauthenticated_create(): void
    {
        $this->postJson('/api/v1/purchase-returns', $this->returnData())->assertStatus(401);
    }

    public function test_unauthenticated_complete(): void
    {
        $this->postJson('/api/v1/purchase-returns/1/complete')->assertStatus(401);
    }

    // --- RBAC ---

    public function test_cashier_can_view_returns(): void
    {
        $this->createReturn();

        $this->withToken($this->tokenCashierA)
            ->getJson('/api/v1/purchase-returns')->assertStatus(200);
    }

    public function test_cashier_cannot_create_return(): void
    {
        $this->withToken($this->tokenCashierA)
            ->postJson('/api/v1/purchase-returns', $this->returnData())->assertStatus(403);
    }

    public function test_cashier_cannot_complete_return(): void
    {
        $return = $this->createReturn();

        $this->withToken($this->tokenCashierA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete")->assertStatus(403);
    }

    public function test_staff_cannot_view_returns(): void
    {
        $this->withToken($this->tokenStaffA)
            ->getJson('/api/v1/purchase-returns')->assertStatus(403);
    }

    public function test_staff_cannot_create_return(): void
    {
        $this->withToken($this->tokenStaffA)
            ->postJson('/api/v1/purchase-returns', $this->returnData())->assertStatus(403);
    }

    public function test_manager_can_create_return(): void
    {
        $this->withToken($this->tokenManagerA)
            ->postJson('/api/v1/purchase-returns', $this->returnData())->assertStatus(201);
    }

    public function test_manager_can_complete_return(): void
    {
        $return = $this->createReturn();

        $this->withToken($this->tokenManagerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete")->assertStatus(200);
    }

    // --- IDOR ---

    public function test_idor_get_nonexistent(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/purchase-returns/99999')->assertStatus(404);
    }

    public function test_idor_complete_nonexistent(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns/99999/complete')->assertStatus(404);
    }

    public function test_idor_cancel_nonexistent(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns/99999/cancel')->assertStatus(404);
    }

    public function test_idor_delete_nonexistent(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->deleteJson('/api/v1/purchase-returns/99999')->assertStatus(404);
    }

    // --- Validation ---

    public function test_create_missing_items(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $this->receivedPurchase->id,
                'return_date' => '2026-01-20',
            ])->assertStatus(422);
    }

    public function test_create_missing_purchase_id(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', [
                'return_date' => '2026-01-20',
                'items' => [['product_id' => $this->productA->id, 'quantity' => 10, 'unit_cost' => 5000]],
            ])->assertStatus(422);
    }

    public function test_create_quantity_zero(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', $this->returnData([
                'items' => [['product_id' => $this->productA->id, 'quantity' => 0, 'unit_cost' => 5000]],
            ]))->assertStatus(422);
    }

    public function test_create_negative_quantity(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', $this->returnData([
                'items' => [['product_id' => $this->productA->id, 'quantity' => -10, 'unit_cost' => 5000]],
            ]))->assertStatus(422);
    }

    public function test_create_negative_unit_cost(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', $this->returnData([
                'items' => [['product_id' => $this->productA->id, 'quantity' => 10, 'unit_cost' => -100]],
            ]))->assertStatus(422);
    }

    public function test_create_product_not_in_purchase(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', $this->returnData([
                'items' => [['product_id' => $this->productB->id, 'quantity' => 10, 'unit_cost' => 5000]],
            ]))->assertStatus(422);
    }

    // --- Filter ---

    public function test_filter_by_status(): void
    {
        $r1 = $this->createReturn();
        $r2 = $this->createReturn();
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$r2->id}/complete");

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/purchase-returns?status=completed');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'completed');
    }

    public function test_filter_by_purchase_id(): void
    {
        $this->createReturn();

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/purchase-returns?purchase_id={$this->receivedPurchase->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    // --- Model ---

    public function test_return_belongs_to_tenant(): void
    {
        $return = $this->createReturn();
        $this->assertInstanceOf(Tenant::class, $return->tenant);
        $this->assertEquals($this->tenantA->id, $return->tenant->id);
    }

    public function test_return_belongs_to_purchase(): void
    {
        $return = $this->createReturn();
        $this->assertInstanceOf(Purchase::class, $return->purchase);
        $this->assertEquals($this->receivedPurchase->id, $return->purchase->id);
    }

    public function test_return_has_items(): void
    {
        $return = $this->createReturn();
        $this->assertCount(1, $return->items);
        $this->assertInstanceOf(PurchaseReturnItem::class, $return->items->first());
    }

    public function test_return_tenant_id_not_mass_assignable(): void
    {
        $r = new PurchaseReturn;
        $r->fill(['tenant_id' => $this->tenantB->id, 'return_number' => 'TEST']);
        $this->assertNull($r->tenant_id);
    }

    public function test_return_number_is_unique(): void
    {
        $r1 = $this->createReturn();
        $r2 = $this->createReturn();
        $this->assertNotEquals($r1->return_number, $r2->return_number);
    }

    // --- Inventory Security ---

    public function test_tenant_b_inventory_unchanged(): void
    {
        // Create inventory in Tenant B
        $invB = new Inventory;
        $invB->tenant_id = $this->tenantB->id;
        $invB->store_id = $this->storeB->id;
        $invB->product_id = $this->productB->id;
        $invB->quantity = 200;
        $invB->save();

        // Tenant A completes a return
        $return = $this->createReturn();
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete");

        // Tenant B inventory unchanged
        $invBAfter = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeB->id)
            ->where('product_id', $this->productB->id)
            ->first();
        $this->assertEquals(200, $invBAfter->quantity);
    }

    public function test_insufficient_stock_for_return_blocked(): void
    {
        // Manually reduce inventory to 10 (less than return quantity of 20)
        $inv = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $inv->quantity = 10;
        $inv->save();

        $return = $this->createReturn();
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete");

        $response->assertStatus(422);
    }
}
