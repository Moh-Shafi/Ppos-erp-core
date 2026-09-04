<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyProgram;
use App\Models\LoyaltyTier;
use App\Models\LoyaltyTransaction;
use App\Services\LoyaltyProgramService;
use Illuminate\Http\Request;

class LoyaltyProgramController extends Controller
{
    public function __construct(
        private readonly LoyaltyProgramService $loyaltyProgramService = new LoyaltyProgramService(),
    ) {}

    public function programsIndex()
    {
        return response()->json(['data' => LoyaltyProgram::with('tiers')->get()]);
    }

    public function programsStore(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'points_per_currency' => 'required|numeric|min:0',
            'currency_per_point' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $program = $this->loyaltyProgramService->createProgram(
            $validated['name'],
            (float) $validated['points_per_currency'],
            (float) $validated['currency_per_point'],
            $validated['is_active'] ?? true,
        );

        return response()->json(['data' => $program], 201);
    }

    public function programsUpdate(Request $request, int $id)
    {
        $program = LoyaltyProgram::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'points_per_currency' => 'sometimes|numeric|min:0',
            'currency_per_point' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $program->update($validated);
        return response()->json(['data' => $program->fresh(['tiers'])]);
    }

    public function tiersIndex(Request $request)
    {
        $query = LoyaltyTier::query()->with('program');

        if ($request->filled('program_id')) {
            $query->where('loyalty_program_id', (int) $request->get('program_id'));
        }

        return response()->json(['data' => $query->orderBy('min_points')->get()]);
    }

    public function tiersStore(Request $request)
    {
        $validated = $request->validate([
            'loyalty_program_id' => 'required|integer',
            'name' => 'required|string|max:50',
            'min_points' => 'required|integer|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $tier = $this->loyaltyProgramService->createTier(
            $validated['loyalty_program_id'],
            $validated['name'],
            $validated['min_points'],
            $validated['discount_percentage'] ?? 0,
        );

        return response()->json(['data' => $tier], 201);
    }

    public function balance(int $customerId)
    {
        $balance = $this->loyaltyProgramService->getBalance($customerId);
        $tier = $this->loyaltyProgramService->getTier($customerId);

        return response()->json(['data' => ['balance' => $balance, 'tier' => $tier]]);
    }

    public function transactions(Request $request, int $customerId)
    {
        $query = LoyaltyTransaction::query()->where('customer_id', $customerId)->with('program');

        $perPage = min((int) $request->get('per_page', 20), 100);
        return response()->json($query->orderBy('id', 'desc')->paginate($perPage));
    }

    public function redeem(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer',
            'points' => 'required|integer|min:1',
            'sale_id' => 'required|integer',
        ]);

        $result = $this->loyaltyProgramService->redeemPoints(
            $validated['customer_id'],
            $validated['points'],
            $validated['sale_id'],
        );

        return response()->json(['data' => $result]);
    }
}
