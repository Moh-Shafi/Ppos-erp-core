<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;

class Phase4PriceListTest extends Phase4TestHelper
{
    public function test_checkout_with_price_list_uses_price_list_price(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->product, 100);

        Auth::login($this->cashier);
        $service = app(\App\Services\SaleService::class);

        $sale = $service->checkout($this->checkoutData(
            [['product_id' => $this->product->id, 'quantity' => 2]],
            [['payment_method' => 'cash', 'amount' => 999999]],
            ['customer_id' => $this->customerWithPriceList->id]
        ));

        $item = $sale->items->first();
        $this->assertEquals(8500, (float) $item->unit_price);
        $this->assertEquals(10000, (float) $item->original_price);
        $this->assertEquals($this->priceList->id, $sale->price_list_id);
    }

    public function test_checkout_without_price_list_uses_product_price(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->product, 100);

        Auth::login($this->cashier);
        $service = app(\App\Services\SaleService::class);

        $sale = $service->checkout($this->checkoutData(
            [['product_id' => $this->product->id, 'quantity' => 1]],
            [['payment_method' => 'cash', 'amount' => 999999]],
            ['customer_id' => $this->customer->id]
        ));

        $item = $sale->items->first();
        $this->assertEquals(10000, (float) $item->unit_price);
        $this->assertNull($item->original_price);
        $this->assertNull($sale->price_list_id);
    }

    public function test_checkout_price_list_overrides_variant_price(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->productWithVariants, 100);

        Auth::login($this->cashier);
        $service = app(\App\Services\SaleService::class);

        $sale = $service->checkout($this->checkoutData(
            [['product_id' => $this->productWithVariants->id, 'variant_id' => $this->variant1->id, 'quantity' => 1]],
            [['payment_method' => 'cash', 'amount' => 999999]],
            ['customer_id' => $this->customerWithPriceList->id]
        ));

        $item = $sale->items->first();
        // Price list has 16000 for this variant, which overrides variant's 18000
        $this->assertEquals(16000, (float) $item->unit_price);
        $this->assertEquals(15000, (float) $item->original_price);
    }

    public function test_checkout_no_customer_uses_product_price(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->product, 100);

        Auth::login($this->cashier);
        $service = app(\App\Services\SaleService::class);

        $sale = $service->checkout($this->checkoutData([
            ['product_id' => $this->product->id, 'quantity' => 1],
        ]));

        $item = $sale->items->first();
        $this->assertEquals(10000, (float) $item->unit_price);
        $this->assertNull($sale->price_list_id);
    }

    public function test_checkout_price_list_product_not_in_list_uses_product_price(): void
    {
        $this->setupPhase4();
        // Create a product not in the price list
        $newProduct = $this->product;
        // Use productWithVariants variant2 which is not in price list
        $this->setInventory($this->store, $this->productWithVariants, 100);

        Auth::login($this->cashier);
        $service = app(\App\Services\SaleService::class);

        $sale = $service->checkout($this->checkoutData(
            [['product_id' => $this->productWithVariants->id, 'variant_id' => $this->variant2->id, 'quantity' => 1]],
            [['payment_method' => 'cash', 'amount' => 999999]],
            ['customer_id' => $this->customerWithPriceList->id]
        ));

        $item = $sale->items->first();
        // variant2 has no price_override (null), so falls back to product price 15000
        // No price list item for variant2, so stays at 15000
        $this->assertEquals(15000, (float) $item->unit_price);
    }
}
