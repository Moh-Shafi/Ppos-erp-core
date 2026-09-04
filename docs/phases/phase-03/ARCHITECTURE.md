# Phase 3 — CRM & Purchasing Enhancement — Architecture

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-13  
**Phase:** 3 — CRM & Purchasing Enhancement  
**Depends On:** Phase 2 (Inventory Enhancement — CLOSED)

---

## 1. System Architecture

### 1.1 Service Layer

All Phase 3 business logic is encapsulated in services, following the existing pattern:

| Service | Responsibility |
|---------|---------------|
| `LoyaltyService` | Points accrual, redemption, expiry, balance queries |
| `CustomerCreditService` | Credit limit checks, balance tracking, transactions |
| `SupplierRatingService` | Rating CRUD, average calculation |
| `RequisitionService` | Requisition workflow (create, submit, approve, reject, convert to PO) |
| `GrnService` | GRN creation, receiving, cancellation, InventoryService integration |
| `InvoiceMatchingService` | 3-way match logic, tolerance checks, approval workflow |
| `AutoReorderService` | Low-stock analysis, reorder quantity calculation, requisition generation |

### 1.2 Existing Services Enhanced

| Service | Changes |
|---------|---------|
| `PurchaseService` | Add `requisition_id` / `grn_id` linking; `receive` method remains for backward compat |
| `InventoryService` | No changes — GRN calls existing `increase` method |
| `AuditService` | No changes — all Phase 3 services log via existing pattern |

### 1.3 Controller Layer

| Controller | Endpoints |
|-----------|-----------|
| `CustomerController` (enhanced) | Existing CRUD + loyalty points, credit balance, price list assignment |
| `LoyaltyController` | Points balance, transactions, manual adjust, redeem |
| `CustomerCreditController` | Credit balance, transactions, manual adjust |
| `SupplierController` (enhanced) | Existing CRUD + ratings |
| `SupplierRatingController` | Rating CRUD |
| `RequisitionController` | Requisition CRUD + workflow (submit, approve, reject, cancel, convert) |
| `GrnController` | GRN CRUD + receive + cancel |
| `SupplierInvoiceController` | Invoice CRUD + match + approve + reject |
| `AutoReorderController` | Report + generate requisition |

---

## 2. Database Schema

### 2.1 New Tables

#### customer_loyalty_points
```
id              BIGINT PK
tenant_id       FK -> tenants
customer_id     FK -> customers
points_balance  INT (default 0)
total_earned    INT (default 0)
total_redeemed  INT (default 0)
created_at, updated_at

UNIQUE: (tenant_id, customer_id) — one row per customer
INDEX: (tenant_id, customer_id)
```

#### customer_loyalty_transactions
```
id              BIGINT PK
tenant_id       FK -> tenants
customer_id     FK -> customers
points          INT (positive for earn, negative for redeem/expire)
type            ENUM(earn, redeem, expire, adjust)
source          ENUM(sale, manual, expiry_sweep)
reference_type  VARCHAR(50) NULL (e.g., 'sale', 'manual')
reference_id    BIGINT NULL
balance_after   INT
note            TEXT NULL
created_at, updated_at

INDEX: (tenant_id, customer_id)
INDEX: (tenant_id, customer_id, created_at)
```

#### customer_credit_transactions
```
id              BIGINT PK
tenant_id       FK -> tenants
customer_id     FK -> customers
amount          DECIMAL(15,2) (positive = debit/increase debt, negative = credit/reduce debt)
type            ENUM(debit, credit, adjust)
source          ENUM(sale, payment, manual)
reference_type  VARCHAR(50) NULL
reference_id    BIGINT NULL
balance_after   DECIMAL(15,2)
note            TEXT NULL
created_at, updated_at

INDEX: (tenant_id, customer_id)
INDEX: (tenant_id, customer_id, created_at)
```

#### supplier_ratings
```
id              BIGINT PK
tenant_id       FK -> tenants
supplier_id     FK -> suppliers
rating          TINYINT (1-5)
criteria        ENUM(quality, delivery, pricing, service, overall)
note            TEXT NULL
rated_by        FK -> users
created_at, updated_at

INDEX: (tenant_id, supplier_id)
INDEX: (tenant_id, supplier_id, criteria)
```

#### purchase_requisitions
```
id              BIGINT PK
tenant_id       FK -> tenants
store_id        FK -> stores
request_number  VARCHAR(50)
status          ENUM(draft, pending, approved, rejected, cancelled)
requested_by    FK -> users
approved_by     FK -> users NULL
approved_at     TIMESTAMP NULL
rejection_reason TEXT NULL
note            TEXT NULL
created_at, updated_at

UNIQUE: (tenant_id, request_number)
INDEX: (tenant_id, status)
INDEX: (tenant_id, store_id)
```

