<?php

namespace Tests\Unit;

use App\Models\Plan;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Reports\AuthorizedStoreScope;
use App\Services\Reports\Definitions\SalesReportDefinition;
use App\Services\Reports\ReportContext;
use App\Services\Reports\ReportEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase7RuntimeIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Store $store;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::first() ?? Plan::create(['name' => 'Basic', 'slug' => 'basic']);

        $this->tenant = Tenant::create([
            'name' => 'Isolated',
            'slug' => Str::random(10),
            'plan_id' => $plan->id,
        ]);

        $this->store = new Store([
            'name' => 'Main',
            'code' => Str::random(10),
            'is_active' => true,
        ]);
        $this->store->tenant_id = $this->tenant->id;
        $this->store->save();

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $sale = new Sale([
            'store_id' => $this->store->id,
            'cashier_id' => $this->user->id,
            'customer_id' => null,
            'sale_number' => Str::random(10),
            'status' => 'completed',
            'payment_status' => 'unpaid',
            'sale_date' => now()->subDay(),
            'subtotal' => 100000,
            'discount' => 0,
            'tax' => 0,
            'total' => 100000,
            'paid_amount' => 0,
            'change_amount' => 0,
        ]);
        $sale->tenant_id = $this->tenant->id;
        $sale->save();
    }

    public function test_raw_sales_query(): void
    {
        $rows = DB::table('sales')
            ->where('tenant_id', $this->tenant->id)
            ->limit(1)
            ->get();

        $this->assertNotNull($rows);
    }

    public function test_sales_report_definition_query(): void
    {
        $definition = new SalesReportDefinition();
        $scope = AuthorizedStoreScope::forUser($this->user);
        $ctx = new ReportContext(user: $this->user, filters: [
            'date_from' => now()->subWeek()->toDateString(),
            'date_to' => now()->toDateString(),
        ]);

        $query = $definition->query($ctx, $scope);

        $sql = $query->toSql();
        $bindings = $query->getBindings();

        logger()->info('Sales report query', ['sql' => $sql, 'bindings' => $bindings]);

        $rows = $query->get();

        $this->assertNotNull($rows);
    }

    public function test_sales_report_query_paginate(): void
    {
        $definition = new SalesReportDefinition();
        $scope = AuthorizedStoreScope::forUser($this->user);
        $ctx = new ReportContext(user: $this->user, filters: [
            'date_from' => now()->subWeek()->toDateString(),
            'date_to' => now()->toDateString(),
        ]);

        $query = $definition->query($ctx, $scope);

        $page = $query->paginate(15);

        $this->assertNotNull($page);
    }

    public function test_report_engine_runs(): void
    {
        $engine = app(ReportEngine::class);
        $scope = AuthorizedStoreScope::forUser($this->user);
        $ctx = new ReportContext(user: $this->user, filters: [
            'date_from' => now()->subWeek()->toDateString(),
            'date_to' => now()->toDateString(),
        ]);

        $result = $engine->run('sales', $ctx, $scope);

        $this->assertEquals('sales', $result->report);
    }
}
