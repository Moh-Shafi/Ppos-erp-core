# Phase 4 — POS Enhancement (ERP Integration) — PDR

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-13  
**Phase:** 4 — POS Enhancement (ERP Integration)  
**Depends On:** Phase 0 (ERP Architecture — CLOSED), Phase 1 (Catalog & Product Enhancement — CLOSED), Phase 3 (CRM & Purchasing Enhancement — CLOSED)

---

## 1. Objective

Upgrade the existing POS / Kasir module from a standalone sales terminal to a fully ERP-integrated point of sale. This phase connects POS to the module/feature-flag system, product variants, customer-specific price lists, customer credit limits, and adds advanced POS capabilities: hold/recall sale, refund processing, discount presets, keyboard shortcuts, and per-store receipt customization.

All features are **business-type agnostic** — they work for any tenant with the POS module enabled. Feature flags control which advanced capabilities are available per tenant.

---

## 2. Deliverables

### 2.1 POS Module & Feature Flag Integration
- POS module check enforced on all POS routes (middleware `module:pos`)
- Feature-flagged POS capabilities:
  - `pos.split_payment` — already supported (multi-payment at checkout)
  - `pos.hold_sale` — hold/recall sale (feature-flagged, default off)
  - `pos.refund` — refund processing (feature-flagged, default off)
  - `pos.discount_presets` — quick discount buttons (feature-flagged, default on)
- Frontend POS page reads feature flags from `useModuleConfigStore` and shows/hides UI accordingly

### 2.2 Product Variant Selection in POS
- Products with `has_variants = true` show variant selection modal in POS
- Variant price uses `price_override` if set, otherwise falls back to `product.selling_price`
- Variant SKU and barcode displayed for confirmation
- Sale items record variant_id (new column on `sale_items`)
- Inventory decrease uses variant-specific stock if applicable (future — variants share product inventory in this phase)

### 2.3 Customer Price List Integration
- When a customer is selected in POS, their `price_list_id` is used
- Price list items override `product.selling_price` at checkout
- Backend `SaleService::checkout` resolves price from:
  1. Customer's price list item (if customer has price_list_id and product is in that list)
  2. Product's `selling_price` (default fallback)
- Price list price is snapshotted in `sale_items.unit_price` (same as current behavior)

### 2.4 Customer Credit Limit Enforcement
- When a customer is selected and `sales.customer_credit` feature is enabled:
  - POS checkout calls credit check API before completing sale
  - If customer has `credit_limit` set and `outstanding_balance + sale_total > credit_limit + tolerance`, sale is blocked
  - Credit sale (unpaid/partial payment) increases `outstanding_balance` via `CustomerCreditService::addDebit`
  - Full payment does not affect `outstanding_balance`
- Backend `SaleService::checkout` integrates with `CustomerCreditService` when feature enabled

### 2.5 Hold / Recall Sale (Feature-Flagged: `pos.hold_sale`)
- Cashier can hold current cart state (items, customer, discount, tax, notes)
- Held sale creates a `held_sales` record with cart snapshot (JSON)
- Held sales list shows all held sales for current store
- Recall restores cart from held sale and deletes the held record
- Held sales are per-store, per-cashier
- Auto-expiry: held sales older than configurable threshold (default 24 hours) are auto-purged
- No inventory reservation at hold time (inventory checked at checkout)

### 2.6 Refund Processing (Feature-Flagged: `pos.refund`)
- Refund a completed sale (full or partial)
- Full refund: restores all inventory, marks sale as `refunded`, refunds payments
- Partial refund: specified items returned, inventory restored proportionally, sale total adjusted
- Refund creates `sale_return` inventory movements (same as cancel, but per-item for partial)
- Refund records `refunded_by` user and `refund_reason`
- Refund amount cannot exceed original sale total
- Payments are marked as `refunded` (full) or adjusted (partial — future, Phase 5 payment infrastructure)

### 2.7 Discount Presets (Feature-Flagged: `pos.discount_presets`)
- Tenant-level discount presets: percentage or fixed amount
- CRUD for discount presets (owner/manager only)
- Quick-discount buttons in POS checkout for applying preset discounts
- Presets: `id, tenant_id, name, type (percentage|fixed), value, is_active, sort_order`
- Applied discount is validated by backend (same validation as current `discount` field)

### 2.8 Keyboard Shortcuts
- F1: Focus search bar
- F2: Open checkout modal
- F3: Select customer
- F4: Hold sale (if feature enabled)
- F9: Apply quick discount (if feature enabled)
- F12: New transaction / clear cart
- Enter (in search): Add first product to cart
- +/-: Increment/decrement quantity of selected cart item
- Keyboard shortcuts are frontend-only, no backend changes

