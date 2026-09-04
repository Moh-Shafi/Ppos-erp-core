# Phase 4 — POS Enhancement (ERP Integration) — Flow

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-13  
**Phase:** 4 — POS Enhancement (ERP Integration)  
**Depends On:** Phase 0, Phase 1, Phase 3

---

## 1. POS Checkout Flow (Enhanced)

### 1.1 Standard Checkout (No Variants, No Price List, No Credit)

```
┌─────────┐     ┌──────────┐     ┌─────────────┐     ┌──────────────┐
│  User   │────►│  POS UI  │────►│  SaleCtrl   │────►│  SaleService │
│  clicks │     │  sends   │     │  validates  │     │  checkout()  │
│  Bayar  │     │  POST    │     │  input      │     │              │
└─────────┘     └──────────┘     └─────────────┘     └──────┬───────┘
                                                          │
                    ┌─────────────────────────────────────┘
                    │
                    ▼
              ┌───────────┐     ┌──────────────┐     ┌──────────────┐
              │ Validate  │────►│ Calculate    │────►│ Create Sale  │
              │ store,    │     │ totals       │     │ + SaleItems  │
              │ customer, │     │ (backend)    │     │ (snapshot)   │
              │ items     │     └──────────────┘     └──────┬───────┘
              └───────────┘                                   │
                                                              ▼
                                              ┌──────────────────────┐
                                              │ Decrease Inventory   │
                                              │ (per item, locked)   │
                                              └──────────┬───────────┘
                                                         │
                                                         ▼
                                              ┌──────────────────────┐
                                              │ Create Payments      │
                                              │ (PaymentService)     │
                                              └──────────┬───────────┘
                                                         │
                                                         ▼
                                              ┌──────────────────────┐
                                              │ Return Sale + rels   │
                                              └──────────────────────┘
```

### 1.2 Checkout with Variant Selection

```
User clicks product with has_variants=true
  │
  ▼
┌───────────────────┐     No variants      ┌────────────────────┐
│ Product has       │─────or single───────►│ Add to cart        │
│ variants?         │     variant          │ (product_id only)  │
└────────┬──────────┘                      └────────────────────┘
         │ Yes, multiple
         ▼
┌───────────────────┐
│ VariantSelectModal│
│ Shows options:    │
│ - Size: S/M/L     │
│ - Color: Red/Blue │
└────────┬──────────┘
         │ User selects
         ▼
┌───────────────────┐
│ Add to cart with  │
│ product_id +      │
│ variant_id        │
└───────────────────┘
```

### 1.3 Checkout with Customer Price List

```
User selects customer
  │
  ▼
┌──────────────────────┐     No price_list_id    ┌────────────────────┐
│ Customer has         │────────────────────────►│ Use product        │
│ price_list_id?       │                         │ selling_price      │
└────────┬─────────────┘                         └────────────────────┘
         │ Yes
         ▼
┌──────────────────────┐
│ Fetch price list     │
│ items for cart       │
│ products             │
└────────┬─────────────┘
         │
         ▼
┌──────────────────────┐     Found              ┌────────────────────┐
│ PriceListItem        │───────────────────────►│ Use price_list     │
│ exists for product?  │                         │ item price         │
└────────┬─────────────┘                         └────────────────────┘
         │ Not found
         ▼
┌──────────────────────┐
│ Fall back to         │
│ product.selling_price│
└──────────────────────┘
```

### 1.4 Checkout with Credit Limit Enforcement

```
SaleService::checkout() — after calculating total
  │
  ▼
┌──────────────────────────┐     Feature off     ┌────────────────┐
│ sales.customer_credit    │────────────────────►│ Skip credit    │
│ feature enabled?         │                     │ check          │
└────────┬─────────────────┘                     └────────────────┘
         │ Feature on
         ▼
┌──────────────────────────┐
│ Customer has            │     No limit        ┌────────────────┐
│ credit_limit set?       │────────────────────►│ Skip check     │
└────────┬─────────────────┘                     └────────────────┘
         │ Has limit
         ▼
┌──────────────────────────┐
│ CustomerCreditService::  │
│ checkLimit(customer,     │
│   total)                 │
└────────┬─────────────────┘
         │
    ┌────┴────┐
    │ allowed?│
    └────┬────┘
    Yes  │  No
    ┌────┴────────┐
    │             │
    ▼             ▼
┌────────┐  ┌──────────────────┐
│Continue│  │ Throw             │
│ sale   │  │ DomainException   │
└────────┘  │ "Credit limit     │
            │ exceeded"         │
            └──────────────────┘
```

### 1.5 Post-Checkout: Credit Debit + Loyalty Points

