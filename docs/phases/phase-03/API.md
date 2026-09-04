# Phase 3 — CRM & Purchasing Enhancement — API

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-13  
**Phase:** 3 — CRM & Purchasing Enhancement  
**Depends On:** Phase 2 (Inventory Enhancement — CLOSED)

---

## 1. Customer Endpoints (Enhanced)

### 1.1 Existing Endpoints (unchanged)
```
GET    /api/v1/customers              — List (paginated, search)
GET    /api/v1/customers/{id}         — Show
POST   /api/v1/customers              — Create
PUT    /api/v1/customers/{id}         — Update
DELETE /api/v1/customers/{id}         — Delete
```

### 1.2 Enhanced Create/Update (new fields)
```json
// POST /api/v1/customers — request body additions
{
  "credit_limit": 5000000,        // decimal, nullable
  "price_list_id": 3,             // integer, nullable, exists:price_lists,id
}

// PUT /api/v1/customers/{id} — same additions (all optional)
```

### 1.3 Customer Detail (enhanced response)
```json
// GET /api/v1/customers/{id} — response additions
{
  "id": 1,
  "name": "John Doe",
  "credit_limit": 5000000,
  "outstanding_balance": 750000,
  "price_list_id": 3,
  "loyalty_points": {
    "points_balance": 150,
    "total_earned": 500,
    "total_redeemed": 350
  }
}
```

---

## 2. Loyalty Endpoints

**Module:** `customers`  
**Feature:** `customers.loyalty_points`  
**Permission:** `customers.view` (view), `customers.manage` (adjust)

```
GET    /api/v1/customers/{id}/loyalty          — Get points balance
GET    /api/v1/customers/{id}/loyalty/transactions — List transactions (paginated)
POST   /api/v1/customers/{id}/loyalty/adjust    — Manual adjust (manager/owner)
```

### POST /api/v1/customers/{id}/loyalty/adjust
```json
// Request
{
  "points": 50,           // positive = add, negative = deduct
  "note": "Birthday bonus"
}

// Response 200
{
  "customer_id": 1,
  "points_balance": 200,
  "transaction": {
    "id": 15,
    "points": 50,
    "type": "adjust",
    "source": "manual",
    "balance_after": 200,
    "note": "Birthday bonus"
  }
}
```

---

## 3. Customer Credit Endpoints

**Module:** `customers`  
**Feature:** `sales.customer_credit`  
**Permission:** `customers.view` (view), `customers.manage` (adjust)

```
GET    /api/v1/customers/{id}/credit             — Get credit balance
GET    /api/v1/customers/{id}/credit/transactions — List credit transactions (paginated)
POST   /api/v1/customers/{id}/credit/adjust       — Manual adjust (manager/owner)
POST   /api/v1/customers/{id}/credit/check         — Check if sale amount is within limit
```

### POST /api/v1/customers/{id}/credit/adjust
```json
// Request
{
  "amount": -500000,      // negative = reduce debt, positive = increase debt
  "note": "Payment received offline"
}

// Response 200
{
  "customer_id": 1,
  "outstanding_balance": 250000,
  "transaction": {
    "id": 8,
    "amount": -500000,
    "type": "credit",
    "source": "manual",
    "balance_after": 250000
  }
}
```

### POST /api/v1/customers/{id}/credit/check
```json
// Request
{
  "amount": 750000
}

// Response 200
{
  "allowed": true,
  "outstanding_balance": 750000,
  "credit_limit": 5000000,
  "remaining": 4250000
}
```

---

## 4. Supplier Endpoints (Enhanced)

### 4.1 Existing Endpoints (unchanged)
```
GET    /api/v1/suppliers              — List
GET    /api/v1/suppliers/{id}         — Show
POST   /api/v1/suppliers              — Create
PUT    /api/v1/suppliers/{id}         — Update
DELETE /api/v1/suppliers/{id}         — Delete
```

