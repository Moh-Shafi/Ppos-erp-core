<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService,
    ) {}

    public function balance(int $customerId)
    {
        $customer = Customer::findOrFail($customerId);
        $balance = $this->loyaltyService->getBalance($customer);

        return response()->json([
            'customer_id' => $customer->id,
            'points_balance' => $balance?->points_balance ?? 0,
            'total_earned' => $balance?->total_earned ?? 0,
            'total_redeemed' => $balance?->total_redeemed ?? 0,
        ]);
    }

    public function transactions(int $customerId, Request $request)
    {
        $customer = Customer::findOrFail($customerId);
        $perPage = min((int) $request->get('per_page', 20), 100);

        return response()->json($this->loyaltyService->getTransactions($customer, $perPage));
    }

    public function adjust(int $customerId, Request $request)
    {
        $customer = Customer::findOrFail($customerId);

        $validated = $request->validate([
            'points' => 'required|integer',
            'note' => 'required|string|max:500',
        ]);

        $balance = $this->loyaltyService->adjustPoints($customer, $validated['points'], $validated['note']);

        return response()->json([
            'customer_id' => $customer->id,
            'points_balance' => $balance,
        ]);
    }
}
