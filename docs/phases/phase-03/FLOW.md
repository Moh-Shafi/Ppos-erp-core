# Phase 3 — CRM & Purchasing Enhancement — Flow

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-13  
**Phase:** 3 — CRM & Purchasing Enhancement  
**Depends On:** Phase 2 (Inventory Enhancement — CLOSED)

---

## 1. Customer Loyalty Points Flow

### 1.1 Points Accrual (Sale Completion)

```
Sale completed (paid)
  → LoyaltyService::earnPoints(customer, saleTotal)
    → Read tenant settings: loyalty_earn_rate (e.g., 10000)
    → Calculate: points = floor(saleTotal / earn_rate)
    → If points > 0:
      → DB::transaction:
        → CustomerLoyaltyPoints::updateOrCreate (increment points_balance, total_earned)
        → CustomerLoyaltyTransaction::create (type=earn, source=sale, reference=sale)
        → AuditService::log('loyalty.points_earned', ...)
    → Return points earned
```

### 1.2 Points Redemption (POS Checkout)

```
User selects "redeem points" at checkout
  → LoyaltyService::redeemPoints(customer, pointsToRedeem)
    → Check customer has sufficient balance
    → Read tenant settings: loyalty_redeem_value (e.g., 1000)
    → Calculate: discount = pointsToRedeem * redeem_value
    → DB::transaction:
      → CustomerLoyaltyPoints::decrement points_balance, increment total_redeemed
      → CustomerLoyaltyTransaction::create (type=redeem, source=sale)
      → AuditService::log('loyalty.points_redeemed', ...)
    → Return discount amount
```

### 1.3 Points Expiry Sweep

```
Scheduled job (daily) or manual trigger
  → LoyaltyService::processExpiry()
    → Read tenant settings: loyalty_expiry_months
    → If null → skip (no expiry)
    → Find transactions older than expiry_months where type=earn and not yet expired
    → For each expired earn transaction:
      → DB::transaction:
        → CustomerLoyaltyPoints::decrement points_balance (if still has points)
        → CustomerLoyaltyTransaction::create (type=expire, source=expiry_sweep)
        → AuditService::log('loyalty.points_expired', ...)
```

### 1.4 Manual Points Adjustment

```
Manager/Owner adjusts points manually
  → LoyaltyService::adjustPoints(customer, delta, note)
    → DB::transaction:
      → CustomerLoyaltyPoints::update balance
      → CustomerLoyaltyTransaction::create (type=adjust, source=manual)
      → AuditService::log('loyalty.points_adjusted', ...)
```

---

## 2. Customer Credit Flow

### 2.1 Credit Sale at POS

```
Customer selects "pay on credit"
  → CustomerCreditService::checkLimit(customer, saleTotal)
    → If credit_limit is null → allow (no limit set)
    → If outstanding_balance + saleTotal > credit_limit + tolerance → block
    → Return allowed/blocked
  → If allowed:
    → Sale completed
    → CustomerCreditService::addDebit(customer, saleTotal, sale)
      → DB::transaction:
        → Customer::increment outstanding_balance
        → CustomerCreditTransaction::create (type=debit, source=sale)
        → AuditService::log('credit.debit_added', ...)
```

### 2.2 Credit Payment

```
Customer pays outstanding balance
  → CustomerCreditService::addCredit(customer, paymentAmount, payment)
    → DB::transaction:
      → Customer::decrement outstanding_balance
      → CustomerCreditTransaction::create (type=credit, source=payment)
      → AuditService::log('credit.payment_received', ...)
```

### 2.3 Manual Credit Adjustment

```
Manager/Owner adjusts balance manually
  → CustomerCreditService::adjust(customer, amount, note)
    → DB::transaction:
      → Customer::update outstanding_balance
      → CustomerCreditTransaction::create (type=adjust, source=manual)
      → AuditService::log('credit.adjusted', ...)
```

---

## 3. Purchase Requisition Flow

### 3.1 Full Workflow

```
┌──────┐    ┌─────────┐    ┌──────────┐    ┌──────────┐    ┌──────────────┐
│ DRAFT │───>│ PENDING  │───>│ APPROVED │───>│ CONVERT  │───>│ PURCHASE     │
│      │    │          │    │          │    │ TO PO    │    │ ORDER (PO)   │
└──┬───┘    └────┬─────┘    └──────────┘    └──────────┘    └──────────────┘
   │             │
   │ delete      │ reject
   ▼             ▼
  (deleted)   REJECTED
                  │
                  │ cancel
                  ▼
              CANCELLED
```

### 3.2 Create Requisition

```
Staff/Manager creates requisition
  → RequisitionService::create(data)
    → Validate store belongs to tenant
    → Validate products belong to tenant
    → Generate request_number: PR-YYYYMMDD-XXXX
    → DB::transaction:
      → PurchaseRequisition::create (status=draft, requested_by=Auth::id())
      → PurchaseRequisitionItem::create for each item
      → AuditService::log('requisition.created', ...)
```

### 3.3 Submit for Approval

```
  → RequisitionService::submit(requisition)
    → Must be in 'draft' status
    → Set status = 'pending'
    → AuditService::log('requisition.submitted', ...)
```

### 3.4 Approve/Reject

