<?php

namespace App\Http\Middleware;

use App\Models\Feature;
use App\Models\TenantFeature;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckFeature
{
    public function handle(Request $request, Closure $next, string $featureSlug): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $feature = Feature::where('slug', $featureSlug)->first();

        if (!$feature) {
            return response()->json(['message' => 'Feature not found'], 404);
        }

        $tenantFeature = TenantFeature::where('tenant_id', $user->tenant_id)
            ->where('feature_id', $feature->id)
            ->where('is_enabled', true)
            ->first();

        if (!$tenantFeature) {
            return response()->json([
                'message' => 'Feature not enabled',
                'error_code' => 'FEATURE_NOT_ENABLED',
                'feature' => $featureSlug,
            ], 403);
        }

        return $next($request);
    }
}
