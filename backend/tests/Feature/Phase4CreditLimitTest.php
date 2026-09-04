<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Support\Facades\Auth;

class Phase4CreditLimitTest extends Phase4TestHelper
{
    public function test_checkout_blocked_when_credit_limit_exceeded(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->product, 100);

        // Set credit limit to 10000, outstanding to 8000
        $this->customerWithCredit->credit_limit = 10000;
        $this->customerWithCredit->outstanding_balance = 8000;
        $this->customerWithCredit->save();

        Auth::login($this->cashier);
        $service = app(\App\Services\SaleService::class);

        // Sale total = 10000 * 2 = 20000, outstanding + 20000 = 28000 > 10000
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Credit limit exceeded');

        $service->checkout($this->checkoutData(
            [['product_id' => $this->product->id, 'quantity' => 2]],
            [['payment_method' => 'cash', 'amount' => 999999]],
            ['customer_id' => $this->customerWithCredit->id]
        ));
    }

    public function test_checkout_allowed_when_within_credit_limit(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->product, 100);

        // Credit limit 50000, outstanding 0, sale = 10000
        Auth::login($this->cashier);
        $service = app(\App\Services\SaleService::class);

        $sale = $service->checkout($this->checkoutData(
            [['product_id' => $this->product->id, 'quantity' => 1]],
            [['payment_method' => 'cash', 'amount' => 10000]],
            ['customer_id' => $this->customerWithCredit->id]
        ));

        $this->assertEquals('completed', $sale->status);
    }

    public function test_checkout_with_null_credit_limit_allowed(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->product, 100);

        // customer has no credit_limit (null = unlimited)
        $this->customer->credit_limit = null;
        $this->customer->save();

        Auth::login($this->cashier);
        $service = app(\App\Services\SaleService::class);

        $sale = $service->checkout($this->checkoutData(
            [['product_id' => $this->product->id, 'quantity' => 10]],
            [['payment_method' => 'cash', 'amount' => 999999]],
            ['customer_id' => $this->customer->id]
        ));

        $this->assertEquals('completed', $sale->status);
    }

    public function test_checkout_credit_debit_for_unpaid_portion(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->product, 100);

        // Pay only 5000 of 10000 total, leaving 5000 outstanding
        Auth::login($this->cashier);
        $service = app(\App\Services\SaleService::class);

        $sale = $service->checkout($this->checkoutData(
            [['product_id' => $this->product->id, 'quantity' => 1]],
            [['payment_method' => 'cash', 'amount' => 5000]],
            ['customer_id' => $this->customerWithCredit->id]
        ));

        $customer = Customer::withoutTenantScope()->find($this->customerWithCredit->id);
        $this->assertEquals(5000, (float) $customer->outstanding_balance);
        $this->assertEquals('partial', $sale->payment_status);
    }

    public function test_checkout_no_credit_check_without_customer(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->product, 100);

        Auth::login($this->cashier);
        $service = app(\App\Services\SaleService::class);

        $sale = $service->checkout($this->checkoutData([
            ['product_id' => $this->product->id, 'quantity' => 1],
        ]));

        $this->assertEquals('completed', $sale->status);
    }
}
