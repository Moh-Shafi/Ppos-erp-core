# Phase 6 — Finance / Accounting — Flow

**Document Status:** DRAFT  
**Created:** 2026-08-15

---

## 1. Manual Journal Entry Flow

```
User (Owner/Accountant)
    ↓
POST /finance/journal-entries
    ↓
AccountingController::storeJournalEntry
    ↓
JournalEntryService::post
    ↓
1. Resolve fiscal period from entry_date
2. Validate period is open
3. Sum debits and credits
4. Reject if unbalanced or zero
5. Persist JournalEntry + JournalEntryLines
6. Update AccountBalance cache
    ↓
Response: entry_number, total_debit, total_credit, lines
```

## 2. Auto-Posting from Sale

```
SaleService::checkout completes sale
    ↓
AccountingService::postFor('Sale', $sale->id)
    ↓
Resolve default accounts (Cash, Revenue, AR)
    ↓
Build debit/credit lines based on payment method
    ↓
JournalEntryService::post
    ↓
JournalEntry created with source = 'auto'
```

## 3. Trial Balance Flow

```
GET /finance/reports/trial-balance
    ↓
ReportService::trialBalance
    ↓
Aggregate journal_entry_lines by account between dates
    ↓
Return is_balanced + total_debit/credit + accounts
```

## 4. Fiscal Period Close Flow

```
Owner POST /finance/fiscal-periods/{id}/close
    ↓
FiscalPeriod status = 'closed'
    ↓
AccountBalanceService::recalculate($period->id)
    ↓
All account balances for period computed and stored
    ↓
Future posts with entry_date in closed period rejected
```