### 4.2 Supplier Detail (enhanced response)
```json
// GET /api/v1/suppliers/{id} — response additions
{
  "id": 1,
  "name": "PT Supplier Jaya",
  "average_rating": 4.2,
  "rating_count": 15
}
```

---

## 5. Supplier Rating Endpoints

**Module:** `suppliers`  
**Permission:** `suppliers.view` (view), `suppliers.manage` (create/update/delete)

```
GET    /api/v1/suppliers/{id}/ratings       — List ratings for supplier
POST   /api/v1/suppliers/{id}/ratings       — Create rating
PUT    /api/v1/suppliers/{id}/ratings/{rid} — Update rating
DELETE /api/v1/suppliers/{id}/ratings/{rid} — Delete rating
```

### POST /api/v1/suppliers/{id}/ratings
```json
// Request
{
  "rating": 4,
  "criteria": "quality",
  "note": "Good product quality consistently"
}

// Response 201
{
  "id": 10,
  "supplier_id": 1,
  "rating": 4,
  "criteria": "quality",
  "note": "Good product quality consistently",
  "rated_by": 2,
  "created_at": "2026-08-13T10:00:00Z"
}
```

---

## 6. Purchase Requisition Endpoints

**Module:** `purchasing`  
**Feature:** `purchasing.requisition`  
**Permission:** `purchases.view` (view), `purchases.manage` (create/manage), `purchasing.requisition` (approve/reject)

```
GET    /api/v1/requisitions                 — List (paginated, filter by status/store)
GET    /api/v1/requisitions/{id}            — Show with items
POST   /api/v1/requisitions                 — Create (with items)
PUT    /api/v1/requisitions/{id}            — Update draft (with items)
DELETE /api/v1/requisitions/{id}            — Delete (draft only)
POST   /api/v1/requisitions/{id}/submit     — Submit for approval (draft → pending)
POST   /api/v1/requisitions/{id}/approve    — Approve (pending → approved)
POST   /api/v1/requisitions/{id}/reject     — Reject (pending → rejected)
POST   /api/v1/requisitions/{id}/cancel     — Cancel (draft/pending → cancelled)
POST   /api/v1/requisitions/{id}/convert    — Convert to PO (approved → PO created)
```

### POST /api/v1/requisitions
```json
// Request
{
  "store_id": 1,
  "note": "Monthly restock",
  "items": [
    {
      "product_id": 5,
      "quantity": 100,
      "estimated_cost": 5000,
      "note": "Regular order"
    }
  ]
}

// Response 201
{
  "id": 1,
  "request_number": "PR-20260813-0001",
  "status": "draft",
  "store_id": 1,
  "requested_by": 2,
  "items": [...]
}
```

### POST /api/v1/requisitions/{id}/convert
```json
// Request
{
  "supplier_id": 1,
  "items": [
    {
      "product_id": 5,
      "quantity": 100,
      "unit_cost": 5000
    }
  ]
}

// Response 201
{
  "purchase": {
    "id": 15,
    "purchase_number": "PO-20260813-0003",
    "status": "draft",
    "requisition_id": 1
  }
}
```

---

## 7. Goods Receipt Note (GRN) Endpoints

**Module:** `purchasing`  
**Permission:** `purchases.view` (view), `purchasing.grn` (create/receive/cancel)

```
GET    /api/v1/grns                  — List (paginated, filter by status/supplier/store)
GET    /api/v1/grns/{id}             — Show with items
POST   /api/v1/grns                  — Create standalone GRN
POST   /api/v1/grns/from-po/{poId}   — Create GRN from Purchase Order
POST   /api/v1/grns/{id}/receive     — Receive GRN (triggers inventory increase)
POST   /api/v1/grns/{id}/cancel      — Cancel (draft only)
```

