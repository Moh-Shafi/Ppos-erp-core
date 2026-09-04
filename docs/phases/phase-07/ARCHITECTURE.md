# Phase 7 — Reports & Analytics — Architecture

**Document Status:** APPROVED / FROZEN  
**Created:** 2026-08-15  
**Phase:** 7 — Reports & Analytics  
**Depends On:** `docs/phases/phase-07/PDR.md` — APPROVED

---

## 1. Overview

Phase 7 adds a **Reporting & Analytics layer** on top of existing operational modules (Sales, Inventory, Purchasing, Customers, Payments) and the Phase 6 accounting foundation. The architecture is read-only, multi-tenant, module-aware, and store-authorized. It does not duplicate business logic or transactional truth.

The architecture consists of:
- `ReportEngine` — dispatches report requests by `report_id`.
- `ReportQuery` — builds and validates read-only, tenant-scoped SQL.
- `ReportBuilder` — aggregates, groups, sorts, paginates.
- `AuthorizedStoreScope` — enforces store authorization for every query.
- `KpiRegistry` / `KpiService` — server-side, version-controlled KPI implementations.
- `ExportService` — formats the same validated dataset to JSON/CSV/XLSX/PDF.
- `DashboardService` — loads widgets and KPIs.

---

## 2. Core Components

| Component | Responsibility | Key Invariant |
|-----------|----------------|---------------|
| `ReportEngine` | Resolves `report_id` to a registered `ReportDefinition` | No arbitrary query execution |
| `ReportDefinition` | Declares source tables, allowed filters, default grouping | Version-controlled |
| `AuthorizedStoreScope` | Builds the list of stores a user may query | Enforced in all services, not only controller |
| `ReportQuery` | Generates SQL from `ReportDefinition` + filters + store scope | Tenant scoped and store constrained |
| `ReportBuilder` | Aggregates, sorts, paginates, formats data for API | No business logic changes |
| `KpiRegistry` | Maps `kpi_id` to a concrete `Kpi` class | Database stores only metadata |
| `KpiService` | Computes one or more KPI values | Uses `ReportQuery` for data access |
| `DashboardService` | Loads and validates widget configuration | Widgets gated by module/permission |
| `ExportService` | Converts `ReportResult` to requested format | Same dataset as JSON |
| `ReportController` | Validates requests and returns JSON/exports | No query resolution here |

---

## 3. Request Flow

### 3.1 Standard Report Request

```
Client
  │
  ▼
GET /api/v1/reports/{report_id}
  │
  ▼
ReportController
  │
  ▼
ReportEngine.resolve(report_id)
  │
  ▼
AuthorizedStoreScope.forUser($user)
  │
  ▼
ReportQuery.build(definition, filters, tenant, storeScope)
  │
  ▼
Database (read-only)
  │
  ▼
ReportBuilder.aggregate(queryResult)
  │
  ▼
ReportResult
  │
  ▼
JSON Response
```

### 3.2 Export Request

```
Client
  │
  ▼
POST /api/v1/reports/export
  { report_id, filters, format: csv|xlsx|pdf }
  │
  ▼
ReportController
  │
  ▼
ReportEngine.resolve(report_id)
  │
  ▼
AuthorizedStoreScope.forUser($user)
  │
  ▼
ReportQuery.build(...) → ReportBuilder → ReportResult
  │
  ▼
ExportService.format(ReportResult, format)
  │
  ▼
File/Stream Response
```

### 3.3 Dashboard Request

```
Client
  │
  ▼
GET /api/v1/reports/dashboard
  │
  ▼
DashboardService.loadWidgets($user, $tenant)
  │
  ├─ filter visible by module + permission
  ├─ resolve kpi_id via KpiRegistry
  ├─ KpiService.compute each
  │       └─ uses ReportQuery → DB
  │
  ▼
Dashboard DTO
  │
  ▼
JSON Response
```

---

## 4. Authorization Model

### 4.1 Tenant Isolation

Every report query starts with `tenant_id = $user->tenant_id`. There is no cross-tenant reporting in Phase 7.

### 4.2 Store Authorization

```
User
  │
  ▼
Role → Permission: reports.view
  │
  ▼
Assigned Stores (all or subset)
  │
  ▼
AuthorizedStoreScope
  │
  ▼
ReportQuery only filters on authorized stores
```

