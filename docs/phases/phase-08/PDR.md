# Phase 8 — Business-Specific Modules — PDR

**Document Status:** DRAFT  
**Created:** 2026-08-16  
**Phase:** 8 — Business-Specific Modules  
**Depends On:** Phase 4 (POS Enhancement), Phase 7 (Reports & Analytics)  
**Roadmap Reference:** `docs/PDR/02-PHASE_ROADMAP.md` — Phase 8

---

## 1. OBJECTIVE

Implement business-type-specific modules that differentiate the ERP platform across restaurant, retail, and service business types. These modules build on the shared ERP foundation (multi-tenant, RBAC, module/feature system, POS, inventory, accounting) to deliver specialized operational capabilities without duplicating core logic.

### 1.1 In Scope

**8A — Restaurant Modules:**
- Table management (floor areas, table status, QR code per table)
- Reservations (booking, time slots, table assignment, walk-in vs reserved)
- Kitchen Order Tickets (KOT) — order routing from POS to kitchen
- Kitchen Display System (KDS) — order queue, status tracking, readiness notifications
- Modifiers / add-ons (size, spice level, extra toppings) with price deltas
- Recipes (ingredients per menu item → automatic stock deduction on sale)
- Bill splitting (per table, per person, per item)

**8B — Retail Modules:**
- Barcode scanning integration (camera + USB scanner support)
- Quick sale mode (scan → add to cart → checkout)
- Price tag / label printing
- Promotional pricing (buy X get Y, percentage off, fixed amount off, tiered discounts)
- Loyalty program (points per purchase, redemption at checkout)

**8C — Service Business Modules:**
- Appointment scheduling (calendar view, time slots, recurring bookings)
- Staff assignment (service provider selection, availability checking)
- Service catalog (services as products with duration, pricing)
- Invoice generation (from appointment → invoice → payment)

### 1.2 Out of Scope (Deferred)

- Hotel reservations (room management, check-in/check-out) — future phase
- Pharmacy compliance (prescription tracking, controlled substances) — future phase
- Manufacturing / MRP — future phase
- Kitchen printer hardware integration (ESC/POS) — Phase 11
- Offline mode for any module — Phase 11
- Advanced loyalty tiers and gamification — future enhancement
- Third-party delivery platform integration (GrabFood, GoFood) — future

### 1.3 Guiding Principle

> Business-specific modules are **feature-flagged add-ons** on top of the shared ERP foundation. They must not modify core domain models (Sale, Product, Inventory) but may extend them via polymorphic relations, pivot tables, or optional columns. A tenant without a module enabled must experience zero impact on existing functionality.

---

## 2. SHARED FOUNDATION ANALYSIS

### 2.1 What Is Shared Across 8A/8B/8C

| Capability | Used By | Implementation |
|-----------|---------|----------------|
| Product/Category catalog | 8A (menu items), 8B (products), 8C (services) | Existing `products` + `categories` tables; services use `is_service` flag |
| POS checkout | 8A (table orders), 8B (quick sale), 8C (appointment checkout) | Existing `SaleService::checkout()` extended with hooks |
| Inventory deduction | 8A (recipe auto-deduction), 8B (standard) | Existing `InventoryService`; recipe extends with ingredient mapping |
| Customer management | 8A (reservations), 8B (loyalty), 8C (appointments) | Existing `customers` table |
| Payments | All three | Existing payment infrastructure |
| Journal entries | All three | Phase 6 accounting; no new journal types needed |
| Module/feature flags | All three | Existing module registry; modules already seeded |
| RBAC permissions | All three | Existing permission system extended with new module permissions |

### 2.2 What Is Business-Type-Specific

| 8A Restaurant | 8B Retail | 8C Service |
|--------------|-----------|------------|
| Tables & floor areas | Barcode scanning | Appointments |
| KOT / KDS | Quick sale mode | Staff schedules |
| Modifiers | Price tag printing | Service catalog (duration-based) |
| Recipes / ingredient mapping | Promotions engine | Invoice from appointment |
| Bill splitting | Loyalty program | |

