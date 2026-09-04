<?php

namespace Tests\Feature;

use App\Models\GoodsReceiptNote;
use App\Models\Inventory;
use App\Models\Purchase;
use App\Services\GrnService;
use App\Services\InventoryService;
use App\Services\PurchaseService;
use Illuminate\Support\Facades\Auth;

class Phase3GrnTest extends Phase3TestHelper
{
    private GrnService $grnService;
    private PurchaseService $purchaseService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupPhase3();
        $this->grnService = new GrnService();
        $this->purchaseService = new PurchaseService(new InventoryService());
    }

    private function createOrderedPurchase(): Purchase
    {
        Auth::login($this->owner);
        $supplier = $this->createSupplier();
        $product = $this->createProduct();

        $purchase = $this->purchaseService->create([
            'supplier_id' => $supplier->id,
            'store_id' => $this->store->id,
            'purchase_date' => now()->format('Y-m-d'),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 50, 'unit_cost' => 5000],
            ],
        ]);

        $purchase = $this->purchaseService->order($purchase);
        return $purchase->fresh();
    }

    public function test_create_grn_from_po(): void
    {
        $purchase = $this->createOrderedPurchase();

        $grn = $this->grnService->createFromPo($purchase);

        $this->assertEquals('draft', $grn->status);
        $this->assertEquals($purchase->id, $grn->purchase_id);
        $this->assertEquals(1, $grn->items->count());
        $this->assertEquals(50, $grn->items->first()->quantity_ordered);
    }

    public function test_create_standalone_grn(): void
    {
        Auth::login($this->owner);
        $supplier = $this->createSupplier();
        $product = $this->createProduct();

        $grn = $this->grnService->create([
            'store_id' => $this->store->id,
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'unit_cost' => 5000],
            ],
        ]);

        $this->assertEquals('draft', $grn->status);
        $this->assertNull($grn->purchase_id);
    }

    public function test_receive_grn_full(): void
    {
        $purchase = $this->createOrderedPurchase();
        $grn = $this->grnService->createFromPo($purchase);

        Auth::login($this->owner);
        $received = $this->grnService->receive($grn, [
            ['id' => $grn->items->first()->id, 'quantity_received' => 50],
        ]);

        $this->assertEquals('received', $received->status);

        $inventory = Inventory::where('store_id', $this->store->id)
            ->where('product_id', $purchase->items->first()->product_id)
            ->first();
        $this->assertNotNull($inventory);
        $this->assertEquals(50, $inventory->quantity);
    }

    public function test_receive_grn_partial(): void
    {
        $purchase = $this->createOrderedPurchase();
        $grn = $this->grnService->createFromPo($purchase);

        Auth::login($this->owner);
        $received = $this->grnService->receive($grn, [
            ['id' => $grn->items->first()->id, 'quantity_received' => 30, 'quantity_rejected' => 5],
        ]);

        $this->assertEquals('received', $received->status);

        $inventory = Inventory::where('store_id', $this->store->id)
            ->where('product_id', $purchase->items->first()->product_id)
            ->first();
        $this->assertEquals(30, $inventory->quantity);
    }

    public function test_cancel_draft_grn(): void
    {
        $purchase = $this->createOrderedPurchase();
        $grn = $this->grnService->createFromPo($purchase);

        $cancelled = $this->grnService->cancel($grn);

        $this->assertEquals('cancelled', $cancelled->status);
    }

    public function test_cannot_receive_cancelled_grn(): void
    {
        $purchase = $this->createOrderedPurchase();
        $grn = $this->grnService->createFromPo($purchase);
        $this->grnService->cancel($grn);

        Auth::login($this->owner);
        $this->expectException(\DomainException::class);
        $this->grnService->receive($grn, [
            ['id' => $grn->items->first()->id, 'quantity_received' => 50],
        ]);
    }

    public function test_cannot_receive_already_received(): void
    {
        $purchase = $this->createOrderedPurchase();
        $grn = $this->grnService->createFromPo($purchase);

        Auth::login($this->owner);
        $this->grnService->receive($grn, [
            ['id' => $grn->items->first()->id, 'quantity_received' => 50],
        ]);

        $this->expectException(\DomainException::class);
        $this->grnService->receive($grn, [
            ['id' => $grn->items->first()->id, 'quantity_received' => 50],
        ]);
    }

    public function test_po_status_updated_on_receive(): void
    {
        $purchase = $this->createOrderedPurchase();
        $grn = $this->grnService->createFromPo($purchase);

        Auth::login($this->owner);
        $this->grnService->receive($grn, [
            ['id' => $grn->items->first()->id, 'quantity_received' => 50],
        ]);

        $purchase->refresh();
        $this->assertEquals('received', $purchase->status);
    }

    public function test_grn_api(): void
    {
        $purchase = $this->createOrderedPurchase();
        $this->grnService->createFromPo($purchase);

        $response = $this->withHeaders($this->authHeader($this->tokenOwner))
            ->getJson('/api/v1/grns');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'current_page', 'total']);
    }
}
