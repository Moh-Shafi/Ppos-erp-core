<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerLoyaltyPoints;
use App\Models\CustomerLoyaltyTransaction;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
    ) {}

    public function getBalance(Customer $customer): ?CustomerLoyaltyPoints
    {
        return $customer->loyaltyPoints;
    }

    public function earnPoints(Customer $customer, float $saleTotal, ?int $saleId = null): int
    {
        $tenantId = $customer->tenant_id;
        $earnRate = $this->getTenantSetting($tenantId, 'loyalty_earn_rate', 10000);

        if ($earnRate <= 0) {
            return 0;
        }

        $points = (int) floor($saleTotal / $earnRate);

        if ($points <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($customer, $points, $saleId, $tenantId) {
            $loyalty = CustomerLoyaltyPoints::firstOrCreate(
                ['tenant_id' => $tenantId, 'customer_id' => $customer->id],
                ['points_balance' => 0, 'total_earned' => 0, 'total_redeemed' => 0],
            );

            $loyalty->points_balance += $points;
            $loyalty->total_earned += $points;
            $loyalty->save();

            CustomerLoyaltyTransaction::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'points' => $points,
                'type' => 'earn',
                'source' => 'sale',
                'reference_type' => 'sale',
                'reference_id' => $saleId,
                'balance_after' => $loyalty->points_balance,
            ]);

            $this->auditService->log('loyalty.points_earned', 'customer_loyalty', $customer->id, null, [
                'points' => $points,
                'balance' => $loyalty->points_balance,
                'sale_id' => $saleId,
            ], tenantId: $tenantId);

            return $points;
        });
    }

    public function redeemPoints(Customer $customer, int $pointsToRedeem, ?int $saleId = null): float
    {
        if ($pointsToRedeem <= 0) {
            throw new \DomainException('Points to redeem must be greater than 0');
        }

        $tenantId = $customer->tenant_id;
        $redeemValue = $this->getTenantSetting($tenantId, 'loyalty_redeem_value', 1000);

        return DB::transaction(function () use ($customer, $pointsToRedeem, $redeemValue, $saleId, $tenantId) {
            $loyalty = CustomerLoyaltyPoints::where('tenant_id', $tenantId)
                ->where('customer_id', $customer->id)
                ->lockForUpdate()
                ->first();

            if (!$loyalty || $loyalty->points_balance < $pointsToRedeem) {
                throw new \DomainException('Insufficient loyalty points');
            }

            $loyalty->points_balance -= $pointsToRedeem;
            $loyalty->total_redeemed += $pointsToRedeem;
            $loyalty->save();

            CustomerLoyaltyTransaction::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'points' => -$pointsToRedeem,
                'type' => 'redeem',
                'source' => 'sale',
                'reference_type' => 'sale',
                'reference_id' => $saleId,
                'balance_after' => $loyalty->points_balance,
            ]);

            $this->auditService->log('loyalty.points_redeemed', 'customer_loyalty', $customer->id, null, [
                'points' => $pointsToRedeem,
                'balance' => $loyalty->points_balance,
                'sale_id' => $saleId,
            ], tenantId: $tenantId);

            return (float) ($pointsToRedeem * $redeemValue);
        });
    }

    public function adjustPoints(Customer $customer, int $delta, string $note): int
    {
        $tenantId = $customer->tenant_id;

        return DB::transaction(function () use ($customer, $delta, $note, $tenantId) {
            $loyalty = CustomerLoyaltyPoints::firstOrCreate(
                ['tenant_id' => $tenantId, 'customer_id' => $customer->id],
                ['points_balance' => 0, 'total_earned' => 0, 'total_redeemed' => 0],
            );

            $loyalty->points_balance += $delta;
            if ($delta > 0) {
                $loyalty->total_earned += $delta;
            } else {
                $loyalty->total_redeemed += abs($delta);
            }
            $loyalty->save();

            $transaction = CustomerLoyaltyTransaction::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'points' => $delta,
                'type' => 'adjust',
                'source' => 'manual',
                'balance_after' => $loyalty->points_balance,
                'note' => $note,
            ]);

            $this->auditService->log('loyalty.points_adjusted', 'customer_loyalty', $customer->id, null, [
                'delta' => $delta,
                'balance' => $loyalty->points_balance,
                'note' => $note,
            ], tenantId: $tenantId);

            return $loyalty->points_balance;
        });
    }

    public function processExpiry(int $tenantId): int
    {
        $expiryMonths = $this->getTenantSetting($tenantId, 'loyalty_expiry_months', null);

        if ($expiryMonths === null) {
            return 0;
        }

        $cutoffDate = now()->subMonths($expiryMonths);

        $expiredTransactions = CustomerLoyaltyTransaction::where('tenant_id', $tenantId)
            ->where('type', 'earn')
            ->where('source', 'sale')
            ->where('created_at', '<', $cutoffDate)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('customer_loyalty_transactions')
                    ->whereColumn('customer_id', 'customer_loyalty_transactions.customer_id')
                    ->where('type', 'expire')
                    ->whereColumn('reference_id', 'customer_loyalty_transactions.reference_id');
            })
            ->get();

        $totalExpired = 0;

        foreach ($expiredTransactions as $earnTx) {
            DB::transaction(function () use ($earnTx, $tenantId, &$totalExpired) {
                $loyalty = CustomerLoyaltyPoints::where('tenant_id', $tenantId)
                    ->where('customer_id', $earnTx->customer_id)
                    ->lockForUpdate()
                    ->first();

                if (!$loyalty || $loyalty->points_balance <= 0) {
                    return;
                }

                $expireAmount = min($earnTx->points, $loyalty->points_balance);

                $loyalty->points_balance -= $expireAmount;
                $loyalty->save();

                CustomerLoyaltyTransaction::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $earnTx->customer_id,
                    'points' => -$expireAmount,
                    'type' => 'expire',
                    'source' => 'expiry_sweep',
                    'reference_type' => 'sale',
                    'reference_id' => $earnTx->reference_id,
                    'balance_after' => $loyalty->points_balance,
                ]);

                $this->auditService->log('loyalty.points_expired', 'customer_loyalty', $earnTx->customer_id, null, [
                    'points' => $expireAmount,
                    'balance' => $loyalty->points_balance,
                ], tenantId: $tenantId);

                $totalExpired += $expireAmount;
            });
        }

        return $totalExpired;
    }

    public function getTransactions(Customer $customer, int $perPage = 20)
    {
        return CustomerLoyaltyTransaction::where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    private function getTenantSetting(int $tenantId, string $key, mixed $default = null): mixed
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant || !isset($tenant->settings[$key])) {
            return $default;
        }
        return $tenant->settings[$key];
    }
}
