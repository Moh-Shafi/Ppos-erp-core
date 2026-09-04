<?php

namespace Tests\Feature;

use App\Models\DashboardWidget;
use App\Models\Plan;
use App\Models\ReportConfig;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase7DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $staff;
    protected Tenant $tenant;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\ModuleSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);

        $plan = Plan::first() ?? Plan::create(['name' => 'Basic', 'slug' => 'basic']);

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => Str::random(10),
            'plan_id' => $plan->id,
        ]);

        $ownerRole = Role::where('slug', 'owner')->first();
        $staffRole = Role::where('slug', 'staff')->first();

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $ownerRole->id,
        ]);

        $this->staff = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $staffRole->id,
        ]);

        $this->actingAs($this->owner);

        $this->store = new Store([
            'name' => 'Main Store',
            'code' => Str::random(10),
            'is_active' => true,
        ]);
        $this->store->tenant_id = $this->tenant->id;
        $this->store->save();
    }

    public function test_dashboard_loads_widgets_and_kpis(): void
    {
        Sale::create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'cashier_id' => $this->owner->id,
            'customer_id' => null,
            'sale_number' => Str::random(10),
            'status' => 'completed',
            'payment_status' => 'unpaid',
            'sale_date' => now()->toDateString(),
            'subtotal' => 100000,
            'discount' => 0,
            'tax' => 0,
            'total' => 100000,
            'paid_amount' => 0,
            'change_amount' => 0,
        ]);

        DashboardWidget::create([
            'user_id' => $this->owner->id,
            'type' => 'kpi',
            'kpi_id' => 'today-revenue',
            'filters' => [],
            'position' => ['x' => 0, 'y' => 0],
        ]);

        $this->getJson('/api/v1/reports/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('date_range.from', null)
            ->assertJsonCount(1, 'widgets');

        $this->getJson('/api/v1/reports/kpis')
            ->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    public function test_widget_crud_is_tenant_and_user_isolated(): void
    {
        $create = $this->postJson('/api/v1/reports/dashboard/widgets', [
            'type' => 'kpi',
            'kpi_id' => 'total-sales',
            'position' => ['x' => 0, 'y' => 1],
        ]);

        $create->assertStatus(201);
        $widgetId = $create->json('data.id');

        $this->getJson('/api/v1/reports/dashboard/widgets')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/v1/reports/dashboard/widgets/{$widgetId}")
            ->assertStatus(200)
            ->assertJsonPath('data.kpi_id', 'total-sales');

        $this->putJson("/api/v1/reports/dashboard/widgets/{$widgetId}", [
            'position' => ['x' => 1, 'y' => 1],
        ])->assertStatus(200);

        $this->deleteJson("/api/v1/reports/dashboard/widgets/{$widgetId}")
            ->assertStatus(204);
    }

    public function test_staff_cannot_manage_dashboard_widgets(): void
    {
        $this->actingAs($this->staff);

        $this->postJson('/api/v1/reports/dashboard/widgets', [
            'type' => 'kpi',
            'kpi_id' => 'total-sales',
            'position' => [],
        ])->assertStatus(403);
    }

    public function test_report_config_crud_is_isolated(): void
    {
        $create = $this->postJson('/api/v1/reports/report-configs', [
            'name' => 'Weekly Sales',
            'report_id' => 'sales',
            'filters' => ['date_from' => '2026-08-01', 'date_to' => '2026-08-07'],
        ]);

        $create->assertStatus(201);
        $configId = $create->json('data.id');

        $this->getJson('/api/v1/reports/report-configs')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/v1/reports/report-configs/{$configId}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Weekly Sales');

        $this->putJson("/api/v1/reports/report-configs/{$configId}", [
            'name' => 'Monthly Sales',
        ])->assertStatus(200);

        $this->deleteJson("/api/v1/reports/report-configs/{$configId}")
            ->assertStatus(204);
    }

    public function test_unauthorized_user_cannot_access_report_configs(): void
    {
        $this->actingAs($this->staff);

        $this->postJson('/api/v1/reports/report-configs', [
            'name' => 'Config',
            'report_id' => 'sales',
            'filters' => [],
        ])->assertStatus(403);
    }
}
