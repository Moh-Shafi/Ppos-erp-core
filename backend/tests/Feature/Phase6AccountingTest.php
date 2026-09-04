<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\JournalEntryService;
use App\Services\ReportService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class Phase6AccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PlanSeeder::class);
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $this->seed(\Database\Seeders\DefaultAccountsSeeder::class);
    }

    private function makeTenantWithUser(string $roleSlug): array
    {
        $tenant = Tenant::create(['name' => 'Accounting Test Toko', 'slug' => 'accounting-test-' . uniqid()]);
        $role = Role::where('slug', $roleSlug)->first();

        $user = User::create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'name' => 'Accounting Test User',
            'email' => 'accounting.' . $roleSlug . '.' . uniqid() . '@test.com',
            'password' => 'password',
        ]);

        $this->seed(\Database\Seeders\DefaultAccountsSeeder::class);

        return [$tenant, $user];
    }

    public function test_default_accounts_seeded(): void
    {
        [$tenant] = $this->makeTenantWithUser('owner');

        $this->assertDatabaseHas('accounts', [
            'tenant_id' => $tenant->id,
            'code' => '1-1000',
        ]);

        $this->assertGreaterThanOrEqual(8, Account::where('tenant_id', $tenant->id)->count());
    }

    public function test_manual_journal_entry_balances(): void
    {
        [$tenant, $user] = $this->makeTenantWithUser('owner');

        $cash = Account::where('tenant_id', $tenant->id)->where('code', '1-1000')->first();
        $revenue = Account::where('tenant_id', $tenant->id)->where('code', '4-1000')->first();

        $this->assertNotNull($cash);
        $this->assertNotNull($revenue);

        $service = app(JournalEntryService::class);

        $entry = $service->post([
            'tenant_id' => $tenant->id,
            'entry_date' => now()->toDateString(),
            'description' => 'Test manual journal',
            'source' => 'manual',
            'posted_by' => $user->id,
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 100000, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 100000],
            ],
        ]);

        $this->assertEquals(100000, $entry->total_debit);
        $this->assertEquals(100000, $entry->total_credit);
        $this->assertCount(2, $entry->lines);
    }

    public function test_unbalanced_manual_journal_rejected(): void
    {
        [$tenant] = $this->makeTenantWithUser('owner');

        $cash = Account::where('tenant_id', $tenant->id)->where('code', '1-1000')->first();

        $this->assertNotNull($cash);

        $service = app(JournalEntryService::class);

        $this->expectException(\DomainException::class);

        $service->post([
            'tenant_id' => $tenant->id,
            'entry_date' => now()->toDateString(),
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 100000, 'credit' => 0],
            ],
        ]);
    }

    public function test_cash_sale_auto_journal(): void
    {
        [$tenant, $user] = $this->makeTenantWithUser('owner');

        $store = new Store();
        $store->tenant_id = $tenant->id;
        $store->name = 'Accounting Store';
        $store->code = 'AS';
        $store->is_active = true;
        $store->save();

        $category = new \App\Models\Category();
        $category->tenant_id = $tenant->id;
        $category->name = 'Accounting Category';
        $category->slug = 'accounting-category';
        $category->save();

        $product = new Product();
        $product->tenant_id = $tenant->id;
        $product->category_id = $category->id;
        $product->name = 'Accounting Test Product';
        $product->sku = 'ACC-TEST';
        $product->cost_price = 3000;
        $product->selling_price = 5000;
        $product->unit = 'pcs';
        $product->is_active = true;
        $product->save();

        $inv = new Inventory();
        $inv->tenant_id = $tenant->id;
        $inv->product_id = $product->id;
        $inv->store_id = $store->id;
        $inv->quantity = 100;
        $inv->minimum_quantity = 0;
        $inv->save();

        Auth::login($user);

        $sale = app(SaleService::class)->checkout([
            'store_id' => $store->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 5000],
            ],
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 5000],
            ],
        ]);

        $service = app(AccountingService::class);
        $entry = $service->postFor('Sale', $sale->id);

        $this->assertNotNull($entry);
        $this->assertEquals(5000, $entry->total_debit);
        $this->assertEquals(5000, $entry->total_credit);
        $this->assertDatabaseHas('journal_entries', [
            'tenant_id' => $tenant->id,
            'reference_type' => 'Sale',
            'reference_id' => $sale->id,
            'source' => 'auto',
        ]);
    }

    public function test_trial_balance_balances(): void
    {
        [$tenant] = $this->makeTenantWithUser('owner');

        $cash = Account::where('tenant_id', $tenant->id)->where('code', '1-1000')->first();
        $revenue = Account::where('tenant_id', $tenant->id)->where('code', '4-1000')->first();

        $this->assertNotNull($cash);
        $this->assertNotNull($revenue);

        $service = app(JournalEntryService::class);

        $service->post([
            'tenant_id' => $tenant->id,
            'entry_date' => now()->toDateString(),
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 100000, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 100000],
            ],
        ]);

        $reportService = app(ReportService::class);
        $report = $reportService->trialBalance($tenant->id, now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString());

        $this->assertTrue($report['is_balanced']);
        $this->assertEquals(100000, $report['total_debit']);
        $this->assertEquals(100000, $report['total_credit']);
    }

    public function test_profit_and_loss_report(): void
    {
        [$tenant] = $this->makeTenantWithUser('owner');

        $cash = Account::where('tenant_id', $tenant->id)->where('code', '1-1000')->first();
        $revenue = Account::where('tenant_id', $tenant->id)->where('code', '4-1000')->first();
        $cogs = Account::where('tenant_id', $tenant->id)->where('code', '5-1000')->first();

        $this->assertNotNull($cash);
        $this->assertNotNull($revenue);
        $this->assertNotNull($cogs);

        $service = app(JournalEntryService::class);

        $service->post([
            'tenant_id' => $tenant->id,
            'entry_date' => now()->toDateString(),
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 100000, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 100000],
            ],
        ]);

        $service->post([
            'tenant_id' => $tenant->id,
            'entry_date' => now()->toDateString(),
            'lines' => [
                ['account_id' => $cogs->id, 'debit' => 30000, 'credit' => 0],
                ['account_id' => $cash->id, 'debit' => 0, 'credit' => 30000],
            ],
        ]);

        $reportService = app(ReportService::class);
        $report = $reportService->profitAndLoss($tenant->id, now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString());

        $this->assertEquals(100000, $report['revenue']);
        $this->assertEquals(30000, $report['expenses']);
        $this->assertEquals(70000, $report['net_income']);
    }

    public function test_balance_sheet_balances(): void
    {
        [$tenant] = $this->makeTenantWithUser('owner');

        $cash = Account::where('tenant_id', $tenant->id)->where('code', '1-1000')->first();
        $revenue = Account::where('tenant_id', $tenant->id)->where('code', '4-1000')->first();
        $ap = Account::where('tenant_id', $tenant->id)->where('code', '2-1000')->first();
        $cogs = Account::where('tenant_id', $tenant->id)->where('code', '5-1000')->first();

        $this->assertNotNull($cash);
        $this->assertNotNull($revenue);
        $this->assertNotNull($ap);
        $this->assertNotNull($cogs);

        $service = app(JournalEntryService::class);

        $service->post([
            'tenant_id' => $tenant->id,
            'entry_date' => now()->toDateString(),
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 100000, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 100000],
            ],
        ]);

        $service->post([
            'tenant_id' => $tenant->id,
            'entry_date' => now()->toDateString(),
            'lines' => [
                ['account_id' => $cogs->id, 'debit' => 30000, 'credit' => 0],
                ['account_id' => $ap->id, 'debit' => 0, 'credit' => 30000],
            ],
        ]);

        $retained = Account::where('tenant_id', $tenant->id)->where('code', '3-1000')->first();
        if ($retained) {
            $service->post([
                'tenant_id' => $tenant->id,
                'entry_date' => now()->toDateString(),
                'lines' => [
                    ['account_id' => $revenue->id, 'debit' => 100000, 'credit' => 0],
                    ['account_id' => $cogs->id, 'debit' => 0, 'credit' => 30000],
                    ['account_id' => $retained->id, 'debit' => 0, 'credit' => 70000],
                ],
            ]);
        }

        $reportService = app(ReportService::class);
        $report = $reportService->balanceSheet($tenant->id, now()->toDateString());

        $this->assertTrue($report['is_balanced']);
        $this->assertEquals(100000, $report['assets']);
    }

    public function test_fiscal_period_close_blocks_posting(): void
    {
        [$tenant] = $this->makeTenantWithUser('owner');

        FiscalPeriod::create([
            'tenant_id' => $tenant->id,
            'name' => '2026-Q1',
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
            'status' => 'closed',
        ]);

        $cash = Account::where('tenant_id', $tenant->id)->where('code', '1-1000')->first();
        $revenue = Account::where('tenant_id', $tenant->id)->where('code', '4-1000')->first();

        $this->assertNotNull($cash);
        $this->assertNotNull($revenue);

        $service = app(JournalEntryService::class);

        $this->expectException(\DomainException::class);

        $service->post([
            'tenant_id' => $tenant->id,
            'entry_date' => '2026-01-15',
            'lines' => [
                ['account_id' => $cash->id, 'debit' => 100000, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 100000],
            ],
        ]);
    }

    public function test_accountant_can_view_finance_accounts(): void
    {
        [$tenant, $user] = $this->makeTenantWithUser('accountant');

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/finance/accounts');

        $response->assertStatus(200);
    }
}
