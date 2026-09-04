<?php

namespace Tests\Feature\Restaurant;

use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Store;
use App\Models\TableArea;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TableTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Store $store;
    private User $manager;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\ModuleSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $managerRole = Role::where('slug', 'manager')->first();

        $this->tenant = Tenant::create(['name' => 'P8 Table', 'slug' => 'p8-table']);

        $this->manager = User::create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $managerRole->id,
            'name' => 'Manager',
            'email' => 'manager@p8table.com',
            'password' => 'password',
        ]);

        $store = new Store;
        $store->tenant_id = $this->tenant->id;
        $store->name = 'Main';
        $store->code = 'MS01';
        $store->is_active = true;
        $store->save();
        $this->store = $store;

        Auth::login($this->manager);

        $module = \App\Models\Module::where('slug', 'tables')->firstOrFail();
        TenantModule::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'module_id' => $module->id],
            ['is_enabled' => true]
        )->update(['is_enabled' => true]);

        $this->token = $this->manager->createToken('test')->plainTextToken;
    }

    public function test_can_create_area_and_table(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/tables/areas', ['store_id' => $this->store->id, 'name' => 'Garden'])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Garden');

        $area = TableArea::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($area);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/tables', [
                'store_id' => $this->store->id,
                'table_area_id' => $area->id,
                'name' => 'G-1',
                'code' => 'G1-' . uniqid(),
                'capacity' => 4,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'G-1');

        $this->assertDatabaseHas('tables', [
            'tenant_id' => $this->tenant->id,
            'table_area_id' => $area->id,
            'name' => 'G-1',
        ]);
    }

    public function test_can_update_table_status(): void
    {
        $area = TableArea::create(['store_id' => $this->store->id, 'name' => 'Indoor']);
        $table = RestaurantTable::create([
            'store_id' => $this->store->id,
            'table_area_id' => $area->id,
            'name' => 'I-1',
            'code' => 'I1-' . uniqid(),
            'capacity' => 2,
            'status' => 'available',
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/tables/{$table->id}/status", ['status' => 'occupied'])
            ->assertStatus(200);

        $table->refresh();
        $this->assertEquals('occupied', $table->status);
    }
}
