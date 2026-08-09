<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SaleApiTest extends TestCase
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
    private Customer $customerA;
    private Customer $customerB;
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
            'name' => 'Owner A', 'email' => 'owner.a@saleapi.com', 'password' => 'password',
        ]);
        $this->managerA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $managerRole->id,
            'name' => 'Manager A', 'email' => 'manager.a@saleapi.com', 'password' => 'password',
        ]);
        $this->cashierA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $cashierRole->id,
            'name' => 'Cashier A', 'email' => 'cashier.a@saleapi.com', 'password' => 'password',
        ]);
        $this->staffA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $staffRole->id,
            'name' => 'Staff A', 'email' => 'staff.a@saleapi.com', 'password' => 'password',
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

        $this->customerA = new Customer;
        $this->customerA->tenant_id = $this->tenantA->id;
        $this->customerA->name = 'John Doe';
        $this->customerA->is_active = true;
        $this->customerA->save();

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
        $this->productA2->name = 'Es Teh';
        $this->productA2->sku = 'TEH-001';
        $this->productA2->barcode = '654321';
        $this->productA2->cost_price = 3000;
        $this->productA2->selling_price = 5000;
        $this->productA2->unit = 'gelas';
        $this->productA2->save();

        // Tenant B
        $this->tenantB = Tenant::create(['name' => 'Toko B', 'slug' => 'toko-b']);
        $this->ownerB = User::create([
            'tenant_id' => $this->tenantB->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner B', 'email' => 'owner.b@saleapi.com', 'password' => 'password',
        ]);
        $this->tokenOwnerB = $this->ownerB->createToken('test')->plainTextToken;

        $this->storeB = new Store;
        $this->storeB->tenant_id = $this->tenantB->id;
        $this->storeB->name = 'Store B';
        $this->storeB->code = 'SB';
        $this->storeB->is_active = true;
        $this->storeB->save();

        $this->customerB = new Customer;
        $this->customerB->tenant_id = $this->tenantB->id;
        $this->customerB->name = 'Jane Smith';
        $this->customerB->is_active = true;
        $this->customerB->save();

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
        $this->productB->barcode = '999999';
        $this->productB->cost_price = 15000;
        $this->productB->selling_price = 25000;
        $this->productB->unit = 'porsi';
        $this->productB->save();
    }

    private function setInventory(Store $store, Product $product, int $qty): void
    {
        $inv = Inventory::withoutTenantScope()
            ->where('tenant_id', $store->tenant_id)
            ->where('store_id', $store->id)
            ->where('product_id', $product->id)
            ->first();

        if (!$inv) {
            $inv = new Inventory;
            $inv->tenant_id = $store->tenant_id;
            $inv->store_id = $store->id;
            $inv->product_id = $product->id;
            $inv->minimum_quantity = 0;
        }
        $inv->quantity = $qty;
        $inv->save();
    }

    private function checkoutPayload(array $items, array $payments = [], array $extra = []): array
    {
        return array_merge([
            'store_id' => $this->storeA->id,
            'items' => $items,
            'payments' => $payments ?: [['payment_method' => 'cash', 'amount' => 999999]],
        ], $extra);
    }

    // =========================================================================
    // 1. CHECKOUT — SUCCESS
    // =========================================================================

    public function test_checkout_success_returns_201(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 2]],
                [['payment_method' => 'cash', 'amount' => 16000]],
            ));

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'completed');
        $response->assertJsonPath('payment_status', 'paid');
        $response->assertJsonPath('subtotal', '16000.00');
        $response->assertJsonPath('total', '16000.00');
        $response->assertJsonPath('cashier_id', $this->ownerA->id);
        $response->assertJsonStructure([
            'id', 'sale_number', 'status', 'payment_status', 'sale_date',
            'subtotal', 'discount', 'tax', 'total', 'paid_amount', 'change_amount',
            'store', 'cashier', 'customer', 'items', 'payments',
        ]);
    }

    public function test_checkout_cashier_can_checkout(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenCashierA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
            ));

        $response->assertStatus(201);
        $response->assertJsonPath('cashier_id', $this->cashierA->id);
    }

    public function test_checkout_manager_can_checkout(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenManagerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
            ));

        $response->assertStatus(201);
        $response->assertJsonPath('cashier_id', $this->managerA->id);
    }

    public function test_checkout_with_customer(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
                ['customer_id' => $this->customerA->id],
            ));

        $response->assertStatus(201);
        $response->assertJsonPath('customer_id', $this->customerA->id);
    }

    public function test_checkout_multiple_products_response(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        $this->setInventory($this->storeA, $this->productA2, 50);
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [
                    ['product_id' => $this->productA1->id, 'quantity' => 2],
                    ['product_id' => $this->productA2->id, 'quantity' => 3],
                ],
                [['payment_method' => 'cash', 'amount' => 31000]],
            ));

        $response->assertStatus(201);
        $response->assertJsonPath('subtotal', '31000.00');
        $response->assertJsonCount(2, 'items');
    }

    public function test_checkout_split_payment_response(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 2]],
                [
                    ['payment_method' => 'cash', 'amount' => 10000],
                    ['payment_method' => 'qris', 'amount' => 6000],
                ],
            ));

        $response->assertStatus(201);
        $response->assertJsonCount(2, 'payments');
        $response->assertJsonPath('paid_amount', '16000.00');
    }

    // =========================================================================
    // 2. CHECKOUT — VALIDATION ERRORS (422)
    // =========================================================================

    public function test_checkout_missing_store_id(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', [
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'cash', 'amount' => 8000]],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['store_id']);
    }

    public function test_checkout_missing_items(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', [
                'store_id' => $this->storeA->id,
                'items' => [],
                'payments' => [['payment_method' => 'cash', 'amount' => 8000]],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items']);
    }

    public function test_checkout_missing_payments(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', [
                'store_id' => $this->storeA->id,
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 1]],
                'payments' => [],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payments']);
    }

    public function test_checkout_invalid_payment_method(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'crypto', 'amount' => 8000]],
            ));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payments.0.payment_method']);
    }

    public function test_checkout_zero_quantity_validation(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 0]],
            ));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_checkout_negative_quantity_validation(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => -5]],
            ));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_checkout_nonexistent_product_validation(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => 99999, 'quantity' => 1]],
            ));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items.0.product_id']);
    }

    public function test_checkout_zero_payment_validation(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 0]],
            ));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payments.0.amount']);
    }

    // =========================================================================
    // 3. CHECKOUT — UNAUTHORIZED (401)
    // =========================================================================

    public function test_checkout_no_token_returns_401(): void
    {
        $response = $this->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
            [['product_id' => $this->productA1->id, 'quantity' => 1]],
        ));

        $response->assertStatus(401);
    }

    public function test_checkout_invalid_token_returns_401(): void
    {
        $response = $this->withToken('invalid-token')
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
            ));

        $response->assertStatus(401);
    }

    public function test_list_sales_no_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/sales');

        $response->assertStatus(401);
    }

    public function test_show_sale_no_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/sales/1');

        $response->assertStatus(401);
    }

    public function test_cancel_sale_no_token_returns_401(): void
    {
        $response = $this->postJson('/api/v1/sales/1/cancel');

        $response->assertStatus(401);
    }

    // =========================================================================
    // 4. CHECKOUT — FORBIDDEN (403)
    // =========================================================================

    public function test_checkout_staff_forbidden(): void
    {
        $response = $this->withToken($this->tokenStaffA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
            ));

        $response->assertStatus(403);
    }

    public function test_list_sales_staff_forbidden(): void
    {
        $response = $this->withToken($this->tokenStaffA)
            ->getJson('/api/v1/sales');

        $response->assertStatus(403);
    }

    // =========================================================================
    // 5. CHECKOUT — TENANT ISOLATION
    // =========================================================================

    public function test_checkout_cross_tenant_store_returns_422(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', [
                'store_id' => $this->storeB->id,
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'cash', 'amount' => 8000]],
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Store does not belong to your tenant');
    }

    public function test_checkout_cross_tenant_product_returns_422(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productB->id, 'quantity' => 1]],
            ));

        $response->assertStatus(422);
    }

    public function test_checkout_cross_tenant_customer_returns_422(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
                ['customer_id' => $this->customerB->id],
            ));

        $response->assertStatus(422);
    }

    public function test_tenant_b_cannot_see_tenant_a_sales(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
            ));
        $response->assertStatus(201);
        $saleId = $response->json('id');

        Auth::forgetGuards();

        // Tenant B tries to view Tenant A's sale
        $response = $this->withToken($this->tokenOwnerB)
            ->getJson("/api/v1/sales/{$saleId}");

        $response->assertStatus(404);
    }

    public function test_tenant_b_cannot_cancel_tenant_a_sale(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
            ));
        $saleId = $response->json('id');

        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerB)
            ->postJson("/api/v1/sales/{$saleId}/cancel");

        $response->assertStatus(404);
    }

    // =========================================================================
    // 6. CHECKOUT — STOCK FAILURE (422)
    // =========================================================================

    public function test_checkout_insufficient_stock_returns_422(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 5);
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 10]],
            ));

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Insufficient stock for Coca Cola. Available: 5, Requested: 10');
    }

    public function test_checkout_insufficient_stock_no_sale_created(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 5);
        Auth::forgetGuards();

        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 10]],
            ));

        $this->assertEquals(0, Sale::withoutTenantScope()->count());
        $this->assertEquals(5, Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA1->id)
            ->first()->quantity);
    }

    // =========================================================================
    // 7. CHECKOUT — MASS ASSIGNMENT
    // =========================================================================

    public function test_checkout_tenant_id_ignored_from_request(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
                ['tenant_id' => $this->tenantB->id, 'cashier_id' => $this->ownerB->id],
            ));

        $response->assertStatus(201);
        $response->assertJsonPath('tenant_id', $this->tenantA->id);
        $response->assertJsonPath('cashier_id', $this->ownerA->id);
    }

    public function test_checkout_total_spoofing_ignored(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 2]],
                [['payment_method' => 'cash', 'amount' => 16000]],
                ['total' => 1, 'subtotal' => 1],
            ));

        $response->assertStatus(201);
        $response->assertJsonPath('subtotal', '16000.00');
        $response->assertJsonPath('total', '16000.00');
    }

    // =========================================================================
    // 8. LIST / SHOW
    // =========================================================================

    public function test_list_sales(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        // Create 2 sales
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
            ))->assertStatus(201);

        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 2]],
                [['payment_method' => 'cash', 'amount' => 16000]],
            ))->assertStatus(201);

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/sales');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_list_sales_filter_by_status(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        // Create sale
        $r1 = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
            ));
        $r1->assertStatus(201);
        $saleId = $r1->json('id');

        // Create another and cancel it
        $r2 = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
            ));
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$r2->json('id')}/cancel")->assertStatus(200);

        // Filter by completed
        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/sales?status=completed');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'completed');

        // Filter by cancelled
        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/sales?status=cancelled');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'cancelled');
    }

    public function test_show_sale(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        $r = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 2]],
                [['payment_method' => 'cash', 'amount' => 16000]],
            ));
        $saleId = $r->json('id');

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/sales/{$saleId}");

        $response->assertStatus(200);
        $response->assertJsonPath('id', $saleId);
        $response->assertJsonStructure([
            'id', 'sale_number', 'status', 'items', 'payments', 'store', 'cashier',
        ]);
    }

    public function test_show_sale_includes_items_and_payments(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        $this->setInventory($this->storeA, $this->productA2, 50);
        Auth::forgetGuards();

        $r = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [
                    ['product_id' => $this->productA1->id, 'quantity' => 1],
                    ['product_id' => $this->productA2->id, 'quantity' => 2],
                ],
                [
                    ['payment_method' => 'cash', 'amount' => 10000],
                    ['payment_method' => 'qris', 'amount' => 8000],
                ],
            ));
        $saleId = $r->json('id');

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/sales/{$saleId}");

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'items');
        $response->assertJsonCount(2, 'payments');
    }

    public function test_show_nonexistent_sale_returns_404(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/sales/99999');

        $response->assertStatus(404);
    }

    // =========================================================================
    // 9. CANCEL
    // =========================================================================

    public function test_cancel_sale_success(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        $r = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 20]],
                [['payment_method' => 'cash', 'amount' => 160000]],
            ));
        $saleId = $r->json('id');

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$saleId}/cancel");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'cancelled');

        // Verify inventory restored
        $inv = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA1->id)
            ->first();
        $this->assertEquals(100, $inv->quantity);
    }

    public function test_cancel_cashier_can_cancel(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        $r = $this->withToken($this->tokenCashierA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
            ));
        $saleId = $r->json('id');

        $response = $this->withToken($this->tokenCashierA)
            ->postJson("/api/v1/sales/{$saleId}/cancel");

        $response->assertStatus(200);
    }

    public function test_cancel_staff_forbidden(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        $r = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
            ));
        $saleId = $r->json('id');

        Auth::forgetGuards();

        $response = $this->withToken($this->tokenStaffA)
            ->postJson("/api/v1/sales/{$saleId}/cancel");

        $response->assertStatus(403);
    }

    public function test_cancel_already_cancelled_returns_422(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        $r = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
            ));
        $saleId = $r->json('id');

        // First cancel OK
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$saleId}/cancel")->assertStatus(200);

        // Second cancel fails
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$saleId}/cancel");

        $response->assertStatus(422);
    }

    public function test_cancel_nonexistent_sale_returns_404(): void
    {
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/99999/cancel');

        $response->assertStatus(404);
    }

    // =========================================================================
    // 10. RBAC — ROLE-BASED ACCESS
    // =========================================================================

    public function test_cashier_can_list_sales(): void
    {
        $response = $this->withToken($this->tokenCashierA)
            ->getJson('/api/v1/sales');

        $response->assertStatus(200);
    }

    public function test_cashier_can_show_sale(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        $r = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
            ));
        $saleId = $r->json('id');

        $response = $this->withToken($this->tokenCashierA)
            ->getJson("/api/v1/sales/{$saleId}");

        $response->assertStatus(200);
    }

    public function test_staff_cannot_show_sale(): void
    {
        $response = $this->withToken($this->tokenStaffA)
            ->getJson('/api/v1/sales/1');

        $response->assertStatus(403);
    }

    // =========================================================================
    // 11. IDOR
    // =========================================================================

    public function test_idor_nonexistent_sale_checkout_not_affected(): void
    {
        // checkout endpoint doesn't take sale ID, but show/cancel should 404
        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/sales/99999');

        $response->assertStatus(404);
    }

    // =========================================================================
    // 12. RESPONSE STRUCTURE
    // =========================================================================

    public function test_checkout_response_has_sale_number(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
            ));

        $response->assertStatus(201);
        $this->assertNotEmpty($response->json('sale_number'));
        $this->assertStringStartsWith('INV-', $response->json('sale_number'));
    }

    public function test_checkout_response_items_have_snapshot(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 2]],
                [['payment_method' => 'cash', 'amount' => 16000]],
            ));

        $response->assertStatus(201);
        $response->assertJsonPath('items.0.product_name', 'Coca Cola');
        $response->assertJsonPath('items.0.sku', 'COKE-001');
        $response->assertJsonPath('items.0.unit_price', '8000.00');
        $response->assertJsonPath('items.0.quantity', 2);
        $response->assertJsonPath('items.0.total', '16000.00');
    }

    public function test_checkout_response_has_change_amount(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 10000]],
            ));

        $response->assertStatus(201);
        $response->assertJsonPath('change_amount', '2000.00'); // 10000 - 8000
    }

    // =========================================================================
    // 13. PAGINATION
    // =========================================================================

    public function test_list_sales_pagination(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 1000);
        Auth::forgetGuards();

        for ($i = 0; $i < 5; $i++) {
            $this->withToken($this->tokenOwnerA)
                ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                    [['product_id' => $this->productA1->id, 'quantity' => 1]],
                    [['payment_method' => 'cash', 'amount' => 8000]],
                ));
        }

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/sales?per_page=3');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonPath('total', 5);
        $response->assertJsonPath('per_page', 3);
    }
}
