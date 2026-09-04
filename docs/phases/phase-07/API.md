# Phase 7 — Reports & Analytics — API

**Document Status:** DRAFT  
**Created:** 2026-08-15  
**Phase:** 7 — Reports & Analytics  
**Depends On:** `docs/phases/phase-07/ARCHITECTURE.md` — APPROVED / FROZEN

---

## 1. Base Path

```
/api/v1/reports
```

All endpoints are authenticated and tenant-scoped.

---

## 2. Dashboard

### 2.1 Get Dashboard

```
GET /api/v1/reports/dashboard?date_from=&date_to=&store_id=
```

**Description:** Returns the user's configured widgets for the active tenant, filtered by module and permission.

**Permissions:** `reports.view`

**Feature:** `reports.dashboard`

**Response:**

```json
{
  "date_range": { "from": "2026-08-01", "to": "2026-08-15" },
  "widgets": [
    {
      "id": 1,
      "type": "kpi",
      "kpi_id": "revenue-today",
      "position": { "x": 0, "y": 0, "w": 2, "h": 1 },
      "value": { "value": 1250000, "format": "currency" }
    },
    {
      "id": 2,
      "type": "report",
      "report_id": "sales",
      "position": { "x": 2, "y": 0, "w": 4, "h": 2 },
      "data": { ... }
    }
  ]
}
```

### 2.2 List KPIs

```
GET /api/v1/reports/kpis
```

**Permissions:** `reports.view`

**Response:**

```json
{
  "data": ["total-sales", "total-orders", "today-revenue", "low-stock-count", "total-customers"]
}
```

---

## 3. Reports

### 3.1 Generic Report Endpoint

```
GET /api/v1/reports/{report_id}
```

**Description:** Executes a registered report by `report_id`. Available `report_id` values are listed in `ARCHITECTURE.md` §6.5.

**Common Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `date_from` | date | Start date (YYYY-MM-DD) |
| `date_to` | date | End date (YYYY-MM-DD) |
| `store_id` | integer | Filter to a single authorized store |
| `stores` | array | Compare multiple authorized stores (requires `reports.comparison`) |
| `group_by` | string | `day`, `week`, `month`, `product`, `category`, `store` |
| `page` | integer | Pagination page |
| `per_page` | integer | Items per page |
| `sort` | string | Sort column and direction, e.g. `total:desc` |

**Permissions:** `reports.view`; financial reports require `reports.financial`.

**Feature flags:** Per report, e.g. `reports.sales`, `reports.profit`, `reports.financial`.

**Response:**

```json
{
  "report": "sales",
  "date_range": { "from": "2026-08-01", "to": "2026-08-15" },
  "filters": { "store_id": 1 },
  "columns": [
    { "key": "date", "label": "Date" },
    { "key": "total", "label": "Total", "format": "currency" }
  ],
  "data": [
    { "date": "2026-08-01", "total": 1250000 }
  ],
  "summary": { "total": 12500000 },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 50 },
  "exports": {
    "csv": { "method": "POST", "endpoint": "/api/v1/reports/export", "format": "csv" },
    "xlsx": { "method": "POST", "endpoint": "/api/v1/reports/export", "format": "xlsx" },
    "pdf": { "method": "POST", "endpoint": "/api/v1/reports/export", "format": "pdf" }
  }
}
```

### 3.2 Specific Report Endpoints

```
GET /api/v1/reports/sales
GET /api/v1/reports/profit
GET /api/v1/reports/inventory
GET /api/v1/reports/purchasing
GET /api/v1/reports/customers
GET /api/v1/reports/payments
GET /api/v1/reports/product-performance
GET /api/v1/reports/branch-comparison

GET /api/v1/reports/financial/trial-balance
GET /api/v1/reports/financial/profit-loss
GET /api/v1/reports/financial/balance-sheet
GET /api/v1/reports/financial/cash-flow
GET /api/v1/reports/financial/general-ledger
GET /api/v1/reports/financial/ar-aging
GET /api/v1/reports/financial/ap-aging
```

Each endpoint is a registered `report_id` and follows the same contract as §3.1.

### 3.3 Drill-Down

```
GET /api/v1/reports/{report_id}/drill-down
```

**Query Parameters:**

