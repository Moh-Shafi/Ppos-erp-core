<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\UnitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase1UnitTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Tenant $tenant;
    private UnitService $unitService;

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

        $this->actingAs($this->owner, 'sanctum');

        $this->unitService = app(UnitService::class);
    }

    public function test_create_unit(): void
    {
        $unit = $this->unitService->createUnit([
            'name' => 'Pieces',
            'symbol' => 'pcs',
            'is_base_unit' => true,
        ], $this->tenant->id);

        $this->assertEquals('Pieces', $unit->name);
        $this->assertEquals('pcs', $unit->symbol);
        $this->assertTrue($unit->is_base_unit);
    }

    public function test_create_unit_unique_symbol_per_tenant(): void
    {
        $this->unitService->createUnit([
            'name' => 'Pieces',
            'symbol' => 'pcs',
        ], $this->tenant->id);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->unitService->createUnit([
            'name' => 'Pieces2',
            'symbol' => 'pcs',
        ], $this->tenant->id);
    }

    public function test_add_conversion(): void
    {
        $pcs = $this->unitService->createUnit(['name' => 'Pieces', 'symbol' => 'pcs'], $this->tenant->id);
        $dus = $this->unitService->createUnit(['name' => 'Dus', 'symbol' => 'dus'], $this->tenant->id);

        $conversion = $this->unitService->addConversion($dus->id, $pcs->id, 12, $this->tenant->id);

        $this->assertEquals('12.0000', $conversion->factor);
    }

    public function test_convert_quantity(): void
    {
        $pcs = $this->unitService->createUnit(['name' => 'Pieces', 'symbol' => 'pcs'], $this->tenant->id);
        $dus = $this->unitService->createUnit(['name' => 'Dus', 'symbol' => 'dus'], $this->tenant->id);

        $this->unitService->addConversion($dus->id, $pcs->id, 12, $this->tenant->id);

        $result = $this->unitService->convert(2, $dus->id, $pcs->id, $this->tenant->id);

        $this->assertEquals(24.0, $result);
    }

    public function test_convert_reverse(): void
    {
        $pcs = $this->unitService->createUnit(['name' => 'Pieces', 'symbol' => 'pcs'], $this->tenant->id);
        $dus = $this->unitService->createUnit(['name' => 'Dus', 'symbol' => 'dus'], $this->tenant->id);

        $this->unitService->addConversion($dus->id, $pcs->id, 12, $this->tenant->id);

        $result = $this->unitService->convert(24, $pcs->id, $dus->id, $this->tenant->id);

        $this->assertEquals(2.0, $result);
    }

    public function test_convert_no_conversion_defined(): void
    {
        $pcs = $this->unitService->createUnit(['name' => 'Pieces', 'symbol' => 'pcs'], $this->tenant->id);
        $kg = $this->unitService->createUnit(['name' => 'Kilogram', 'symbol' => 'kg'], $this->tenant->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->unitService->convert(5, $pcs->id, $kg->id, $this->tenant->id);
    }

    public function test_delete_unit_in_use_blocked(): void
    {
        $category = Category::create([
            'name' => 'Test',
            'slug' => 'test',
            'is_active' => true,
        ]);

        $pcs = $this->unitService->createUnit(['name' => 'Pieces', 'symbol' => 'pcs'], $this->tenant->id);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Test Product',
            'cost_price' => 5000,
            'selling_price' => 10000,
            'unit' => 'pcs',
            'base_unit_id' => $pcs->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot delete unit that is assigned to products');
        $this->unitService->deleteUnit($pcs->id, $this->tenant->id);
    }

    public function test_delete_unit_not_in_use(): void
    {
        $pcs = $this->unitService->createUnit(['name' => 'Pieces', 'symbol' => 'pcs'], $this->tenant->id);

        $this->unitService->deleteUnit($pcs->id, $this->tenant->id);

        $this->assertDatabaseMissing('units', ['id' => $pcs->id]);
    }

    public function test_api_list_units(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $this->unitService->createUnit(['name' => 'Pieces', 'symbol' => 'pcs'], $this->tenant->id);

        $response = $this->getJson('/api/v1/units');

        $response->assertStatus(200);
    }

    public function test_api_create_unit(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $response = $this->postJson('/api/v1/units', [
            'name' => 'Pieces',
            'symbol' => 'pcs',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('unit.name', 'Pieces');
    }

    public function test_api_create_conversion(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $pcs = $this->unitService->createUnit(['name' => 'Pieces', 'symbol' => 'pcs'], $this->tenant->id);
        $dus = $this->unitService->createUnit(['name' => 'Dus', 'symbol' => 'dus'], $this->tenant->id);

        $response = $this->postJson('/api/v1/units/conversions', [
            'from_unit_id' => $dus->id,
            'to_unit_id' => $pcs->id,
            'factor' => 12,
        ]);

        $response->assertStatus(201);
    }
}