#### purchase_requisition_items
```
id              BIGINT PK
tenant_id       FK -> tenants
requisition_id  FK -> purchase_requisitions (cascade)
product_id      FK -> products
quantity        INT
estimated_cost  DECIMAL(15,2) NULL
note            TEXT NULL
created_at, updated_at

INDEX: (tenant_id, requisition_id)
UNIQUE: (tenant_id, requisition_id, product_id)
```

#### goods_receipt_notes
```
id              BIGINT PK
tenant_id       FK -> tenants
grn_number      VARCHAR(50)
purchase_id     FK -> purchases NULL (null = standalone)
store_id        FK -> stores
supplier_id     FK -> suppliers
status          ENUM(draft, received, cancelled)
received_by     FK -> users
received_date   DATE
note            TEXT NULL
created_at, updated_at

UNIQUE: (tenant_id, grn_number)
INDEX: (tenant_id, status)
INDEX: (tenant_id, supplier_id)
INDEX: (tenant_id, purchase_id)
```

#### grn_items
```
id                  BIGINT PK
tenant_id           FK -> tenants
grn_id              FK -> goods_receipt_notes (cascade)
product_id          FK -> products
quantity_ordered    INT (0 if standalone)
quantity_received   INT
quantity_rejected   INT (default 0)
unit_cost           DECIMAL(15,2)
batch_id            FK -> stock_batches NULL
expiry_date         DATE NULL
rejection_reason    VARCHAR(255) NULL
note                TEXT NULL
created_at, updated_at

INDEX: (tenant_id, grn_id)
UNIQUE: (tenant_id, grn_id, product_id)
```

#### supplier_invoices
```
id              BIGINT PK
tenant_id       FK -> tenants
invoice_number  VARCHAR(100)
supplier_id     FK -> suppliers
purchase_id     FK -> purchases NULL
grn_id          FK -> goods_receipt_notes NULL
status          ENUM(pending, matched, mismatched, approved, rejected)
subtotal        DECIMAL(15,2)
tax             DECIMAL(15,2) DEFAULT 0
total           DECIMAL(15,2)
invoice_date    DATE
due_date        DATE NULL
match_result    JSON NULL (stores match details)
approved_by     FK -> users NULL
approved_at     TIMESTAMP NULL
rejection_reason TEXT NULL
created_at, updated_at

UNIQUE: (tenant_id, invoice_number)
INDEX: (tenant_id, status)
INDEX: (tenant_id, supplier_id)
```

### 2.2 Modified Tables

#### customers (additive columns)
```
+ credit_limit       DECIMAL(15,2) NULL
+ outstanding_balance DECIMAL(15,2) DEFAULT 0
+ price_list_id      FK -> price_lists NULL
```

#### purchases (additive columns)
```
+ requisition_id     FK -> purchase_requisitions NULL
+ grn_id             FK -> goods_receipt_notes NULL
```

### 2.3 Migration Order

Migrations 000054 through 000064 (11 migrations):

1. 000054 — Add columns to `customers` (credit_limit, outstanding_balance, price_list_id)
2. 000055 — Create `customer_loyalty_points` table
3. 000056 — Create `customer_loyalty_transactions` table
4. 000057 — Create `customer_credit_transactions` table
5. 000058 — Create `supplier_ratings` table
6. 000059 — Create `purchase_requisitions` table
7. 000060 — Create `purchase_requisition_items` table
8. 000061 — Add columns to `purchases` (requisition_id, grn_id)
9. 000062 — Create `goods_receipt_notes` table
10. 000063 — Create `grn_items` table
11. 000064 — Create `supplier_invoices` table

---

## 3. Model Relationships