`AuthorizedStoreScope` is a value object passed into every report service. It is built once per request and cannot be bypassed by request parameters.

### 4.3 RBAC Gating

| Permission | Effect |
|------------|--------|
| `reports.view` | Can call report endpoints |
| `reports.export` | Can export to CSV/XLSX/PDF |
| `reports.dashboard.manage` | Can save dashboard layout |
| `reports.comparison` | Can query multiple stores and comparison periods |
| `reports.financial` | Can access Trial Balance, P&L, Balance Sheet, Cash Flow, AR/AP aging |

---

## 5. Data Contracts

### 5.1 Profit Analytics Contract

Profit and margin are computed from Phase 6 journal data only:

```
JournalEntry
    ↓
JournalEntryLine
    ↓
Revenue / COGS / Expense Accounts
    ↓
Profit Analytics
```

Reporting never computes profit from `sales`, `sale_items`, or `inventories` directly. If an operational event has no journal entry, it does not contribute to profit.

### 5.2 Cash Flow Contract

Cash Flow is computed from `JournalEntryLine` movements on Cash/Bank accounts. The report is mandatory in Phase 7.

```
Account.type in (asset, bank)
    ↓
JournalEntryLine.debit / credit on those accounts
    ↓
Cash Flow
```

Classification into `operating`, `investing`, and `financing` is derived from the approved Phase 6 account/journal structure. If the metadata required for a deterministic classification is not available, the report must expose an explicit `unclassified` category rather than invent business rules.

It is not derived from the `payments` table. Any operational event that has not posted a journal entry does not appear.

### 5.3 AR / AP Aging Contract

AR and AP Aging are hybrid operational/accounting reports.

```
AR Aging = operational receivable data (sales, payments)
           + available journal data

AP Aging = operational payable data (purchases, supplier_invoices)
           + available journal data
```

They must not simulate complete accounting and must not invent deferred Phase 6 auto-journal rules. If a payable/receivable has no corresponding journal entry, the report reflects the operational record without creating synthetic journal data.

### 5.4 Operational Reports Contract

Sales, inventory, purchasing, customer, and payment analytics consume operational tables. They must not recompute stock/cost/pricing; they read the same values used by the operational modules.

---

## 6. Database Read Model

### 6.1 New Tables

| Table | Purpose |
|-------|---------|
| `report_configs` | Saved report filters and parameters per user/tenant |
| `dashboard_widgets` | Widget layout and settings per user/tenant |
| `report_snapshots` | Optional cached results (TTL, version, invalidation) |
| `kpi_definitions` | KPI metadata: id, name, category, allowed filters, format |

### 6.2 No Source-of-Truth Copies

Reporting tables do not store business facts. `report_snapshots` are cache only and are invalidated on major operational events or by TTL.

### 6.3 Indexes

- `journal_entry_lines` (tenant_id, account_id, entry_date)
- `sales` (tenant_id, store_id, created_at)
- `purchases` (tenant_id, store_id, created_at)
- `inventories` (tenant_id, store_id, product_id)
- `payments` (tenant_id, store_id, created_at, method)

### 6.4 Configuration Lifecycle

`report_configs` and `dashboard_widgets` are owned by a user within a tenant. Lifecycle:

| Resource | Create | Read | Update | Delete |
|----------|--------|------|--------|--------|
| `report_configs` | `POST /api/v1/reports/configs` | `GET /api/v1/reports/configs` | `PATCH /api/v1/reports/configs/{id}` | `DELETE /api/v1/reports/configs/{id}` |
| `dashboard_widgets` | `POST /api/v1/reports/dashboard/widgets` | `GET /api/v1/reports/dashboard` | `PATCH /api/v1/reports/dashboard/widgets/{id}` | `DELETE /api/v1/reports/dashboard/widgets/{id}` |

Each operation enforces `tenant_id` and `user_id`. A user may not read or modify another user's widgets or configs without explicit permission.

### 6.5 PDR Capability to Report ID Mapping

Every PDR capability is exposed through a registered `report_id`. Initial mapping:

| PDR Capability | Report ID(s) |
|----------------|--------------|
| General Ledger | `general-ledger` |
| Trial Balance | `trial-balance` |
| P&L | `profit-loss` |
| Balance Sheet | `balance-sheet` |
| Cash Flow | `cash-flow` |
| AR Aging | `ar-aging` |
| AP Aging | `ap-aging` |
| Sales Analytics | `sales` |
| Inventory Analytics | `inventory` |
| Purchasing Analytics | `purchasing` |
| Customer Analytics | `customers` |
| Payment Analytics | `payments` |
| Product Performance | `product-performance` |
| Branch / Store comparison | `branch-comparison` |

