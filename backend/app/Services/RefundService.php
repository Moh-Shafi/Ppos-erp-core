<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleRefund;
use App\Models\SaleRefundItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RefundService
{
    public function __construct(
        private InventoryService $inventoryService,
        private PaymentService $paymentService,
        private ModuleService $moduleService,
        private CustomerCreditService $creditService,
        private AuditService $auditService,
    ) {}

    /**
     * Process a full refund: restores all inventory, refunds all payments,
     * marks sale as refunded. Atomic transaction.
     */
    public function fullRefund(Sale $sale, ?string $reason = null): SaleRefund
    {
        if ($sale->status !== 'completed') {
            throw new \DomainException('Only completed sales can be refunded');
        }

        if ($sale->refund_status === 'full') {
            throw new \DomainException('Sale is already fully refunded');
        }

        return DB::transaction(function () use ($sale, $reason) {
            $sale = Sale::withoutTenantScope()
                ->where('id', $sale->id)
                ->lockForUpdate()
                ->first();

            if ($sale->status !== 'completed') {
                throw new \DomainException('Only completed sales can be refunded');
            }

            if ($sale->refund_status === 'full') {
                throw new \DomainException('Sale is already fully refunded');
            }

            $sale->load(['items.product', 'items.variant', 'store', 'payments']);

            $refundAmount = (float) $sale->total - (float) $sale->refunded_amount;

            $refund = new SaleRefund;
            $refund->tenant_id = $sale->tenant_id;
            $refund->sale_id = $sale->id;
            $refund->refunded_by = Auth::id();
            $refund->type = 'full';
            $refund->refund_reason = $reason;
            $refund->refund_amount = $refundAmount;
            $refund->status = 'completed';
            $refund->refunded_at = now();
            $refund->save();

            foreach ($sale->items as $item) {
                SaleRefundItem::create([
                    'sale_refund_id' => $refund->id,
                    'sale_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'refund_amount' => (float) $item->total,
                ]);

                $this->inventoryService->increase(
                    $sale->store,
                    $item->product,
                    $item->quantity,
                    'sale_return',
                    $sale,
                    "Full refund {$sale->sale_number}",
                );
            }

            $this->paymentService->refundPayments($sale, $sale->tenant_id);

            $sale->refund_status = 'full';
            $sale->refunded_amount = $refundAmount;
            $sale->status = 'refunded';
            $sale->save();

            $this->creditRefundAdjustment($sale, $refundAmount);

            $this->auditService->log(
                'pos.refund.full',
                'sale',
                $sale->id,
                null,
                ['refund_id' => $refund->id, 'amount' => $refundAmount, 'reason' => $reason],
                tenantId: $sale->tenant_id,
            );

            return $refund->fresh(['items', 'refundedBy']);
        });
    }

    /**
     * Process a partial refund: restores specific items, adjusts sale totals.
     * Atomic transaction.
     *
     * @param  array  $items  Array of ['sale_item_id' => int, 'quantity' => int]
     */
    public function partialRefund(Sale $sale, array $items, ?string $reason = null): SaleRefund
    {
        if ($sale->status !== 'completed') {
            throw new \DomainException('Only completed sales can be refunded');
        }

        if ($sale->refund_status === 'full') {
            throw new \DomainException('Sale is already fully refunded');
        }

        if (empty($items)) {
            throw new \DomainException('At least one item is required for partial refund');
        }

        return DB::transaction(function () use ($sale, $items, $reason) {
            $sale = Sale::withoutTenantScope()
                ->where('id', $sale->id)
                ->lockForUpdate()
                ->first();

            if ($sale->status !== 'completed') {
                throw new \DomainException('Only completed sales can be refunded');
            }

            if ($sale->refund_status === 'full') {
                throw new \DomainException('Sale is already fully refunded');
            }

            $sale->load(['items.product', 'items.variant', 'store', 'payments']);

            $saleItems = $sale->items->keyBy('id');

            $refundAmount = 0;
            $refundItemsData = [];

            foreach ($items as $ri) {
                $saleItem = $saleItems->get($ri['sale_item_id']);
                if (!$saleItem) {
                    throw new \DomainException("Sale item {$ri['sale_item_id']} not found in this sale");
                }

                $refundQty = (int) $ri['quantity'];
                if ($refundQty <= 0) {
                    throw new \DomainException('Refund quantity must be greater than 0');
                }

                $alreadyRefundedQty = SaleRefundItem::whereHas('saleRefund', function ($q) use ($sale) {
                    $q->where('sale_id', $sale->id)->where('status', 'completed');
                })->where('sale_item_id', $saleItem->id)->sum('quantity');

                $remainingQty = $saleItem->quantity - $alreadyRefundedQty;
                if ($refundQty > $remainingQty) {
                    throw new \DomainException(
                        "Refund quantity {$refundQty} exceeds remaining quantity {$remainingQty} for item {$saleItem->product_name}"
                    );
                }

                $itemRefundAmount = (float) $saleItem->unit_price * $refundQty;
                $refundAmount += $itemRefundAmount;

                $refundItemsData[] = [
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'quantity' => $refundQty,
                    'unit_price' => $saleItem->unit_price,
                    'refund_amount' => $itemRefundAmount,
                ];

                $this->inventoryService->increase(
                    $sale->store,
                    $saleItem->product,
                    $refundQty,
                    'sale_return',
                    $sale,
                    "Partial refund {$sale->sale_number}",
                );
            }

            $refund = new SaleRefund;
            $refund->tenant_id = $sale->tenant_id;
            $refund->sale_id = $sale->id;
            $refund->refunded_by = Auth::id();
            $refund->type = 'partial';
            $refund->refund_reason = $reason;
            $refund->refund_amount = $refundAmount;
            $refund->status = 'completed';
            $refund->refunded_at = now();
            $refund->save();

            foreach ($refundItemsData as $riData) {
                SaleRefundItem::create(array_merge(
                    ['sale_refund_id' => $refund->id],
                    $riData
                ));
            }

            $newRefundedAmount = (float) $sale->refunded_amount + $refundAmount;

            $allItemsFullyRefunded = true;
            foreach ($sale->items as $item) {
                $totalRefundedForItem = SaleRefundItem::whereHas('saleRefund', function ($q) use ($sale) {
                    $q->where('sale_id', $sale->id)->where('status', 'completed');
                })->where('sale_item_id', $item->id)->sum('quantity');

                if ($totalRefundedForItem < $item->quantity) {
                    $allItemsFullyRefunded = false;
                    break;
                }
            }

            if ($allItemsFullyRefunded) {
                $sale->refund_status = 'full';
                $sale->status = 'refunded';
                $this->paymentService->refundPayments($sale, $sale->tenant_id);
            } else {
                $sale->refund_status = 'partial';
            }

            $sale->refunded_amount = $newRefundedAmount;
            $sale->save();

            $this->creditRefundAdjustment($sale, $refundAmount);

            $this->auditService->log(
                'pos.refund.partial',
                'sale',
                $sale->id,
                null,
                ['refund_id' => $refund->id, 'amount' => $refundAmount, 'items' => $items, 'reason' => $reason],
                tenantId: $sale->tenant_id,
            );

            return $refund->fresh(['items', 'refundedBy']);
        });
    }

    /**
     * If customer credit feature is enabled and the sale had an outstanding
     * debit, reduce the customer's outstanding balance by the refund amount.
     */
    private function creditRefundAdjustment(Sale $sale, float $refundAmount): void
    {
        if (!$sale->customer_id) {
            return;
        }

        if (!$this->moduleService->isFeatureEnabled($sale->tenant_id, 'sales.customer_credit')) {
            return;
        }

        $customer = Customer::withoutTenantScope()->find($sale->customer_id);
        if (!$customer) {
            return;
        }

        $this->creditService->addCredit($customer, $refundAmount, 'sale_refund', $sale->id);
    }
}
