<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\HeldSale;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HoldSaleService
{
    public function __construct(
        private AuditService $auditService,
    ) {}

    public function list(int $tenantId, int $storeId, ?string $status = 'held', int $perPage = 20)
    {
        $query = HeldSale::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->with(['cashier:id,name', 'customer:id,name'])
            ->orderByDesc('held_at')
            ->paginate($perPage);
    }

    public function hold(array $data): HeldSale
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $store = Store::withoutTenantScope()->find($data['store_id']);
        if (!$store || $store->tenant_id !== $tenantId) {
            throw new \DomainException('Store does not belong to your tenant');
        }

        $customerId = null;
        if (!empty($data['customer_id'])) {
            $customer = Customer::withoutTenantScope()->find($data['customer_id']);
            if (!$customer || $customer->tenant_id !== $tenantId) {
                throw new \DomainException('Customer does not belong to your tenant');
            }
            $customerId = $customer->id;
        }

        $cartData = $data['cart_data'];
        if (empty($cartData['items'])) {
            throw new \DomainException('Cart must have at least one item');
        }

        $heldSale = new HeldSale;
        $heldSale->tenant_id = $tenantId;
        $heldSale->store_id = $store->id;
        $heldSale->cashier_id = $user->id;
        $heldSale->customer_id = $customerId;
        $heldSale->cart_data = $cartData;
        $heldSale->hold_number = $this->generateHoldNumber($tenantId);
        $heldSale->status = 'held';
        $heldSale->held_at = now();
        $heldSale->expires_at = now()->addDay();
        $heldSale->save();

        $this->auditService->log(
            'pos.hold_sale',
            'held_sale',
            $heldSale->id,
            null,
            ['hold_number' => $heldSale->hold_number, 'store_id' => $store->id],
            tenantId: $tenantId,
        );

        return $heldSale->fresh(['cashier:id,name', 'customer:id,name']);
    }

    public function recall(int $heldSaleId): HeldSale
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        return DB::transaction(function () use ($heldSaleId, $tenantId) {
            $heldSale = HeldSale::withoutTenantScope()
                ->where('id', $heldSaleId)
                ->lockForUpdate()
                ->first();

            if (!$heldSale || $heldSale->tenant_id !== $tenantId) {
                throw new \DomainException('Held sale not found');
            }

            if ($heldSale->status !== 'held') {
                throw new \DomainException("Held sale cannot be recalled (status: {$heldSale->status})");
            }

            if ($heldSale->expires_at && $heldSale->expires_at < now()) {
                $heldSale->status = 'expired';
                $heldSale->save();
                throw new \DomainException('Held sale has expired');
            }

            $heldSale->status = 'recalled';
            $heldSale->recalled_at = now();
            $heldSale->save();

            $this->auditService->log(
                'pos.recall_sale',
                'held_sale',
                $heldSale->id,
                null,
                ['hold_number' => $heldSale->hold_number],
                tenantId: $tenantId,
            );

            return $heldSale->fresh(['cashier:id,name', 'customer:id,name']);
        });
    }

    public function delete(int $heldSaleId): void
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $heldSale = HeldSale::withoutTenantScope()
            ->where('id', $heldSaleId)
            ->first();

        if (!$heldSale || $heldSale->tenant_id !== $tenantId) {
            throw new \DomainException('Held sale not found');
        }

        $holdNumber = $heldSale->hold_number;
        $heldSale->delete();

        $this->auditService->log(
            'pos.hold_sale_deleted',
            'held_sale',
            $heldSaleId,
            null,
            ['hold_number' => $holdNumber],
            tenantId: $tenantId,
        );
    }

    public function processExpiry(int $tenantId): int
    {
        $expired = HeldSale::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('status', 'held')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $heldSale) {
            $heldSale->status = 'expired';
            $heldSale->save();
        }

        return $expired->count();
    }

    private function generateHoldNumber(int $tenantId): string
    {
        $date = now()->format('Ymd');
        $prefix = "HOLD-{$date}-";

        $last = HeldSale::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('hold_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last->hold_number);
            $seq = (int) end($parts) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
