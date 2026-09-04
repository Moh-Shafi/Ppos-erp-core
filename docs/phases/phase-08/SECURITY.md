# Phase 8 — Business-Specific Modules — Security

**Document Status:** DRAFT  
**Created:** 2026-08-16  
**Phase:** 8 — Business-Specific Modules  
**PDR Reference:** `docs/phases/phase-08/PDR.md`  
**Architecture Reference:** `docs/phases/phase-08/ARCHITECTURE.md`

---

## 1. TENANT ISOLATION

### 1.1 BelongsToTenant Trait

All Phase 8 models use the `BelongsToTenant` trait with global scope:
- `TableArea`, `RestaurantTable`, `Reservation`, `KotHeader`, `Modifier`, `Recipe`, `BillSplit`
- `Promotion`, `PromotionRule`, `LoyaltyProgram`, `LoyaltyTier`, `LoyaltyTransaction`, `PriceTagTemplate`
- `ServiceCatalog`, `StaffSchedule`, `Appointment`, `AppointmentService`

**Rule:** `tenant_id` is NEVER in `$fillable`, NEVER from request. Auto-set from `Auth::user()->tenant_id`.

### 1.2 Cross-Tenant Access Prevention

- All queries auto-filtered by `tenant_id` via global scope
- `withoutTenantScope()` used only in service-layer validation (same pattern as existing code)
- Every controller uses `Auth::user()->tenant_id` for scoping
- IDOR protection: all resource access checks `tenant_id` ownership

### 1.3 Store Authorization

Phase 8 endpoints that are store-scoped (tables, reservations, KOT, appointments) must validate the user's authorized store list. The `store_id` in requests is validated against the user's assigned stores, same pattern as Phase 7's `AuthorizedStoreScope`.

---

## 2. MODULE & FEATURE GATING

### 2.1 Module Middleware

Every Phase 8 route is wrapped in `module:{slug}` middleware:
- `module:tables` — tables and bill splitting routes
- `module:reservations` — reservation routes
- `module:kitchen` — KOT, KDS, modifiers, recipes routes
- `module:promotions` — promotion routes
- `module:loyalty` — loyalty program routes
- `module:pricetags` — price tag routes
- `module:appointments` — appointment, service catalog, staff schedule routes

**Rule:** If module is not enabled for tenant, API returns 403. Frontend hides navigation items.

### 2.2 Feature Middleware

Granular feature flags:
- `feature:tables.qr_ordering` — QR code generation
- `feature:recipes.auto_deduct` — automatic ingredient deduction
- `feature:promotions.buy_x_get_y` — BOGO promotion type
- `feature:loyalty.tiers` — tiered loyalty system
- `feature:appointments.recurring` — recurring appointments

### 2.3 Permission Middleware

Every route has `permission:{slug}` middleware. Permissions are module-scoped and assigned to roles via `RbacSeeder`.

---

## 3. INPUT VALIDATION

### 3.1 Modifier Validation

- Modifier selections in checkout are validated against the product's attached modifiers
- If a modifier is `is_required: true`, the checkout validation ensures it's present
- If a modifier type is `single`, only one option is allowed
- Option IDs are validated to belong to the specified modifier

### 3.2 Promotion Validation

- Promotion discounts are **always calculated server-side** — never trust client-calculated discount amounts
- `PromotionService::validate()` re-evaluates all rules server-side at checkout time
- Promotion `usage_limit` is checked atomically within the checkout transaction
- Expired promotions (end_date < today) are automatically excluded

### 3.3 Recipe Validation

- Recipe ingredient quantities must be positive decimals
- Ingredient products must belong to the same tenant
- Circular recipe references are prevented (a product cannot be an ingredient of its own recipe)
- Ingredient stock is checked before deduction (same `lockForUpdate` pattern as standard inventory)

### 3.4 Appointment Validation

- Appointment time slots are validated against staff schedules
- Double booking is prevented via time overlap detection
- Recurring appointments are validated for max series length (prevent infinite loops)
- Staff assignment validates that the user belongs to the same tenant and has an active role

### 3.5 Bill Split Validation

- Split amounts must sum to the sale total
- Sale item IDs in splits must belong to the referenced sale
- Customer IDs in per-person splits must belong to the same tenant
- Payment amounts per split must be positive

