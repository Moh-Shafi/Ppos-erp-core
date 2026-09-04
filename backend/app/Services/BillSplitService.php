<?php

namespace App\Services;

use App\Models\BillSplit;
use App\Models\BillSplitItem;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class BillSplitService
{
    public function create(int $saleId, string $splitType, array $data): array
    {
        $sale = Sale::with(['items'])->findOrFail($saleId);

        return DB::transaction(function () use ($sale, $splitType, $data) {
            $splits = [];

            switch ($splitType) {
                case 'equal':
                    $splits = $this->splitEqual($sale, $data['person_count']);
                    break;
                case 'per_item':
                    $splits = $this->splitPerItem($sale, $data['item_assignments']);
                    break;
                case 'per_person':
                    $splits = $this->splitPerPerson($sale, $data['person_items']);
                    break;
                case 'custom':
                    $splits = $this->splitCustom($sale, $data['custom_splits']);
                    break;
                default:
                    throw new \DomainException("Invalid split type: {$splitType}");
            }

            return $splits;
        });
    }

    protected function splitEqual(Sale $sale, int $personCount): array
    {
        $perPerson = round((float) $sale->total / $personCount, 2);
        $splits = [];

        for ($i = 0; $i < $personCount; $i++) {
            $amount = $i === $personCount - 1
                ? round((float) $sale->total - ($perPerson * ($personCount - 1)), 2)
                : $perPerson;

            $split = BillSplit::create([
                'sale_id' => $sale->id,
                'split_type' => 'equal',
                'total_amount' => $amount,
                'status' => 'pending',
            ]);

            $splits[] = $split;
        }

        return $splits;
    }

    protected function splitPerItem(Sale $sale, array $assignments): array
    {
        $splitAmounts = [];

        foreach ($assignments as $assignment) {
            $saleItem = $sale->items->firstWhere('id', $assignment['sale_item_id']);
            if (!$saleItem) {
                throw new \DomainException("Sale item {$assignment['sale_item_id']} not found");
            }

            $index = $assignment['bill_split_index'];
            if (!isset($splitAmounts[$index])) {
                $splitAmounts[$index] = 0;
            }
            $splitAmounts[$index] += (float) $saleItem->total;
        }

        $splits = [];
        foreach ($splitAmounts as $amount) {
            $split = BillSplit::create([
                'sale_id' => $sale->id,
                'split_type' => 'per_item',
                'total_amount' => $amount,
                'status' => 'pending',
            ]);
            $splits[] = $split;
        }

        return $splits;
    }

    protected function splitPerPerson(Sale $sale, array $personItems): array
    {
        $splits = [];

        foreach ($personItems as $person) {
            $amount = 0;
            foreach ($person['sale_item_ids'] as $itemId) {
                $saleItem = $sale->items->firstWhere('id', $itemId);
                if ($saleItem) {
                    $amount += (float) $saleItem->total;
                }
            }

            $split = BillSplit::create([
                'sale_id' => $sale->id,
                'split_type' => 'per_person',
                'total_amount' => $amount,
                'status' => 'pending',
            ]);

            if (!empty($person['customer_id'])) {
                BillSplitItem::create([
                    'bill_split_id' => $split->id,
                    'customer_id' => $person['customer_id'],
                    'amount' => $amount,
                ]);
            }

            $splits[] = $split;
        }

        return $splits;
    }

    protected function splitCustom(Sale $sale, array $customSplits): array
    {
        $splits = [];

        foreach ($customSplits as $cs) {
            $split = BillSplit::create([
                'sale_id' => $sale->id,
                'split_type' => 'custom',
                'total_amount' => $cs['amount'],
                'status' => 'pending',
            ]);

            BillSplitItem::create([
                'bill_split_id' => $split->id,
                'sale_item_id' => $cs['sale_item_id'] ?? null,
                'customer_id' => $cs['customer_id'] ?? null,
                'amount' => $cs['amount'],
            ]);

            $splits[] = $split;
        }

        return $splits;
    }

    public function processPayment(int $splitId, string $paymentMethod, float $amount): BillSplit
    {
        $split = BillSplit::findOrFail($splitId);

        if ($split->status === 'paid') {
            throw new \DomainException('This split is already paid');
        }

        $paymentService = app(PaymentService::class);

        DB::transaction(function () use ($split, $paymentMethod, $amount, $paymentService) {
            $paymentService->createForCheckout($split->sale, [
                ['payment_method' => $paymentMethod, 'amount' => $amount],
            ], $split->tenant_id);

            $split->status = 'paid';
            $split->save();
        });

        return $split->fresh(['items', 'sale']);
    }
}