```
Sale created + payments processed
  │
  ▼
┌──────────────────────────┐     Paid in full     ┌────────────────┐
│ payment_status = 'paid'? │─────────────────────►│ No credit      │
└────────┬─────────────────┘                      │ debit needed   │
         │ Partial/Unpaid                          └────────────────┘
         ▼
┌──────────────────────────┐
│ CustomerCreditService::  │
│ addDebit(customer,       │
│   unpaid_amount,         │
│   'sale', sale_id)       │
└────────┬─────────────────┘
         │
         ▼
┌──────────────────────────┐     Feature off     ┌────────────────┐
│ customers.loyalty_points │────────────────────►│ Skip loyalty   │
│ feature enabled?         │                     └────────────────┘
└────────┬─────────────────┘
         │ Feature on
         ▼
┌──────────────────────────┐
│ LoyaltyService::         │
│ earnPoints(customer,     │
│   sale_total, 'sale',    │
│   sale_id)               │
└────────┬─────────────────┘
         │
         ▼
┌──────────────────────────┐
│ Return sale              │
└──────────────────────────┘
```

---

## 2. Hold / Recall Sale Flow

### 2.1 Hold Sale

```
┌─────────┐     ┌──────────┐     ┌──────────────┐     ┌────────────────┐
│  User   │────►│  POS UI  │────►│  HoldSale    │────►│  HoldSale      │
│  clicks │     │  serial- │     │  Controller  │     │  Service       │
│  Hold   │     │  izes    │     │  validates   │     │  hold()        │
│  (F4)   │     │  cart    │     │  input       │     │                │
└─────────┘     └──────────┘     └──────────────┘     └───────┬────────┘
                                                          │
                    ┌─────────────────────────────────────┘
                    │
                    ▼
              ┌───────────────┐     ┌──────────────┐     ┌──────────────┐
              │ Generate      │────►│ Create       │────►│ Return       │
              │ hold_number   │     │ held_sales   │     │ HeldSale     │
              │ HOLD-...-XXXX │     │ record       │     │              │
              └───────────────┘     │ + expires_at │     └──────────────┘
                                    └──────────────┘
                                          │
                                          ▼
              ┌──────────────┐     ┌──────────────┐
              │ Frontend     │◄────│ Response     │
              │ clears cart  │     │ 201 Created  │
              │ Shows toast  │     └──────────────┘
              └──────────────┘
```

### 2.2 Recall Sale

```
┌─────────┐     ┌──────────────┐     ┌────────────────┐     ┌────────────────┐
│  User   │────►│  HeldSales   │────►│  HoldSale      │────►│  HoldSale      │
│  clicks │     │  Modal       │     │  Controller    │     │  Service       │
│  Recall │     │  shows list  │     │  POST recall   │     │  recall()      │
└─────────┘     └──────────────┘     └────────────────┘     └───────┬────────┘
                                                                │
                    ┌───────────────────────────────────────────┘
                    │
                    ▼
              ┌───────────────┐     ┌──────────────┐     ┌──────────────┐
              │ Validate      │────►│ Mark as      │────►│ Return       │
              │ status='held' │     │ 'recalled'   │     │ cart_data    │
              │ not expired   │     │ set recalled │     │              │
              └───────────────┘     │ _at          │     └──────────────┘
                                    └──────────────┘
                                          │
                                          ▼
              ┌──────────────────┐     ┌──────────────┐
              │ Frontend         │◄────│ Response     │
              │ restores cart    │     │ 200 OK       │
              │ (items, customer,│     │ + cart_data  │
              │  discount, tax,  │     └──────────────┘
              │  notes, variants)│
              └──────────────────┘
```

### 2.3 Auto-Expiry

```
Scheduled command (daily or hourly)
  │
  ▼
┌──────────────────────────────┐
│ HoldSaleService::            │
│ processExpiry()              │
│                              │
│ SELECT * FROM held_sales     │
│ WHERE status = 'held'        │
│   AND expires_at < NOW()     │
└────────┬─────────────────────┘
         │
         ▼
┌──────────────────────────────┐
│ For each expired held sale:  │
│ Update status = 'expired'    │
└──────────────────────────────┘
```

---

## 3. Refund Flow

### 3.1 Full Refund

