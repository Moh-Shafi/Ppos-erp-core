# Phase 3 — CRM & Purchasing Enhancement — Security

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-13  
**Phase:** 3 — CRM & Purchasing Enhancement  
**Depends On:** Phase 2 (Inventory Enhancement — CLOSED)

---

## 1. RBAC

### 1.1 Permission Matrix

| Permission | Owner | Manager | Cashier | Staff | Accountant |
|------------|-------|---------|---------|-------|------------|
| `customers.view` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `customers.manage` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `crm.view` | ✅ | ✅ | ✅ | ❌ | ✅ |
| `crm.manage` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `suppliers.view` | ✅ | ✅ | ✅ | ❌ | ✅ |
| `suppliers.manage` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `purchases.view` | ✅ | ✅ | ✅ | ❌ | ✅ |
| `purchases.manage` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `purchasing.requisition` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `purchasing.grn` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `purchasing.invoice_match` | ✅ | ✅ | ❌ | ❌ | ✅ |

### 1.2 Module Requirements

| Endpoint Group | Module | Feature |
|---------------|--------|---------|
| Customer CRUD (existing) | `customers` | — |
| Loyalty | `customers` | `customers.loyalty_points` |
| Customer Credit | `customers` | `sales.customer_credit` |
| Supplier CRUD (existing) | `suppliers` | — |
| Supplier Ratings | `suppliers` | — |
| Purchase CRUD (existing) | `purchasing` | — |
| Requisitions | `purchasing` | `purchasing.requisition` |
| GRN | `purchasing` | — |
| Supplier Invoices | `purchasing` | `purchasing.invoice_matching` |
| Auto-Reorder | `purchasing` | — |

### 1.3 Frontend RBAC

- Customer detail: `ProtectedRoute module="customers" permission="customers.view"`
- Loyalty dashboard: `ProtectedRoute module="customers" permission="crm.view"` + feature check `customers.loyalty_points`
- Customer credit: `ProtectedRoute module="customers" permission="crm.view"` + feature check `sales.customer_credit`
- Supplier detail: `ProtectedRoute module="suppliers" permission="suppliers.view"`
- Requisitions: `ProtectedRoute module="purchasing" permission="purchases.view"` + feature check `purchasing.requisition`
- GRN: `ProtectedRoute module="purchasing" permission="purchases.view"`
- Supplier invoices: `ProtectedRoute module="purchasing" permission="purchases.view"` + feature check `purchasing.invoice_matching`
- Auto-reorder: `ProtectedRoute module="purchasing" permission="purchases.view"`
- Approve/Reject buttons: `purchasing.requisition` or `purchasing.invoice_match` permission
- Adjust loyalty/credit buttons: `crm.manage` permission

---

## 2. Tenant Isolation

### 2.1 Model-Level

All new models use `BelongsToTenant` trait with global scope:
- `CustomerLoyaltyPoints`
- `CustomerLoyaltyTransaction`
- `CustomerCreditTransaction`
- `SupplierRating`
- `PurchaseRequisition`
- `PurchaseRequisitionItem`
- `GoodsReceiptNote`
- `GrnItem`
- `SupplierInvoice`

### 2.2 Query-Level

- All queries auto-filtered by `tenant_id` via global scope
- `withoutTenantScope()` used only in service-level ownership validation
- `tenant_id` auto-set from `Auth::user()->tenant_id`, never from request

### 2.3 Cross-Tenant Access Tests

- Cannot access other tenant's customers, suppliers, requisitions, GRNs, invoices
- Cross-tenant product/supplier/store references in create/update rejected
- Returns 404 (not 403) to prevent information leakage

---

## 3. Segregation of Duties (SoD)

### 3.1 Requisition Approval

- **Rule:** User who created the requisition cannot approve or reject it
- **Enforcement:** `RequisitionService::approve()` and `reject()` check `requested_by != Auth::id()`
- **Test:** `cannot_approve_own_requisition`
- **Audit:** Logged with both `requested_by` and `approved_by`/`rejector` user IDs

### 3.2 Invoice Approval

- **Rule:** User who created the invoice cannot approve it
- **Enforcement:** `InvoiceMatchingService::approve()` checks `created_by != Auth::id()`
- **Exception:** Owner role can override (business owner has ultimate authority)
- **Test:** `cannot_approve_own_invoice`

### 3.3 GRN Receiving

- **Rule:** GRN can only be received by users with `purchasing.grn` permission
- **Rule:** Cannot receive a cancelled GRN
- **Rule:** Cannot modify a received GRN
- **Test:** `cannot_receive_cancelled_grn`, `cannot_modify_received_grn`

---

## 4. Feature Flag Enforcement

### 4.1 Route Middleware

