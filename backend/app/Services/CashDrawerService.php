<?php

namespace App\Services;

use App\Models\CashDrawerSession;
use App\Models\Payment;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CashDrawerService
{
    /**
     * Open a new cash drawer session.
     */
    public function open(int $tenantId, array $data): CashDrawerSession
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $existing = CashDrawerSession::where('tenant_id', $tenantId)
                ->where('store_id', $data['store_id'])
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new \DomainException('Another session is already open for this store');
            }

            $session = new CashDrawerSession();
            $session->tenant_id = $tenantId;
            $session->store_id = $data['store_id'];
            $session->user_id = Auth::id();
            $session->opening_amount = $data['opening_amount'];
            $session->status = 'open';
            $session->opened_at = now();
            $session->notes = $data['notes'] ?? null;
            $session->save();

            return $session;
        });
    }

    /**
     * Close a cash drawer session.
     */
    public function close(CashDrawerSession $session, array $data): CashDrawerSession
    {
        if ($session->status !== 'open') {
            throw new \DomainException('Session is not open');
        }

        return DB::transaction(function () use ($session, $data) {
            $session = CashDrawerSession::lockForUpdate()->find($session->id);

            $expected = $this->calculateExpectedAmount($session);

            $session->closing_amount = $data['closing_amount'];
            $session->expected_amount = $expected;
            $session->difference = $data['closing_amount'] - $expected;
            $session->status = 'closed';
            $session->closed_at = now();
            $session->notes = $data['notes'] ?? $session->notes;
            $session->save();

            return $session;
        });
    }

    /**
     * Reconcile a closed session.
     */
    public function reconcile(CashDrawerSession $session, string $notes = null): CashDrawerSession
    {
        if ($session->status !== 'closed') {
            throw new \DomainException('Session must be closed before reconciliation');
        }

        $session->status = 'reconciled';
        if ($notes) {
            $session->notes = $notes;
        }
        $session->save();

        return $session;
    }

    /**
     * Record a cash payment against the active drawer session.
     */
    public function recordCashPayment(int $tenantId, int $storeId, float $amount, int $paymentId = null): ?CashDrawerSession
    {
        $session = CashDrawerSession::where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('status', 'open')
            ->first();

        if (!$session) {
            return null;
        }

        $session->touch();

        return $session;
    }

    /**
     * Calculate expected amount for a session.
     */
    public function calculateExpectedAmount(CashDrawerSession $session): float
    {
        $cashSales = (float) Payment::withoutTenantScope()
            ->where('sale_id', function ($q) use ($session) {
                $q->select('id')
                    ->from('sales')
                    ->where('tenant_id', $session->tenant_id)
                    ->where('store_id', $session->store_id)
                    ->where('payment_method', 'cash')
                    ->where('created_at', '>=', $session->opened_at)
                    ->when($session->closed_at, function ($q) use ($session) {
                        $q->where('created_at', '<=', $session->closed_at);
                    });
            })
            ->where('status', 'success')
            ->where('payment_method', 'cash')
            ->sum('amount');

        $cashRefunds = (float) Payment::withoutTenantScope()
            ->where('tenant_id', $session->tenant_id)
            ->where('payment_method', 'cash')
            ->where('refund_status', '!=', 'none')
            ->where('updated_at', '>=', $session->opened_at)
            ->when($session->closed_at, function ($q) use ($session) {
                $q->where('updated_at', '<=', $session->closed_at);
            })
            ->sum('refund_amount');

        return (float) $session->opening_amount + $cashSales - $cashRefunds;
    }
}
