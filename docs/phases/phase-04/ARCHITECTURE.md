# Phase 4 — POS Enhancement (ERP Integration) — Architecture

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-13  
**Phase:** 4 — POS Enhancement (ERP Integration)  
**Depends On:** Phase 0, Phase 1, Phase 3

---

## 1. System Context

Phase 4 enhances the existing POS module within the multi-tenant ERP architecture. The POS module (`pos`) is already registered in the module system and has feature flags defined in `ModuleSeeder`. This phase connects the POS to the ERP module system, product variants (Phase 1), customer price lists (Phase 1), and customer credit limits (Phase 3), while adding new capabilities (hold/recall, refund, discount presets, receipt customization).

### 1.1 Current State

```
┌─────────────────────────────────────────────────────────────┐
│                        Frontend (React)                      │
│  POSPage.tsx                                                 │
│  ├── Product grid (search, category filter)                  │
│  ├── Cart (store select, customer select, discount, tax)     │
│  ├── Checkout modal (multi-payment)                          │
│  └── Receipt component                                       │
└──────────────────────┬──────────────────────────────────────┘
                       │ REST API (Sanctum)
┌──────────────────────▼──────────────────────────────────────┐
│                     Backend (Laravel)                        │
│  SaleController                                              │
│  ├── checkout() → SaleService::checkout()                    │
│  ├── cancel() → SaleService::cancel()                        │
│  ├── addPayment() → PaymentService::addPayment()             │
│  └── listPayments()                                          │
│                                                              │
│  SaleService                                                 │
│  ├── checkout() — atomic: sale + items + inventory + payment │
│  ├── cancel() — restores inventory, refunds payments         │
│  └── generateSaleNumber()                                    │
│                                                              │
│  PaymentService                                              │
│  ├── createForCheckout() — idempotent payment creation       │
│  ├── addPayment() — add payment to existing sale             │
│  └── refundPayments() — mark payments as refunded            │
│                                                              │
│  InventoryService                                            │
│  ├── decrease() — stock decrease with movement record        │
│  └── increase() — stock increase with movement record        │
└─────────────────────────────────────────────────────────────┘
```

### 1.2 Target State (Phase 4)

```
┌─────────────────────────────────────────────────────────────┐
│                        Frontend (React)                      │
│  POSPage.tsx (enhanced)                                      │
│  ├── Product grid (search, category, variant selection)      │
│  ├── Cart (store, customer, price list display, credit check)│
│  ├── Checkout modal (multi-payment, discount presets)        │
│  ├── Hold/Recall UI (if pos.hold_sale enabled)               │
│  ├── Refund UI (if pos.refund enabled)                       │
│  ├── Keyboard shortcuts                                      │
│  └── Receipt (per-store customization)                       │
│                                                              │
│  New Pages: DiscountPresetsPage, HeldSalesList               │
│  New Services: holdSale.ts, refund.ts, discountPreset.ts     │
└──────────────────────┬──────────────────────────────────────┘
                       │ REST API (Sanctum)
┌──────────────────────▼──────────────────────────────────────┐
│                     Backend (Laravel)                        │
│  SaleController (enhanced)                                   │
│  ├── checkout() — now with variant_id, price list, credit    │
│  ├── cancel() — unchanged                                    │
│  ├── refund() → RefundService (NEW)                          │
│  └── addPayment() — unchanged                                │
│                                                              │
│  HoldSaleController (NEW)                                    │
│  ├── hold() → HoldSaleService::hold()                        │
│  ├── recall() → HoldSaleService::recall()                    │
│  ├── list() → HoldSaleService::list()                        │
│  └── expire() → HoldSaleService::processExpiry()             │
│                                                              │
│  RefundController (NEW)                                      │
│  ├── refund() → RefundService::processRefund()               │
│  └── show() → RefundService::show()                          │
│                                                              │
│  DiscountPresetController (NEW)                              │
│  └── CRUD → DiscountPresetService                            │
│                                                              │
│  SaleService (enhanced)                                      │
│  ├── checkout() — resolves price list, enforces credit limit │
│  ├── cancel() — unchanged                                    │
│  └── resolveUnitPrice() — NEW: price list → product price    │
│                                                              │
│  HoldSaleService (NEW)                                       │
│  ├── hold() — snapshot cart to held_sales                    │
│  ├── recall() — restore cart from held_sales                 │
│  ├── list() — list held sales for store                      │
│  └── processExpiry() — auto-expire old held sales            │
│                                                              │
│  RefundService (NEW)                                         │
│  ├── processRefund() — full/partial refund with inventory    │
│  ├── validateRefund() — check sale eligibility               │
│  └── calculateRefundAmount() — compute refund total          │
│                                                              │
│  DiscountPresetService (NEW)                                 │
│  └── CRUD operations for discount presets                    │
│                                                              │
│  CustomerCreditService (existing, from Phase 3)              │
│  └── checkLimit() — called during checkout if feature on     │
│                                                              │
│  InventoryService (existing)                                 │
│  └── increase() — used by refund for inventory restore       │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. Component Architecture

### 2.1 Backend Services

#### SaleService (Enhanced)
```
SaleService
├── __construct(InventoryService, PaymentService, CustomerCreditService)
├── checkout(array $data): Sale
│   ├── Validate store, customer, items (existing)
│   ├── NEW: Resolve variant_id per item (if product.has_variants)
│   ├── NEW: resolveUnitPrice() — price list → product price
│   ├── NEW: Check customer credit limit (if feature enabled)
│   ├── Calculate totals (existing, enhanced with price list prices)
│   ├── Create Sale + SaleItems (with variant_id, original_price)
│   ├── Decrease inventory (existing)
│   ├── Create payments (existing)
│   └── NEW: Add debit to customer credit (if unpaid/partial)
│   └── NEW: Earn loyalty points (if feature enabled)
├── cancel(Sale $sale): Sale (existing, unchanged)
└── resolveUnitPrice(Product, ?Customer, ?ProductVariant): float
    ├── If customer has price_list_id:
    │   └── Look up PriceListItem for product/variant
    │       └── Return price_list_item.price if found
    ├── If variant has price_override:
    │   └── Return variant.price_override
    └── Return product.selling_price (default)
