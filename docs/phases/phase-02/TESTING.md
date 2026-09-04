# Phase 2 — Inventory Enhancement — Testing

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-12  
**Phase:** 2 — Inventory Enhancement  
**Depends On:** Phase 1 (Catalog & Product Enhancement — CLOSED)

---

## 1. Test Strategy

### 1.1 Test Layers

| Layer | Tool | Scope |
|-------|------|-------|
| Unit/Feature (Backend) | PHPUnit | Service logic, API endpoints, validation, security |
| E2E (Frontend) | Playwright | User workflows through browser |
| Regression | PHPUnit + Playwright | All existing tests must still pass |

### 1.2 Test Database

- MySQL `pos_saas_testing` (not SQLite)
- Each test class uses `RefreshDatabase` trait
- Auth pattern: `$this->actingAs($user, 'sanctum')` + `Auth::forgetGuards()`

---

## 2. Backend Test Files

### 2.1 Phase2MigrationTest

Verifies all new migrations and altered tables.

| Test | Description |
|------|-------------|
| `warehouses_table_exists` | Table with expected columns |
| `warehouse_stocks_table_exists` | Table with expected columns |
| `stock_batches_table_exists` | Table with expected columns |
| `stock_adjustment_reasons_table_exists` | Table with expected columns |
| `stocktake_sessions_table_exists` | Table with expected columns |
| `stocktake_items_table_exists` | Table with expected columns |
| `transfer_requests_table_exists` | Table with expected columns |
| `transfer_request_items_table_exists` | Table with expected columns |
| `inventories_has_batch_and_expiry_columns` | Added batch_id, expiry_date, maximum_quantity |
| `inventory_movements_has_batch_and_reason_columns` | Added batch_id, reason_id |
| `existing_inventories_preserved` | Data not lost during migration |
| `existing_movements_preserved` | Data not lost during migration |
| `phase2_permissions_seeded` | New permissions exist |
| `phase2_features_seeded` | New features exist |
| `adjustment_reasons_seeded` | System reasons pre-seeded |

### 2.2 Phase2WarehouseTest

| Test | Description |
|------|-------------|
| `create_warehouse` | Service creates warehouse with tenant_id |
| `list_warehouses_tenant_scoped` | Only tenant's warehouses returned |
| `update_warehouse` | Update name, address, phone |
| `delete_warehouse_without_stock` | Success |
| `delete_warehouse_with_stock_blocked` | Returns 422 |
| `api_list_warehouses` | GET /warehouses paginated |
| `api_create_warehouse` | POST /warehouses |
| `api_update_warehouse` | PUT /warehouses/{id} |
| `api_delete_warehouse` | DELETE /warehouses/{id} |
| `api_warehouse_stock` | GET /warehouses/{id}/stock |
| `api_adjust_warehouse_stock` | POST /warehouses/{id}/adjust |
| `cashier_cannot_manage_warehouse` | 403 |
| `staff_cannot_view_warehouse` | 403 |
| `tenant_isolation` | Cannot access other tenant's warehouse |

### 2.3 Phase2BatchTest

| Test | Description |
|------|-------------|
| `create_batch` | Service creates batch for product |
| `duplicate_batch_number_rejected` | Unique per tenant+product |
| `batch_with_expiry` | Expiry date set |
| `receive_stock_with_batch` | InventoryService.increase with batch_id |
| `decrease_stock_with_batch` | InventoryService.decrease with batch_id |
| `fefo_selection` | Auto-select earliest expiry batch |
| `api_list_batches` | GET /products/{id}/batches |
| `api_create_batch` | POST /products/{id}/batches |
| `batch_endpoints_disabled_without_feature` | 403 when feature off |
| `cross_tenant_batch_rejected` | Cannot use other tenant's batch |

### 2.4 Phase2StocktakeTest

| Test | Description |
|------|-------------|
| `create_session_snapshots_inventory` | system_quantity captured at creation |
| `start_counting` | Status transition draft → counting |
| `update_counted_quantity` | PUT items with counted_quantity |
| `variance_calculated` | variance = counted - system |
| `reconcile` | Status transition counting → reconciling |
| `post_creates_adjustments` | Adjustments created for variance ≠ 0 |
| `post_zero_variance_no_adjustments` | No adjustments when all match |
| `cancel_from_draft` | Status → cancelled |
| `cancel_from_counting` | Status → cancelled |
| `cannot_cancel_reconciling` | Returns 422 |
| `cannot_modify_posted` | Returns 422 |
| `api_full_workflow` | Create → start → count → reconcile → post |
| `stocktake_disabled_without_feature` | 403 when feature off |
| `tenant_isolation` | Cannot access other tenant's stocktake |
| `cashier_cannot_stocktake` | 403 |

### 2.5 Phase2TransferRequestTest

