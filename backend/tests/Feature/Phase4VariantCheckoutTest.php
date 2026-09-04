<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\SaleItem;
use Illuminate\Support\Facades\Auth;

class Phase4VariantCheckoutTest extends Phase4TestHelper
{
    public function test_checkout_with_variant_uses_variant_price_override(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->productWithVariants, 100);

        Auth::login($this->cashier);
        $service = app(\App\Services\SaleService::class);

        $sale = $service->checkout($this->checkoutData([
            ['product_id' => $this->productWithVariants->id, 'variant_id' => $this->variant1->id, 'quantity' => 2],
        ]));

        $item = $sale->items->first();
        $this->assertNotNull($item->variant_id);
        $this->assertEquals($this->variant1->id, $item->variant_id);
        $this->assertEquals(18000, (float) $item->unit_price);
        $this->assertEquals(15000, (float) $item->original_price);
    }

    public function test_checkout_with_variant_no_price_override_uses_product_price(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->productWithVariants, 100);

        Auth::login($this->cashier);
        $service = app(\App\Services\SaleService::class);

        $sale = $service->checkout($this->checkoutData([
            ['product_id' => $this->productWithVariants->id, 'variant_id' => $this->variant2->id, 'quantity' => 1],
        ]));

        $item = $sale->items->first();
        $this->assertEquals($this->variant2->id, $item->variant_id);
        $this->assertEquals(15000, (float) $item->unit_price);
        $this->assertNull($item->original_price);
    }

    public function test_checkout_variant_product_without_variant_rejected(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->productWithVariants, 100);

        Auth::login($this->cashier);
        $service = app(\App\Services\SaleService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('requires a variant selection');

        $service->checkout($this->checkoutData([
            ['product_id' => $this->productWithVariants->id, 'quantity' => 1],
        ]));
    }

    public function test_checkout_with_invalid_variant_rejected(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->productWithVariants, 100);

        Auth::login($this->cashier);
        $service = app(\App\Services\SaleService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Variant');

        $service->checkout($this->checkoutData([
            ['product_id' => $this->product->id, 'variant_id' => $this->variant1->id, 'quantity' => 1],
        ]));
    }

    public function test_checkout_with_inactive_variant_rejected(): void
    {
        $this->setupPhase4();
        $this->variant1->is_active = false;
        $this->variant1->save();
        $this->setInventory($this->store, $this->productWithVariants, 100);

        Auth::login($this->cashier);
        $service = app(\App\Services\SaleService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not active');

        $service->checkout($this->checkoutData([
            ['product_id' => $this->productWithVariants->id, 'variant_id' => $this->variant1->id, 'quantity' => 1],
        ]));
    }

    public function test_checkout_variant_snapshots_sku(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->productWithVariants, 100);

        Auth::login($this->cashier);
        $service = app(\App\Services\SaleService::class);

        $sale = $service->checkout($this->checkoutData([
            ['product_id' => $this->productWithVariants->id, 'variant_id' => $this->variant1->id, 'quantity' => 1],
        ]));

        $item = $sale->items->first();
        $this->assertEquals($this->variant1->sku, $item->sku);
    }

    public function test_checkout_without_variant_for_non_variant_product_works(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->product, 100);

        Auth::login($this->cashier);
        $service = app(\App\Services\SaleService::class);

        $sale = $service->checkout($this->checkoutData([
            ['product_id' => $this->product->id, 'quantity' => 1],
        ]));

        $item = $sale->items->first();
        $this->assertNull($item->variant_id);
        $this->assertEquals(10000, (float) $item->unit_price);
    }
}
