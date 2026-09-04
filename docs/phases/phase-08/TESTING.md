# Phase 8 — Business-Specific Modules — Testing

**Document Status:** DRAFT  
**Created:** 2026-08-16  
**Phase:** 8 — Business-Specific Modules  
**PDR Reference:** `docs/phases/phase-08/PDR.md`

---

## 1. TESTING STRATEGY

### 1.1 Layers

| Layer | Tool | Scope |
|-------|------|-------|
| Backend Feature Tests | PHPUnit | API endpoints, service logic, validation, RBAC |
| Backend Unit Tests | PHPUnit | Service methods, calculation logic |
| Frontend E2E | Playwright | User flows, UI rendering, module gating |
| Regression | PHPUnit + Playwright | All existing tests must remain green |

### 1.2 Test Database

All backend tests run against `pos_saas_testing` database (existing pattern). Each test class uses `RefreshDatabase` trait.

### 1.3 Test Data

`E2ESeeder` is extended with Phase 8 test data:
- Restaurant: 2 table areas, 6 tables, 2 reservations, 3 modifiers, 2 recipes
- Retail: 2 promotions, 1 loyalty program with 2 tiers, 1 price tag template
- Service: 3 services, 2 staff schedules, 3 appointments

---

## 2. BACKEND TEST FILES

### 2.1 8A Restaurant

```
tests/Feature/Restaurant/
  ├── TableAreaTest.php
  ├── TableTest.php
  ├── TableStatusTest.php
  ├── TableQrCodeTest.php
  ├── ReservationTest.php
  ├── ReservationAvailabilityTest.php
  ├── ReservationStatusFlowTest.php
  ├── KotTest.php
  ├── KotStatusTest.php
  ├── KdsQueueTest.php
  ├── ModifierTest.php
  ├── ModifierOptionTest.php
  ├── ProductModifierTest.php
  ├── ModifierCheckoutTest.php
  ├── RecipeTest.php
  ├── RecipeIngredientTest.php
  ├── RecipeDeductionTest.php
  ├── BillSplitTest.php
  ├── BillSplitPaymentTest.php
  └── RestaurantCheckoutIntegrationTest.php
```

### 2.2 8B Retail

```
tests/Feature/Retail/
  ├── PromotionTest.php
  ├── PromotionRuleTest.php
  ├── PromotionValidationTest.php
  ├── PromotionCheckoutTest.php
  ├── PromotionExpiryTest.php
  ├── LoyaltyProgramTest.php
  ├── LoyaltyTierTest.php
  ├── LoyaltyTransactionTest.php
  ├── LoyaltyRedemptionTest.php
  ├── PriceTagTemplateTest.php
  └── PriceTagGenerationTest.php
```

### 2.3 8C Service

```
tests/Feature/Service/
  ├── ServiceCatalogTest.php
  ├── StaffScheduleTest.php
  ├── StaffAvailabilityTest.php
  ├── AppointmentTest.php
  ├── AppointmentStatusFlowTest.php
  ├── AppointmentDoubleBookingTest.php
  ├── AppointmentCalendarTest.php
  ├── AppointmentCheckoutTest.php
  └── RecurringAppointmentTest.php
```

### 2.4 Cross-Cutting

```
tests/Feature/Phase8/
  ├── ModuleGatingTest.php          — module/feature middleware enforcement
  ├── PermissionGatingTest.php      — role-based access control
  ├── TenantIsolationTest.php       — cross-tenant access prevention
  ├── CheckoutExtensionTest.php     — extended checkout with all hooks
  └── RegressionTest.php            — existing tests still pass
```

---

## 3. KEY TEST SCENARIOS

### 3.1 Restaurant

| Test | Key Assertions |
|------|----------------|
| Table CRUD | Create, update, delete table; area assignment |
| Table status | available → occupied → cleaning → available cycle |
| QR code generation | Unique token, regeneratable |
| Reservation create | Customer, date, time, party_size |
| Reservation availability | Excludes occupied tables for requested time |
| Reservation status flow | pending → confirmed → seated → completed |
| KOT generation | Auto-created on checkout with kitchen module |
| KOT status flow | new → preparing → ready → served |
| KDS queue | Sorted by priority then created_at |
| Modifier CRUD | Create modifier with options, attach to product |
| Modifier checkout | Price delta calculated, stored in metadata |
| Required modifier | Checkout fails if required modifier not selected |
| Recipe CRUD | Create recipe with ingredients |
| Recipe deduction | Ingredient stock deducted, product stock not deducted |
| Insufficient ingredient | Checkout fails with clear error |
| Bill split equal | Splits total evenly by person count |
| Bill split per item | Items assigned to specific splits |
| Bill split payment | Each split paid independently |