```
┌─────────┐     ┌──────────────┐     ┌────────────────┐     ┌────────────────┐
│  User   │────►│  RefundModal │────►│  Refund        │────►│  Refund        │
│  clicks │     │  selects     │     │  Controller    │     │  Service       │
│  Refund │     │  "Full       │     │  validates     │     │  processRefund│
│         │     │  Refund"     │     │                │     │                │
└─────────┘     │  + reason    │     └────────────────┘     └───────┬────────┘
                └──────────────┘                                    │
                    ┌───────────────────────────────────────────────┘
                    │
                    ▼
              ┌───────────────┐
              │ Validate sale │
              │ status =      │
              │ 'completed'   │
              └───────┬───────┘
                      │
                      ▼
              ┌───────────────┐     ┌──────────────┐
              │ DB::          │────►│ Create       │
              │ transaction   │     │ sale_refunds │
              └───────┬───────┘     │ (type=full)  │
                      │             └──────┬───────┘
                      ▼                    │
              ┌───────────────┐            ▼
              │ For each      │     ┌──────────────┐
              │ sale_item:    │────►│ Create       │
              │ increase      │     │ sale_refund  │
              │ inventory     │     │ _items       │
              │ (sale_return) │     └──────┬───────┘
              └───────┬───────┘            │
                      │                    ▼
                      ▼             ┌──────────────┐
              ┌───────────────┐     │ Update sale:  │
              │ PaymentService│     │ status=       │
              │ ::refundAll() │     │ 'refunded'    │
              └───────┬───────┘     │ refund_status │
                      │             │ ='full'       │
                      ▼             └──────┬───────┘
              ┌───────────────┐            │
              │ Mark payments │            ▼
              │ as 'refunded' │     ┌──────────────┐
              └───────┬───────┘     │ Return       │
                      │             │ SaleRefund   │
                      ▼             └──────────────┘
              ┌───────────────┐
              │ Commit        │
              │ transaction   │
              └───────────────┘
```

### 3.2 Partial Refund

```
┌─────────┐     ┌──────────────┐     ┌────────────────┐     ┌────────────────┐
│  User   │────►│  RefundModal │────►│  Refund        │────►│  Refund        │
│  clicks │     │  selects     │     │  Controller    │     │  Service       │
│  Refund │     │  items +     │     │  validates     │     │  processRefund│
│         │     │  quantities  │     │                │     │                │
└─────────┘     │  + reason    │     └────────────────┘     └───────┬────────┘
                └──────────────┘                                    │
                    ┌───────────────────────────────────────────────┘
                    │
                    ▼
              ┌───────────────┐
              │ Validate sale │
              │ status =      │
              │ 'completed'   │
              └───────┬───────┘
                      │
                      ▼
              ┌───────────────┐
              │ Validate each │
              │ refund qty <= │
              │ original qty  │
              └───────┬───────┘
                      │
                      ▼
              ┌───────────────┐
              │ Calculate     │
              │ refund_amount │
              │ = Σ(qty ×     │
              │    unit_price)│
              └───────┬───────┘
                      │
                      ▼
              ┌───────────────┐     ┌──────────────┐
              │ DB::          │────►│ Create       │
              │ transaction   │     │ sale_refunds │
              └───────┬───────┘     │ (type=partial)│
                      │             └──────┬───────┘
                      ▼                    │
              ┌───────────────┐            ▼
              │ For each      │     ┌──────────────┐
              │ refund item:  │────►│ Create       │
              │ increase      │     │ sale_refund  │
              │ inventory     │     │ _items       │
              │ (sale_return) │     └──────┬───────┘
              └───────┬───────┘            │
                      │                    ▼
                      ▼             ┌──────────────┐
              ┌───────────────┐     │ Update sale:  │
              │ Update        │     │ refund_status │
              │ payments:     │     │ ='partial'    │
              │ refund_amount │     │ refunded_amt  │
              │ refund_status │     │ += refund_amt │
              └───────┬───────┘     └──────┬───────┘
                      │                    │
                      ▼                    ▼
              ┌───────────────┐     ┌──────────────┐
              │ Commit        │     │ Return       │
              │ transaction   │     │ SaleRefund   │
              └───────────────┘     └──────────────┘
```

---

## 4. Discount Preset Flow

### 4.1 Apply Discount Preset at Checkout

```
┌─────────┐     ┌──────────────┐     ┌──────────────┐
│  User   │────►│  POS UI      │────►│  Cart store  │
│  clicks │     │  shows preset│     │  setDiscount │
│  preset │     │  buttons     │     │  (value)     │
│  button │     │              │     │              │
└─────────┘     └──────────────┘     └──────┬───────┘
                                            │
                                            ▼
                                    ┌──────────────┐
                                    │ Cart total   │
                                    │ recalculated │
                                    │ (discount    │
                                    │  applied)    │
                                    └──────────────┘
```

### 4.2 Discount Preset CRUD (Owner/Manager)

```
┌─────────┐     ┌──────────────────┐     ┌────────────────┐
│ Owner/  │────►│ DiscountPresets  │────►│ DiscountPreset │
│ Manager │     │ Page             │     │ Controller     │
│ manages │     │ CRUD UI          │     │                │
│ presets │     │                  │     │                │
└─────────┘     └──────────────────┘     └───────┬────────┘
                                                │
                                                ▼
                                        ┌────────────────┐
                                        │ DiscountPreset │
                                        │ Service        │
                                        │ (CRUD)         │
                                        └────────────────┘
```

---

## 5. Receipt Customization Flow