- Same as §3.1 plus
- `group_key` — the column to drill on; must be one of the `allowedDrillDownKeys` in the `ReportDefinition`
- `group_value` — the value to drill into

**Response:** Detail rows for the selected group.

---

## 4. Export

### 4.1 Export Report

```
POST /api/v1/reports/export
```

**Description:** Returns the same report data in CSV, XLSX, or PDF. Export uses the same `ReportDefinition`, validated filters, and `AuthorizedStoreScope` as JSON. Pagination is disabled for export to return the full result set up to a configured size limit. "Same dataset" means identical query semantics, not necessarily the same paginated page.

**Request:**

```json
{
  "report_id": "sales",
  "filters": {
    "date_from": "2026-08-01",
    "date_to": "2026-08-15",
    "store_id": 1
  },
  "format": "xlsx"
}
```

**Permissions:** `reports.view` + `reports.export`

**Feature flag:** Per `report_id` + `reports.export_csv`, `reports.export_xlsx`, `reports.export_pdf`

**Response:** File stream with `Content-Disposition: attachment; filename="sales-20260801-20260815.xlsx"`.

---

## 5. Dashboard Widget Configuration

### 5.1 List Widgets

```
GET /api/v1/reports/dashboard/widgets
```

### 5.2 Show Widget

```
GET /api/v1/reports/dashboard/widgets/{id}
```

### 5.3 Create Widget

```
POST /api/v1/reports/dashboard/widgets
```

**Request:**

```json
{
  "type": "kpi",
  "kpi_id": "revenue-today",
  "position": { "x": 0, "y": 0, "w": 2, "h": 1 },
  "filters": { "store_id": 1 }
}
```

### 5.4 Update Widget

```
PUT /api/v1/reports/dashboard/widgets/{id}
```

### 5.5 Delete Widget

```
DELETE /api/v1/reports/dashboard/widgets/{id}
```

**Permissions:** `reports.dashboard.manage`

---

## 6. Report Configurations

### 6.1 List Configs

```
GET /api/v1/reports/report-configs
```

### 6.2 Show Config

```
GET /api/v1/reports/report-configs/{id}
```

### 6.3 Save Config

```
POST /api/v1/reports/report-configs
```

**Request:**

```json
{
  "name": "Monthly Sales — Store 1",
  "report_id": "sales",
  "filters": { "date_from": "2026-08-01", "date_to": "2026-08-31", "store_id": 1, "group_by": "month" }
}
```

### 6.4 Update Config

```
PUT /api/v1/reports/report-configs/{id}
```

### 6.5 Delete Config

```
DELETE /api/v1/reports/report-configs/{id}
```

**Permissions:** `reports.dashboard.manage`

---

## 7. HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | OK |
| 400 | Invalid report_id or filters |
| 403 | Missing permission, feature, or store not authorized |
| 422 | Business validation error (e.g. invalid date range) |
| 504 | Export timeout |

---

## 8. Standard Filters

All report endpoints support:

- `date_from` / `date_to`
- `store_id` (single authorized store; mutually exclusive with `stores`)
- `stores` (array of authorized stores for comparison; requires `reports.comparison`; must be a subset of `AuthorizedStoreScope`; mutually exclusive with `store_id`)
- `page` / `per_page`
- `sort` — one of `allowedSortColumns` in the `ReportDefinition`, format `column:direction` where `direction` is `asc` or `desc`
- `group_by` — one of `allowedGroupBy` values in the `ReportDefinition`

Rules:

- If both `store_id` and `stores` are supplied, the request returns `422`.
- All requested stores must be within the user's `AuthorizedStoreScope`.

Financial endpoints additionally support:

- `fiscal_period_id`
- `as_of` (for balance sheet / aging)

AR/AP aging endpoints additionally support:

- `as_of` — cutoff date
- Aging buckets: `Current`, `1-30`, `31-60`, `61-90`, `90+`
- Basis: days since `due_date` if available, then `invoice_date`, then record `created_at`

Note: The `journal_entries` table is tenant-scoped and does not include `store_id`. Financial reports are tenant-level unless they join through the `reference_type`/`reference_id` to a source document that carries `store_id` (e.g. a `sale`). If `store_id` is supplied for a financial report that cannot resolve it, the filter is ignored and a `meta.note` is returned.
