<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $ownerA;
    private User $cashierA;
    private User $staffA;
    private User $ownerB;
    private string $tokenOwnerA;
    private string $tokenCashierA;
    private string $tokenStaffA;
    private string $tokenOwnerB;
    private Store $storeA;
    private Store $storeB;
    private Customer $customerA;
    private Category $catA;
    private Product $productA1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();
        $cashierRole = Role::where('slug', 'cashier')->first();
        $staffRole = Role::where('slug', 'staff')->first();

        // Tenant A
        $this->tenantA = Tenant::create(['name' => 'Toko A', 'slug' => 'toko-a-pay']);
        $this->ownerA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner A', 'email' => 'owner.a@paytest.com', 'password' => 'password',
        ]);
        $this->cashierA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $cashierRole->id,
            'name' => 'Cashier A', 'email' => 'cashier.a@paytest.com', 'password' => 'password',
        ]);
        $this->staffA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $staffRole->id,
            'name' => 'Staff A', 'email' => 'staff.a@paytest.com', 'password' => 'password',
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

        // Tenant B
        $this->tenantB = Tenant::create(['name' => 'Toko B', 'slug' => 'toko-b-pay']);
        $this->ownerB = User::create([
            'tenant_id' => $this->tenantB->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner B', 'email' => 'owner.b@paytest.com', 'password' => 'password',
        ]);
        $this->tokenOwnerB = $this->ownerB->createToken('test')->plainTextToken;

        $this->storeB = new Store;
        $this->storeB->tenant_id = $this->tenantB->id;
        $this->storeB->name = 'Store B';
        $this->storeB->code = 'SB';
        $this->storeB->is_active = true;
        $this->storeB->save();
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

    private function createSale(int $qty = 2, array $payments = [['payment_method' => 'cash', 'amount' => 999999]]): Sale
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout([
            'store_id' => $this->storeA->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => $qty]],
            'payments' => $payments,
        ]);
        Auth::forgetGuards();
        return $sale->fresh();
    }

    private function createPartialSale(): Sale
    {
        // Total = 16000 (2 * 8000), pay only 10000 → partial
        return $this->createSale(2, [['payment_method' => 'cash', 'amount' => 10000]]);
    }

    // =========================================================================
    // 1. PAYMENT METHODS — ALL TYPES
    // =========================================================================

    public function test_checkout_cash_payment_creates_payment(): void
    {
        $sale = $this->createSale(1, [['payment_method' => 'cash', 'amount' => 8000]]);

        $this->assertCount(1, $sale->payments);
        $this->assertEquals('cash', $sale->payments->first()->payment_method);
        $this->assertEquals('8000.00', $sale->payments->first()->amount);
        $this->assertEquals('success', $sale->payments->first()->status);
    }

    public function test_checkout_qris_payment_creates_payment(): void
    {
        $sale = $this->createSale(1, [['payment_method' => 'qris', 'amount' => 8000, 'payment_reference' => 'QRIS-001']]);

        $this->assertEquals('qris', $sale->payments->first()->payment_method);
        $this->assertEquals('QRIS-001', $sale->payments->first()->payment_reference);
    }

    public function test_checkout_card_payment_creates_payment(): void
    {
        $sale = $this->createSale(1, [['payment_method' => 'card', 'amount' => 8000]]);

        $this->assertEquals('card', $sale->payments->first()->payment_method);
    }

    public function test_checkout_bank_transfer_payment_creates_payment(): void
    {
        $sale = $this->createSale(1, [['payment_method' => 'bank_transfer', 'amount' => 8000]]);

        $this->assertEquals('bank_transfer', $sale->payments->first()->payment_method);
    }

    // =========================================================================
    // 2. SPLIT PAYMENT
    // =========================================================================

    public function test_checkout_split_payment_cash_and_qris(): void
    {
        $sale = $this->createSale(2, [
            ['payment_method' => 'cash', 'amount' => 10000],
            ['payment_method' => 'qris', 'amount' => 6000, 'payment_reference' => 'QRIS-SPLIT-001'],
        ]);

        $this->assertCount(2, $sale->payments);
        $this->assertEquals('16000.00', $sale->paid_amount);
        $this->assertEquals('paid', $sale->payment_status);
    }

    public function test_checkout_split_payment_three_methods(): void
    {
        $sale = $this->createSale(2, [
            ['payment_method' => 'cash', 'amount' => 5000],
            ['payment_method' => 'card', 'amount' => 5000],
            ['payment_method' => 'bank_transfer', 'amount' => 6000],
        ]);

        $this->assertCount(3, $sale->payments);
        $this->assertEquals('16000.00', $sale->paid_amount);
    }

    // =========================================================================
    // 3. PARTIAL PAYMENT + ADD PAYMENT
    // =========================================================================

    public function test_add_payment_to_partial_sale(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createPartialSale();

        $this->assertEquals('partial', $sale->payment_status);
        $this->assertEquals('10000.00', $sale->paid_amount);
        $this->assertEquals('6000.00', $sale->outstandingAmount());

        $payment = app(PaymentService::class)->addPayment($sale, [
            'payment_method' => 'qris',
            'amount' => 6000,
        ]);

        $this->assertEquals('6000.00', $payment->amount);
        $sale = $sale->fresh();
        $this->assertEquals('paid', $sale->payment_status);
        $this->assertEquals('16000.00', $sale->paid_amount);
        Auth::forgetGuards();
    }

    public function test_add_payment_exact_outstanding(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createPartialSale();

        $payment = app(PaymentService::class)->addPayment($sale, [
            'payment_method' => 'cash',
            'amount' => 6000,
        ]);

        $sale = $sale->fresh();
        $this->assertEquals('paid', $sale->payment_status);
        $this->assertEquals('0.00', $sale->change_amount);
        Auth::forgetGuards();
    }

    public function test_add_payment_overpayment_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createPartialSale(); // outstanding = 6000

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('exceeds outstanding');
        app(PaymentService::class)->addPayment($sale, [
            'payment_method' => 'cash',
            'amount' => 7000,
        ]);
        Auth::forgetGuards();
    }

    public function test_add_payment_to_already_paid_sale_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createSale(1, [['payment_method' => 'cash', 'amount' => 8000]]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already fully paid');
        app(PaymentService::class)->addPayment($sale, [
            'payment_method' => 'cash',
            'amount' => 1000,
        ]);
        Auth::forgetGuards();
    }

    public function test_add_payment_to_cancelled_sale_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createSale(1, [['payment_method' => 'cash', 'amount' => 8000]]);
        $this->actingAs($this->ownerA, 'sanctum');
        app(SaleService::class)->cancel($sale);

        $this->expectException(\DomainException::class);
        app(PaymentService::class)->addPayment($sale->fresh(), [
            'payment_method' => 'cash',
            'amount' => 1000,
        ]);
        Auth::forgetGuards();
    }

    public function test_add_payment_zero_amount_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createPartialSale();

        $this->expectException(\DomainException::class);
        app(PaymentService::class)->addPayment($sale, [
            'payment_method' => 'cash',
            'amount' => 0,
        ]);
        Auth::forgetGuards();
    }

    public function test_add_payment_negative_amount_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createPartialSale();

        $this->expectException(\DomainException::class);
        app(PaymentService::class)->addPayment($sale, [
            'payment_method' => 'cash',
            'amount' => -1000,
        ]);
        Auth::forgetGuards();
    }

    public function test_add_payment_invalid_method_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createPartialSale();

        $this->expectException(\DomainException::class);
        app(PaymentService::class)->addPayment($sale, [
            'payment_method' => 'crypto',
            'amount' => 6000,
        ]);
        Auth::forgetGuards();
    }

    // =========================================================================
    // 4. OVERPAYMENT / CHANGE
    // =========================================================================

    public function test_checkout_overpayment_change_calculated(): void
    {
        $sale = $this->createSale(1, [['payment_method' => 'cash', 'amount' => 10000]]);

        $this->assertEquals('paid', $sale->payment_status);
        $this->assertEquals('2000.00', $sale->change_amount); // 10000 - 8000
    }

    public function test_checkout_exact_payment_no_change(): void
    {
        $sale = $this->createSale(1, [['payment_method' => 'qris', 'amount' => 8000]]);

        $this->assertEquals('0.00', $sale->change_amount);
    }

    public function test_checkout_large_overpayment_change(): void
    {
        $sale = $this->createSale(1, [['payment_method' => 'cash', 'amount' => 50000]]);

        $this->assertEquals('42000.00', $sale->change_amount); // 50000 - 8000
    }

    // =========================================================================
    // 5. PAYMENT STATUS
    // =========================================================================

    public function test_payment_status_paid(): void
    {
        $sale = $this->createSale(1, [['payment_method' => 'cash', 'amount' => 8000]]);
        $this->assertEquals('paid', $sale->payment_status);
    }

    public function test_payment_status_partial(): void
    {
        $sale = $this->createSale(2, [['payment_method' => 'cash', 'amount' => 5000]]);
        $this->assertEquals('partial', $sale->payment_status);
        $this->assertEquals('5000.00', $sale->paid_amount);
    }

    public function test_payment_status_transitions_partial_to_paid(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createPartialSale();
        $this->assertEquals('partial', $sale->payment_status);

        app(PaymentService::class)->addPayment($sale, [
            'payment_method' => 'cash',
            'amount' => 6000,
        ]);

        $this->assertEquals('paid', $sale->fresh()->payment_status);
        Auth::forgetGuards();
    }

    // =========================================================================
    // 6. IDEMPOTENCY — DUPLICATE PAYMENT PROTECTION
    // =========================================================================

    public function test_idempotency_key_prevents_duplicate_checkout_payment(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        // First checkout with idempotency key
        $sale1 = app(SaleService::class)->checkout([
            'store_id' => $this->storeA->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 1]],
            'payments' => [
                ['payment_method' => 'qris', 'amount' => 8000, 'idempotency_key' => 'IDEM-001'],
            ],
        ]);

        $this->assertCount(1, $sale1->payments);
        $this->assertEquals('IDEM-001', $sale1->payments->first()->idempotency_key);

        // Second checkout with same idempotency key → should fail
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Idempotency key already used');
        app(SaleService::class)->checkout([
            'store_id' => $this->storeA->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 1]],
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 8000, 'idempotency_key' => 'IDEM-001'],
            ],
        ]);
        Auth::forgetGuards();
    }

    public function test_idempotency_key_prevents_duplicate_add_payment(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createPartialSale();

        // First payment with idempotency key
        $pay1 = app(PaymentService::class)->addPayment($sale, [
            'payment_method' => 'cash',
            'amount' => 3000,
            'idempotency_key' => 'IDEM-PAY-001',
        ]);
        $this->assertEquals('IDEM-PAY-001', $pay1->idempotency_key);

        // Second payment with same key → rejected
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Idempotency key already used');
        app(PaymentService::class)->addPayment($sale->fresh(), [
            'payment_method' => 'cash',
            'amount' => 3000,
            'idempotency_key' => 'IDEM-PAY-001',
        ]);
        Auth::forgetGuards();
    }

    public function test_payment_reference_prevents_duplicate(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        // First checkout with reference
        $sale1 = app(SaleService::class)->checkout([
            'store_id' => $this->storeA->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 1]],
            'payments' => [
                ['payment_method' => 'qris', 'amount' => 8000, 'payment_reference' => 'REF-UNIQUE-001'],
            ],
        ]);

        // Second checkout with same reference → should fail
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Payment reference already exists');
        app(SaleService::class)->checkout([
            'store_id' => $this->storeA->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 1]],
            'payments' => [
                ['payment_method' => 'qris', 'amount' => 8000, 'payment_reference' => 'REF-UNIQUE-001'],
            ],
        ]);
        Auth::forgetGuards();
    }

    public function test_duplicate_reference_within_same_request_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Duplicate payment reference within request');
        app(SaleService::class)->checkout([
            'store_id' => $this->storeA->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 2]],
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 8000, 'payment_reference' => 'DUP-REF'],
                ['payment_method' => 'qris', 'amount' => 8000, 'payment_reference' => 'DUP-REF'],
            ],
        ]);
        Auth::forgetGuards();
    }

    public function test_duplicate_idempotency_key_within_same_request_rejected(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Duplicate idempotency key within request');
        app(SaleService::class)->checkout([
            'store_id' => $this->storeA->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 2]],
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 8000, 'idempotency_key' => 'DUP-IDEM'],
                ['payment_method' => 'qris', 'amount' => 8000, 'idempotency_key' => 'DUP-IDEM'],
            ],
        ]);
        Auth::forgetGuards();
    }

    public function test_different_idempotency_keys_allowed(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout([
            'store_id' => $this->storeA->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 2]],
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 8000, 'idempotency_key' => 'IDEM-A'],
                ['payment_method' => 'qris', 'amount' => 8000, 'idempotency_key' => 'IDEM-B'],
            ],
        ]);

        $this->assertCount(2, $sale->payments);
        $this->assertEquals('IDEM-A', $sale->payments[0]->idempotency_key);
        $this->assertEquals('IDEM-B', $sale->payments[1]->idempotency_key);
        Auth::forgetGuards();
    }

    // =========================================================================
    // 7. REFUND ON CANCEL
    // =========================================================================

    public function test_cancel_refunds_all_payments(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createSale(2, [
            ['payment_method' => 'cash', 'amount' => 10000],
            ['payment_method' => 'qris', 'amount' => 6000, 'payment_reference' => 'QRIS-CANCEL-001'],
        ]);

        $this->assertEquals('success', $sale->payments[0]->status);
        $this->assertEquals('success', $sale->payments[1]->status);

        $this->actingAs($this->ownerA, 'sanctum');
        app(SaleService::class)->cancel($sale);

        $sale = $sale->fresh();
        $this->assertEquals('cancelled', $sale->status);
        foreach ($sale->payments as $payment) {
            $this->assertEquals('refunded', $payment->status);
        }
        Auth::forgetGuards();
    }

    public function test_cancel_refunds_partial_payment(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createPartialSale();

        $this->assertEquals('success', $sale->payments->first()->status);

        $this->actingAs($this->ownerA, 'sanctum');
        app(SaleService::class)->cancel($sale);

        $sale = $sale->fresh();
        $this->assertEquals('refunded', $sale->payments->first()->status);
        Auth::forgetGuards();
    }

    public function test_cancel_payment_status_refunded_in_db(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createSale(1, [['payment_method' => 'cash', 'amount' => 8000]]);
        $this->actingAs($this->ownerA, 'sanctum');
        app(SaleService::class)->cancel($sale);

        $this->assertDatabaseHas('payments', [
            'sale_id' => $sale->id,
            'status' => 'refunded',
        ]);
        Auth::forgetGuards();
    }

    // =========================================================================
    // 8. TRANSACTION ROLLBACK
    // =========================================================================

    public function test_add_payment_rollback_on_failure(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createPartialSale(); // outstanding = 6000

        // Try to overpay → should rollback
        try {
            app(PaymentService::class)->addPayment($sale, [
                'payment_method' => 'cash',
                'amount' => 10000, // exceeds 6000 outstanding
            ]);
            $this->fail('Should have thrown');
        } catch (\DomainException $e) {
            // Verify no new payment was created
            $sale = $sale->fresh();
            $this->assertCount(1, $sale->payments); // only original payment
            $this->assertEquals('10000.00', $sale->paid_amount); // unchanged
            $this->assertEquals('partial', $sale->payment_status); // unchanged
        }
        Auth::forgetGuards();
    }

    public function test_checkout_payment_creation_rollback_on_duplicate(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        // Create first sale with reference
        app(SaleService::class)->checkout([
            'store_id' => $this->storeA->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 1]],
            'payments' => [['payment_method' => 'qris', 'amount' => 8000, 'payment_reference' => 'REF-ROLLBACK-001']],
        ]);

        $saleCountBefore = Sale::withoutTenantScope()->count();
        $invBefore = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA1->id)
            ->first()->quantity;

        // Second checkout with same reference → should fail and rollback
        try {
            app(SaleService::class)->checkout([
                'store_id' => $this->storeA->id,
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'qris', 'amount' => 8000, 'payment_reference' => 'REF-ROLLBACK-001']],
            ]);
            $this->fail('Should have thrown');
        } catch (\DomainException $e) {
            // Verify no new sale, inventory unchanged
            $this->assertEquals($saleCountBefore, Sale::withoutTenantScope()->count());
            $invAfter = Inventory::withoutTenantScope()
                ->where('store_id', $this->storeA->id)
                ->where('product_id', $this->productA1->id)
                ->first()->quantity;
            $this->assertEquals($invBefore, $invAfter);
        }
        Auth::forgetGuards();
    }

    // =========================================================================
    // 9. TENANT ISOLATION
    // =========================================================================

    public function test_tenant_b_cannot_add_payment_to_tenant_a_sale(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createPartialSale();
        $saleId = $sale->id;
        Auth::forgetGuards();

        // Tenant B tries to add payment via API
        $response = $this->withToken($this->tokenOwnerB)
            ->postJson("/api/v1/sales/{$saleId}/payments", [
                'payment_method' => 'cash',
                'amount' => 6000,
            ]);

        $response->assertStatus(404); // Tenant B can't see Tenant A's sale
    }

    public function test_tenant_b_cannot_list_tenant_a_payments(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createSale(1, [['payment_method' => 'cash', 'amount' => 8000]]);
        $saleId = $sale->id;
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerB)
            ->getJson("/api/v1/sales/{$saleId}/payments");

        $response->assertStatus(404);
    }

    public function test_payment_tenant_id_from_sale_not_request(): void
    {
        $sale = $this->createSale(1, [['payment_method' => 'cash', 'amount' => 8000]]);

        $payment = $sale->payments->first();
        $this->assertEquals($this->tenantA->id, $payment->tenant_id);
    }

    // =========================================================================
    // 10. INVENTORY / SALE CONSISTENCY
    // =========================================================================

    public function test_payment_failure_does_not_affect_inventory(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        // Checkout fails due to duplicate reference → inventory unchanged
        // First, create a payment with a reference
        app(SaleService::class)->checkout([
            'store_id' => $this->storeA->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 5]],
            'payments' => [['payment_method' => 'qris', 'amount' => 40000, 'payment_reference' => 'CONSIST-001']],
        ]);

        $invBefore = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA1->id)
            ->first()->quantity;
        $this->assertEquals(95, $invBefore);

        // Failed checkout should not change inventory
        try {
            app(SaleService::class)->checkout([
                'store_id' => $this->storeA->id,
                'items' => [['product_id' => $this->productA1->id, 'quantity' => 10]],
                'payments' => [['payment_method' => 'qris', 'amount' => 80000, 'payment_reference' => 'CONSIST-001']],
            ]);
        } catch (\DomainException $e) {
            // Expected
        }

        $invAfter = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA1->id)
            ->first()->quantity;
        $this->assertEquals(95, $invAfter); // unchanged
        Auth::forgetGuards();
    }

    public function test_cancel_restores_inventory_and_refunds_payment(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createSale(5, [['payment_method' => 'cash', 'amount' => 40000]]);

        $invBefore = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA1->id)
            ->first()->quantity;
        $this->assertEquals(95, $invBefore); // 100 - 5

        $this->actingAs($this->ownerA, 'sanctum');
        app(SaleService::class)->cancel($sale);

        $invAfter = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA1->id)
            ->first()->quantity;
        $this->assertEquals(100, $invAfter); // restored

        $sale = $sale->fresh();
        $this->assertEquals('cancelled', $sale->status);
        $this->assertEquals('refunded', $sale->payments->first()->status);
        Auth::forgetGuards();
    }

    // =========================================================================
    // 11. API ENDPOINTS
    // =========================================================================

    public function test_api_add_payment_success(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createPartialSale();
        $saleId = $sale->id;
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$saleId}/payments", [
                'payment_method' => 'qris',
                'amount' => 6000,
                'payment_reference' => 'API-PAY-001',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('amount', '6000.00');
        $response->assertJsonPath('payment_method', 'qris');
        $response->assertJsonPath('status', 'success');
    }

    public function test_api_add_payment_cashier_allowed(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createPartialSale();
        $saleId = $sale->id;
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenCashierA)
            ->postJson("/api/v1/sales/{$saleId}/payments", [
                'payment_method' => 'cash',
                'amount' => 6000,
            ]);

        $response->assertStatus(201);
    }

    public function test_api_add_payment_staff_forbidden(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createPartialSale();
        $saleId = $sale->id;
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenStaffA)
            ->postJson("/api/v1/sales/{$saleId}/payments", [
                'payment_method' => 'cash',
                'amount' => 6000,
            ]);

        $response->assertStatus(403);
    }

    public function test_api_add_payment_no_token_401(): void
    {
        $response = $this->postJson('/api/v1/sales/1/payments', [
            'payment_method' => 'cash',
            'amount' => 6000,
        ]);

        $response->assertStatus(401);
    }

    public function test_api_add_payment_overpayment_422(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createPartialSale();
        $saleId = $sale->id;
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$saleId}/payments", [
                'payment_method' => 'cash',
                'amount' => 10000, // outstanding is 6000
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Payment amount exceeds outstanding balance. Outstanding: 6000, Payment: 10000');
    }

    public function test_api_add_payment_already_paid_422(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createSale(1, [['payment_method' => 'cash', 'amount' => 8000]]);
        $saleId = $sale->id;
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$saleId}/payments", [
                'payment_method' => 'cash',
                'amount' => 1000,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Sale is already fully paid');
    }

    public function test_api_add_payment_validation_error(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createPartialSale();
        $saleId = $sale->id;
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$saleId}/payments", [
                'payment_method' => 'invalid',
                'amount' => 6000,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payment_method']);
    }

    public function test_api_list_payments(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createSale(2, [
            ['payment_method' => 'cash', 'amount' => 10000],
            ['payment_method' => 'qris', 'amount' => 6000],
        ]);
        $saleId = $sale->id;
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenOwnerA)
            ->getJson("/api/v1/sales/{$saleId}/payments");

        $response->assertStatus(200);
        $response->assertJsonCount(2);
    }

    public function test_api_list_payments_cashier_allowed(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createSale(1, [['payment_method' => 'cash', 'amount' => 8000]]);
        $saleId = $sale->id;
        Auth::forgetGuards();

        $response = $this->withToken($this->tokenCashierA)
            ->getJson("/api/v1/sales/{$saleId}/payments");

        $response->assertStatus(200);
    }

    public function test_api_list_payments_staff_forbidden(): void
    {
        $response = $this->withToken($this->tokenStaffA)
            ->getJson('/api/v1/sales/1/payments');

        $response->assertStatus(403);
    }

    public function test_api_list_payments_no_token_401(): void
    {
        $response = $this->getJson('/api/v1/sales/1/payments');

        $response->assertStatus(401);
    }

    public function test_api_add_payment_idempotency_key_duplicate_422(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createPartialSale();
        $saleId = $sale->id;
        Auth::forgetGuards();

        // First payment with idempotency key
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$saleId}/payments", [
                'payment_method' => 'cash',
                'amount' => 3000,
                'idempotency_key' => 'API-IDEM-001',
            ])->assertStatus(201);

        // Second payment with same key → 422
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$saleId}/payments", [
                'payment_method' => 'cash',
                'amount' => 3000,
                'idempotency_key' => 'API-IDEM-001',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Idempotency key already used: API-IDEM-001');
    }

    public function test_api_add_payment_reference_duplicate_422(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createPartialSale();
        $saleId = $sale->id;
        Auth::forgetGuards();

        // First payment with reference
        $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$saleId}/payments", [
                'payment_method' => 'qris',
                'amount' => 3000,
                'payment_reference' => 'API-REF-001',
            ])->assertStatus(201);

        // Second payment with same reference → 422
        $response = $this->withToken($this->tokenOwnerA)
            ->postJson("/api/v1/sales/{$saleId}/payments", [
                'payment_method' => 'qris',
                'amount' => 3000,
                'payment_reference' => 'API-REF-001',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Payment reference already exists: API-REF-001');
    }

    // =========================================================================
    // 12. METADATA
    // =========================================================================

    public function test_payment_metadata_stored(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        $sale = app(SaleService::class)->checkout([
            'store_id' => $this->storeA->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 1]],
            'payments' => [
                ['payment_method' => 'card', 'amount' => 8000, 'metadata' => ['bank' => 'BCA', 'last4' => '1234']],
            ],
        ]);

        $payment = $sale->payments->first();
        $this->assertIsArray($payment->metadata);
        $this->assertEquals('BCA', $payment->metadata['bank']);
        $this->assertEquals('1234', $payment->metadata['last4']);
        Auth::forgetGuards();
    }

    public function test_add_payment_metadata_stored(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $sale = $this->createPartialSale();

        $payment = app(PaymentService::class)->addPayment($sale, [
            'payment_method' => 'card',
            'amount' => 6000,
            'metadata' => ['bank' => 'Mandiri', 'last4' => '5678'],
        ]);

        $this->assertEquals('Mandiri', $payment->metadata['bank']);
        Auth::forgetGuards();
    }

    // =========================================================================
    // 13. E2E PAYMENT FLOW
    // =========================================================================

    public function test_e2e_partial_then_add_payment_then_cancel(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->setInventory($this->storeA, $this->productA1, 100);

        // 1. Checkout with partial payment
        $sale = app(SaleService::class)->checkout([
            'store_id' => $this->storeA->id,
            'customer_id' => $this->customerA->id,
            'items' => [['product_id' => $this->productA1->id, 'quantity' => 5]],
            'payments' => [['payment_method' => 'cash', 'amount' => 20000]],
            'notes' => 'E2E payment test',
        ]);

        $this->assertEquals('partial', $sale->payment_status);
        $this->assertEquals('20000.00', $sale->paid_amount);
        $this->assertEquals('40000.00', $sale->total); // 5 * 8000
        $this->assertEquals('20000.00', $sale->outstandingAmount());

        // 2. Add second payment
        $payment2 = app(PaymentService::class)->addPayment($sale, [
            'payment_method' => 'qris',
            'amount' => 20000,
            'payment_reference' => 'E2E-QRIS-001',
            'idempotency_key' => 'E2E-IDEM-001',
        ]);

        $sale = $sale->fresh();
        $this->assertEquals('paid', $sale->payment_status);
        $this->assertEquals('40000.00', $sale->paid_amount);
        $this->assertCount(2, $sale->payments);

        // 3. Verify inventory
        $inv = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA1->id)
            ->first();
        $this->assertEquals(95, $inv->quantity); // 100 - 5

        // 4. Cancel sale
        $this->actingAs($this->ownerA, 'sanctum');
        app(SaleService::class)->cancel($sale);

        $sale = $sale->fresh();
        $this->assertEquals('cancelled', $sale->status);

        // 5. Verify payments refunded
        foreach ($sale->payments as $p) {
            $this->assertEquals('refunded', $p->status);
        }

        // 6. Verify inventory restored
        $inv = Inventory::withoutTenantScope()
            ->where('store_id', $this->storeA->id)
            ->where('product_id', $this->productA1->id)
            ->first();
        $this->assertEquals(100, $inv->quantity);

        // 7. Verify cannot add payment to cancelled sale
        $this->expectException(\DomainException::class);
        app(PaymentService::class)->addPayment($sale, [
            'payment_method' => 'cash',
            'amount' => 1000,
        ]);
        Auth::forgetGuards();
    }
}