### 2.9 Receipt Customization (Per Store)
- `stores.receipt_settings` JSON column (already exists as `stores.settings`)
- Receipt settings include:
  - `header_text` — custom header (e.g., store tagline)
  - `footer_text` — custom footer (e.g., "Return within 7 days with receipt")
  - `show_cashier` — boolean
  - `show_customer` — boolean
  - `show_qr_code` — boolean (QRIS payment reference)
  - `paper_width` — 58mm or 80mm
  - `logo_url` — optional logo
- Frontend `Receipt` component reads store settings and renders accordingly
- Backend returns `receipt_settings` within store object

---

## 3. Database Changes

### 3.1 New Table: `held_sales`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | Auto-increment |
| tenant_id | FK → tenants | Cascade delete |
| store_id | FK → stores | Cascade delete |
| cashier_id | FK → users | Cascade delete |
| customer_id | FK → customers, nullable | Null on delete |
| cart_data | JSON | Full cart snapshot |
| hold_number | varchar(50) | Format: HOLD-YYYYMMDD-XXXX |
| status | enum('held','recalled','expired') | Default 'held' |
| held_at | datetime | When held |
| recalled_at | datetime, nullable | When recalled |
| expires_at | datetime | Auto-expiry threshold |

Indexes: `(tenant_id, store_id, status)`, `(tenant_id, hold_number)` unique

### 3.2 New Table: `discount_presets`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | Auto-increment |
| tenant_id | FK → tenants | Cascade delete |
| name | varchar(100) | Display name |
| type | enum('percentage','fixed') | Discount type |
| value | decimal(15,2) | Percentage (0-100) or fixed amount |
| is_active | boolean | Default true |
| sort_order | integer | Default 0 |

Indexes: `(tenant_id, is_active)`

### 3.3 New Table: `sale_refunds`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | Auto-increment |
| tenant_id | FK → tenants | Cascade delete |
| sale_id | FK → sales | Cascade delete |
| refunded_by | FK → users | Cascade delete |
| type | enum('full','partial') | Refund type |
| refund_reason | text, nullable | Reason for refund |
| refund_amount | decimal(15,2) | Total refund amount |
| status | enum('completed','cancelled') | Default 'completed' |
| refunded_at | datetime | When refund processed |

Indexes: `(tenant_id, sale_id)`, `(tenant_id, status)`

### 3.4 New Table: `sale_refund_items`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint (PK) | Auto-increment |
| sale_refund_id | FK → sale_refunds | Cascade delete |
| sale_item_id | FK → sale_items | Cascade delete |
| product_id | FK → products | Cascade delete |
| quantity | integer | Refunded quantity |
| unit_price | decimal(15,2) | Snapshot from sale_item |
| refund_amount | decimal(15,2) | Line refund amount |

### 3.5 Modified Table: `sales`
| Change | Details |
|--------|---------|
| Add `hold_status` | enum('none','held','recalled') default 'none' — tracks hold lifecycle on sale |
| Add `held_at` | datetime, nullable — when sale was held (if converted to sale) |
| Add `refunded_amount` | decimal(15,2) default 0 — cumulative refunded amount |
| Add `refund_status` | enum('none','partial','full') default 'none' |
| Add `price_list_id` | FK → price_lists, nullable — price list used for this sale |

### 3.6 Modified Table: `sale_items`
| Change | Details |
|--------|---------|
| Add `variant_id` | FK → product_variants, nullable — variant selected in POS |
| Add `original_price` | decimal(15,2), nullable — price before price list override |

### 3.7 Modified Table: `stores`
| Change | Details |
|--------|---------|
| Add `receipt_settings` | JSON, nullable — receipt customization config |

Note: `stores.settings` already exists as a JSON column. `receipt_settings` is a dedicated column for clarity and separation of concerns.

### 3.8 Modified Table: `payments`
| Change | Details |
|--------|---------|
| Add `refund_amount` | decimal(15,2) default 0 — amount refunded from this payment |
| Add `refund_status` | enum('none','partial','full') default 'none' |

### 3.9 New Permissions
| Permission Slug | Module | Description |
|----------------|--------|-------------|
| `pos.refund` | pos | Process refunds (owner, manager) |
| `pos.hold_sale` | pos | Hold/recall sales (owner, manager, cashier) |
| `pos.discount_presets` | pos | Manage discount presets (owner, manager) |

### 3.10 Feature Flags (Already Seeded in ModuleSeeder)
| Feature Slug | Default | Description |
|--------------|---------|-------------|
| `pos.hold_sale` | off | Hold/recall sale capability |
| `pos.refund` | off | Refund processing capability |
| `pos.discount_presets` | on | Discount presets in POS |

---

## 4. Scope

