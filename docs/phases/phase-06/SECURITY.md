# Phase 6 — Finance / Accounting — Security

**Document Status:** DRAFT  
**Created:** 2026-08-15

---

## 1. Tenant Isolation

- All new models (`Account`, `FiscalPeriod`, `JournalEntry`, `JournalEntryLine`, `AccountBalance`) use `BelongsToTenant`.
- `tenant_id` is in `$fillable` for backend seeding and service use but is set from authenticated user context for API operations.
- Account codes and journal entry numbers are unique per tenant.
- Reports and ledger queries are scoped by `tenant_id`.

## 2. RBAC

| Permission | Access |
|------------|--------|
| `finance.view` | View accounts, journal entries, ledger, fiscal periods |
| `finance.manage` | Create/update accounts and fiscal periods |
| `finance.post_journals` | Post manual journal entries |
| `finance.close_period` | Close a fiscal period |
| `finance.reports` | View Trial Balance, P&L, Balance Sheet |

## 3. Journal Integrity

- Manual journal entries must be balanced (debits = credits).
- Service-level validation rejects zero-value and unbalanced entries.
- Closed fiscal periods reject new journal entries.
- Journal entries are immutable once posted (reversal creates a new entry in the current open period).

## 4. Audit Trail

- Every `JournalEntry` records `posted_by`, `posted_at`, `source`, `reference_type`, `reference_id`.
- `JournalEntryLine` supports line-level `reference_type` / `reference_id`.
- Source-document linking ties financial postings back to `Sale`, `Purchase`, `Payment`, `SaleRefund`.

## 5. Secret Exposure

- No gateway credentials, API keys, or tenant secrets stored in accounting tables.
- `XenditPayment` and `PaymentServiceProvider` remain unchanged from Phase 5.