```
Customer
├── loyaltyPoints (HasOne) → CustomerLoyaltyPoints
├── loyaltyTransactions (HasMany) → CustomerLoyaltyTransaction
├── creditTransactions (HasMany) → CustomerCreditTransaction
├── priceList (BelongsTo) → PriceList
└── sales (HasMany) → Sale [existing]

Supplier
├── ratings (HasMany) → SupplierRating
├── purchases (HasMany) → Purchase [existing]
└── grns (HasMany) → GoodsReceiptNote

PurchaseRequisition
├── items (HasMany) → PurchaseRequisitionItem
├── store (BelongsTo) → Store
├── requestedBy (BelongsTo) → User
├── approvedBy (BelongsTo) → User
└── purchases (HasMany) → Purchase [via requisition_id]

GoodsReceiptNote
├── items (HasMany) → GrnItem
├── purchase (BelongsTo) → Purchase [nullable]
├── store (BelongsTo) → Store
├── supplier (BelongsTo) → Supplier
├── receivedBy (BelongsTo) → User
└── supplierInvoice (HasOne) → SupplierInvoice [via grn_id]

SupplierInvoice
├── supplier (BelongsTo) → Supplier
├── purchase (BelongsTo) → Purchase [nullable]
├── grn (BelongsTo) → GoodsReceiptNote [nullable]
└── approvedBy (BelongsTo) → User [nullable]
```

---

## 4. Integration Points

### 4.1 POS Integration (Phase 4 preparation)
- `SaleService` will call `LoyaltyService::earnPoints()` on sale completion
- `SaleService` will call `LoyaltyService::redeemPoints()` when points used as discount
- `SaleService` will call `CustomerCreditService::checkLimit()` before completing credit sales
- `SaleService` will call `CustomerCreditService::addDebit()` for credit sales

### 4.2 Inventory Integration
- `GrnService::receive()` calls `InventoryService::increase()` for each received item
- Batch/expiry info passed through when `inventory.batch_tracking` feature enabled

### 4.3 Purchase Integration
- `RequisitionService::convertToPo()` creates a Purchase via `PurchaseService::create()`
- GRN can link to existing PO or be standalone
- Supplier invoice can link to PO and/or GRN for 3-way match

### 4.4 Audit Logging
All Phase 3 services log critical mutations via `AuditService::log()`:
- Loyalty: points_earned, points_redeemed, points_expired, points_adjusted
- Credit: credit_debit, credit_credit, credit_adjusted
- Requisition: created, submitted, approved, rejected, cancelled, converted_to_po
- GRN: created, received, cancelled
- Invoice: created, matched, mismatched, approved, rejected

---

## 5. Frontend Architecture

### 5.1 New Pages

| Page | Route | Module | Permission |
|------|-------|--------|-----------|
| CustomerDetailPage (enhanced) | /customers/:id | customers | customers.view |
| LoyaltyDashboardPage | /crm/loyalty | crm | crm.view |
| CustomerCreditPage | /crm/credit | crm | crm.view |
| SupplierDetailPage (enhanced) | /suppliers/:id | suppliers | suppliers.view |
| RequisitionsPage | /purchasing/requisitions | purchasing | purchases.view |
| RequisitionDetailPage | /purchasing/requisitions/:id | purchasing | purchases.view |
| GrnPage | /purchasing/grns | purchasing | purchases.view |
| GrnDetailPage | /purchasing/grns/:id | purchasing | purchases.view |
| SupplierInvoicesPage | /purchasing/invoices | purchasing | purchases.view |
| AutoReorderPage | /purchasing/auto-reorder | purchasing | purchases.view |

### 5.2 New Frontend Services

| Service | File |
|---------|------|
| loyaltyService | `src/services/loyalty.ts` |
| customerCreditService | `src/services/customerCredit.ts` |
| supplierRatingService | `src/services/supplierRating.ts` |
| requisitionService | `src/services/requisition.ts` |
| grnService | `src/services/grn.ts` |
| supplierInvoiceService | `src/services/supplierInvoice.ts` |
| autoReorderService | `src/services/autoReorder.ts` |

### 5.3 Sidebar Navigation Updates

New nav items (visible only when module enabled + user has permission):
- **CRM** section (when `crm` module enabled):
  - Loyalty Dashboard (feature: `customers.loyalty_points`)
  - Customer Credit (feature: `sales.customer_credit`)
- **Purchasing** section (when `purchasing` module enabled):
  - Requisitions (feature: `purchasing.requisition`)
  - Goods Receipt (always visible)
  - Supplier Invoices (feature: `purchasing.invoice_matching`)
  - Auto-Reorder (always visible)

---

## 6. Number Generation Patterns

| Entity | Pattern | Example |
|--------|---------|---------|
| Purchase Requisition | `PR-YYYYMMDD-XXXX` | PR-20260813-0001 |
| Goods Receipt Note | `GRN-YYYYMMDD-XXXX` | GRN-20260813-0001 |
| Supplier Invoice | Uses supplier's invoice number (not auto-generated) | INV-2026-001 |

All numbers are unique per tenant with sequential suffix.

---

*End of Phase 3 Architecture*
