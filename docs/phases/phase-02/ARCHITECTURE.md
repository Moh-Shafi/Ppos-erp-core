# Phase 2 — Inventory Enhancement — Architecture

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-12  
**Phase:** 2 — Inventory Enhancement  
**Depends On:** Phase 1 (Catalog & Product Enhancement — CLOSED)

---

## 1. Current State

### 1.1 Existing Inventory System

```
Inventory (per store per product)
  ├── store_id (FK)
  ├── product_id (FK)
  ├── quantity (int)
  ├── minimum_quantity (int)
  └── tenant_id (BelongsToTenant)

InventoryMovement (log of every stock change)
  ├── store_id, product_id, user_id
  ├── type: purchase|sale|sale_return|purchase_return|adjustment|transfer_in|transfer_out|initial
  ├── quantity (signed: +increase / -decrease)
  ├── before_quantity, after_quantity
  ├── reference_type, reference_id (polymorphic)
  └── note
```

### 1.2 Existing InventoryService

- `increase()` — add stock, creates movement
- `decrease()` — remove stock, creates movement, checks sufficient
- `adjust()` — positive or negative delta
- `transfer()` — atomic inter-store transfer with ordered locking
- All operations: `DB::transaction()` + `lockForUpdate()`
- Tenant ownership validation on store + product

### 1.3 Existing API Routes

```
GET    /api/v1/inventory              — list inventory (paginated, filterable)
GET    /api/v1/inventory/movements    — list movements
GET    /api/v1/inventory/{productId}  — show product inventory across stores
POST   /api/v1/inventory/adjust       — adjust stock
POST   /api/v1/inventory/transfer     — direct transfer (no approval)
```

---

## 2. New Architecture

### 2.1 Warehouse Layer

```
Tenant
  ├── Stores (POS-facing locations)
  └── Warehouses (storage locations, no POS)
        └── WarehouseStock (per warehouse per product)
```

Warehouses are separate from stores. A warehouse holds bulk stock. Stores request stock from warehouses via transfer requests. Direct warehouse-to-store transfers use the same `InventoryService` but with warehouse as source.

**Design decision:** Warehouses are NOT stores with a flag. They have their own table and stock table. This avoids polluting store-based logic (POS, sales) with warehouse concerns.

### 2.2 Batch/lot Tracking

```
StockBatch
  ├── product_id (FK)
  ├── batch_number (string, unique per tenant+product)
  ├── quantity (int, current batch quantity)
  ├── received_date (date)
  ├── expiry_date (nullable date)
  ├── cost_price (decimal, for valuation)
  └── tenant_id
```

When `inventory.batch_tracking` is enabled:
- `Inventory` rows can have `batch_id` (nullable — non-batched products still work)
- `InventoryMovement` records `batch_id` when applicable
- Receiving stock via purchase can assign a batch
- Sales/adjustments can specify which batch to draw from

When disabled: `batch_id` is always null, batch endpoints return 403.

### 2.3 Expiry Tracking

When `inventory.expiry_tracking` is enabled:
- `Inventory` rows have `expiry_date` (nullable)
- `StockBatch` has `expiry_date`
- FEFO: when decreasing stock, suggest the batch with earliest expiry
- Expiry report: products expiring within N days

### 2.4 Stock Valuation

```
Tenant Setting: stock_valuation_method = fifo | lifo | average

Valuation calculation:
  FIFO  — oldest movements (by created_at) consumed first
  LIFO  — newest movements consumed first
  Average — weighted average cost: sum(cost * qty) / sum(qty)
```

Valuation uses `inventory_movements` with `type = 'purchase'` (incoming stock with cost) and `type = 'sale'` (outgoing). The cost price is taken from the product's `cost_price` at time of movement (snapshot in movement metadata or product).

**Implementation:** `StockValuationService` calculates valuation on-demand. No stored valuation cache (simpler, always accurate). Can add cache later if performance requires.

### 2.5 Stocktake Module