### 2.3 Module Dependencies

```
8A Restaurant:
  tables → pos, customers
  reservations → tables, customers
  kitchen → pos, inventory
  kds → kitchen

8B Retail:
  barcode → pos, inventory (already registered)
  promotions → pos, sales
  loyalty → customers, sales (extends existing customers.loyalty_points feature)

8C Service:
  appointments → customers
  service_catalog → core (products with is_service)
```

---

## 3. ARCHITECTURE OVERVIEW

```
Shared ERP Foundation (Phases 0-7)
  │
  ├── Product/Catalog (shared)
  ├── POS/Sales (shared, extended)
  ├── Inventory (shared, extended for recipes)
  ├── Customers (shared)
  ├── Payments (shared)
  ├── Accounting (shared)
  ├── Reports (shared)
  │
  ├── 8A Restaurant Layer
  │     ├── Tables (floor plan, status, QR)
  │     ├── Reservations (booking, slots)
  │     ├── KOT (order tickets → kitchen)
  │     ├── KDS (display queue, status)
  │     ├── Modifiers (product add-ons)
  │     ├── Recipes (ingredient → stock deduction)
  │     └── Bill Splitting (per table/person/item)
  │
  ├── 8B Retail Layer
  │     ├── Barcode (scan → cart)
  │     ├── Quick Sale (streamlined checkout)
  │     ├── Price Tags (print labels)
  │     ├── Promotions (rules engine)
  │     └── Loyalty Program (points, tiers, redemption)
  │
  └── 8C Service Layer
        ├── Appointments (calendar, slots, recurring)
        ├── Staff Schedules (availability, assignment)
        ├── Service Catalog (duration-based services)
        └── Invoice Generation (appointment → invoice)
```

### 3.1 Design Rules

1. **No core table modifications** — new modules use their own tables with FK references to core tables
2. **Polymorphic relations** for cross-module references (e.g., `saleable_type`/`saleable_id` on tables)
3. **Feature-flagged** — every module's routes are wrapped in `feature:` or `module:` middleware
4. **POS extension via hooks** — `SaleService::checkout()` accepts optional `metadata` for table_id, appointment_id, promotion_ids
5. **Recipe deduction** — after checkout, if product has a recipe, deduct ingredient stock (not the product stock itself for manufactured items)
6. **Modifier pricing** — modifiers add price deltas to sale items, calculated at checkout time

---

## 4. DATABASE CHANGES

### 4.1 8A Restaurant — New Tables

```sql
-- Floor areas (sections of the restaurant)
table_areas
  id, tenant_id, store_id, name, sort_order, timestamps

-- Tables (physical tables in the restaurant)
tables
  id, tenant_id, store_id, table_area_id, name, code, capacity,
  status (available, occupied, reserved, cleaning),
  qr_code (unique token for QR ordering), timestamps

-- Reservations
reservations
  id, tenant_id, store_id, customer_id, table_id (nullable),
  reservation_date, start_time, end_time, party_size,
  status (pending, confirmed, seated, completed, cancelled, no_show),
  notes, timestamps

-- Kitchen Order Tickets
kot_headers
  id, tenant_id, store_id, sale_id (nullable), table_id (nullable),
  kot_number, status (new, preparing, ready, served, cancelled),
  priority (normal, rush), created_by, timestamps

-- KOT items (linked to sale items)
kot_items
  id, kot_header_id, sale_item_id, product_id, quantity,
  modifiers (JSON), notes, status (queued, preparing, ready, served),
  timestamps

-- Modifiers (e.g., "Size", "Spice Level")
modifiers
  id, tenant_id, name, type (single, multiple), is_required, timestamps

-- Modifier options (e.g., "Large +Rp 5000", "Extra Spicy")
modifier_options
  id, modifier_id, name, price_delta, sort_order, timestamps

-- Product-Modifier mapping
product_modifiers
  id, product_id, modifier_id, timestamps

-- Recipes (ingredient list for a product)
recipes
  id, tenant_id, product_id, yield_quantity, yield_unit_id, timestamps

-- Recipe ingredients (items consumed when recipe is produced)
recipe_ingredients
  id, recipe_id, ingredient_product_id, quantity, unit_id, timestamps

-- Bill splits (for splitting a table's bill)
bill_splits
  id, tenant_id, sale_id, split_type (equal, per_item, per_person, custom),
  total_amount, status (pending, paid), timestamps

-- Bill split items
bill_split_items
  id, bill_split_id, sale_item_id (nullable), customer_id (nullable),
  amount, timestamps
```

