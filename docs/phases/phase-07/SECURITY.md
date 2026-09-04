# Phase 7 — Reports & Analytics — Security

**Document Status:** DRAFT  
**Created:** 2026-08-15  
**Phase:** 7 — Reports & Analytics  
**Depends On:** `docs/phases/phase-07/ARCHITECTURE.md` — APPROVED / FROZEN

---

## 1. Threat Model

| Threat | Mitigation |
|--------|------------|
| Tenant A user reads Tenant B data | `tenant_id` applied in every `ReportQuery` |
| User queries an unauthorized store | `AuthorizedStoreScope` enforced in services |
| User bypasses permission with arbitrary `store_id` | `store_id` validated against `AuthorizedStoreScope`; `store_id` and `stores[]` are mutually exclusive; all entries in `stores[]` are validated as a subset of `AuthorizedStoreScope` |
| User runs arbitrary SQL | `report_id` resolved to version-controlled `ReportDefinition`; no raw SQL accepted |
| User exports data they cannot view | Export reuses the same permission/feature gates as JSON |
| User modifies another user’s dashboard | `dashboard_widgets` filtered by `user_id` and `tenant_id`; widget filters are validated against `ReportDefinition` and `AuthorizedStoreScope` before save and on load |
| Stale snapshot returns wrong data | TTL, version, and invalidation on source data change |
| KPI query logic tampered via DB | `kpi_definitions` stores only metadata; `KpiRegistry` in code |
| Overload via heavy export | Rate limiting, pagination, sync export size limits |
| Cross-tenant analytics | Out of scope and forbidden in Phase 7 |

---

## 2. Mandatory Security Rules

### 2.1 Every Query Requires Tenant + Store Scope

```php
$storeScope = AuthorizedStoreScope::forUser($user);
ReportQuery::build($definition, $filters, $user->tenant, $storeScope);
```

No report may execute without these two inputs.

### 2.2 `report_id` Is Immutable and Registered

```php
$definition = ReportRegistry::get($report_id);
if (!$definition) { throw new UnregisteredReportException(); }
```

### 2.3 Filters Are Whitelisted

Only filters declared in the `ReportDefinition` are accepted. Unknown filter keys are rejected. The same rule applies to `group_by` (must be in `allowedGroupBy`), `group_key` for drill-down (must be in `allowedDrillDownKeys`), and `sort` (column must be in `allowedSortColumns` and direction must be `asc` or `desc`).

### 2.4 Permission and Feature Gates

```php
$user->mustHavePermission($definition->requiredPermission);
$tenant->mustHaveFeature($definition->requiredFeature);
```

### 2.5 Exports Share Authorization

Export endpoints call the same `ReportEngine` path as JSON endpoints. The same `tenant_id`, `AuthorizedStoreScope`, and permission checks apply.

### 2.6 No Cross-Tenant Reporting

Cross-tenant queries are explicitly out of scope and are blocked at the database layer.

---

## 3. RBAC Reference

| Permission | Endpoint / Action |
|------------|-------------------|
| `reports.view` | `GET /reports/{report_id}`, `GET /reports/dashboard` |
| `reports.export` | `POST /reports/export` |
| `reports.dashboard.manage` | `POST/PATCH/DELETE /reports/dashboard/widgets` |
| `reports.comparison` | Multi-store `stores` filter |
| `reports.financial` | `/reports/financial/*` |

---

## 4. Audit and Logging

| Event | Log Type |
|-------|----------|
| Report executed | audit log (tenant, user, report_id, filters) |
| Export downloaded | audit log |
| Unregistered report requested | audit log + 400 |
| Store outside scope | audit log + 403 |
| Missing permission | audit log + 403 |
| Snapshot invalidated | application log |

---

## 5. Data Handling

- Reporting tables (`report_configs`, `dashboard_widgets`, `report_snapshots`) contain no business facts.
- `report_configs` are revalidated against the current `AuthorizedStoreScope` on every load.
- `dashboard_widgets` filters are revalidated on load.
- `report_snapshots` are encrypted at rest (optional) and have TTL.
- Export files are not stored on disk; they are streamed to the client.

---

## 6. Contract Review Security Fixes (Implemented)

- AR/AP aging `as_of` values are now bound as query parameters (`?`) inside `DB::raw` instead of string interpolation.
- `ReportEngine` validates `group_by` against `allowedGroupBy`, `sort` column against `allowedSortColumns`, and sort direction against `asc`/`desc`.
- Sales/Purchasing/Payments `allowedGroupBy` and columns were aligned so unimplemented groupings cannot be requested.
- `CashFlow` query now outputs the `classification` column documented in the contract.
- `ApAging` balance is restricted to `received` purchase orders to avoid recognising un-received liabilities.
