<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\FiscalPeriod;
use Illuminate\Database\Seeder;

class DefaultAccountsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (\App\Models\Tenant::all() as $tenant) {
            $this->seedForTenant($tenant->id);

            // Create a default open fiscal period for the current year
            $now = now();
            FiscalPeriod::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'start_date' => $now->copy()->startOfYear()->toDateString(),
                    'end_date' => $now->copy()->endOfYear()->toDateString(),
                ],
                [
                    'name' => $now->year . ' Fiscal Year',
                    'status' => 'open',
                ]
            );
        }
    }

    public function defaultAccounts(): array
    {
        return [
            ['code' => '1-0000', 'name' => 'Assets', 'type' => 'asset', 'parent_id' => null, 'is_system' => true],
            ['code' => '1-1000', 'name' => 'Cash on Hand', 'type' => 'asset', 'parent_code' => '1-0000', 'is_bank' => true, 'is_system' => true],
            ['code' => '1-1100', 'name' => 'Bank Account', 'type' => 'asset', 'parent_code' => '1-0000', 'is_bank' => true, 'is_system' => true],
            ['code' => '1-1200', 'name' => 'Accounts Receivable', 'type' => 'asset', 'parent_code' => '1-0000', 'is_system' => true],
            ['code' => '1-1300', 'name' => 'Inventory', 'type' => 'asset', 'parent_code' => '1-0000', 'is_system' => true],
            ['code' => '2-0000', 'name' => 'Liabilities', 'type' => 'liability', 'parent_id' => null, 'is_system' => true],
            ['code' => '2-1000', 'name' => 'Accounts Payable', 'type' => 'liability', 'parent_code' => '2-0000', 'is_system' => true],
            ['code' => '3-0000', 'name' => 'Equity', 'type' => 'equity', 'parent_id' => null, 'is_system' => true],
            ['code' => '3-1000', 'name' => 'Retained Earnings', 'type' => 'equity', 'parent_code' => '3-0000', 'is_system' => true],
            ['code' => '4-0000', 'name' => 'Revenue', 'type' => 'revenue', 'parent_id' => null, 'is_system' => true],
            ['code' => '4-1000', 'name' => 'Sales Revenue', 'type' => 'revenue', 'parent_code' => '4-0000', 'is_system' => true],
            ['code' => '4-2000', 'name' => 'Refund Allowance', 'type' => 'revenue', 'parent_code' => '4-0000', 'is_system' => true],
            ['code' => '5-0000', 'name' => 'Expenses', 'type' => 'expense', 'parent_id' => null, 'is_system' => true],
            ['code' => '5-1000', 'name' => 'Cost of Goods Sold', 'type' => 'expense', 'parent_code' => '5-0000', 'is_system' => true],
            ['code' => '5-1100', 'name' => 'Payment Gateway Fees', 'type' => 'expense', 'parent_code' => '5-0000', 'is_system' => true],
            ['code' => '5-1200', 'name' => 'Inventory Adjustment', 'type' => 'expense', 'parent_code' => '5-0000', 'is_system' => true],
            ['code' => '5-1300', 'name' => 'Cash Short Over', 'type' => 'expense', 'parent_code' => '5-0000', 'is_system' => true],
            ['code' => '5-1400', 'name' => 'Loyalty Expense', 'type' => 'expense', 'parent_code' => '5-0000', 'is_system' => true],
        ];
    }

    public function seedForTenant(int $tenantId, ?array $accounts = null): void
    {
        if (Account::where('tenant_id', $tenantId)->exists()) {
            return;
        }

        $accounts ??= $this->defaultAccounts();

        $byCode = [];

        foreach ($accounts as $account) {
            $parentCode = $account['parent_code'] ?? null;
            $parentId = $parentCode ? ($byCode[$parentCode] ?? null) : $account['parent_id'];

            $record = Account::create([
                'tenant_id' => $tenantId,
                'code' => $account['code'],
                'name' => $account['name'],
                'type' => $account['type'],
                'parent_id' => $parentId,
                'is_bank' => $account['is_bank'] ?? false,
                'is_system' => $account['is_system'] ?? false,
                'is_active' => true,
            ]);

            $byCode[$account['code']] = $record->id;
        }
    }
}
