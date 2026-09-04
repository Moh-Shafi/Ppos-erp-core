# Phase 6 — Finance / Accounting — PDR

**Document Status:** APPROVED — CLOSED  
**Created:** 2026-08-15  
**Approved:** 2026-08-15  
**Phase:** 6 — Finance / Accounting  
**Depends On:** Phase 5 (Payment Infrastructure), Phase 4 (POS Enhancement), Phase 3 (CRM & Purchasing)  
**Roadmap Reference:** `docs/PDR/02-PHASE_ROADMAP.md` — Phase 6

---

## 1. OBJECTIVE

Build a **multi-tenant double-entry accounting system** that records financial impact of all existing operational transactions (sales, purchases, returns, refunds, payments, credit, loyalty, inventory, cash drawer) in a structured general ledger. The system must provide the foundation for **AR/AP, Profit & Loss (P&L), Balance Sheet, and Trial Balance** reporting without rewriting existing domain services.

Phase 6 delivers:
- **Chart of Accounts (CoA)** — hierarchical, tenant-scoped, account types (asset, liability, equity, revenue, expense)
- **Journal Entries** — double-entry, balanced by tenant, source-document linked
- **General Ledger** — per-account transaction history
- **Accounts Receivable (AR)** — from customer sales on credit
- **Accounts Payable (AP)** — from supplier purchases/invoices
- **Trial Balance** — debit/credit totals per account
- **Profit & Loss Statement** — revenue vs expense over a period
- **Balance Sheet** — assets, liabilities, equity at a point in time
- **Auto-journal generation** from sales, purchases, refunds, payments, inventory adjustments
- **Manual journal entry** for adjustments, accruals, corrections

---

## 2. DESIGN PRINCIPLES

### 2.1 Double-Entry Everywhere

Every financial event produces at least two `JournalEntryLine` rows: one debit and one credit. Total debits must equal total credits per journal entry.

```
Sale (cash)
  Debit  : Cash                              1,000,000
  Credit : Revenue                           1,000,000

Purchase on credit
  Debit  : Inventory / Cost of Goods Sold      750,000
  Credit : Accounts Payable                    750,000

Payment received on AR
  Debit  : Cash                                500,000
  Credit : Accounts Receivable                 500,000
```

### 2.2 Source-Document Linking

Each journal entry records the source model (`Sale`, `Purchase`, `Payment`, `SupplierInvoice`, `CustomerCreditTransaction`, `InventoryMovement`, etc.) via `reference_type` and `reference_id`. This creates an immutable audit trail from financial posting back to operational document.

### 2.3 Event-Driven Posting

Accountants do not manually enter every sale. Instead, `AccountingService` observers or hooks in existing services create journal entries when operational events happen. Manual journal entries are allowed only for adjustments and period-end closing.

### 2.4 Tenant Isolation

Every accounting table is scoped by `tenant_id` using `BelongsToTenant`. CoA codes, journal numbers, and account numbers are unique per tenant.

### 2.5 Multi-Currency Foundation (IDR Default)

All amounts stored in base currency (IDR). A `currency` and `exchange_rate` field is added to `JournalEntryLine` for future multi-currency support, but Phase 6 operates exclusively in IDR.

---

## 3. SCOPE

### 3.1 In Scope

| Item | Description |
|------|-------------|
| Chart of Accounts | CRUD, account types, parent/child hierarchy, default CoA seeding per business type |
| Journal Entries | Double-entry, balanced validation, source-document linking |
| General Ledger | Ledger entries per account, filterable by date, source |
| AR | Track customer credit sales and payments |
| AP | Track supplier purchases and supplier invoices |
| Trial Balance | Sum of debits/credits per account for a period |
| Profit & Loss | Revenue - Expense over a period |
| Balance Sheet | Assets, Liabilities, Equity at a date |
| Auto-posting | From `Sale`, `Purchase`, `Payment`, `SaleRefund`, `CustomerCreditTransaction`, `SupplierInvoice`, `InventoryMovement` |
| Manual journal | Authorized accountants can post adjusting entries |
| Fiscal periods | Open/closed accounting periods to lock historical data |

