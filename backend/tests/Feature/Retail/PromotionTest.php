<?php

namespace Tests\Feature\Retail;

use App\Models\Promotion;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\TenantModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $manager;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $managerRole = Role::where('slug', 'manager')->first();

        $this->tenant = Tenant::create(['name' => 'P8 Retail', 'slug' => 'p8-retail']);

        $this->manager = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $managerRole->id,
            'name' => 'Manager',
            'email' => 'manager@p8retail.com',
            'password' => 'password',
        ]);

        Auth::login($this->manager);

        $this->enableModule('promotions');
        $this->token = $this->manager->createToken('test')->plainTextToken;
    }

    public function test_can_create_promotion(): void
    {
        $payload = [
            'name' => 'Weekend Discount',
            'type' => 'percentage',
            'value' => 10,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'rules' => [
                ['rule_type' => 'min_purchase', 'rule_value' => ['min' => 50000]],
            ],
        ];

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/promotions', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Weekend Discount');

        $this->assertDatabaseHas('promotions', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Weekend Discount',
        ]);
    }

    public function test_promotion_validation_requires_fields(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/promotions', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'type', 'value', 'start_date', 'end_date']);
    }

    public function test_can_activate_and_deactivate_promotion(): void
    {
        $promotion = Promotion::create([
            'name' => 'Flash Sale',
            'type' => 'fixed',
            'value' => 5000,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'is_active' => false,
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/promotions/{$promotion->id}/activate")
            ->assertStatus(200);

        $promotion->refresh();
        $this->assertTrue($promotion->is_active);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/promotions/{$promotion->id}/deactivate")
            ->assertStatus(200);

        $promotion->refresh();
        $this->assertFalse($promotion->is_active);
    }

    private function enableModule(string $slug): void
    {
        $module = \App\Models\Module::where('slug', $slug)->firstOrFail();
        TenantModule::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'module_id' => $module->id],
            ['is_enabled' => true]
        )->update(['is_enabled' => true]);
        TenantFeature::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'feature_id' => $module->features()->first()?->id ?? 0],
            ['is_enabled' => true]
        );
    }
}
