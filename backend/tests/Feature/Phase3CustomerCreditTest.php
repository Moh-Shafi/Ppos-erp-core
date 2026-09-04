<?php

namespace Tests\Feature;

use App\Models\CustomerCreditTransaction;
use App\Services\CustomerCreditService;
use Illuminate\Support\Facades\Auth;

class Phase3CustomerCreditTest extends Phase3TestHelper
{
    private CustomerCreditService $creditService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupPhase3();
        $this->creditService = new CustomerCreditService();
    }

    public function test_credit_limit_blocks_sale(): void
    {
        Auth::login($this->owner);
        $customer = $this->createCustomer();
        $customer->credit_limit = 1000000;
        $customer->outstanding_balance = 800000;
        $customer->save();

        $result = $this->creditService->checkLimit($customer, 300000);

        $this->assertFalse($result['allowed']);
    }

    public function test_credit_limit_allows_within_limit(): void
    {
        Auth::login($this->owner);
        $customer = $this->createCustomer();
        $customer->credit_limit = 1000000;
        $customer->outstanding_balance = 500000;
        $customer->save();

        $result = $this->creditService->checkLimit($customer, 300000);

        $this->assertTrue($result['allowed']);
    }

    public function test_no_limit_allows_any_amount(): void
    {
        Auth::login($this->owner);
        $customer = $this->createCustomer();
        $customer->credit_limit = null;
        $customer->save();

        $result = $this->creditService->checkLimit($customer, 999999999);

        $this->assertTrue($result['allowed']);
    }

    public function test_add_debit_on_credit_sale(): void
    {
        Auth::login($this->owner);
        $customer = $this->createCustomer();

        $balance = $this->creditService->addDebit($customer, 500000, 'sale', 1);

        $this->assertEquals(500000, $balance);
        $customer->refresh();
        $this->assertEquals(500000, (float) $customer->outstanding_balance);
    }

    public function test_add_credit_on_payment(): void
    {
        Auth::login($this->owner);
        $customer = $this->createCustomer();
        $this->creditService->addDebit($customer, 500000, 'sale', 1);

        $balance = $this->creditService->addCredit($customer, 200000, 'payment', 1);

        $this->assertEquals(300000, $balance);
        $customer->refresh();
        $this->assertEquals(300000, (float) $customer->outstanding_balance);
    }

    public function test_manual_credit_adjust(): void
    {
        Auth::login($this->owner);
        $customer = $this->createCustomer();
        $this->creditService->addDebit($customer, 500000, 'sale', 1);

        $balance = $this->creditService->adjust($customer, -200000, 'Payment received offline');

        $this->assertEquals(300000, $balance);
    }

    public function test_credit_transaction_log(): void
    {
        Auth::login($this->owner);
        $customer = $this->createCustomer();
        $this->creditService->addDebit($customer, 500000, 'sale', 1);

        $tx = CustomerCreditTransaction::where('customer_id', $customer->id)->first();
        $this->assertNotNull($tx);
        $this->assertEquals('debit', $tx->type);
        $this->assertEquals(500000, (float) $tx->amount);
        $this->assertEquals(500000, (float) $tx->balance_after);
    }

    public function test_credit_check_api(): void
    {
        Auth::login($this->owner);
        $customer = $this->createCustomer();
        $customer->credit_limit = 1000000;
        $customer->save();

        $response = $this->withHeaders($this->authHeader($this->tokenOwner))
            ->postJson("/api/v1/customers/{$customer->id}/credit/check", [
                'amount' => 500000,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['allowed' => true]);
    }

    public function test_credit_adjust_api(): void
    {
        Auth::login($this->owner);
        $customer = $this->createCustomer();
        $this->creditService->addDebit($customer, 500000, 'sale', 1);

        $response = $this->withHeaders($this->authHeader($this->tokenOwner))
            ->postJson("/api/v1/customers/{$customer->id}/credit/adjust", [
                'amount' => -200000,
                'note' => 'Payment received',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['outstanding_balance' => 300000]);
    }
}
