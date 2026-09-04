<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CustomerCreditService;
use Illuminate\Http\Request;

class CustomerCreditController extends Controller
{
    public function __construct(
        private readonly CustomerCreditService $creditService,
    ) {}

    public function balance(int $customerId)
    {
        $customer = Customer::findOrFail($customerId);

        return response()->json([
            'customer_id' => $customer->id,
            'outstanding_balance' => (float) $customer->outstanding_balance,
            'credit_limit' => $customer->credit_limit !== null ? (float) $customer->credit_limit : null,
        ]);
    }

    public function transactions(int $customerId, Request $request)
    {
        $customer = Customer::findOrFail($customerId);
        $perPage = min((int) $request->get('per_page', 20), 100);

        return response()->json($this->creditService->getTransactions($customer, $perPage));
    }

    public function adjust(int $customerId, Request $request)
    {
        $customer = Customer::findOrFail($customerId);

        $validated = $request->validate([
            'amount' => 'required|numeric',
            'note' => 'required|string|max:500',
        ]);

        $balance = $this->creditService->adjust($customer, $validated['amount'], $validated['note']);

        return response()->json([
            'customer_id' => $customer->id,
            'outstanding_balance' => $balance,
        ]);
    }

    public function check(int $customerId, Request $request)
    {
        $customer = Customer::findOrFail($customerId);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $result = $this->creditService->checkLimit($customer, $validated['amount']);

        return response()->json($result);
    }
}
