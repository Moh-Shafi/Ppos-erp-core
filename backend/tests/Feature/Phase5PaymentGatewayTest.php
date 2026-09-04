<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayInterface;
use App\Payments\XenditPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase5PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['payments.default_gateway' => 'xendit']);
        config(['payments.gateways.xendit.api_key' => 'test_key']);
        config(['payments.gateways.xendit.webhook_token' => 'webhook_test_token']);
        config(['payments.gateways.xendit.base_url' => 'https://api.xendit.co']);
        config(['payments.gateways.xendit.api_version' => '2024-11-11']);

        Http::fake([
            'api.xendit.co/payment_requests' => Http::response([
                'id' => 'pr-test-123',
                'status' => 'REQUIRES_ACTION',
                'actions' => [
                    ['type' => 'QR_DISPLAY', 'value' => '00020101021126580014ID.X...'],
                ],
                'expires_at' => now()->addMinutes(15)->toIso8601String(),
            ], 201),
            'api.xendit.co/payment_requests/*' => Http::response([
                'id' => 'pr-test-123',
                'status' => 'SUCCEEDED',
                'request_amount' => '50000',
                'paid_at' => now()->toIso8601String(),
            ], 200),
            'api.xendit.co/refunds*' => Http::response([
                'id' => 'rfd-test-123',
                'status' => 'SUCCEEDED',
                'amount' => '25000',
            ], 201),
            'api.xendit.co/v2/accounts' => Http::response([
                'id' => 'usr-test-123',
                'status' => 'PENDING',
                'email' => 'test@example.com',
            ], 201),
        ]);
    }

    public function test_xendit_gateway_is_bound(): void
    {
        $gateway = app(PaymentGatewayInterface::class);
        $this->assertInstanceOf(XenditPayment::class, $gateway);
    }

    public function test_xendit_create_charge_qris_returns_qr_string(): void
    {
        $gateway = app(PaymentGatewayInterface::class);
        $result = $gateway->createCharge([
            'payment_method' => 'qris',
            'amount' => 50000,
            'reference_id' => 'SALE-TEST-001',
            'for_user_id' => 'usr-test-123',
        ]);

        $this->assertEquals('pr-test-123', $result['gateway_transaction_id']);
        $this->assertEquals('REQUIRES_ACTION', $result['gateway_status']);
        $this->assertEquals('pending', $result['status']);
        $this->assertEquals('00020101021126580014ID.X...', $result['qr_string']);
    }

    public function test_xendit_get_status_returns_succeeded(): void
    {
        $gateway = app(PaymentGatewayInterface::class);
        $result = $gateway->getStatus('pr-test-123');

        $this->assertEquals('SUCCEEDED', $result['status']);
        $this->assertEquals(50000.0, $result['amount']);
    }

    public function test_xendit_refund_returns_refund_id(): void
    {
        $gateway = app(PaymentGatewayInterface::class);
        $result = $gateway->refund('pr-test-123', 25000, 'Customer request', [
            'for_user_id' => 'usr-test-123',
        ]);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(25000, $result['amount']);
        $this->assertEquals('rfd-test-123', $result['refund_id']);
    }

    public function test_xendit_provision_sub_account_returns_account_id(): void
    {
        $gateway = app(PaymentGatewayInterface::class);
        $result = $gateway->provisionSubAccount([
            'tenant_id' => 1,
            'business_name' => 'Test Tenant',
            'business_email' => 'test@example.com',
        ]);

        $this->assertEquals('usr-test-123', $result['gateway_account_id']);
        $this->assertEquals('pending', $result['status']);
    }

    public function test_xendit_verify_webhook_valid_token(): void
    {
        $gateway = app(PaymentGatewayInterface::class);

        $payload = json_encode([
            'event' => 'payment.capture',
            'business_id' => 'biz-test',
            'created' => now()->toIso8601String(),
            'data' => [
                'payment_request_id' => 'pr-test-123',
                'payment_id' => 'py-test-123',
                'status' => 'SUCCEEDED',
                'request_amount' => '50000',
                'created' => now()->toIso8601String(),
            ],
        ]);

        $result = $gateway->verifyWebhook($payload, [
            'x-callback-token' => 'webhook_test_token',
        ]);

        $this->assertTrue($result['verified']);
        $this->assertEquals('payment.capture', $result['event_type']);
        $this->assertEquals('pr-test-123', $result['gateway_transaction_id']);
        $this->assertEquals('SUCCEEDED', $result['status']);
    }

    public function test_xendit_verify_webhook_invalid_token(): void
    {
        $gateway = app(PaymentGatewayInterface::class);

        $result = $gateway->verifyWebhook('{}', [
            'x-callback-token' => 'wrong-token',
        ]);

        $this->assertFalse($result['verified']);
        $this->assertEquals('none', $result['event_type']);
        $this->assertNull($result['gateway_transaction_id']);
    }
}
