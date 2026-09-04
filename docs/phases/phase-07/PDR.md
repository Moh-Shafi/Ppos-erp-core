# Phase 7 — Reports & Analytics — PDR

**Document Status:** APPROVED  
**Created:** 2026-08-15  
**Phase:** 7 — Reports & Analytics  
**Depends On:** Phase 6 (Finance / Accounting)  
**Roadmap Reference:** `docs/PDR/02-PHASE_ROADMAP.md` — Phase 7

---

## 1. OBJECTIVE

Build a multi-tenant, module-aware Reports & Analytics platform that provides centralized operational, financial, sales, inventory, purchasing, customer, payment, and business-performance reporting across the ERP, with configurable dashboards, filtering, period/branch/store comparison, drill-down capabilities, and standardized export formats, while consuming existing domain and accounting data without duplicating business logic.

### 1.1 In Scope

- Reporting Foundation (query architecture, filtering, aggregation, pagination, export)
- Dashboard & Widgets (module-aware, configurable, permission-based)
- Sales Analytics
- Profit Analytics (consuming Phase 6 accounting data)
- Inventory Analytics
- Purchasing Analytics
- Customer Analytics
- Payment Analytics
- Financial Analytics (Trial Balance, P&L, Balance Sheet, Cash Flow, General Ledger)
- AR Aging and AP Aging
- Branch / Store comparison
- Product performance
- Export: CSV, Excel (XLSX), PDF
- Frontend Reporting UI
- RBAC and feature-flag integration

### 1.2 Out of Scope (Deferred)

- Tax / VAT / GST / SST specific reporting
- Fixed asset depreciation
- Payroll analytics
- Production / MRP analytics
- Advanced BI, predictive analytics, AI forecasting
- Data warehouse / external BI
- Auto-journal rules deferred from Phase 6 (purchase AP, supplier invoice, inventory adjustment)
- Cross-tenant reporting (multi-tenant analytics)

### 1.3 Guiding Principle

> The reporting layer must consume data from existing Sales, Inventory, Purchasing, Payment, Customer, and Accounting modules. It must not re-implement business logic, calculations, or transaction rules that already exist in domain services.

---

## 2. DESIGN PRINCIPLES

### 2.1 Single Source of Truth

All report data is derived from existing operational tables and the `JournalEntry`/`JournalEntryLine` tables from Phase 6. Reports do not maintain separate copies of transactional truth.

### 2.2 Module-Aware Reporting

Reports and widgets are gated by module and feature flags. A tenant without the `finance` module does not see financial widgets. A cashier without `reports.view` cannot access the dashboard.

### 2.3 Read-Optimized Queries

Heavy analytics use read-optimized query builders with explicit tenant scoping, date filtering, and optional pre-computed aggregates (e.g., `report_snapshots`). Reports never write back to operational tables. `report_snapshots` are a performance optimization only; the MVP must be fully functional and performant without them and they must never be treated as a source of truth.

### 2.4 Unified Export Layer

The same `ReportService` result is rendered as JSON (API), CSV, XLSX, or PDF. No separate logic for each export format.

### 2.5 Tenant & Store Isolation

Every query is scoped by `tenant_id`. Multi-store tenants can filter and compare by `store_id`, but only within the stores explicitly authorized for the requesting user. The `store_id` filter must be constrained by the user's authorized store list; users cannot bypass authorization by supplying arbitrary store IDs. Dashboard widgets respect the active store context from the frontend.

### 2.6 Profit Analytics Contract

Profit analytics flow exclusively from Phase 6 journal data:

```
JournalEntry
    ↓
JournalEntryLine
    ↓
Revenue / COGS / Expense Accounts
    ↓
Profit Analytics
```

If Phase 6 has not yet posted an auto-journal for an operational event, the report reflects only the journal entries that exist. The report must not re-calculate or simulate profit from operational tables.

### 2.7 Store Authorization Scope

Every report query must be constrained by an `AuthorizedStoreScope` derived from the authenticated user's permissions. This scope is enforced inside the reporting layer (services and query builders), not only at the controller. The `store_id` filter in API requests is validated and limited to this scope.

### 2.8 Cash Flow Contract

