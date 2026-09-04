<?php

namespace Tests\Feature;

use App\Models\Inventory;
use Illuminate\Support\Facades\Auth;

class Phase4ApiTest extends Phase4TestHelper
{
    // =========================================================================
    // Variant Checkout API
    // =========================================================================

    public function test_api_checkout_with_variant(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->productWithVariants, 100);

        $response = $this->withHeaders($this->authHeader($this->tokenCashier))
            ->postJson('/api/v1/sales/checkout', [
                'store_id' => $this->store->id,
                'items' => [
                    ['product_id' => $this->productWithVariants->id, 'variant_id' => $this->variant1->id, 'quantity' => 2],
                ],
                'payments' => [['payment_method' => 'cash', 'amount' => 999999]],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('items.0.variant_id', $this->variant1->id);
        $response->assertJsonPath('items.0.unit_price', '18000.00');
    }

    public function test_api_checkout_variant_product_without_variant_fails(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->productWithVariants, 100);

        $response = $this->withHeaders($this->authHeader($this->tokenCashier))
            ->postJson('/api/v1/sales/checkout', [
                'store_id' => $this->store->id,
                'items' => [
                    ['product_id' => $this->productWithVariants->id, 'quantity' => 1],
                ],
                'payments' => [['payment_method' => 'cash', 'amount' => 999999]],
            ]);

        $response->assertStatus(422);
    }

    // =========================================================================
    // Refund API
    // =========================================================================

    public function test_api_full_refund(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->product, 100);

        // Create sale first
        Auth::login($this->cashier);
        $service = app(\App\Services\SaleService::class);
        $sale = $service->checkout($this->checkoutData([
            ['product_id' => $this->product->id, 'quantity' => 1],
        ]));
        Auth::logout();