### 3.2 Out of Scope (Future Phases)

| Item | Reason |
|------|--------|
| Tax reporting (VAT/GST/SST) | Phase 8 / country-specific module |
| Multi-currency transactions | Foundation only; full support in Phase 8 |
| Payroll / salary accounting | Phase 9 (HR/Payroll) |
| Fixed asset depreciation | Phase 8 (Business-Specific) |
| Bank reconciliation | Future enhancement beyond settlement reconciliation |
| Advanced cost accounting (ABC) | Future enhancement |

---

## 4. DATABASE CHANGES

### 4.1 New Tables

#### `accounts` (Chart of Accounts)

| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned PK | Auto-increment |
| tenant_id | FK → tenants | cascadeOnDelete |
| code | varchar(50) | Account code, unique per tenant |
| name | varchar(255) | Human-readable name |
| type | enum | `asset`, `liability`, `equity`, `revenue`, `expense` |
| parent_id | FK → accounts nullable | Hierarchy |
| is_bank | boolean | Default false — for cash/bank accounts |
| is_system | boolean | Default false — system accounts cannot be deleted |
| is_active | boolean | Default true |
| metadata | json nullable | Extra config |

**Indexes:** `unique(tenant_id, code)`, `index(tenant_id, type)`, `index(tenant_id, parent_id)`

#### `fiscal_periods`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned PK | Auto-increment |
| tenant_id | FK → tenants | cascadeOnDelete |
| name | varchar(100) | e.g. "2026-Q1" |
| start_date | date | |
| end_date | date | |
| status | enum | `open`, `closed` |

**Indexes:** `unique(tenant_id, start_date, end_date)`, `index(tenant_id, status)`

#### `journal_entries`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned PK | Auto-increment |
| tenant_id | FK → tenants | cascadeOnDelete |
| entry_number | varchar(100) | Unique per tenant |
| entry_date | date | Posting date |
| fiscal_period_id | FK → fiscal_periods nullable | |
| reference_type | varchar(100) | e.g. `Sale`, `Purchase`, `Payment` |
| reference_id | bigint unsigned | Source document ID |
| source | enum | `auto`, `manual` |
| description | text | |
| total_debit | decimal(15,2) | Must equal total_credit |
| total_credit | decimal(15,2) | |
| posted_by | FK → users | User who posted (nullable for auto) |
| posted_at | timestamp | |

**Indexes:** `unique(tenant_id, entry_number)`, `index(tenant_id, reference_type, reference_id)`, `index(tenant_id, entry_date)`

#### `journal_entry_lines`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned PK | Auto-increment |
| tenant_id | FK → tenants | cascadeOnDelete |
| journal_entry_id | FK → journal_entries | cascadeOnDelete |
| account_id | FK → accounts | |
| line_number | smallint | Display order |
| debit | decimal(15,2) | Default 0 |
| credit | decimal(15,2) | Default 0 |
| description | text | |
| reference_type | varchar(100) | Optional sub-reference |
| reference_id | bigint unsigned | Optional sub-reference |
| currency | varchar(3) | Default `IDR` |
| exchange_rate | decimal(18,6) | Default 1.0 |

**Indexes:** `index(tenant_id, account_id)`, `index(journal_entry_id)`, `index(tenant_id, reference_type, reference_id)`

#### `account_balances` (pre-computed running balances)

| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned PK | Auto-increment |
| tenant_id | FK → tenants | cascadeOnDelete |
| account_id | FK → accounts | cascadeOnDelete |
| fiscal_period_id | FK → fiscal_periods | cascadeOnDelete |
| opening_balance | decimal(15,2) | Balance at start of period |
| period_debits | decimal(15,2) | Sum of debits in period |
| period_credits | decimal(15,2) | Sum of credits in period |
| closing_balance | decimal(15,2) | Computed |

**Indexes:** `unique(tenant_id, account_id, fiscal_period_id)`

### 4.2 Modified Tables

None — Phase 6 creates new accounting tables only. Operational tables (`sales`, `purchases`, `payments`, etc.) are read to generate journal entries.

---

