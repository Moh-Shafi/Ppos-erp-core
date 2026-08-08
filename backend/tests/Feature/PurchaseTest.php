<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Role;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $ownerA;
    private User $ownerB;
    private User $cashierA;
    private User $staffA;
    private string $tokenOwnerA;
    private string $tokenOwnerB;
    private string $tokenCashierA;
    private string $tokenStaffA;
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
        $cashierRole = Role::where('slug', 'cashier')->first();
        $staffRole = Role::where('slug', 'staff')->first();

        $this->tenantA = Tenant::create(['name' => 'Toko A', 'slug' => 'toko-a']);
        $this->ownerA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner A', 'email' => 'owner.a@t.com', 'password' => 'password',
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

    // --- API Smoke ---

    public function test_list_purchases(): void
    {
        Auth::login($this->ownerA);
        app(PurchaseService::class)->create($this->purchaseData());

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/purchases');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_show_purchase(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/purchases/{$purchase->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('purchase_number', $purchase->purchase_number);
        $response->assertJsonPath('status', 'draft');
        $response->assertJsonCount(1, 'items');
    }

    public function test_create_purchase(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData());

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'draft');
        $response->assertJsonPath('subtotal', '250000.00');
        $response->assertJsonPath('total', '250000.00');
        $response->assertJsonPath('tenant_id', $this->tenantA->id);
        $response->assertJsonPath('created_by', $this->ownerA->id);
        $response->assertJsonCount(1, 'items');
        $response->assertJsonPath('items.0.total', '250000.00');
    }

    public function test_create_purchase_with_multiple_items(): void
    {
        $product2 = new Product;
        $product2->tenant_id = $this->tenantA->id;
        $product2->category_id = $this->catA->id;
        $product2->name = 'Sprite';
        $product2->sku = 'SPR-001';
        $product2->barcode = '222222';
        $product2->cost_price = 4000;
        $product2->selling_price = 7000;
        $product2->unit = 'botol';
        $product2->save();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 50, 'unit_cost' => 5000],
                    ['product_id' => $product2->id, 'quantity' => 30, 'unit_cost' => 4000, 'discount' => 1000, 'tax' => 500],
                ],
            ]));

        $response->assertStatus(201);
        $response->assertJsonCount(2, 'items');
        // subtotal = 50*5000 + 30*4000 = 250000 + 120000 = 370000
        $response->assertJsonPath('subtotal', '370000.00');
        // discount = 1000, tax = 500
        $response->assertJsonPath('discount', '1000.00');
        $response->assertJsonPath('tax', '500.00');
        // total = 370000 - 1000 + 500 = 369500
        $response->assertJsonPath('total', '369500.00');
    }

    public function test_total_calculated_by_backend_not_from_request(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 10, 'unit_cost' => 5000, 'total' => 1],
                ],
            ]));

        $response->assertStatus(201);
        // Backend should calculate 10 * 5000 = 50000, not 1
        $response->assertJsonPath('items.0.total', '50000.00');
        $response->assertJsonPath('total', '50000.00');
    }

    public function test_update_draft_purchase(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());

        $response = $this->withToken($this->tokenOwnerA)
            ->putJson("/api/v1/purchases/{$purchase->id}", [
                'notes' => 'Updated notes',
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 100, 'unit_cost' => 4500],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('notes', 'Updated notes');
        $response->assertJsonPath('subtotal', '450000.00');
        $response->assertJsonPath('total', '450000.00');
    }

    public function test_delete_draft_purchase(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());

        $response = $this->withToken($this->tokenOwnerA)
            ->deleteJson("/api/v1/purchases/{$purchase->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('purchases', ['id' => $purchase->id]);
    }

    public function test_cannot_delete_ordered_purchase(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        app(PurchaseService::class)->order($purchase);

        $response = $this->withToken($this->tokenOwnerA)
            ->deleteJson("/api/v1/purchases/{$purchase->id}");

        $response->assertStatus(422);
    }

    // --- Status Workflow ---

    public function test_draft_to_ordered(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/order");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'ordered');
    }

    public function test_ordered_to_received(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        app(PurchaseService::class)->order($purchase);

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/receive");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'received');
    }

    public function test_cancel_draft(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/cancel");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'cancelled');
    }

    public function test_cancel_ordered(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        app(PurchaseService::class)->order($purchase);

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/cancel");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'cancelled');
    }

    public function test_cannot_cancel_received(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        app(PurchaseService::class)->order($purchase);
        app(PurchaseService::class)->receive($purchase);

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/cancel");

        $response->assertStatus(422);
    }

    public function test_cannot_order_non_draft(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        app(PurchaseService::class)->order($purchase);

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/order");

        $response->assertStatus(422);
    }

    public function test_cannot_receive_draft(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/receive");

        $response->assertStatus(422);
    }

    public function test_cannot_receive_cancelled(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        app(PurchaseService::class)->cancel($purchase);

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/receive");

        $response->assertStatus(422);
    }

    public function test_cannot_receive_twice(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        app(PurchaseService::class)->order($purchase);
        app(PurchaseService::class)->receive($purchase);

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/purchases/{$purchase->id}/receive");

        $response->assertStatus(422);
    }

    // --- Inventory Integration ---

    public function test_receiving_increases_inventory(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        app(PurchaseService::class)->order($purchase);
        app(PurchaseService::class)->receive($purchase);

        $inv = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();

        $this->assertNotNull($inv);
        $this->assertEquals(50, $inv->quantity);
    }

    public function test_receiving_creates_inventory_movement(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        app(PurchaseService::class)->order($purchase);
        app(PurchaseService::class)->receive($purchase);

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

    public function test_draft_does_not_increase_inventory(): void
    {
        Auth::login($this->ownerA);
        app(PurchaseService::class)->create($this->purchaseData());

        $inv = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();

        $this->assertNull($inv);
    }

    public function test_ordered_does_not_increase_inventory(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        app(PurchaseService::class)->order($purchase);

        $inv = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();

        $this->assertNull($inv);
    }

    public function test_duplicate_receiving_does_not_double_inventory(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        app(PurchaseService::class)->order($purchase);
        app(PurchaseService::class)->receive($purchase);

        // Try to receive again
        try {
            app(PurchaseService::class)->receive($purchase);
        } catch (\DomainException $e) {
            // expected
        }

        $inv = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA->id)
            ->first();

        $this->assertEquals(50, $inv->quantity); // not 100
    }

    // --- Tenant Isolation ---

    public function test_tenant_a_cannot_see_tenant_b_purchases(): void
    {
        Auth::login($this->ownerA);
        app(PurchaseService::class)->create($this->purchaseData());
        Auth::logout();

        $response = $this->withToken($this->tokenOwnerB)
            ->getJson('/api/v1/purchases');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_tenant_a_cannot_access_tenant_b_purchase(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        Auth::logout();

        $response = $this->withToken($this->tokenOwnerB)
            ->getJson("/api/v1/purchases/{$purchase->id}");

        $response->assertStatus(404);
    }

    public function test_tenant_a_cannot_update_tenant_b_purchase(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        Auth::logout();

        $response = $this->withToken($this->tokenOwnerB)
            ->putJson("/api/v1/purchases/{$purchase->id}", ['notes' => 'Hacked']);

        $response->assertStatus(404);
    }

    public function test_tenant_a_cannot_delete_tenant_b_purchase(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        Auth::logout();

        $response = $this->withToken($this->tokenOwnerB)
            ->deleteJson("/api/v1/purchases/{$purchase->id}");

        $response->assertStatus(404);
    }

    public function test_tenant_a_cannot_receive_tenant_b_purchase(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        app(PurchaseService::class)->order($purchase);
        Auth::logout();

        $response = $this->withToken($this->tokenOwnerB)
            ->postJson("/api/v1/purchases/{$purchase->id}/receive");

        $response->assertStatus(404);
    }

    // --- Cross-tenant Supplier/Store/Product ---

    public function test_cross_tenant_supplier_blocked(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'supplier_id' => $this->supplierB->id,
            ]));

        $response->assertStatus(422);
    }

    public function test_cross_tenant_store_blocked(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'store_id' => $this->storeB->id,
            ]));

        $response->assertStatus(422);
    }

    public function test_cross_tenant_product_blocked(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'items' => [
                    ['product_id' => $this->productB->id, 'quantity' => 10, 'unit_cost' => 1000],
                ],
            ]));

        $response->assertStatus(422);
    }

    // --- Mass Assignment ---

    public function test_tenant_id_ignored_from_request(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'tenant_id' => $this->tenantB->id,
            ]));

        $response->assertStatus(201);
        $response->assertJsonPath('tenant_id', $this->tenantA->id);
    }

    public function test_created_by_ignored_from_request(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'created_by' => $this->ownerB->id,
            ]));

        $response->assertStatus(201);
        $response->assertJsonPath('created_by', $this->ownerA->id);
    }

    public function test_subtotal_ignored_from_request(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'subtotal' => 1,
                'total' => 1,
            ]));

        $response->assertStatus(201);
        $response->assertJsonPath('subtotal', '250000.00');
        $response->assertJsonPath('total', '250000.00');
    }

    public function test_status_ignored_from_request(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'status' => 'received',
            ]));

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'draft');
    }

    // --- Authentication ---

    public function test_unauthenticated_list(): void
    {
        $response = $this->getJson('/api/v1/purchases');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_create(): void
    {
        $response = $this->postJson('/api/v1/purchases', $this->purchaseData());
        $response->assertStatus(401);
    }

    public function test_unauthenticated_receive(): void
    {
        $response = $this->postJson('/api/v1/purchases/1/receive');
        $response->assertStatus(401);
    }

    // --- RBAC ---

    public function test_cashier_can_view_purchases(): void
    {
        Auth::login($this->ownerA);
        app(PurchaseService::class)->create($this->purchaseData());
        Auth::logout();

        $response = $this->withToken($this->tokenCashierA)
            ->getJson('/api/v1/purchases');

        $response->assertStatus(200);
    }

    public function test_cashier_cannot_create_purchase(): void
    {
        $response = $this->withToken($this->tokenCashierA)
            ->postJson('/api/v1/purchases', $this->purchaseData());

        $response->assertStatus(403);
    }

    public function test_cashier_cannot_receive_purchase(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        app(PurchaseService::class)->order($purchase);
        Auth::logout();

        $response = $this->withToken($this->tokenCashierA)
            ->postJson("/api/v1/purchases/{$purchase->id}/receive");

        $response->assertStatus(403);
    }

    public function test_staff_cannot_view_purchases(): void
    {
        $response = $this->withToken($this->tokenStaffA)
            ->getJson('/api/v1/purchases');

        $response->assertStatus(403);
    }

    public function test_staff_cannot_create_purchase(): void
    {
        $response = $this->withToken($this->tokenStaffA)
            ->postJson('/api/v1/purchases', $this->purchaseData());

        $response->assertStatus(403);
    }

    // --- IDOR ---

    public function test_idor_get_nonexistent_returns_404(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/purchases/99999');

        $response->assertStatus(404);
    }

    public function test_idor_receive_nonexistent_returns_404(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases/99999/receive');

        $response->assertStatus(404);
    }

    // --- Validation ---

    public function test_create_missing_items(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $this->supplierA->id,
                'store_id' => $this->storeA->id,
                'purchase_date' => '2026-01-15',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items']);
    }

    public function test_create_missing_supplier(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', [
                'store_id' => $this->storeA->id,
                'purchase_date' => '2026-01-15',
                'items' => [['product_id' => $this->productA->id, 'quantity' => 10, 'unit_cost' => 5000]],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['supplier_id']);
    }

    public function test_create_invalid_quantity(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'items' => [['product_id' => $this->productA->id, 'quantity' => 0, 'unit_cost' => 5000]],
            ]));

        $response->assertStatus(422);
    }

    public function test_create_negative_unit_cost(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/purchases', $this->purchaseData([
                'items' => [['product_id' => $this->productA->id, 'quantity' => 10, 'unit_cost' => -100]],
            ]));

        $response->assertStatus(422);
    }

    // --- Filter ---

    public function test_filter_by_status(): void
    {
        Auth::login($this->ownerA);
        $p1 = app(PurchaseService::class)->create($this->purchaseData());
        $p2 = app(PurchaseService::class)->create($this->purchaseData());
        app(PurchaseService::class)->order($p2);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/purchases?status=ordered');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'ordered');
    }

    // --- Model ---

    public function test_purchase_belongs_to_tenant(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        $this->assertInstanceOf(Tenant::class, $purchase->tenant);
        $this->assertEquals($this->tenantA->id, $purchase->tenant->id);
    }

    public function test_purchase_has_items(): void
    {
        Auth::login($this->ownerA);
        $purchase = app(PurchaseService::class)->create($this->purchaseData());
        $this->assertCount(1, $purchase->items);
        $this->assertInstanceOf(PurchaseItem::class, $purchase->items->first());
    }

    public function test_purchase_tenant_id_not_mass_assignable(): void
    {
        $p = new Purchase;
        $p->fill(['tenant_id' => $this->tenantB->id, 'purchase_number' => 'TEST']);
        $this->assertNull($p->tenant_id);
    }

    public function test_purchase_number_is_unique(): void
    {
        Auth::login($this->ownerA);
        $p1 = app(PurchaseService::class)->create($this->purchaseData());
        $p2 = app(PurchaseService::class)->create($this->purchaseData());

        $this->assertNotEquals($p1->purchase_number, $p2->purchase_number);
    }
}
