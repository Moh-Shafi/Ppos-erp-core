# Phase 2 — Inventory Enhancement — API

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-12  
**Phase:** 2 — Inventory Enhancement  
**Depends On:** Phase 1 (Catalog & Product Enhancement — CLOSED)

---

## 1. API Conventions

- Base URL: `/api/v1`
- Auth: `Authorization: Bearer {token}` (Sanctum)
- All endpoints require `auth:sanctum` middleware
- Permission middleware: `permission:{slug}`
- Feature middleware: `feature:{slug}` (where noted)
- Responses: JSON, paginated endpoints return Laravel pagination structure
- Error format: `{"message": "...", "errors": {"field": ["..."]}}`

---

## 2. Warehouses

### 2.1 List Warehouses
```
GET /api/v1/warehouses
Permission: warehouse.view
Query: ?search=&is_active=&page=&per_page=
Response 200: { data: [...], total, per_page, current_page, last_page }
```

### 2.2 Get Warehouse
```
GET /api/v1/warehouses/{id}
Permission: warehouse.view
Response 200: { warehouse: { id, name, address, phone, is_active, created_at } }
```

### 2.3 Create Warehouse
```
POST /api/v1/warehouses
Permission: warehouse.manage
Body: { name: string, address?: string, phone?: string, is_active?: bool }
Response 201: { message, warehouse }
```

### 2.4 Update Warehouse
```
PUT /api/v1/warehouses/{id}
Permission: warehouse.manage
Body: { name?, address?, phone?, is_active? }
Response 200: { message, warehouse }
```

### 2.5 Delete Warehouse
```
DELETE /api/v1/warehouses/{id}
Permission: warehouse.manage
Response 200: { message }
Error 422: if warehouse has stock
```

### 2.6 Warehouse Stock
```
GET /api/v1/warehouses/{id}/stock
Permission: warehouse.view
Query: ?search=&batch_id=&low_stock=&page=&per_page=
Response 200: { data: [...], total, ... }
Item: { id, product_id, product: {name, sku}, quantity, batch_id, batch?: {...}, expiry_date }
```

### 2.7 Adjust Warehouse Stock
```
POST /api/v1/warehouses/{id}/adjust
Permission: warehouse.manage
Body: { product_id: int, delta: int, reason_id?: int, batch_id?: int, note?: string }
Response 200: { message, movement }
```

---

## 3. Stock Batches (Feature: inventory.batch_tracking)

### 3.1 List Batches
```
GET /api/v1/products/{productId}/batches
Permission: inventory.view
Feature: inventory.batch_tracking
Response 200: { data: [...] }
Item: { id, batch_number, quantity, received_date, expiry_date, cost_price }
```

### 3.2 Create Batch
```
POST /api/v1/products/{productId}/batches
Permission: inventory.manage
Feature: inventory.batch_tracking
Body: { batch_number: string, quantity: int, received_date: date, expiry_date?: date, cost_price?: decimal }
Response 201: { message, batch }
Error 422: duplicate batch_number for same tenant+product
```

### 3.3 Get Batch
```
GET /api/v1/batches/{id}
Permission: inventory.view
Feature: inventory.batch_tracking
Response 200: { batch: {...} }
```

---

## 4. Stocktake (Feature: inventory.stocktake)

### 4.1 List Sessions
```
GET /api/v1/stocktake
Permission: inventory.view
Feature: inventory.stocktake
Query: ?status=&store_id=&page=&per_page=
Response 200: { data: [...], total, ... }
```

### 4.2 Get Session
```
GET /api/v1/stocktake/{id}
Permission: inventory.view
Feature: inventory.stocktake
Response 200: { stocktake: { ..., items: [...] } }
```

### 4.3 Create Session
```
POST /api/v1/stocktake
Permission: inventory.stocktake
Feature: inventory.stocktake
Body: { store_id: int, note?: string }
Response 201: { message, stocktake }
Note: System snapshots all inventory for store into stocktake_items
```

