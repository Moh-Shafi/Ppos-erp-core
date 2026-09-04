# Phase 2 — Inventory Enhancement — Flow

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-12  
**Phase:** 2 — Inventory Enhancement  
**Depends On:** Phase 1 (Catalog & Product Enhancement — CLOSED)

---

## 1. Warehouse Management Flow

### 1.1 Create Warehouse

```
Owner/Manager → Warehouses page → "Add Warehouse" button
  → Modal: name, address, phone, is_active
  → POST /api/v1/warehouses
  → Warehouse created (tenant-scoped)
  → Table refreshes, new warehouse appears
```

### 1.2 Warehouse Stock View

```
Owner/Manager → Warehouses page → click warehouse row
  → WarehouseDetailPage
  → GET /api/v1/warehouses/{id}/stock (paginated, searchable)
  → Table: Product | SKU | Quantity | Batch (if enabled) | Expiry (if enabled)
  → "Adjust Stock" button → modal with reason dropdown
```

### 1.3 Warehouse → Store Transfer (via Transfer Request)

```
Owner/Manager → Transfer Requests page → "New Request" button
  → Modal: from = warehouse, to = store, items (product + qty)
  → POST /api/v1/transfer-requests
  → Status: draft
  → User clicks "Submit" → status: pending
  → Manager/Owner approves → status: approved
  → User marks "Start Transit" → status: in_transit (stock deducted from warehouse)
  → User marks "Complete" → status: completed (stock added to store)
```

---

## 2. Batch Tracking Flow (Feature-Flagged)

### 2.1 Receive Stock with Batch

```
Purchase receiving (existing flow, enhanced)
  → When inventory.batch_tracking enabled:
    → Receiving modal shows "Batch Number" and "Expiry Date" fields
    → POST /api/v1/inventory/adjust with batch_id
    → StockBatch record created (or existing batch selected)
    → Inventory row linked to batch
```

### 2.2 View Batches

```
Owner/Manager → Product detail page → "Batches" tab
  → GET /api/v1/products/{id}/batches
  → Table: Batch # | Quantity | Received Date | Expiry Date | Cost Price
```

### 2.3 Sell from Specific Batch (FEFO)

```
Cashier → POS → add product to cart
  → If product has batches:
    → System suggests earliest-expiry batch (FEFO)
    → Cashier can override batch selection
  → On checkout: InventoryService.decrease() with batch_id
```

---

## 3. Expiry Tracking Flow (Feature-Flagged)

### 3.1 Expiry Alerts

```
Owner/Manager → Dashboard
  → When inventory.expiry_tracking enabled:
    → "Expiring Soon" widget appears
    → Shows products expiring within 30/60/90 days
    → GET /api/v1/inventory/reports/expiry?days=30
    → Click product → navigate to product detail
```

### 3.2 FEFO Stock Decrease

```
InventoryService.decrease() with batch tracking:
  1. If batch_id specified → decrease from that batch
  2. If batch_id not specified → auto-select earliest expiry batch (FEFO)
  3. If no batches exist → decrease from non-batched inventory
```

---

## 4. Stocktake Flow

### 4.1 Create Stocktake Session

```
Owner/Manager → Stocktake page → "New Session" button
  → Modal: select store
  → POST /api/v1/stocktake
  → Session created with status: draft
  → System snapshots all current inventory quantities → system_quantity
  → Items table populated with all products for the store
```

### 4.2 Count Phase

```
User → Stocktake detail page → status: counting
  → Table: Product | System Qty | Counted Qty (input) | Variance (auto)
  → User enters counted quantities
  → PUT /api/v1/stocktake/{id}/items/{itemId} with counted_quantity
  → Variance = counted - system (auto-calculated)
  → Can filter: show only variances, show all
```

### 4.3 Reconcile Phase

```
User → click "Reconcile" → status: reconciling
  → Review all items with variance ≠ 0
  → Add notes to each variance item
  → Review adjustment reasons
  → Cannot edit counted quantities in this phase
```

### 4.4 Post Phase

```
Owner/Manager → click "Post" → status: posted
  → Confirmation dialog: "This will create N stock adjustments"
  → POST /api/v1/stocktake/{id}/post
  → For each item with variance ≠ 0:
    → InventoryService.adjust(store, product, variance, reason, note)
    → Movement created with type: adjustment, reason_id
  → Session marked as posted, completed_at set
  → Cannot undo (audit trail preserved)
```

### 4.5 Cancel

```
User → click "Cancel" (only in draft or counting status)
  → POST /api/v1/stocktake/{id}/cancel
  → Status: cancelled
  → No adjustments made
```

---

## 5. Transfer Request Flow

### 5.1 Create Transfer Request

```
Owner/Manager → Transfer Requests page → "New Request" button
  → Modal:
    → From: dropdown (stores + warehouses)
    → To: dropdown (stores + warehouses, excluding "from")
    → Items: product picker + quantity (repeatable)
    → Note (optional)
  → POST /api/v1/transfer-requests
  → Status: draft
  → Request number auto-generated (TR-0001)
```

