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
}
