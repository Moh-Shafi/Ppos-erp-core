# Phase 3 — CRM & Purchasing Enhancement — PDR

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-13  
**Phase:** 3 — CRM & Purchasing Enhancement  
**Depends On:** Phase 2 (Inventory Enhancement — CLOSED)

---

## 1. Objective

Upgrade the customer, supplier, and purchasing systems from basic POS-level CRUD to ERP-grade operations with loyalty programs, credit management, purchase requisitions, goods receipt notes (GRN), 3-way matching, and auto-reorder capabilities.

All features are **business-type agnostic** — they work for any tenant regardless of business type. Business types only control which modules/features are enabled by default.

---

## 2. Deliverables

### 2.1 Customer Loyalty Points (Feature-Flagged: `customers.loyalty_points`)
- `customer_loyalty_points` table: customer, points_balance, total_earned, total_redeemed
- `customer_loyalty_transactions` table: customer, points, type (earn/redeem/expire), source (sale/manual), reference
- Configurable earn rate per tenant (e.g., 1 point per Rp 10,000)
- Configurable redemption value (e.g., 1 point = Rp 1,000)
- Points accrue automatically on completed sales
- Points redeemable as discount at POS
- Expiry policy: configurable (no expiry, 6 months, 12 months)
- Feature-flagged: enabled for retail, grocery, pharmacy by default

### 2.2 Customer Credit Limits (Feature-Flagged: `sales.customer_credit`)
- `customers` table gains `credit_limit` (decimal, nullable), `outstanding_balance` (decimal, default 0)
- `customer_credit_transactions` table: customer, amount, type (debit/credit), source (sale/payment/manual), reference, balance_after
- Credit limit enforcement at POS checkout — block sale if exceeds limit
- Outstanding balance tracks unpaid sales on credit
- Manual credit adjustment with reason
- Credit history report per customer
- Feature-flagged: enabled for wholesale, service by default

### 2.3 Customer Price List Assignment
- `customers` table gains `price_list_id` (nullable FK)
- When customer assigned to price list, POS uses customer-specific pricing
- Falls back to default product price if no price list or item not in list
- No feature flag — always available (uses existing price_lists table from Phase 1)

### 2.4 Supplier Rating & Evaluation
- `supplier_ratings` table: supplier, rating (1-5), criteria (quality, delivery, pricing, service), note, rated_by
- Average rating displayed on supplier list and detail
- Rating history per supplier
- No feature flag — always available

### 2.5 Purchase Requisition (Feature-Flagged: `purchasing.requisition`)
- `purchase_requisitions` table: request_number, store, status (draft, pending, approved, rejected, cancelled), requested_by, approved_by, notes
- `purchase_requisition_items` table: requisition, product, quantity, estimated_cost, note
- Workflow: draft → pending → approved/rejected → (convert to PO)
- Approval: Manager/Owner only (SoD — cannot approve own requisition)
- Convert approved requisition to Purchase Order (one-to-one or multiple POs from one requisition)
- Feature-flagged: enabled for all business types by default

### 2.6 Goods Receipt Note (GRN) — Separate from PO
- `goods_receipt_notes` table: grn_number, purchase_id (nullable FK), store, supplier_id, status (draft, received, cancelled), received_by, received_date, notes
- `grn_items` table: grn, product, quantity_ordered, quantity_received, quantity_rejected, unit_cost, batch_id (nullable), expiry_date (nullable), note
- GRN can be linked to a PO or standalone (direct supplier delivery)
- Partial receipts supported (quantity_received ≤ quantity_ordered)
- Receiving triggers InventoryService.increase for received quantities
- Rejection reasons recorded
- Batch/expiry info captured at GRN level (when batch tracking enabled)

### 2.7 Supplier Invoice Matching (3-Way Match: PO → GRN → Invoice)
- `supplier_invoices` table: invoice_number, supplier, grn_id (nullable FK), purchase_id (nullable FK), status (pending, matched, mismatched, approved, rejected), subtotal, tax, total, invoice_date, due_date
- 3-way match logic: PO quantities vs GRN received vs Invoice quantities
- Tolerance configurable per tenant (default 5%)
- Match results: matched / quantity_mismatch / price_mismatch / total_mismatch
- Approval workflow: pending → matched → approved/rejected
- Feature-flagged: `purchasing.invoice_matching`

### 2.8 Auto-Reorder
- Based on minimum stock levels (from Phase 2 `inventories.minimum_quantity`)
- Auto-reorder report: products at or below minimum stock
- Suggested reorder quantity = maximum_quantity - current_stock (or 2x minimum if no maximum)
- Manual trigger to generate purchase requisition from auto-reorder report
- No feature flag — always available (uses existing inventory data)

