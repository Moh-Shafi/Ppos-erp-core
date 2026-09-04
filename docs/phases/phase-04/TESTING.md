# Phase 4 — POS Enhancement (ERP Integration) — Testing

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-13  
**Phase:** 4 — POS Enhancement (ERP Integration)  
**Depends On:** Phase 0, Phase 1, Phase 3

---

## 1. Testing Strategy

### 1.1 Test Layers

| Layer | Tool | Scope |
|-------|------|-------|
| Backend Unit | PHPUnit | Service-level logic (price resolution, credit check, refund calc) |
| Backend Feature | PHPUnit | API endpoints, middleware, RBAC, feature flags |
| Backend E2E | PHPUnit | Full flows (checkout → refund, hold → recall → checkout) |
| Frontend | TypeScript + Vite | Type safety, build verification |
| Regression | PHPUnit | All existing 1021 tests must pass |

### 1.2 Test File Structure

```
backend/tests/Feature/
├── Phase4TestHelper.php          — Shared setup (tenant, users, tokens, feature flags, test data)
├── Phase4MigrationTest.php       — Schema verification for new/modified tables
├── Phase4VariantCheckoutTest.php — Variant selection in POS checkout
├── Phase4PriceListTest.php       — Customer price list resolution at checkout
├── Phase4CreditLimitTest.php     — Credit limit enforcement at checkout
├── Phase4HoldSaleTest.php        — Hold/recall sale lifecycle
├── Phase4RefundTest.php          — Full and partial refund processing
├── Phase4DiscountPresetTest.php  — Discount preset CRUD + API
├── Phase4ReceiptSettingsTest.php — Receipt settings API
└── Phase4E2ETest.php             — End-to-end integration flows
```

---

## 2. Test Setup

### 2.1 Phase4TestHelper

```php
class Phase4TestHelper extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $owner;
    protected User $manager;
    protected User $cashier;
    protected User $staff;
    protected Store $store;
    protected Product $product;
    protected Product $productWithVariants;
    protected ProductVariant $variant;
    protected Customer $customer;
    protected PriceList $priceList;
    protected PriceListItem $priceListItem;
    protected string $tokenOwner;
    protected string $tokenManager;
    protected string $tokenCashier;
    protected string $tokenStaff;

    protected function setupPhase4(): void
    {
        // Run seeders
        $this->seed(ModuleSeeder::class);
        $this->seed(RbacSeeder::class);
        $this->seed(BusinessTypeSeeder::class);

        // Create tenant, users, tokens (same pattern as Phase3TestHelper)
        // Enable POS module + feature flags for tenant
        // Create store, product, product with variants, customer, price list
    }

    protected function enableFeature(string $slug): void { ... }
    protected function disableFeature(string $slug): void { ... }
    protected function authHeader(string $token): array { ... }
    protected function createProductWithVariants(): array { ... }
    protected function createCustomerWithPriceList(): array { ... }
    protected function createCompletedSale(): Sale { ... }
}
```

---

## 3. Test Cases

### 3.1 Phase4MigrationTest

| Test | Description | Assertions |
|------|-------------|------------|
| held_sales table exists | Check table creation | Table exists with expected columns |
| discount_presets table exists | Check table creation | Table exists with expected columns |
| sale_refunds table exists | Check table creation | Table exists with expected columns |
| sale_refund_items table exists | Check table creation | Table exists with expected columns |
| sales table has new columns | Check hold_status, held_at, refunded_amount, refund_status, price_list_id | Columns exist with correct types |
| sale_items table has new columns | Check variant_id, original_price | Columns exist with correct types |
| stores table has receipt_settings | Check receipt_settings column | JSON column exists |
| payments table has refund fields | Check refund_amount, refund_status | Columns exist with correct types |
| all new tables have tenant_id | Verify tenant isolation | All tables have tenant_id FK |

### 3.2 Phase4VariantCheckoutTest

| Test | Description | Setup | Expected |
|------|-------------|-------|----------|
| checkout with variant_id | Product has variants, checkout with variant_id | Product with 2 variants | Sale created, sale_item.variant_id set, unit_price from variant.price_override |
| checkout without variant for non-variant product | Product has_variants=false | Simple product | Sale created, sale_item.variant_id null |
| checkout with inactive variant | Variant is_active=false | Inactive variant | 422 error |
| checkout with variant not belonging to product | variant_id from different product | Two products with variants | 422 error |
| checkout variant uses product price when no override | Variant has price_override=null | Variant without override | unit_price = product.selling_price |
| checkout variant snapshots sku | Variant has different sku | Variant with sku | sale_item.sku = variant.sku |
| variant checkout api | Full API flow with variant | Auth token | 201 response with variant_id in items |

