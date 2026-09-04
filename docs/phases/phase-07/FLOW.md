# Phase 7 — Reports & Analytics — Flow

**Document Status:** DRAFT  
**Created:** 2026-08-15  
**Phase:** 7 — Reports & Analytics  
**Depends On:** `docs/phases/phase-07/ARCHITECTURE.md` — APPROVED / FROZEN

---

## 1. Standard Report Request Flow

```
Client
  │
  ▼
GET /api/v1/reports/{report_id}
  │
  ▼
ReportController
  ├─ validate JWT token
  ├─ resolve tenant from token
  ├─ resolve user permissions
  └─ validate filter schema
  │
  ▼
ReportEngine
  ├─ report_id → ReportRegistry → ReportDefinition
  ├─ feature flag check
  └─ permission check
  │
  ▼
AuthorizedStoreScope
  └─ user → list of authorized stores
  │
  ▼
ReportQuery
  ├─ apply tenant_id
  ├─ apply store filter (from scope)
  ├─ apply date range and other filters
  ├─ validate fiscal period reference
  ├─ note: closed fiscal periods do not block read-only reporting
  └─ build read-only SQL
  │
  ▼
Database
  │
  ▼
ReportBuilder
  ├─ aggregate
  ├─ sort
  ├─ paginate
  ├─ compute summary
  └─ build columns
  │
  ▼
ReportResult (JSON)
  │
  ▼
Client
```

---

## 2. Export Request Flow

```
Client
  │
  ▼
POST /api/v1/reports/export
  { report_id, filters, format }
  │
  ▼
ReportController
  ├─ validate format in (csv, xlsx, pdf)
  ├─ validate report_id registered
  └─ validate filters
  │
  ▼
ReportEngine → AuthorizedStoreScope
  │
  ▼
ReportQuery → ReportBuilder → ReportResult
  │
  ▼
ExportService
  ├─ same data as JSON
  ├─ csv: streaming rows
  ├─ xlsx: workbook formatting
  └─ pdf: tabular layout
  │
  ▼
File/Stream Response
```

---

## 3. Dashboard Widget Flow

```
Client
  │
  ▼
GET /api/v1/reports/dashboard
  │
  ▼
DashboardService
  ├─ load user/tenant widget layout
  ├─ filter widgets by module enabled
  └─ filter widgets by permission
  │
  ▼
For each widget:
  ├─ Widget type: kpi_id or report_id
  ├─ resolve via KpiRegistry or ReportRegistry
  ├─ compute with AuthorizedStoreScope
  ├─ apply widget-level filters
  └─ build widget DTO
  │
  ▼
Dashboard DTO (JSON)
  │
  ▼
Client
```

---

## 4. Drill-Down Flow

```
Client (summary table/cell selected)
  │
  ▼
GET /api/v1/reports/{report_id}/drill-down
  { filters, group_key, group_value }
  │
  ▼
ReportController
  │
  ▼
ReportEngine
  ├─ drill-down is registered per report
  └─ same report_id + additional context
  │
  ▼
AuthorizedStoreScope
  │
  ▼
ReportQuery
  ├─ same base filters
  ├─ validate `group_key` against `allowedDrillDownKeys`
  ├─ additional equality filter on group_key/value
  └─ remove grouping
  │
  ▼
ReportBuilder (detail rows)
  │
  ▼
Drill-Down Result
```

---

## 5. KPI Computation Flow

```
Dashboard or KPI endpoint
  │
  ▼
KpiService
  ├─ kpi_id → KpiRegistry → Kpi class
  ├─ validate feature/permission
  └─ read widget filter
  │
  ▼
AuthorizedStoreScope
  │
  ▼
Kpi.compute(scope, filters)
  ├─ uses ReportQuery (not raw DB)
  ├─ may call one or more reports
  └─ returns scalar or structured value
  │
  ▼
KpiValue DTO
```

---

## 6. Snapshot Cache Flow (Optional)

```
ReportEngine
  │
  └─ ReportExecutionStrategy
      │
      ├─ DirectQueryStrategy (MVP default)
      │     ↓
      │   ReportQuery → DB → ReportBuilder
      │
      └─ SnapshotStrategy (optional, post-MVP)
            │
            ├─ check report_snapshots for (tenant_id, report_id, filter_hash, version)
            ├─ if valid (not expired, not invalidated):
            │      return cached result
            └─ if missing or stale:
                   execute DB query
                   store result with created_at, expires_at, version
                   return result

Source data change (sale, payment, journal, etc.)
  │
  ▼
SnapshotInvalidator
  ├─ increment version or delete affected snapshots
  └─ next request rebuilds
```

---

## 7. Configuration CRUD Flow

### 7.1 Save Report Config

```
Client
  │
  ▼
POST /api/v1/reports/configs
  { name, report_id, filters }
  │
  ▼
ReportController
  ├─ validate report_id is registered
  ├─ validate filters against ReportDefinition
  ├─ validate all store filters against current AuthorizedStoreScope
  └─ set tenant_id and user_id
  │
  ▼
report_configs (DB)

```
Load saved config:
  │
  ▼
ReportController
  ├─ verify config ownership (user/tenant)
  ├─ revalidate filters against current AuthorizedStoreScope
  └─ reject if stored store_id is no longer authorized
```
```

### 7.2 Save Dashboard Widget

```
Client
  │
  ▼
POST /api/v1/reports/dashboard/widgets
  { kpi_id or report_id, position, size, filters }
  │
  ▼
DashboardController
  ├─ validate kpi_id or report_id is registered
  ├─ validate permissions and feature flag for that widget
  ├─ validate widget filters against ReportDefinition allowed filters
  ├─ validate widget store filters against AuthorizedStoreScope
  └─ set tenant_id and user_id
  │
  ▼
dashboard_widgets (DB)
```

---

## 8. Error Flows

| Trigger | Response | Log |
|---------|----------|-----|
| Unregistered `report_id` | 400 Bad Request | audit log | 
| `store_id` outside `AuthorizedStoreScope` | 403 Forbidden | audit log |
| Missing permission | 403 Forbidden | audit log |
| Missing feature flag | 403 Forbidden | audit log |
| Invalid date range | 422 Unprocessable Entity | application log |
| Export timeout | 504 Gateway Timeout + retry link | application log |
| Snapshot stale | rebuild automatically | no client error |
| Invalid fiscal period reference (non-existent or malformed) | 422 + clear message | application log |

---

## 9. Flow Invariants

1. `AuthorizedStoreScope` is built before any query execution.
2. `report_id` is always resolved through the registry.
3. Financial reports never query operational tables for profit/cash flow.
4. Operational reports never recompute business values.
5. Export uses the same `ReportResult` as JSON.
6. Snapshot is checked only for performance, not correctness.