### 3.2 Retail

| Test | Key Assertions |
|------|----------------|
| Promotion CRUD | Create with rules, activate/deactivate |
| Promotion validation | Correct discount calculated for cart |
| Promotion expiry | Expired promotion excluded |
| Promotion usage limit | Usage count incremented, limit enforced |
| Buy X get Y | Free item calculated correctly |
| Tiered promotion | Correct tier applied based on cart value |
| Loyalty program CRUD | Create program with tiers |
| Loyalty earn | Points calculated from paid amount |
| Loyalty redeem | Points deducted, discount applied |
| Insufficient points | Redemption fails with error |
| Tier upgrade | Customer tier updated when points threshold crossed |
| Price tag template | CRUD operations |
| Price tag PDF generation | PDF returned, correct products included |

### 3.3 Service

| Test | Key Assertions |
|------|----------------|
| Service catalog CRUD | Create service (product with is_service) |
| Staff schedule CRUD | Create, update, delete schedules |
| Staff availability | Correct free slots calculated |
| Appointment create | Customer, services, staff, time |
| Appointment double booking | Overlapping appointment rejected |
| Appointment status flow | pending → confirmed → in_progress → completed |
| Appointment completion | Sale created, linked to appointment |
| Appointment calendar | Correct date range, appointments returned |
| Recurring appointment | Series generated at correct interval |

### 3.4 Cross-Cutting

| Test | Key Assertions |
|------|----------------|
| Module gating | 403 when module not enabled |
| Feature gating | 403 when feature not enabled |
| Permission gating | 403 when permission not in role |
| Tenant isolation | Cross-tenant access returns 404 |
| Checkout with all hooks | Table linked, KOT generated, recipe deducted, promotion applied, loyalty earned |
| Existing tests pass | 1138+ tests still green |

---

## 4. E2E TEST FILES

```
e2e/
  ├── phase8-restaurant.spec.ts
  ├── phase8-retail.spec.ts
  └── phase8-service.spec.ts
```

### 4.1 E2E Test Scenarios

**Restaurant:**
- Login as owner → navigate to Tables → create table area → create tables → verify in list
- Navigate to Reservations → create reservation → confirm → seat → complete
- Navigate to KDS → verify KOT queue displays → advance status
- POS checkout with table → verify table status changes → KOT appears in KDS
- Bill split: checkout → split bill → pay each split

**Retail:**
- Login as owner → create promotion → activate → verify in list
- POS checkout with promotion → verify discount applied
- Create loyalty program → create tiers → checkout with loyalty customer → verify points earned
- Redeem loyalty points at checkout

**Service:**
- Login as owner → create service → create staff schedule
- Create appointment → confirm → start → complete → verify invoice generated
- Check calendar view → verify appointments displayed
- Attempt double booking → verify error

---

## 5. REGRESSION GATE

### 5.1 Pre-Phase 8 Baseline

- Backend: 1138 tests / 2840 assertions (Phase 7 CLOSED)
- Frontend E2E: 3 tests (Phase 7)
- Frontend build: 188 modules

### 5.2 Post-Phase 8 Criteria

| Check | Target |
|-------|--------|
| Backend tests | 1138 + new Phase 8 tests, ALL pass |
| Frontend E2E | 3 + new Phase 8 E2E tests, ALL pass |
| Frontend build | Success, no errors |
| Phase 7 tests | Still pass (no regression) |
| Phase 6 tests | Still pass (no regression) |
| Exit code | 0 |

### 5.3 Regression Command

```bash
docker exec pos_saas_backend php artisan test
```

---

## 6. PERFORMANCE CONSIDERATIONS

| Concern | Mitigation |
|---------|------------|
| KDS polling (5s) | Lightweight query, indexed by (tenant_id, store_id, status) |
| Promotion validation | Cache active promotions per tenant (in-memory) |
| Recipe deduction | Within checkout transaction, uses lockForUpdate (same as standard) |
| Appointment availability | Indexed queries on (tenant_id, user_id, appointment_date) |
| Bill split calculation | In-memory calculation, no heavy queries |

---

*End of Phase 8 Testing — DRAFT*