## 5. FEATURE FLAGS

| Flag | Description | Default |
|------|-------------|---------|
| `finance.chart_of_accounts` | Enable CoA management | Enabled |
| `finance.journal_entries` | Enable journal entries and general ledger | Enabled |
| `finance.ar` | Enable accounts receivable tracking | Enabled |
| `finance.ap` | Enable accounts payable tracking | Enabled |
| `finance.financial_reports` | Enable P&L, Balance Sheet, Trial Balance | Enabled |
| `finance.manual_journals` | Enable manual journal entries | Enabled |
| `finance.fiscal_periods` | Enable open/close periods | Enabled |

---

## 6. PERMISSIONS (RBAC)

| Permission | Description | Roles |
|------------|-------------|-------|
| `finance.view` | View accounts, ledger, reports | Owner, Manager, Accountant |
| `finance.manage` | Create/edit accounts and fiscal periods | Owner, Manager, Accountant |
| `finance.post_journals` | Post manual journal entries | Owner, Manager, Accountant |
| `finance.close_period` | Close fiscal periods | Owner |
| `finance.reports` | View P&L, Balance Sheet, Trial Balance | Owner, Manager, Accountant |

---

## 7. API ENDPOINTS

### 7.1 Chart of Accounts

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/finance/accounts` | `finance.view` | List accounts (tree or flat) |
| POST | `/finance/accounts` | `finance.manage` | Create account |
| PUT | `/finance/accounts/{id}` | `finance.manage` | Update account |
| DELETE | `/finance/accounts/{id}` | `finance.manage` | Delete non-system account |
| GET | `/finance/accounts/{id}/ledger` | `finance.view` | General ledger for account |

### 7.2 Journal Entries

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/finance/journal-entries` | `finance.view` | List journal entries |
| POST | `/finance/journal-entries` | `finance.post_journals` | Post manual journal entry |
| GET | `/finance/journal-entries/{id}` | `finance.view` | View journal entry with lines |
| POST | `/finance/journal-entries/{id}/reverse` | `finance.post_journals` | Reverse a manual entry |

### 7.3 Fiscal Periods

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/finance/fiscal-periods` | `finance.view` | List periods |
| POST | `/finance/fiscal-periods` | `finance.manage` | Create period |
| POST | `/finance/fiscal-periods/{id}/close` | `finance.close_period` | Close period (no more entries) |

### 7.4 Reports

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/finance/reports/trial-balance` | `finance.reports` | Trial balance for period |
| GET | `/finance/reports/profit-loss` | `finance.reports` | P&L for period |
| GET | `/finance/reports/balance-sheet` | `finance.reports` | Balance sheet at date |