### 3.3 Phase4PriceListTest

| Test | Description | Setup | Expected |
|------|-------------|-------|----------|
| price list overrides product price | Customer has price_list_id, item in price list | Price list item: 12000 vs product 15000 | unit_price = 12000, original_price = 15000 |
| price list falls back to product price | Customer has price_list_id, product not in list | Price list without item for product | unit_price = product.selling_price |
| no price list uses product price | Customer has no price_list_id | Customer without price list | unit_price = product.selling_price |
| price list with variant | Price list item for specific variant | Price list item for variant | unit_price = price_list_item.price |
| price list snapshots original_price | Price list overrides price | Price list item | sale_item.original_price = product.selling_price |
| sale records price_list_id | Customer has price list | Checkout | sale.price_list_id = customer.price_list_id |
| price list api checkout | Full API flow | Auth token | 201 response with correct prices |

### 3.4 Phase4CreditLimitTest

| Test | Description | Setup | Expected |
|------|-------------|-------|----------|
| credit limit blocks sale | outstanding + total > limit | credit_limit=100k, balance=80k, total=30k | 422 "Credit limit exceeded" |
| credit limit allows within limit | outstanding + total <= limit | credit_limit=100k, balance=50k, total=30k | Sale completed |
| no credit limit allows any amount | credit_limit=null | No limit set | Sale completed |
| credit check only when feature enabled | Feature disabled, over limit | Feature off, balance=80k, limit=100k | Sale completed (no check) |
| unpaid sale adds debit | Partial payment, credit feature on | total=50k, paid=20k | outstanding_balance += 30k |
| full payment no debit | Full payment, credit feature on | total=50k, paid=50k | outstanding_balance unchanged |
| credit limit api | Full API flow | Auth token | Correct 422 or 201 response |

### 3.5 Phase4HoldSaleTest

| Test | Description | Setup | Expected |
|------|-------------|-------|----------|
| hold sale creates record | Cart with items | Items in cart | held_sales record created, hold_number generated |
| hold sale snapshots cart data | Cart with variant items | Variant in cart | cart_data JSON contains variant_id |
| recall sale restores cart | Held sale exists | Held sale | cart_data returned, status='recalled' |
| cannot recall expired sale | Held sale expired | expires_at in past | 422 error |
| cannot recall already recalled | Sale already recalled | status='recalled' | 422 error |
| list held sales for store | Multiple held sales | 2 held sales for store | Returns both, filtered by store |
| delete held sale | Held sale exists | Held sale | Record deleted, 204 response |
| auto-expire old held sales | Held sale past expiry | expires_at < now | processExpiry() marks as 'expired' |
| hold sale api | Full API flow | Auth token | 201 response |
| recall sale api | Full API flow | Auth token | 200 response with cart_data |
| hold sale feature flag off | Feature disabled | pos.hold_sale off | 403 response |
| cashier can hold sale | Cashier has pos.hold_sale | Cashier token | 201 response |
| staff cannot hold sale | Staff lacks pos.hold_sale | Staff token | 403 response |

### 3.6 Phase4RefundTest

| Test | Description | Setup | Expected |
|------|-------------|-------|----------|
| full refund restores inventory | Completed sale with 2 items | Sale with 2 items | Inventory restored for both, sale.status='refunded' |
| full refund marks payments refunded | Completed sale with payment | Sale with cash payment | Payment.status='refunded' |
| full refund creates sale_refund | Completed sale | Sale | sale_refunds record type='full' |
| partial refund restores specific items | Completed sale, refund 1 of 2 items | Sale with 2 items | Only refunded item inventory restored |
| partial refund adjusts sale total | Refund 1 item | Sale total=50k, refund 15k | sale.refunded_amount=15k, refund_status='partial' |
| cannot refund cancelled sale | Sale status='cancelled' | Cancelled sale | 422 error |
| cannot refund already refunded sale | Sale status='refunded' | Refunded sale | 422 error |
| refund qty exceeds original | Refund qty > sale_item qty | Sale item qty=2, refund qty=3 | 422 error |
| refund records refunded_by | Owner processes refund | Owner token | sale_refund.refunded_by = owner.id |
| refund records reason | Refund with reason | Reason text | sale_refund.refund_reason = text |
| refund api full | Full API flow | Auth token | 201 response |
| refund api partial | Partial API flow | Auth token | 201 response |
| cashier cannot refund | Cashier lacks pos.refund | Cashier token | 403 response |
| manager can refund | Manager has pos.refund | Manager token | 201 response |
| refund feature flag off | Feature disabled | pos.refund off | 403 response |