### 4.4 Start Counting
```
POST /api/v1/stocktake/{id}/start
Permission: inventory.stocktake
Feature: inventory.stocktake
Response 200: { message, stocktake }
Error 422: if status != draft
```

### 4.5 Update Counted Quantity
```
PUT /api/v1/stocktake/{id}/items/{itemId}
Permission: inventory.stocktake
Feature: inventory.stocktake
Body: { counted_quantity: int, note?: string }
Response 200: { message, item }
Error 422: if session status != counting
```

### 4.6 Reconcile
```
POST /api/v1/stocktake/{id}/reconcile
Permission: inventory.stocktake
Feature: inventory.stocktake
Response 200: { message, stocktake }
Error 422: if status != counting, if any items have no counted_quantity
```

### 4.7 Post
```
POST /api/v1/stocktake/{id}/post
Permission: inventory.stocktake
Feature: inventory.stocktake
Body: { reason_id: int }
Response 200: { message, stocktake, adjustments_created: int }
Note: Creates InventoryService.adjust() for each item with variance ≠ 0
Error 422: if status != reconciling
```

### 4.8 Cancel
```
POST /api/v1/stocktake/{id}/cancel
Permission: inventory.stocktake
Feature: inventory.stocktake
Response 200: { message, stocktake }
Error 422: if status not in [draft, counting]
```

---

## 5. Transfer Requests (Feature: inventory.transfer_request)

### 5.1 List Requests
```
GET /api/v1/transfer-requests
Permission: inventory.view
Feature: inventory.transfer_request
Query: ?status=&from_store_id=&to_store_id=&page=&per_page=
Response 200: { data: [...], total, ... }
```

### 5.2 Get Request
```
GET /api/v1/transfer-requests/{id}
Permission: inventory.view
Feature: inventory.transfer_request
Response 200: { transfer_request: { ..., items: [...] } }
```

### 5.3 Create Request
```
POST /api/v1/transfer-requests
Permission: inventory.manage
Feature: inventory.transfer_request
Body: {
  from_store_id?: int, from_warehouse_id?: int,
  to_store_id?: int, to_warehouse_id?: int,
  items: [{ product_id: int, quantity: int, batch_id?: int }],
  note?: string
}
Response 201: { message, transfer_request }
Validation: exactly one from_* and one to_* must be set
```

### 5.4 Submit
```
POST /api/v1/transfer-requests/{id}/submit
Permission: inventory.manage
Feature: inventory.transfer_request
Response 200: { message, transfer_request }
Error 422: if status != draft, if items empty
```

### 5.5 Approve
```
POST /api/v1/transfer-requests/{id}/approve
Permission: inventory.manage
Feature: inventory.transfer_request
Response 200: { message, transfer_request }
Error 422: if status != pending, if approver == requester
```

### 5.6 Reject
```
POST /api/v1/transfer-requests/{id}/reject
Permission: inventory.manage
Feature: inventory.transfer_request
Body: { reason?: string }
Response 200: { message, transfer_request }
Error 422: if status != pending
```

### 5.7 Start Transit
```
POST /api/v1/transfer-requests/{id}/transit
Permission: inventory.manage
Feature: inventory.transfer_request
Response 200: { message, transfer_request }
Note: Stock deducted from source
Error 422: if status != approved, if insufficient stock
```

### 5.8 Complete
```
POST /api/v1/transfer-requests/{id}/complete
Permission: inventory.manage
Feature: inventory.transfer_request
Response 200: { message, transfer_request }
Note: Stock added to destination
Error 422: if status != in_transit
```

### 5.9 Cancel
```
POST /api/v1/transfer-requests/{id}/cancel
Permission: inventory.manage
Feature: inventory.transfer_request
Body: { reason?: string }
Response 200: { message, transfer_request }
Note: If in_transit, stock returned to source
Error 422: if status in [completed, rejected, cancelled]
```

---

## 6. Stock Adjustment Reasons

### 6.1 List Reasons
```
GET /api/v1/adjustment-reasons
Permission: inventory.view
Query: ?is_active=&category=
Response 200: { data: [...] }
```

