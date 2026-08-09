<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase4SecurityGateTest extends TestCase
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
    private Supplier $supplierB;
    private Product $productA;
    private Product $productB;
    private Category $catA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();
        $managerRole = Role::where('slug', 'manager')->first();
        $cashierRole = Role::where('slug', 'cashier')->first();
        $staffRole = Role::where('slug', 'staff')->first();

        // Tenant A
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

        $this->supplierB = new Supplier;
        $this->supplierB->tenant_id = $this->tenantB->id;
        $this->supplierB->name = 'Supplier B';
        $this->supplierB->is_active = true;
        $this->supplierB->save();

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

    private function purchaseData(array $overrides = []): array
    {
        return array_merge([
            'supplier_id' => $this->supplierA->id,
            'store_id' => $this->storeA->id,
            'purchase_date' => '2026-01-15',
            'expected_date' => '2026-01-20',
            'notes' => 'Test purchase',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 50,
                    'unit_cost' => 5000,
                    'discount' => 0,
                    'tax' => 0,
                ],
            ],
        ], $overrides);
    }

    private function createPurchaseForA(): Purchase
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        Auth::logout();
        return $purchase;
    }

    private function createAndOrderPurchaseForA(): Purchase
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        app(PurchaseService::class)->order($purchase);
        Auth::logout();
        return $purchase->fresh();
    }

    private function createReceivedPurchaseForA(): Purchase
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        app(PurchaseService::class)->order($purchase);
        app(PurchaseService::class)->receive($purchase);
        Auth::logout();
        return $purchase->fresh();
    }

    // =========================================================================
    // 1. API SMOKE — End-to-End Flow
    // =========================================================================

    public function test_smoke_full_purchase_flow(): void
    {
        // Login
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner.a@t.com',
            'password' => 'password',
        ]);
        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('token');

        // Customer CRUD
        $custResponse = $this->withToken($token)
            ->postJson('/api/v1/customers', [
                'name' => 'Budi',
                'phone' => '08123456789',
            ]);
        $custResponse->assertStatus(201);
        $customerId = $custResponse->json('id');

        $this->withToken($token)->getJson('/api/v1/customers')->assertStatus(200);
        $this->withToken($token)->putJson("/api/v1/customers/{$customerId}", ['name' => 'Budi Updated'])->assertStatus(200);
        $this->withToken($token)->deleteJson("/api/v1/customers/{$customerId}")->assertStatus(200);

        // Supplier CRUD
        $supResponse = $this->withToken($token)
            ->postJson('/api/v1/suppliers', [
                'name' => 'PT Supplier',
                'contact_person' => 'Joko',
            ]);
        $supResponse->assertStatus(201);
        $supplierId = $supResponse->json('id');

        $this->withToken($token)->getJson('/api/v1/suppliers')->assertStatus(200);
        $this->withToken($token)->putJson("/api/v1/suppliers/{$supplierId}", ['name' => 'PT Supplier Updated'])->assertStatus(200);

        // Purchase create
        $purResponse = $this->withToken($token)
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $supplierId,
                'store_id' => $this->storeA->id,
                'purchase_date' => '2026-01-15',
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 10, 'unit_cost' => 5000],
                ],
            ]);
        $purResponse->assertStatus(201);
        $purchaseId = $purResponse->json('id');
        $this->assertEquals('draft', $purResponse->json('status'));

        // Inventory should NOT have increased yet
        $invBefore = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $this->assertNull($invBefore);

        // Purchase → ordered
        $orderResponse = $this->withToken($token)->postJson("/api/v1/purchases/{$purchaseId}/order");
        $orderResponse->assertStatus(200);
        $this->assertEquals('ordered', $orderResponse->json('status'));

        // Inventory still should NOT have increased
        $invOrdered = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $this->assertNull($invOrdered);

        // Purchase → received
        $receiveResponse = $this->withToken($token)->postJson("/api/v1/purchases/{$purchaseId}/receive");
        $receiveResponse->assertStatus(200);
        $this->assertEquals('received', $receiveResponse->json('status'));

        // Inventory should have increased
        $invAfter = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $this->assertNotNull($invAfter);
        $this->assertEquals(10, $invAfter->quantity);

        // Movement should exist
        $movement = InventoryMovement::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->where('type', 'purchase')
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(10, $movement->quantity);
        $this->assertEquals(0, $movement->before_quantity);
        $this->assertEquals(10, $movement->after_quantity);

        // Purchase list/show/filter
        $this->withToken($token)->getJson('/api/v1/purchases')->assertStatus(200);
        $this->withToken($token)->getJson("/api/v1/purchases/{$purchaseId}")->assertStatus(200);
        $this->withToken($token)->getJson('/api/v1/purchases?status=received')->assertStatus(200);
    }

    // =========================================================================
    // 2. AUTHENTICATION
    // =========================================================================

    public function test_auth_no_token_customer_endpoints(): void
    {
        $this->getJson('/api/v1/customers')->assertStatus(401);
        $this->postJson('/api/v1/customers', ['name' => 'Test'])->assertStatus(401);
    }

    public function test_auth_no_token_supplier_endpoints(): void
    {
        $this->getJson('/api/v1/suppliers')->assertStatus(401);
        $this->postJson('/api/v1/suppliers', ['name' => 'Test'])->assertStatus(401);
    }

    public function test_auth_no_token_purchase_endpoints(): void
    {
        $this->getJson('/api/v1/purchases')->assertStatus(401);
        $this->postJson('/api/v1/purchases', $this->purchaseData())->assertStatus(401);
        $this->postJson('/api/v1/purchases/1/order')->assertStatus(401);
        $this->postJson('/api/v1/purchases/1/receive')->assertStatus(401);
        $this->postJson('/api/v1/purchases/1/cancel')->assertStatus(401);
    }

    public function test_auth_invalid_token(): void
    {
        $this->withToken('invalid-token-12345')
            ->getJson('/api/v1/purchases')->assertStatus(401);
        $this->withToken('invalid-token-12345')
            ->getJson('/api/v1/customers')->assertStatus(401);
        $this->withToken('invalid-token-12345')
            ->getJson('/api/v1/suppliers')->assertStatus(401);
    }

    public function test_auth_deleted_user_token_invalid(): void
    {
        $token = $this->ownerA->createToken('test')->plainTextToken;

        // Delete the user
        DB::table('users')->where('id', $this->ownerA->id)->delete();

        $this->withToken($token)->getJson('/api/v1/purchases')->assertStatus(401);
        $this->withToken($token)->getJson('/api/v1/customers')->assertStatus(401);
        $this->withToken($token)->getJson('/api/v1/suppliers')->assertStatus(401);
    }

    public function test_auth_revoked_token_invalid(): void
    {
        $token = $this->ownerA->createToken('test')->plainTextToken;

        // Revoke all tokens
        $this->ownerA->tokens()->delete();

        $this->withToken($token)->getJson('/api/v1/purchases')->assertStatus(401);
        $this->withToken($token)->getJson('/api/v1/customers')->assertStatus(401);
        $this->withToken($token)->getJson('/api/v1/suppliers')->assertStatus(401);
    }

    // =========================================================================
    // 3. TENANT ISOLATION
    // =========================================================================

    public function test_tenant_isolation_purchase_list(): void
    {
        $this->createPurchaseForA();

        $response = $this->withToken($this->tokenOwnerB)
            ->getJson('/api/v1/purchases');
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_tenant_isolation_purchase_show(): void
    {
        $purchase = $this->createPurchaseForA();

        $this->withToken($this->tokenOwnerB)
            ->getJson("/api/v1/purchases/{$purchase->id}")->assertStatus(404);
    }

    public function test_tenant_isolation_purchase_update(): void
    {
        $purchase = $this->createPurchaseForA();

        $this->withToken($this->tokenOwnerB)
            ->putJson("/api/v1/purchases/{$purchase->id}", ['notes' => 'Hacked'])
            ->assertStatus(404);
    }

    public function test_tenant_isolation_purchase_delete(): void
    {
        $purchase = $this->createPurchaseForA();

        $this->withToken($this->tokenOwnerB)
            ->deleteJson("/api/v1/purchases/{$purchase->id}")->assertStatus(404);
    }

    public function test_tenant_isolation_purchase_receive(): void
    {
        $purchase = $this->createAndOrderPurchaseForA();

        $this->withToken($this->tokenOwnerB)
            ->postJson("/api/v1/purchases/{$purchase->id}/receive")->assertStatus(404);
    }

    public function test_tenant_isolation_purchase_order(): void
    {
        $purchase = $this->createPurchaseForA();

        $this->withToken($this->tokenOwnerB)
            ->postJson("/api/v1/purchases/{$purchase->id}/order")->assertStatus(404);
    }

    public function test_tenant_isolation_purchase_cancel(): void
    {
        $purchase = $this->createPurchaseForA();

        $this->withToken($this->tokenOwnerB)
            ->postJson("/api/v1/purchases/{$purchase->id}/cancel")->assertStatus(404);
    }

    public function test_tenant_isolation_cross_tenant_supplier_blocked(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'supplier_id' => $this->supplierB->id,
            ]))->assertStatus(422);
    }

    public function test_tenant_isolation_cross_tenant_store_blocked(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'store_id' => $this->storeB->id,
            ]))->assertStatus(422);
    }

    public function test_tenant_isolation_cross_tenant_product_blocked(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'items' => [
                    ['product_id' => $this->productB->id, 'quantity' => 10, 'unit_cost' => 1000],
                ],
            ]))->assertStatus(422);
    }

    public function test_tenant_isolation_customer_list(): void
    {
        Auth::login($this->ownerA);
        $cust = new \App\Models\Customer;
        $cust->tenant_id = $this->tenantA->id;
        $cust->name = 'Customer A';
        $cust->save();
        Auth::logout();

        $response = $this->withToken($this->tokenOwnerB)
            ->getJson('/api/v1/customers');
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_tenant_isolation_supplier_list(): void
    {
        $response = $this->withToken($this->tokenOwnerB)
            ->getJson('/api/v1/suppliers');
        $response->assertStatus(200);
        // Tenant B should see only its own supplier (Supplier B), not Supplier A
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Supplier B');
    }

    // =========================================================================
    // 4. MASS ASSIGNMENT / SPOOFING
    // =========================================================================

    public function test_spoof_tenant_id_ignored(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'tenant_id' => $this->tenantB->id,
            ]));

        $response->assertStatus(201);
        $response->assertJsonPath('tenant_id', $this->tenantA->id);
    }

    public function test_spoof_created_by_ignored(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'created_by' => $this->ownerB->id,
            ]));

        $response->assertStatus(201);
        $response->assertJsonPath('created_by', $this->ownerA->id);
    }

    public function test_spoof_status_ignored(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'status' => 'received',
            ]));

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'draft');
    }

    public function test_spoof_subtotal_ignored(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'subtotal' => 1,
            ]));

        $response->assertStatus(201);
        $response->assertJsonPath('subtotal', '250000.00');
    }

    public function test_spoof_discount_ignored(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'discount' => 999999,
            ]));

        $response->assertStatus(201);
        $response->assertJsonPath('discount', '0.00');
    }

    public function test_spoof_tax_ignored(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'tax' => 999999,
            ]));

        $response->assertStatus(201);
        $response->assertJsonPath('tax', '0.00');
    }

    public function test_spoof_total_ignored(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'total' => 1,
            ]));

        $response->assertStatus(201);
        $response->assertJsonPath('total', '250000.00');
    }

    public function test_spoof_all_fields_at_once(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'tenant_id' => $this->tenantB->id,
                'created_by' => $this->ownerB->id,
                'status' => 'received',
                'subtotal' => 1,
                'discount' => 999999,
                'tax' => 999999,
                'total' => 1,
            ]));

        $response->assertStatus(201);
        $response->assertJsonPath('tenant_id', $this->tenantA->id);
        $response->assertJsonPath('created_by', $this->ownerA->id);
        $response->assertJsonPath('status', 'draft');
        $response->assertJsonPath('subtotal', '250000.00');
        $response->assertJsonPath('discount', '0.00');
        $response->assertJsonPath('tax', '0.00');
        $response->assertJsonPath('total', '250000.00');
    }

    public function test_spoof_item_total_ignored(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 10, 'unit_cost' => 5000, 'total' => 1],
                ],
            ]));

        $response->assertStatus(201);
        $response->assertJsonPath('items.0.total', '50000.00');
    }

    // =========================================================================
    // 5. PURCHASE → INVENTORY SECURITY
    // =========================================================================

    public function test_inventory_draft_does_not_increase(): void
    {
        $this->createPurchaseForA();

        $inv = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $this->assertNull($inv);
    }

    public function test_inventory_ordered_does_not_increase(): void
    {
        $this->createAndOrderPurchaseForA();

        $inv = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $this->assertNull($inv);
    }

    public function test_inventory_received_increases(): void
    {
        $this->createReceivedPurchaseForA();

        $inv = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $this->assertNotNull($inv);
        $this->assertEquals(50, $inv->quantity);
    }

    public function test_inventory_received_creates_movement(): void
    {
        $purchase = $this->createReceivedPurchaseForA();

        $movement = InventoryMovement::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->where('type', 'purchase')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(50, $movement->quantity);
        $this->assertEquals(0, $movement->before_quantity);
        $this->assertEquals(50, $movement->after_quantity);
        $this->assertEquals($purchase->id, $movement->reference_id);
        $this->assertEquals(Purchase::class, $movement->reference_type);
    }

    public function test_inventory_double_receive_prevented(): void
    {
        $purchase = $this->createReceivedPurchaseForA();

        // Try to receive again via API
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/receive")
            ->assertStatus(422);

        $inv = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $this->assertEquals(50, $inv->quantity); // not 100
    }

    public function test_inventory_cancelled_does_not_increase(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        app(PurchaseService::class)->cancel($purchase);
        Auth::logout();

        $inv = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $this->assertNull($inv);
    }

    public function test_inventory_receive_cancelled_blocked(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        app(PurchaseService::class)->cancel($purchase);
        Auth::logout();

        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/receive")
            ->assertStatus(422);

        $inv = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();
        $this->assertNull($inv);
    }

    public function test_inventory_tenant_b_unchanged_when_tenant_a_receives(): void
    {
        // Create some inventory in Tenant B first
        $invB = new Inventory;
        $invB->tenant_id = $this->tenantB->id;
        $invB->store_id = $this->storeB->id;
        $invB->product_id = $this->productB->id;
        $invB->quantity = 100;
        $invB->save();

        // Tenant A receives a purchase
        $this->createReceivedPurchaseForA();

        // Tenant B inventory should be unchanged
        $invBAfter = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeB->id)
            ->where('product_id', $this->productB->id)
            ->first();
        $this->assertEquals(100, $invBAfter->quantity);

        // No movements should exist for Tenant B
        $movementsB = InventoryMovement::withoutTenantScope()
            ->where('store_id', $this->storeB->id)
            ->get();
        $this->assertCount(0, $movementsB);
    }

    public function test_inventory_movement_count_correct(): void
    {
        $this->createReceivedPurchaseForA();

        $movements = InventoryMovement::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->get();

        $this->assertCount(1, $movements);
    }

    // =========================================================================
    // 6. RBAC — Full Matrix
    // =========================================================================

    public function test_rbac_owner_can_view_purchases(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/purchases')->assertStatus(200);
    }

    public function test_rbac_manager_can_view_purchases(): void
    {
        $this->withToken($this->tokenManagerA)
            ->getJson('/api/v1/purchases')->assertStatus(200);
    }

    public function test_rbac_cashier_can_view_purchases(): void
    {
        $this->withToken($this->tokenCashierA)
            ->getJson('/api/v1/purchases')->assertStatus(200);
    }

    public function test_rbac_staff_cannot_view_purchases(): void
    {
        $this->withToken($this->tokenStaffA)
            ->getJson('/api/v1/purchases')->assertStatus(403);
    }

    public function test_rbac_owner_can_create_purchase(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData())->assertStatus(201);
    }

    public function test_rbac_manager_can_create_purchase(): void
    {
        $this->withToken($this->tokenManagerA)
            ->postJson('/api/v1/purchases', $this->purchaseData())->assertStatus(201);
    }

    public function test_rbac_cashier_cannot_create_purchase(): void
    {
        $this->withToken($this->tokenCashierA)
            ->postJson('/api/v1/purchases', $this->purchaseData())->assertStatus(403);
    }

    public function test_rbac_staff_cannot_create_purchase(): void
    {
        $this->withToken($this->tokenStaffA)
            ->postJson('/api/v1/purchases', $this->purchaseData())->assertStatus(403);
    }

    public function test_rbac_owner_can_update_purchase(): void
    {
        $purchase = $this->createPurchaseForA();

        $this->withToken($this->tokenOwnerA)
            ->putJson("/api/v1/purchases/{$purchase->id}", ['notes' => 'Updated'])
            ->assertStatus(200);
    }

    public function test_rbac_manager_can_update_purchase(): void
    {
        $purchase = $this->createPurchaseForA();

        $this->withToken($this->tokenManagerA)
            ->putJson("/api/v1/purchases/{$purchase->id}", ['notes' => 'Updated'])
            ->assertStatus(200);
    }

    public function test_rbac_cashier_cannot_update_purchase(): void
    {
        $purchase = $this->createPurchaseForA();

        $this->withToken($this->tokenCashierA)
            ->putJson("/api/v1/purchases/{$purchase->id}", ['notes' => 'Updated'])
            ->assertStatus(403);
    }

    public function test_rbac_staff_cannot_update_purchase(): void
    {
        $purchase = $this->createPurchaseForA();

        $this->withToken($this->tokenStaffA)
            ->putJson("/api/v1/purchases/{$purchase->id}", ['notes' => 'Updated'])
            ->assertStatus(403);
    }

    public function test_rbac_owner_can_delete_purchase(): void
    {
        $purchase = $this->createPurchaseForA();

        $this->withToken($this->tokenOwnerA)
            ->deleteJson("/api/v1/purchases/{$purchase->id}")->assertStatus(200);
    }

    public function test_rbac_manager_can_delete_purchase(): void
    {
        $purchase = $this->createPurchaseForA();

        $this->withToken($this->tokenManagerA)
            ->deleteJson("/api/v1/purchases/{$purchase->id}")->assertStatus(200);
    }

    public function test_rbac_cashier_cannot_delete_purchase(): void
    {
        $purchase = $this->createPurchaseForA();

        $this->withToken($this->tokenCashierA)
            ->deleteJson("/api/v1/purchases/{$purchase->id}")->assertStatus(403);
    }

    public function test_rbac_staff_cannot_delete_purchase(): void
    {
        $purchase = $this->createPurchaseForA();

        $this->withToken($this->tokenStaffA)
            ->deleteJson("/api/v1/purchases/{$purchase->id}")->assertStatus(403);
    }

    public function test_rbac_owner_can_order_purchase(): void
    {
        $purchase = $this->createPurchaseForA();

        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/order")->assertStatus(200);
    }

    public function test_rbac_manager_can_order_purchase(): void
    {
        $purchase = $this->createPurchaseForA();

        $this->withToken($this->tokenManagerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/order")->assertStatus(200);
    }

    public function test_rbac_cashier_cannot_order_purchase(): void
    {
        $purchase = $this->createPurchaseForA();

        $this->withToken($this->tokenCashierA)
            ->postJson("/api/v1/purchases/{$purchase->id}/order")->assertStatus(403);
    }

    public function test_rbac_staff_cannot_order_purchase(): void
    {
        $purchase = $this->createPurchaseForA();

        $this->withToken($this->tokenStaffA)
            ->postJson("/api/v1/purchases/{$purchase->id}/order")->assertStatus(403);
    }

    public function test_rbac_owner_can_receive_purchase(): void
    {
        $purchase = $this->createAndOrderPurchaseForA();

        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/receive")->assertStatus(200);
    }

    public function test_rbac_manager_can_receive_purchase(): void
    {
        $purchase = $this->createAndOrderPurchaseForA();

        $this->withToken($this->tokenManagerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/receive")->assertStatus(200);
    }

    public function test_rbac_cashier_cannot_receive_purchase(): void
    {
        $purchase = $this->createAndOrderPurchaseForA();

        $this->withToken($this->tokenCashierA)
            ->postJson("/api/v1/purchases/{$purchase->id}/receive")->assertStatus(403);
    }

    public function test_rbac_staff_cannot_receive_purchase(): void
    {
        $purchase = $this->createAndOrderPurchaseForA();

        $this->withToken($this->tokenStaffA)
            ->postJson("/api/v1/purchases/{$purchase->id}/receive")->assertStatus(403);
    }

    public function test_rbac_owner_can_cancel_purchase(): void
    {
        $purchase = $this->createPurchaseForA();

        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/cancel")->assertStatus(200);
    }

    public function test_rbac_manager_can_cancel_purchase(): void
    {
        $purchase = $this->createPurchaseForA();

        $this->withToken($this->tokenManagerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/cancel")->assertStatus(200);
    }

    public function test_rbac_cashier_cannot_cancel_purchase(): void
    {
        $purchase = $this->createPurchaseForA();

        $this->withToken($this->tokenCashierA)
            ->postJson("/api/v1/purchases/{$purchase->id}/cancel")->assertStatus(403);
    }

    public function test_rbac_staff_cannot_cancel_purchase(): void
    {
        $purchase = $this->createPurchaseForA();

        $this->withToken($this->tokenStaffA)
            ->postJson("/api/v1/purchases/{$purchase->id}/cancel")->assertStatus(403);
    }

    // =========================================================================
    // 7. IDOR
    // =========================================================================

    public function test_idor_purchase_nonexistent_get(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/purchases/999999')->assertStatus(404);
    }

    public function test_idor_purchase_nonexistent_order(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases/999999/order')->assertStatus(404);
    }

    public function test_idor_purchase_nonexistent_receive(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases/999999/receive')->assertStatus(404);
    }

    public function test_idor_purchase_nonexistent_cancel(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases/999999/cancel')->assertStatus(404);
    }

    public function test_idor_purchase_nonexistent_delete(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->deleteJson('/api/v1/purchases/999999')->assertStatus(404);
    }

    public function test_idor_no_cross_tenant_data_leak(): void
    {
        // Create purchase in Tenant A
        $purchaseA = $this->createReceivedPurchaseForA();

        // Tenant B should not see it in list
        $response = $this->withToken($this->tokenOwnerB)
            ->getJson('/api/v1/purchases');
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');

        // Tenant B should not access it directly
        $this->withToken($this->tokenOwnerB)
            ->getJson("/api/v1/purchases/{$purchaseA->id}")->assertStatus(404);
    }

    // =========================================================================
    // 8. BUSINESS LOGIC ABUSE
    // =========================================================================

    public function test_abuse_quantity_zero(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'items' => [['product_id' => $this->productA->id, 'quantity' => 0, 'unit_cost' => 5000]],
            ]))->assertStatus(422);
    }

    public function test_abuse_quantity_negative(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'items' => [['product_id' => $this->productA->id, 'quantity' => -10, 'unit_cost' => 5000]],
            ]))->assertStatus(422);
    }

    public function test_abuse_negative_unit_cost(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'items' => [['product_id' => $this->productA->id, 'quantity' => 10, 'unit_cost' => -100]],
            ]))->assertStatus(422);
    }

    public function test_abuse_empty_items(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'items' => [],
            ]))->assertStatus(422);
    }

    public function test_abuse_order_already_ordered(): void
    {
        $purchase = $this->createAndOrderPurchaseForA();

        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/order")->assertStatus(422);
    }

    public function test_abuse_receive_draft(): void
    {
        $purchase = $this->createPurchaseForA();

        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/receive")->assertStatus(422);
    }

    public function test_abuse_receive_twice(): void
    {
        $purchase = $this->createReceivedPurchaseForA();

        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/receive")->assertStatus(422);
    }

    public function test_abuse_cancel_received(): void
    {
        $purchase = $this->createReceivedPurchaseForA();

        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/cancel")->assertStatus(422);
    }

    public function test_abuse_total_not_spoofable(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 10, 'unit_cost' => 5000, 'total' => 1],
                ],
                'total' => 1,
                'subtotal' => 1,
            ]));

        $response->assertStatus(201);
        // Backend must calculate 10 * 5000 = 50000, not 1
        $response->assertJsonPath('items.0.total', '50000.00');
        $response->assertJsonPath('total', '50000.00');
        $response->assertJsonPath('subtotal', '50000.00');
    }

    public function test_abuse_delete_non_draft_purchase(): void
    {
        $purchase = $this->createAndOrderPurchaseForA();

        $this->withToken($this->tokenOwnerA)
            ->deleteJson("/api/v1/purchases/{$purchase->id}")->assertStatus(422);
    }

    public function test_abuse_update_non_draft_purchase(): void
    {
        $purchase = $this->createAndOrderPurchaseForA();

        $this->withToken($this->tokenOwnerA)
            ->putJson("/api/v1/purchases/{$purchase->id}", ['notes' => 'Hacked'])
            ->assertStatus(422);
    }
}
