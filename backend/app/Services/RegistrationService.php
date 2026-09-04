<?php

namespace App\Services;

use App\Models\BusinessProfile;
use App\Models\BusinessType;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegistrationService
{
    public function __construct(
        protected ModuleService $moduleService,
        protected AuditService $auditService,
    ) {}

    public function register(array $data): User
    {
        $businessTypeId = $data['business_type_id'] ?? null;

        if ($businessTypeId) {
            $businessType = BusinessType::where('id', $businessTypeId)
                ->where('is_active', true)
                ->firstOrFail();
        } else {
            $businessType = BusinessType::where('slug', 'general')->firstOrFail();
        }

        return DB::transaction(function () use ($data, $businessType) {
            $tenant = Tenant::create([
                'name' => $data['store_name'],
                'slug' => str()->slug($data['store_name']),
            ]);

            $businessProfile = BusinessProfile::create([
                'tenant_id' => $tenant->id,
                'business_type_id' => $businessType->id,
                'business_name' => $data['store_name'],
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'locale' => 'id',
                'is_active' => true,
            ]);

            $ownerRole = Role::where('slug', 'owner')->whereNull('tenant_id')->first();

            $user = User::create([
                'tenant_id' => $tenant->id,
                'role_id' => $ownerRole?->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $store = new Store;
            $store->tenant_id = $tenant->id;
            $store->name = $data['store_name'];
            $store->code = 'STR-001';
            $store->is_active = true;
            $store->is_headquarters = true;
            $store->save();

            $this->moduleService->applyBusinessTypeDefaults($tenant->id, $businessType->id);

            $this->auditService->log('register', 'Tenant', $tenant->id, null, [
                'tenant_id' => $tenant->id,
                'business_type' => $businessType->slug,
                'store_name' => $data['store_name'],
            ]);

            return $user;
        });
    }

    public function getConfig(int $tenantId, int $userId): array
    {
        $modules = $this->moduleService->getEnabledModuleSlugs($tenantId);
        $features = $this->moduleService->getEnabledFeatureSlugs($tenantId);

        $user = User::with('role.permissions')->find($userId);
        $permissions = $user?->role?->permissions?->pluck('slug')?->toArray() ?? [];

        $stores = Store::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get(['id', 'name', 'code', 'is_headquarters']);

        $businessProfile = BusinessProfile::with('businessType:id,slug,name')
            ->where('tenant_id', $tenantId)->first();

        return [
            'modules' => $modules,
            'features' => $features,
            'permissions' => $permissions,
            'stores' => $stores,
            'business_profile' => $businessProfile,
        ];
    }
}
