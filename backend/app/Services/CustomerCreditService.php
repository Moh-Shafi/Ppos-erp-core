<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerCreditTransaction;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class CustomerCreditService
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
    ) {}

    public function checkLimit(Customer $customer, float $amount): array
    {
        $creditLimit = $customer->credit_limit;
        $outstanding = (float) $customer->outstanding_balance;

        if ($creditLimit === null) {
            return [
                'allowed' => true,
                'outstanding_balance' => $outstanding,
                'credit_limit' => null,
                'remaining' => null,
            ];
        }

        $tenantId = $customer->tenant_id;
        $tolerance = (float) $this->getTenantSetting($tenantId, 'credit_tolerance', 0);

        $remaining = $creditLimit - $outstanding;
        $allowed = ($outstanding + $amount) <= ($creditLimit + $tolerance);

        return [
            'allowed' => $allowed,
            'outstanding_balance' => $outstanding,
            'credit_limit' => (float) $creditLimit,
            'remaining' => $remaining,
        ];
    }

    public function addDebit(Customer $customer, float $amount, ?string $referenceType = null, ?int $referenceId = null): float
    {
        $tenantId = $customer->tenant_id;

        return DB::transaction(function () use ($customer, $amount, $referenceType, $referenceId, $tenantId) {
            $customer = Customer::lockForUpdate()->find($customer->id);
            $newBalance = (float) $customer->outstanding_balance + $amount;
            $customer->outstanding_balance = $newBalance;
            $customer->save();

            CustomerCreditTransaction::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'amount' => $amount,
                'type' => 'debit',
                'source' => 'sale',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'balance_after' => $newBalance,
            ]);

            $this->auditService->log('credit.debit_added', 'customer_credit', $customer->id, null, [
                'amount' => $amount,
                'balance_after' => $newBalance,
            ], tenantId: $tenantId);

            return $newBalance;
        });
    }

    public function addCredit(Customer $customer, float $amount, ?string $referenceType = null, ?int $referenceId = null): float
    {
        $tenantId = $customer->tenant_id;

        return DB::transaction(function () use ($customer, $amount, $referenceType, $referenceId, $tenantId) {
            $customer = Customer::lockForUpdate()->find($customer->id);
            $newBalance = max(0, (float) $customer->outstanding_balance - $amount);
            $customer->outstanding_balance = $newBalance;
            $customer->save();

            CustomerCreditTransaction::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'amount' => -$amount,
                'type' => 'credit',
                'source' => 'payment',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'balance_after' => $newBalance,
            ]);

            $this->auditService->log('credit.payment_received', 'customer_credit', $customer->id, null, [
                'amount' => $amount,
                'balance_after' => $newBalance,
            ], tenantId: $tenantId);

            return $newBalance;
        });
    }

    public function adjust(Customer $customer, float $amount, string $note): float
    {
        $tenantId = $customer->tenant_id;

        return DB::transaction(function () use ($customer, $amount, $note, $tenantId) {
            $customer = Customer::lockForUpdate()->find($customer->id);
            $newBalance = (float) $customer->outstanding_balance + $amount;
            if ($newBalance < 0) {
                $newBalance = 0;
            }
            $customer->outstanding_balance = $newBalance;
            $customer->save();

            CustomerCreditTransaction::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'amount' => $amount,
                'type' => 'adjust',
                'source' => 'manual',
                'balance_after' => $newBalance,
                'note' => $note,
            ]);

            $this->auditService->log('credit.adjusted', 'customer_credit', $customer->id, null, [
                'amount' => $amount,
                'balance_after' => $newBalance,
                'note' => $note,
            ], tenantId: $tenantId);

            return $newBalance;
        });
    }

    public function getTransactions(Customer $customer, int $perPage = 20)
    {
        return CustomerCreditTransaction::where('tenant_id', $customer->tenant_id)
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