---

## 3. Database Changes

### New Tables

| Table | Purpose |
|------|---------|
| `customer_loyalty_points` | Per-customer loyalty points balance |
| `customer_loyalty_transactions` | Points earn/redeem/expire transaction log |
| `customer_credit_transactions` | Credit debit/credit transaction log |
| `supplier_ratings` | Supplier evaluation ratings |
| `purchase_requisitions` | Requisition headers with approval workflow |
| `purchase_requisition_items` | Requisition line items |
| `goods_receipt_notes` | GRN headers |
| `grn_items` | GRN line items with received/rejected quantities |
| `supplier_invoices` | Supplier invoices for 3-way matching |

### Modified Tables

| Table | Changes |
|------|---------|
| `customers` | Add `credit_limit` (decimal nullable), `outstanding_balance` (decimal default 0), `price_list_id` (nullable FK) |
| `purchases` | Add `requisition_id` (nullable FK), `grn_id` (nullable FK) |

### Tenant Settings (via `tenants.settings` JSON)

| Setting | Type | Default | Notes |
|---------|------|---------|-------|
| `loyalty_earn_rate` | decimal | 10000 | Spend amount to earn 1 point |
| `loyalty_redeem_value` | decimal | 1000 | Rupiah value of 1 point |
| `loyalty_expiry_months` | int|null | null | null = no expiry |
| `credit_tolerance` | decimal | 0 | Allow overspending by this amount |
| `invoice_match_tolerance` | decimal | 5 | Percentage tolerance for 3-way match |

---

## 4. Feature Flags

| Feature Slug | Module | Default Enabled | Description |
|-------------|--------|-----------------|-------------|
| `customers.loyalty_points` | customers | false (retail, grocery, pharmacy: true) | Enable loyalty points system |
| `sales.customer_credit` | sales | false (wholesale, service: true) | Enable customer credit limits |
| `purchasing.requisition` | purchasing | true | Enable purchase requisition workflow |
| `purchasing.invoice_matching` | purchasing | false | Enable 3-way matching |

### New Permissions

| Permission | Owner | Manager | Cashier | Staff | Accountant |
|------------|-------|---------|---------|-------|------------|
| `crm.view` | ✅ | ✅ | ✅ | ❌ | ✅ |
| `crm.manage` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `purchasing.requisition` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `purchasing.grn` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `purchasing.invoice_match` | ✅ | ✅ | ❌ | ❌ | ✅ |

---

## 5. Acceptance Criteria

- [ ] Customer loyalty points accrual on sale + redemption at POS
- [ ] Customer credit limit enforcement at POS checkout
- [ ] Customer price list assignment affects POS pricing
- [ ] Supplier rating CRUD + average rating display
- [ ] Purchase requisition workflow (draft → pending → approved → convert to PO)
- [ ] GRN separate from PO (standalone + PO-linked)
- [ ] Partial receipts supported via GRN
- [ ] 3-way matching (PO vs GRN vs Invoice) with tolerance
- [ ] Auto-reorder report generates requisition suggestions
- [ ] All existing customer/supplier/purchase tests pass (regression)
- [ ] New tests for loyalty, credit, requisitions, GRN, 3-way match, auto-reorder
- [ ] Feature flags correctly gate functionality
- [ ] Frontend pages for all new features
- [ ] E2E tests for key workflows

---

## 6. Constraints

1. **Additive only** — no existing columns dropped, no existing tables renamed
2. **InventoryService preserved** — all stock increases go through `InventoryService` with `lockForUpdate`
3. **Tenant isolation** — all new models use `BelongsToTenant`
4. **tenant_id never from request** — auto-set from `Auth::user()->tenant_id`
5. **Feature-flagged** — loyalty/credit/requisition/invoice_matching gracefully degrade when disabled
6. **Business-type agnostic** — CRM & purchasing work for all business types
7. **Backward compatible** — existing purchase workflow (draft → ordered → received) remains functional
8. **GRN is the new receiving path** — but existing `PurchaseController.receive` remains for backward compatibility
9. **SoD on requisition approval** — cannot approve own requisition

---

## 7. Dependencies

- Phase 0 (ERP Architecture) — module/feature system, RBAC, audit logging
- Phase 1 (Catalog & Product Enhancement) — price lists for customer assignment
- Phase 2 (Inventory Enhancement) — minimum stock levels for auto-reorder, batch/expiry for GRN

---

*End of Phase 3 PDR*
