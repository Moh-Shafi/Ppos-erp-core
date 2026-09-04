<?php

namespace Tests\Feature\Phase8;

use App\Models\Feature;
use App\Models\Module;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\TenantModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleGatingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $ownerRole = Role::where('slug', 'owner')->first();

        $this->tenant = Tenant::create(['name' => 'P8 Gating', 'slug' => 'p8-gating']);

        $this->owner = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $ownerRole->id,
            'name' => 'Owner',
            'email' => 'owner@p8gating.com',
            'password' => 'password',
        ]);

        $this->token = $this->owner->createToken('test')->plainTextToken;
    }

    public function test_module_disabled_returns_403(): void
    {
        $this->disableModule('tables');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/tables')
            ->assertStatus(403);
    }

    public function test_module_enabled_returns_200(): void
    {
        $this->enableModule('tables');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/tables')
            ->assertStatus(200);
    }

    public function test_feature_disabled_returns_403(): void
    {
        $this->enableModule('tables');
        $this->disableFeature('tables.qr_ordering');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/tables/1/qr-code')
            ->assertStatus(403);
    }

    public function test_permission_denied_returns_403(): void
    {
        $this->enableModule('tables');

        $staffRole = Role::where('slug', 'staff')->first();
        $staff = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $staffRole->id,
            'name' => 'Staff',
            'email' => 'staff@p8gating.com',
            'password' => 'password',
        ]);
        $token = $staff->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/tables', ['name' => 'New Table'])
            ->assertStatus(403);
    }

    private function enableModule(string $slug): void
    {
        $module = Module::where('slug', $slug)->firstOrFail();
        TenantModule::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'module_id' => $module->id],
            ['is_enabled' => true]
        )->update(['is_enabled' => true]);
    }

    private function disableModule(string $slug): void
    {
        $module = Module::where('slug', $slug)->firstOrFail();
        TenantModule::updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'module_id' => $module->id],
            ['is_enabled' => false]
        );
    }

    private function disableFeature(string $slug): void
    {
        $feature = Feature::where('slug', $slug)->firstOrFail();
        TenantFeature::updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'feature_id' => $feature->id],
            ['is_enabled' => false]
        );
    }
}
