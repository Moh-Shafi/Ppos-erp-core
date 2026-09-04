<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Reports\AuthorizedStoreScope;
use App\Services\Reports\ReportContext;
use App\Services\Reports\ReportEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase7ReportEngineTest extends TestCase
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
            'name' => 'Test Tenant',
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
    }

    public function test_unknown_report_throws(): void
    {
        $this->expectException(\App\Services\Reports\Exceptions\UnregisteredReportException::class);

        $engine = app(ReportEngine::class);
        $scope = AuthorizedStoreScope::forUser($this->user);
        $ctx = new ReportContext(user: $this->user, filters: []);

        $engine->run('unknown', $ctx, $scope);
    }

    public function test_sales_report_runs(): void
    {
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

        $engine = app(ReportEngine::class);
        $scope = AuthorizedStoreScope::forUser($this->user);
        $ctx = new ReportContext(user: $this->user, filters: [
            'date_from' => now()->subWeek()->toDateString(),
            'date_to' => now()->toDateString(),
        ]);

        $result = $engine->run('sales', $ctx, $scope);

        $this->assertEquals('sales', $result->report);
        $this->assertNotNull($result->data);
    }

    public function test_unauthorized_store_rejected(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other',
            'slug' => Str::random(10),
            'plan_id' => $this->tenant->plan_id,
        ]);

        $otherStore = new Store([
            'name' => 'Other',
            'code' => Str::random(10),
            'is_active' => true,
        ]);
        $otherStore->tenant_id = $otherTenant->id;
        $otherStore->save();

        $this->expectException(\InvalidArgumentException::class);

        $engine = app(ReportEngine::class);
        $scope = AuthorizedStoreScope::forUser($this->user);
        $ctx = new ReportContext(user: $this->user, filters: ['store_id' => $otherStore->id]);

        $engine->run('sales', $ctx, $scope);
    }

    public function test_trial_balance_runs(): void
    {
        $cash = Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1100',
            'name' => 'Cash',
            'type' => 'asset',
            'is_bank' => true,
            'is_system' => false,
            'is_active' => true,
        ]);

        $revenue = Account::create([
            'tenant_id' => $this->tenant->id,
            'code' => '4000',
            'name' => 'Revenue',
            'type' => 'revenue',
            'is_system' => false,
            'is_active' => true,
        ]);

        $entry = JournalEntry::create([
            'tenant_id' => $this->tenant->id,
            'entry_number' => 'JE-001',
            'entry_date' => now()->subDay(),
            'fiscal_period_id' => null,
            'source' => 'manual',
            'description' => 'test',
            'total_debit' => 100000,
            'total_credit' => 100000,
            'posted_by' => $this->user->id,
            'posted_at' => now(),
        ]);

        $line1 = new JournalEntryLine([
            'journal_entry_id' => $entry->id,
            'account_id' => $cash->id,
            'debit' => 100000,
            'credit' => 0,
        ]);
        $line1->tenant_id = $this->tenant->id;
        $line1->save();

        $line2 = new JournalEntryLine([
            'journal_entry_id' => $entry->id,
            'account_id' => $revenue->id,
            'debit' => 0,
            'credit' => 100000,
        ]);
        $line2->tenant_id = $this->tenant->id;
        $line2->save();

        $engine = app(ReportEngine::class);
        $scope = AuthorizedStoreScope::forUser($this->user);
        $ctx = new ReportContext(user: $this->user, filters: []);

        $result = $engine->run('trial-balance', $ctx, $scope);

        $this->assertEquals('trial-balance', $result->report);
        $this->assertNotNull($result->data);
    }
}
