<?php

namespace Tests\Feature;

use App\Models\HeldSale;
use Illuminate\Support\Facades\Auth;

class Phase4HoldSaleTest extends Phase4TestHelper
{
    public function test_hold_sale_creates_held_sale(): void
    {
        $this->setupPhase4();

        Auth::login($this->cashier);
        $service = app(\App\Services\HoldSaleService::class);

        $heldSale = $service->hold([
            'store_id' => $this->store->id,
            'cart_data' => [
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 2],
                ],
            ],
        ]);

        $this->assertEquals('held', $heldSale->status);
        $this->assertNotNull($heldSale->hold_number);
        $this->assertNotNull($heldSale->expires_at);
        $this->assertNotEmpty($heldSale->cart_data['items']);
    }

    public function test_hold_sale_with_customer(): void
    {
        $this->setupPhase4();

        Auth::login($this->cashier);
        $service = app(\App\Services\HoldSaleService::class);

        $heldSale = $service->hold([
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'cart_data' => [
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 1],
                ],
            ],
        ]);

        $this->assertEquals($this->customer->id, $heldSale->customer_id);
    }

    public function test_hold_sale_empty_cart_rejected(): void
    {
        $this->setupPhase4();

        Auth::login($this->cashier);
        $service = app(\App\Services\HoldSaleService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('at least one item');

        $service->hold([
            'store_id' => $this->store->id,
            'cart_data' => ['items' => []],
        ]);
    }

    public function test_recall_held_sale(): void
    {
        $this->setupPhase4();

        Auth::login($this->cashier);
        $service = app(\App\Services\HoldSaleService::class);

        $heldSale = $service->hold([
            'store_id' => $this->store->id,
            'cart_data' => [
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 1],
                ],
            ],
        ]);

        $recalled = $service->recall($heldSale->id);

        $this->assertEquals('recalled', $recalled->status);
        $this->assertNotNull($recalled->recalled_at);
    }

    public function test_recall_already_recalled_rejected(): void
    {
        $this->setupPhase4();

        Auth::login($this->cashier);
        $service = app(\App\Services\HoldSaleService::class);

        $heldSale = $service->hold([
            'store_id' => $this->store->id,
            'cart_data' => [
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 1],
                ],
            ],
        ]);

        $service->recall($heldSale->id);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('cannot be recalled');
        $service->recall($heldSale->id);
    }

    public function test_recall_expired_held_sale_rejected(): void
    {
        $this->setupPhase4();

        Auth::login($this->cashier);
        $service = app(\App\Services\HoldSaleService::class);

        $heldSale = $service->hold([
            'store_id' => $this->store->id,
            'cart_data' => [
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 1],
                ],
            ],
        ]);

        // Manually expire
        $heldSale->expires_at = now()->subHour();
        $heldSale->save();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('expired');
        $service->recall($heldSale->id);
    }

    public function test_delete_held_sale(): void
    {
        $this->setupPhase4();

        Auth::login($this->cashier);
        $service = app(\App\Services\HoldSaleService::class);

        $heldSale = $service->hold([
            'store_id' => $this->store->id,
            'cart_data' => [
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 1],
                ],
            ],
        ]);

        $service->delete($heldSale->id);

        $this->assertDatabaseMissing('held_sales', ['id' => $heldSale->id]);
    }

    public function test_list_held_sales(): void
    {
        $this->setupPhase4();

        Auth::login($this->cashier);
        $service = app(\App\Services\HoldSaleService::class);

        for ($i = 0; $i < 3; $i++) {
            $service->hold([
                'store_id' => $this->store->id,
                'cart_data' => [
                    'items' => [
                        ['product_id' => $this->product->id, 'quantity' => 1],
                    ],
                ],
            ]);
        }

        $result = $service->list($this->tenant->id, $this->store->id, 'held');
        $this->assertCount(3, $result->items());
    }

    public function test_hold_number_unique_format(): void
    {
        $this->setupPhase4();

        Auth::login($this->cashier);
        $service = app(\App\Services\HoldSaleService::class);

        $heldSale = $service->hold([
            'store_id' => $this->store->id,
            'cart_data' => [
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 1],
                ],
            ],
        ]);

        $this->assertMatchesRegularExpression('/^HOLD-\d{8}-\d{4}$/', $heldSale->hold_number);
    }
}
