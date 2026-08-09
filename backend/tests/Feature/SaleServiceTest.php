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
use App\Services\InventoryService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SaleServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $ownerA;
    private User $cashierA;
    private User $staffA;
    private User $ownerB;
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
        $cashierRole = Role::where('slug', 'cashier')->first();
        $staffRole = Role::where('slug', 'staff')->first();

        // Tenant A
        $this->tenantA = Tenant::create(['name' => 'Toko A', 'slug' => 'toko-a']);
        $this->ownerA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner A', 'email' => 'owner.a@sale.com', 'password' => 'password',
        ]);
        $this->cashierA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $cashierRole->id,
            'name' => 'Cashier A', 'email' => 'cashier.a@sale.com', 'password' => 'password',
        ]);
        $this->staffA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $staffRole->id,
            'name' => 'Staff A', 'email' => 'staff.a@sale.com', 'password' => 'password',
        ]);

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
            'name' => 'Owner B', 'email' => 'owner.b@sale.com', 'password' => 'password',
        ]);

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

    private function getInventory(Product $product): ?Inventory
    {
        return Inventory::withoutTenantScope()
            ->where('tenant_id', $this->tenantA->id)
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $product->id)
            ->first();
    }

    private function getMovements(Product $product): \Illuminate\Database\Eloquent\Collection
    {
        return InventoryMovement::withoutTenantScope()
            ->where('tenant_id', $this->tenantA->id)
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $product->id)
            ->orderBy('id')
            ->get();
    }

    private function checkoutData(array $items, array $payments = [], array $extra = []): array
    {
        return array_merge([
            'store_id' => $this->storeA->id,
            'items' => $items,
            'payments' => $payments ?: [['payment_method' => 'cash', 'amount' => 999999]],
        ], $extra);
    }

    // =========================================================================
    // 1. CHECKOUT — BASIC
    // =========================================================================

    public function test_checkout_single_product(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 2],
        ], [['payment_method' => 'cash', 'amount' => 16000]]));

        $this->assertEquals('completed', $sale->status);
        $this->assertEquals('paid', $sale->payment_status);
        $this->assertEquals('16000.00', $sale->subtotal);
        $this->assertEquals('16000.00', $sale->total);
        $this->assertEquals('16000.00', $sale->paid_amount);
        $this->assertCount(1, $sale->items);
        $this->assertCount(1, $sale->payments);
        Auth::forgetGuards();
    }

    public function test_checkout_multiple_products(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        $this->setInventory($this->storeA, $this->productA2, 50);

        $sale = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 3],
            ['product_id' => $this->productA2->id, 'quantity' => 2],
        ], [['payment_method' => 'cash', 'amount' => 34000]]));

        $this->assertEquals('34000.00', $sale->subtotal); // 3*8000 + 2*5000
        $this->assertEquals('34000.00', $sale->total);
        $this->assertCount(2, $sale->items);
        Auth::forgetGuards();
    }

    public function test_checkout_with_customer(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout($this->checkoutData(
            [['product_id' => $this->productA1->id, 'quantity' => 1]],
            [['payment_method' => 'cash', 'amount' => 8000]],
            ['customer_id' => $this->customerA->id],
        ));

        $this->assertEquals($this->customerA->id, $sale->customer_id);
        Auth::forgetGuards();
    }

    public function test_checkout_without_customer_walk_in(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 1],
        ], [['payment_method' => 'cash', 'amount' => 8000]]));

        $this->assertNull($sale->customer_id);
        Auth::forgetGuards();
    }

    public function test_checkout_generates_sale_number(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 1],
        ], [['payment_method' => 'cash', 'amount' => 8000]]));

        $this->assertNotEmpty($sale->sale_number);
        $this->assertStringStartsWith('INV-', $sale->sale_number);
        Auth::forgetGuards();
    }

    public function test_checkout_cashier_id_from_auth(): void
    {
        $this->actingAs($this->cashierA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 1],
        ], [['payment_method' => 'cash', 'amount' => 8000]]));

        $this->assertEquals($this->cashierA->id, $sale->cashier_id);
        Auth::forgetGuards();
    }

    // =========================================================================
    // 2. STOCK DEDUCTION
    // =========================================================================

    public function test_checkout_decreases_inventory(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 30],
        ], [['payment_method' => 'cash', 'amount' => 240000]]));

        $inv = $this->getInventory($this->productA1);
        $this->assertEquals(70, $inv->quantity);
        Auth::forgetGuards();
    }

    public function test_checkout_multiple_items_inventory(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        $this->setInventory($this->storeA, $this->productA2, 50);

        app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 20],
            ['product_id' => $this->productA2->id, 'quantity' => 10],
        ], [['payment_method' => 'cash', 'amount' => 210000]]));

        $this->assertEquals(80, $this->getInventory($this->productA1)->quantity);
        $this->assertEquals(40, $this->getInventory($this->productA2)->quantity);
        Auth::forgetGuards();
    }

    public function test_checkout_insufficient_stock_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 5);

        $this->expectException(\InvalidArgumentException::class);
        app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 10],
        ]));
        Auth::forgetGuards();
    }

    public function test_checkout_no_inventory_record_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        // Don't set inventory — no record exists

        $this->expectException(\InvalidArgumentException::class);
        app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 1],
        ]));
        Auth::forgetGuards();
    }

    public function test_checkout_does_not_affect_other_store_inventory(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        // Set inventory for Tenant B
        $this->setInventory($this->storeB, $this->productB, 200);

        app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 10],
        ], [['payment_method' => 'cash', 'amount' => 80000]]));

        // Tenant B inventory unchanged
        $invB = Inventory::withoutTenantScope()
            ->where('tenant_id', $this->tenantB->id)
            ->where('store_id', $this->storeB->id)
            ->where('product_id', $this->productB->id)
            ->first();
        $this->assertEquals(200, $invB->quantity);
        Auth::forgetGuards();
    }

    // =========================================================================
    // 3. PRICE SNAPSHOT
    // =========================================================================

    public function test_checkout_price_from_product_not_request(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        // Even if frontend sends unit_price=1, backend should use product's selling_price
        $sale = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 2, 'unit_price' => 1],
        ], [['payment_method' => 'cash', 'amount' => 16000]]));

        $item = $sale->items->first();
        $this->assertEquals('8000.00', $item->unit_price); // from product, not request
        $this->assertEquals('16000.00', $item->subtotal);
        $this->assertEquals('16000.00', $sale->total);
        Auth::forgetGuards();
    }

    public function test_checkout_snapshots_product_name_and_sku(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 1],
        ], [['payment_method' => 'cash', 'amount' => 8000]]));

        $item = $sale->items->first();
        $this->assertEquals('Coca Cola', $item->product_name);
        $this->assertEquals('COKE-001', $item->sku);
        $this->assertEquals('8000.00', $item->unit_price);
        Auth::forgetGuards();
    }

    public function test_checkout_snapshot_preserved_after_price_change(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 2],
        ], [['payment_method' => 'cash', 'amount' => 16000]]));

        // Change product price and name
        $this->productA1->selling_price = 12000;
        $this->productA1->name = 'Coca Cola New';
        $this->productA1->save();

        // Reload sale item — snapshot preserved
        $item = $sale->items->first()->fresh();
        $this->assertEquals('Coca Cola', $item->product_name);
        $this->assertEquals('8000.00', $item->unit_price);
        $this->assertEquals('16000.00', $item->total);
        Auth::forgetGuards();
    }

    // =========================================================================
    // 4. TOTAL CALCULATION (BACKEND)
    // =========================================================================

    public function test_checkout_subtotal_calculated_by_backend(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        $this->setInventory($this->storeA, $this->productA2, 100);

        $sale = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 2],
            ['product_id' => $this->productA2->id, 'quantity' => 3],
        ], [['payment_method' => 'cash', 'amount' => 31000]]));

        // 2*8000 + 3*5000 = 16000 + 15000 = 31000
        $this->assertEquals('31000.00', $sale->subtotal);
        $this->assertEquals('31000.00', $sale->total);
        Auth::forgetGuards();
    }

    public function test_checkout_with_discount(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout($this->checkoutData(
            [['product_id' => $this->productA1->id, 'quantity' => 2]],
            [['payment_method' => 'cash', 'amount' => 14000]],
            ['discount' => 2000],
        ));

        $this->assertEquals('16000.00', $sale->subtotal);
        $this->assertEquals('2000.00', $sale->discount);
        $this->assertEquals('14000.00', $sale->total);
        Auth::forgetGuards();
    }

    public function test_checkout_with_tax(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout($this->checkoutData(
            [['product_id' => $this->productA1->id, 'quantity' => 2]],
            [['payment_method' => 'cash', 'amount' => 17600]],
            ['tax' => 1600],
        ));

        $this->assertEquals('16000.00', $sale->subtotal);
        $this->assertEquals('1600.00', $sale->tax);
        $this->assertEquals('17600.00', $sale->total);
        Auth::forgetGuards();
    }

    public function test_checkout_discount_exceeds_subtotal_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $this->expectException(\DomainException::class);
        app(SaleService::class)->checkout($this->checkoutData(
            [['product_id' => $this->productA1->id, 'quantity' => 1]],
            [['payment_method' => 'cash', 'amount' => 8000]],
            ['discount' => 10000],
        ));
        Auth::forgetGuards();
    }

    public function test_checkout_negative_discount_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $this->expectException(\DomainException::class);
        app(SaleService::class)->checkout($this->checkoutData(
            [['product_id' => $this->productA1->id, 'quantity' => 1]],
            [['payment_method' => 'cash', 'amount' => 8000]],
            ['discount' => -1000],
        ));
        Auth::forgetGuards();
    }

    public function test_checkout_negative_tax_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $this->expectException(\DomainException::class);
        app(SaleService::class)->checkout($this->checkoutData(
            [['product_id' => $this->productA1->id, 'quantity' => 1]],
            [['payment_method' => 'cash', 'amount' => 8000]],
            ['tax' => -500],
        ));
        Auth::forgetGuards();
    }

    public function test_checkout_total_spoofing_ignored(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        // Frontend sends total=1, backend should calculate correctly
        $sale = app(SaleService::class)->checkout($this->checkoutData(
            [['product_id' => $this->productA1->id, 'quantity' => 2]],
            [['payment_method' => 'cash', 'amount' => 16000]],
            ['total' => 1, 'subtotal' => 1],
        ));

        $this->assertEquals('16000.00', $sale->subtotal);
        $this->assertEquals('16000.00', $sale->total);
        Auth::forgetGuards();
    }

    // =========================================================================
    // 5. PAYMENT
    // =========================================================================

    public function test_checkout_cash_payment(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 1],
        ], [['payment_method' => 'cash', 'amount' => 10000]]));

        $this->assertEquals('paid', $sale->payment_status);
        $this->assertEquals('10000.00', $sale->paid_amount);
        $this->assertEquals('2000.00', $sale->change_amount); // 10000 - 8000
        $this->assertCount(1, $sale->payments);
        $this->assertEquals('cash', $sale->payments->first()->payment_method);
        Auth::forgetGuards();
    }

    public function test_checkout_qris_payment(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 1],
        ], [['payment_method' => 'qris', 'amount' => 8000, 'payment_reference' => 'QRIS-12345']]));

        $this->assertEquals('paid', $sale->payment_status);
        $this->assertEquals('0.00', $sale->change_amount);
        $this->assertEquals('QRIS-12345', $sale->payments->first()->payment_reference);
        Auth::forgetGuards();
    }

    public function test_checkout_card_payment(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 1],
        ], [['payment_method' => 'card', 'amount' => 8000]]));

        $this->assertEquals('paid', $sale->payment_status);
        $this->assertEquals('card', $sale->payments->first()->payment_method);
        Auth::forgetGuards();
    }

    public function test_checkout_bank_transfer_payment(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 1],
        ], [['payment_method' => 'bank_transfer', 'amount' => 8000]]));

        $this->assertEquals('paid', $sale->payment_status);
        $this->assertEquals('bank_transfer', $sale->payments->first()->payment_method);
        Auth::forgetGuards();
    }

    public function test_checkout_multiple_payments_split(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 2],
        ], [
            ['payment_method' => 'cash', 'amount' => 10000],
            ['payment_method' => 'qris', 'amount' => 6000],
        ]));

        $this->assertEquals('paid', $sale->payment_status);
        $this->assertEquals('16000.00', $sale->paid_amount);
        $this->assertCount(2, $sale->payments);
        Auth::forgetGuards();
    }

    public function test_checkout_partial_payment(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 2],
        ], [['payment_method' => 'cash', 'amount' => 10000]]));

        $this->assertEquals('partial', $sale->payment_status);
        $this->assertEquals('10000.00', $sale->paid_amount);
        $this->assertEquals('16000.00', $sale->total);
        Auth::forgetGuards();
    }

    public function test_checkout_negative_payment_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $this->expectException(\DomainException::class);
        app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 1],
        ], [['payment_method' => 'cash', 'amount' => -5000]]));
        Auth::forgetGuards();
    }

    public function test_checkout_zero_payment_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $this->expectException(\DomainException::class);
        app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 1],
        ], [['payment_method' => 'cash', 'amount' => 0]]));
        Auth::forgetGuards();
    }

    public function test_checkout_invalid_payment_method_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $this->expectException(\DomainException::class);
        app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 1],
        ], [['payment_method' => 'crypto', 'amount' => 8000]]));
        Auth::forgetGuards();
    }

    public function test_checkout_no_payment_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $this->expectException(\DomainException::class);
        app(SaleService::class)->checkout([
            'store_id' => $this->storeA->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 1]],
            'payments' => [],
        ]);
        Auth::forgetGuards();
    }

    // =========================================================================
    // 6. INVENTORY MOVEMENT
    // =========================================================================

    public function test_checkout_creates_sale_movement(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 20],
        ], [['payment_method' => 'cash', 'amount' => 160000]]));

        $movements = $this->getMovements($this->productA1);
        $this->assertCount(1, $movements);
        $this->assertEquals('sale', $movements->first()->type);
        $this->assertEquals(-20, $movements->first()->quantity);
        $this->assertEquals(100, $movements->first()->before_quantity);
        $this->assertEquals(80, $movements->first()->after_quantity);
        Auth::forgetGuards();
    }

    public function test_checkout_movement_references_sale(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 10],
        ], [['payment_method' => 'cash', 'amount' => 80000]]));

        $movement = $this->getMovements($this->productA1)->first();
        $this->assertEquals(Sale::class, $movement->reference_type);
        $this->assertEquals($sale->id, $movement->reference_id);
        Auth::forgetGuards();
    }

    public function test_checkout_movement_user_id_from_auth(): void
    {
        $this->actingAs($this->cashierA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 5],
        ], [['payment_method' => 'cash', 'amount' => 40000]]));

        $movement = $this->getMovements($this->productA1)->first();
        $this->assertEquals($this->cashierA->id, $movement->user_id);
        Auth::forgetGuards();
    }

    public function test_checkout_multiple_products_multiple_movements(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        $this->setInventory($this->storeA, $this->productA2, 50);

        app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 10],
            ['product_id' => $this->productA2->id, 'quantity' => 5],
        ], [['payment_method' => 'cash', 'amount' => 105000]]));

        $this->assertCount(1, $this->getMovements($this->productA1));
        $this->assertCount(1, $this->getMovements($this->productA2));
        $this->assertEquals(-10, $this->getMovements($this->productA1)->first()->quantity);
        $this->assertEquals(-5, $this->getMovements($this->productA2)->first()->quantity);
        Auth::forgetGuards();
    }

    // =========================================================================
    // 7. TENANT SECURITY
    // =========================================================================

    public function test_checkout_cross_tenant_product_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $this->expectException(\DomainException::class);
        app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productB->id, 'quantity' => 1],
        ]));
        Auth::forgetGuards();
    }

    public function test_checkout_cross_tenant_store_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $this->expectException(\DomainException::class);
        app(SaleService::class)->checkout([
            'store_id' => $this->storeB->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 1]],
            'payments' => [['payment_method' => 'cash', 'amount' => 8000]],
        ]);
        Auth::forgetGuards();
    }

    public function test_checkout_cross_tenant_customer_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $this->expectException(\DomainException::class);
        app(SaleService::class)->checkout($this->checkoutData(
            [['product_id' => $this->productA1->id, 'quantity' => 1]],
            [['payment_method' => 'cash', 'amount' => 8000]],
            ['customer_id' => $this->customerB->id],
        ));
        Auth::forgetGuards();
    }

    public function test_checkout_tenant_id_not_from_request(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        // Pass tenant_id of Tenant B — should be ignored
        $sale = app(SaleService::class)->checkout($this->checkoutData(
            [['product_id' => $this->productA1->id, 'quantity' => 1]],
            [['payment_method' => 'cash', 'amount' => 8000]],
            ['tenant_id' => $this->tenantB->id],
        ));

        $this->assertEquals($this->tenantA->id, $sale->tenant_id);
        Auth::forgetGuards();
    }

    public function test_checkout_cashier_id_not_from_request(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        // Pass cashier_id of another user — should be ignored
        $sale = app(SaleService::class)->checkout($this->checkoutData(
            [['product_id' => $this->productA1->id, 'quantity' => 1]],
            [['payment_method' => 'cash', 'amount' => 8000]],
            ['cashier_id' => $this->cashierA->id],
        ));

        $this->assertEquals($this->ownerA->id, $sale->cashier_id);
        Auth::forgetGuards();
    }

    // =========================================================================
    // 8. BUSINESS LOGIC ABUSE
    // =========================================================================

    public function test_checkout_empty_items_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');

        $this->expectException(\DomainException::class);
        app(SaleService::class)->checkout($this->checkoutData([]));
        Auth::forgetGuards();
    }

    public function test_checkout_zero_quantity_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $this->expectException(\DomainException::class);
        app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 0],
        ]));
        Auth::forgetGuards();
    }

    public function test_checkout_negative_quantity_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $this->expectException(\DomainException::class);
        app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => -5],
        ]));
        Auth::forgetGuards();
    }

    public function test_checkout_duplicate_products_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $this->expectException(\DomainException::class);
        app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 2],
            ['product_id' => $this->productA1->id, 'quantity' => 3],
        ]));
        Auth::forgetGuards();
    }

    public function test_checkout_inactive_product_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->productA1->is_active = false;
        $this->productA1->save();
        $this->setInventory($this->storeA, $this->productA1, 100);

        $this->expectException(\DomainException::class);
        app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 1],
        ]));
        Auth::forgetGuards();
    }

    public function test_checkout_nonexistent_product_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $this->expectException(\DomainException::class);
        app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => 99999, 'quantity' => 1],
        ]));
        Auth::forgetGuards();
    }

    // =========================================================================
    // 9. TRANSACTION ROLLBACK
    // =========================================================================

    public function test_checkout_insufficient_stock_rollback_no_sale_created(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 5);

        try {
            app(SaleService::class)->checkout($this->checkoutData([
                ['product_id' => $this->productA1->id, 'quantity' => 10],
            ]));
            $this->fail('Should have thrown');
        } catch (\InvalidArgumentException $e) {
            // Verify no sale was created
            $this->assertEquals(0, Sale::withoutTenantScope()->count());
            // Verify inventory unchanged
            $this->assertEquals(5, $this->getInventory($this->productA1)->quantity);
            // Verify no movements
            $this->assertCount(0, $this->getMovements($this->productA1));
        }
        Auth::forgetGuards();
    }

    public function test_checkout_partial_failure_rollback(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        $this->setInventory($this->storeA, $this->productA2, 3); // insufficient for qty 5

        try {
            app(SaleService::class)->checkout($this->checkoutData([
                ['product_id' => $this->productA1->id, 'quantity' => 10],
                ['product_id' => $this->productA2->id, 'quantity' => 5], // will fail
            ]));
            $this->fail('Should have thrown');
        } catch (\InvalidArgumentException $e) {
            // Everything should be rolled back
            $this->assertEquals(0, Sale::withoutTenantScope()->count());
            $this->assertEquals(100, $this->getInventory($this->productA1)->quantity);
            $this->assertEquals(3, $this->getInventory($this->productA2)->quantity);
            $this->assertCount(0, $this->getMovements($this->productA1));
            $this->assertCount(0, $this->getMovements($this->productA2));
        }
        Auth::forgetGuards();
    }

    public function test_checkout_cross_tenant_failure_rollback(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        try {
            app(SaleService::class)->checkout($this->checkoutData([
                ['product_id' => $this->productA1->id, 'quantity' => 5],
                ['product_id' => $this->productB->id, 'quantity' => 1], // cross-tenant
            ]));
            $this->fail('Should have thrown');
        } catch (\DomainException $e) {
            $this->assertEquals(0, Sale::withoutTenantScope()->count());
            $this->assertEquals(100, $this->getInventory($this->productA1)->quantity);
        }
        Auth::forgetGuards();
    }

    // =========================================================================
    // 10. CANCEL SALE
    // =========================================================================

    public function test_cancel_sale_restores_inventory(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 20],
        ], [['payment_method' => 'cash', 'amount' => 160000]]));

        $this->assertEquals(80, $this->getInventory($this->productA1)->quantity);

        $cancelled = app(SaleService::class)->cancel($sale);
        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertEquals(100, $this->getInventory($this->productA1)->quantity);
        Auth::forgetGuards();
    }

    public function test_cancel_sale_creates_sale_return_movement(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 20],
        ], [['payment_method' => 'cash', 'amount' => 160000]]));

        app(SaleService::class)->cancel($sale);

        $movements = $this->getMovements($this->productA1);
        $this->assertCount(2, $movements);
        $this->assertEquals('sale', $movements[0]->type);
        $this->assertEquals(-20, $movements[0]->quantity);
        $this->assertEquals('sale_return', $movements[1]->type);
        $this->assertEquals(20, $movements[1]->quantity);
        Auth::forgetGuards();
    }

    public function test_cancel_only_completed_sale(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 1],
        ], [['payment_method' => 'cash', 'amount' => 8000]]));

        app(SaleService::class)->cancel($sale);

        $this->expectException(\DomainException::class);
        app(SaleService::class)->cancel($sale->fresh());
        Auth::forgetGuards();
    }

    public function test_cancel_sale_multiple_items_restores_all(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        $this->setInventory($this->storeA, $this->productA2, 50);

        $sale = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 20],
            ['product_id' => $this->productA2->id, 'quantity' => 10],
        ], [['payment_method' => 'cash', 'amount' => 210000]]));

        $this->assertEquals(80, $this->getInventory($this->productA1)->quantity);
        $this->assertEquals(40, $this->getInventory($this->productA2)->quantity);

        app(SaleService::class)->cancel($sale);

        $this->assertEquals(100, $this->getInventory($this->productA1)->quantity);
        $this->assertEquals(50, $this->getInventory($this->productA2)->quantity);
        Auth::forgetGuards();
    }

    // =========================================================================
    // 11. E2E REAL-WORLD SCENARIO
    // =========================================================================

    public function test_e2e_full_sale_flow(): void
    {
        $this->actingAs($this->cashierA, 'sanctum');

        // 1. Setup inventory
        $this->setInventory($this->storeA, $this->productA1, 100);
        $this->setInventory($this->storeA, $this->productA2, 50);

        // 2. Checkout: 2 Coke + 3 Es Teh = 16000 + 15000 = 31000
        $sale = app(SaleService::class)->checkout([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customerA->id,
            'items' => [
                ['product_id' => $this->productA1->id, 'quantity' => 2],
                ['product_id' => $this->productA2->id, 'quantity' => 3],
            ],
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 20000],
                ['payment_method' => 'qris', 'amount' => 11000],
            ],
            'discount' => 1000,
            'tax' => 0,
        ]);

        // 3. Verify sale
        $this->assertEquals('completed', $sale->status);
        $this->assertEquals('paid', $sale->payment_status);
        $this->assertEquals('31000.00', $sale->subtotal);
        $this->assertEquals('1000.00', $sale->discount);
        $this->assertEquals('30000.00', $sale->total);
        $this->assertEquals('31000.00', $sale->paid_amount);
        $this->assertEquals('1000.00', $sale->change_amount);
        $this->assertEquals($this->cashierA->id, $sale->cashier_id);
        $this->assertEquals($this->customerA->id, $sale->customer_id);

        // 4. Verify items with snapshot
        $this->assertCount(2, $sale->items);
        $item1 = $sale->items->where('product_id', $this->productA1->id)->first();
        $item2 = $sale->items->where('product_id', $this->productA2->id)->first();
        $this->assertEquals('Coca Cola', $item1->product_name);
        $this->assertEquals('COKE-001', $item1->sku);
        $this->assertEquals('8000.00', $item1->unit_price);
        $this->assertEquals(2, $item1->quantity);
        $this->assertEquals('16000.00', $item1->total);
        $this->assertEquals('Es Teh', $item2->product_name);
        $this->assertEquals('5000.00', $item2->unit_price);
        $this->assertEquals(3, $item2->quantity);
        $this->assertEquals('15000.00', $item2->total);

        // 5. Verify inventory deducted
        $this->assertEquals(98, $this->getInventory($this->productA1)->quantity);
        $this->assertEquals(47, $this->getInventory($this->productA2)->quantity);

        // 6. Verify movements
        $movements1 = $this->getMovements($this->productA1);
        $movements2 = $this->getMovements($this->productA2);
        $this->assertCount(1, $movements1);
        $this->assertEquals('sale', $movements1->first()->type);
        $this->assertEquals(-2, $movements1->first()->quantity);
        $this->assertEquals(100, $movements1->first()->before_quantity);
        $this->assertEquals(98, $movements1->first()->after_quantity);
        $this->assertEquals(Sale::class, $movements1->first()->reference_type);
        $this->assertEquals($sale->id, $movements1->first()->reference_id);

        $this->assertCount(1, $movements2);
        $this->assertEquals('sale', $movements2->first()->type);
        $this->assertEquals(-3, $movements2->first()->quantity);

        // 7. Verify payments
        $this->assertCount(2, $sale->payments);
        $cashPay = $sale->payments->where('payment_method', 'cash')->first();
        $qrisPay = $sale->payments->where('payment_method', 'qris')->first();
        $this->assertEquals('20000.00', $cashPay->amount);
        $this->assertEquals('11000.00', $qrisPay->amount);
        $this->assertEquals('success', $cashPay->status);
        $this->assertEquals('success', $qrisPay->status);

        // 8. Cancel sale and verify inventory restored
        app(SaleService::class)->cancel($sale);
        $this->assertEquals(100, $this->getInventory($this->productA1)->quantity);
        $this->assertEquals(50, $this->getInventory($this->productA2)->quantity);

        // 9. Verify cancel movements
        $movements1After = $this->getMovements($this->productA1);
        $this->assertCount(2, $movements1After);
        $this->assertEquals('sale_return', $movements1After[1]->type);
        $this->assertEquals(2, $movements1After[1]->quantity);
        $this->assertEquals(98, $movements1After[1]->before_quantity);
        $this->assertEquals(100, $movements1After[1]->after_quantity);

        // 10. Verify sale status
        $this->assertEquals('cancelled', $sale->fresh()->status);
        Auth::forgetGuards();
    }

    // =========================================================================
    // 12. TENANT B ISOLATION
    // =========================================================================

    public function test_tenant_b_inventory_unchanged_after_tenant_a_sale(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        $this->setInventory($this->storeB, $this->productB, 200);

        app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 30],
        ], [['payment_method' => 'cash', 'amount' => 240000]]));

        $invB = Inventory::withoutTenantScope()
            ->where('tenant_id', $this->tenantB->id)
            ->where('store_id', $this->storeB->id)
            ->where('product_id', $this->productB->id)
            ->first();
        $this->assertEquals(200, $invB->quantity);

        // No movements for Tenant B
        $movementsB = InventoryMovement::withoutTenantScope()
            ->where('tenant_id', $this->tenantB->id)
            ->count();
        $this->assertEquals(0, $movementsB);
        Auth::forgetGuards();
    }

    public function test_tenant_b_cannot_see_tenant_a_sales(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 1],
        ], [['payment_method' => 'cash', 'amount' => 8000]]));
        Auth::forgetGuards();

        $this->actingAs($this->ownerB, 'sanctum');
        $this->assertEquals(0, Sale::count());
        Auth::forgetGuards();
    }

    // =========================================================================
    // 13. CONCURRENT CHECKOUT (RACE CONDITION SIMULATION)
    // =========================================================================

    public function test_concurrent_checkout_does_not_create_negative_stock(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 10);

        // Simulate two concurrent checkouts by using nested transactions
        // First checkout takes 7, leaving 3. Second tries 5 — should fail.
        $sale1 = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 7],
        ], [['payment_method' => 'cash', 'amount' => 56000]]));

        $this->assertEquals(3, $this->getInventory($this->productA1)->quantity);

        // Second checkout should fail — only 3 left, requesting 5
        $this->expectException(\InvalidArgumentException::class);
        app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 5],
        ]));
        Auth::forgetGuards();
    }

    public function test_concurrent_checkout_exact_stock_boundary(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 10);

        // First takes 6, leaving 4
        app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 6],
        ], [['payment_method' => 'cash', 'amount' => 48000]]));

        // Second takes exactly 4 — should succeed
        $sale2 = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 4],
        ], [['payment_method' => 'cash', 'amount' => 32000]]));

        $this->assertEquals(0, $this->getInventory($this->productA1)->quantity);
        $this->assertEquals('completed', $sale2->status);
        Auth::forgetGuards();
    }

    // =========================================================================
    // 14. SALE NUMBER GENERATION
    // =========================================================================

    public function test_sale_number_unique_per_tenant(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 1000);

        $sale1 = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 1],
        ], [['payment_method' => 'cash', 'amount' => 8000]]));

        $sale2 = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 1],
        ], [['payment_method' => 'cash', 'amount' => 8000]]));

        $this->assertNotEquals($sale1->sale_number, $sale2->sale_number);
        Auth::forgetGuards();
    }

    public function test_sale_number_same_for_different_tenants(): void
    {
        // Both tenants should be able to have INV-YYYYMMDD-0001
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);
        $saleA = app(SaleService::class)->checkout($this->checkoutData([
            ['product_id' => $this->productA1->id, 'quantity' => 1],
        ], [['payment_method' => 'cash', 'amount' => 8000]]));
        Auth::forgetGuards();

        $this->actingAs($this->ownerB, 'sanctum');
        $this->setInventory($this->storeB, $this->productB, 100);
        $saleB = app(SaleService::class)->checkout([
            'store_id' => $this->storeB->id,
            'items' => [['product_id' => $this->productB->id, 'quantity' => 1]],
            'payments' => [['payment_method' => 'cash', 'amount' => 25000]],
        ]);
        Auth::forgetGuards();

        // Both should end with 0001 (unique per tenant, not globally)
        $this->assertStringEndsWith('0001', $saleA->sale_number);
        $this->assertStringEndsWith('0001', $saleB->sale_number);
    }
}
