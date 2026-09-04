# Phase 2 — Inventory Enhancement — Security

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-12  
**Phase:** 2 — Inventory Enhancement  
**Depends On:** Phase 1 (Catalog & Product Enhancement — CLOSED)

---

## 1. RBAC

### 1.1 Permission Matrix

| Permission | Owner | Manager | Cashier | Staff | Accountant |
|------------|-------|---------|---------|-------|------------|
| `inventory.view` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `inventory.manage` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `inventory.stocktake` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `inventory.valuation` | ✅ | ✅ | ❌ | ❌ | ✅ |
| `warehouse.view` | ✅ | ✅ | ✅ | ❌ | ✅ |
| `warehouse.manage` | ✅ | ✅ | ❌ | ❌ | ❌ |

### 1.2 Module Requirements

| Endpoint Group | Module | Feature |
|---------------|--------|---------|
| Inventory (existing + enhanced) | `inventory` | — |
| Warehouses | `warehouse` | — |
| Stock Batches | `inventory` | `inventory.batch_tracking` |
| Expiry Reports | `inventory` | `inventory.expiry_tracking` |
| Stocktake | `inventory` | `inventory.stocktake` |
| Transfer Requests | `inventory` | `inventory.transfer_request` |
| Valuation Reports | `inventory` | `inventory.valuation` |

### 1.3 Frontend RBAC

- Inventory page: `ProtectedRoute module="inventory" permission="inventory.view"`
- Warehouses page: `ProtectedRoute module="warehouse" permission="warehouse.view"`
- Stocktake page: `ProtectedRoute module="inventory" permission="inventory.stocktake"` + feature check
- Transfer Requests page: `ProtectedRoute module="inventory" permission="inventory.view"` + feature check
- Inventory Reports: `ProtectedRoute module="inventory" permission="inventory.view"`
- Valuation tab: visible only when `inventory.valuation` feature enabled + `inventory.valuation` permission
- Expiry tab: visible only when `inventory.expiry_tracking` feature enabled
- Batch fields: visible only when `inventory.batch_tracking` feature enabled
- Adjust/Delete buttons: `inventory.manage` or `warehouse.manage` permission

---

## 2. Tenant Isolation

### 2.1 Existing Pattern (Preserved)

All new models use `BelongsToTenant` trait with global scope:
- `Warehouse`, `WarehouseStock`, `StockBatch`, `StockAdjustmentReason`
- `StocktakeSession`, `StocktakeItem`, `TransferRequest`, `TransferRequestItem`

### 2.2 Tenant ID Never From Request

- `tenant_id` never in `$fillable`
- Auto-set from `Auth::user()->tenant_id`
- Service layer always passes `$tenantId` explicitly

### 2.3 Cross-Tenant Protection

| Scenario | Protection |
|----------|-----------|
| Access warehouse from other tenant | `BelongsToTenant` global scope → 404 |
| Access stocktake from other tenant | `BelongsToTenant` global scope → 404 |
| Access transfer request from other tenant | `BelongsToTenant` global scope → 404 |
| Create transfer to store of other tenant | Service validates `from` and `to` belong to tenant |
| Adjust stock for other tenant's product | `InventoryService.validateOwnership()` already checks |

---

## 3. Input Validation

### 3.1 Warehouses

| Field | Rule | Notes |
|-------|------|-------|
| `name` | required, string, max 255 | — |
| `address` | nullable, string, max 500 | — |
| `phone` | nullable, string, max 50 | — |
| `is_active` | boolean | default true |

### 3.2 Stock Batches

| Field | Rule | Notes |
|-------|------|-------|
| `batch_number` | required, string, max 100, unique per tenant+product | — |
| `quantity` | required, integer, min 0 | — |
| `received_date` | required, date | — |
| `expiry_date` | nullable, date, after:received_date | — |
| `cost_price` | nullable, numeric, min 0 | decimal(15,2) |

### 3.3 Stocktake

| Field | Rule | Notes |
|-------|------|-------|
| `store_id` | required, exists in tenant | — |
| `counted_quantity` | required, integer, min 0 | — |

### 3.4 Transfer Requests

| Field | Rule | Notes |
|-------|------|-------|
| `from_store_id` / `from_warehouse_id` | one required, exists in tenant | exactly one |
| `to_store_id` / `to_warehouse_id` | one required, exists in tenant, different from source | exactly one |
| `items` | required, array, min 1 | — |
| `items.*.product_id` | required, exists in tenant | — |
| `items.*.quantity` | required, integer, min 1 | — |
| `items.*.batch_id` | nullable, exists in tenant | when batch tracking enabled |

### 3.5 Stock Adjustment

| Field | Rule | Notes |
|-------|------|-------|
| `product_id` | required, exists in tenant | — |
| `store_id` | required, exists in tenant | — |
| `delta` | required, integer, not 0 | positive or negative |
| `reason_id` | nullable, exists in tenant | — |
| `batch_id` | nullable, exists in tenant | when batch tracking enabled |
| `note` | nullable, string, max 1000 | — |

---

## 4. Business Logic Security

