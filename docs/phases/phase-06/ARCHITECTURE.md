# Phase 6 — Finance / Accounting — Architecture

**Document Status:** DRAFT  
**Created:** 2026-08-15  
**Depends On:** `PDR.md`, Phase 5 architecture

---

## 1. CURRENT STATE (Phase 0–5)

### 1.1 Operational Financial Data

| Component | Location | Relevance to Accounting |
|-----------|----------|------------------------|
| `Sale` / `SaleItem` | `app/Models/Sale.php` | Revenue source, AR, COGS |
| `Purchase` / `PurchaseItem` | `app/Models/Purchase.php` | AP, inventory asset |
| `Payment` | `app/Models/Payment.php` | Cash/bank inflow/outflow |
| `SaleRefund` / `SaleRefundItem` | `app/Models/SaleRefund.php` | Refund allowance, inventory return |
| `SupplierInvoice` | `app/Models/SupplierInvoice.php` | AP liability |
| `CustomerCreditTransaction` | `app/Models/CustomerCreditTransaction.php` | AR adjustments |
| `InventoryMovement` | `app/Models/InventoryMovement.php` | Inventory value changes |
| `CashDrawerSession` | `app/Models/CashDrawerSession.php` | Cash control, variance |
| `PaymentSettlement` | `app/Models/PaymentSettlement.php` | Fee expense, bank settlement |

### 1.2 Limitations

- No general ledger
- No double-entry tracking
- No structured Chart of Accounts
- No P&L, Balance Sheet, Trial Balance
- No formal AR/AP sub-ledgers
- No fiscal period control
- Financial data scattered across operational tables

---

## 2. TARGET ARCHITECTURE

### 2.1 Core Models

| Model | Purpose |
|-------|---------|
| `Account` | Chart of Accounts entry (type, parent, code, name) |
| `FiscalPeriod` | Accounting period with open/closed status |
| `JournalEntry` | Header for a balanced double-entry posting |
| `JournalEntryLine` | Individual debit/credit line |
| `AccountBalance` | Pre-computed opening/closing balance per period |

### 2.2 Service Layer

| Service | Responsibility |
|---------|---------------|
| `AccountingService` | Auto-generate journal entries from operational events |
| `JournalEntryService` | Validate balance, post, reverse manual entries |
| `AccountBalanceService` | Compute and cache account balances per period |
| `ReportService` | Trial Balance, P&L, Balance Sheet, AR/AP aging |

### 2.3 Posting Flow

```
Operational Event
    ↓
Existing Service (SaleService, PurchaseService, PaymentService)
    ↓
AccountingService::postFor($model)
    ↓
Resolve Fiscal Period
    ↓
Build JournalEntry + JournalEntryLines
    ↓
JournalEntryService::post($entry) (validate balance)
    ↓
Persist + update AccountBalance cache
```

### 2.4 Report Generation

```
Trial Balance
    → SELECT account, SUM(debit), SUM(credit) FROM journal_entry_lines
      WHERE entry_date BETWEEN start AND end
      GROUP BY account_id

P&L
    → SUM revenue accounts (credit - debit) for period
    → SUM expense accounts (debit - credit) for period
    → Net = Revenue - Expenses

Balance Sheet
    → Assets: SUM(debit - credit) up to date
    → Liabilities: SUM(credit - debit) up to date
    → Equity: SUM(credit - debit) up to date
    → Verify: Assets = Liabilities + Equity
```

---

## 3. DEFAULT CHART OF ACCOUNTS (IDR)

### 3.1 System Accounts

| Code | Name | Type | Is Bank |
|------|------|------|---------|
| 1-0000 | Assets | asset | false |
| 1-1000 | Cash on Hand | asset | true |
| 1-1100 | Bank Account | asset | true |
| 1-1200 | Accounts Receivable | asset | false |
| 1-1300 | Inventory | asset | false |
| 2-0000 | Liabilities | liability | false |
| 2-1000 | Accounts Payable | liability | false |
| 3-0000 | Equity | equity | false |
| 3-1000 | Retained Earnings | equity | false |
| 4-0000 | Revenue | revenue | false |
| 4-1000 | Sales Revenue | revenue | false |
| 4-2000 | Refund Allowance | revenue | false |
| 5-0000 | Expenses | expense | false |
| 5-1000 | Cost of Goods Sold | expense | false |
| 5-1100 | Payment Gateway Fees | expense | false |
| 5-1200 | Inventory Adjustment | expense | false |
| 5-1300 | Cash Short/Over | expense | false |
| 5-1400 | Loyalty Expense | expense | false |

---

## 4. SECURITY & INTEGRITY

- All models use `BelongsToTenant`.
- `JournalEntry` total debit/credit equality is enforced at service level and optionally via DB trigger.
- Closed fiscal periods reject new `entry_date` inserts.
- Manual journal entries can be reversed but never mutated.
- Source-document references create immutable audit trail.

---

## 5. PERFORMANCE NOTES

- `journal_entry_lines` has composite indexes for `(tenant_id, account_id, entry_date)` and `(tenant_id, reference_type, reference_id)`.
- `AccountBalance` table is updated at period end or on-demand; reports can fall back to aggregation if cache is stale.
- Ledger queries use `entry_date` ordering with cursor pagination.

---

*End of Phase 6 Architecture Document*