---

## 4. FINANCIAL INTEGRITY

### 4.1 Transaction Boundaries

All Phase 8 operations that modify financial data are within `DB::transaction()`:
- Checkout with modifiers: modifier deltas calculated within the existing checkout transaction
- Recipe deduction: within the checkout transaction (same `DB::transaction()`)
- Bill split payments: each split payment is a separate transaction linked to the sale
- Appointment completion (invoice generation): uses `SaleService::checkout()` which is already transactional
- Loyalty point earn/redeem: within the checkout transaction

### 4.2 Inventory Locking

Recipe ingredient deduction uses the same `lockForUpdate()` pattern as standard inventory:
```
Inventory::withoutTenantScope()
    ->where('tenant_id', $tenantId)
    ->where('store_id', $store->id)
    ->whereIn('product_id', $ingredientProductIds)
    ->lockForUpdate()
    ->get()
```

### 4.3 No Bypass of Core Accounting

Phase 8 modules do not create journal entries directly. All financial transactions flow through the existing `SaleService::checkout()` which triggers the existing accounting hooks from Phase 6. Promotions and loyalty redemptions adjust the sale discount, which is already handled by the accounting system.

---

## 5. QR CODE SECURITY

### 5.1 QR Token Generation

- QR code tokens are cryptographically random (32+ characters)
- Tokens are unique per table (`qr_code` column has UNIQUE constraint)
- QR code links to a public endpoint that only returns table number and store name — no sensitive data
- QR codes can be regenerated (old token invalidated)

### 5.2 QR Ordering (Future)

When customer-facing QR ordering is implemented:
- QR link will use a signed URL (Laravel signed routes)
- Order submission will require the signed URL token
- Rate limiting on QR ordering endpoint
- No access to other tenant data via QR token

---

## 6. KDS ACCESS CONTROL

### 6.1 KDS Visibility

- KDS queue is scoped by `tenant_id` and `store_id`
- Only users with `kds.view` permission can access the queue
- KDS does not expose customer payment information — only order items and modifiers
- KOT items show product name, quantity, modifiers, and notes — no pricing

### 6.2 KDS Status Updates

- Only users with `kds.manage` or `kitchen.manage` can update KOT status
- Status transitions are one-directional: new → preparing → ready → served
- Cancelled KOTs require `kitchen.manage` permission
- All status changes are logged (audit trail)

---

## 7. DATA BOUNDARIES

### 7.1 No Cross-Module Data Leakage

- Table data does not expose sale payment details
- KOT data does not expose sale pricing
- Appointment data does not expose other customers' appointments (only those the user has permission to view)
- Loyalty transactions are scoped per customer; a cashier cannot view another customer's loyalty balance without `loyalty.view` permission

### 7.2 Service Catalog Isolation

- Service catalog entries are tenant-scoped
- Services are products with `is_service = true` — same tenant isolation as products
- Staff schedules are tenant-scoped and only visible to users with `staff.schedule.view` permission

### 7.3 Promotion Data Protection

- Promotion rules and conditions are not exposed to customers
- `POST /promotions/validate` returns only applicable promotion names and discount amounts — not rule logic
- Usage counts are internal — only visible to users with `promotions.view` permission

---

## 8. SECURITY TEST CHECKLIST

| Check | Method |
|-------|--------|
| Tenant A cannot access Tenant B's tables | API test with cross-tenant IDs |
| Tenant without `tables` module gets 403 | API test with module disabled |
| Cashier cannot create tables (no tables.manage) | API test with cashier role |
| Modifier selections validated against product's modifiers | Unit test with invalid modifier IDs |
| Promotion discount calculated server-side | Unit test: client sends different discount, server recalculates |
| Recipe ingredient stock checked before deduction | Unit test with insufficient ingredient stock |
| Appointment double booking prevented | Unit test with overlapping time slots |
| Bill split amounts must sum to sale total | Unit test with mismatched amounts |
| KDS queue does not show pricing | API test: inspect KDS response |
| QR code token is unique and random | Unit test: generate 1000 QR codes, assert uniqueness |
| Loyalty redemption checks balance | Unit test: redeem more points than available |
| Staff schedule belongs to tenant | API test with cross-tenant user_id |

---

*End of Phase 8 Security — DRAFT*
