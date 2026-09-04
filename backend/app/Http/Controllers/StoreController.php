<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function index(Request $request)
    {
        $stores = Store::all();

        return response()->json([
            'stores' => $stores,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
        ]);

        $store = Store::create($validated);

        return response()->json([
            'message' => 'Store created successfully',
            'store' => $store,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $store = Store::findOrFail($id);

        return response()->json([
            'store' => $store,
        ]);
    }

    public function update(Request $request, $id)
    {
        $store = Store::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
            'receipt_settings' => 'nullable|array',
            'receipt_settings.header_text' => 'nullable|string|max:500',
            'receipt_settings.footer_text' => 'nullable|string|max:500',
            'receipt_settings.show_cashier' => 'nullable|boolean',
            'receipt_settings.show_customer' => 'nullable|boolean',
            'receipt_settings.show_qr_code' => 'nullable|boolean',
            'receipt_settings.paper_width' => 'nullable|string|max:10',
            'receipt_settings.logo_url' => 'nullable|string|max:500',
        ]);

        $store->update($validated);

        return response()->json([
            'message' => 'Store updated successfully',
            'store' => $store->fresh(),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $store = Store::findOrFail($id);
        $store->delete();

        return response()->json([
            'message' => 'Store deleted successfully',
        ]);
    }

    public function getReceiptSettings(int $id)
    {
        $store = Store::findOrFail($id);

        return response()->json($store->receipt_settings);
    }

    public function updateReceiptSettings(Request $request, int $id)
    {
        $store = Store::findOrFail($id);

        $validated = $request->validate([
            'header_text' => 'nullable|string|max:500',
            'footer_text' => 'nullable|string|max:500',
            'show_cashier' => 'nullable|boolean',
            'show_customer' => 'nullable|boolean',
            'show_qr_code' => 'nullable|boolean',
            'paper_width' => 'nullable|string|max:10',
            'logo_url' => 'nullable|string|max:500',
        ]);

        $store->receipt_settings = $validated;
        $store->save();

        return response()->json($store->fresh()->receipt_settings);
    }
}
