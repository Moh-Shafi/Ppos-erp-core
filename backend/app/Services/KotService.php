<?php

namespace App\Services;

use App\Models\KotHeader;
use App\Models\KotItem;
use App\Models\Sale;
use Illuminate\Support\Facades\Auth;

class KotService
{
    public function generateFromSale(Sale $sale): ?KotHeader
    {
        $existingKot = KotHeader::withoutTenantScope()
            ->where('sale_id', $sale->id)
            ->first();

        if ($existingKot) {
            return $existingKot;
        }

        $kotNumber = $this->generateKotNumber($sale->tenant_id, $sale->store_id);

        $kot = KotHeader::create([
            'tenant_id' => $sale->tenant_id,
            'store_id' => $sale->store_id,
            'sale_id' => $sale->id,
            'table_id' => $sale->table_id,
            'kot_number' => $kotNumber,
            'status' => 'new',
            'priority' => 'normal',
            'created_by' => Auth::id() ?? $sale->cashier_id,
        ]);

        foreach ($sale->items as $item) {
            KotItem::create([
                'kot_header_id' => $kot->id,
                'sale_item_id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'modifiers' => $item->metadata['modifiers'] ?? null,
                'notes' => $item->metadata['notes'] ?? null,
                'status' => 'queued',
            ]);
        }

        return $kot->fresh(['items']);
    }

    public function updateStatus(int $kotId, string $status): KotHeader
    {
        $kot = KotHeader::findOrFail($kotId);

        $validTransitions = [
            'new' => ['preparing', 'cancelled'],
            'preparing' => ['ready', 'cancelled'],
            'ready' => ['served'],
        ];

        if (!isset($validTransitions[$kot->status]) || !in_array($status, $validTransitions[$kot->status])) {
            throw new \DomainException("Cannot transition KOT from '{$kot->status}' to '{$status}'");
        }

        $kot->status = $status;
        $kot->save();

        if ($status === 'preparing') {
            $kot->items()->where('status', 'queued')->update(['status' => 'preparing']);
        } elseif ($status === 'ready') {
            $kot->items()->where('status', 'preparing')->update(['status' => 'ready']);
        } elseif ($status === 'served') {
            $kot->items()->where('status', 'ready')->update(['status' => 'served']);
        }

        return $kot->fresh(['items']);
    }

    public function updateItemStatus(int $itemId, string $status): KotItem
    {
        $item = KotItem::findOrFail($itemId);
        $item->status = $status;
        $item->save();
        return $item;
    }

    public function getKdsQueue(int $storeId): array
    {
        $kots = KotHeader::withoutTenantScope()
            ->where('store_id', $storeId)
            ->whereIn('status', ['new', 'preparing', 'ready'])
            ->orderByRaw("CASE WHEN priority = 'rush' THEN 0 ELSE 1 END")
            ->orderBy('created_at')
            ->with(['items.product', 'table'])
            ->get();

        return [
            'new' => $kots->where('status', 'new')->values(),
            'preparing' => $kots->where('status', 'preparing')->values(),
            'ready' => $kots->where('status', 'ready')->values(),
        ];
    }

    private function generateKotNumber(int $tenantId, int $storeId): string
    {
        $date = now()->format('Ymd');
        $prefix = "KOT-{$date}-";

        $last = KotHeader::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('kot_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last->kot_number);
            $seq = (int) end($parts) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
