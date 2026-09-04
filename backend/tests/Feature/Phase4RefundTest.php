<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Sale;
use App\Models\SaleRefund;
use Illuminate\Support\Facades\Auth;

class Phase4RefundTest extends Phase4TestHelper
{
    private function createSale(): Sale
    {
        $this->setInventory($this->store, $this->product, 100);
        $this->setInventory($this->store, $this->productWithVariants, 100);

        Auth::login($this->cashier);
        $service = app(\App\Services\SaleService::class);

        return $service->checkout($this->checkoutData([
            ['product_id' => $this->product->id, 'quantity' => 2],
            ['product_id' => $this->productWithVariants->id, 'variant_id' => $this->variant1->id, 'quantity' => 1],
        ]));
    }

    public function test_full_refund_restores_inventory(): void
    {
        $this->setupPhase4();
        $sale = $this->createSale();

        $inv1Before = Inventory::withoutTenantScope()
            ->where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->first();
        $this->assertEquals(98, $inv1Before->quantity);

        Auth::login($this->cashier);
        $refundService = app(\App\Services\RefundService::class);
        $refund = $refundService->fullRefund($sale, 'Customer returned all items');

        $this->assertEquals('full', $refund->type);
        $this->assertEquals('completed', $refund->status);

        $inv1After = Inventory::withoutTenantScope()
            ->where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->first();
        $this->assertEquals(100, $inv1After->quantity);

        $inv2After = Inventory::withoutTenantScope()
            ->where('store_id', $this->store->id)
            ->where('product_id', $this->productWithVariants->id)
            ->first();
        $this->assertEquals(100, $inv2After->quantity);
    }

    public function test_full_refund_updates_sale_status(): void
    {
        $this->setupPhase4();
        $sale = $this->createSale();

        Auth::login($this->cashier);
        $refundService = app(\App\Services\RefundService::class);
        $refundService->fullRefund($sale);

        $sale = Sale::withoutTenantScope()->find($sale->id);
        $this->assertEquals('refunded', $sale->status);
        $this->assertEquals('full', $sale->refund_status);
        $this->assertEquals($sale->total, $sale->refunded_amount);
    }

    public function test_full_refund_creates_refund_items(): void
    {
        $this->setupPhase4();
        $sale = $this->createSale();

        Auth::login($this->cashier);
        $refundService = app(\App\Services\RefundService::class);
        $refund = $refundService->fullRefund($sale);

        $this->assertCount(2, $refund->items);
    }

    public function test_full_refund_on_already_refunded_sale_rejected(): void
    {
        $this->setupPhase4();
        $sale = $this->createSale();

        Auth::login($this->cashier);
        $refundService = app(\App\Services\RefundService::class);
        $refundService->fullRefund($sale);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only completed sales');
        $refundService->fullRefund($sale);
    }

    public function test_full_refund_on_non_completed_sale_rejected(): void
    {
        $this->setupPhase4();
        $sale = $this->createSale();

        Auth::login($this->cashier);
        $saleService = app(\App\Services\SaleService::class);
        $saleService->cancel($sale);

        $refundService = app(\App\Services\RefundService::class);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only completed sales');
        $refundService->fullRefund($sale);
    }

    public function test_partial_refund_restores_some_inventory(): void
    {
        $this->setupPhase4();
        $sale = $this->createSale();

        $saleItem = $sale->items()->where('product_id', $this->product->id)->first();

        Auth::login($this->cashier);
        $refundService = app(\App\Services\RefundService::class);
        $refund = $refundService->partialRefund($sale, [
            ['sale_item_id' => $saleItem->id, 'quantity' => 1],
        ], 'One item defective');

        $this->assertEquals('partial', $refund->type);
        $this->assertEquals(1, $refund->items->first()->quantity);

        $inv = Inventory::withoutTenantScope()
            ->where('store_id', $this->store->id)
            ->where('product_id', $this->product->id)
            ->first();
        // Was 98 (2 sold), refund 1 → 99
        $this->assertEquals(99, $inv->quantity);
    }

    public function test_partial_refund_updates_sale_refund_status(): void
    {
        $this->setupPhase4();
        $sale = $this->createSale();

        $saleItem = $sale->items()->where('product_id', $this->product->id)->first();

        Auth::login($this->cashier);
        $refundService = app(\App\Services\RefundService::class);
        $refundService->partialRefund($sale, [
            ['sale_item_id' => $saleItem->id, 'quantity' => 1],
        ]);

        $sale = Sale::withoutTenantScope()->find($sale->id);
        $this->assertEquals('partial', $sale->refund_status);
        $this->assertEquals('completed', $sale->status);
        $this->assertGreaterThan(0, (float) $sale->refunded_amount);
    }

    public function test_partial_refund_exceeding_quantity_rejected(): void
    {
        $this->setupPhase4();
        $sale = $this->createSale();

        $saleItem = $sale->items()->where('product_id', $this->product->id)->first();

        Auth::login($this->cashier);
        $refundService = app(\App\Services\RefundService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('exceeds remaining');
        $refundService->partialRefund($sale, [
            ['sale_item_id' => $saleItem->id, 'quantity' => 999],
        ]);
    }

    public function test_partial_refund_all_items_becomes_full(): void
    {
        $this->setupPhase4();
        $sale = $this->createSale();

        Auth::login($this->cashier);
        $refundService = app(\App\Services\RefundService::class);

        $items = [];
        foreach ($sale->items as $item) {
            $items[] = ['sale_item_id' => $item->id, 'quantity' => $item->quantity];
        }

        $refund = $refundService->partialRefund($sale, $items, 'All items returned');

        $sale = Sale::withoutTenantScope()->find($sale->id);
        $this->assertEquals('full', $sale->refund_status);
        $this->assertEquals('refunded', $sale->status);
    }

    public function test_refund_is_atomic_rollback_on_error(): void
    {
        $this->setupPhase4();
        $sale = $this->createSale();

        Auth::login($this->cashier);
        $refundService = app(\App\Services\RefundService::class);

        // Try to refund with invalid sale item
        $this->expectException(\DomainException::class);
        $refundService->partialRefund($sale, [
            ['sale_item_id' => 999999, 'quantity' => 1],
        ]);

        // Sale should be unchanged
        $sale = Sale::withoutTenantScope()->find($sale->id);
        $this->assertEquals('completed', $sale->status);
        $this->assertEquals('none', $sale->refund_status);
    }
}