Cash Flow is derived from `JournalEntryLine` movements on Cash/Bank accounts in Phase 6. It is not calculated from the `payments` table or other operational payment records. If an operational event has not yet produced a journal entry, it will not appear in the Cash Flow report.

### 2.9 AR / AP Aging Contract

AR Aging is based on outstanding customer receivables from existing `sales`/`payments` operational data and any related journal entries. AP Aging is based on outstanding supplier payables from `purchases`/`supplier_invoices` and any related journal entries. Because Phase 6 auto-posting for purchase AP and supplier invoice payment is deferred, these reports must reflect the currently available operational and journal data without simulating complete accounting.

---

## 3. ARCHITECTURE OVERVIEW

```
Operational Modules
       │
       ├── Sales
       ├── Inventory
       ├── Purchasing
       ├── Customers
       ├── Payments
       └── Accounting (Phase 6)
                │
                ↓
        Reporting Layer
                │
        ┌───────┼────────┐
        ↓       ↓        ↓
     Reports  Analytics  KPIs
        │       │        │
        └───────┼────────┘
                ↓
           Dashboard
                │
        ┌───────┼────────┐
        ↓       ↓        ↓
       JSON    Export   Drill-down
```

### 3.1 Core Components

| Component | Responsibility |
|-----------|----------------|
| `ReportEngine` | Dispatches report requests to correct query builder |
| `ReportQuery` | Builds read-optimized, tenant-scoped SQL |
| `ReportBuilder` | Aggregates, groups, sorts, paginates |
| `KpiService` | Computes single-value KPIs for dashboard |
| `DashboardService` | Loads user/tenant configured widgets |
| `ExportService` | Transforms report result to CSV/XLSX/PDF |
| `ReportController` | HTTP endpoints for reports, dashboard, exports |

---

## 4. DATABASE CHANGES

### 4.1 New Tables

- `report_configs` — saved report parameters per user/tenant
- `dashboard_widgets` — user/tenant dashboard layout and widget config
- `report_snapshots` — optional cached/precomputed report results; performance optimization only, never a source of truth. The MVP is fully functional without snapshots. Any snapshot implementation must include TTL, versioning, and invalidation strategy to prevent stale data.
- `kpi_definitions` — system KPI metadata and configuration only. The executable KPI implementation lives in a version-controlled backend `KpiRegistry`/`KpiService`; the database never stores executable query logic or SQL.

### 4.2 Modified Tables

No modifications to existing operational tables. Reporting only reads.

### 4.3 Indexes

- Composite indexes on `journal_entry_lines` (account_id, entry_date)
- Date and tenant indexes on sales, purchases, payments, inventory_movements

---

## 5. API DESIGN

### 5.1 Base Path

```
/api/v1/reports
```

### 5.2 Endpoints (Draft)

```
GET /api/v1/reports/dashboard?date_from=&date_to=&store_id=
GET /api/v1/reports/sales?date_from=&date_to=&store_id=&group_by=day|week|month
GET /api/v1/reports/profit?date_from=&date_to=&store_id=&group_by=product|category
GET /api/v1/reports/inventory?store_id=&low_stock=1
GET /api/v1/reports/purchasing?date_from=&date_to=&supplier_id=
GET /api/v1/reports/customers?date_from=&date_to=&top_n=
GET /api/v1/reports/payments?date_from=&date_to=&method=
GET /api/v1/reports/financial/trial-balance?date_from=&date_to=
GET /api/v1/reports/financial/profit-loss?date_from=&date_to=
GET /api/v1/reports/financial/balance-sheet?as_of=
GET /api/v1/reports/financial/cash-flow?date_from=&date_to=
GET /api/v1/reports/financial/ar-aging?as_of=
GET /api/v1/reports/financial/ap-aging?as_of=

POST /api/v1/reports/export
```

### 5.3 Standard Response

```json
{
  "report": "sales",
  "date_range": { "from": "2026-08-01", "to": "2026-08-15" },
  "filters": { "store_id": 1 },
  "columns": [...],
  "data": [...],
  "summary": { "total": 1230000 },
  "links": { "csv": "...", "xlsx": "...", "pdf": "..." }
}
```

### 5.4 Export Contract