### 6.2 Create Reason
```
POST /api/v1/adjustment-reasons
Permission: inventory.manage
Body: { name: string, category: string, is_active?: bool }
Response 201: { message, reason }
```

### 6.3 Update Reason
```
PUT /api/v1/adjustment-reasons/{id}
Permission: inventory.manage
Body: { name?, is_active? }
Response 200: { message, reason }
Error 422: if is_system = true (cannot modify system reasons, except is_active)
```

### 6.4 Delete Reason
```
DELETE /api/v1/adjustment-reasons/{id}
Permission: inventory.manage
Response 200: { message }
Error 422: if is_system = true
```

---

## 7. Enhanced Inventory Endpoints

### 7.1 Adjust Stock (Enhanced)
```
POST /api/v1/inventory/adjust
Permission: inventory.manage
Body: {
  product_id: int, store_id: int, delta: int,
  reason_id?: int, batch_id?: int, note?: string
}
Response 200: { message, movement }
```

### 7.2 Transfer (Enhanced)
```
POST /api/v1/inventory/transfer
Permission: inventory.manage
Body: {
  from_store_id: int, to_store_id: int, product_id: int, quantity: int,
  batch_id?: int, note?: string
}
Response 200: { message, out_movement, in_movement }
```

### 7.3 List Inventory (Enhanced)
```
GET /api/v1/inventory
Permission: inventory.view
Query: ?store_id=&search=&low_stock=&batch_id=&expiring_within=&page=&per_page=
Response 200: { data: [...], total, ... }
Item: { id, store_id, product_id, product: {...}, quantity, minimum_quantity, maximum_quantity, batch_id, batch?: {...}, expiry_date }
```

### 7.4 Movements (Enhanced)
```
GET /api/v1/inventory/movements
Permission: inventory.view
Query: ?store_id=&product_id=&type=&batch_id=&reason_id=&from=&to=&page=&per_page=
Response 200: { data: [...], total, ... }
Item: { ..., batch_id, batch?: {...}, reason_id, reason?: {...} }
```

---

## 8. Inventory Reports

### 8.1 Stock Summary
```
GET /api/v1/inventory/reports/summary
Permission: inventory.view
Query: ?store_id=&warehouse_id=
Response 200: { data: [{ product_id, product: {...}, total_quantity, total_value, stores: [...] }] }
```

### 8.2 Stock Valuation
```
GET /api/v1/inventory/reports/valuation
Permission: inventory.view
Feature: inventory.valuation
Query: ?method=fifo|lifo|average&store_id=
Response 200: { method, data: [{ product_id, product: {...}, quantity, unit_cost, total_value }], grand_total }
```

### 8.3 Low Stock Report
```
GET /api/v1/inventory/reports/low-stock
Permission: inventory.view
Query: ?store_id=
Response 200: { data: [{ product_id, product: {...}, store_id, store: {...}, current_qty, min_qty, max_qty, status, suggested_reorder }] }
```

### 8.4 Expiry Report
```
GET /api/v1/inventory/reports/expiry
Permission: inventory.view
Feature: inventory.expiry_tracking
Query: ?days=30&store_id=
Response 200: { data: [{ product_id, product: {...}, batch_id, batch_number, expiry_date, quantity, days_until_expiry }] }
```

### 8.5 Movement History (Enhanced)
```
GET /api/v1/inventory/reports/movements
Permission: inventory.view
Query: ?store_id=&product_id=&type=&batch_id=&reason_id=&from=&to=&page=&per_page=
Response 200: { data: [...], total, ... }
```

---

## 9. Tenant Settings (Inventory)

### 9.1 Get Inventory Settings
```
GET /api/v1/inventory/settings
Permission: inventory.view
Response 200: { stock_valuation_method: "average" }
```

### 9.2 Update Inventory Settings
```
PUT /api/v1/inventory/settings
Permission: inventory.manage
Body: { stock_valuation_method: "fifo"|"lifo"|"average" }
Response 200: { message, settings }
```

---

*End of Phase 2 API*