```

#### HoldSaleService (New)
```
HoldSaleService
├── __construct()
├── hold(int $storeId, int $cashierId, ?int $customerId, array $cartData): HeldSale
│   ├── Generate hold_number: HOLD-YYYYMMDD-XXXX
│   ├── Create held_sales record with cart JSON snapshot
│   ├── Set expires_at = now() + threshold (default 24h)
│   └── Return HeldSale model
├── recall(int $heldSaleId): array
│   ├── Validate held sale belongs to tenant/store
│   ├── Check status = 'held' (not expired/recalled)
│   ├── Mark status = 'recalled', set recalled_at
│   └── Return cart_data array
├── list(int $storeId): Collection
│   └── Return held sales for store with status = 'held'
├── processExpiry(): int
│   ├── Find held_sales where status = 'held' and expires_at < now()
│   ├── Mark as 'expired'
│   └── Return count of expired sales
└── delete(int $heldSaleId): void
    └── Delete held sale record
```

#### RefundService (New)
```
RefundService
├── __construct(InventoryService, PaymentService)
├── processRefund(Sale $sale, array $data): SaleRefund
│   ├── Validate sale is 'completed' (not already refunded/cancelled)
│   ├── Validate refund items belong to sale
│   ├── Validate refund quantities <= original quantities
│   ├── Calculate refund_amount
│   ├── DB::transaction:
│   │   ├── Create sale_refunds record
│   │   ├── Create sale_refund_items records
│   │   ├── For each refunded item:
│   │   │   └── InventoryService::increase() with 'sale_return' type
│   │   ├── Update sale: refunded_amount, refund_status
│   │   ├── If full refund:
│   │   │   ├── Update sale.status = 'refunded'
│   │   │   └── PaymentService::refundPayments()
│   │   └── Return SaleRefund with relations
├── show(int $refundId): SaleRefund
├── list(int $saleId): Collection
└── calculateRefundAmount(Sale, array $items): float
```

#### DiscountPresetService (New)
```
DiscountPresetService
├── __construct()
├── list(): Collection
├── create(array $data): DiscountPreset
├── update(int $id, array $data): DiscountPreset
├── delete(int $id): void
└── getActive(): Collection
    └── Return active presets ordered by sort_order