### 4.2 8B Retail — New Tables

```sql
-- Promotions (marketing campaigns)
promotions
  id, tenant_id, name, description, type (percentage, fixed, buy_x_get_y, tiered),
  value, start_date, end_date, is_active, usage_limit, usage_count,
  conditions (JSON), timestamps

-- Promotion rules (detailed rules for complex promotions)
promotion_rules
  id, promotion_id, rule_type (min_purchase, min_quantity, category, product, customer_group),
  rule_value (JSON), timestamps

-- Loyalty programs (tiered loyalty system)
loyalty_programs
  id, tenant_id, name, points_per_currency (e.g., 1 point per Rp 10,000),
  is_active, timestamps

-- Loyalty tiers (e.g., Silver, Gold, Platinum)
loyalty_tiers
  id, loyalty_program_id, name, min_points, discount_percentage, timestamps

-- Loyalty transactions (points accrual and redemption)
loyalty_transactions
  id, tenant_id, customer_id, loyalty_program_id, type (earn, redeem, expire, adjust),
  points, balance_after, reference_type, reference_id, notes, timestamps

-- Price tag templates
price_tag_templates
  id, tenant_id, name, layout (JSON: paper size, fields, font sizes), timestamps
```

### 4.3 8C Service — New Tables

```sql
-- Service catalog (extends products with service-specific fields)
-- Uses products table with is_service = true
-- Additional fields stored in:

service_catalog
  id, tenant_id, product_id, duration_minutes, is_recurring,
  recurring_interval (daily, weekly, monthly), buffer_time_minutes,
  timestamps

-- Staff schedules (availability per staff member)
staff_schedules
  id, tenant_id, user_id, day_of_week (0-6), start_time, end_time,
  is_available, effective_from, effective_until, timestamps

-- Appointments
appointments
  id, tenant_id, store_id, customer_id, user_id (assigned staff),
  service_catalog_id, appointment_date, start_time, end_time,
  status (pending, confirmed, in_progress, completed, cancelled, no_show),
  notes, sale_id (nullable, linked after checkout), timestamps

-- Appointment services (multiple services per appointment)
appointment_services
  id, appointment_id, service_catalog_id, duration_minutes, price, timestamps
```

### 4.4 Modified Tables

- `products` — add `is_service` boolean (default false) for 8C service catalog
- `sale_items` — add `metadata` JSON column for modifier details, table_id, appointment_id
- `sales` — add `table_id` nullable FK, `appointment_id` nullable FK (polymorphic context)

### 4.5 Indexes

- `tables`: (tenant_id, store_id, status)
- `reservations`: (tenant_id, store_id, reservation_date, status)
- `kot_headers`: (tenant_id, store_id, status, created_at)
- `promotions`: (tenant_id, is_active, start_date, end_date)
- `loyalty_transactions`: (tenant_id, customer_id, created_at)
- `appointments`: (tenant_id, store_id, appointment_date, status)

---

## 5. API DESIGN

### 5.1 8A Restaurant

