<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BusinessProfileController extends Controller
{
    public function show(): JsonResponse
    {
        $profile = BusinessProfile::where('tenant_id', Auth::user()->tenant_id)
            ->with('businessType:id,slug,name')
            ->firstOrFail();

        return response()->json([
            'data' => $profile,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_name' => 'sometimes|required|string|max:255',
            'tax_id' => 'sometimes|nullable|string|max:50',
            'address' => 'sometimes|nullable|string|max:500',
            'city' => 'sometimes|nullable|string|max:100',
            'province' => 'sometimes|nullable|string|max:100',
            'postal_code' => 'sometimes|nullable|string|max:20',
            'phone' => 'sometimes|nullable|string|max:50',
            'email' => 'sometimes|nullable|email|max:255',
            'logo' => 'sometimes|nullable|string|max:255',
            'timezone' => 'sometimes|nullable|string|max:50',
            'currency' => 'sometimes|nullable|string|max:3',
            'locale' => 'sometimes|nullable|string|max:10',
        ]);

        $profile = BusinessProfile::where('tenant_id', Auth::user()->tenant_id)->firstOrFail();
        $profile->update($validated);

        return response()->json([
            'message' => 'Business profile updated successfully',
            'data' => $profile->fresh(['businessType:id,slug,name']),
        ]);
    }
}
