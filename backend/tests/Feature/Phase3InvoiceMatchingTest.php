<?php

namespace Tests\Feature;

use App\Models\SupplierInvoice;
use App\Services\GrnService;
use App\Services\InventoryService;
use App\Services\InvoiceMatchingService;
use App\Services\PurchaseService;
use Illuminate\Support\Facades\Auth;

class Phase3InvoiceMatchingTest extends Phase3TestHelper
{
    private InvoiceMatchingService $invoiceService;
    private GrnService $grnService;
    private PurchaseService $purchaseService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupPhase3();
        $this->invoiceService = new InvoiceMatchingService();
        $this->grnService = new GrnService();
        $this->purchaseService = new PurchaseService(new InventoryService());
    }

    private function createMatchedData(): array
    {
        Auth::login($this->owner);
        $supplier = $this->createSupplier();
        $product = $this->createProduct();

        $purchase = $this->purchaseService->create([
            'supplier_id' => $supplier->id,
            'store_id' => $this->store->id,
            'purchase_date' => now()->format('Y-m-d'),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 5000],
            ],
        ]);
        $purchase = $this->purchaseService->order($purchase);
        $purchase = $purchase->fresh();

        $grn = $this->grnService->createFromPo($purchase);
        $this->grnService->receive($grn, [
            ['id' => $grn->items->first()->id, 'quantity_received' => 10],
        ]);
        $grn = $grn->fresh();

        return [$purchase, $grn, $supplier];
    }

    public function test_create_invoice(): void
    {
        Auth::login($this->owner);
        $supplier = $this->createSupplier();

        $invoice = $this->invoiceService->create([
            'invoice_number' => 'INV-TEST-001',
            'supplier_id' => $supplier->id,
            'subtotal' => 50000,
            'tax' => 5000,
            'total' => 55000,
            'invoice_date' => now()->format('Y-m-d'),
        ]);

        $this->assertEquals('pending', $invoice->status);
    }

    public function test_3way_match_success(): void
    {
        [$purchase, $grn, $supplier] = $this->createMatchedData();

        Auth::login($this->owner);
        $invoice = $this->invoiceService->create([
            'invoice_number' => 'INV-TEST-002',
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchase->id,
            'grn_id' => $grn->id,
            'subtotal' => $purchase->subtotal,
            'tax' => $purchase->tax,
            'total' => $purchase->total,
            'invoice_date' => now()->format('Y-m-d'),
        ]);

        $matched = $this->invoiceService->match($invoice);

        $this->assertEquals('matched', $matched->status);
        $matchResult = $matched->match_result;
        $this->assertTrue($matchResult['quantity_match']);
        $this->assertTrue($matchResult['total_match']);
    }

    public function test_3way_match_quantity_mismatch(): void
    {
        [$purchase, $grn, $supplier] = $this->createMatchedData();

        Auth::login($this->owner);
        $invoice = $this->invoiceService->create([
            'invoice_number' => 'INV-TEST-003',
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchase->id,
            'grn_id' => $grn->id,
            'subtotal' => 999999,
            'tax' => 0,
            'total' => 999999,
            'invoice_date' => now()->format('Y-m-d'),
        ]);

        $matched = $this->invoiceService->match($invoice);

        $this->assertEquals('mismatched', $matched->status);
    }

    public function test_approve_invoice(): void
    {
        [$purchase, $grn, $supplier] = $this->createMatchedData();

        Auth::login($this->owner);
        $invoice = $this->invoiceService->create([
            'invoice_number' => 'INV-TEST-004',
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchase->id,
            'grn_id' => $grn->id,
            'subtotal' => $purchase->subtotal,
            'tax' => $purchase->tax,
            'total' => $purchase->total,
            'invoice_date' => now()->format('Y-m-d'),
        ]);
        $this->invoiceService->match($invoice);
        $invoice = $invoice->fresh();

        $approved = $this->invoiceService->approve($invoice);

        $this->assertEquals('approved', $approved->status);
    }

    public function test_reject_invoice(): void
    {
        [$purchase, $grn, $supplier] = $this->createMatchedData();

        Auth::login($this->owner);
        $invoice = $this->invoiceService->create([
            'invoice_number' => 'INV-TEST-005',
            'supplier_id' => $supplier->id,
            'purchase_id' => $purchase->id,
            'grn_id' => $grn->id,
            'subtotal' => $purchase->subtotal,
            'tax' => $purchase->tax,
            'total' => $purchase->total,
            'invoice_date' => now()->format('Y-m-d'),
        ]);
        $this->invoiceService->match($invoice);
        $invoice = $invoice->fresh();

        $rejected = $this->invoiceService->reject($invoice, 'Wrong total');

        $this->assertEquals('rejected', $rejected->status);
    }

    public function test_cannot_approve_pending_invoice(): void
    {
        Auth::login($this->owner);
        $supplier = $this->createSupplier();

        $invoice = $this->invoiceService->create([
            'invoice_number' => 'INV-TEST-006',
            'supplier_id' => $supplier->id,
            'subtotal' => 50000,
            'tax' => 0,
            'total' => 50000,
            'invoice_date' => now()->format('Y-m-d'),
        ]);

        $this->expectException(\DomainException::class);
        $this->invoiceService->approve($invoice);
    }

    public function test_invoice_api(): void
    {
        Auth::login($this->owner);
        $supplier = $this->createSupplier();
        $this->invoiceService->create([
            'invoice_number' => 'INV-TEST-007',
            'supplier_id' => $supplier->id,
            'subtotal' => 50000,
            'tax' => 0,
            'total' => 50000,
            'invoice_date' => now()->format('Y-m-d'),
        ]);

        $response = $this->withHeaders($this->authHeader($this->tokenOwner))
            ->getJson('/api/v1/supplier-invoices');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'current_page', 'total']);
    }
}