```
Base: /api/v1

# Tables
GET    /tables                          — list tables (filter by area, status)
POST   /tables                          — create table
GET    /tables/{id}                     — show table
PUT    /tables/{id}                     — update table
DELETE /tables/{id}                     — delete table
POST   /tables/{id}/status              — change status (available, occupied, cleaning)
GET    /tables/areas                    — list floor areas
POST   /tables/areas                    — create area
PUT    /tables/areas/{id}               — update area
DELETE /tables/areas/{id}               — delete area

# Reservations
GET    /reservations                    — list (filter by date, status)
POST   /reservations                    — create reservation
GET    /reservations/{id}               — show reservation
PUT    /reservations/{id}               — update reservation
POST   /reservations/{id}/confirm       — confirm reservation
POST   /reservations/{id}/seat          — seat customer (link to table)
POST   /reservations/{id}/complete      — complete reservation
POST   /reservations/{id}/cancel        — cancel reservation
GET    /reservations/availability       — check availability (date, time, party_size)

# KOT / KDS
GET    /kot                             — list KOTs (filter by status)
GET    /kot/{id}                        — show KOT with items
POST   /kot/{saleId}/generate           — generate KOT from sale
POST   /kot/{id}/status                 — update KOT status (preparing, ready, served)
POST   /kot/items/{itemId}/status       — update individual item status
GET    /kds/queue                       — get KDS queue (sorted by priority/time)

# Modifiers
GET    /modifiers                       — list modifiers
POST   /modifiers                       — create modifier
PUT    /modifiers/{id}                  — update modifier
DELETE /modifiers/{id}                  — delete modifier
POST   /modifiers/{id}/options          — add option
PUT    /modifiers/{id}/options/{optId}  — update option
DELETE /modifiers/{id}/options/{optId}  — delete option
GET    /products/{id}/modifiers         — list modifiers for product
POST   /products/{id}/modifiers         — attach modifier to product

# Recipes
GET    /recipes                         — list recipes
POST   /recipes                         — create recipe (with ingredients)
GET    /recipes/{id}                    — show recipe with ingredients
PUT    /recipes/{id}                    — update recipe
DELETE /recipes/{id}                    — delete recipe
POST   /recipes/{id}/ingredients        — add ingredient
PUT    /recipes/{id}/ingredients/{ingId} — update ingredient
DELETE /recipes/{id}/ingredients/{ingId} — remove ingredient

# Bill Splitting
POST   /sales/{id}/split                — create bill split
GET    /sales/{id}/splits               — list splits for sale
POST   /splits/{id}/pay                 — process payment for a split
```

### 5.2 8B Retail

```
# Promotions
GET    /promotions                      — list promotions
POST   /promotions                      — create promotion
GET    /promotions/{id}                 — show promotion with rules
PUT    /promotions/{id}                 — update promotion
DELETE /promotions/{id}                 — delete promotion
POST   /promotions/{id}/activate        — activate promotion
POST   /promotions/{id}/deactivate      — deactivate promotion
POST   /promotions/validate             — validate cart against active promotions

# Loyalty
GET    /loyalty/programs                — list programs
POST   /loyalty/programs                — create program
PUT    /loyalty/programs/{id}           — update program
GET    /loyalty/tiers                   — list tiers
POST   /loyalty/tiers                   — create tier
GET    /loyalty/customers/{id}/balance  — get customer points balance
GET    /loyalty/customers/{id}/transactions — get transaction history
POST   /loyalty/redeem                  — redeem points at checkout

# Price Tags
GET    /price-tags/templates            — list templates
POST   /price-tags/templates            — create template
POST   /price-tags/generate             — generate price tags (product_ids, template_id)
```

### 5.3 8C Service

```
# Service Catalog
GET    /services                        — list services (products with is_service)
POST   /services                        — create service
PUT    /services/{id}                   — update service
DELETE /services/{id}                   — delete service

# Staff Schedules
GET    /staff/schedules                 — list schedules (filter by user, day)
POST   /staff/schedules                 — create schedule
PUT    /staff/schedules/{id}            — update schedule
DELETE /staff/schedules/{id}            — delete schedule
GET    /staff/availability              — check availability (date, service_id)

# Appointments
GET    /appointments                    — list appointments (filter by date, staff, status)
POST   /appointments                    — create appointment
GET    /appointments/{id}               — show appointment
PUT    /appointments/{id}               — update appointment
POST   /appointments/{id}/confirm       — confirm appointment
POST   /appointments/{id}/start         — start service (in_progress)
POST   /appointments/{id}/complete      — complete appointment (generate invoice)
POST   /appointments/{id}/cancel        — cancel appointment
GET    /appointments/calendar           — calendar view (month/week/day)
```

