<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SaleModelTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $ownerA;
    private User $ownerB;
    private Store $storeA;
    private Store $storeB;
    private Customer $customerA;
    private Customer $customerB;
    private Category $catA;
    private Product $productA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();

        // Tenant A
        $this->tenantA = Tenant::create(['name' => 'Toko A', 'slug' => 'toko-a']);
        $this->ownerA = User::create([
            'tenant_id' => $this->tenantA->id, 'role_id' => $ownerRole->id,
            'name' => 'Owner A', 'email' => 'owner.a@salem.com', 'password' => 'password',
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
            'name' => 'Owner B', 'email' => 'owner.b@salem.com', 'password' => 'password',
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
    }

    private function createSale(Tenant $tenant, Store $store, User $cashier, ?Customer $customer = null): Sale
    {
        $sale = new Sale;
        $sale->tenant_id = $tenant->id;
        $sale->store_id = $store->id;
        $sale->cashier_id = $cashier->id;
        $sale->customer_id = $customer?->id;
        $maxId = (int) Sale::withoutTenantScope()->max('id');
        $sale->sale_number = 'INV-' . str_pad((string) ($maxId + 1), 4, '0', STR_PAD_LEFT);
        $sale->status = 'completed';
        $sale->payment_status = 'paid';
        $sale->sale_date = now();
        $sale->subtotal = 16000;
        $sale->discount = 0;
        $sale->tax = 0;
        $sale->total = 16000;
        $sale->paid_amount = 16000;
        $sale->change_amount = 0;
        $sale->save();

        $item = new SaleItem;
        $item->sale_id = $sale->id;
        $item->product_id = $this->productA->id;
        $item->product_name = 'Coca Cola';
        $item->sku = 'COKE-001';
        $item->quantity = 2;
        $item->unit_price = 8000;
        $item->discount = 0;
        $item->tax = 0;
        $item->subtotal = 16000;
        $item->total = 16000;
        $item->save();

        $payment = new Payment;
        $payment->tenant_id = $tenant->id;
        $payment->sale_id = $sale->id;
        $payment->payment_method = 'cash';
        $payment->amount = 16000;
        $payment->change_amount = 0;
        $payment->status = 'success';
        $payment->payment_date = now();
        $payment->save();

        return $sale->fresh(['items', 'payments']);
    }

    // --- Sale Relationships ---

    public function test_sale_belongs_to_tenant(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $this->assertEquals($this->tenantA->id, $sale->tenant->id);
    }

    public function test_sale_belongs_to_store(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $this->assertEquals($this->storeA->id, $sale->store->id);
    }

    public function test_sale_belongs_to_cashier(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $this->assertEquals($this->ownerA->id, $sale->cashier->id);
    }

    public function test_sale_belongs_to_customer(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA, $this->customerA);
        $this->assertEquals($this->customerA->id, $sale->customer->id);
    }

    public function test_sale_customer_can_be_null(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $this->assertNull($sale->customer);
    }

    public function test_sale_has_many_items(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $this->assertCount(1, $sale->items);
        $this->assertInstanceOf(SaleItem::class, $sale->items->first());
    }

    public function test_sale_has_many_payments(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $this->assertCount(1, $sale->payments);
        $this->assertInstanceOf(Payment::class, $sale->payments->first());
    }

    // --- SaleItem Relationships ---

    public function test_sale_item_belongs_to_sale(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $item = $sale->items->first();
        $this->assertEquals($sale->id, $item->sale->id);
    }

    public function test_sale_item_belongs_to_product(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $item = $sale->items->first();
        $this->assertEquals($this->productA->id, $item->product->id);
    }

    public function test_sale_item_has_snapshot_fields(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $item = $sale->items->first();
        $this->assertEquals('Coca Cola', $item->product_name);
        $this->assertEquals('COKE-001', $item->sku);
        $this->assertEquals('8000.00', $item->unit_price);
    }

    public function test_sale_item_snapshot_preserved_after_product_change(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $item = $sale->items->first();

        // Change product price and name
        $this->productA->selling_price = 12000;
        $this->productA->name = 'Coca Cola New';
        $this->productA->save();

        // Reload item — snapshot should be unchanged
        $item->refresh();
        $this->assertEquals('Coca Cola', $item->product_name);
        $this->assertEquals('COKE-001', $item->sku);
        $this->assertEquals('8000.00', $item->unit_price);
        $this->assertEquals('16000.00', $item->total);
    }

    // --- Payment Relationships ---

    public function test_payment_belongs_to_tenant(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $payment = $sale->payments->first();
        $this->assertEquals($this->tenantA->id, $payment->tenant->id);
    }

    public function test_payment_belongs_to_sale(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $payment = $sale->payments->first();
        $this->assertEquals($sale->id, $payment->sale->id);
    }

    // --- Reverse Relationships ---

    public function test_store_has_many_sales(): void
    {
        $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $this->assertCount(2, $this->storeA->fresh()->sales);
    }

    public function test_user_has_many_sales_as_cashier(): void
    {
        $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $this->assertCount(1, $this->ownerA->fresh()->sales);
    }

    public function test_customer_has_many_sales(): void
    {
        $this->createSale($this->tenantA, $this->storeA, $this->ownerA, $this->customerA);
        $this->assertCount(1, $this->customerA->fresh()->sales);
    }

    public function test_product_has_many_sale_items(): void
    {
        $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $this->assertCount(1, $this->productA->fresh()->saleItems);
    }

    // --- Mass Assignment Protection ---

    public function test_sale_tenant_id_not_mass_assignable(): void
    {
        // tenant_id is NOT in $fillable, so mass-assigning it won't set it.
        // DB column is NOT NULL, so creating without tenant_id throws QueryException.
        $this->expectException(\Illuminate\Database\QueryException::class);
        Sale::create([
            'tenant_id' => $this->tenantB->id,
            'store_id' => $this->storeA->id,
            'cashier_id' => $this->ownerA->id,
            'sale_number' => 'INV-TEST1',
            'status' => 'completed',
            'payment_status' => 'paid',
            'sale_date' => now(),
            'subtotal' => 1000,
            'total' => 1000,
            'paid_amount' => 1000,
        ]);
    }

    public function test_payment_tenant_id_not_mass_assignable(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);

        // tenant_id is NOT in $fillable, DB column is NOT NULL
        $this->expectException(\Illuminate\Database\QueryException::class);
        Payment::create([
            'tenant_id' => $this->tenantB->id,
            'sale_id' => $sale->id,
            'payment_method' => 'cash',
            'amount' => 5000,
            'payment_date' => now(),
        ]);
    }

    // --- Tenant Isolation ---

    public function test_tenant_scope_filters_sales(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        Auth::forgetGuards();

        $this->actingAs($this->ownerB, 'sanctum');
        $this->createSale($this->tenantB, $this->storeB, $this->ownerB);
        Auth::forgetGuards();

        $this->actingAs($this->ownerA, 'sanctum');
        $this->assertCount(1, Sale::all());
        Auth::forgetGuards();

        $this->actingAs($this->ownerB, 'sanctum');
        $this->assertCount(1, Sale::all());
        Auth::forgetGuards();
    }

    public function test_tenant_scope_filters_payments(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        Auth::forgetGuards();

        $this->actingAs($this->ownerB, 'sanctum');
        $saleB = $this->createSale($this->tenantB, $this->storeB, $this->ownerB);
        Auth::forgetGuards();

        $this->actingAs($this->ownerB, 'sanctum');
        $this->assertCount(1, Payment::all());
        Auth::forgetGuards();

        $this->actingAs($this->ownerA, 'sanctum');
        $this->assertCount(1, Payment::all());
        Auth::forgetGuards();
    }

    public function test_tenant_a_cannot_access_tenant_b_sale(): void
    {
        $this->actingAs($this->ownerB, 'sanctum');
        $saleB = $this->createSale($this->tenantB, $this->storeB, $this->ownerB);
        Auth::forgetGuards();

        $this->actingAs($this->ownerA, 'sanctum');
        $found = Sale::find($saleB->id);
        $this->assertNull($found);
        Auth::forgetGuards();
    }

    public function test_without_tenant_scope_sees_all_sales(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');
        $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        Auth::forgetGuards();

        $this->actingAs($this->ownerB, 'sanctum');
        $this->createSale($this->tenantB, $this->storeB, $this->ownerB);
        Auth::forgetGuards();

        $this->assertCount(2, Sale::withoutTenantScope()->get());
    }

    // --- Casts ---

    public function test_sale_monetary_fields_cast_to_decimal(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $this->assertEquals('16000.00', $sale->subtotal);
        $this->assertEquals('16000.00', $sale->total);
        $this->assertEquals('16000.00', $sale->paid_amount);
        $this->assertEquals('0.00', $sale->change_amount);
    }

    public function test_sale_date_cast_to_datetime(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $sale->sale_date);
    }

    public function test_sale_item_monetary_fields_cast_to_decimal(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $item = $sale->items->first();
        $this->assertEquals('8000.00', $item->unit_price);
        $this->assertEquals('16000.00', $item->subtotal);
        $this->assertEquals('16000.00', $item->total);
    }

    public function test_payment_monetary_fields_cast_to_decimal(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $payment = $sale->payments->first();
        $this->assertEquals('16000.00', $payment->amount);
        $this->assertEquals('0.00', $payment->change_amount);
    }

    public function test_payment_metadata_cast_to_array(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $payment = $sale->payments->first();
        $payment->metadata = ['gateway' => 'midtrans', 'txn_id' => 'ABC123'];
        $payment->save();
        $payment->refresh();
        $this->assertIsArray($payment->metadata);
        $this->assertEquals('midtrans', $payment->metadata['gateway']);
    }

    // --- Sale Number Uniqueness ---

    public function test_sale_number_unique_per_tenant(): void
    {
        $sale1 = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);

        // Manually create a second sale with the same sale_number for the same tenant
        $sale2 = new Sale;
        $sale2->tenant_id = $this->tenantA->id;
        $sale2->store_id = $this->storeA->id;
        $sale2->cashier_id = $this->ownerA->id;
        $sale2->sale_number = $sale1->sale_number; // duplicate
        $sale2->status = 'completed';
        $sale2->payment_status = 'paid';
        $sale2->sale_date = now();
        $sale2->subtotal = 1000;
        $sale2->total = 1000;
        $sale2->paid_amount = 1000;

        $this->expectException(\Illuminate\Database\QueryException::class);
        $sale2->save();
    }

    // --- Status Enums ---

    public function test_sale_status_enum_values(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $this->assertEquals('completed', $sale->status);

        $sale->status = 'cancelled';
        $sale->save();
        $this->assertEquals('cancelled', $sale->fresh()->status);

        $sale->status = 'refunded';
        $sale->save();
        $this->assertEquals('refunded', $sale->fresh()->status);
    }

    public function test_payment_status_enum_values(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $this->assertEquals('paid', $sale->payment_status);

        $sale->payment_status = 'partial';
        $sale->save();
        $this->assertEquals('partial', $sale->fresh()->payment_status);

        $sale->payment_status = 'unpaid';
        $sale->save();
        $this->assertEquals('unpaid', $sale->fresh()->payment_status);
    }

    public function test_payment_method_enum_values(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $payment = $sale->payments->first();

        foreach (['cash', 'qris', 'card', 'bank_transfer'] as $method) {
            $payment->payment_method = $method;
            $payment->save();
            $this->assertEquals($method, $payment->fresh()->payment_method);
        }
    }

    public function test_payment_transaction_status_enum_values(): void
    {
        $sale = $this->createSale($this->tenantA, $this->storeA, $this->ownerA);
        $payment = $sale->payments->first();

        foreach (['success', 'pending', 'failed'] as $status) {
            $payment->status = $status;
            $payment->save();
            $this->assertEquals($status, $payment->fresh()->status);
        }
    }
}
