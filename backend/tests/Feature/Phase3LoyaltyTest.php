<?php

namespace Tests\Feature;

use App\Models\CustomerLoyaltyPoints;
use App\Models\CustomerLoyaltyTransaction;
use App\Services\LoyaltyService;
use Illuminate\Support\Facades\Auth;

class Phase3LoyaltyTest extends Phase3TestHelper
{
    private LoyaltyService $loyaltyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupPhase3();
        $this->loyaltyService = new LoyaltyService();
    }

    public function test_earn_points_on_sale(): void
    {
        Auth::login($this->owner);
        $customer = $this->createCustomer();

        $points = $this->loyaltyService->earnPoints($customer, 50000, 1);

        $this->assertEquals(5, $points); // 50000 / 10000 = 5

        $loyalty = CustomerLoyaltyPoints::where('customer_id', $customer->id)->first();
        $this->assertEquals(5, $loyalty->points_balance);
        $this->assertEquals(5, $loyalty->total_earned);
    }

    public function test_redeem_points(): void
    {
        Auth::login($this->owner);
        $customer = $this->createCustomer();

        $this->loyaltyService->earnPoints($customer, 50000, 1);
        $discount = $this->loyaltyService->redeemPoints($customer, 3, 2);

        $this->assertEquals(3000, $discount); // 3 * 1000

        $loyalty = CustomerLoyaltyPoints::where('customer_id', $customer->id)->first();
        $this->assertEquals(2, $loyalty->points_balance);
        $this->assertEquals(3, $loyalty->total_redeemed);
    }

    public function test_cannot_redeem_more_than_balance(): void
    {
        Auth::login($this->owner);
        $customer = $this->createCustomer();

        $this->loyaltyService->earnPoints($customer, 10000, 1); // 1 point

        $this->expectException(\DomainException::class);
        $this->loyaltyService->redeemPoints($customer, 5, 2);
    }

    public function test_manual_adjust_points(): void
    {
        Auth::login($this->owner);
        $customer = $this->createCustomer();

        $balance = $this->loyaltyService->adjustPoints($customer, 50, 'Birthday bonus');

        $this->assertEquals(50, $balance);

        $tx = CustomerLoyaltyTransaction::where('customer_id', $customer->id)->first();
        $this->assertEquals('adjust', $tx->type);
        $this->assertEquals('manual', $tx->source);
        $this->assertEquals('Birthday bonus', $tx->note);
    }

    public function test_no_expiry_when_disabled(): void
    {
        Auth::login($this->owner);
        $customer = $this->createCustomer();
        $this->loyaltyService->earnPoints($customer, 50000, 1);

        $expired = $this->loyaltyService->processExpiry($this->tenant->id);

        $this->assertEquals(0, $expired);
    }

    public function test_loyalty_transaction_log(): void
    {
        Auth::login($this->owner);
        $customer = $this->createCustomer();
        $this->loyaltyService->earnPoints($customer, 50000, 1);

        $tx = CustomerLoyaltyTransaction::where('customer_id', $customer->id)->first();
        $this->assertNotNull($tx);
        $this->assertEquals(5, $tx->points);
        $this->assertEquals('earn', $tx->type);
        $this->assertEquals(5, $tx->balance_after);
    }

    public function test_loyalty_api_endpoints(): void
    {
        Auth::login($this->owner);
        $customer = $this->createCustomer();
        $this->loyaltyService->earnPoints($customer, 50000, 1);

        $response = $this->withHeaders($this->authHeader($this->tokenOwner))
            ->getJson("/api/v1/customers/{$customer->id}/loyalty");

        $response->assertStatus(200);
        $response->assertJson(['points_balance' => 5]);
    }

    public function test_loyalty_adjust_api(): void
    {
        Auth::login($this->owner);
        $customer = $this->createCustomer();

        $response = $this->withHeaders($this->authHeader($this->tokenOwner))
            ->postJson("/api/v1/customers/{$customer->id}/loyalty/adjust", [
                'points' => 25,
                'note' => 'Promo bonus',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['points_balance' => 25]);
    }

    public function test_cashier_cannot_adjust_points(): void
    {
        $customer = $this->createCustomer();

        $response = $this->withHeaders($this->authHeader($this->tokenCashier))
            ->postJson("/api/v1/customers/{$customer->id}/loyalty/adjust", [
                'points' => 25,
                'note' => 'test',
            ]);

        $response->assertStatus(403);
    }
}