---

## 6. RBAC & FEATURES

### 6.1 New Permissions

**8A Restaurant:**
- `tables.view`, `tables.manage`
- `reservations.view`, `reservations.manage`
- `kitchen.view`, `kitchen.manage`
- `kds.view`, `kds.manage`
- `modifiers.view`, `modifiers.manage`
- `recipes.view`, `recipes.manage`
- `billsplit.view`, `billsplit.manage`

**8B Retail:**
- `promotions.view`, `promotions.manage`
- `loyalty.view`, `loyalty.manage`
- `pricetags.view`, `pricetags.manage`

**8C Service:**
- `services.view`, `services.manage`
- `appointments.view`, `appointments.manage`
- `staff.schedule.view`, `staff.schedule.manage`

### 6.2 New Feature Flags

**8A Restaurant:**
- `tables.qr_ordering` — QR code ordering
- `kitchen.auto_kot` — auto-generate KOT on sale
- `recipes.auto_deduct` — auto stock deduction from recipes
- `billsplit.per_person`, `billsplit.per_item`

**8B Retail:**
- `promotions.buy_x_get_y` — BOGO promotion type
- `promotions.tiered` — tiered discount promotions
- `loyalty.tiers` — tiered loyalty system

**8C Service:**
- `appointments.recurring` — recurring booking support
- `appointments.reminders` — reminder notifications (future)

### 6.3 Module-Permission Mapping

All new permissions are mapped to their respective modules in the `RbacSeeder`. The Owner role gets all permissions. Manager gets view + manage for operational modules. Cashier gets view + limited manage (tables, KDS, appointments). Staff gets view only.

---

## 7. FRONTEND UX

### 7.1 8A Restaurant

- **Floor Plan View**: visual grid of tables with color-coded status (green=available, red=occupied, yellow=reserved, gray=cleaning)
- **Table Detail**: current order, timer, party size, server assigned
- **Reservation Calendar**: day/week view with time slots, drag-to-assign table
- **KDS Screen**: full-screen queue view (auto-refresh), columns by status (New → Preparing → Ready), tap to advance status
- **Modifier Selection**: POS integration — when adding product with modifiers, show modal with options
- **Recipe Viewer**: admin view showing ingredient list and cost calculation
- **Bill Split Modal**: visual split interface — drag items to split groups, show per-person totals

### 7.2 8B Retail

- **Barcode Scan Mode**: full-screen scan interface, auto-add to cart, quick checkout button
- **Promotion Manager**: list view with active/inactive toggle, rule builder interface
- **Loyalty Dashboard**: customer points overview, tier distribution, redemption stats
- **Price Tag Print**: product selection → template selection → print preview → generate PDF

### 7.3 8C Service

- **Appointment Calendar**: month/week/day views with color-coded status
- **Staff Schedule Grid**: weekly grid with availability blocks
- **Service Catalog**: card-based listing with duration, price, category
- **Appointment Detail**: customer info, service history, checkout button

### 7.4 Navigation

New nav items are added to the sidebar, gated by module + permission:
- Restaurant: Tables, Reservations, Kitchen (KDS)
- Retail: Promotions, Loyalty, Price Tags
- Service: Appointments, Service Catalog, Staff Schedules

---

## 8. IMPLEMENTATION ORDER

### Phase 8 — Complete Cycle

