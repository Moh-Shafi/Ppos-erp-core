<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\PaymentGatewayAccount;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class SubAccountService
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
    ) {}

    /**
     * Provision a Xendit sub-account for a tenant.
     */
    public function provision(Tenant $tenant, array $data): PaymentGatewayAccount
    {
        return DB::transaction(function () use ($tenant, $data) {
            $existing = PaymentGatewayAccount::where('tenant_id', $tenant->id)
                ->where('gateway', 'xendit')
                ->first();

            if ($existing) {
                throw new \DomainException('Gateway account already provisioned');
            }

            $result = $this->gateway->provisionSubAccount([
                'tenant_id' => $tenant->id,
                'business_name' => $data['business_name'] ?? $tenant->name,
                'business_email' => $data['business_email'] ?? null,
                'business_type' => $data['business_type'] ?? null,
            ]);

            $account = new PaymentGatewayAccount();
            $account->tenant_id = $tenant->id;
            $account->gateway = 'xendit';
            $account->gateway_account_id = $result['gateway_account_id'];
            $account->status = $result['status'];
            $account->kyc_status = 'none';
            $account->capabilities = $result['gateway_response']['capabilities'] ?? [];
            $account->metadata = $result['gateway_response'];
            $account->save();

            $tenant->xendit_user_id = $result['gateway_account_id'];
            $tenant->save();

            return $account;
        });
    }

    /**
     * Get active account for a tenant.
     */
    public function getActive(Tenant $tenant): ?PaymentGatewayAccount
    {
        return PaymentGatewayAccount::where('tenant_id', $tenant->id)
            ->where('gateway', 'xendit')
            ->whereIn('status', ['active', 'pending'])
            ->first();
    }

    /**
     * Update sub-account status from webhook or manual sync.
     */
    public function updateStatus(string $gatewayAccountId, array $updates): ?PaymentGatewayAccount
    {
        $account = PaymentGatewayAccount::where('gateway_account_id', $gatewayAccountId)
            ->where('gateway', 'xendit')
            ->first();

        if (!$account) {
            return null;
        }

        if (!empty($updates['status'])) {
            $account->status = $updates['status'];
        }

        if (!empty($updates['kyc_status'])) {
            $account->kyc_status = $updates['kyc_status'];
        }

        if (!empty($updates['capabilities'])) {
            $account->capabilities = $updates['capabilities'];
        }

        if ($updates['status'] === 'active' && !$account->activated_at) {
            $account->activated_at = now();
        }

        $account->save();

        return $account;
    }

    /**
     * Resolve tenant from Xendit account ID.
     */
    public function resolveTenant(string $gatewayAccountId): ?Tenant
    {
        $account = PaymentGatewayAccount::where('gateway_account_id', $gatewayAccountId)
            ->where('gateway', 'xendit')
            ->first();

        return $account?->tenant;
    }
}