### 3.7 Phase4DiscountPresetTest

| Test | Description | Setup | Expected |
|------|-------------|-------|----------|
| create discount preset | Owner creates preset | Percentage preset | Record created |
| create fixed preset | Fixed amount preset | Fixed preset | Record created |
| update preset | Update name/value | Existing preset | Record updated |
| delete preset | Delete preset | Existing preset | Record deleted |
| list active presets | Active and inactive presets | 2 active, 1 inactive | Returns 2 active |
| percentage validation | Value > 100 | value=150 | 422 error |
| create api | Full API flow | Owner token | 201 response |
| update api | Full API flow | Owner token | 200 response |
| delete api | Full API flow | Owner token | 204 response |
| cashier cannot create | Cashier lacks permission | Cashier token | 403 response |
| feature flag off | Feature disabled | pos.discount_presets off | 403 response |

### 3.8 Phase4ReceiptSettingsTest

| Test | Description | Setup | Expected |
|------|-------------|-------|----------|
| get receipt settings | Store with settings | Store with receipt_settings | 200 response with settings |
| update receipt settings | Update header_text | New header | 200 response with updated settings |
| get settings for store without config | Store with null receipt_settings | Store without settings | 200 response with defaults/null |
| update api | Full API flow | Owner token | 200 response |
| cashier cannot update | Cashier lacks settings.manage | Cashier token | 403 response |

### 3.9 Phase4E2ETest

| Test | Description | Flow | Expected |
|------|-------------|------|----------|
| variant + price list + checkout | Full POS flow with variants and price list | Create product with variants → create customer with price list → checkout | Sale created with correct prices, variant_id, price_list_id |
| hold → recall → checkout | Hold/recall lifecycle | Add items → hold → recall → checkout | Sale created from recalled cart |
| checkout → full refund | Full refund flow | Checkout → refund full → verify inventory | Inventory restored, sale refunded |
| checkout → partial refund | Partial refund flow | Checkout with 2 items → refund 1 item → verify | Partial inventory restored, sale partially refunded |
| checkout with credit + loyalty | Credit sale with loyalty points | Customer with credit limit + loyalty enabled → checkout partial payment | Credit debit added, loyalty points earned |
| checkout → refund → verify no double refund | Prevent double refund | Checkout → full refund → attempt another refund | Second refund returns 422 |

---

## 4. Regression Tests

### 4.1 Existing Tests
All 1021 existing tests must pass without modification. Phase 4 changes are additive:
- `SaleService::checkout()` — enhanced but backward compatible (variant_id optional, price list optional)
- `Sale` model — new fields have defaults (hold_status='none', refund_status='none', refunded_amount=0)
- `SaleItem` model — new fields are nullable (variant_id, original_price)
- `Store` model — new field is nullable (receipt_settings)
- `Payment` model — new fields have defaults (refund_amount=0, refund_status='none')

### 4.2 Backward Compatibility
- Checkout without variant_id works exactly as before
- Checkout without customer price list works exactly as before
- Checkout without credit feature enabled works exactly as before
- No existing API response format changes (only new fields added)

---

## 5. Test Execution

### 5.1 Commands

```bash
# Phase 4 tests only
php artisan test --filter=Phase4

# Full regression
php artisan test

# Specific test class
php artisan test --filter=Phase4RefundTest

# With coverage
php artisan test --filter=Phase4 --coverage
```

### 5.2 Expected Results

| Suite | Tests | Assertions | Failures |
|-------|-------|------------|----------|
| Phase 4 (new) | ~80+ | ~200+ | 0 |
| Existing (regression) | 1021 | 2549 | 0 |
| **Total** | **~1100+** | **~2750+** | **0** |

---

## 6. Frontend Verification

### 6.1 TypeScript Compilation
```bash
cd frontend && npx tsc --noEmit
```
Expected: No errors

### 6.2 Vite Build
```bash
cd frontend && npx vite build
```
Expected: Build successful

### 6.3 Manual Verification Checklist
- [ ] POS page shows variant selection modal for products with variants
- [ ] POS page shows price list price when customer with price list is selected
- [ ] POS page shows credit limit warning when customer exceeds limit
- [ ] Hold button (F4) appears when pos.hold_sale feature enabled
- [ ] Held sales modal shows list and allows recall
- [ ] Refund button appears on sale detail when pos.refund enabled
- [ ] Refund modal allows full and partial refund
- [ ] Discount preset buttons appear in checkout when pos.discount_presets enabled
- [ ] Keyboard shortcuts work (F1, F2, F3, F4, F9, F12, Enter, +/-)
- [ ] Receipt renders with per-store customization

---

*End of Phase 4 Testing*
