<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Sale;
use App\Models\Tenant;
use App\Services\SettlementService;
use App\Services\SubAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentGatewayController extends Controller
{
    public function __construct(
        private SubAccountService $subAccountService,
        private SettlementService $settlementService,
    ) {}

    /**
     * Show tenant gateway account.
     */
    public function account(Request $request)
    {
        $tenant = $request->user()->tenant;
        $account = $this->subAccountService->getActive($tenant);

        if (!$account) {
            return response()->json(['data' => null], 200);
        }

        return response()->json(['data' => $account]);
    }

    /**
     * Provision Xendit sub-account.
     */
    public function provision(Request $request)
    {
        $validated = $request->validate([
            'business_name' => 'nullable|string|max:255',
            'business_email' => 'nullable|email|max:255',
            'business_type' => 'nullable|string|max:100',
        ]);

        $tenant = $request->user()->tenant;

        try {
            $account = $this->subAccountService->provision($tenant, $validated);
            return response()->json(['data' => $account], 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Create a gateway charge for a sale.
     */
    public function createCharge(Request $request, int $id)
    {
        $sale = Sale::findOrFail($id);

        $validated = $request->validate([
            'method' => 'required|string|in:qris,card,bank_transfer',
            'amount' => 'required|numeric|min:0.01',
            'idempotency_key' => 'required|string|max:255',
        ]);

        $gateway = app(\App\Contracts\PaymentGatewayInterface::class);
        $tenant = $request->user()->tenant;
        $account = $this->subAccountService->getActive($tenant);

        if (!$account || $account->status !== 'active') {
            return response()->json(['message' => 'Gateway not active for tenant'], 422);
        }

        try {
            $result = $gateway->createCharge([
                'payment_method' => $validated['method'],
                'amount' => (float) $validated['amount'],
                'reference_id' => $sale->sale_number . '-' . $validated['idempotency_key'],
                'idempotency_key' => $validated['idempotency_key'],
                'for_user_id' => $tenant->xendit_user_id,
                'fee_rule' => $tenant->xendit_fee_rule_id,
                'description' => 'Payment for ' . $sale->sale_number,
                'metadata' => [
                    'sale_id' => $sale->id,
                    'tenant_id' => $tenant->id,
                ],
            ]);

            $payment = new Payment();
            $payment->tenant_id = $tenant->id;
            $payment->sale_id = $sale->id;
            $payment->payment_method = $validated['method'];
            $payment->amount = $validated['amount'];
            $payment->change_amount = 0;
            $payment->idempotency_key = $validated['idempotency_key'];
            $payment->gateway_transaction_id = $result['gateway_transaction_id'];
            $payment->gateway_status = $result['gateway_status'];
            $payment->gateway_response = $result['gateway_response'];
            $payment->status = 'pending';
            $payment->expires_at = $result['expires_at'];
            $payment->gateway_account_id = $account->gateway_account_id;
            $payment->metadata = $result['metadata'];
            $payment->payment_date = now();
            $payment->save();

            return response()->json(['data' => $payment], 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get gateway charge status.
     */
    public function getChargeStatus(Request $request, int $id, int $chargeId)
    {
        $sale = Sale::findOrFail($id);
        $payment = $sale->payments()->findOrFail($chargeId);

        return response()->json(['data' => $payment]);
    }

    /**
     * Refund a payment.
     */
    public function refund(Request $request, int $id)
    {
        $payment = Payment::findOrFail($id);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:2000',
        ]);

        $amount = (float) $validated['amount'];

        if ($payment->status !== 'success') {
            return response()->json(['message' => 'Can only refund successful payments'], 422);
        }

        if ($amount > (float) $payment->amount - (float) $payment->refund_amount) {
            return response()->json(['message' => 'Refund amount exceeds payment amount'], 422);
        }

        $tenant = Auth::user()->tenant;
        $gateway = app(\App\Contracts\PaymentGatewayInterface::class);

        try {
            $result = $gateway->refund(
                $payment->gateway_transaction_id,
                $amount,
                $validated['reason'] ?? 'Customer request',
                [
                    'for_user_id' => $tenant->xendit_user_id,
                ]
            );

            $payment->refund_amount += $amount;
            $payment->refund_status = ($payment->refund_amount >= $payment->amount) ? 'full' : 'partial';
            if ($payment->refund_status === 'full') {
                $payment->status = 'refunded';
            }
            $payment->save();

            return response()->json(['data' => [
                'refund_id' => $result['refund_id'],
                'status' => $result['status'],
                'amount' => $amount,
                'payment_id' => $payment->id,
                'payment_refund_status' => $payment->refund_status,
            ]], 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * List settlements.
     */
    public function settlements(Request $request)
    {
        $query = \App\Models\PaymentSettlement::query();

        if ($request->filled('date_from')) {
            $query->whereDate('settled_at', '>=', $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('settled_at', '<=', $request->get('date_to'));
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $settlements = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($settlements);
    }

    /**
     * Run reconciliation.
     */
    public function reconcile(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $tenant = Auth::user()->tenant;
        $report = $this->settlementService->reconcile(
            $tenant->id,
            $validated['date_from'],
            $validated['date_to']
        );

        return response()->json(['data' => $report]);
    }
}