```

### 2.2 Backend Models

#### New Models
```
HeldSale
├── fillable: store_id, cashier_id, customer_id, cart_data, hold_number, status, held_at, recalled_at, expires_at
├── casts: cart_data → array, held_at → datetime, recalled_at → datetime, expires_at → datetime
├── relations: store(), cashier(), customer()
└── scopes: held(), forStore()

DiscountPreset
├── fillable: name, type, value, is_active, sort_order
├── casts: is_active → boolean, value → decimal:2
├── relations: tenant()
└── scopes: active()

SaleRefund
├── fillable: sale_id, refunded_by, type, refund_reason, refund_amount, status, refunded_at
├── casts: refund_amount → decimal:2, refunded_at → datetime
├── relations: sale(), refundedBy(), items()

SaleRefundItem
├── fillable: sale_refund_id, sale_item_id, product_id, quantity, unit_price, refund_amount
├── casts: unit_price → decimal:2, refund_amount → decimal:2
├── relations: saleRefund(), saleItem(), product()
```

#### Enhanced Models
```
Sale (enhanced)
├── NEW fillable: hold_status, held_at, refunded_amount, refund_status, price_list_id
├── NEW casts: held_at → datetime, refunded_amount → decimal:2
├── NEW relations: refunds(), priceList()
└── NEW methods: isRefunded(), isPartiallyRefunded(), isHeld()

SaleItem (enhanced)
├── NEW fillable: variant_id, original_price
├── NEW casts: original_price → decimal:2
├── NEW relations: variant()

Store (enhanced)
├── NEW fillable: receipt_settings
├── NEW casts: receipt_settings → array

Payment (enhanced)
├── NEW fillable: refund_amount, refund_status
├── NEW casts: refund_amount → decimal:2
```

### 2.3 Frontend Architecture

#### Enhanced Cart Store
```
useCartStore (enhanced)
├── EXISTING: items, storeId, customerId, discount, tax, notes
├── NEW: selectedVariants: Map<productId, variantId>
├── NEW: appliedPresetId: number | null
├── NEW methods:
│   ├── setVariant(productId, variantId)
│   ├── clearVariant(productId)
│   ├── applyPreset(presetId, discountValue, discountType)
│   └── clearPreset()
└── ENHANCED: addProduct() — if has_variants, don't add directly (trigger variant modal)
```

#### New Frontend Services
```
holdSaleService
├── list(): GET /held-sales
├── hold(data): POST /held-sales
├── recall(id): POST /held-sales/{id}/recall
└── delete(id): DELETE /held-sales/{id}

refundService
├── show(saleId, refundId): GET /sales/{saleId}/refunds/{refundId}
├── list(saleId): GET /sales/{saleId}/refunds
└── process(saleId, data): POST /sales/{saleId}/refunds

discountPresetService
├── list(): GET /discount-presets
├── create(data): POST /discount-presets
├── update(id, data): PUT /discount-presets/{id}
└── delete(id): DELETE /discount-presets/{id}
```

#### New Frontend Components
```
VariantSelectModal
├── Props: product, onSelect, onClose
├── Shows variant options (size, color, etc.)
├── Returns selected variant to cart

HeldSalesButton
├── Shows count of held sales for current store
├── Opens HeldSalesModal on click

HeldSalesModal
├── Lists held sales with time, customer, item count
├── Recall button per held sale
├── Delete button per held sale

RefundModal
├── Props: sale, onRefunded, onClose
├── Shows sale items with refund quantity inputs
├── Full refund button
├── Refund reason input
├── Submits to refundService

DiscountPresetButtons
├── Shows active discount presets as buttons
├── Applies discount to cart on click
├── Highlights active preset

KeyboardShortcuts (hook)
├── useKeyboardShortcuts(callbacks)
├── Registers F1-F12, Enter, +/- handlers
├── Cleanup on unmount
```

---

## 3. Data Flow

### 3.1 Checkout with Variants + Price List + Credit

```
User selects product with variants
  → VariantSelectModal opens
  → User selects variant
  → Cart adds item with variant_id

User selects customer (with price_list_id)
  → Frontend fetches price list items for selected products
  → Cart display updates with price list prices
  → Credit limit check: GET /customers/{id}/credit/check