```php
// Loyalty routes
Route::middleware('feature:customers.loyalty_points')->group(...)

// Credit routes
Route::middleware('feature:sales.customer_credit')->group(...)

// Requisition routes
Route::middleware('feature:purchasing.requisition')->group(...)

// Invoice matching routes
Route::middleware('feature:purchasing.invoice_matching')->group(...)
```

### 4.2 Service-Level Checks

Each service also checks feature availability before executing business logic:
```php
if (!$this->featureEnabled('customers.loyalty_points', $tenantId)) {
    throw new \DomainException('Loyalty points feature is not enabled');
}
```

### 4.3 Graceful Degradation

- When loyalty feature disabled: customer detail omits `loyalty_points` field
- When credit feature disabled: customer detail omits `credit_limit` / `outstanding_balance`
- When requisition feature disabled: purchasing page shows only existing PO workflow
- When invoice matching disabled: supplier invoices page hidden

---

## 5. Data Integrity

### 5.1 Points Integrity

- `CustomerLoyaltyPoints.points_balance` = `total_earned` - `total_redeemed` - expired
- All changes go through `LoyaltyService` within `DB::transaction`
- `CustomerLoyaltyTransaction.balance_after` always reflects post-transaction balance
- Cannot redeem more points than balance (enforced in service)

### 5.2 Credit Integrity

- `Customer.outstanding_balance` = sum of all debit - sum of all credit transactions
- All changes go through `CustomerCreditService` within `DB::transaction`
- `CustomerCreditTransaction.balance_after` always reflects post-transaction balance
- Credit limit check is atomic (lockForUpdate on customer row)

### 5.3 GRN Integrity

- `quantity_received + quantity_rejected ≤ quantity_ordered` (when PO-linked)
- `quantity_received ≥ 0` and `quantity_rejected ≥ 0`
- Receiving triggers `InventoryService::increase` within same transaction
- Cannot receive twice (status check: only 'draft' can be received)

### 5.4 3-Way Match Integrity

- Match compares PO items, GRN items, and invoice line items
- Tolerance applied as percentage (configurable per tenant)
- Match result stored as JSON with detailed breakdown
- Once approved, invoice cannot be re-matched

---

## 6. Audit Logging

### 6.1 Logged Actions

| Service | Action | Entity Type |
|---------|--------|-------------|
| LoyaltyService | `loyalty.points_earned` | `customer_loyalty` |
| LoyaltyService | `loyalty.points_redeemed` | `customer_loyalty` |
| LoyaltyService | `loyalty.points_expired` | `customer_loyalty` |
| LoyaltyService | `loyalty.points_adjusted` | `customer_loyalty` |
| CustomerCreditService | `credit.debit_added` | `customer_credit` |
| CustomerCreditService | `credit.payment_received` | `customer_credit` |
| CustomerCreditService | `credit.adjusted` | `customer_credit` |
| SupplierRatingService | `supplier_rating.created` | `supplier_rating` |
| SupplierRatingService | `supplier_rating.updated` | `supplier_rating` |
| SupplierRatingService | `supplier_rating.deleted` | `supplier_rating` |
| RequisitionService | `requisition.created` | `purchase_requisition` |
| RequisitionService | `requisition.submitted` | `purchase_requisition` |
| RequisitionService | `requisition.approved` | `purchase_requisition` |
| RequisitionService | `requisition.rejected` | `purchase_requisition` |
| RequisitionService | `requisition.cancelled` | `purchase_requisition` |
| RequisitionService | `requisition.converted_to_po` | `purchase_requisition` |
| GrnService | `grn.created` | `goods_receipt_note` |
| GrnService | `grn.received` | `goods_receipt_note` |
| GrnService | `grn.cancelled` | `goods_receipt_note` |
| InvoiceMatchingService | `invoice.created` | `supplier_invoice` |
| InvoiceMatchingService | `invoice.matched` | `supplier_invoice` |
| InvoiceMatchingService | `invoice.mismatched` | `supplier_invoice` |
| InvoiceMatchingService | `invoice.approved` | `supplier_invoice` |
| InvoiceMatchingService | `invoice.rejected` | `supplier_invoice` |

### 6.2 Audit Log Content

All logs include:
- `tenant_id` — from Auth user
- `user_id` — from Auth user
- `action` — as listed above
- `entity_type` — as listed above
- `entity_id` — the affected record ID
- `old_values` — previous state (for updates)
- `new_values` — new state
- `ip_address` — from Request
- `user_agent` — from Request

---

## 7. Stock Manipulation Prevention

### 7.1 GRN-Only Stock Increase

- Stock increases only through `InventoryService::increase()` called by `GrnService::receive()`
- The existing `PurchaseService::receive()` remains for backward compatibility but is deprecated
- No direct stock manipulation endpoints — all go through service layer with transactions

### 7.2 Rejection Tracking

- GRN items record `quantity_rejected` with `rejection_reason`
- Rejected quantities do NOT increase stock
- Rejection reasons auditable and reportable

---

*End of Phase 3 Security*