```
StocktakeSession
  ├── session_number (auto-generated, unique per tenant)
  ├── store_id (FK)
  ├── status: draft|counting|reconciling|posted|cancelled
  ├── created_by (user_id)
  ├── started_at, completed_at (nullable)
  └── tenant_id

StocktakeItem
  ├── stocktake_session_id (FK)
  ├── product_id (FK)
  ├── system_quantity (int, snapshot at session start)
  ├── counted_quantity (nullable int, filled during count)
  ├── variance (int, computed: counted - system)
  ├── note (nullable)
  └── tenant_id
```

**Workflow:**
1. **Create** (draft): snapshot system quantities → `system_quantity` recorded
2. **Count** (counting): user enters `counted_quantity` per item
3. **Reconcile** (reconciling): review variances, add notes
4. **Post** (posted): create adjustment movements via `InventoryService.adjust()` for each item with variance ≠ 0
5. **Cancel**: can cancel from draft or counting

### 2.6 Transfer Requests

```
TransferRequest
  ├── request_number (auto-generated)
  ├── from_store_id / from_warehouse_id (one must be set)
  ├── to_store_id / to_warehouse_id (one must be set)
  ├── status: draft|pending|approved|rejected|in_transit|completed|cancelled
  ├── requested_by (user_id)
  ├── approved_by (nullable user_id)
  ├── approved_at (nullable timestamp)
  ├── note (nullable)
  └── tenant_id

TransferRequestItem
  ├── transfer_request_id (FK)
  ├── product_id (FK)
  ├── quantity (int)
  ├── batch_id (nullable FK, when batch tracking enabled)
  └── tenant_id
```

**Workflow:**
1. **Draft** → user creates request with items
2. **Pending** → user submits for approval
3. **Approved** → manager/owner approves (or **Rejected**)
4. **In Transit** → stock reserved/deducted from source
5. **Completed** → stock added to destination (via `InventoryService.transfer()` or warehouse equivalent)
6. **Cancelled** → from draft or pending only

**Approval rules:**
- Only Owner or Manager can approve
- Requested_by user cannot approve their own request (segregation of duties)
- Can be cancelled by creator (draft/pending) or approver (approved/in_transit)

### 2.7 Stock Adjustment Reasons

```
StockAdjustmentReason
  ├── name (string)
  ├── category: damaged|lost|found|recount|initial|other
  ├── is_system (bool — pre-seeded, cannot delete)
  ├── is_active (bool)
  └── tenant_id
```

Pre-seeded system reasons:
- Damaged Goods (damaged)
- Lost/Missing (lost)
- Found/Surplus (found)
- Recount Adjustment (recount)
- Initial Stock (initial)
- Other (other)

Custom reasons: tenant can create additional reasons.

---

## 3. Service Layer

### 3.1 Existing Services (Enhanced)

**InventoryService** — add optional `batch_id` and `reason_id` parameters:
- `increase()` — add `?int $batchId = null, ?int $reasonId = null`
- `decrease()` — add `?int $batchId = null, ?int $reasonId = null`
- `adjust()` — add `?int $batchId = null, ?int $reasonId = null`
- `transfer()` — add `?int $batchId = null`
- New: `increaseWarehouse()`, `decreaseWarehouse()` — warehouse stock operations
- New: `transferWarehouseToStore()` — warehouse → store
- New: `transferStoreToWarehouse()` — store → warehouse

### 3.2 New Services

| Service | Responsibility |
|---------|---------------|
| `WarehouseService` | Warehouse CRUD, warehouse stock management |
| `StockBatchService` | Batch CRUD, batch assignment, FEFO selection |
| `StocktakeService` | Session lifecycle, item management, posting |
| `TransferRequestService` | Request lifecycle, approval, execution |
| `StockValuationService` | FIFO/LIFO/Average calculations |
| `AdjustmentReasonService` | Reason CRUD, seeding |

---

## 4. Model Relationships

```
Tenant
  ├── Warehouses → WarehouseStock → Product
  ├── StockBatches → Product
  ├── StockAdjustmentReasons
  ├── StocktakeSessions → StocktakeItems → Product
  └── TransferRequests → TransferRequestItems → Product

Inventory
  ├── batch_id → StockBatch (nullable)
  └── (new) maximum_quantity

InventoryMovement
  ├── batch_id → StockBatch (nullable)
  └── reason_id → StockAdjustmentReason (nullable)
```