Final endpoint names and query shapes are standardized in `API.md`.

---

## 7. Report Definition Registry

A `ReportDefinition` is a version-controlled PHP class that declares:

```php
class SalesReportDefinition implements ReportDefinition
{
    public string $reportId = 'sales';
    public array $allowedFilters = ['date_from', 'date_to', 'store_id', 'group_by'];
    public array $requiredScopes = ['reports.view'];
    public array $allowedFeatureFlags = ['reports.sales'];

    public function query(ReportContext $ctx): ReportQuery;
}
```

The registry maps `report_id` strings to these classes. No report can be run unless it is registered.

---

## 8. KPI Engine

### 8.1 KpiRegistry

A registry maps `kpi_id` to a version-controlled `Kpi` class. The `kpi_definitions` table only stores metadata for display/permission.

### 8.2 Kpi Interface

```php
interface Kpi
{
    public function compute(AuthorizedStoreScope $scope, array $filters): KpiValue;
}
```

All KPIs use `ReportQuery` to fetch data. They never query the database directly outside the reporting layer.

---

## 9. Export Service

### 9.1 Same Dataset Rule

JSON, CSV, XLSX, and PDF all use the same `ReportResult`. Formatting does not change the data or access scope.

### 9.2 No Arbitrary Query

The client sends:

```json
{
  "report_id": "sales",
  "filters": { "date_from": "...", "date_to": "...", "store_id": 1 },
  "format": "xlsx"
}
```

The `ReportEngine` resolves `report_id` and filters. No raw SQL or unvalidated query configuration is accepted.

---

## 10. Snapshot Strategy (Optional)

### 10.1 MVP Without Snapshots

The system works fully without `report_snapshots`. Snapshot is added only after MVP proves performance need.

### 10.2 Snapshot Rules

If implemented, every snapshot must have:
- `tenant_id`
- `report_id`
- `filter_hash`
- `created_at` and `expires_at` (TTL)
- `version` (incremented on schema/filter changes)
- Invalidation on source data change or TTL expiry

Snapshots are never the source of truth.

---

## 11. Frontend Architecture

### 11.1 Widget Model

```
Dashboard
  │
  ├── Widget (kpi_id / report_id)
  │     └─ filter: date range, store
  │
  ├── WidgetRenderer
  │     └─ card | chart | table
  │
  └── ExportButton
        └─ calls POST /reports/export
```

### 11.2 Permission/Feature Gating

The frontend receives module/feature/permission config from `GET /api/v1/me`. It hides widgets and menu items the user cannot access.

---

## 12. Integration Points

### 12.1 Phase 6 Accounting

Financial reports query `accounts`, `fiscal_periods`, `journal_entries`, and `journal_entry_lines`. They do not write to these tables.

### 12.2 Phase 5 Payment

Payment analytics query `payments` and `payment_settlements`. Settlement status is reported but not re-calculated.

### 12.3 Phase 4 POS / Customers

Sales and customer reports query `sales`, `sale_items`, `customers`, `customer_loyalty_points`.

### 12.4 Phase 3 Purchasing

Purchasing reports query `purchases`, `purchase_items`, `suppliers`, `supplier_invoices`, `goods_receipt_notes`.

### 12.5 Phase 2 Inventory

Inventory reports query `inventories`, `inventory_movements`, `stock_batches`.

---

## 13. Design Invariants

1. **No business logic duplication.** Reports consume, not recreate.
2. **No cross-tenant queries.** Tenant isolation is mandatory.
3. **Store authorization enforced in services.** `AuthorizedStoreScope` is mandatory for every report query, even those without an explicit `store_id` filter. It is enforced in all services, not only in controllers.
4. **KPI and report query logic is in version-controlled code.** Not in database.
5. **Same dataset for JSON and exports.**
6. **No arbitrary query execution by clients.** `report_id` is resolved through `ReportRegistry` to a version-controlled `ReportDefinition`. Clients cannot submit raw SQL, query fragments, or unregistered report configurations.
7. **Snapshots are optional and never source of truth.**
8. **Phase 6 baseline is immutable.** No changes to existing tests or core accounting logic.