### 4.1 Transfer Request Approval — Segregation of Duties

- The user who created the transfer request **cannot approve it**
- Only Owner or Manager roles can approve
- Enforced in `TransferRequestService::approve()`: check `requested_by !== Auth::id()`
- Test: `test_approver_cannot_be_requester`

### 4.2 Stocktake Immutable After Posting

- Once posted, stocktake session cannot be modified or deleted
- Stocktake items cannot be edited after posting
- Adjustments created during posting are permanent (but audited)
- Test: `test_cannot_modify_posted_stocktake`

### 4.3 Transfer Request State Machine

- Status transitions are strictly enforced
- Cannot skip states (e.g., draft → approved without pending)
- Cannot reverse completed/rejected/cancelled
- Test: `test_invalid_status_transition_rejected`

### 4.4 Warehouse Deletion Safety

- Cannot delete warehouse with existing stock
- Cannot delete warehouse with pending/in_transit transfer requests
- Test: `test_cannot_delete_warehouse_with_stock`

### 4.5 System Adjustment Reasons Protected

- System reasons (`is_system = true`) cannot be deleted
- Only `is_active` can be toggled on system reasons
- Name and category of system reasons cannot be changed
- Test: `test_cannot_delete_system_reason`

---

## 5. Audit Logging

All Phase 2 mutations are logged via `AuditService`:

| Action | Entity Type |
|--------|-------------|
| `warehouse.created` | Warehouse |
| `warehouse.updated` | Warehouse |
| `warehouse.deleted` | Warehouse |
| `stock_batch.created` | StockBatch |
| `stocktake.created` | StocktakeSession |
| `stocktake.posted` | StocktakeSession |
| `stocktake.cancelled` | StocktakeSession |
| `transfer_request.created` | TransferRequest |
| `transfer_request.submitted` | TransferRequest |
| `transfer_request.approved` | TransferRequest |
| `transfer_request.rejected` | TransferRequest |
| `transfer_request.completed` | TransferRequest |
| `transfer_request.cancelled` | TransferRequest |
| `adjustment_reason.created` | StockAdjustmentReason |
| `adjustment_reason.updated` | StockAdjustmentReason |

---

## 6. Known Risks and Mitigations

| Risk | Severity | Mitigation |
|------|----------|------------|
| **IDOR on stocktake** — access other tenant's stocktake by ID | High | `BelongsToTenant` global scope. Service queries with `where('tenant_id', $tenantId)`. |
| **IDOR on transfer request** — approve transfer for other tenant | High | `BelongsToTenant` global scope. Service validates from/to entities belong to tenant. |
| **Race condition on stocktake posting** — concurrent post attempts | Medium | `lockForUpdate` on stocktake session row during posting. Status check inside transaction. |
| **Transfer request insufficient stock at transit time** — stock was depleted between approval and transit | Medium | `InventoryService.transfer()` checks sufficient stock. If insufficient, return 422 with clear message. |
| **Batch assignment to wrong product** — user assigns batch_id from product A to product B | Medium | Validate `batch_id` belongs to same `product_id` in service layer. |
| **Negative stock via adjustment** — large negative adjustment creates negative inventory | Low | `InventoryService.adjust()` already prevents negative `after_quantity`. |
| **FEFO bypass** — user manually selects non-FEFO batch | Low | FEFO is a suggestion, not enforcement. User override is intentional. Logged in movement. |
| **Valuation manipulation** — changing cost_price retroactively | Medium | Valuation uses movement timestamps. Cost price is snapshot at movement time, not current product cost. |

---

## 7. Security Test Cases

| Test | Description |
|------|-------------|
| Tenant A cannot see Tenant B's warehouses | GET /warehouses returns only tenant A's |
| Tenant A cannot access Tenant B's warehouse by ID | GET /warehouses/{id} returns 404 |
| Staff cannot create warehouses | POST /warehouses returns 403 for Staff |
| Cashier cannot manage warehouses | POST/PUT/DELETE /warehouses returns 403 |
| Tenant A cannot access Tenant B's stocktake | GET /stocktake/{id} returns 404 |
| Staff cannot create stocktake | POST /stocktake returns 403 |
| Approver cannot be requester on transfer request | POST /transfer-requests/{id}/approve returns 422 |
| Cannot approve non-pending transfer request | POST /transfer-requests/{id}/approve returns 422 if status != pending |
| Cannot post non-reconciling stocktake | POST /stocktake/{id}/post returns 422 if status != reconciling |
| Cannot delete warehouse with stock | DELETE /warehouses/{id} returns 422 |
| Cannot delete system adjustment reason | DELETE /adjustment-reasons/{id} returns 422 for is_system |
| Batch endpoints return 403 when feature disabled | Without inventory.batch_tracking feature |
| Stocktake endpoints return 403 when feature disabled | Without inventory.stocktake feature |
| Transfer request cross-tenant rejected | from/to entities validated against tenant |
| Transfer request to same source rejected | from and to cannot be same |
| Insufficient stock at transit returns 422 | POST /transfer-requests/{id}/transit with insufficient stock |

---

*End of Phase 2 Security*