| Test | Description |
|------|-------------|
| `create_request` | Service creates with items |
| `submit_draft` | Status draft → pending |
| `approve_pending` | Status pending → approved |
| `reject_pending` | Status pending → rejected |
| `start_transit` | Status approved → in_transit, stock deducted |
| `complete_transit` | Status in_transit → completed, stock added |
| `cancel_from_draft` | Status → cancelled |
| `cancel_from_pending` | Status → cancelled |
| `cancel_in_transit_returns_stock` | Stock returned to source |
| `cannot_approve_own_request` | 422 when approver == requester |
| `cannot_approve_non_pending` | 422 |
| `cannot_complete_non_in_transit` | 422 |
| `insufficient_stock_at_transit` | 422 |
| `store_to_store_transfer` | Full workflow |
| `warehouse_to_store_transfer` | Full workflow |
| `api_full_workflow` | Create → submit → approve → transit → complete |
| `transfer_request_disabled_without_feature` | 403 when feature off |
| `tenant_isolation` | Cannot access other tenant's request |
| `cross_tenant_from_to_rejected` | From/to must belong to tenant |

### 2.6 Phase2ValuationTest

| Test | Description |
|------|-------------|
| `fifo_valuation` | First-in first-out calculation |
| `lifo_valuation` | Last-in first-out calculation |
| `average_valuation` | Weighted average calculation |
| `valuation_with_no_movements` | Returns 0 |
| `valuation_report_api` | GET /inventory/reports/valuation |
| `valuation_disabled_without_feature` | 403 when feature off |
| `tenant_isolation` | Only tenant's movements used |

### 2.7 Phase2AdjustmentReasonTest

| Test | Description |
|------|-------------|
| `system_reasons_seeded` | Pre-seeded reasons exist |
| `create_custom_reason` | Tenant creates custom reason |
| `update_custom_reason` | Update name, is_active |
| `delete_custom_reason` | Success |
| `cannot_delete_system_reason` | Returns 422 |
| `cannot_update_system_reason_name` | Returns 422 |
| `toggle_system_reason_active` | Can toggle is_active on system reason |
| `api_list_reasons` | GET /adjustment-reasons |
| `api_create_reason` | POST /adjustment-reasons |
| `adjust_with_reason` | POST /inventory/adjust with reason_id |
| `movement_records_reason` | Movement has reason_id after adjust |

### 2.8 Phase2InventoryEnhancedTest

| Test | Description |
|------|-------------|
| `inventory_has_maximum_quantity` | Column exists and nullable |
| `adjust_with_batch_id` | Movement records batch_id |
| `list_inventory_with_batch_filter` | Filter by batch_id |
| `list_inventory_expiring_within` | Filter by expiry date range |
| `movements_include_batch_and_reason` | Response includes relations |
| `low_stock_report` | Products below minimum_quantity |
| `stock_summary_report` | Aggregated stock by product |
| `expiry_report` | Products expiring within N days |
| `expiry_report_disabled_without_feature` | 403 when feature off |
| `existing_inventory_tests_still_pass` | Regression |

---

## 3. E2E Test Plan

### 3.1 Test File: `frontend/e2e/phase2.spec.ts`

| # | Test | Description |
|---|------|-------------|
| 1 | owner can create warehouse | Navigate to warehouses, create, verify in table |
| 2 | owner can view warehouse stock | Click warehouse, see stock table |
| 3 | owner can adjust warehouse stock | Adjust button, modal, reason dropdown |
| 4 | owner can create stocktake session | Navigate, create, verify items populated |
| 5 | owner can count and post stocktake | Full workflow: count → reconcile → post |
| 6 | owner can create transfer request | Create with items, submit |
| 7 | manager can approve transfer request | Different user approves |
| 8 | owner can complete transfer request | Transit → complete, verify stock moved |
| 9 | owner can view inventory valuation report | Navigate to reports, see valuation |
| 10 | owner can view low stock report | Navigate, see low stock items |
| 11 | owner can manage adjustment reasons | Create custom reason |
| 12 | cashier cannot manage warehouses | 403/redirect |
| 13 | staff cannot access stocktake | 403/redirect |
| 14 | existing inventory page still works | Regression smoke |
| 15 | existing POS flow still works | Regression smoke |

---

## 4. Regression Test Plan

### 4.1 Backend Regression

All 868 existing tests must pass unchanged:
- Inventory tests (increase, decrease, adjust, transfer, concurrent, rollback)
- Product tests (CRUD, variants, barcodes, images)
- Category tests (hierarchy, cycle prevention)
- Sale tests (checkout, cancel, payments)
- Purchase tests (order, receive, cancel, returns)
- Supplier tests
- Customer tests
- Phase 0 tests (modules, registration, RBAC, payment gateway)
- Phase 1 tests (catalog, variants, price lists, units, import/export)

### 4.2 E2E Regression

All existing E2E specs must pass:
- Phase 0 specs (7)
- Phase 1 specs (20)
- Original regression specs (18)

### 4.3 Expected Test Counts

| Category | Tests |
|----------|-------|
| Existing backend | 868 |
| New Phase 2 backend | ~80 |
| **Total backend** | **~948** |
| Existing E2E | 45 |
| New Phase 2 E2E | 15 |
| **Total E2E** | **~60** |

---

## 5. Test Execution

### 5.1 Backend

```bash
docker compose exec backend php artisan test --testsuite=Feature
```

### 5.2 E2E

```bash
docker compose exec frontend npx playwright test e2e/phase2.spec.ts
```

### 5.3 Full Regression

```bash
docker compose exec backend php artisan test
docker compose exec frontend npx playwright test
```

---

*End of Phase 2 Testing*
