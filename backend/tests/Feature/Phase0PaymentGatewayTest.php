<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Payments\ManualPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase0PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_gateway_interface_is_bound(): void
    {
        $gateway = app(PaymentGatewayInterface::class);
        $this->assertInstanceOf(ManualPayment::class, $gateway);
    }

    public function test_manual_payment_create_charge(): void
    {
        $gateway = app(PaymentGatewayInterface::class);
        $result = $gateway->createCharge(['amount' => 50000]);

        $this->assertEquals('success', $result['status']);
        $this->assertNotEmpty($result['gateway_transaction_id']);
        $this->assertStringStartsWith('MANUAL-', $result['gateway_transaction_id']);
    }

    public function test_manual_payment_get_status(): void
    {
        $gateway = app(PaymentGatewayInterface::class);
        $result = $gateway->getStatus('MANUAL-test-123');

        $this->assertEquals('success', $result['status']);
        $this->assertNotNull($result['paid_at']);
    }

    public function test_manual_payment_refund(): void
    {
        $gateway = app(PaymentGatewayInterface::class);
        $result = $gateway->refund('MANUAL-test-123', 25000, 'Customer request');

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(25000, $result['amount']);
        $this->assertStringStartsWith('MANUAL-REF-', $result['refund_id']);
    }

    public function test_manual_payment_verify_webhook_returns_unverified(): void
    {
        $gateway = app(PaymentGatewayInterface::class);
        $result = $gateway->verifyWebhook('payload', []);

        $this->assertFalse($result['verified']);
        $this->assertEquals('none', $result['event_type']);
    }

    public function test_manual_payment_provision_sub_account(): void
    {
        $gateway = app(PaymentGatewayInterface::class);
        $result = $gateway->provisionSubAccount(['tenant_id' => 1]);

        $this->assertEquals('active', $result['status']);
        $this->assertStringStartsWith('MANUAL-', $result['gateway_account_id']);
    }

    public function test_payments_config_has_manual_and_xendit(): void
    {
        $this->assertEquals('manual', config('payments.default_gateway'));
        $this->assertArrayHasKey('manual', config('payments.gateways'));
        $this->assertArrayHasKey('xendit', config('payments.gateways'));
        $this->assertEquals('manual', config('payments.gateways.manual.driver'));
        $this->assertEquals('xendit', config('payments.gateways.xendit.driver'));
    }

    public function test_xendit_config_is_empty_in_phase0(): void
    {
        $this->assertEquals('', config('payments.gateways.xendit.api_key'));
        $this->assertEquals('', config('payments.gateways.xendit.webhook_token'));
    }
}
