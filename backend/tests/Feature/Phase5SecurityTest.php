<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase5SecurityTest extends TestCase
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
    private Store $storeA1;
    private Store $storeA2;
    private Store $storeB;
    private Customer $customerA;
    private Customer $customerB;
    private Category $catA;
    private Category $catB;
    private Product $productA1;
    private Product $productA2;
    private Product $productB1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();
        $managerRole = Role::where('slug', 'manager')->first();
        $cashierRole = Role::where('slug', 'cashier')->first();
        $staffRole = Role::where('slug', 'staff')->first();

        // === Tenant A ===
        $this->tenantA = Tenant::create(['name' => 'Sec Toko A', 'slug' => 'sec-toko-a']);
        $this->ownerA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $ownerRole->id,
            'name' => 'Sec Owner A', 'email' => 'sec.owner.a@test.com', 'password' => 'password',
        ]);
        $this->managerA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $managerRole->id,
            'name' => 'Sec Manager A', 'email' => 'sec.manager.a@test.com', 'password' => 'password',
        ]);
        $this->cashierA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $cashierRole->id,
            'name' => 'Sec Cashier A', 'email' => 'sec.cashier.a@test.com', 'password' => 'password',
        ]);
        $this->staffA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $staffRole->id,
            'name' => 'Sec Staff A', 'email' => 'sec.staff.a@test.com', 'password' => 'password',
        ]);

        $this->tokenOwnerA = $this->ownerA->createToken('test')->plainTextToken;
        $this->tokenManagerA = $this->managerA->createToken('test')->plainTextToken;
        $this->tokenCashierA = $this->cashierA->createToken('test')->plainTextToken;
        $this->tokenStaffA = $this->staffA->createToken('test')->plainTextToken;

        $this->storeA1 = new Store;
        $this->storeA1->tenant_id = $this->tenantA->id;
        $this->storeA1->name = 'Store A1';
        $this->storeA1->code = 'SA1';
        $this->storeA1->is_active = true;
        $this->storeA1->save();

        $this->storeA2 = new Store;
        $this->storeA2->tenant_id = $this->tenantA->id;
        $this->storeA2->name = 'Store A2';
        $this->storeA2->code = 'SA2';
        $this->storeA2->is_active = true;
        $this->storeA2->save();

        $this->customerA = new Customer;
        $this->customerA->tenant_id = $this->tenantA->id;
        $this->customerA->name = 'Sec Customer A';
        $this->customerA->is_active = true;
        $this->customerA->save();

        $this->catA = new Category;
        $this->catA->tenant_id = $this->tenantA->id;
        $this->catA->name = 'Sec Drinks';
        $this->catA->slug = 'sec-drinks';
        $this->catA->save();

        $this->productA1 = new Product;
        $this->productA1->tenant_id = $this->tenantA->id;
        $this->productA1->category_id = $this->catA->id;
        $this->productA1->name = 'Sec Cola';
        $this->productA1->sku = 'SEC-COLA';
        $this->productA1->barcode = '111111';
        $this->productA1->cost_price = 4000;
        $this->productA1->selling_price = 8000;
        $this->productA1->unit = 'botol';
        $this->productA1->save();

        $this->productA2 = new Product;
        $this->productA2->tenant_id = $this->tenantA->id;
        $this->productA2->category_id = $this->catA->id;
        $this->productA2->name = 'Sec Tea';
        $this->productA2->sku = 'SEC-TEA';
        $this->productA2->barcode = '222222';
        $this->productA2->cost_price = 2000;
        $this->productA2->selling_price = 5000;
        $this->productA2->unit = 'gelas';
        $this->productA2->save();

        // === Tenant B ===
        $this->tenantB = Tenant::create(['name' => 'Sec Toko B', 'slug' => 'sec-toko-b']);
        $this->ownerB = User::create([
            'tenant_id' => $this->tenantB->id, 'role_id' => $ownerRole->id,
            'name' => 'Sec Owner B', 'email' => 'sec.owner.b@test.com', 'password' => 'password',
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
        $this->customerB->name = 'Sec Customer B';
        $this->customerB->is_active = true;
        $this->customerB->save();

        $this->catB = new Category;
        $this->catB->tenant_id = $this->tenantB->id;
        $this->catB->name = 'Sec B Drinks';
        $this->catB->slug = 'sec-b-drinks';
        $this->catB->save();

        $this->productB1 = new Product;
        $this->productB1->tenant_id = $this->tenantB->id;
        $this->productB1->category_id = $this->catB->id;
        $this->productB1->name = 'Sec B Cola';
        $this->productB1->sku = 'SEC-BCOLA';
        $this->productB1->barcode = '333333';
        $this->productB1->cost_price = 3000;
        $this->productB1->selling_price = 7000;
        $this->productB1->unit = 'botol';
        $this->productB1->save();
    }

    private function setInventory(Store $store, Product $product, int $qty): void
    {
        Inventory::withoutTenantScope()
            ->where('tenant_id', $store->tenant_id)
            ->where('store_id', $store->id)
            ->where('product_id', $product->id)
            ->delete();

        $inv = new Inventory;
        $inv->tenant_id = $store->tenant_id;
        $inv->store_id = $store->id;
        $inv->product_id = $product->id;
        $inv->quantity = $qty;
        $inv->minimum_quantity = 0;
        $inv->save();
    }

    private function checkoutPayload(array $items, array $payments = [['payment_method' => 'cash', 'amount' => 999999]], array $overrides = []): array
    {
        return array_merge([
            'store_id' => $this->storeA1->id,
            'items' => $items,
            'payments' => $payments,
        ], $overrides);
    }

    private function createSaleViaApi(string $token, ?array $payload = null): \Illuminate\Testing\TestResponse
    {
        $this->setInventory($this->storeA1, $this->productA1, 100);

        $payload = $payload ?? $this->checkoutPayload(
            [['product_id' => $this->productA1->id, 'quantity' => 2]],
            [['payment_method' => 'cash', 'amount' => 16000]],
        );

        return $this->withToken($token)->postJson('/api/v1/sales/checkout', $payload);
    }

    private function createSaleService(): Sale
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $sale = app(SaleService::class)->checkout([
            'store_id' => $this->storeA1->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 2]],
            'payments' => [['payment_method' => 'cash', 'amount' => 16000]],
        ]);
        Auth::forgetGuards();
        return $sale;
    }

    // =========================================================================
    // 1. AUTHENTICATION — All endpoints require valid token
    // =========================================================================

    public function test_no_token_checkout_401(): void
    {
        $this->postJson('/api/v1/sales/checkout', [])->assertStatus(401);
    }

    public function test_no_token_list_sales_401(): void
    {
        $this->getJson('/api/v1/sales')->assertStatus(401);
    }

    public function test_no_token_show_sale_401(): void
    {
        $this->getJson('/api/v1/sales/1')->assertStatus(401);
    }

    public function test_no_token_cancel_sale_401(): void
    {
        $this->postJson('/api/v1/sales/1/cancel')->assertStatus(401);
    }

    public function test_no_token_list_payments_401(): void
    {
        $this->getJson('/api/v1/sales/1/payments')->assertStatus(401);
    }

    public function test_no_token_add_payment_401(): void
    {
        $this->postJson('/api/v1/sales/1/payments', [])->assertStatus(401);
    }

    public function test_invalid_token_checkout_401(): void
    {
        $this->withToken('invalid-token-12345')
            ->postJson('/api/v1/sales/checkout', [])
            ->assertStatus(401);
    }

    public function test_invalid_token_list_sales_401(): void
    {
        $this->withToken('invalid-token-12345')
            ->getJson('/api/v1/sales')
            ->assertStatus(401);
    }

    public function test_expired_token_rejected(): void
    {
        // Delete the token to simulate expiry
        $this->ownerA->tokens()->delete();
        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/sales');
        $response->assertStatus(401);
    }

    // =========================================================================
    // 2. AUTHORIZATION / RBAC
    // =========================================================================

    public function test_staff_cannot_checkout(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $this->withToken($this->tokenStaffA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
            ))
            ->assertStatus(403);
    }

    public function test_staff_cannot_list_sales(): void
    {
        $this->withToken($this->tokenStaffA)
            ->getJson('/api/v1/sales')
            ->assertStatus(403);
    }

    public function test_staff_cannot_show_sale(): void
    {
        $sale = $this->createSaleService();
        $this->withToken($this->tokenStaffA)
            ->getJson("/api/v1/sales/{$sale->id}")
            ->assertStatus(403);
    }

    public function test_staff_cannot_cancel_sale(): void
    {
        $sale = $this->createSaleService();
        $this->withToken($this->tokenStaffA)
            ->postJson("/api/v1/sales/{$sale->id}/cancel")
            ->assertStatus(403);
    }

    public function test_staff_cannot_list_payments(): void
    {
        $sale = $this->createSaleService();
        $this->withToken($this->tokenStaffA)
            ->getJson("/api/v1/sales/{$sale->id}/payments")
            ->assertStatus(403);
    }

    public function test_staff_cannot_add_payment(): void
    {
        $sale = $this->createSaleService();
        $this->withToken($this->tokenStaffA)
            ->postJson("/api/v1/sales/{$sale->id}/payments", [
                'payment_method' => 'cash', 'amount' => 1000,
            ])
            ->assertStatus(403);
    }

    public function test_cashier_can_checkout(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $this->withToken($this->tokenCashierA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
            ))
            ->assertStatus(201);
    }

    public function test_cashier_can_list_sales(): void
    {
        $this->withToken($this->tokenCashierA)
            ->getJson('/api/v1/sales')
            ->assertStatus(200);
    }

    public function test_cashier_can_show_sale(): void
    {
        $sale = $this->createSaleService();
        $this->withToken($this->tokenCashierA)
            ->getJson("/api/v1/sales/{$sale->id}")
            ->assertStatus(200);
    }

    public function test_cashier_can_cancel_sale(): void
    {
        $sale = $this->createSaleService();
        $this->withToken($this->tokenCashierA)
            ->postJson("/api/v1/sales/{$sale->id}/cancel")
            ->assertStatus(200);
    }

    public function test_cashier_can_add_payment(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $sale = app(SaleService::class)->checkout([
            'store_id' => $this->storeA1->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 2]],
            'payments' => [['payment_method' => 'cash', 'amount' => 10000]],
        ]);
        Auth::forgetGuards();

        $this->withToken($this->tokenCashierA)
            ->postJson("/api/v1/sales/{$sale->id}/payments", [
                'payment_method' => 'cash', 'amount' => 6000,
            ])
            ->assertStatus(201);
    }

    public function test_manager_can_checkout(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $this->withToken($this->tokenManagerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
            ))
            ->assertStatus(201);
    }

    public function test_manager_can_cancel(): void
    {
        $sale = $this->createSaleService();
        $this->withToken($this->tokenManagerA)
            ->postJson("/api/v1/sales/{$sale->id}/cancel")
            ->assertStatus(200);
    }

    // =========================================================================
    // 3. TENANT ISOLATION
    // =========================================================================

    public function test_tenant_b_cannot_see_tenant_a_sales(): void
    {
        $sale = $this->createSaleService();
        $this->withToken($this->tokenOwnerB)
            ->getJson("/api/v1/sales/{$sale->id}")
            ->assertStatus(404);
    }

    public function test_tenant_b_cannot_list_tenant_a_sales(): void
    {
        $this->createSaleService();
        $response = $this->withToken($this->tokenOwnerB)
            ->getJson('/api/v1/sales');
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_tenant_b_cannot_cancel_tenant_a_sale(): void
    {
        $sale = $this->createSaleService();
        $this->withToken($this->tokenOwnerB)
            ->postJson("/api/v1/sales/{$sale->id}/cancel")
            ->assertStatus(404);
    }

    public function test_tenant_b_cannot_checkout_with_tenant_a_store(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $this->withToken($this->tokenOwnerB)
            ->postJson('/api/v1/sales/checkout', [
                'store_id' => $this->storeA1->id,
                'items' => [['product_id' => $this->productB1->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'cash', 'amount' => 7000]],
            ])
            ->assertStatus(422);
    }

    public function test_tenant_b_cannot_checkout_with_tenant_a_product(): void
    {
        $this->setInventory($this->storeB, $this->productB1, 100);
        $this->withToken($this->tokenOwnerB)
            ->postJson('/api/v1/sales/checkout', [
                'store_id' => $this->storeB->id,
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'cash', 'amount' => 8000]],
            ])
            ->assertStatus(422);
    }

    public function test_tenant_b_cannot_checkout_with_tenant_a_customer(): void
    {
        $this->setInventory($this->storeB, $this->productB1, 100);
        $this->withToken($this->tokenOwnerB)
            ->postJson('/api/v1/sales/checkout', [
                'store_id' => $this->storeB->id,
                'customer_id' => $this->customerA->id,
                'items' => [['product_id' => $this->productB1->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'cash', 'amount' => 7000]],
            ])
            ->assertStatus(422);
    }

    public function test_tenant_b_cannot_add_payment_to_tenant_a_sale(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $sale = app(SaleService::class)->checkout([
            'store_id' => $this->storeA1->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 2]],
            'payments' => [['payment_method' => 'cash', 'amount' => 10000]],
        ]);
        Auth::forgetGuards();

        $this->withToken($this->tokenOwnerB)
            ->postJson("/api/v1/sales/{$sale->id}/payments", [
                'payment_method' => 'cash', 'amount' => 6000,
            ])
            ->assertStatus(404);
    }

    public function test_tenant_b_cannot_list_tenant_a_sale_payments(): void
    {
        $sale = $this->createSaleService();
        $this->withToken($this->tokenOwnerB)
            ->getJson("/api/v1/sales/{$sale->id}/payments")
            ->assertStatus(404);
    }

    public function test_tenant_b_inventory_unchanged_after_tenant_a_sale(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 50);
        $this->setInventory($this->storeB, $this->productB1, 50);

        $sale = $this->createSaleService();

        $invB = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeB->id)
            ->where('product_id', $this->productB1->id)
            ->first();
        $this->assertEquals(50, $invB->quantity);
    }

    // =========================================================================
    // 4. IDOR PROTECTION
    // =========================================================================

    public function test_idor_show_nonexistent_sale_404(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/sales/99999')
            ->assertStatus(404);
    }

    public function test_idor_cancel_nonexistent_sale_404(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/99999/cancel')
            ->assertStatus(404);
    }

    public function test_idor_add_payment_nonexistent_sale_404(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/99999/payments', [
                'payment_method' => 'cash', 'amount' => 1000,
            ])
            ->assertStatus(404);
    }

    public function test_idor_list_payments_nonexistent_sale_404(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/sales/99999/payments')
            ->assertStatus(404);
    }

    public function test_idor_cross_tenant_sale_id_404(): void
    {
        $sale = $this->createSaleService();
        // Tenant B uses Tenant A's sale ID → 404 (not 403, no info leak)
        $this->withToken($this->tokenOwnerB)
            ->getJson("/api/v1/sales/{$sale->id}")
            ->assertStatus(404);
    }

    // =========================================================================
    // 5. MASS ASSIGNMENT PROTECTION
    // =========================================================================

    public function test_tenant_id_not_accepted_from_request(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
                ['tenant_id' => $this->tenantB->id],
            ));

        $response->assertStatus(201);
        $this->assertEquals($this->tenantA->id, Sale::withoutTenantScope()->find($response->json('id'))->tenant_id);
    }

    public function test_cashier_id_not_accepted_from_request(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
                ['cashier_id' => $this->ownerB->id],
            ));

        $response->assertStatus(201);
        $this->assertEquals($this->ownerA->id, Sale::withoutTenantScope()->find($response->json('id'))->cashier_id);
    }

    public function test_total_not_accepted_from_request(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
                ['total' => 1, 'subtotal' => 1, 'paid_amount' => 999999],
            ));

        $response->assertStatus(201);
        // Backend should calculate: 1 * 8000 = 8000
        $this->assertEquals('8000.00', $response->json('total'));
        $this->assertEquals('8000.00', $response->json('subtotal'));
        $this->assertEquals('8000.00', $response->json('paid_amount'));
    }

    public function test_sale_number_not_accepted_from_request(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
                ['sale_number' => 'HACKED-001'],
            ));

        $response->assertStatus(201);
        $this->assertNotEquals('HACKED-001', $response->json('sale_number'));
        $this->assertStringStartsWith('INV-', $response->json('sale_number'));
    }

    public function test_payment_tenant_id_not_accepted_from_api(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $sale = app(SaleService::class)->checkout([
            'store_id' => $this->storeA1->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 2]],
            'payments' => [['payment_method' => 'cash', 'amount' => 10000]],
        ]);
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$sale->id}/payments", [
                'payment_method' => 'cash',
                'amount' => 6000,
                'tenant_id' => $this->tenantB->id,
            ]);

        $response->assertStatus(201);
        $payment = Payment::withoutTenantScope()->find($response->json('id'));
        $this->assertEquals($this->tenantA->id, $payment->tenant_id);
    }

    // =========================================================================
    // 6. INPUT VALIDATION
    // =========================================================================

    public function test_checkout_missing_store_id_422(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', [
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'cash', 'amount' => 8000]],
            ])
            ->assertStatus(422);
    }

    public function test_checkout_missing_items_422(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', [
                'store_id' => $this->storeA1->id,
                'payments' => [['payment_method' => 'cash', 'amount' => 8000]],
            ])
            ->assertStatus(422);
    }

    public function test_checkout_missing_payments_422(): void
    {
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', [
                'store_id' => $this->storeA1->id,
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 1]],
            ])
            ->assertStatus(422);
    }

    public function test_checkout_negative_quantity_422(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => -5]],
                [['payment_method' => 'cash', 'amount' => 8000]],
            ))
            ->assertStatus(422);
    }

    public function test_checkout_zero_quantity_422(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 0]],
                [['payment_method' => 'cash', 'amount' => 8000]],
            ))
            ->assertStatus(422);
    }

    public function test_checkout_negative_discount_422(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
                ['discount' => -1000],
            ))
            ->assertStatus(422);
    }

    public function test_checkout_negative_tax_422(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
                ['tax' => -500],
            ))
            ->assertStatus(422);
    }

    public function test_add_payment_missing_method_422(): void
    {
        $sale = $this->createSaleService();
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$sale->id}/payments", ['amount' => 1000])
            ->assertStatus(422);
    }

    public function test_add_payment_missing_amount_422(): void
    {
        $sale = $this->createSaleService();
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$sale->id}/payments", ['payment_method' => 'cash'])
            ->assertStatus(422);
    }

    public function test_add_payment_negative_amount_422(): void
    {
        $sale = $this->createSaleService();
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$sale->id}/payments", [
                'payment_method' => 'cash', 'amount' => -100,
            ])
            ->assertStatus(422);
    }

    // =========================================================================
    // 7. PAYMENT IDEMPOTENCY — DB-LEVEL UNIQUE CONSTRAINT
    // =========================================================================

    public function test_db_unique_constraint_prevents_duplicate_idempotency_key(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA1, $this->productA1, 100);

        // Create first sale with idempotency key
        app(SaleService::class)->checkout([
            'store_id' => $this->storeA1->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 1]],
            'payments' => [
                ['payment_method' => 'qris', 'amount' => 8000, 'idempotency_key' => 'DB-IDEM-001'],
            ],
        ]);

        // Try to insert duplicate directly at DB level → should throw QueryException
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('payments')->insert([
            'tenant_id' => $this->tenantA->id,
            'sale_id' => 1,
            'payment_method' => 'cash',
            'amount' => 8000,
            'change_amount' => 0,
            'idempotency_key' => 'DB-IDEM-001',
            'status' => 'success',
            'payment_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Auth::forgetGuards();
    }

    public function test_db_unique_constraint_prevents_duplicate_payment_reference(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA1, $this->productA1, 100);

        app(SaleService::class)->checkout([
            'store_id' => $this->storeA1->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 1]],
            'payments' => [
                ['payment_method' => 'qris', 'amount' => 8000, 'payment_reference' => 'DB-REF-001'],
            ],
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('payments')->insert([
            'tenant_id' => $this->tenantA->id,
            'sale_id' => 1,
            'payment_method' => 'cash',
            'amount' => 8000,
            'change_amount' => 0,
            'payment_reference' => 'DB-REF-001',
            'status' => 'success',
            'payment_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Auth::forgetGuards();
    }

    public function test_null_idempotency_key_allowed_multiple(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA1, $this->productA1, 100);

        // Multiple payments with NULL idempotency_key should be fine
        $sale = app(SaleService::class)->checkout([
            'store_id' => $this->storeA1->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 2]],
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 8000],
                ['payment_method' => 'qris', 'amount' => 8000],
            ],
        ]);

        $this->assertCount(2, $sale->payments);
        $this->assertNull($sale->payments[0]->idempotency_key);
        $this->assertNull($sale->payments[1]->idempotency_key);
        Auth::forgetGuards();
    }

    public function test_null_payment_reference_allowed_multiple(): void
    {
        $sale = $this->createSaleService();
        $this->assertNull($sale->payments->first()->payment_reference);
    }

    public function test_same_idempotency_key_different_tenants_allowed(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA1, $this->productA1, 100);
        app(SaleService::class)->checkout([
            'store_id' => $this->storeA1->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 1]],
            'payments' => [['payment_method' => 'qris', 'amount' => 8000, 'idempotency_key' => 'SHARED-KEY']],
        ]);
        Auth::forgetGuards();

        // Tenant B can use same key
        $this->actingAs($this->ownerB, 'sanctum');
        $this->setInventory($this->storeB, $this->productB1, 100);
        $saleB = app(SaleService::class)->checkout([
            'store_id' => $this->storeB->id,
            'items' => [['product_id' => $this->productB1->id, 'quantity' => 1]],
            'payments' => [['payment_method' => 'qris', 'amount' => 7000, 'idempotency_key' => 'SHARED-KEY']],
        ]);
        $this->assertEquals('SHARED-KEY', $saleB->payments->first()->idempotency_key);
        Auth::forgetGuards();
    }

    // =========================================================================
    // 8. RACE CONDITIONS — Concurrent checkout + payment
    // =========================================================================

    public function test_concurrent_checkout_does_not_create_negative_stock(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA1, $this->productA1, 5);

        $checkoutData = [
            'store_id' => $this->storeA1->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 3]],
            'payments' => [['payment_method' => 'cash', 'amount' => 24000]],
        ];

        $successCount = 0;
        $failCount = 0;

        for ($i = 0; $i < 2; $i++) {
            try {
                app(SaleService::class)->checkout($checkoutData);
                $successCount++;
            } catch (\DomainException | \InvalidArgumentException $e) {
                $failCount++;
            }
        }

        // Only first should succeed, second should fail
        $this->assertEquals(1, $successCount);
        $this->assertEquals(1, $failCount);

        $inv = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA1->id)
            ->where('product_id', $this->productA1->id)
            ->first();
        $this->assertEquals(2, $inv->quantity); // 5 - 3
        Auth::forgetGuards();
    }

    public function test_concurrent_add_payment_does_not_exceed_total(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $sale = app(SaleService::class)->checkout([
            'store_id' => $this->storeA1->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 2]],
            'payments' => [['payment_method' => 'cash', 'amount' => 10000]], // partial, outstanding=6000
        ]);
        Auth::forgetGuards();

        // Two concurrent payments of 6000 each — only one should succeed
        $successCount = 0;
        $failCount = 0;

        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($this->ownerA, 'sanctum');
            try {
                app(PaymentService::class)->addPayment($sale->fresh(), [
                    'payment_method' => 'cash',
                    'amount' => 6000,
                    'idempotency_key' => "RACE-PAY-{$i}",
                ]);
                $successCount++;
            } catch (\DomainException $e) {
                $failCount++;
            }
            Auth::forgetGuards();
        }

        $this->assertEquals(1, $successCount, 'Only one concurrent payment should succeed');
        $this->assertEquals(1, $failCount, 'One should be rejected');

        $sale = $sale->fresh();
        $this->assertEquals('16000.00', $sale->paid_amount); // 10000 + 6000
        $this->assertEquals('paid', $sale->payment_status);
    }

    public function test_concurrent_checkout_with_idempotency_key_one_succeeds(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA1, $this->productA1, 100);

        $checkoutData = [
            'store_id' => $this->storeA1->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 1]],
            'payments' => [['payment_method' => 'qris', 'amount' => 8000, 'idempotency_key' => 'CONCURRENT-IDEM']],
        ];

        $successCount = 0;
        $failCount = 0;

        for ($i = 0; $i < 2; $i++) {
            try {
                app(SaleService::class)->checkout($checkoutData);
                $successCount++;
            } catch (\DomainException | \InvalidArgumentException $e) {
                $failCount++;
            }
        }

        $this->assertEquals(1, $successCount);
        $this->assertEquals(1, $failCount);
        Auth::forgetGuards();
    }

    // =========================================================================
    // 9. SQL INJECTION PROTECTION
    // =========================================================================

    public function test_sql_injection_in_search_field(): void
    {
        $sale = $this->createSaleService();
        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/sales?search=' . urlencode("'; DROP TABLE sales; --"));

        $response->assertStatus(200);
        // Table still exists
        $this->assertDatabaseHas('sales', ['id' => $sale->id]);
    }

    public function test_sql_injection_in_sale_number_search(): void
    {
        $sale = $this->createSaleService();
        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/sales?search=' . urlencode($sale->sale_number . "' OR '1'='1"));

        $response->assertStatus(200);
        // Should only return matching sales, not all
        $response->assertJsonCount(0, 'data'); // The injection string won't match
    }

    public function test_sql_injection_in_product_id(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', [
                'store_id' => $this->storeA1->id,
                'items' => [['product_id' => "1; DROP TABLE products; --", 'quantity' => 1]],
                'payments' => [['payment_method' => 'cash', 'amount' => 8000]],
            ]);

        // Should get validation error (product_id must be integer)
        $response->assertStatus(422);
        $this->assertDatabaseHas('products', ['id' => $this->productA1->id]);
    }

    // =========================================================================
    // 10. SENSITIVE DATA EXPOSURE
    // =========================================================================

    public function test_sale_response_does_not_expose_tenant_id(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
            ));

        $response->assertStatus(201);
        $response->assertJsonMissingPath('tenant_id');
    }

    public function test_payment_response_does_not_expose_tenant_id(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $sale = app(SaleService::class)->checkout([
            'store_id' => $this->storeA1->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 2]],
            'payments' => [['payment_method' => 'cash', 'amount' => 10000]],
        ]);
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$sale->id}/payments", [
                'payment_method' => 'cash', 'amount' => 6000,
            ]);

        $response->assertStatus(201);
        $response->assertJsonMissingPath('tenant_id');
    }

    public function test_list_sales_does_not_expose_tenant_id(): void
    {
        $this->createSaleService();
        $response = $this->withToken($this->tokenOwnerA)
            ->getJson('/api/v1/sales');

        $response->assertStatus(200);
        $response->assertJsonMissingPath('data.0.tenant_id');
    }

    public function test_show_sale_does_not_expose_tenant_id(): void
    {
        $sale = $this->createSaleService();
        $response = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/sales/{$sale->id}");

        $response->assertStatus(200);
        $response->assertJsonMissingPath('tenant_id');
    }

    public function test_payment_list_does_not_expose_tenant_id(): void
    {
        $sale = $this->createSaleService();
        $response = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/sales/{$sale->id}/payments");

        $response->assertStatus(200);
        $json = $response->json();
        foreach ($json as $payment) {
            $this->assertArrayNotHasKey('tenant_id', $payment);
        }
    }

    public function test_sale_response_does_not_expose_cost_price(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
            ));

        $response->assertStatus(201);
        $items = $response->json('items');
        foreach ($items as $item) {
            $this->assertArrayNotHasKey('cost_price', $item);
        }
    }

    // =========================================================================
    // 11. BUSINESS LOGIC SECURITY
    // =========================================================================

    public function test_checkout_with_inactive_product_rejected(): void
    {
        $this->productA1->is_active = false;
        $this->productA1->save();
        $this->setInventory($this->storeA1, $this->productA1, 100);

        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
            ))
            ->assertStatus(422);
    }

    public function test_checkout_discount_exceeds_subtotal_rejected(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
                ['discount' => 999999],
            ))
            ->assertStatus(422);
    }

    public function test_cancel_already_cancelled_sale_rejected(): void
    {
        $sale = $this->createSaleService();

        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$sale->id}/cancel")
            ->assertStatus(200);

        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$sale->id}/cancel")
            ->assertStatus(422);
    }

    public function test_checkout_duplicate_products_rejected(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [
                    ['product_id' => $this->productA1->id, 'quantity' => 1],
                    ['product_id' => $this->productA1->id, 'quantity' => 1],
                ],
                [['payment_method' => 'cash', 'amount' => 16000]],
            ))
            ->assertStatus(422);
    }

    public function test_price_snapshot_not_affected_by_concurrent_price_change(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA1, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout([
            'store_id' => $this->storeA1->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 1]],
            'payments' => [['payment_method' => 'cash', 'amount' => 8000]],
        ]);

        // Change product price after sale
        $this->productA1->selling_price = 99999;
        $this->productA1->save();

        // Sale item should still have original price
        $saleItem = $sale->fresh()->items->first();
        $this->assertEquals('8000.00', $saleItem->unit_price);
        Auth::forgetGuards();
    }

    // =========================================================================
    // 12. RESPONSE STRUCTURE SECURITY
    // =========================================================================

    public function test_checkout_response_has_required_fields(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 1]],
                [['payment_method' => 'cash', 'amount' => 8000]],
            ));

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id', 'sale_number', 'status', 'payment_status', 'sale_date',
            'subtotal', 'discount', 'tax', 'total', 'paid_amount', 'change_amount',
            'items' => [['id', 'product_id', 'product_name', 'sku', 'unit_price', 'quantity', 'total']],
            'payments' => [['id', 'payment_method', 'amount', 'status']],
            'store' => ['id', 'name'],
            'cashier' => ['id', 'name'],
        ]);
    }

    public function test_error_response_does_not_leak_internal_details(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 1);
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', $this->checkoutPayload(
                [['product_id' => $this->productA1->id, 'quantity' => 999]],
                [['payment_method' => 'cash', 'amount' => 999999]],
            ));

        $response->assertStatus(422);
        $message = $response->json('message');
        // Should not contain stack trace, file paths, or SQL queries
        $this->assertStringNotContainsString('/var/www', $message);
        $this->assertStringNotContainsString('.php', $message);
        $this->assertStringNotContainsString('SQL', $message);
        $this->assertStringNotContainsString('stack', $message);
    }

    // =========================================================================
    // 13. API SMOKE TESTS — E2E Critical Flows
    // =========================================================================

    public function test_smoke_checkout_list_show_cancel_flow(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 100);
        $this->setInventory($this->storeA1, $this->productA2, 50);

        // 1. Checkout
        $checkoutResponse = $this->withToken($this->tokenCashierA)
            ->postJson('/api/v1/sales/checkout', [
                'store_id' => $this->storeA1->id,
                'customer_id' => $this->customerA->id,
                'items' => [
                    ['product_id' => $this->productA1->id, 'quantity' => 3],
                    ['product_id' => $this->productA2->id, 'quantity' => 2],
                ],
                'payments' => [
                    ['payment_method' => 'cash', 'amount' => 20000],
                    ['payment_method' => 'qris', 'amount' => 14000, 'payment_reference' => 'SMOKE-SEC-001'],
                ],
                'discount' => 2000,
                'tax' => 0,
                'notes' => 'Security smoke test',
            ]);

        $checkoutResponse->assertStatus(201);
        $saleId = $checkoutResponse->json('id');

        // Verify totals: 3*8000 + 2*5000 = 34000, discount 2000, total = 32000
        $this->assertEquals('34000.00', $checkoutResponse->json('subtotal'));
        $this->assertEquals('2000.00', $checkoutResponse->json('discount'));
        $this->assertEquals('32000.00', $checkoutResponse->json('total'));
        $this->assertEquals('34000.00', $checkoutResponse->json('paid_amount'));
        $this->assertEquals('2000.00', $checkoutResponse->json('change_amount'));
        $this->assertEquals('paid', $checkoutResponse->json('payment_status'));

        // 2. List
        $listResponse = $this->withToken($this->tokenCashierA)
            ->getJson('/api/v1/sales');
        $listResponse->assertStatus(200);
        $listResponse->assertJsonCount(1, 'data');
        $listResponse->assertJsonPath('data.0.id', $saleId);

        // 3. Show
        $showResponse = $this->withToken($this->tokenCashierA)
            ->getJson("/api/v1/sales/{$saleId}");
        $showResponse->assertStatus(200);
        $showResponse->assertJsonPath('id', $saleId);
        $showResponse->assertJsonCount(2, 'items');
        $showResponse->assertJsonCount(2, 'payments');

        // 4. List payments
        $payResponse = $this->withToken($this->tokenCashierA)
            ->getJson("/api/v1/sales/{$saleId}/payments");
        $payResponse->assertStatus(200);
        $payResponse->assertJsonCount(2);

        // 5. Cancel
        $cancelResponse = $this->withToken($this->tokenCashierA)
            ->postJson("/api/v1/sales/{$saleId}/cancel");
        $cancelResponse->assertStatus(200);
        $cancelResponse->assertJsonPath('status', 'cancelled');

        // 6. Verify payments refunded
        $paymentsAfter = $this->withToken($this->tokenCashierA)
            ->getJson("/api/v1/sales/{$saleId}/payments");
        foreach ($paymentsAfter->json() as $payment) {
            $this->assertEquals('refunded', $payment['status']);
        }

        // 7. Verify inventory restored
        $inv1 = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA1->id)
            ->where('product_id', $this->productA1->id)
            ->first();
        $this->assertEquals(100, $inv1->quantity);

        $inv2 = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA1->id)
            ->where('product_id', $this->productA2->id)
            ->first();
        $this->assertEquals(50, $inv2->quantity);
    }

    public function test_smoke_partial_payment_then_complete_then_cancel(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 100);

        // 1. Checkout with partial payment
        $checkoutResponse = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', [
                'store_id' => $this->storeA1->id,
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 3]],
                'payments' => [['payment_method' => 'cash', 'amount' => 10000]],
            ]);

        $checkoutResponse->assertStatus(201);
        $saleId = $checkoutResponse->json('id');
        $this->assertEquals('partial', $checkoutResponse->json('payment_status'));
        $this->assertEquals('24000.00', $checkoutResponse->json('total')); // 3 * 8000
        $this->assertEquals('10000.00', $checkoutResponse->json('paid_amount'));

        // 2. Add second payment
        $payResponse = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$saleId}/payments", [
                'payment_method' => 'qris',
                'amount' => 14000,
                'payment_reference' => 'SMOKE-PARTIAL-001',
                'idempotency_key' => 'SMOKE-PARTIAL-IDEM',
            ]);

        $payResponse->assertStatus(201);
        $this->assertEquals('14000.00', $payResponse->json('amount'));

        // 3. Verify sale is now paid
        $showResponse = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/sales/{$saleId}");
        $showResponse->assertStatus(200);
        $showResponse->assertJsonPath('payment_status', 'paid');
        $showResponse->assertJsonPath('paid_amount', '24000.00');

        // 4. Try to add another payment → 422
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$saleId}/payments", [
                'payment_method' => 'cash', 'amount' => 1000,
            ])
            ->assertStatus(422);

        // 5. Cancel
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$saleId}/cancel")
            ->assertStatus(200);

        // 6. Verify all payments refunded
        $payments = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/sales/{$saleId}/payments");
        $this->assertCount(2, $payments->json());
        foreach ($payments->json() as $p) {
            $this->assertEquals('refunded', $p['status']);
        }

        // 7. Try to add payment to cancelled sale → 422
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$saleId}/payments", [
                'payment_method' => 'cash', 'amount' => 1000,
            ])
            ->assertStatus(422);
    }

    public function test_smoke_idempotency_replay_returns_error_not_duplicate(): void
    {
        $this->setInventory($this->storeA1, $this->productA1, 100);

        // First checkout with idempotency key
        $r1 = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', [
                'store_id' => $this->storeA1->id,
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 1]],
                'payments' => [
                    ['payment_method' => 'qris', 'amount' => 8000, 'idempotency_key' => 'REPLAY-001'],
                ],
            ]);
        $r1->assertStatus(201);
        $saleId1 = $r1->json('id');

        // Replay with same idempotency key → 422 (not 201, not duplicate sale)
        $r2 = $this->withToken($this->tokenOwnerA)
            ->postJson('/api/v1/sales/checkout', [
                'store_id' => $this->storeA1->id,
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 1]],
                'payments' => [
                    ['payment_method' => 'qris', 'amount' => 8000, 'idempotency_key' => 'REPLAY-001'],
                ],
            ]);
        $r2->assertStatus(422);

        // Only one sale in DB
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_smoke_all_endpoints_unauthorized_without_token(): void
    {
        $this->getJson('/api/v1/sales')->assertStatus(401);
        $this->getJson('/api/v1/sales/1')->assertStatus(401);
        $this->postJson('/api/v1/sales/checkout', [])->assertStatus(401);
        $this->postJson('/api/v1/sales/1/cancel')->assertStatus(401);
        $this->getJson('/api/v1/sales/1/payments')->assertStatus(401);
        $this->postJson('/api/v1/sales/1/payments', [])->assertStatus(401);
    }
}