User clicks checkout
  → POST /sales/checkout
  → SaleController validates (includes variant_id per item)
  → SaleService::checkout():
    1. Validate store, customer, items (existing)
    2. For each item:
       a. If variant_id provided, load ProductVariant
       b. resolveUnitPrice(product, customer, variant)
       c. Snapshot unit_price, original_price, variant_id
    3. Calculate totals
    4. If customer_credit feature enabled:
       a. CustomerCreditService::checkLimit(customer, total)
       b. If not allowed → throw DomainException
    5. Create Sale + SaleItems
    6. Decrease inventory (existing)
    7. Create payments (existing)
    8. If payment_status != 'paid':
       a. CustomerCreditService::addDebit(customer, unpaid, 'sale', sale_id)
    9. If loyalty_points feature enabled:
       a. LoyaltyService::earnPoints(customer, sale_total, 'sale', sale_id)
    10. Return sale with relations
```

### 3.2 Hold / Recall Flow

```
User clicks "Hold Sale" (F4)
  → Frontend serializes cart: {items, customerId, discount, tax, notes, variants}
  → POST /held-sales {store_id, customer_id, cart_data}
  → HoldSaleService::hold():
    1. Generate hold_number
    2. Create held_sales record
    3. Set expires_at = now + 24h
  → Frontend clears cart
  → Show success toast

User clicks "Held Sales" button
  → GET /held-sales?store_id={current}
  → HoldSaleService::list()
  → Show HeldSalesModal

User clicks "Recall" on a held sale
  → POST /held-sales/{id}/recall
  → HoldSaleService::recall():
    1. Validate status = 'held'
    2. Mark as 'recalled'
    3. Return cart_data
  → Frontend restores cart from cart_data
  → User continues checkout
```

### 3.3 Refund Flow

```
User opens sale detail (from Sales page)
  → Clicks "Refund" button (if pos.refund enabled)
  → RefundModal opens with sale items

Full refund:
  → POST /sales/{saleId}/refunds {type: 'full', reason: '...'}
  → RefundService::processRefund():
    1. Validate sale.status = 'completed'
    2. DB::transaction:
       a. Create sale_refunds (type='full')
       b. For each sale_item:
          - Create sale_refund_item
          - InventoryService::increase(store, product, qty, 'sale_return')
       c. Update sale: status='refunded', refund_status='full', refunded_amount=total
       d. PaymentService::refundPayments()
    3. Return SaleRefund

Partial refund:
  → User selects items and quantities to refund
  → POST /sales/{saleId}/refunds {type: 'partial', items: [...], reason: '...'}
  → RefundService::processRefund():
    1. Validate sale.status = 'completed'
    2. Validate refund quantities <= original
    3. Calculate refund_amount = sum(refund_qty * unit_price)
    4. DB::transaction:
       a. Create sale_refunds (type='partial')
       b. For each refund item:
          - Create sale_refund_item
          - InventoryService::increase(store, product, refund_qty, 'sale_return')
       c. Update sale: refund_status='partial', refunded_amount += refund_amount
       d. Update payments refund_amount/refund_status proportionally
    5. Return SaleRefund
