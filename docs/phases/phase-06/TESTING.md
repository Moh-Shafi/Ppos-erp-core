# Phase 6 — Finance / Accounting — Testing

**Document Status:** DRAFT  
**Created:** 2026-08-15

---

## 1. Backend Tests

### 1.1 Test File

| File | Count | Description |
|------|-------|-------------|
| `tests/Feature/Phase6AccountingTest.php` | 9 | Chart of Accounts, manual journals, auto-posting, Trial Balance, P&L, Balance Sheet, fiscal period close, RBAC |

### 1.2 Test Cases

- `test_default_accounts_seeded`
- `test_manual_journal_entry_balances`
- `test_unbalanced_manual_journal_rejected`
- `test_cash_sale_auto_journal`
- `test_trial_balance_balances`
- `test_profit_and_loss_report`
- `test_balance_sheet_balances`
- `test_fiscal_period_close_blocks_posting`
- `test_accountant_can_view_finance_accounts`

### 1.3 Running Tests

```bash
docker exec pos_saas_backend php artisan test --filter=Phase6AccountingTest
```

## 2. E2E Tests

### 2.1 Test File

| File | Count | Description |
|------|-------|-------------|
| `frontend/e2e/phase6.spec.ts` | 4 | Chart of Accounts, manual journal, Trial Balance, P&L/Balance Sheet via API |

### 2.2 Test Cases

- Chart of Accounts returns default accounts
- Manual journal entry is posted and balanced
- Trial Balance is balanced after posting
- Profit & Loss and Balance Sheet endpoints return data

### 2.3 Running E2E

```bash
cd frontend
npx playwright test phase6.spec.ts
```

## 3. Regression Gate

Full backend regression run via:

```bash
docker exec pos_saas_backend php artisan test
```

*Include final result in Phase 6 Final Audit Report.*