        $response = $this->withHeaders($this->authHeader($this->tokenOwner))
            ->postJson("/api/v1/sales/{$sale->id}/refunds", [
                'type' => 'full',
                'reason' => 'Customer returned item',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('type', 'full');
    }

    public function test_api_partial_refund(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->product, 100);

        Auth::login($this->cashier);
        $service = app(\App\Services\SaleService::class);
        $sale = $service->checkout($this->checkoutData([
            ['product_id' => $this->product->id, 'quantity' => 3],
        ]));
        Auth::logout();

        $saleItem = $sale->items->first();

        $response = $this->withHeaders($this->authHeader($this->tokenOwner))
            ->postJson("/api/v1/sales/{$sale->id}/refunds", [
                'type' => 'partial',
                'reason' => 'One item defective',
                'items' => [
                    ['sale_item_id' => $saleItem->id, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('type', 'partial');
    }

    public function test_api_list_refunds(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->product, 100);

        Auth::login($this->cashier);
        $service = app(\App\Services\SaleService::class);
        $sale = $service->checkout($this->checkoutData([
            ['product_id' => $this->product->id, 'quantity' => 1],
        ]));

        $refundService = app(\App\Services\RefundService::class);
        $refundService->fullRefund($sale);
        Auth::logout();

        $response = $this->withHeaders($this->authHeader($this->tokenCashier))
            ->getJson("/api/v1/sales/{$sale->id}/refunds");

        $response->assertStatus(200);
        $response->assertJsonCount(1);
    }

    // =========================================================================
    // Held Sale API
    // =========================================================================

    public function test_api_hold_sale(): void
    {
        $this->setupPhase4();

        $response = $this->withHeaders($this->authHeader($this->tokenCashier))
            ->postJson('/api/v1/held-sales', [
                'store_id' => $this->store->id,
                'cart_data' => [
                    'items' => [
                        ['product_id' => $this->product->id, 'quantity' => 2],
                    ],
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'held');
    }

    public function test_api_recall_held_sale(): void
    {
        $this->setupPhase4();

        $holdResponse = $this->withHeaders($this->authHeader($this->tokenCashier))
            ->postJson('/api/v1/held-sales', [
                'store_id' => $this->store->id,
                'cart_data' => [
                    'items' => [
                        ['product_id' => $this->product->id, 'quantity' => 1],
                    ],
                ],
            ]);

        $heldSaleId = $holdResponse->json('id');

        $response = $this->withHeaders($this->authHeader($this->tokenCashier))
            ->postJson("/api/v1/held-sales/{$heldSaleId}/recall");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'recalled');
    }

    public function test_api_list_held_sales(): void
    {
        $this->setupPhase4();

        $this->withHeaders($this->authHeader($this->tokenCashier))
            ->postJson('/api/v1/held-sales', [
                'store_id' => $this->store->id,
                'cart_data' => [
                    'items' => [
                        ['product_id' => $this->product->id, 'quantity' => 1],
                    ],
                ],
            ]);

        $response = $this->withHeaders($this->authHeader($this->tokenCashier))
            ->getJson('/api/v1/held-sales?store_id=' . $this->store->id);

        $response->assertStatus(200);
    }

    public function test_api_delete_held_sale(): void
    {
        $this->setupPhase4();

        $holdResponse = $this->withHeaders($this->authHeader($this->tokenCashier))
            ->postJson('/api/v1/held-sales', [
                'store_id' => $this->store->id,
                'cart_data' => [
                    'items' => [
                        ['product_id' => $this->product->id, 'quantity' => 1],
                    ],
                ],
            ]);

        $heldSaleId = $holdResponse->json('id');

        $response = $this->withHeaders($this->authHeader($this->tokenCashier))
            ->deleteJson("/api/v1/held-sales/{$heldSaleId}");

        $response->assertStatus(204);
    }

    // =========================================================================
    // Discount Preset API
    // =========================================================================

    public function test_api_create_discount_preset(): void
    {
        $this->setupPhase4();

        $response = $this->withHeaders($this->authHeader($this->tokenOwner))
            ->postJson('/api/v1/discount-presets', [
                'name' => '10% Off',
                'type' => 'percentage',
                'value' => 10,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('name', '10% Off');
    }

    public function test_api_list_discount_presets(): void
    {
        $this->setupPhase4();

        $this->withHeaders($this->authHeader($this->tokenOwner))
            ->postJson('/api/v1/discount-presets', [
                'name' => '5% Off',
                'type' => 'percentage',
                'value' => 5,
            ]);

        $response = $this->withHeaders($this->authHeader($this->tokenCashier))
            ->getJson('/api/v1/discount-presets');

        $response->assertStatus(200);
    }

    public function test_api_update_discount_preset(): void
    {
        $this->setupPhase4();

        $createResponse = $this->withHeaders($this->authHeader($this->tokenOwner))
            ->postJson('/api/v1/discount-presets', [
                'name' => 'Original',
                'type' => 'percentage',
                'value' => 5,
            ]);

        $id = $createResponse->json('id');

        $response = $this->withHeaders($this->authHeader($this->tokenOwner))
            ->putJson("/api/v1/discount-presets/{$id}", [
                'name' => 'Updated',
                'value' => 15,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('name', 'Updated');
    }

    public function test_api_delete_discount_preset(): void
    {
        $this->setupPhase4();

        $createResponse = $this->withHeaders($this->authHeader($this->tokenOwner))
            ->postJson('/api/v1/discount-presets', [
                'name' => 'Delete Me',
                'type' => 'percentage',
                'value' => 5,
            ]);

        $id = $createResponse->json('id');

        $response = $this->withHeaders($this->authHeader($this->tokenOwner))
            ->deleteJson("/api/v1/discount-presets/{$id}");

        $response->assertStatus(204);
    }

    public function test_api_discount_preset_percentage_over_100_rejected(): void
    {
        $this->setupPhase4();

        $response = $this->withHeaders($this->authHeader($this->tokenOwner))
            ->postJson('/api/v1/discount-presets', [
                'name' => 'Invalid',
                'type' => 'percentage',
                'value' => 150,
            ]);

        $response->assertStatus(422);
    }

    // =========================================================================
    // Receipt Settings API
    // =========================================================================

    public function test_api_update_receipt_settings(): void
    {
        $this->setupPhase4();

        $response = $this->withHeaders($this->authHeader($this->tokenOwner))
            ->putJson("/api/v1/stores/{$this->store->id}/receipt-settings", [
                'header_text' => 'My Store',
                'footer_text' => 'Thanks!',
                'show_cashier' => true,
                'show_customer' => true,
                'show_qr_code' => false,
                'paper_width' => '80mm',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('header_text', 'My Store');
    }

    public function test_api_get_receipt_settings(): void
    {
        $this->setupPhase4();

        $this->withHeaders($this->authHeader($this->tokenOwner))
            ->putJson("/api/v1/stores/{$this->store->id}/receipt-settings", [
                'header_text' => 'Test',
            ]);

        $response = $this->withHeaders($this->authHeader($this->tokenOwner))
            ->getJson("/api/v1/stores/{$this->store->id}/receipt-settings");

        $response->assertStatus(200);
        $response->assertJsonPath('header_text', 'Test');
    }
}