1. **Backend: Migrations** — all new tables for 8A, 8B, 8C
2. **Backend: Models** — Eloquent models with relationships
3. **Backend: Services** — business logic (TableService, ReservationService, KotService, KdsService, ModifierService, RecipeService, BillSplitService, PromotionService, LoyaltyService, PriceTagService, AppointmentService, ServiceCatalogService, StaffScheduleService)
4. **Backend: Controllers + Routes** — REST endpoints with module/feature/permission middleware
5. **Backend: Seeders** — RbacSeeder updates, ModuleSeeder feature flags, E2ESeeder test data
6. **Backend: POS Integration** — SaleService hooks for table_id, modifiers, recipe deduction, promotions
7. **Backend: Tests** — feature tests for all modules
8. **Frontend: Pages** — all React pages for 8A, 8B, 8C
9. **Frontend: Services** — API clients
10. **Frontend: Navigation** — module-aware nav items
11. **E2E Tests** — Playwright specs for primary flows
12. **Full Regression** — backend + frontend
13. **Security Audit** — tenant isolation, module gating, permission enforcement
14. **Documentation** — finalize all docs
15. **Phase 8 CLOSED**

---

## 9. ACCEPTANCE CRITERIA

### 8A Restaurant
- [ ] Tables CRUD with floor areas and status management
- [ ] QR code generated per table
- [ ] Reservations CRUD with availability checking
- [ ] KOT auto-generated from POS sale (when kitchen module enabled)
- [ ] KDS queue displays and updates status in real-time
- [ ] Modifiers attached to products, selectable in POS
- [ ] Modifier price deltas calculated correctly at checkout
- [ ] Recipes with ingredients, auto-deduct on sale
- [ ] Bill splitting works (equal, per-item, per-person)
- [ ] Module/feature flags gate all functionality

### 8B Retail
- [ ] Barcode scan adds product to cart
- [ ] Quick sale mode streamlines checkout
- [ ] Promotions CRUD with rule builder
- [ ] Promotion validation at checkout (percentage, fixed, buy_x_get_y)
- [ ] Loyalty program with tiers, points accrual on sale
- [ ] Points redemption at checkout
- [ ] Price tag templates and PDF generation
- [ ] Module/feature flags gate all functionality

### 8C Service
- [ ] Service catalog CRUD (duration-based services)
- [ ] Staff schedules with availability checking
- [ ] Appointments CRUD with calendar view
- [ ] Appointment → invoice → payment flow
- [ ] Staff assignment with conflict detection
- [ ] Module/feature flags gate all functionality

### Cross-Cutting
- [ ] All existing tests pass (1138+ regression)
- [ ] New Phase 8 backend tests pass
- [ ] New Phase 8 E2E tests pass
- [ ] Tenant isolation on every endpoint
- [ ] Permission and feature flags enforced (backend + frontend)
- [ ] Phase 7 baseline remains green
- [ ] Documentation: ARCHITECTURE, API, FLOW, SECURITY, TESTING, Final Report

---

## 10. DEPENDENCIES

- Phase 4 (POS Enhancement) — CLOSED ✅
- Phase 7 (Reports & Analytics) — CLOSED ✅
- Phase 6 (Finance / Accounting) — CLOSED ✅
- Phase 0 (Module/Feature system) — CLOSED ✅

---

## 11. RISKS & MITIGATIONS

| Risk | Mitigation |
|------|------------|
| POS checkout becomes too complex with hooks | Use a clean event/hook pattern; modifiers and recipes are optional metadata |
| Recipe auto-deduction conflicts with standard inventory deduction | Recipe deduction replaces standard product deduction for recipe-linked products; clear separation |
| KDS real-time updates require WebSocket | MVP uses polling (5s interval); WebSocket deferred to Phase 10 |
| Promotion engine performance with complex rules | Validate rules server-side, cache active promotions, limit rule complexity |
| Bill splitting creates payment complexity | Each split is a separate payment record linked to the same sale |
| Service catalog conflicts with product catalog | Use `is_service` flag; services are products with additional `service_catalog` row |

---

## 12. DEFINITION OF DONE

- PDR approved.
- All architecture, API, flow, security, and testing documents written.
- Implementation complete for 8A, 8B, 8C.
- Backend tests, E2E tests, full backend regression, and security gate passing.
- Final report and phase marked CLOSED.
- Phase 7 baseline remains green (1138 tests + 3 E2E).

---

*End of Phase 8 PDR — DRAFT*