### In Scope
- POS module/feature flag integration (backend + frontend)
- Product variant selection in POS UI
- Customer price list resolution at checkout
- Customer credit limit enforcement at checkout
- Hold/recall sale (backend service + API + frontend UI)
- Refund processing (backend service + API + frontend UI)
- Discount presets (backend CRUD + API + frontend UI)
- Keyboard shortcuts (frontend only)
- Receipt customization (backend settings + frontend rendering)
- All existing POS tests must continue to pass (regression)

### Out of Scope
- Offline mode (Phase 11 — Desktop & Printer)
- Payment gateway integration (Phase 5 — Payment Infrastructure)
- Journal entries for refunds (Phase 6 — Finance/Accounting)
- Variant-specific inventory tracking (variants share product inventory in this phase)
- Loyalty point redemption as payment discount (future enhancement)
- Multi-currency support
- POS pin/bypass login (future)

---

## 5. Acceptance Criteria

- [ ] POS routes enforce `module:pos` middleware
- [ ] POS frontend shows/hides UI based on feature flags (`pos.hold_sale`, `pos.refund`, `pos.discount_presets`)
- [ ] Product variants selectable in POS when `has_variants = true`
- [ ] Variant `price_override` applied at checkout when set
- [ ] Customer price list applied at checkout (overrides `product.selling_price`)
- [ ] Customer credit limit enforced when `sales.customer_credit` feature enabled
- [ ] Credit sale increases `outstanding_balance` via `CustomerCreditService`
- [ ] Hold sale creates `held_sales` record with cart snapshot
- [ ] Recall sale restores cart and deletes held record
- [ ] Held sales auto-expire after configurable threshold
- [ ] Full refund restores inventory, marks sale `refunded`, refunds payments
- [ ] Partial refund restores specified items' inventory, adjusts sale total
- [ ] Refund records `refunded_by` and `refund_reason`
- [ ] Discount presets CRUD works (owner/manager only)
- [ ] Discount preset buttons appear in POS checkout when feature enabled
- [ ] Keyboard shortcuts work (F1, F2, F3, F4, F9, F12, Enter, +/-)
- [ ] Receipt renders per-store customization settings
- [ ] All existing POS/sale tests pass (regression — 1021+ tests)
- [ ] New tests for: variant selection, price list, credit limit, hold/recall, refund, discount presets, receipt settings
- [ ] E2E test: select variant product → select customer with price list → checkout → verify price
- [ ] E2E test: hold sale → recall sale → checkout
- [ ] E2E test: checkout → full refund → verify inventory restored
- [ ] E2E test: checkout → partial refund → verify inventory partially restored

---

## 6. Dependencies

| Dependency | Status | Notes |
|------------|--------|-------|
| Phase 0 — ERP Architecture | ✅ CLOSED | Module system, feature flags, RBAC, store context |
| Phase 1 — Catalog & Product Enhancement | ✅ CLOSED | Product variants, price lists, barcodes |
| Phase 3 — CRM & Purchasing Enhancement | ✅ CLOSED | Customer credit limits, loyalty points |
| Existing POS (SaleService, SaleController) | ✅ Active | Base for enhancement |

---

## 7. Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Price list resolution complexity | Medium | Medium | Clear priority order: price list → product price; backend-only resolution |
| Refund partial calculation edge cases | Medium | High | Comprehensive test coverage; atomic transactions |
| Hold sale cart data format change | Low | Medium | JSON snapshot is flexible; version field in cart_data |
| Credit limit + partial payment interaction | Medium | High | Credit only debited for unpaid portion; full payment = no credit impact |
| Existing POS test regression | Low | High | All 1021 tests must pass; new features are additive |

---

## 8. Implementation Order

1. **Backend migrations** — new tables, modified columns
2. **Backend models** — HeldSale, DiscountPreset, SaleRefund, SaleRefundItem; update Sale, SaleItem, Store, Payment
3. **Backend services** — HoldSaleService, RefundService, DiscountPresetService; update SaleService for variants, price lists, credit
4. **Backend controllers** — HoldSaleController, RefundController, DiscountPresetController; update SaleController
5. **Backend routes** — new route groups with feature flag middleware
6. **Backend seeders** — new permissions, update RbacSeeder
7. **Frontend types** — new interfaces for held sales, discount presets, refunds
8. **Frontend services** — holdSale, refund, discountPreset API services
9. **Frontend POS page** — variant selection, price list display, credit limit check, hold/recall UI, refund UI, discount presets, keyboard shortcuts
10. **Frontend receipt** — per-store customization
11. **Backend tests** — unit + feature tests for all new functionality
12. **E2E tests** — full flows
13. **Regression** — all existing tests pass
14. **Final audit**

---

*End of Phase 4 PDR*
