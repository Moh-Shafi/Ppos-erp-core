<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\BusinessType;
use App\Models\Feature;
use App\Models\Module;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\TenantModule;
use App\Models\User;
use App\Services\ModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase0ModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Tenant $tenant;
    private ModuleService $moduleService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        $this->seed(\Database\Seeders\BusinessTypeSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();
        $this->tenant = Tenant::create(['name' => 'Test Toko', 'slug' => 'test-toko']);

        $this->owner = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $ownerRole->id,
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'password' => 'password',
        ]);

        $this->moduleService = app(ModuleService::class);
    }

    public function test_modules_are_seeded(): void
    {
        $this->assertGreaterThan(0, Module::count());
        $this->assertTrue(Module::where('slug', 'core')->exists());
        $this->assertTrue(Module::where('slug', 'pos')->exists());
        $this->assertTrue(Module::where('slug', 'inventory')->exists());
    }

    public function test_features_are_seeded(): void
    {
        $this->assertGreaterThan(0, Feature::count());
        $posModule = Module::where('slug', 'pos')->first();
        $this->assertTrue(Feature::where('module_id', $posModule->id)->exists());
    }

    public function test_business_types_are_seeded(): void
    {
        $this->assertGreaterThan(0, BusinessType::count());
        $this->assertTrue(BusinessType::where('slug', 'restaurant')->exists());
        $this->assertTrue(BusinessType::where('slug', 'general')->exists());
    }

    public function test_business_type_has_default_modules(): void
    {
        $restaurant = BusinessType::where('slug', 'restaurant')->first();
        $this->assertGreaterThan(0, $restaurant->modules()->count());
        $this->assertTrue($restaurant->modules()->where('slug', 'pos')->exists());
    }

    public function test_enable_module_creates_tenant_module(): void
    {
        $this->moduleService->enableModule($this->tenant->id, 'core');

        $this->assertTrue(TenantModule::where('tenant_id', $this->tenant->id)
            ->where('module_id', Module::where('slug', 'core')->value('id'))
            ->where('is_enabled', true)
            ->exists());
    }

    public function test_enable_module_with_dependencies(): void
    {
        $this->moduleService->enableModule($this->tenant->id, 'core');
        $this->moduleService->enableModule($this->tenant->id, 'inventory');
        $this->moduleService->enableModule($this->tenant->id, 'pos');

        $this->assertTrue($this->moduleService->isModuleEnabled($this->tenant->id, 'pos'));
    }

    public function test_enable_module_fails_without_dependency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('dependency');

        $this->moduleService->enableModule($this->tenant->id, 'pos');
    }

    public function test_disable_core_module_fails(): void
    {
        $this->moduleService->enableModule($this->tenant->id, 'core');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('core');

        $this->moduleService->disableModule($this->tenant->id, 'core');
    }

    public function test_disable_module_with_dependent_fails(): void
    {
        $this->moduleService->enableModule($this->tenant->id, 'core');
        $this->moduleService->enableModule($this->tenant->id, 'inventory');
        $this->moduleService->enableModule($this->tenant->id, 'pos');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('dependent');

        $this->moduleService->disableModule($this->tenant->id, 'inventory');
    }

    public function test_disable_module_succeeds_when_no_dependents(): void
    {
        $this->moduleService->enableModule($this->tenant->id, 'core');
        $this->moduleService->enableModule($this->tenant->id, 'inventory');
        $this->moduleService->enableModule($this->tenant->id, 'pos');

        $this->moduleService->disableModule($this->tenant->id, 'pos');
        $this->assertFalse($this->moduleService->isModuleEnabled($this->tenant->id, 'pos'));
    }

    public function test_enable_feature_requires_parent_module(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('parent module');

        $this->moduleService->enableFeature($this->tenant->id, 'pos.split_payment');
    }

    public function test_enable_feature_succeeds_with_parent_module(): void
    {
        $this->moduleService->enableModule($this->tenant->id, 'core');
        $this->moduleService->enableModule($this->tenant->id, 'inventory');
        $this->moduleService->enableModule($this->tenant->id, 'pos');

        $this->moduleService->enableFeature($this->tenant->id, 'pos.split_payment');
        $this->assertTrue($this->moduleService->isFeatureEnabled($this->tenant->id, 'pos.split_payment'));
    }

    public function test_disable_non_toggleable_feature_fails(): void
    {
        $this->moduleService->enableModule($this->tenant->id, 'core');
        $this->moduleService->enableModule($this->tenant->id, 'audit');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not owner-toggleable');

        $this->moduleService->disableFeature($this->tenant->id, 'audit.view');
    }

    public function test_apply_business_type_defaults(): void
    {
        $restaurant = BusinessType::where('slug', 'restaurant')->first();
        $this->moduleService->applyBusinessTypeDefaults($this->tenant->id, $restaurant->id);

        $this->assertTrue($this->moduleService->isModuleEnabled($this->tenant->id, 'core'));
        $this->assertTrue($this->moduleService->isModuleEnabled($this->tenant->id, 'pos'));
        $this->assertTrue($this->moduleService->isModuleEnabled($this->tenant->id, 'inventory'));
        $this->assertTrue($this->moduleService->isModuleEnabled($this->tenant->id, 'tables'));
    }

    public function test_get_enabled_module_slugs(): void
    {
        $this->moduleService->enableModule($this->tenant->id, 'core');
        $this->moduleService->enableModule($this->tenant->id, 'inventory');

        $slugs = $this->moduleService->getEnabledModuleSlugs($this->tenant->id);
        $this->assertContains('core', $slugs);
        $this->assertContains('inventory', $slugs);
    }

    public function test_get_enabled_feature_slugs(): void
    {
        $this->moduleService->enableModule($this->tenant->id, 'core');
        $this->moduleService->enableModule($this->tenant->id, 'inventory');
        $this->moduleService->enableModule($this->tenant->id, 'pos');
        $this->moduleService->enableFeature($this->tenant->id, 'pos.split_payment');

        $slugs = $this->moduleService->getEnabledFeatureSlugs($this->tenant->id);
        $this->assertContains('pos.split_payment', $slugs);
    }
}
