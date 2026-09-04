<?php

namespace App\Services;

use App\Models\BusinessType;
use App\Models\Feature;
use App\Models\Module;
use App\Models\TenantFeature;
use App\Models\TenantModule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ModuleService
{
    public function getEnabledModules(int $tenantId): Collection
    {
        $moduleIds = TenantModule::where('tenant_id', $tenantId)
            ->where('is_enabled', true)
            ->pluck('module_id');

        return Module::whereIn('id', $moduleIds)->orderBy('sort_order')->get();
    }

    public function getEnabledModuleSlugs(int $tenantId): array
    {
        return $this->getEnabledModules($tenantId)->pluck('slug')->toArray();
    }

    public function enableModule(int $tenantId, string $moduleSlug): TenantModule
    {
        $module = Module::where('slug', $moduleSlug)->firstOrFail();

        if (!$this->validateDependencies($tenantId, $module)) {
            $missing = $this->getMissingDependencies($tenantId, $module);
            throw new \InvalidArgumentException(
                "Cannot enable module: dependency '{$missing}' is not enabled"
            );
        }

        return DB::transaction(function () use ($tenantId, $module) {
            $tenantModule = TenantModule::firstOrCreate(
                ['tenant_id' => $tenantId, 'module_id' => $module->id],
                ['is_enabled' => true, 'enabled_at' => now()]
            );

            if (!$tenantModule->is_enabled) {
                $tenantModule->is_enabled = true;
                $tenantModule->enabled_at = now();
                $tenantModule->save();
            }

            $this->enableDefaultFeatures($tenantId, $module);

            return $tenantModule;
        });
    }

    public function disableModule(int $tenantId, string $moduleSlug): void
    {
        $module = Module::where('slug', $moduleSlug)->firstOrFail();

        if ($module->is_core) {
            throw new \InvalidArgumentException('Cannot disable core module');
        }

        $dependentSlugs = $this->getDependentEnabledModules($tenantId, $module);
        if (!empty($dependentSlugs)) {
            throw new \InvalidArgumentException(
                "Cannot disable module: dependent module '{$dependentSlugs[0]}' is still enabled"
            );
        }

        DB::transaction(function () use ($tenantId, $module) {
            TenantModule::where('tenant_id', $tenantId)
                ->where('module_id', $module->id)
                ->update(['is_enabled' => false]);

            $featureIds = $module->features()->pluck('id');
            TenantFeature::where('tenant_id', $tenantId)
                ->whereIn('feature_id', $featureIds)
                ->update(['is_enabled' => false]);
        });
    }

    public function isModuleEnabled(int $tenantId, string $moduleSlug): bool
    {
        $module = Module::where('slug', $moduleSlug)->first();
        if (!$module) {
            return false;
        }

        return TenantModule::where('tenant_id', $tenantId)
            ->where('module_id', $module->id)
            ->where('is_enabled', true)
            ->exists();
    }

    public function getEnabledFeatures(int $tenantId): Collection
    {
        $featureIds = TenantFeature::where('tenant_id', $tenantId)
            ->where('is_enabled', true)
            ->pluck('feature_id');

        return Feature::whereIn('id', $featureIds)->get();
    }

    public function getEnabledFeatureSlugs(int $tenantId): array
    {
        return $this->getEnabledFeatures($tenantId)->pluck('slug')->toArray();
    }

    public function enableFeature(int $tenantId, string $featureSlug): TenantFeature
    {
        $feature = Feature::where('slug', $featureSlug)->firstOrFail();

        if (!$this->isModuleEnabled($tenantId, $feature->module->slug)) {
            throw new \InvalidArgumentException(
                "Cannot enable feature: parent module '{$feature->module->slug}' is not enabled"
            );
        }

        return DB::transaction(function () use ($tenantId, $feature) {
            $tenantFeature = TenantFeature::firstOrCreate(
                ['tenant_id' => $tenantId, 'feature_id' => $feature->id],
                ['is_enabled' => true]
            );

            if (!$tenantFeature->is_enabled) {
                $tenantFeature->is_enabled = true;
                $tenantFeature->save();
            }

            return $tenantFeature;
        });
    }

    public function disableFeature(int $tenantId, string $featureSlug): void
    {
        $feature = Feature::where('slug', $featureSlug)->firstOrFail();

        if (!$feature->is_owner_toggleable) {
            throw new \InvalidArgumentException('Feature is not owner-toggleable');
        }

        TenantFeature::where('tenant_id', $tenantId)
            ->where('feature_id', $feature->id)
            ->update(['is_enabled' => false]);
    }

    public function isFeatureEnabled(int $tenantId, string $featureSlug): bool
    {
        $feature = Feature::where('slug', $featureSlug)->first();
        if (!$feature) {
            return false;
        }

        return TenantFeature::where('tenant_id', $tenantId)
            ->where('feature_id', $feature->id)
            ->where('is_enabled', true)
            ->exists();
    }

    public function applyBusinessTypeDefaults(int $tenantId, int $businessTypeId): void
    {
        $businessType = BusinessType::with('modules', 'features')->findOrFail($businessTypeId);

        DB::transaction(function () use ($tenantId, $businessType) {
            $sortedModules = $this->sortByDependencies($businessType->modules);

            foreach ($sortedModules as $module) {
                if ($module->pivot->is_default_enabled) {
                    $this->enableModule($tenantId, $module->slug);
                }
            }

            foreach ($businessType->features as $feature) {
                if ($feature->pivot->is_default_enabled) {
                    $this->enableFeature($tenantId, $feature->slug);
                }
            }
        });
    }

    protected function sortByDependencies($modules)
    {
        $sorted = collect();
        $remaining = $modules->keyBy('slug');

        while ($remaining->isNotEmpty()) {
            $added = false;
            foreach ($remaining as $slug => $module) {
                $deps = $module->dependencies ?? [];
                $depsMet = true;
                foreach ($deps as $dep) {
                    if (!$sorted->contains('slug', $dep) && $remaining->has($dep)) {
                        $depsMet = false;
                        break;
                    }
                }
                if ($depsMet) {
                    $sorted->push($module);
                    $remaining->forget($slug);
                    $added = true;
                }
            }
            if (!$added) {
                foreach ($remaining as $module) {
                    $sorted->push($module);
                }
                break;
            }
        }

        return $sorted;
    }

    public function validateDependencies(int $tenantId, Module $module): bool
    {
        $dependencies = $module->dependencies ?? [];
        if (empty($dependencies)) {
            return true;
        }

        foreach ($dependencies as $depSlug) {
            if (!$this->isModuleEnabled($tenantId, $depSlug)) {
                return false;
            }
        }

        return true;
    }

    protected function getMissingDependencies(int $tenantId, Module $module): ?string
    {
        $dependencies = $module->dependencies ?? [];
        foreach ($dependencies as $depSlug) {
            if (!$this->isModuleEnabled($tenantId, $depSlug)) {
                return $depSlug;
            }
        }
        return null;
    }

    protected function getDependentEnabledModules(int $tenantId, Module $module): array
    {
        $allModules = Module::all();
        $dependent = [];

        foreach ($allModules as $m) {
            $deps = $m->dependencies ?? [];
            if (in_array($module->slug, $deps) && $this->isModuleEnabled($tenantId, $m->slug)) {
                $dependent[] = $m->slug;
            }
        }

        return $dependent;
    }

    protected function enableDefaultFeatures(int $tenantId, Module $module): void
    {
        $defaultFeatures = $module->features()->where('is_default_enabled', true)->get();

        foreach ($defaultFeatures as $feature) {
            TenantFeature::firstOrCreate(
                ['tenant_id' => $tenantId, 'feature_id' => $feature->id],
                ['is_enabled' => true]
            );
        }
    }
}