CSV, XLSX, and PDF exports must consume the exact same validated report dataset and query definition as JSON. Exports must not bypass permission, tenant, or store authorization. Formatting changes the output encoding but not the underlying data or access scope.

The client must only send a registered `report_id`, validated filter values, and the desired format. The `ReportEngine` resolves the query definition server-side. Clients must not submit arbitrary SQL, raw query definitions, or unvalidated report configurations.

---

## 6. RBAC & FEATURES

### 6.1 New Permissions

- `reports.view` — access any report
- `reports.export` — export reports
- `reports.dashboard.manage` — add/remove widgets
- `reports.comparison` — view multi-store / period comparison
- `reports.financial` — view financial reports

### 6.2 New Feature Flags

- `reports.dashboard`
- `reports.sales`
- `reports.profit`
- `reports.inventory`
- `reports.purchasing`
- `reports.customers`
- `reports.payments`
- `reports.financial`
- `reports.cash_flow`
- `reports.ar_aging`
- `reports.ap_aging`
- `reports.export_csv`
- `reports.export_xlsx`
- `reports.export_pdf`

---

## 7. FRONTEND UX

- Dashboard with configurable widget grid
- Date range picker with presets (today, this week, this month, custom)
- Store/branch selector and comparison toggle
- Drill-down from summary to detailed list
- Export dropdown (CSV, Excel, PDF)
- Responsive cards, tables, and charts

---

## 8. IMPLEMENTATION ORDER

1. Reporting Foundation — `ReportEngine`, `ReportQuery`, `ReportBuilder`
2. KPI service and dashboard API
3. Sales, Inventory, Purchasing, Customer, Payment analytics
4. Financial reports integration (Phase 6 data)
5. AR / AP aging
6. Export service (CSV, XLSX, PDF)
7. Frontend dashboard and report views
8. RBAC and feature flag integration
9. Backend and E2E tests
10. Full regression
11. Final audit

---

## 9. ACCEPTANCE CRITERIA

- [ ] Dashboard loads with module-aware KPI widgets.
- [ ] Sales analytics grouped by day/week/month.
- [ ] Profit analytics consistent with Phase 6 accounting data.
- [ ] Inventory analytics reflect current stock and movement.
- [ ] Purchasing analytics show PO and supplier performance.
- [ ] Customer analytics show top customers and loyalty.
- [ ] Payment analytics show method/status breakdown.
- [ ] Financial reports (Trial Balance, P&L, Balance Sheet, Cash Flow) render.
- [ ] AR aging and AP aging reports generate.
- [ ] Export to CSV, XLSX, and PDF works from the same report query.
- [ ] Tenant and store isolation on every endpoint.
- [ ] Permission and feature flags hide/show UI and API correctly.
- [ ] All existing Phase 6 tests pass (regression).
- [ ] New Phase 7 backend tests pass.
- [ ] New Phase 7 E2E tests pass.
- [ ] Documentation: ARCHITECTURE, API, FLOW, SECURITY, TESTING, Final Report.

---

## 10. DEPENDENCIES

- Phase 6 (Finance / Accounting) — must remain CLOSED and green.
- Phase 5 (Payment Infrastructure) — for payment reports.
- Phase 4 (POS / Customers) — for sales and customer reports.
- Phase 3 (Purchasing / Suppliers) — for purchasing reports.
- Phase 2 (Inventory) — for inventory reports.

---

## 11. RISKS & MITIGATIONS

| Risk | Mitigation |
|------|------------|
| Report queries slow on large data | Use indexes, date filters, optional snapshots, pagination |
| Profit report conflicts with accounting | Always use Phase 6 `JournalEntryLine` data, not re-calculate |
| Dashboard becomes cluttered | Widgets module-aware and configurable per user |
| Export timeout for large data | Async export with download link (Phase 7 MVP: sync with limits) |
| Feature scope creep | Strictly defer tax/payroll/BI to Phase 8/Future |

---

## 12. DEFINITION OF DONE

- PDR approved.
- All architecture, API, flow, security, and testing documents written.
- Implementation complete.
- Backend tests, E2E tests, full backend regression, and security gate passing.
- Final report and phase marked CLOSED.
- Phase 6 baseline remains green (1111 tests + 4 E2E).

---

*End of Phase 7 PDR — DRAFT*