---

## 5. Migration Plan

### 5.1 New Migrations (000043–000052)

| # | Migration | Table/Change |
|---|-----------|-------------|
| 043 | `create_warehouses_table` | `warehouses` |
| 044 | `create_warehouse_stocks_table` | `warehouse_stocks` |
| 045 | `create_stock_batches_table` | `stock_batches` |
| 046 | `create_stock_adjustment_reasons_table` | `stock_adjustment_reasons` |
| 047 | `add_batch_and_expiry_to_inventories_table` | Alter `inventories` (+batch_id, +expiry_date, +maximum_quantity) |
| 048 | `add_batch_and_reason_to_inventory_movements_table` | Alter `inventory_movements` (+batch_id, +reason_id) |
| 049 | `create_stocktake_sessions_table` | `stocktake_sessions` |
| 050 | `create_stocktake_items_table` | `stocktake_items` |
| 051 | `create_transfer_requests_table` | `transfer_requests` |
| 052 | `create_transfer_request_items_table` | `transfer_request_items` |

### 5.2 Seeders

- `StockAdjustmentReasonSeeder` — pre-seed system reasons
- Update `ModuleSeeder` — add new features (`inventory.transfer_request`, `inventory.valuation`)
- Update `RbacSeeder` — add new permissions (`warehouse.view`, `warehouse.manage`, `inventory.stocktake`, `inventory.valuation`)
- Update `BusinessTypeSeeder` — map new features to business types

### 5.3 Safety

- All migrations are **additive** — no existing column dropped
- New columns on `inventories` and `inventory_movements` are all **nullable**
- Existing code continues to work without changes (new params are optional)
- Rollback: drop new tables, drop new columns

---

## 6. Frontend Architecture

### 6.1 New Pages

| Page | Route | Module | Permission |
|------|-------|--------|------------|
| WarehousesPage | `/warehouses` | warehouse | `warehouse.view` |
| WarehouseDetailPage | `/warehouses/:id` | warehouse | `warehouse.view` |
| StocktakePage | `/stocktake` | inventory | `inventory.stocktake` |
| StocktakeDetailPage | `/stocktake/:id` | inventory | `inventory.stocktake` |
| TransferRequestsPage | `/transfer-requests` | inventory | `inventory.view` |
| TransferRequestDetailPage | `/transfer-requests/:id` | inventory | `inventory.view` |
| InventoryReportsPage | `/inventory/reports` | inventory | `inventory.view` |

### 6.2 Enhanced Pages

| Page | Enhancement |
|------|-------------|
| InventoryPage | Show batch info, expiry date, max quantity, adjustment reason |
| Dashboard | Expiry alert widget (when feature enabled) |

### 6.3 New Services

| Service | Methods |
|---------|---------|
| `warehouseService` | list, get, create, update, delete, getStock, adjustStock |
| `stocktakeService` | list, get, create, startCounting, updateItem, reconcile, post, cancel |
| `transferRequestService` | list, get, create, submit, approve, reject, startTransit, complete, cancel |
| `stockBatchService` | list, get, create, getByProduct |
| `adjustmentReasonService` | list, create, update, delete |

| Service | Methods |
|---------|---------|
| `inventoryReportService` | stockSummary, valuation, lowStock, expiryReport, movementHistory |

---

## 7. Key Design Decisions

1. **Warehouses ≠ Stores** — separate tables, separate stock tables. Stores have POS, warehouses don't.
2. **Batch tracking is optional** — nullable `batch_id` everywhere. System works with or without batches.
3. **Stocktake posts via InventoryService** — ensures same locking, movement logging, and audit trail.
4. **Transfer requests are an approval layer on top of InventoryService.transfer()** — the existing direct transfer remains for quick operations.
5. **Valuation is calculated on-demand** — no cached valuation columns. Simpler, always accurate.
6. **FEFO is a suggestion, not enforcement** — system suggests earliest-expiry batch but user can override.
7. **All new features are feature-flagged** — tenant can enable/disable per feature.

---

*End of Phase 2 Architecture*
