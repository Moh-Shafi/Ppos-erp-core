<?php

namespace App\Http\Middleware;

use App\Models\Module;
use App\Models\TenantModule;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckModule
{
    public function handle(Request $request, Closure $next, string $moduleSlug): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $module = Module::where('slug', $moduleSlug)->first();

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        $tenantModule = TenantModule::where('tenant_id', $user->tenant_id)
            ->where('module_id', $module->id)
            ->where('is_enabled', true)
            ->first();

        if (!$tenantModule) {
            return response()->json([
                'message' => 'Module not enabled',
                'error_code' => 'MODULE_NOT_ENABLED',
                'module' => $moduleSlug,
            ], 403);
        }

        return $next($request);
    }
}