```
┌──────────────────────────────────────────────────────────┐
│ Store Settings Page (Owner)                              │
│                                                          │
│ ┌────────────────┐  ┌────────────────┐                  │
│ │ Header Text    │  │ Footer Text    │                  │
│ │ "Selamat Datang"│  │ "Barang yang   │                  │
│ │                │  │  dibeli tidak  │                  │
│ │                │  │  dapat ditukar"│                  │
│ └────────────────┘  └────────────────┘                  │
│ ┌────────────────┐  ┌────────────────┐                  │
│ │ Show Cashier   │  │ Show Customer  │                  │
│ │ [✓]            │  │ [✓]            │                  │
│ └────────────────┘  └────────────────┘                  │
│ ┌────────────────┐  ┌────────────────┐                  │
│ │ Paper Width    │  │ Show QR Code   │                  │
│ │ [80mm ▼]       │  │ [✓]            │                  │
│ └────────────────┘  └────────────────┘                  │
│                                                          │
│ Saves to: stores.receipt_settings (JSON)                 │
└──────────────────────────────────────────────────────────┘

After checkout:
  │
  ▼
┌──────────────────────────────────────────────────────────┐
│ Receipt Component                                         │
│                                                          │
│ Reads sale.store.receipt_settings                         │
│                                                          │
│ ┌────────────────────────────────────────┐               │
│ │ [Logo]                                 │               │
│ │      Selamat Datang                    │ ← header_text │
│ │      Store Name                        │               │
│ │      Sale Number, Date, Cashier        │ ← if show_    │
│ │                                        │    cashier    │
│ │ ───────────────────────────────        │               │
│ │ 2x Product A      @ 15.000   30.000   │               │
│ │ 1x Product B      @ 25.000   25.000   │               │
│ │ ───────────────────────────────        │               │
│ │ Subtotal                   55.000      │               │
│ │ Discount                   -5.000      │               │
│ │ Total                      50.000      │               │
│ │ ───────────────────────────────        │               │
│ │ Cash                       50.000      │               │
│ │ ───────────────────────────────        │               │
│ │ [QR Code]                              │ ← if show_    │
│ │                                        │    qr_code    │
│ │ Barang yang dibeli tidak dapat ditukar │ ← footer_text │
│ │ Terima kasih!                          │               │
│ └────────────────────────────────────────┘               │
└──────────────────────────────────────────────────────────┘
```

---

## 6. Keyboard Shortcuts Flow

```
┌─────────────────────────────────────────────────────────┐
│ POS Page (useKeyboardShortcuts hook)                    │
│                                                         │
│ F1  → Focus search input                                │
│ F2  → Open checkout modal (if cart not empty)           │
│ F3  → Focus customer select                             │
│ F4  → Hold sale (if pos.hold_sale enabled)              │
│ F9  → Show discount presets (if pos.discount_presets)   │
│ F12 → Clear cart / new transaction                      │
│ Enter (in search) → Add first product to cart           │
│ +   → Increment selected cart item                      │
│ -   → Decrement selected cart item                      │
│                                                         │
│ All shortcuts:                                          │
│ - Check feature flags before executing                  │
│ - Prevent default browser behavior                      │
│ - Single event listener (keydown)                       │
│ - Cleanup on component unmount                          │
└─────────────────────────────────────────────────────────┘
```

---

## 7. State Diagrams

### 7.1 Sale State Diagram (Enhanced)

```
                    ┌──────────┐
                    │  (none)  │
                    │  draft   │
                    └─────┬────┘
                          │ checkout
                          ▼
                    ┌──────────┐
        ┌───────────│ completed│───────────┐
        │           └──────────┘           │
        │ cancel              refund       │
        ▼                     (full)       ▼
  ┌──────────┐           ┌──────────┐
  │ cancelled│           │ refunded │
  └──────────┘           └──────────┘
                              ▲
                              │
                    ┌──────────┐
                    │ completed│
                    │ (partial │
                    │  refund) │
                    └──────────┘
```

### 7.2 Held Sale State Diagram

```
  ┌────────┐     hold     ┌────────┐     recall    ┌──────────┐
  │ (none) │─────────────►│  held  │─────────────►│ recalled │
  └────────┘              └────┬───┘              └──────────┘
                               │
                               │ expiry
                               ▼
                          ┌────────┐
                          │ expired│
                          └────────┘
```

### 7.3 Payment State Diagram (Enhanced)

```
  ┌────────┐     success     ┌────────┐     full refund   ┌──────────┐
  │ (none) │───────────────►│success │──────────────────►│ refunded │
  └────────┘                └────┬───┘                    └──────────┘
                                 │
                                 │ partial refund
                                 ▼
                           ┌────────┐
                           │partial │
                           │refund  │
                           └────────┘
```

---

*End of Phase 4 Flow*
