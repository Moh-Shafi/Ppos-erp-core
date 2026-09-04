<?php

namespace App\Http\Controllers;

use App\Services\CatalogService;
use Illuminate\Http\Request;

class ProductImportExportController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalogService = new CatalogService(),
    ) {}

    public function export(Request $request)
    {
        $csv = $this->catalogService->exportProducts($request->user()->tenant_id);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="products.csv"',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        try {
            $result = $this->catalogService->importProducts($request->file('file'), $request->user()->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json($result);
    }

    public function lookup(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string|max:100',
        ]);

        $result = $this->catalogService->lookupByBarcode($request->get('barcode'), $request->user()->tenant_id);

        if (!$result) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json([
            'product' => $result['product'],
            'variant' => $result['variant'],
        ]);
    }

    public function generateVariants(Request $request, $id)
    {
        $validated = $request->validate([
            'option_value_ids' => 'required|array|min:1',
            'option_value_ids.*' => 'required|array|min:1',
            'option_value_ids.*.*' => 'integer',
        ]);

        $combinations = $this->catalogService->generateVariantCombinations($id, $validated['option_value_ids']);

        return response()->json(['combinations' => $combinations]);
    }
}