```

---

## 4. Middleware & Route Architecture

### 4.1 Route Groups

```php
// POS module check on all POS-related routes
Route::middleware(['module:pos'])->group(function () {
    // POS checkout (existing, enhanced)
    Route::post('sales/checkout', ...);

    // Hold/Recall (feature-flagged)
    Route::middleware('feature:pos.hold_sale')->prefix('held-sales')->group(function () {
        Route::get('/', [HoldSaleController::class, 'index']);
        Route::post('/', [HoldSaleController::class, 'store']);
        Route::post('/{id}/recall', [HoldSaleController::class, 'recall']);
        Route::delete('/{id}', [HoldSaleController::class, 'destroy']);
    });

    // Refund (feature-flagged)
    Route::middleware('feature:pos.refund')->prefix('sales/{saleId}/refunds')->group(function () {
        Route::get('/', [RefundController::class, 'index']);
        Route::post('/', [RefundController::class, 'store']);
        Route::get('/{refundId}', [RefundController::class, 'show']);
    });

    // Discount Presets (feature-flagged)
    Route::middleware('feature:pos.discount_presets')->prefix('discount-presets')->group(function () {
        Route::get('/', [DiscountPresetController::class, 'index']);
        Route::post('/', [DiscountPresetController::class, 'store'])
            ->middleware('permission:pos.discount_presets');
        Route::put('/{id}', [DiscountPresetController::class, 'update'])
            ->middleware('permission:pos.discount_presets');
        Route::delete('/{id}', [DiscountPresetController::class, 'destroy'])
            ->middleware('permission:pos.discount_presets');
    });
});
```

### 4.2 Feature Flag Enforcement

| Feature Flag | Routes Affected | Frontend Impact |
|-------------|----------------|-----------------|
| `pos.hold_sale` | `/held-sales/*` | Show hold button, held sales list |
| `pos.refund` | `/sales/{id}/refunds/*` | Show refund button on sales |
| `pos.discount_presets` | `/discount-presets/*` | Show preset buttons in checkout |
| `sales.customer_credit` | Credit check during checkout | Credit limit warning in POS |
| `customers.loyalty_points` | Points earning during checkout | Loyalty points display |

---

## 5. Database Schema (ERD)

```
┌──────────────┐     ┌──────────────┐     ┌───────────────────┐
│   stores     │     │    sales     │     │   sale_items      │
├──────────────┤     ├──────────────┤     ├───────────────────┤
│ id           │◄──┐ │ id           │◄──┐ │ id                │
│ tenant_id    │   │ │ tenant_id    │   │ │ sale_id           │
│ name         │   │ │ store_id     │   │ │ product_id        │
│ settings     │   │ │ cashier_id   │   │ │ variant_id (NEW)  │
│ receipt_     │   │ │ customer_id  │   │ │ product_name      │
│  settings    │   │ │ sale_number  │   │ │ sku               │
│  (NEW)       │   │ │ status       │   │ │ quantity          │
└──────────────┘   │ │ payment_     │   │ │ unit_price        │
                   │ │  status      │   │ │ original_price    │
                   │ │ hold_status  │   │ │  (NEW)            │
                   │ │  (NEW)       │   │ │ discount          │
                   │ │ refund_status│   │ │ tax               │
                   │ │  (NEW)       │   │ │ subtotal          │
                   │ │ refunded_    │   │ │ total             │
                   │ │  amount(NEW) │   │ └───────────────────┘
                   │ │ price_list_id│   │
                   │ │  (NEW)       │   │     ┌───────────────────┐
                   │ └──────────────┘   │     │ sale_refunds      │
                   │                    │     ├───────────────────┤
                   │     ┌──────────────┘     │ id                │
                   │     │                    │ tenant_id         │
                   │     │                    │ sale_id           │
                   │     │                    │ refunded_by       │
                   │     │                    │ type (full/partial)│
                   │     │                    │ refund_reason     │
                   │     │                    │ refund_amount     │
                   │     │                    │ status            │
                   │     │                    │ refunded_at       │
                   │     │                    └───────────────────┘
                   │     │                            │
                   │     │                    ┌───────────────────┐
                   │     │                    │ sale_refund_items │
                   │     │                    ├───────────────────┤
                   │     │                    │ id                │
                   │     │                    │ sale_refund_id    │
                   │     │                    │ sale_item_id      │
                   │     │                    │ product_id        │
                   │     │                    │ quantity          │
                   │     │                    │ unit_price        │
                   │     │                    │ refund_amount     │
                   │     │                    └───────────────────┘
                   │     │
                   │     │     ┌───────────────────┐
                   │     │     │ held_sales        │
                   │     │     ├───────────────────┤
                   │     │     │ id                │
                   │     │     │ tenant_id         │
                   │     │     │ store_id          │
                   │     │     │ cashier_id        │
                   │     │     │ customer_id       │
                   │     │     │ cart_data (JSON)  │
                   │     │     │ hold_number       │
                   │     │     │ status            │
                   │     │     │ held_at           │
                   │     │     │ recalled_at       │
                   │     │     │ expires_at        │
                   │     │     └───────────────────┘
                   │     │
                   │     │     ┌───────────────────┐
                   │     │     │ discount_presets  │
                   │     │     ├───────────────────┤
                   │     │     │ id                │
                   │     │     │ tenant_id         │
                   │     │     │ name              │
                   │     │     │ type (pct/fixed)  │
                   │     │     │ value             │
                   │     │     │ is_active         │
                   │     │     │ sort_order        │
                   │     │     └───────────────────┘
                   │     │
                   │     │     ┌───────────────────┐
                   └─────┼────►│   payments        │
                         │     ├───────────────────┤
                         │     │ id                │
                         │     │ sale_id           │
                         │     │ payment_method    │
                         │     │ amount            │
                         │     │ refund_amount(NEW)│
                         │     │ refund_status(NEW)│
                         │     │ status            │
                         │     └───────────────────┘
```

---

## 6. Integration Points

### 6.1 Phase 1 Integration — Product Variants
- `Product.has_variants` → POS shows variant selection modal
- `ProductVariant.price_override` → Used in price resolution
- `ProductVariant.sku`, `barcode` → Displayed in POS for confirmation
- `sale_items.variant_id` → Links to `ProductVariant`

### 6.2 Phase 1 Integration — Price Lists
- `Customer.price_list_id` → Resolved at checkout
- `PriceListItem` for product/variant → Overrides `selling_price`
- `sale_items.original_price` → Records price before override
- `sale.price_list_id` → Records which price list was used

### 6.3 Phase 3 Integration — Customer Credit
- `Customer.credit_limit`, `outstanding_balance` → Checked at checkout
- `CustomerCreditService::checkLimit()` → Called by SaleService
- `CustomerCreditService::addDebit()` → Called for unpaid/partial sales
- Feature flag `sales.customer_credit` → Controls whether credit check runs

### 6.4 Phase 3 Integration — Loyalty Points
- `LoyaltyService::earnPoints()` → Called after successful checkout
- Feature flag `customers.loyalty_points` → Controls whether points are earned
- Points earned = `sale_total / earn_rate` (from tenant settings)

### 6.5 Existing Integration — Inventory
- `InventoryService::decrease()` → Called during checkout (existing)
- `InventoryService::increase()` → Called during refund (new usage)
- Movement type `sale_return` → Used for refund inventory restore

### 6.6 Existing Integration — Payments
- `PaymentService::createForCheckout()` → Called during checkout (existing)
- `PaymentService::refundPayments()` → Called during full refund (existing, reused)
- `Payment.refund_amount`, `refund_status` → New fields for partial refund tracking

---

## 7. Security Architecture

### 7.1 Tenant Isolation
- All new tables include `tenant_id` with FK constraint
- All queries use `BelongsToTenant` trait or explicit `where('tenant_id', ...)`
- `HeldSale`, `DiscountPreset`, `SaleRefund`, `SaleRefundItem` all use `BelongsToTenant`
- No cross-tenant data access possible

### 7.2 RBAC
| Permission | Owner | Manager | Cashier | Staff |
|-----------|-------|---------|---------|-------|
| pos.use | ✅ | ✅ | ✅ | ❌ |
| pos.hold_sale | ✅ | ✅ | ✅ | ❌ |
| pos.refund | ✅ | ✅ | ❌ | ❌ |
| pos.discount_presets | ✅ | ✅ | ❌ | ❌ |
| sales.view | ✅ | ✅ | ✅ | ❌ |
| sales.manage | ✅ | ✅ | ✅ | ❌ |

### 7.3 Feature Flag Enforcement
- Backend: `feature:pos.hold_sale`, `feature:pos.refund`, `feature:pos.discount_presets` middleware
- Frontend: `useModuleConfigStore.hasFeature()` checks
- Both layers enforce independently (defense in depth)

### 7.4 Input Validation
- All refund quantities validated: `>= 1` and `<= original sale_item.quantity`
- Discount preset value validated: percentage 0-100, fixed > 0
- Cart data JSON schema validated on hold
- Refund reason: optional, max 2000 chars

---

## 8. Performance Considerations

- **Price list resolution**: Single query to fetch all price list items for cart products (batch, not per-item)
- **Held sales list**: Indexed on `(tenant_id, store_id, status)`, only fetches 'held' status
- **Refund**: Atomic transaction, locks sale row to prevent concurrent refunds
- **Discount presets**: Cached in frontend after first fetch for session duration
- **Keyboard shortcuts**: Event delegation, single listener, cleanup on unmount

---

*End of Phase 4 Architecture*
