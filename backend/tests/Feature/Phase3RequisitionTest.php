<?php

namespace Tests\Feature;

use App\Models\PurchaseRequisition;
use App\Services\RequisitionService;
use Illuminate\Support\Facades\Auth;

class Phase3RequisitionTest extends Phase3TestHelper
{
    private RequisitionService $requisitionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupPhase3();
        $this->requisitionService = new RequisitionService();
    }

    private function createRequisitionAsManager(): PurchaseRequisition
    {
        Auth::login($this->manager);
        $product = $this->createProduct();

        return $this->requisitionService->create([
            'store_id' => $this->store->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10, 'estimated_cost' => 5000],
            ],
        ]);
    }

    public function test_create_requisition(): void
    {
        $req = $this->createRequisitionAsManager();

        $this->assertEquals('draft', $req->status);
        $this->assertNotEmpty($req->request_number);
        $this->assertEquals(1, $req->items->count());
    }

    public function test_submit_requisition(): void
    {
        $req = $this->createRequisitionAsManager();

        $submitted = $this->requisitionService->submit($req);

        $this->assertEquals('pending', $submitted->status);
    }

    public function test_approve_requisition(): void
    {
        $req = $this->createRequisitionAsManager();
        $this->requisitionService->submit($req);

        Auth::login($this->owner);
        $approved = $this->requisitionService->approve($req);

        $this->assertEquals('approved', $approved->status);
        $this->assertEquals($this->owner->id, $approved->approved_by);
    }

    public function test_reject_requisition(): void
    {
        $req = $this->createRequisitionAsManager();
        $this->requisitionService->submit($req);

        Auth::login($this->owner);
        $rejected = $this->requisitionService->reject($req, 'Not needed');

        $this->assertEquals('rejected', $rejected->status);
        $this->assertEquals('Not needed', $rejected->rejection_reason);
    }

    public function test_cancel_requisition(): void
    {
        $req = $this->createRequisitionAsManager();

        $cancelled = $this->requisitionService->cancel($req);

        $this->assertEquals('cancelled', $cancelled->status);
    }

    public function test_cannot_approve_own_requisition(): void
    {
        $req = $this->createRequisitionAsManager();
        $this->requisitionService->submit($req);

        Auth::login($this->manager);
        $this->expectException(\DomainException::class);
        $this->requisitionService->approve($req);
    }

    public function test_cannot_submit_non_draft(): void
    {
        $req = $this->createRequisitionAsManager();
        $this->requisitionService->submit($req);

        $this->expectException(\DomainException::class);
        $this->requisitionService->submit($req);
    }

    public function test_cannot_delete_non_draft(): void
    {
        $req = $this->createRequisitionAsManager();
        $this->requisitionService->submit($req);

        $this->expectException(\DomainException::class);
        $this->requisitionService->delete($req);
    }

    public function test_requisition_api(): void
    {
        $this->createRequisitionAsManager();

        $response = $this->withHeaders($this->authHeader($this->tokenOwner))
            ->getJson('/api/v1/requisitions');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'current_page', 'total']);
    }

    public function test_requisition_create_api(): void
    {
        $product = $this->createProduct();

        $response = $this->withHeaders($this->authHeader($this->tokenManager))
            ->postJson('/api/v1/requisitions', [
                'store_id' => $this->store->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 20],
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJson(['status' => 'draft']);
    }
}
