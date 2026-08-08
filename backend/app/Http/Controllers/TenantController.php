<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function show(Request $request)
    {
        $tenant = $request->user()->tenant;

        return response()->json([
            'tenant' => $tenant,
        ]);
    }

    public function update(Request $request)
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = str()->slug($validated['name']);
        }

        $tenant->update($validated);

        return response()->json([
            'message' => 'Tenant updated successfully',
            'tenant' => $tenant->fresh(),
        ]);
    }
}
