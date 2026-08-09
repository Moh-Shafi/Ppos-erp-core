<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SaleSmokeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $cashier;
    private string $token;
    private Store $store;
    private Customer $customer;
    private Product $product1;
    private Product $product2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $cashierRole = Role::where('slug', 'cashier')->first();

        $this->tenant = Tenant::create(['name' => 'Smoke Test Toko', 'slug' => 'smoke-toko']);
        $this->cashier = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $cashierRole->id,
            'name' => 'Smoke Cashier',
            'email' => 'smoke.cashier@test.com',
            'password' => 'password',
        ]);

        $this->store = new Store;
        $this->store->tenant_id = $this->tenant->id;
        $this->store->name = 'Smoke Store';
        $this->store->code = 'SS';
        $this->store->is_active = true;
        $this->store->save();

        $this->customer = new Customer;
        $this->customer->tenant_id = $this->tenant->id;
        $this->customer->name = 'Smoke Customer';
        $this->customer->is_active = true;
        $this->customer->save();

        $cat = new Category;
        $cat->tenant_id = $this->tenant->id;
        $cat->name = 'Smoke Drinks';
        $cat->slug = 'smoke-drinks';
        $cat->save();

        $this->product1 = new Product;
        $this->product1->tenant_id = $this->tenant->id;
        $this->product1->category_id = $cat->id;
        $this->product1->name = 'Smoke Cola';
        $this->product1->sku = 'SMOKE-COLA';
        $this->product1->barcode = '111111';
        $this->product1->cost_price = 4000;
        $this->product1->selling_price = 7000;
        $this->product1->unit = 'botol';
        $this->product1->save();

        $this->product2 = new Product;
        $this->product2->tenant_id = $this->tenant->id;
        $this->product2->category_id = $cat->id;
        $this->product2->name = 'Smoke Tea';
        $this->product2->sku = 'SMOKE-TEA';
        $this->product2->barcode = '222222';
        $this->product2->cost_price = 2000;
        $this->product2->selling_price = 4000;
        $this->product2->unit = 'gelas';
        $this->product2->save();

        // Set inventory
        $this->setInventory($this->store, $this->product1, 50);
        $this->setInventory($this->store, $this->product2, 30);
    }

    private function setInventory(Store $store, Product $product, int $qty): void
    {
        $inv = new Inventory;
        $inv->tenant_id = $store->tenant_id;
        $inv->store_id = $store->id;
        $inv->product_id = $product->id;
        $inv->quantity = $qty;
        $inv->minimum_quantity = 0;
        $inv->save();
    }

    private function getInventory(Product $product): ?Inventory
    {
        return Inventory::withoutTenantScope()
            ->where('tenant_id', $this->tenant->id)
            ->where('store_id', $this->store->id)
            ->where('product_id', $product->id)
            ->first();
    }

    /**
     * SMOKE TEST: Full E2E flow via API endpoints.
     *
     * Login → Checkout → List → Show → Cancel → Verify DB
     */
    public function test_smoke_full_pos_flow(): void
    {
        // ================================================================
        // STEP 1: LOGIN — get Sanctum token
        // ================================================================
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'smoke.cashier@test.com',
            'password' => 'password',
        ]);

        $loginResponse->assertStatus(200);
        $loginResponse->assertJsonStructure(['token', 'user']);
        $token = $loginResponse->json('token');
        $this->assertNotEmpty($token);

        // ================================================================
        // STEP 2: CHECKOUT — POST /api/v1/sales/checkout
        // ================================================================
        $checkoutPayload = [
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'items' => [
                ['product_id' => $this->product1->id, 'quantity' => 3],
                ['product_id' => $this->product2->id, 'quantity' => 2],
            ],
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 20000],
                ['payment_method' => 'qris', 'amount' => 9000],
            ],
            'discount' => 1000,
            'tax' => 0,
            'notes' => 'Smoke test sale',
        ];

        $checkoutResponse = $this->withToken($token)
            ->postJson('/api/v1/sales/checkout', $checkoutPayload);

        $checkoutResponse->assertStatus(201);

        // Verify response structure
        $checkoutResponse->assertJsonStructure([
            'id', 'sale_number', 'status', 'payment_status', 'sale_date',
            'subtotal', 'discount', 'tax', 'total', 'paid_amount', 'change_amount',
            'notes', 'store', 'cashier', 'customer', 'items', 'payments',
        ]);

        $saleId = $checkoutResponse->json('id');
        $saleNumber = $checkoutResponse->json('sale_number');

        // Verify business logic
        $this->assertEquals('completed', $checkoutResponse->json('status'));
        $this->assertEquals('paid', $checkoutResponse->json('payment_status'));
        $this->assertEquals('29000.00', $checkoutResponse->json('subtotal')); // 3*7000 + 2*4000 = 21000+8000
        $this->assertEquals('1000.00', $checkoutResponse->json('discount'));
        $this->assertEquals('28000.00', $checkoutResponse->json('total')); // 29000 - 1000 + 0
        $this->assertEquals('29000.00', $checkoutResponse->json('paid_amount')); // 20000 + 9000
        $this->assertEquals('1000.00', $checkoutResponse->json('change_amount')); // 29000 - 28000
        $this->assertStringStartsWith('INV-', $saleNumber);

        // Verify items with snapshot
        $items = $checkoutResponse->json('items');
        $this->assertCount(2, $items);

        $item1 = collect($items)->firstWhere('product_id', $this->product1->id);
        $this->assertEquals('Smoke Cola', $item1['product_name']);
        $this->assertEquals('SMOKE-COLA', $item1['sku']);
        $this->assertEquals('7000.00', $item1['unit_price']);
        $this->assertEquals(3, $item1['quantity']);
        $this->assertEquals('21000.00', $item1['total']);

        $item2 = collect($items)->firstWhere('product_id', $this->product2->id);
        $this->assertEquals('Smoke Tea', $item2['product_name']);
        $this->assertEquals('4000.00', $item2['unit_price']);
        $this->assertEquals(2, $item2['quantity']);
        $this->assertEquals('8000.00', $item2['total']);

        // Verify payments
        $payments = $checkoutResponse->json('payments');
        $this->assertCount(2, $payments);
        $cashPay = collect($payments)->firstWhere('payment_method', 'cash');
        $this->assertEquals('20000.00', $cashPay['amount']);
        $this->assertEquals('success', $cashPay['status']);
        $qrisPay = collect($payments)->firstWhere('payment_method', 'qris');
        $this->assertEquals('9000.00', $qrisPay['amount']);

        // Verify cashier attribution
        $this->assertEquals($this->cashier->id, $checkoutResponse->json('cashier_id'));

        // ================================================================
        // STEP 2b: VERIFY DB STATE AFTER CHECKOUT
        // ================================================================
        // Sale in DB
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'tenant_id' => $this->tenant->id,
            'sale_number' => $saleNumber,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => '28000.00',
        ]);

        // SaleItems in DB
        $this->assertDatabaseCount('sale_items', 2);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $saleId,
            'product_id' => $this->product1->id,
            'product_name' => 'Smoke Cola',
            'sku' => 'SMOKE-COLA',
            'unit_price' => '7000.00',
            'quantity' => 3,
        ]);

        // Payments in DB
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseHas('payments', [
            'sale_id' => $saleId,
            'tenant_id' => $this->tenant->id,
            'payment_method' => 'cash',
            'amount' => '20000.00',
            'status' => 'success',
        ]);

        // Inventory deducted
        $this->assertEquals(47, $this->getInventory($this->product1)->quantity); // 50 - 3
        $this->assertEquals(28, $this->getInventory($this->product2)->quantity); // 30 - 2

        // Movements created
        $movements = InventoryMovement::withoutTenantScope()
            ->where('tenant_id', $this->tenant->id)
            ->where('store_id', $this->store->id)
            ->get();
        $this->assertCount(2, $movements);
        $saleMovements = $movements->where('type', 'sale');
        $this->assertCount(2, $saleMovements);

        $m1 = $movements->where('product_id', $this->product1->id)->first();
        $this->assertEquals(-3, $m1->quantity);
        $this->assertEquals(50, $m1->before_quantity);
        $this->assertEquals(47, $m1->after_quantity);
        $this->assertEquals(Sale::class, $m1->reference_type);
        $this->assertEquals($saleId, $m1->reference_id);
        $this->assertEquals($this->cashier->id, $m1->user_id);

        // ================================================================
        // STEP 3: LIST — GET /api/v1/sales
        // ================================================================
        $listResponse = $this->withToken($token)
            ->getJson('/api/v1/sales');

        $listResponse->assertStatus(200);
        $listResponse->assertJsonCount(1, 'data');
        $listResponse->assertJsonPath('data.0.id', $saleId);
        $listResponse->assertJsonPath('data.0.sale_number', $saleNumber);
        $listResponse->assertJsonPath('data.0.status', 'completed');

        // ================================================================
        // STEP 3b: LIST with filter — GET /api/v1/sales?status=completed
        // ================================================================
        $filterResponse = $this->withToken($token)
            ->getJson('/api/v1/sales?status=completed');

        $filterResponse->assertStatus(200);
        $filterResponse->assertJsonCount(1, 'data');

        $filterNone = $this->withToken($token)
            ->getJson('/api/v1/sales?status=cancelled');

        $filterNone->assertStatus(200);
        $filterNone->assertJsonCount(0, 'data');

        // ================================================================
        // STEP 4: SHOW — GET /api/v1/sales/{id}
        // ================================================================
        $showResponse = $this->withToken($token)
            ->getJson("/api/v1/sales/{$saleId}");

        $showResponse->assertStatus(200);
        $showResponse->assertJsonPath('id', $saleId);
        $showResponse->assertJsonPath('sale_number', $saleNumber);
        $showResponse->assertJsonPath('status', 'completed');
        $showResponse->assertJsonPath('payment_status', 'paid');
        $showResponse->assertJsonPath('subtotal', '29000.00');
        $showResponse->assertJsonPath('total', '28000.00');
        $showResponse->assertJsonPath('change_amount', '1000.00');

        // Verify relations loaded
        $showResponse->assertJsonStructure([
            'store' => ['id', 'name'],
            'cashier' => ['id', 'name'],
            'customer' => ['id', 'name'],
            'items' => [['id', 'product_name', 'sku', 'unit_price', 'quantity', 'total']],
            'payments' => [['id', 'payment_method', 'amount', 'status']],
        ]);

        // ================================================================
        // STEP 5: CANCEL — POST /api/v1/sales/{id}/cancel
        // ================================================================
        $cancelResponse = $this->withToken($token)
            ->postJson("/api/v1/sales/{$saleId}/cancel");

        $cancelResponse->assertStatus(200);
        $cancelResponse->assertJsonPath('id', $saleId);
        $cancelResponse->assertJsonPath('status', 'cancelled');

        // ================================================================
        // STEP 5b: VERIFY DB STATE AFTER CANCEL
        // ================================================================
        // Sale status changed
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'status' => 'cancelled',
        ]);

        // Inventory restored
        $this->assertEquals(50, $this->getInventory($this->product1)->quantity); // 47 + 3
        $this->assertEquals(30, $this->getInventory($this->product2)->quantity); // 28 + 2

        // sale_return movements created
        $movementsAfter = InventoryMovement::withoutTenantScope()
            ->where('tenant_id', $this->tenant->id)
            ->where('store_id', $this->store->id)
            ->get();
        $this->assertCount(4, $movementsAfter); // 2 sale + 2 sale_return

        $returnMovements = $movementsAfter->where('type', 'sale_return');
        $this->assertCount(2, $returnMovements);

        $rm1 = $returnMovements->where('product_id', $this->product1->id)->first();
        $this->assertEquals(3, $rm1->quantity); // positive = restored
        $this->assertEquals(47, $rm1->before_quantity);
        $this->assertEquals(50, $rm1->after_quantity);
        $this->assertEquals(Sale::class, $rm1->reference_type);
        $this->assertEquals($saleId, $rm1->reference_id);

        // ================================================================
        // STEP 6: VERIFY CANCELLED SALE APPEARS IN LIST
        // ================================================================
        $listAfterCancel = $this->withToken($token)
            ->getJson('/api/v1/sales?status=cancelled');

        $listAfterCancel->assertStatus(200);
        $listAfterCancel->assertJsonCount(1, 'data');
        $listAfterCancel->assertJsonPath('data.0.id', $saleId);
        $listAfterCancel->assertJsonPath('data.0.status', 'cancelled');

        // Completed list should now be empty
        $completedList = $this->withToken($token)
            ->getJson('/api/v1/sales?status=completed');

        $completedList->assertStatus(200);
        $completedList->assertJsonCount(0, 'data');

        // ================================================================
        // STEP 7: DOUBLE CANCEL — should return 422
        // ================================================================
        $doubleCancel = $this->withToken($token)
            ->postJson("/api/v1/sales/{$saleId}/cancel");

        $doubleCancel->assertStatus(422);
        $doubleCancel->assertJsonPath('message', 'Only completed sales can be cancelled');
    }

    /**
     * SMOKE TEST: Login failure → no token → all endpoints 401.
     */
    public function test_smoke_unauthenticated_access_blocked(): void
    {
        // No login, no token
        $this->getJson('/api/v1/sales')->assertStatus(401);
        $this->getJson('/api/v1/sales/1')->assertStatus(401);
        $this->postJson('/api/v1/sales/checkout', [])->assertStatus(401);
        $this->postJson('/api/v1/sales/1/cancel')->assertStatus(401);
    }

    /**
     * SMOKE TEST: Login with wrong password → 401.
     */
    public function test_smoke_login_wrong_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'smoke.cashier@test.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    /**
     * SMOKE TEST: Checkout → stock check → 2nd checkout fails on same stock.
     */
    public function test_smoke_sequential_checkouts_stock_depletion(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'smoke.cashier@test.com',
            'password' => 'password',
        ]);
        $token = $loginResponse->json('token');

        // 1st checkout: take 30 of 50
        $r1 = $this->withToken($token)
            ->postJson('/api/v1/sales/checkout', [
                'store_id' => $this->store->id,
                'items' => [['product_id' => $this->product1->id, 'quantity' => 30]],
                'payments' => [['payment_method' => 'cash', 'amount' => 210000]],
            ]);
        $r1->assertStatus(201);

        // Verify inventory: 50 - 30 = 20
        $this->assertEquals(20, $this->getInventory($this->product1)->quantity);

        // 2nd checkout: try 25 — only 20 left → 422
        $r2 = $this->withToken($token)
            ->postJson('/api/v1/sales/checkout', [
                'store_id' => $this->store->id,
                'items' => [['product_id' => $this->product1->id, 'quantity' => 25]],
                'payments' => [['payment_method' => 'cash', 'amount' => 175000]],
            ]);
        $r2->assertStatus(422);
        $r2->assertJsonPath('message', 'Insufficient stock for Smoke Cola. Available: 20, Requested: 25');

        // Verify inventory unchanged after failed checkout
        $this->assertEquals(20, $this->getInventory($this->product1)->quantity);

        // 3rd checkout: take exactly 20 — should succeed
        $r3 = $this->withToken($token)
            ->postJson('/api/v1/sales/checkout', [
                'store_id' => $this->store->id,
                'items' => [['product_id' => $this->product1->id, 'quantity' => 20]],
                'payments' => [['payment_method' => 'cash', 'amount' => 140000]],
            ]);
        $r3->assertStatus(201);

        // Verify inventory: 20 - 20 = 0
        $this->assertEquals(0, $this->getInventory($this->product1)->quantity);

        // 4th checkout: try 1 — 0 left → 422
        $r4 = $this->withToken($token)
            ->postJson('/api/v1/sales/checkout', [
                'store_id' => $this->store->id,
                'items' => [['product_id' => $this->product1->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'cash', 'amount' => 7000]],
            ]);
        $r4->assertStatus(422);
    }
}
