<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
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

class Phase4FinalGateTest extends TestCase
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
    private Category $catA;
    private Product $productA1;
    private Product $productA2;
    private Product $productB;

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
            'name' => 'Owner A', 'email' => 'owner.a@gate.com', 'password' => 'password',
        ]);
        $this->managerA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $managerRole->id,
            'name' => 'Manager A', 'email' => 'manager.a@gate.com', 'password' => 'password',
        ]);
        $this->cashierA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $cashierRole->id,
            'name' => 'Cashier A', 'email' => 'cashier.a@gate.com', 'password' => 'password',
        ]);
        $this->staffA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $staffRole->id,
            'name' => 'Staff A', 'email' => 'staff.a@gate.com', 'password' => 'password',
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

        $this->productA1 = new Product;
        $this->productA1->tenant_id = $this->tenantA->id;
        $this->productA1->category_id = $this->catA->id;
        $this->productA1->name = 'Coca Cola';
        $this->productA1->sku = 'COKE-001';
        $this->productA1->barcode = '123456';
        $this->productA1->cost_price = 5000;
        $this->productA1->selling_price = 8000;
        $this->productA1->unit = 'botol';
        $this->productA1->save();

        $this->productA2 = new Product;
        $this->productA2->tenant_id = $this->tenantA->id;
        $this->productA2->category_id = $this->catA->id;
        $this->productA2->name = 'Sprite';
        $this->productA2->sku = 'SPRITE-001';
        $this->productA2->barcode = '789012';
        $this->productA2->cost_price = 6000;
        $this->productA2->selling_price = 9000;
        $this->productA2->unit = 'botol';
        $this->productA2->save();

        // Tenant B
        $this->tenantB = Tenant::create(['name' => 'Toko B', 'slug' => 'toko-b']);
        $this->ownerB = User::create([
            'tenant_id' => $this->tenantB->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner B', 'email' => 'owner.b@gate.com', 'password' => 'password',
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

    private function createReceivedPurchase(int $qty1 = 100, int $qty2 = 0): Purchase
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $items = [['product_id' => $this->productA1->id, 'quantity' => $qty1, 'unit_cost' => 5000]];
        if ($qty2 > 0) {
            $items[] = ['product_id' => $this->productA2->id, 'quantity' => $qty2, 'unit_cost' => 6000];
        }
        $purchase = app(PurchaseService::class)->create([
            'supplier_id' => $this->supplierA->id,
            'store_id' => $this->storeA->id,
            'purchase_date' => '2026-01-15',
            'items' => $items,
        ]);
        app(PurchaseService::class)->order($purchase);
        app(PurchaseService::class)->receive($purchase->fresh());
        $purchase = $purchase->fresh();
        Auth::forgetGuards();
        return $purchase;
    }

    private function createReturn(Purchase $purchase, array $items): PurchaseReturn
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $return = app(PurchaseReturnService::class)->create([
            'purchase_id' => $purchase->id,
            'return_date' => '2026-01-20',
            'items' => $items,
        ]);
        Auth::forgetGuards();
        return $return;
    }

    private function getInventory(int $productId): ?Inventory
    {
        return Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $productId)
            ->first();
    }

    private function getMovements(int $productId): \Illuminate\Database\Eloquent\Collection
    {
        return InventoryMovement::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $productId)
            ->orderBy('id')
            ->get();
    }

    // =========================================================================
    // 1. E2E — FULL FLOW: Supplier → Purchase → Order → Receive → Return → Complete
    // =========================================================================

    public function test_e2e_full_flow_single_product(): void
    {
        // 1. Create + Order + Receive
        $purchase = $this->createReceivedPurchase(100);

        // Verify purchase
        $this->assertEquals('received', $purchase->status);
        $this->assertEquals('500000.00', $purchase->subtotal);
        $this->assertEquals('500000.00', $purchase->total);

        // 2. Inventory = 100
        $inv = $this->getInventory($this->productA1->id);
        $this->assertNotNull($inv);
        $this->assertEquals(100, $inv->quantity);

        // 3. Movement: purchase +100
        $movements = $this->getMovements($this->productA1->id);
        $this->assertCount(1, $movements);
        $this->assertEquals('purchase', $movements[0]->type);
        $this->assertEquals(100, $movements[0]->quantity);
        $this->assertEquals(0, $movements[0]->before_quantity);
        $this->assertEquals(100, $movements[0]->after_quantity);

        // 4. Create Return 20
        $return = $this->createReturn($purchase, [
            ['product_id' => $this->productA1->id, 'quantity' => 20, 'unit_cost' => 5000],
        ]);

        $this->assertEquals('draft', $return->status);
        $this->assertEquals('100000.00', $return->subtotal);
        $this->assertEquals('100000.00', $return->total);

        // Inventory unchanged (draft)
        $this->assertEquals(100, $this->getInventory($this->productA1->id)->quantity);

        // 5. Complete Return
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete");
        $response->assertStatus(200);
        $response->assertJsonPath('status', 'completed');

        // 6. Inventory = 80
        $this->assertEquals(80, $this->getInventory($this->productA1->id)->quantity);

        // 7. Movements: purchase +100, purchase_return -20
        $movements = $this->getMovements($this->productA1->id);
        $this->assertCount(2, $movements);
        $this->assertEquals('purchase', $movements[0]->type);
        $this->assertEquals(100, $movements[0]->quantity);
        $this->assertEquals('purchase_return', $movements[1]->type);
        $this->assertEquals(-20, $movements[1]->quantity);
        $this->assertEquals(100, $movements[1]->before_quantity);
        $this->assertEquals(80, $movements[1]->after_quantity);
        $this->assertEquals($return->id, $movements[1]->reference_id);
        $this->assertEquals(PurchaseReturn::class, $movements[1]->reference_type);

        // 8. Verify via API
        $purchaseRes = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/purchases/{$purchase->id}");
        $purchaseRes->assertStatus(200);
        $purchaseRes->assertJsonPath('status', 'received');
        $purchaseRes->assertJsonPath('total', '500000.00');

        $returnRes = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/purchase-returns/{$return->id}");
        $returnRes->assertStatus(200);
        $returnRes->assertJsonPath('status', 'completed');
        $returnRes->assertJsonPath('total', '100000.00');
    }

    public function test_e2e_full_flow_multi_product(): void
    {
        $purchase = $this->createReceivedPurchase(100, 50);

        // Inventory: productA1=100, productA2=50
        $this->assertEquals(100, $this->getInventory($this->productA1->id)->quantity);
        $this->assertEquals(50, $this->getInventory($this->productA2->id)->quantity);

        // Purchase total = 100*5000 + 50*6000 = 500000 + 300000 = 800000
        $this->assertEquals('800000.00', $purchase->total);

        // Return 30 of productA1 and 10 of productA2
        $return = $this->createReturn($purchase, [
            ['product_id' => $this->productA1->id, 'quantity' => 30, 'unit_cost' => 5000],
            ['product_id' => $this->productA2->id, 'quantity' => 10, 'unit_cost' => 6000],
        ]);

        // Return total = 30*5000 + 10*6000 = 150000 + 60000 = 210000
        $this->assertEquals('210000.00', $return->total);

        // Complete
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete")
            ->assertStatus(200);

        // Inventory: productA1=70, productA2=40
        $this->assertEquals(70, $this->getInventory($this->productA1->id)->quantity);
        $this->assertEquals(40, $this->getInventory($this->productA2->id)->quantity);

        // Movements for productA1: purchase +100, purchase_return -30
        $this->assertCount(2, $this->getMovements($this->productA1->id));
        // Movements for productA2: purchase +50, purchase_return -10
        $this->assertCount(2, $this->getMovements($this->productA2->id));
    }

    public function test_e2e_multiple_returns_cumulative(): void
    {
        $purchase = $this->createReceivedPurchase(100);

        // Return 20 → complete → inv=80
        $r1 = $this->createReturn($purchase, [
            ['product_id' => $this->productA1->id, 'quantity' => 20, 'unit_cost' => 5000],
        ]);
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$r1->id}/complete")->assertStatus(200);
        $this->assertEquals(80, $this->getInventory($this->productA1->id)->quantity);

        // Return 30 → complete → inv=50
        $r2 = $this->createReturn($purchase, [
            ['product_id' => $this->productA1->id, 'quantity' => 30, 'unit_cost' => 5000],
        ]);
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$r2->id}/complete")->assertStatus(200);
        $this->assertEquals(50, $this->getInventory($this->productA1->id)->quantity);

        // Return 60 → reject (20+30+60=110 > 100)
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $purchase->id,
                'return_date' => '2026-01-25',
                'items' => [
                    ['product_id' => $this->productA1->id, 'quantity' => 60, 'unit_cost' => 5000],
                ],
            ]);
        $response->assertStatus(422);

        // Inventory still 50
        $this->assertEquals(50, $this->getInventory($this->productA1->id)->quantity);

        // Movements: purchase +100, purchase_return -20, purchase_return -30
        $movements = $this->getMovements($this->productA1->id);
        $this->assertCount(3, $movements);
        $this->assertEquals('purchase', $movements[0]->type);
        $this->assertEquals(100, $movements[0]->quantity);
        $this->assertEquals('purchase_return', $movements[1]->type);
        $this->assertEquals(-20, $movements[1]->quantity);
        $this->assertEquals('purchase_return', $movements[2]->type);
        $this->assertEquals(-30, $movements[2]->quantity);
    }

    // =========================================================================
    // 2. SECURITY GATE
    // =========================================================================

    // --- Authentication ---

    public function test_gate_auth_no_token_all_endpoints(): void
    {
        $this->getJson('/api/v1/purchases')->assertStatus(401);
        $this->getJson('/api/v1/purchase-returns')->assertStatus(401);
        $this->postJson('/api/v1/purchases', [])->assertStatus(401);
        $this->postJson('/api/v1/purchase-returns', [])->assertStatus(401);
        $this->postJson('/api/v1/purchases/1/order')->assertStatus(401);
        $this->postJson('/api/v1/purchases/1/receive')->assertStatus(401);
        $this->postJson('/api/v1/purchase-returns/1/complete')->assertStatus(401);
        $this->deleteJson('/api/v1/purchases/1')->assertStatus(401);
        $this->deleteJson('/api/v1/purchase-returns/1')->assertStatus(401);
    }

    public function test_gate_auth_invalid_token(): void
    {
        $this->withToken('invalid-token-12345')
            ->getJson('/api/v1/purchases')->assertStatus(401);
        $this->withToken('invalid-token-12345')
            ->getJson('/api/v1/purchase-returns')->assertStatus(401);
    }

    // --- Tenant Isolation ---

    public function test_gate_tenant_a_cannot_access_tenant_b_purchase(): void
    {
        $purchase = $this->createReceivedPurchase(100);

        $this->withToken($this->tokenOwnerB)
            ->getJson("/api/v1/purchases/{$purchase->id}")->assertStatus(404);
        $this->withToken($this->tokenOwnerB)
            ->postJson("/api/v1/purchases/{$purchase->id}/receive")->assertStatus(404);
        $this->withToken($this->tokenOwnerB)
            ->deleteJson("/api/v1/purchases/{$purchase->id}")->assertStatus(404);
    }

    public function test_gate_tenant_a_cannot_access_tenant_b_return(): void
    {
        $purchase = $this->createReceivedPurchase(100);
        $return = $this->createReturn($purchase, [
            ['product_id' => $this->productA1->id, 'quantity' => 10, 'unit_cost' => 5000],
        ]);

        $this->withToken($this->tokenOwnerB)
            ->getJson("/api/v1/purchase-returns/{$return->id}")->assertStatus(404);
        $this->withToken($this->tokenOwnerB)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete")->assertStatus(404);
        $this->withToken($this->tokenOwnerB)
            ->deleteJson("/api/v1/purchase-returns/{$return->id}")->assertStatus(404);
    }

    public function test_gate_cross_tenant_supplier_in_purchase(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $this->supplierB->id,
                'store_id' => $this->storeA->id,
                'purchase_date' => '2026-01-15',
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 10, 'unit_cost' => 5000]],
            ]);
        $response->assertStatus(422);
    }

    public function test_gate_cross_tenant_store_in_purchase(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $this->supplierA->id,
                'store_id' => $this->storeB->id,
                'purchase_date' => '2026-01-15',
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 10, 'unit_cost' => 5000]],
            ]);
        $response->assertStatus(422);
    }

    public function test_gate_cross_tenant_product_in_purchase(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $this->supplierA->id,
                'store_id' => $this->storeA->id,
                'purchase_date' => '2026-01-15',
                'items' => [['product_id' => $this->productB->id, 'quantity' => 10, 'unit_cost' => 5000]],
            ]);
        $response->assertStatus(422);
    }

    public function test_gate_cross_tenant_purchase_in_return(): void
    {
        $purchase = $this->createReceivedPurchase(100);

        $response = $this->withToken($this->tokenOwnerB)
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $purchase->id,
                'return_date' => '2026-01-20',
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 10, 'unit_cost' => 5000]],
            ]);
        $response->assertStatus(422);
    }

    public function test_gate_tenant_b_cannot_see_tenant_a_purchases(): void
    {
        $this->createReceivedPurchase(100);

        $response = $this->withToken($this->tokenOwnerB)
            ->getJson('/api/v1/purchases');
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_gate_tenant_b_cannot_see_tenant_a_returns(): void
    {
        $purchase = $this->createReceivedPurchase(100);
        $this->createReturn($purchase, [
            ['product_id' => $this->productA1->id, 'quantity' => 10, 'unit_cost' => 5000],
        ]);

        $response = $this->withToken($this->tokenOwnerB)
            ->getJson('/api/v1/purchase-returns');
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    // --- Mass Assignment ---

    public function test_gate_mass_assignment_purchase(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $this->supplierA->id,
                'store_id' => $this->storeA->id,
                'purchase_date' => '2026-01-15',
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 10, 'unit_cost' => 5000]],
                'tenant_id' => $this->tenantB->id,
                'created_by' => $this->ownerB->id,
                'status' => 'received',
                'subtotal' => 1,
                'total' => 1,
            ]);
        $response->assertStatus(201);
        $response->assertJsonPath('tenant_id', $this->tenantA->id);
        $response->assertJsonPath('created_by', $this->ownerA->id);
        $response->assertJsonPath('status', 'draft');
        $response->assertJsonPath('subtotal', '50000.00');
        $response->assertJsonPath('total', '50000.00');
    }

    public function test_gate_mass_assignment_return(): void
    {
        $purchase = $this->createReceivedPurchase(100);

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $purchase->id,
                'return_date' => '2026-01-20',
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 10, 'unit_cost' => 5000]],
                'tenant_id' => $this->tenantB->id,
                'created_by' => $this->ownerB->id,
                'status' => 'completed',
                'subtotal' => 1,
                'total' => 1,
            ]);
        $response->assertStatus(201);
        $response->assertJsonPath('tenant_id', $this->tenantA->id);
        $response->assertJsonPath('created_by', $this->ownerA->id);
        $response->assertJsonPath('status', 'draft');
        $response->assertJsonPath('subtotal', '50000.00');
        $response->assertJsonPath('total', '50000.00');
    }

    // --- RBAC ---

    public function test_gate_rbac_owner_can_manage_all(): void
    {
        $purchase = $this->createReceivedPurchase(100);

        $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/purchases')->assertStatus(200);
        $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/purchase-returns')->assertStatus(200);
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $this->supplierA->id,
                'store_id' => $this->storeA->id,
                'purchase_date' => '2026-01-15',
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 5, 'unit_cost' => 5000]],
            ])->assertStatus(201);
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $purchase->id,
                'return_date' => '2026-01-20',
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 5, 'unit_cost' => 5000]],
            ])->assertStatus(201);
    }

    public function test_gate_rbac_manager_can_manage_all(): void
    {
        $purchase = $this->createReceivedPurchase(100);

        $this->withToken($this->tokenManagerA)
            ->getJson('/api/v1/purchases')->assertStatus(200);
        $this->withToken($this->tokenManagerA)
            ->getJson('/api/v1/purchase-returns')->assertStatus(200);
        $this->withToken($this->tokenManagerA)
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $this->supplierA->id,
                'store_id' => $this->storeA->id,
                'purchase_date' => '2026-01-15',
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 5, 'unit_cost' => 5000]],
            ])->assertStatus(201);
        $this->withToken($this->tokenManagerA)
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $purchase->id,
                'return_date' => '2026-01-20',
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 5, 'unit_cost' => 5000]],
            ])->assertStatus(201);
    }

    public function test_gate_rbac_cashier_view_only(): void
    {
        $this->withToken($this->tokenCashierA)
            ->getJson('/api/v1/purchases')->assertStatus(200);
        $this->withToken($this->tokenCashierA)
            ->getJson('/api/v1/purchase-returns')->assertStatus(200);
        $this->withToken($this->tokenCashierA)
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $this->supplierA->id,
                'store_id' => $this->storeA->id,
                'purchase_date' => '2026-01-15',
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 5, 'unit_cost' => 5000]],
            ])->assertStatus(403);
        $this->withToken($this->tokenCashierA)
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => 1,
                'return_date' => '2026-01-20',
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 5, 'unit_cost' => 5000]],
            ])->assertStatus(403);
    }

    public function test_gate_rbac_staff_no_access(): void
    {
        $this->withToken($this->tokenStaffA)
            ->getJson('/api/v1/purchases')->assertStatus(403);
        $this->withToken($this->tokenStaffA)
            ->getJson('/api/v1/purchase-returns')->assertStatus(403);
        $this->withToken($this->tokenStaffA)
            ->postJson('/api/v1/purchases', [])->assertStatus(403);
        $this->withToken($this->tokenStaffA)
            ->postJson('/api/v1/purchase-returns', [])->assertStatus(403);
    }

    // --- IDOR ---

    public function test_gate_idor_nonexistent_purchase(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/purchases/99999')->assertStatus(404);
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases/99999/order')->assertStatus(404);
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases/99999/receive')->assertStatus(404);
        $this->withToken($this->tokenOwnerA)
            ->deleteJson('/api/v1/purchases/99999')->assertStatus(404);
    }

    public function test_gate_idor_nonexistent_return(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/purchase-returns/99999')->assertStatus(404);
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns/99999/complete')->assertStatus(404);
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns/99999/cancel')->assertStatus(404);
        $this->withToken($this->tokenOwnerA)
            ->deleteJson('/api/v1/purchase-returns/99999')->assertStatus(404);
    }

    // =========================================================================
    // 3. INVENTORY INTEGRITY
    // =========================================================================

    public function test_inventory_integrity_receive_then_return_sequence(): void
    {
        $purchase = $this->createReceivedPurchase(100);

        // Stock = 100
        $this->assertEquals(100, $this->getInventory($this->productA1->id)->quantity);

        // Return 20 → complete → stock = 80
        $r1 = $this->createReturn($purchase, [
            ['product_id' => $this->productA1->id, 'quantity' => 20, 'unit_cost' => 5000],
        ]);
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$r1->id}/complete")->assertStatus(200);
        $this->assertEquals(80, $this->getInventory($this->productA1->id)->quantity);

        // Return 30 → complete → stock = 50
        $r2 = $this->createReturn($purchase, [
            ['product_id' => $this->productA1->id, 'quantity' => 30, 'unit_cost' => 5000],
        ]);
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$r2->id}/complete")->assertStatus(200);
        $this->assertEquals(50, $this->getInventory($this->productA1->id)->quantity);

        // Return 60 → reject (20+30+60=110 > 100)
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $purchase->id,
                'return_date' => '2026-01-25',
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 60, 'unit_cost' => 5000]],
            ])->assertStatus(422);

        // Stock still 50
        $this->assertEquals(50, $this->getInventory($this->productA1->id)->quantity);
    }

    public function test_inventory_integrity_double_complete_no_change(): void
    {
        $purchase = $this->createReceivedPurchase(100);

        $return = $this->createReturn($purchase, [
            ['product_id' => $this->productA1->id, 'quantity' => 20, 'unit_cost' => 5000],
        ]);

        // First complete → stock = 80
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete")->assertStatus(200);
        $this->assertEquals(80, $this->getInventory($this->productA1->id)->quantity);

        // Second complete → 422, stock unchanged
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete")->assertStatus(422);
        $this->assertEquals(80, $this->getInventory($this->productA1->id)->quantity);

        // Only 1 purchase_return movement
        $returnMovements = $this->getMovements($this->productA1->id)
            ->where('type', 'purchase_return');
        $this->assertCount(1, $returnMovements);
    }

    public function test_inventory_integrity_draft_no_change(): void
    {
        $this->createReceivedPurchase(100);

        $purchase = Purchase::withoutTenantScope()->where('tenant_id', $this->tenantA->id)->first();

        // Create a draft purchase (not received)
        $this->actingAs($this->ownerA, 'sanctum');
        $draft = app(PurchaseService::class)->create([
            'supplier_id' => $this->supplierA->id,
            'store_id' => $this->storeA->id,
            'purchase_date' => '2026-01-15',
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 50, 'unit_cost' => 5000]],
        ]);
        Auth::forgetGuards();

        // Try to return from draft purchase
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $draft->id,
                'return_date' => '2026-01-20',
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 10, 'unit_cost' => 5000]],
            ]);
        $response->assertStatus(422);

        // Inventory unchanged
        $this->assertEquals(100, $this->getInventory($this->productA1->id)->quantity);
    }

    public function test_inventory_integrity_cancelled_return_no_change(): void
    {
        $purchase = $this->createReceivedPurchase(100);

        $return = $this->createReturn($purchase, [
            ['product_id' => $this->productA1->id, 'quantity' => 20, 'unit_cost' => 5000],
        ]);

        // Cancel instead of complete
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/cancel")->assertStatus(200);

        // Inventory unchanged
        $this->assertEquals(100, $this->getInventory($this->productA1->id)->quantity);

        // No purchase_return movement
        $returnMovements = $this->getMovements($this->productA1->id)
            ->where('type', 'purchase_return');
        $this->assertCount(0, $returnMovements);
    }

    // =========================================================================
    // 4. TRANSACTION / ROLLBACK
    // =========================================================================

    public function test_transaction_rollback_partial_failure(): void
    {
        $purchase = $this->createReceivedPurchase(100, 50);

        // productA1 has 100, productA2 has 50
        $this->assertEquals(100, $this->getInventory($this->productA1->id)->quantity);
        $this->assertEquals(50, $this->getInventory($this->productA2->id)->quantity);

        // Create return with 2 items: first valid (20 of productA1), second invalid (200 of productA2, exceeds 50)
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $purchase->id,
                'return_date' => '2026-01-20',
                'items' => [
                    ['product_id' => $this->productA1->id, 'quantity' => 20, 'unit_cost' => 5000],
                    ['product_id' => $this->productA2->id, 'quantity' => 200, 'unit_cost' => 6000],
                ],
            ]);

        // Should be rejected
        $response->assertStatus(422);

        // No return should exist
        $this->assertDatabaseCount('purchase_returns', 0);
        $this->assertDatabaseCount('purchase_return_items', 0);

        // Inventory unchanged
        $this->assertEquals(100, $this->getInventory($this->productA1->id)->quantity);
        $this->assertEquals(50, $this->getInventory($this->productA2->id)->quantity);
    }

    public function test_transaction_rollback_complete_with_insufficient_stock(): void
    {
        $purchase = $this->createReceivedPurchase(100);

        // Manually reduce stock to 10
        $inv = $this->getInventory($this->productA1->id);
        $inv->quantity = 10;
        $inv->save();

        // Create return for 20 (valid at creation time since purchased=100, returned=0)
        $return = $this->createReturn($purchase, [
            ['product_id' => $this->productA1->id, 'quantity' => 20, 'unit_cost' => 5000],
        ]);

        // Try to complete → should fail (stock=10, return=20)
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete");
        $response->assertStatus(422);

        // Return status should still be draft (not completed)
        $return->refresh();
        $this->assertEquals('draft', $return->status);

        // Inventory unchanged (still 10)
        $this->assertEquals(10, $this->getInventory($this->productA1->id)->quantity);

        // No purchase_return movement
        $returnMovements = $this->getMovements($this->productA1->id)
            ->where('type', 'purchase_return');
        $this->assertCount(0, $returnMovements);
    }

    // =========================================================================
    // 5. BUSINESS LOGIC
    // =========================================================================

    public function test_business_logic_purchase_status_transitions(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $purchase = app(PurchaseService::class)->create([
            'supplier_id' => $this->supplierA->id,
            'store_id' => $this->storeA->id,
            'purchase_date' => '2026-01-15',
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 10, 'unit_cost' => 5000]],
        ]);
        Auth::forgetGuards();

        // draft → ordered ✅
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/order")->assertStatus(200);

        // ordered → received ✅
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/receive")->assertStatus(200);

        // received → receive again ❌
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/receive")->assertStatus(422);

        // received → order ❌
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/order")->assertStatus(422);
    }

    public function test_business_logic_return_status_transitions(): void
    {
        $purchase = $this->createReceivedPurchase(100);

        // draft → completed ✅
        $r1 = $this->createReturn($purchase, [
            ['product_id' => $this->productA1->id, 'quantity' => 10, 'unit_cost' => 5000],
        ]);
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$r1->id}/complete")->assertStatus(200);

        // completed → complete ❌
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$r1->id}/complete")->assertStatus(422);

        // completed → cancel ❌
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$r1->id}/cancel")->assertStatus(422);

        // completed → delete ❌
        $this->withToken($this->tokenOwnerA)
            ->deleteJson("/api/v1/purchase-returns/{$r1->id}")->assertStatus(422);

        // draft → cancelled ✅
        $r2 = $this->createReturn($purchase, [
            ['product_id' => $this->productA1->id, 'quantity' => 10, 'unit_cost' => 5000],
        ]);
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$r2->id}/cancel")->assertStatus(200);

        // cancelled → complete ❌
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$r2->id}/complete")->assertStatus(422);

        // cancelled → cancel ❌
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$r2->id}/cancel")->assertStatus(422);
    }

    public function test_business_logic_return_from_non_received_purchase(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $draftPurchase = app(PurchaseService::class)->create([
            'supplier_id' => $this->supplierA->id,
            'store_id' => $this->storeA->id,
            'purchase_date' => '2026-01-15',
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 10, 'unit_cost' => 5000]],
        ]);
        $orderedPurchase = app(PurchaseService::class)->create([
            'supplier_id' => $this->supplierA->id,
            'store_id' => $this->storeA->id,
            'purchase_date' => '2026-01-15',
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 10, 'unit_cost' => 5000]],
        ]);
        app(PurchaseService::class)->order($orderedPurchase);
        Auth::forgetGuards();

        // Return from draft ❌
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $draftPurchase->id,
                'return_date' => '2026-01-20',
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 5, 'unit_cost' => 5000]],
            ])->assertStatus(422);

        // Return from ordered ❌
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $orderedPurchase->id,
                'return_date' => '2026-01-20',
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 5, 'unit_cost' => 5000]],
            ])->assertStatus(422);
    }

    public function test_business_logic_return_from_received_purchase_ok(): void
    {
        $purchase = $this->createReceivedPurchase(100);

        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $purchase->id,
                'return_date' => '2026-01-20',
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 5, 'unit_cost' => 5000]],
            ])->assertStatus(201);
    }

    public function test_business_logic_return_exceeds_purchased(): void
    {
        $purchase = $this->createReceivedPurchase(100);

        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $purchase->id,
                'return_date' => '2026-01-20',
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 101, 'unit_cost' => 5000]],
            ])->assertStatus(422);
    }

    public function test_business_logic_total_spoofing(): void
    {
        $purchase = $this->createReceivedPurchase(100);

        // Spoof totals on return
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $purchase->id,
                'return_date' => '2026-01-20',
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 20, 'unit_cost' => 5000, 'total' => 1]],
                'subtotal' => 1,
                'total' => 1,
                'discount' => 999,
                'tax' => 999,
            ]);
        $response->assertStatus(201);
        $response->assertJsonPath('items.0.total', '100000.00');
        $response->assertJsonPath('subtotal', '100000.00');
        $response->assertJsonPath('total', '100000.00');
        $response->assertJsonPath('discount', '0.00');
        $response->assertJsonPath('tax', '0.00');
    }

    public function test_business_logic_empty_items(): void
    {
        $purchase = $this->createReceivedPurchase(100);

        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $purchase->id,
                'return_date' => '2026-01-20',
                'items' => [],
            ])->assertStatus(422);
    }

    public function test_business_logic_negative_values(): void
    {
        $purchase = $this->createReceivedPurchase(100);

        // Negative quantity
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $purchase->id,
                'return_date' => '2026-01-20',
                'items' => [['product_id' => $this->productA1->id, 'quantity' => -10, 'unit_cost' => 5000]],
            ])->assertStatus(422);

        // Negative unit cost
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchase-returns', [
                'purchase_id' => $purchase->id,
                'return_date' => '2026-01-20',
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 10, 'unit_cost' => -100]],
            ])->assertStatus(422);
    }

    // =========================================================================
    // 6. MOVEMENT HISTORY VERIFICATION
    // =========================================================================

    public function test_movement_history_complete_verification(): void
    {
        $purchase = $this->createReceivedPurchase(100, 50);

        // Return 20 of productA1
        $return = $this->createReturn($purchase, [
            ['product_id' => $this->productA1->id, 'quantity' => 20, 'unit_cost' => 5000],
        ]);
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete")->assertStatus(200);

        // Verify productA1 movement history
        $movements = $this->getMovements($this->productA1->id);
        $this->assertCount(2, $movements);

        // Movement 1: purchase
        $this->assertEquals('purchase', $movements[0]->type);
        $this->assertEquals(100, $movements[0]->quantity);
        $this->assertEquals(0, $movements[0]->before_quantity);
        $this->assertEquals(100, $movements[0]->after_quantity);
        $this->assertEquals($purchase->id, $movements[0]->reference_id);
        $this->assertEquals(Purchase::class, $movements[0]->reference_type);

        // Movement 2: purchase_return
        $this->assertEquals('purchase_return', $movements[1]->type);
        $this->assertEquals(-20, $movements[1]->quantity);
        $this->assertEquals(100, $movements[1]->before_quantity);
        $this->assertEquals(80, $movements[1]->after_quantity);
        $this->assertEquals($return->id, $movements[1]->reference_id);
        $this->assertEquals(PurchaseReturn::class, $movements[1]->reference_type);

        // Verify productA2 movement history (only purchase, no return)
        $movements2 = $this->getMovements($this->productA2->id);
        $this->assertCount(1, $movements2);
        $this->assertEquals('purchase', $movements2[0]->type);
        $this->assertEquals(50, $movements2[0]->quantity);
    }

    public function test_movement_history_api(): void
    {
        $purchase = $this->createReceivedPurchase(100);

        $return = $this->createReturn($purchase, [
            ['product_id' => $this->productA1->id, 'quantity' => 20, 'unit_cost' => 5000],
        ]);
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete")->assertStatus(200);

        // Fetch movements via API
        $response = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/inventory/movements?product_id={$this->productA1->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        // API returns latest first (descending)
        $this->assertEquals('purchase_return', $data[0]['type']);
        $this->assertEquals(-20, $data[0]['quantity']);
        $this->assertEquals('purchase', $data[1]['type']);
        $this->assertEquals(100, $data[1]['quantity']);
    }

    // =========================================================================
    // 7. TENANT B INVENTORY UNCHANGED
    // =========================================================================

    public function test_tenant_b_inventory_unchanged_throughout(): void
    {
        // Create inventory in Tenant B
        $invB = new Inventory;
        $invB->tenant_id = $this->tenantB->id;
        $invB->store_id = $this->storeB->id;
        $invB->product_id = $this->productB->id;
        $invB->quantity = 200;
        $invB->save();

        // Tenant A: receive + return
        $purchase = $this->createReceivedPurchase(100);
        $return = $this->createReturn($purchase, [
            ['product_id' => $this->productA1->id, 'quantity' => 20, 'unit_cost' => 5000],
        ]);
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchase-returns/{$return->id}/complete")->assertStatus(200);

        // Tenant B inventory unchanged
        $invBAfter = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeB->id)
            ->where('product_id', $this->productB->id)
            ->first();
        $this->assertEquals(200, $invBAfter->quantity);

        // Tenant B has no movements
        $movementsB = InventoryMovement::withoutTenantScope()
            ->where('store_id', $this->storeB->id)
            ->where('product_id', $this->productB->id)
            ->get();
        $this->assertCount(0, $movementsB);
    }
}
