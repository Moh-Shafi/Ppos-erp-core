<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query();

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $customers = $query->orderBy('name')->paginate($perPage);

        return response()->json($customers);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'boolean',
            'credit_limit' => 'nullable|numeric|min:0',
            'price_list_id' => 'nullable|integer|exists:price_lists,id',
        ]);

        if (!isset($validated['outstanding_balance'])) {
            $validated['outstanding_balance'] = 0;
        }

        $customer = Customer::create($validated);
        $customer->refresh();

        return response()->json($customer, 201);
    }

    public function show(Request $request, int $id)
    {
        $customer = Customer::findOrFail($id);

        return response()->json($customer);
    }

    public function update(Request $request, int $id)
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'boolean',
            'credit_limit' => 'nullable|numeric|min:0',
            'price_list_id' => 'nullable|integer|exists:price_lists,id',
        ]);

        $customer->update($validated);
        $customer->refresh();

        return response()->json($customer);
    }

    public function destroy(Request $request, int $id)
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();

        return response()->json(['message' => 'Customer deleted'], 200);
    }
}
