<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\ModuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantModuleController extends Controller
{
    public function __construct(
        protected ModuleService $moduleService,
        protected AuditService $auditService,
    ) {}

    public function index(): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;

        $tenantModules = \App\Models\TenantModule::where('tenant_id', $tenantId)
            ->with('module:id,slug,name,description,is_core,sort_order,icon,dependencies')
            ->get();

        $tenantFeatures = \App\Models\TenantFeature::where('tenant_id', $tenantId)
            ->with('feature:id,module_id,slug,name,description,is_owner_toggleable')
            ->get();

        return response()->json([
            'modules' => $tenantModules->map(fn ($tm) => [
                'id' => $tm->id,
                'module_id' => $tm->module_id,
                'slug' => $tm->module->slug,
                'name' => $tm->module->name,
                'description' => $tm->module->description,
                'is_core' => $tm->module->is_core,
                'is_enabled' => $tm->is_enabled,
                'icon' => $tm->module->icon,
                'dependencies' => $tm->module->dependencies,
                'sort_order' => $tm->module->sort_order,
            ]),
            'features' => $tenantFeatures->map(fn ($tf) => [
                'id' => $tf->id,
                'feature_id' => $tf->feature_id,
                'slug' => $tf->feature->slug,
                'name' => $tf->feature->name,
                'description' => $tf->feature->description,
                'is_enabled' => $tf->is_enabled,
                'is_owner_toggleable' => $tf->feature->is_owner_toggleable,
            ]),
        ]);
    }

    public function toggleModule(Request $request, int $moduleId): JsonResponse
    {
        $request->validate([
            'is_enabled' => 'required|boolean',
        ]);

        $tenantId = Auth::user()->tenant_id;
        $module = \App\Models\Module::findOrFail($moduleId);

        if ($module->is_core && !$request->boolean('is_enabled')) {
            return response()->json([
                'message' => 'Cannot disable core module',
                'error_code' => 'CORE_MODULE',
            ], 422);
        }

        try {
            if ($request->boolean('is_enabled')) {
                $this->moduleService->enableModule($tenantId, $module->slug);
                $this->auditService->log('module.enable', 'Module', $module->id);
            } else {
                $this->moduleService->disableModule($tenantId, $module->slug);
                $this->auditService->log('module.disable', 'Module', $module->id);
            }
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'DEPENDENCY_NOT_MET',
            ], 422);
        }

        return response()->json([
            'message' => 'Module updated successfully',
            'module_slug' => $module->slug,
            'is_enabled' => $request->boolean('is_enabled'),
        ]);
    }

    public function toggleFeature(Request $request, int $featureId): JsonResponse
    {
        $request->validate([
            'is_enabled' => 'required|boolean',
        ]);

        $tenantId = Auth::user()->tenant_id;
        $feature = \App\Models\Feature::findOrFail($featureId);

        if (!$feature->is_owner_toggleable && !$request->boolean('is_enabled')) {
            return response()->json([
                'message' => 'Feature is not owner-toggleable',
                'error_code' => 'NOT_TOGGLEABLE',
            ], 422);
        }

        try {
            if ($request->boolean('is_enabled')) {
                $this->moduleService->enableFeature($tenantId, $feature->slug);
                $this->auditService->log('feature.enable', 'Feature', $feature->id);
            } else {
                $this->moduleService->disableFeature($tenantId, $feature->slug);
                $this->auditService->log('feature.disable', 'Feature', $feature->id);
            }
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'DEPENDENCY_NOT_MET',
            ], 422);
        }

        return response()->json([
            'message' => 'Feature updated successfully',
            'feature_slug' => $feature->slug,
            'is_enabled' => $request->boolean('is_enabled'),
        ]);
    }
}
