<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyTier;
use App\Models\LoyaltyTransaction;
use App\Models\Sale;

class LoyaltyProgramService
{
    public function createProgram(string $name, float $pointsPerCurrency, float $currencyPerPoint, bool $isActive = true): LoyaltyProgram
    {
        return LoyaltyProgram::create([
            'name' => $name,
            'points_per_currency' => $pointsPerCurrency,
            'currency_per_point' => $currencyPerPoint,
            'is_active' => $isActive,
        ]);
    }

    public function createTier(int $programId, string $name, int $minPoints, float $discountPercentage = 0): LoyaltyTier
    {
        return LoyaltyTier::create([
            'loyalty_program_id' => $programId,
            'name' => $name,
            'min_points' => $minPoints,
            'discount_percentage' => $discountPercentage,
        ]);
    }

    public function earnPoints(int $customerId, float $amount, int $saleId): ?LoyaltyTransaction
    {
        $customer = Customer::withoutTenantScope()->find($customerId);
        if (!$customer) {
            return null;
        }

        $program = $this->getActiveProgram($customer->tenant_id);
        if (!$program) {
            return null;
        }

        $points = (int) floor($amount * (float) $program->points_per_currency);
        if ($points <= 0) {
            return null;
        }

        $currentBalance = $this->getBalance($customerId);

        return LoyaltyTransaction::create([
            'customer_id' => $customerId,
            'loyalty_program_id' => $program->id,
            'type' => 'earn',
            'points' => $points,
            'balance_after' => $currentBalance + $points,
            'reference_type' => 'sale',
            'reference_id' => $saleId,
            'notes' => "Earned from sale #{$saleId}",
        ]);
    }

    public function redeemPoints(int $customerId, int $points, int $saleId): array
    {
        $customer = Customer::withoutTenantScope()->find($customerId);
        if (!$customer) {
            throw new \DomainException('Customer not found');
        }

        $program = $this->getActiveProgram($customer->tenant_id);
        if (!$program) {
            throw new \DomainException('No active loyalty program');
        }

        $currentBalance = $this->getBalance($customerId);

        if ($currentBalance < $points) {
            throw new \DomainException(
                "Insufficient loyalty points. Available: {$currentBalance}, Requested: {$points}"
            );
        }

        $discountAmount = round($points * (float) $program->currency_per_point, 2);

        $transaction = LoyaltyTransaction::create([
            'customer_id' => $customerId,
            'loyalty_program_id' => $program->id,
            'type' => 'redeem',
            'points' => -$points,
            'balance_after' => $currentBalance - $points,
            'reference_type' => 'sale',
            'reference_id' => $saleId,
            'notes' => "Redeemed for sale #{$saleId}",
        ]);

        return [
            'transaction' => $transaction,
            'discount_amount' => $discountAmount,
        ];
    }

    public function getBalance(int $customerId): int
    {
        $lastTransaction = LoyaltyTransaction::withoutTenantScope()
            ->where('customer_id', $customerId)
            ->orderBy('id', 'desc')
            ->first();

        return $lastTransaction ? $lastTransaction->balance_after : 0;
    }

    public function getTier(int $customerId): ?LoyaltyTier
    {
        $balance = $this->getBalance($customerId);
        $customer = Customer::withoutTenantScope()->find($customerId);
        if (!$customer) {
            return null;
        }

        $program = $this->getActiveProgram($customer->tenant_id);
        if (!$program) {
            return null;
        }

        return $program->tiers()
            ->where('min_points', '<=', $balance)
            ->orderBy('min_points', 'desc')
            ->first();
    }

    protected function getActiveProgram(int $tenantId): ?LoyaltyProgram
    {
        return LoyaltyProgram::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();
    }
}
