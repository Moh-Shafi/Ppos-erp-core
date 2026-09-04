# Phase 7 — Reports & Analytics — Testing

**Document Status:** APPROVED  
**Created:** 2026-08-15  
**Last Updated:** 2026-08-16  
**Phase:** 7 — Reports & Analytics  
**Depends On:** `docs/phases/phase-07/ARCHITECTURE.md` — APPROVED / FROZEN

---

## 1. Backend Feature Tests

Implemented test files:

- `tests/Feature/Phase7ReportsTest.php` — report engine, sales, trial balance, tenant/store isolation
- `tests/Feature/Phase7ReportExportTest.php` — CSV, XLSX, PDF export, authorization, same-dataset integrity
- `tests/Feature/Phase7DashboardTest.php` — dashboard widgets, KPIs, widget CRUD, report config CRUD, tenant/user isolation
- `tests/Unit/Phase7ReportEngineTest.php` — registry, engine, validation, unauthorized store rejection
- `tests/Unit/Phase7RuntimeIsolationTest.php` — query runtime and service boundaries

Key passing assertions:

- Unregistered `report_id` returns 400
- Unauthorized `store_id` returns 422
- Export inherits permission and shares dataset with JSON endpoint
- Dashboard widgets and report configs are scoped by `tenant_id` and `user_id`
- Staff without `reports.dashboard.manage` cannot modify widgets or configs

---

## 2. E2E Tests

File: `frontend/e2e/phase7.spec.ts`

- `reports dashboard loads for owner`
- `sales report loads and CSV export triggers download`
- `staff is redirected away from reports`

---

## 3. Regression Gate

```bash
docker exec pos_saas_backend php artisan test
```

**Result (2026-08-16):** 1138 tests passed, 2840 assertions, 0 failures, duration 1075.61s.

**Frontend build:** `npm run build` — succeeded, 188 modules transformed.

**E2E tests:** 3 passed (1.7m) — `phase7.spec.ts`

---

## 4. Security Tests

- `test arbitrary report_id rejected`
- `test arbitrary sql not accepted`
- `test cross-tenant report blocked`
- `test store_id outside authorized scope blocked`
- `test export inherits permission`

---

## 5. Performance / Snapshot Tests

- `test reports run without snapshot`
- `test snapshot ttl invalidates`
- `test heavy report paginates`
- `test export limited by size/time`
