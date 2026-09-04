<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Reports\AuthorizedStoreScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase7ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;
    protected Tenant $tenant;

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

        $owner = Role::where('slug', 'owner')->first();

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => $owner->id,
        ]);

        $this->actingAs($this->user);

        $this->store = new Store([
            'name' => 'Main Store',
            'code' => Str::random(10),
            'is_active' => true,
        ]);
        $this->store->tenant_id = $this->tenant->id;
        $this->store->save();
    }

    public function test_unregistered_report_id_rejected(): void
    {
        $this->getJson('/api/v1/reports/unknown-report')
            ->assertStatus(400);
    }

    public function test_sales_report_returns_data(): void
    {
        Sale::create([
            'tenant_id' => $this->tenant->id,
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

        $this->getJson('/api/v1/reports/sales?date_from='.now()->subWeek()->toDateString().'&date_to='.now()->toDateString())
            ->assertStatus(200)
            ->assertJsonPath('report', 'sales');
    }

    public function test_unauthorized_store_blocked(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other',
            'slug' => Str::random(10),
            'plan_id' => $this->tenant->plan_id,
        ]);

        $otherStore = new Store([
            'name' => 'Other Store',
            'code' => Str::random(10),
            'is_active' => true,
        ]);
        $otherStore->tenant_id = $otherTenant->id;
        $otherStore->save();

        $this->getJson("/api/v1/reports/sales?store_id={$otherStore->id}")
            ->assertStatus(422);
    }

    public function test_trial_balance_balances(): void
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

        $fiscal = FiscalPeriod::create([
            'tenant_id' => $this->tenant->id,
            'name' => '2026-08',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'status' => 'open',
        ]);

        $entry = JournalEntry::create([
            'tenant_id' => $this->tenant->id,
            'entry_number' => 'JE-001',
            'entry_date' => now()->subDay(),
            'fiscal_period_id' => $fiscal->id,
            'source' => 'manual',
            'description' => 'Test entry',
            'total_debit' => 100000,
            'total_credit' => 100000,
            'posted_by' => $this->user->id,
            'posted_at' => now(),
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $cash->id,
            'debit' => 100000,
            'credit' => 0,
            'description' => 'cash',
        ]);

        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $revenue->id,
            'debit' => 0,
            'credit' => 100000,
            'description' => 'revenue',
        ]);

        $this->getJson('/api/v1/reports/trial-balance')
            ->assertStatus(200)
            ->assertJsonPath('report', 'trial-balance');
    }

    public function test_store_scope_enforces_tenant_isolation(): void
    {
        $scope = AuthorizedStoreScope::forUser($this->user);

        $this->assertTrue($scope->contains($this->store->id));
        $this->assertFalse($scope->contains(9999));
    }
}
