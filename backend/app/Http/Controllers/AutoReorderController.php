<?php

namespace App\Http\Controllers;

use App\Services\AutoReorderService;
use Illuminate\Http\Request;

class AutoReorderController extends Controller
{
    public function __construct(
        private readonly AutoReorderService $autoReorderService,
    ) {}

    public function report(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
        ]);

        return response()->json($this->autoReorderService->report($validated['store_id']));
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        try {
            $result = $this->autoReorderService->generateRequisition($validated['store_id'], $validated['product_ids']);
            return response()->json($result, 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