```
Manager/Owner approves
  → RequisitionService::approve(requisition)
    → Must be in 'pending' status
    → SoD check: approver != requested_by
    → Set status = 'approved', approved_by = Auth::id(), approved_at = now()
    → AuditService::log('requisition.approved', ...)

Manager/Owner rejects
  → RequisitionService::reject(requisition, reason)
    → Must be in 'pending' status
    → SoD check: rejector != requested_by
    → Set status = 'rejected', rejection_reason = reason
    → AuditService::log('requisition.rejected', ...)
```

### 3.5 Convert to Purchase Order

```
  → RequisitionService::convertToPo(requisition, supplierId, items)
    → Must be in 'approved' status
    → Select supplier + items from requisition
    → Call PurchaseService::create() with items
    → Link purchase.requisition_id = requisition.id
    → AuditService::log('requisition.converted_to_po', ...)
    → Return created Purchase
```

---

## 4. Goods Receipt Note (GRN) Flow

### 4.1 PO-Linked GRN

```
┌──────────┐    ┌──────────┐    ┌──────────┐
│ DRAFT    │───>│ RECEIVED  │    │ CANCELLED │
│          │    │          │    │          │
└────┬─────┘    └──────────┘    └──────────┘
     │               │
     │ cancel        │ triggers
     ▼               ▼
  CANCELLED    InventoryService::increase()
               for each received item
```

### 4.2 Create GRN from PO

```
  → GrnService::createFromPo(purchase, data)
    → Purchase must be 'ordered' status
    → Pre-fill items from purchase items (quantity_ordered = PO quantity)
    → Generate grn_number: GRN-YYYYMMDD-XXXX
    → Create GRN with status = 'draft'
    → AuditService::log('grn.created', ...)
```

### 4.3 Create Standalone GRN

```
  → GrnService::create(data)
    → No PO link (purchase_id = null)
    → User specifies supplier, store, items manually
    → Generate grn_number
    → Create GRN with status = 'draft'
    → AuditService::log('grn.created', ...)
```

### 4.4 Receive GRN

```
  → GrnService::receive(grn, items)
    → Must be in 'draft' status
    → For each item:
      → Validate quantity_received ≤ quantity_ordered (if PO-linked)
      → If batch_tracking enabled: validate batch_id + expiry_date
    → DB::transaction:
      → For each item with quantity_received > 0:
        → InventoryService::increase(store, product, quantity_received, 'grn', grn)
      → Set grn status = 'received', received_date = today
      → If PO-linked: update purchase status = 'received'
      → AuditService::log('grn.received', ...)
```

### 4.5 Cancel GRN

```
  → GrnService::cancel(grn)
    → Must be in 'draft' status (cannot cancel received GRN)
    → Set status = 'cancelled'
    → AuditService::log('grn.cancelled', ...)
```

---

## 5. Supplier Invoice 3-Way Match Flow

### 5.1 Match Workflow

```
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│ PENDING  │───>│ MATCHED  │───>│ APPROVED │    │ REJECTED │
│          │    │          │    │          │    │          │
└────┬─────┘    └──────────┘    └──────────┘    └──────────┘
     │          │
     │          │ mismatch
     ▼          ▼
  (deleted)  MISMATCHED
                  │
                  │ approve anyway / reject
                  ▼
             APPROVED / REJECTED
```

### 5.2 Create Invoice

```
  → InvoiceMatchingService::create(data)
    → Link to purchase_id and/or grn_id
    → Validate supplier belongs to tenant
    → Set status = 'pending'
    → AuditService::log('invoice.created', ...)
```

### 5.3 Run 3-Way Match

```
  → InvoiceMatchingService::match(invoice)
    → Load PO (if linked) and GRN (if linked)
    → Compare:
      → Quantity: PO qty vs GRN received vs Invoice qty
      → Price: PO unit_cost vs Invoice unit_cost
      → Total: GRN total vs Invoice total
    → Apply tolerance (from tenant settings: invoice_match_tolerance %)
    → If all within tolerance → status = 'matched'
    → If any mismatch → status = 'mismatched', store match_result JSON
    → AuditService::log('invoice.matched' or 'invoice.mismatched', ...)
```

### 5.4 Approve/Reject Invoice

```
  → InvoiceMatchingService::approve(invoice)
    → Must be 'matched' or 'mismatched'
    → Set status = 'approved', approved_by = Auth::id()
    → AuditService::log('invoice.approved', ...)

  → InvoiceMatchingService::reject(invoice, reason)
    → Must be 'matched' or 'mismatched'
    → Set status = 'rejected', rejection_reason = reason
    → AuditService::log('invoice.rejected', ...)
```

---

## 6. Auto-Reorder Flow

### 6.1 Generate Report

```
  → AutoReorderService::report(storeId)
    → Query inventories where quantity ≤ minimum_quantity
    → For each low-stock product:
      → Suggested qty = maximum_quantity - quantity (if maximum set)
      → Suggested qty = minimum_quantity * 2 (if no maximum)
    → Return list with: product, current_stock, minimum, suggested_qty, estimated_cost
```

### 6.2 Generate Requisition from Report

```
  → AutoReorderService::generateRequisition(storeId, productIds)
    → Filter report to selected products
    → Call RequisitionService::create() with items
    → Return created requisition
```

---

## 7. Customer Price List Flow

### 7.1 POS Pricing with Customer Price List

```
Customer selected at POS
  → If customer has price_list_id:
    → For each product in cart:
      → Check PriceListItem for (price_list_id, product_id)
      → If found → use price_list price
      → If not found → use product default price
  → If no price_list_id → use product default price
```

---

*End of Phase 3 Flow*
