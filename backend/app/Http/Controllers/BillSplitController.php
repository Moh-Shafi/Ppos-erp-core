<?php

namespace App\Http\Controllers;

use App\Models\BillSplit;
use App\Services\BillSplitService;
use Illuminate\Http\Request;

class BillSplitController extends Controller
{
    public function __construct(
        private readonly BillSplitService $billSplitService = new BillSplitService(),
    ) {}

    public function split(Request $request, int $saleId)
    {
        $validated = $request->validate([
            'split_type' => 'required|in:equal,per_item,per_person,custom',
            'data.person_count' => 'required_if:split_type,equal|integer|min:2',
            'data.item_assignments' => 'required_if:split_type,per_item|array',
            'data.item_assignments.*.sale_item_id' => 'required|integer',
            'data.item_assignments.*.bill_split_index' => 'required|integer|min:0',
            'data.person_items' => 'required_if:split_type,per_person|array',
            'data.person_items.*.sale_item_ids' => 'required|array',
            'data.person_items.*.customer_id' => 'nullable|integer',
            'data.custom_splits' => 'required_if:split_type,custom|array',
            'data.custom_splits.*.sale_item_id' => 'nullable|integer',
            'data.custom_splits.*.customer_id' => 'nullable|integer',
            'data.custom_splits.*.amount' => 'required|numeric|min:0',
        ]);

        $splits = $this->billSplitService->create($saleId, $validated['split_type'], $validated['data']);
        return response()->json(['data' => $splits], 201);
    }

    public function getSplits(int $saleId)
    {
        $splits = BillSplit::where('sale_id', $saleId)->with(['items', 'sale'])->get();
        return response()->json(['data' => $splits]);
    }

    public function pay(Request $request, int $splitId)
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:cash,qris,card,bank_transfer',
            'amount' => 'required|numeric|min:0',
        ]);

        $split = $this->billSplitService->processPayment(
            $splitId,
            $validated['payment_method'],
            (float) $validated['amount'],
        );

        return response()->json(['data' => $split]);
    }
}
