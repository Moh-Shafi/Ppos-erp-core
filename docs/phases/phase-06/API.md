# Phase 6 — Finance / Accounting — API

**Document Status:** DRAFT  
**Created:** 2026-08-15

---

## 1. Chart of Accounts

### List Accounts

```
GET /api/v1/finance/accounts?flat=1&type=asset
```

**Response (flat=1):**
```json
[
  { "id": 1, "code": "1-1000", "name": "Cash on Hand", "type": "asset", "is_bank": true, ... },
  ...
]
```

### Create Account

```
POST /api/v1/finance/accounts
{
  "code": "6-1000",
  "name": "Marketing Expense",
  "type": "expense",
  "parent_id": null,
  "is_bank": false
}
```

### Update Account

```
PUT /api/v1/finance/accounts/{id}
{
  "name": "Updated Name",
  "is_active": true
}
```

### Delete Account

```
DELETE /api/v1/finance/accounts/{id}
```

## 2. Journal Entries

### List Journal Entries

```
GET /api/v1/finance/journal-entries?from=2026-01-01&to=2026-12-31&source=auto
```

### Post Manual Journal Entry

```
POST /api/v1/finance/journal-entries
{
  "entry_date": "2026-08-15",
  "description": "Manual adjustment",
  "lines": [
    { "account_id": 1, "debit": 100000, "credit": 0, "description": "Cash" },
    { "account_id": 10, "debit": 0, "credit": 100000, "description": "Revenue" }
  ]
}
```

### Show Journal Entry

```
GET /api/v1/finance/journal-entries/{id}
```

## 3. Fiscal Periods

### List Periods

```
GET /api/v1/finance/fiscal-periods?status=open
```

### Create Period

```
POST /api/v1/finance/fiscal-periods
{
  "name": "2026-Q1",
  "start_date": "2026-01-01",
  "end_date": "2026-03-31"
}
```

### Close Period

```
POST /api/v1/finance/fiscal-periods/{id}/close
```

## 4. Reports

### Trial Balance

```
GET /api/v1/finance/reports/trial-balance?start_date=2026-01-01&end_date=2026-12-31
```

### Profit & Loss

```
GET /api/v1/finance/reports/profit-loss?start_date=2026-01-01&end_date=2026-12-31
```

### Balance Sheet

```
GET /api/v1/finance/reports/balance-sheet?as_of=2026-12-31
```

### General Ledger

```
GET /api/v1/finance/accounts/{id}/ledger?start_date=2026-01-01&end_date=2026-12-31
```