### POST /api/v1/grns
```json
// Request (standalone)
{
  "store_id": 1,
  "supplier_id": 1,
  "note": "Direct delivery",
  "items": [
    {
      "product_id": 5,
      "quantity_ordered": 0,
      "quantity_received": 50,
      "unit_cost": 5000,
      "batch_id": null,
      "expiry_date": null
    }
  ]
}

// Response 201
{
  "id": 1,
  "grn_number": "GRN-20260813-0001",
  "status": "draft",
  "items": [...]
}
```

### POST /api/v1/grns/{id}/receive
```json
// Request
{
  "items": [
    {
      "id": 1,                    // grn_item id
      "quantity_received": 48,    // actual received
      "quantity_rejected": 2,     // rejected
      "rejection_reason": "Damaged in transit",
      "batch_id": null,
      "expiry_date": null
    }
  ]
}

// Response 200
{
  "id": 1,
  "status": "received",
  "received_date": "2026-08-13",
  "items": [...]
}
```

---

## 8. Supplier Invoice Endpoints

**Module:** `purchasing`  
**Feature:** `purchasing.invoice_matching`  
**Permission:** `purchases.view` (view), `purchasing.invoice_match` (match/approve/reject)

```
GET    /api/v1/supplier-invoices              — List (paginated, filter by status/supplier)
GET    /api/v1/supplier-invoices/{id}         — Show with match details
POST   /api/v1/supplier-invoices              — Create
POST   /api/v1/supplier-invoices/{id}/match   — Run 3-way match
POST   /api/v1/supplier-invoices/{id}/approve — Approve
POST   /api/v1/supplier-invoices/{id}/reject  — Reject
```

### POST /api/v1/supplier-invoices
```json
// Request
{
  "invoice_number": "INV-2026-001",
  "supplier_id": 1,
  "purchase_id": 15,         // nullable
  "grn_id": 1,               // nullable
  "subtotal": 250000,
  "tax": 25000,
  "total": 275000,
  "invoice_date": "2026-08-13",
  "due_date": "2026-09-13"
}

// Response 201
{
  "id": 1,
  "status": "pending",
  "match_result": null
}
```

### POST /api/v1/supplier-invoices/{id}/match
```json
// Response 200 (matched)
{
  "id": 1,
  "status": "matched",
  "match_result": {
    "quantity_match": true,
    "price_match": true,
    "total_match": true,
    "details": {
      "po_total": 275000,
      "grn_total": 275000,
      "invoice_total": 275000,
      "tolerance_pct": 5,
      "variance_pct": 0
    }
  }
}
```

---

## 9. Auto-Reorder Endpoints

**Module:** `purchasing`  
**Permission:** `purchases.view` (view), `purchases.manage` (generate)

```
GET    /api/v1/auto-reorder/report            — Get low-stock report
POST   /api/v1/auto-reorder/generate           — Generate requisition from report
```

### GET /api/v1/auto-reorder/report?store_id=1
```json
// Response 200
{
  "data": [
    {
      "product_id": 5,
      "product_name": "Coffee Beans 1kg",
      "current_stock": 5,
      "minimum_quantity": 20,
      "maximum_quantity": 100,
      "suggested_qty": 95,
      "estimated_cost": 475000,
      "supplier_id": 1
    }
  ],
  "store_id": 1,
  "count": 1
}
```

### POST /api/v1/auto-reorder/generate
```json
// Request
{
  "store_id": 1,
  "product_ids": [5, 10, 15]
}

// Response 201
{
  "requisition": {
    "id": 5,
    "request_number": "PR-20260813-0002",
    "status": "draft",
    "items": [...]
  }
}
```

---

## 10. Route Registration Summary

All new routes are under `/api/v1` with `auth:sanctum` + `throttle:api` middleware.

Feature flag middleware applied:
- `feature:customers.loyalty_points` — loyalty endpoints
- `feature:sales.customer_credit` — credit endpoints
- `feature:purchasing.requisition` — requisition endpoints
- `feature:purchasing.invoice_matching` — supplier invoice endpoints

Permission middleware applied per endpoint as specified in each section above.

---

*End of Phase 3 API*