### 5.2 Submit for Approval

```
Creator → Transfer request detail → "Submit" button
  → POST /api/v1/transfer-requests/{id}/submit
  → Status: pending
  → Validation: items must have qty > 0, source must have sufficient stock
```

### 5.3 Approval

```
Manager/Owner → Transfer request detail → "Approve" or "Reject" button
  → POST /api/v1/transfer-requests/{id}/approve
  → Validation: approver ≠ requester (segregation of duties)
  → Status: approved (or rejected)
  → approved_by, approved_at set
```

### 5.4 In Transit

```
User → Transfer request detail → "Start Transit" button
  → POST /api/v1/transfer-requests/{id}/transit
  → Status: in_transit
  → Stock deducted from source (via InventoryService or WarehouseService)
  → Movement type: transfer_out
```

### 5.5 Complete

```
User → Transfer request detail → "Complete" button
  → POST /api/v1/transfer-requests/{id}/complete
  → Status: completed
  → Stock added to destination
  → Movement type: transfer_in
  → Transfer is now fully executed
```

### 5.6 Cancel

```
Creator (draft/pending) or Approver (approved/in_transit)
  → "Cancel" button
  → POST /api/v1/transfer-requests/{id}/cancel
  → If in_transit: stock returned to source (reverse movement)
  → Status: cancelled
```

---

## 6. Stock Valuation Flow

### 6.1 View Valuation Report

```
Owner/Manager/Accountant → Inventory Reports page → "Stock Valuation" tab
  → Select valuation method (FIFO/LIFO/Average) — defaults to tenant setting
  → GET /api/v1/inventory/reports/valuation?method=fifo
  → Table: Product | Quantity | Unit Cost | Total Value
  → Summary: total stock value at bottom
```

### 6.2 Change Valuation Method

```
Owner → Settings → Inventory tab
  → Select: FIFO / LIFO / Average
  → PUT /api/v1/tenant/settings (stock_valuation_method)
  → Future reports use new method
  → Historical calculations unaffected
```

---

## 7. Stock Adjustment Flow (Enhanced)

### 7.1 Adjust with Reason

```
Owner/Manager → Inventory page → "Adjust Stock" button
  → Modal: Product (pre-selected), Store, Quantity (±), Reason (dropdown), Note
  → POST /api/v1/inventory/adjust
  → Body: { product_id, store_id, delta, reason_id, note, batch_id? }
  → InventoryService.adjust() called with reason_id
  → Movement created with reason_id linked
  → Audit log entry created
```

### 7.2 Manage Adjustment Reasons

```
Owner → Settings → Adjustment Reasons tab
  → Table: Name | Category | System? | Active
  → System reasons: cannot delete, can toggle active
  → Custom reasons: full CRUD
  → "Add Reason" button → modal: name, category, is_active
```

---

## 8. Low Stock / Reorder Flow

### 8.1 Low Stock Report

```
Owner/Manager → Inventory Reports page → "Low Stock" tab
  → GET /api/v1/inventory/reports/low-stock
  → Table: Product | Store | Current Qty | Min Qty | Status | Suggested Reorder
  → Suggested Reorder = max_qty - current_qty (if max set)
  → Filter by store, filter by status (low/out)
```

---

## 9. State Diagrams

### 9.1 Stocktake Session States

```
                    ┌─────────┐
                    │  DRAFT  │
                    └────┬────┘
                         │ start counting
                         ▼
                    ┌──────────┐
        ┌───────────│ COUNTING │───────────┐
        │ cancel    └────┬─────┘  cancel   │
        ▼                │ reconcile       ▼
   ┌─────────┐           ▼           ┌─────────┐
   │CANCELLED │    ┌────────────┐    │CANCELLED│
   └─────────┘    │ RECONCILING │    └─────────┘
                  └──────┬─────┘
                         │ post
                         ▼
                   ┌─────────┐
                   │ POSTED  │
                   └─────────┘
                   (immutable)
```

### 9.2 Transfer Request States

```
                    ┌─────────┐
                    │  DRAFT  │
                    └────┬────┘
                         │ submit
                         ▼
                   ┌──────────┐     reject     ┌──────────┐
                   │ PENDING  │──────────────►│ REJECTED │
                   └────┬─────┘                └──────────┘
                        │ approve
                        ▼
                  ┌──────────┐    cancel    ┌──────────┐
                  │ APPROVED │────────────►│CANCELLED │
                  └────┬─────┘              └──────────┘
                       │ start transit
                       ▼
                 ┌────────────┐   cancel   ┌──────────┐
                 │ IN_TRANSIT │──────────►│CANCELLED │
                 └────┬───────┘            └──────────┘
                      │ complete           (stock returned)
                      ▼
                 ┌───────────┐
                 │ COMPLETED │
                 └───────────┘
                 (immutable)
```

---

*End of Phase 2 Flow*