### 7.5 AR / AP

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/finance/ar` | `finance.view` | AR aging summary |
| GET | `/finance/ap` | `finance.view` | AP aging summary |
| GET | `/finance/ar/customers/{id}` | `finance.view` | Customer AR detail |
| GET | `/finance/ap/suppliers/{id}` | `finance.view` | Supplier AP detail |

---

## 8. AUTO-POSTING RULES

### 8.1 Sale (Cash)

```
Debit  : Cash / Bank Account          = total
Credit : Revenue                      = total
```

### 8.2 Sale (Credit)

```
Debit  : Accounts Receivable (Customer) = total
Credit : Revenue                         = total
```

### 8.3 Payment Received (against AR)

```
Debit  : Cash / Bank Account           = amount
Credit : Accounts Receivable (Customer) = amount
```

### 8.4 Sale Refund

```
Debit  : Revenue / Refund Allowance    = refund_amount
Credit : Cash / AR                      = refund_amount
```

### 8.5 Purchase (on credit)

```
Debit  : Inventory / Cost of Goods Sold = total
Credit : Accounts Payable (Supplier)    = total
```

### 8.6 Supplier Invoice Payment

```
Debit  : Accounts Payable (Supplier)   = amount
Credit : Cash / Bank Account           = amount
```

### 8.7 Inventory Adjustment

```
Debit  : Inventory / Adjustment Expense  = value
Credit : Inventory / Adjustment Revenue  = value
```

### 8.8 Customer Loyalty Redemption

```
Debit  : Loyalty Expense               = value
Credit : Revenue (discount)            = value
```

### 8.9 Cash Drawer Variance

```
Debit  : Cash Short / Over             = difference
Credit : Cash                          = difference
```

---

## 9. SECURITY CONSIDERATIONS

### 9.1 Tenant Isolation

- All accounting tables use `BelongsToTenant`.
- `tenant_id` not in `$fillable`; set from auth.
- Account codes and entry numbers unique per tenant.

### 9.2 Fiscal Period Locking

- Closed periods block new journal entries.
- Reversing a manual entry requires creating a new entry in the current open period, not modifying closed entries.

### 9.3 Manual Journal Controls

- Only users with `finance.post_journals` can create manual entries.
- Manual entries must balance (debits = credits).
- Manual entries can be reversed, never deleted.

### 9.4 Audit Trail

- Every `JournalEntry` records `posted_by`, `posted_at`, `source`, and reference to operational document.
- `journal_entry_lines` allow line-level references for detailed traceability.

---

## 10. ACCEPTANCE CRITERIA

- [x] Default Chart of Accounts seeded per business type
- [x] Create, update, delete custom accounts (non-system)
- [x] Manual journal entry with balanced debit/credit validation
- [x] Fiscal period creation and close
- [x] Auto-journal generated for cash sale
- [x] Auto-journal generated for credit sale (AR)
- [ ] Auto-journal generated for purchase on credit (AP)
- [x] Auto-journal generated for payment received
- [ ] Auto-journal generated for supplier invoice payment
- [x] Auto-journal generated for sale refund
- [ ] Auto-journal generated for inventory adjustment
- [x] General ledger returns correct account history
- [x] Trial Balance balances (total debits = total credits)
- [x] Profit & Loss report returns revenue and expense totals
- [x] Balance Sheet returns assets, liabilities, equity
- [ ] AR aging report for customers
- [ ] AP aging report for suppliers
- [x] Closed period blocks new journal entries
- [x] Tenant isolation on all accounting endpoints
- [x] All existing regression tests pass
- [x] New backend tests for accounting domain
- [x] E2E test: post manual journal and view ledger
- [x] E2E test: view Trial Balance / P&L / Balance Sheet

**Notes:** Items not marked are deferred to Phase 7/8 (full AP/AR aging reports, purchase/supplier-invoice auto-posting, inventory-adjustment auto-posting, and frontend accounting UI). The Phase 6 foundation is closed and tested.

---

## 11. IMPLEMENTATION ORDER

1. Database migrations (5 new tables)
2. Models (`Account`, `FiscalPeriod`, `JournalEntry`, `JournalEntryLine`, `AccountBalance`)
3. Seeder for default Chart of Accounts
4. `AccountingService` for auto-posting rules
5. `JournalEntryService` for validation and posting
6. `ReportService` for Trial Balance, P&L, Balance Sheet
7. Controllers and API routes
8. Feature/permission middleware integration
9. Frontend: CoA UI, journal entry form, reports dashboard
10. Tests: backend feature tests, E2E tests
11. Documentation: ARCHITECTURE, API, FLOW, SECURITY, TESTING

---

## 12. DEPENDENCIES

- **Phase 4** — Sale/Payment/Refund data required for auto-posting
- **Phase 5** — Payment gateway data, cash drawer, settlements
- **Phase 3** — Purchase/SupplierInvoice/GRN data required for AP
- **Phase 2** — InventoryMovement data required for inventory accounting

---

## 13. RISKS & MITIGATIONS

| Risk | Mitigation |
|------|------------|
| Running balance drift | Use `account_balances` pre-computed table; validate Trial Balance balances |
| Back-posting to closed period | Enforce `fiscal_periods.status = 'open'` check on insert |
| Complex auto-posting logic | Encapsulate in `AccountingService` with rule classes; unit-test each rule |
| Performance on high volume | Ledger queries use `journal_entry_lines` index on `account_id, created_at` |
| Multi-currency rounding | Phase 6 uses IDR only; foundation columns added for future |

---

*End of Phase 6 PDR*
