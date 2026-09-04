<?php

namespace App\Services;

use App\Models\StocktakeSession;
use App\Models\StocktakeItem;
use App\Models\Inventory;
use App\Models\Store;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StocktakeService
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
        private readonly InventoryService $inventoryService = new InventoryService(),
    ) {}

    public function createSession(int $storeId, int $tenantId, ?string $note = null): StocktakeSession
    {
        return DB::transaction(function () use ($storeId, $tenantId, $note) {
            $store = Store::where('tenant_id', $tenantId)->findOrFail($storeId);

            $count = StocktakeSession::where('tenant_id', $tenantId)->count() + 1;
            $sessionNumber = 'ST-' . str_pad($count, 4, '0', STR_PAD_LEFT);

            $session = new StocktakeSession;
            $session->tenant_id = $tenantId;
            $session->store_id = $storeId;
            $session->session_number = $sessionNumber;
            $session->status = 'draft';
            $session->created_by = Auth::id();
            $session->note = $note;
            $session->save();

            $inventories = Inventory::where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->get();

            foreach ($inventories as $inv) {
                $item = new StocktakeItem;
                $item->tenant_id = $tenantId;
                $item->stocktake_session_id = $session->id;
                $item->product_id = $inv->product_id;
                $item->system_quantity = $inv->quantity;
                $item->counted_quantity = null;
                $item->variance = null;
                $item->save();
            }

            $this->auditService->log('stocktake.created', 'stocktake_session', $session->id, null, $session->toArray(), tenantId: $tenantId);

            return $session->fresh(['items.product', 'store', 'createdBy']);
        });
    }

    public function startCounting(int $id, int $tenantId): StocktakeSession
    {
        return DB::transaction(function () use ($id, $tenantId) {
            $session = StocktakeSession::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->status !== 'draft') {
                throw new \InvalidArgumentException('Can only start counting from draft status', 422);
            }

            $session->update([
                'status' => 'counting',
                'started_at' => now(),
            ]);

            $this->auditService->log('stocktake.counting_started', 'stocktake_session', $session->id, null, ['status' => 'counting'], tenantId: $tenantId);

            return $session->fresh(['items.product']);
        });
    }

    public function updateItem(int $sessionId, int $itemId, int $countedQuantity, ?string $note, int $tenantId): StocktakeItem
    {
        return DB::transaction(function () use ($sessionId, $itemId, $countedQuantity, $note, $tenantId) {
            $session = StocktakeSession::where('tenant_id', $tenantId)->findOrFail($sessionId);

            if ($session->status !== 'counting') {
                throw new \InvalidArgumentException('Can only update items during counting phase', 422);
            }

            $item = StocktakeItem::where('tenant_id', $tenantId)
                ->where('stocktake_session_id', $sessionId)
                ->where('id', $itemId)
                ->firstOrFail();

            $item->update([
                'counted_quantity' => $countedQuantity,
                'variance' => $countedQuantity - $item->system_quantity,
                'note' => $note,
            ]);

            return $item->fresh(['product']);
        });
    }

    public function reconcile(int $id, int $tenantId): StocktakeSession
    {
        return DB::transaction(function () use ($id, $tenantId) {
            $session = StocktakeSession::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->status !== 'counting') {
                throw new \InvalidArgumentException('Can only reconcile from counting status', 422);
            }

            $uncounted = $session->items()->whereNull('counted_quantity')->count();
            if ($uncounted > 0) {
                throw new \InvalidArgumentException("{$uncounted} items have not been counted", 422);
            }

            $session->update(['status' => 'reconciling']);

            $this->auditService->log('stocktake.reconciled', 'stocktake_session', $session->id, null, ['status' => 'reconciling'], tenantId: $tenantId);

            return $session->fresh(['items.product']);
        });
    }

    public function post(int $id, int $reasonId, int $tenantId): StocktakeSession
    {
        return DB::transaction(function () use ($id, $reasonId, $tenantId) {
            $session = StocktakeSession::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->status !== 'reconciling') {
                throw new \InvalidArgumentException('Can only post from reconciling status', 422);
            }

            $store = Store::where('tenant_id', $tenantId)->findOrFail($session->store_id);
            $adjustmentsCreated = 0;

            foreach ($session->items as $item) {
                if ($item->variance !== 0 && $item->variance !== null) {
                    $product = \App\Models\Product::where('tenant_id', $tenantId)->findOrFail($item->product_id);
                    $this->inventoryService->adjust(
                        $store,
                        $product,
                        $item->variance,
                        null,
                        $item->note ?? "Stocktake {$session->session_number}",
                        null,
                        $reasonId,
                    );
                    $adjustmentsCreated++;
                }
            }

            $session->update([
                'status' => 'posted',
                'completed_at' => now(),
            ]);

            $this->auditService->log('stocktake.posted', 'stocktake_session', $session->id, null, [
                'adjustments_created' => $adjustmentsCreated,
                'session_number' => $session->session_number,
            ], tenantId: $tenantId);

            return $session->fresh(['items.product']);
        });
    }

    public function cancel(int $id, int $tenantId): StocktakeSession
    {
        return DB::transaction(function () use ($id, $tenantId) {
            $session = StocktakeSession::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($session->status, ['draft', 'counting'])) {
                throw new \InvalidArgumentException('Can only cancel from draft or counting status', 422);
            }

            $session->update(['status' => 'cancelled']);

            $this->auditService->log('stocktake.cancelled', 'stocktake_session', $session->id, null, null, tenantId: $tenantId);

            return $session->fresh();
        });
    }
}
