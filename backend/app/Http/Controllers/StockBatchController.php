<?php

namespace App\Http\Controllers;

use App\Services\StockBatchService;
use Illuminate\Http\Request;

class StockBatchController extends Controller
{
    public function __construct(
        private readonly StockBatchService $stockBatchService = new StockBatchService(),
    ) {}

    public function index(Request $request, $productId)
    {
        $batches = $this->stockBatchService->getBatchesForProduct($productId, $request->user()->tenant_id);

        return response()->json(['data' => $batches]);
    }

    public function store(Request $request, $productId)
    {
        $validated = $request->validate([
            'batch_number' => 'required|string|max:100',
            'quantity' => 'required|integer|min:0',
            'received_date' => 'required|date',
            'expiry_date' => 'nullable|date|after:received_date',
            'cost_price' => 'nullable|numeric|min:0',
        ]);

        try {
            $batch = $this->stockBatchService->createBatch($productId, $validated, $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'message' => 'Batch created successfully',
            'batch' => $batch,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $batch = \App\Models\StockBatch::findOrFail($id);

        return response()->json(['batch' => $batch]);
    }
}
