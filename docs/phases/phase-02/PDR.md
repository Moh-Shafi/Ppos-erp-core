# Phase 2 — Inventory Enhancement — PDR

**Document Status:** APPROVED — Phase 2 Complete  
**Created:** 2026-08-12  
**Phase:** 2 — Inventory Enhancement  
**Depends On:** Phase 1 (Catalog & Product Enhancement — CLOSED)

---

## 1. Objective

Upgrade the inventory system from basic stock tracking to a full ERP-grade inventory management module supporting warehouses, batch/lot tracking, expiry dates, stock valuation, stocktake (physical count), and transfer requests with approval workflow.

All features are **business-type agnostic** — they work for any tenant regardless of business type. Business types only control which modules/features are enabled by default.

---

## 2. Deliverables

### 2.1 Warehouse Support
- Separate `warehouses` table (distinct from `stores`)
- `warehouse_stocks` table for per-warehouse inventory
- Stores can draw stock from warehouses via transfer requests
- Warehouse CRUD API + frontend page

### 2.2 Batch/Lot Tracking (Feature-Flagged: `inventory.batch_tracking`)
- `stock_batches` table: batch number, product, quantity, received date
- `inventories` table gains `batch_id` (nullable) and `expiry_date` (nullable)
- `inventory_movements` table gains `batch_id` (nullable)
- When feature disabled, batch fields are ignored (nullable)
- Pharmacy and manufacturing business types get this enabled by default

### 2.3 Expiry Date Tracking (Feature-Flagged: `inventory.expiry_tracking`)
- Expiry date stored on stock batches and inventory rows
- FEFO (First Expiry First Out) suggestion in stocktake and sales
- Expiry alerts dashboard widget (products expiring within 30/60/90 days)
- Pharmacy gets this enabled by default

### 2.4 Stock Valuation (FIFO, LIFO, Average)
- Configurable per tenant via `tenant_settings` or business profile
- `stock_valuation_method` column: `fifo`, `lifo`, `average`
- Valuation calculated from `inventory_movements` (cost price at time of movement)
- Stock valuation report endpoint

### 2.5 Min/Max Stock Levels per Product per Store
- `inventories` table gains `maximum_quantity` (nullable)
- Minimum already exists as `minimum_quantity`
- Reorder suggestion report (products below min or above max)

### 2.6 Stock Adjustment Reasons
- `stock_adjustment_reasons` table (categorized, per-tenant)
- Pre-seeded categories: damaged, lost, found, recount, initial stock, other
- Each adjustment movement records a reason
- Custom reasons per tenant

### 2.7 Stocktake / Inventory Count Module
- `stocktake_sessions` table: session number, store, status (draft, counting, reconciling, posted, cancelled)
- `stocktake_items` table: session, product, system_quantity, counted_quantity, variance, note
- Workflow: create → count → reconcile → post
- On post: adjustments created automatically via InventoryService
- Feature-flagged: `inventory.stocktake`

### 2.8 Transfer Requests (Approval Workflow)
- `transfer_requests` table: from_store, to_store (or warehouse), status (draft, pending, approved, rejected, in_transit, completed, cancelled)
- `transfer_request_items` table: product, quantity, batch_id (nullable)
- Workflow: draft → pending → approved → in_transit → completed
- Manager/Owner approval required
- On completion: InventoryService.transfer() called
- Replaces the current direct transfer (which remains for quick transfers)

### 2.9 Inventory Reports
- Stock summary (current stock by store/warehouse)
- Movement history (already exists, enhanced with batch info)
- Stock valuation report (FIFO/LIFO/Average)
- Low stock / reorder report
- Expiry report (when feature enabled)

---

## 3. Database Changes

### New Tables

| Table | Purpose |
|------|---------|
| `warehouses` | Warehouse locations per tenant |
| `warehouse_stocks` | Stock levels per warehouse per product |
| `stock_batches` | Batch/lot records with expiry dates |
| `stock_adjustment_reasons` | Categorized adjustment reasons per tenant |
| `stocktake_sessions` | Stocktake session headers |
| `stocktake_items` | Stocktake line items with counted quantities |
| `transfer_requests` | Transfer request headers with approval workflow |
| `transfer_request_items` | Transfer request line items |

### Modified Tables

| Table | Changes |
|------|---------|
| `inventories` | Add `batch_id` (nullable FK), `expiry_date` (nullable date), `maximum_quantity` (nullable int) |
| `inventory_movements` | Add `batch_id` (nullable FK), `reason_id` (nullable FK) |

### Tenant Settings
| Setting | Type | Default | Notes |
|---------|------|---------|-------|
| `stock_valuation_method` | enum | `average` | `fifo`, `lifo`, `average` |

---

## 4. Feature Flags

| Feature Slug | Module | Default Enabled | Description |
|-------------|--------|-----------------|-------------|
| `inventory.batch_tracking` | inventory | false (pharmacy, manufacturing: true) | Enable batch/lot tracking |
| `inventory.expiry_tracking` | inventory | false (pharmacy: true) | Enable expiry date tracking |
| `inventory.stocktake` | inventory | false | Enable stocktake module |
| `inventory.transfer_request` | inventory | true | Enable transfer request approval workflow |
| `inventory.valuation` | inventory | true | Enable stock valuation reports |
| `inventory.warehouse` | warehouse | false (wholesale, manufacturing: true) | Enable warehouse module |

### New Permissions

| Permission | Owner | Manager | Cashier | Staff | Accountant |
|------------|-------|---------|---------|-------|------------|
| `warehouse.view` | ✅ | ✅ | ✅ | ❌ | ✅ |
| `warehouse.manage` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `inventory.stocktake` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `inventory.valuation` | ✅ | ✅ | ❌ | ❌ | ✅ |

---

## 5. Acceptance Criteria

- [x] Batch tracking works (when feature enabled) — create batch, receive stock with batch, query by batch
- [x] Expiry tracking works (when feature enabled) — set expiry, query expiring products, FEFO suggestion
- [x] Stocktake process works (create → count → reconcile → post) — full workflow with variance calculation
- [x] Transfer request approval workflow (draft → pending → approved → in_transit → completed)
- [x] Stock valuation report (FIFO, LIFO, Average) — correct calculations
- [x] Warehouse support (CRUD, stock management, transfer to store)
- [x] Stock adjustment reasons (pre-seeded + custom)
- [x] Min/max stock levels enforced and reported
- [x] All existing inventory tests pass (regression)
- [x] New tests for batch, expiry, stocktake, transfer requests, valuation, warehouse
- [x] Feature flags correctly gate functionality
- [x] Frontend pages for all new features
- [x] E2E tests for key workflows

---

## 6. Constraints

1. **Additive only** — no existing columns dropped, no existing tables renamed
2. **InventoryService preserved** — all stock changes still go through `InventoryService` with `lockForUpdate`
3. **Tenant isolation** — all new models use `BelongsToTenant`
4. **tenant_id never from request** — auto-set from `Auth::user()->tenant_id`
5. **Feature-flagged** — batch/expiry/stocktake features gracefully degrade when disabled
6. **Business-type agnostic** — inventory works for all business types; business type only controls defaults
7. **Backward compatible** — existing direct transfer endpoint remains functional

---

## 7. Dependencies

- Phase 0 (ERP Architecture) — module/feature system, RBAC, audit logging
- Phase 1 (Catalog & Product Enhancement) — units, product variants, product model with `base_unit_id`, `is_trackable`, `min_stock`

---

*End of Phase 2 PDR*
