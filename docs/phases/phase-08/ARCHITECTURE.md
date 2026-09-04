# Phase 8 — Business-Specific Modules — Architecture

**Document Status:** DRAFT  
**Created:** 2026-08-16  
**Phase:** 8 — Business-Specific Modules  
**Depends On:** Phase 4 (POS), Phase 7 (Reports), Phase 6 (Finance), Phase 0 (Module System)  
**PDR Reference:** `docs/phases/phase-08/PDR.md`

---

## 1. ARCHITECTURE PRINCIPLE

```
                    Phase 8
                       │
          ┌────────────┼────────────┐
          ↓            ↓            ↓
     Restaurant      Retail       Service
     (8A)            (8B)         (8C)
          │            │            │
          └────────────┼────────────┘
                       ↓
              Existing Core (Untouched)
       POS / Inventory / Sales /
       Customers / Payments /
       Accounting / Reports
```

**Core principle:** Phase 8 modules are **extensions** that sit on top of the existing ERP core. They must not modify core domain models, services, or table structures. Integration is achieved via:

1. **Optional columns** on existing tables (`is_service` on products, `metadata` JSON on sale_items, `table_id`/`appointment_id` nullable FKs on sales)
2. **Own tables** with FK references to core tables
3. **Service-layer hooks** — `SaleService::checkout()` calls optional post-checkout hooks
4. **Module/feature middleware** — every route is gated

---

## 2. SYSTEM ARCHITECTURE

```
┌─────────────────────────────────────────────────────────────┐
│                    FRONTEND (React)                          │
│                                                             │
│  Module-Aware Navigation (extended)                         │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐                     │
│  │Restaurant│  │ Retail  │  │ Service │                     │
│  │  Pages   │  │  Pages  │  │  Pages  │                     │
│  └────┬────┘  └────┬────┘  └────┬────┘                     │
│       └────────────┼────────────┘                           │
│                    ↓                                        │
│  Shared: POS, Cart, Checkout, Customer, Payments            │
└──────────────────────┬──────────────────────────────────────┘
                       │ HTTPS / API
┌──────────────────────▼──────────────────────────────────────┐
│                 BACKEND (Laravel API)                        │
│                                                             │
│  Middleware Stack (unchanged):                              │
│  auth:sanctum → module.check → permission.check             │
│  → feature.check → tenant.scope → store.scope               │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  Phase 8 Service Layer (NEW)                        │    │
│  │                                                     │    │
│  │  8A: TableService, ReservationService,             │    │
│  │      KotService, KdsService, ModifierService,       │    │
│  │      RecipeService, BillSplitService                │    │
│  │                                                     │    │
│  │  8B: PromotionService, LoyaltyProgramService,       │    │
│  │      PriceTagService                                │    │
│  │                                                     │    │
│  │  8C: AppointmentService, ServiceCatalogService,     │    │
│  │      StaffScheduleService                            │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  Core Service Layer (UNCHANGED)                     │    │
│  │  SaleService · InventoryService ·                   │    │
│  │  PaymentService · AccountingService ·               │    │
│  │  ModuleService · ReportEngine                       │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  Domain Models (BelongsToTenant)                    │    │
│  │  + Phase 8 Models (BelongsToTenant)                 │    │
│  └─────────────────────────────────────────────────────┘    │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│                   MySQL Database                             │
│  core tables (unchanged) │ phase 8 tables (new)             │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. POS INTEGRATION ARCHITECTURE

### 3.1 Checkout Extension Pattern

The existing `SaleService::checkout()` is the atomic checkout engine. Phase 8 extends it **without modifying its core logic** via a post-checkout hook system:

```
SaleService::checkout($data)
  │
  ├── 1. Validate store, customer, items (existing)
  ├── 2. Lock inventory, check stock (existing)
  ├── 3. Calculate totals (existing + modifier price deltas)
  ├── 4. Create Sale + SaleItems (existing + metadata JSON)
  ├── 5. Decrease inventory (existing)
  ├── 6. Create payments (existing)
  ├── 7. Loyalty points earning (existing)
  │
  └── 8. POST-CHECKOUT HOOKS (NEW — Phase 8)
        ├── if table_id → link sale to table, update table status
        ├── if appointment_id → link sale to appointment, mark completed
        ├── if recipe products → deduct ingredient stock (replaces product stock deduction for recipe-linked items)
        ├── if promotion_ids → apply promotion discounts, record usage
        └── if loyalty redemption → deduct loyalty points
```

### 3.2 Hook Implementation

```php
// SaleService::checkout() — after existing logic, before return:
$this->runPostCheckoutHooks($sale, $data, $products, $store);

private function runPostCheckoutHooks(
    Sale $sale,
    array $data,
    \Illuminate\Support\Collection $products,
    Store $store
): void {
    $tenantId = $sale->tenant_id;

    // Table linking (8A)
    if (!empty($data['table_id']) && $this->moduleService->isModuleEnabled($tenantId, 'tables')) {
        app(TableService::class)->linkSaleToTable($sale, $data['table_id']);
    }

    // Appointment linking (8C)
    if (!empty($data['appointment_id']) && $this->moduleService->isModuleEnabled($tenantId, 'appointments')) {
        app(AppointmentService::class)->linkSaleToAppointment($sale, $data['appointment_id']);
    }

    // Recipe auto-deduction (8A)
    if ($this->moduleService->isFeatureEnabled($tenantId, 'recipes.auto_deduct')) {
        app(RecipeService::class)->deductIngredientsForSale($sale, $store);
    }

    // Promotion application (8B)
    if (!empty($data['promotion_ids']) && $this->moduleService->isModuleEnabled($tenantId, 'promotions')) {
        app(PromotionService::class)->recordUsage($sale, $data['promotion_ids']);
    }

    // Loyalty redemption (8B)
    if (!empty($data['loyalty_redeem_points']) && $this->moduleService->isFeatureEnabled($tenantId, 'customers.loyalty_points')) {
        app(LoyaltyProgramService::class)->redeemPoints($sale->customer_id, $data['loyalty_redeem_points'], $sale->id);
    }
}
```

### 3.3 Modifier Price Calculation

Modifiers add price deltas to sale items. This is calculated **during** the checkout totals calculation, not as a post-hook:

```php
// In the item calculation loop, after unit price resolution:
$modifierDelta = 0;
$modifierData = [];
if (!empty($item['modifiers']) && $this->moduleService->isModuleEnabled($tenantId, 'kitchen')) {
    [$modifierDelta, $modifierData] = $this->resolveModifiers($item['modifiers'], $product);
}
$lineSubtotal = ($unitPrice + $modifierDelta) * $quantity;
// modifierData stored in sale_item metadata JSON
```

### 3.4 Recipe Deduction Logic

For products with recipes, the standard inventory deduction is **replaced** by ingredient deduction:

```
Product "Nasi Goreng" has Recipe:
  ├── Ingredient: Rice — 1 portion
  ├── Ingredient: Egg — 1 unit
  └── Ingredient: Soy Sauce — 20ml

Sale: 2x Nasi Goreng
  → Standard: deduct 2 from "Nasi Goreng" inventory (SKIPPED for recipe products)
  → Recipe: deduct 2 portions Rice, 2 units Egg, 40ml Soy Sauce
```

**Decision rule:** If a product has a linked recipe AND `recipes.auto_deduct` feature is enabled, deduct ingredients instead of the product itself. If no recipe, standard deduction applies.

---

## 4. DATABASE SCHEMA

### 4.1 8A Restaurant Tables

```sql
-- Floor areas (sections of the restaurant)
CREATE TABLE table_areas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    store_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX (tenant_id, store_id)
);

-- Tables (physical restaurant tables)
CREATE TABLE tables (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    store_id BIGINT UNSIGNED NOT NULL,
    table_area_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL,
    code VARCHAR(20) NOT NULL,
    capacity INT DEFAULT 4,
    status ENUM('available', 'occupied', 'reserved', 'cleaning') DEFAULT 'available',
    qr_code VARCHAR(100) NULL UNIQUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX (tenant_id, store_id, status),
    FOREIGN KEY (table_area_id) REFERENCES table_areas(id)
);

-- Reservations
CREATE TABLE reservations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    store_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    table_id BIGINT UNSIGNED NULL,
    reservation_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    party_size INT NOT NULL,
    status ENUM('pending', 'confirmed', 'seated', 'completed', 'cancelled', 'no_show') DEFAULT 'pending',
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX (tenant_id, store_id, reservation_date, status),
    FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE SET NULL
);

-- Kitchen Order Tickets
CREATE TABLE kot_headers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    store_id BIGINT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NULL,
    table_id BIGINT UNSIGNED NULL,
    kot_number VARCHAR(30) NOT NULL,
    status ENUM('new', 'preparing', 'ready', 'served', 'cancelled') DEFAULT 'new',
    priority ENUM('normal', 'rush') DEFAULT 'normal',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX (tenant_id, store_id, status, created_at)
);

-- KOT items
CREATE TABLE kot_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kot_header_id BIGINT UNSIGNED NOT NULL,
    sale_item_id BIGINT UNSIGNED NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    modifiers JSON NULL,
    notes VARCHAR(255) NULL,
    status ENUM('queued', 'preparing', 'ready', 'served') DEFAULT 'queued',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (kot_header_id) REFERENCES kot_headers(id) ON DELETE CASCADE
);

-- Modifiers
CREATE TABLE modifiers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('single', 'multiple') DEFAULT 'single',
    is_required BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

-- Modifier options
CREATE TABLE modifier_options (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    modifier_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    price_delta DECIMAL(15, 2) DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (modifier_id) REFERENCES modifiers(id) ON DELETE CASCADE
);

-- Product-modifier mapping
CREATE TABLE product_modifiers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    modifier_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    UNIQUE (product_id, modifier_id)
);

-- Recipes
CREATE TABLE recipes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL UNIQUE,
    yield_quantity DECIMAL(15, 3) DEFAULT 1,
    yield_unit_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

-- Recipe ingredients
CREATE TABLE recipe_ingredients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipe_id BIGINT UNSIGNED NOT NULL,
    ingredient_product_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(15, 3) NOT NULL,
    unit_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
);

-- Bill splits
CREATE TABLE bill_splits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NOT NULL,
    split_type ENUM('equal', 'per_item', 'per_person', 'custom') NOT NULL,
    total_amount DECIMAL(15, 2) NOT NULL,
    status ENUM('pending', 'paid') DEFAULT 'pending',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX (tenant_id, sale_id)
);

-- Bill split items
CREATE TABLE bill_split_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bill_split_id BIGINT UNSIGNED NOT NULL,
    sale_item_id BIGINT UNSIGNED NULL,
    customer_id BIGINT UNSIGNED NULL,
    amount DECIMAL(15, 2) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (bill_split_id) REFERENCES bill_splits(id) ON DELETE CASCADE
);
```

### 4.2 8B Retail Tables

```sql
-- Promotions
CREATE TABLE promotions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    type ENUM('percentage', 'fixed', 'buy_x_get_y', 'tiered') NOT NULL,
    value DECIMAL(15, 2) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    usage_limit INT NULL,
    usage_count INT DEFAULT 0,
    conditions JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX (tenant_id, is_active, start_date, end_date)
);

-- Promotion rules
CREATE TABLE promotion_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    promotion_id BIGINT UNSIGNED NOT NULL,
    rule_type ENUM('min_purchase', 'min_quantity', 'category', 'product', 'customer_group') NOT NULL,
    rule_value JSON NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE CASCADE
);

-- Loyalty programs
CREATE TABLE loyalty_programs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    points_per_currency DECIMAL(15, 4) NOT NULL,
    currency_per_point DECIMAL(15, 2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

-- Loyalty tiers
CREATE TABLE loyalty_tiers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loyalty_program_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL,
    min_points INT NOT NULL,
    discount_percentage DECIMAL(5, 2) DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (loyalty_program_id) REFERENCES loyalty_programs(id) ON DELETE CASCADE
);

-- Loyalty transactions
CREATE TABLE loyalty_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    loyalty_program_id BIGINT UNSIGNED NULL,
    type ENUM('earn', 'redeem', 'expire', 'adjust') NOT NULL,
    points INT NOT NULL,
    balance_after INT NOT NULL,
    reference_type VARCHAR(50) NULL,
    reference_id BIGINT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX (tenant_id, customer_id, created_at)
);

-- Price tag templates
CREATE TABLE price_tag_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    layout JSON NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### 4.3 8C Service Tables

```sql
-- Service catalog (extends products)
CREATE TABLE service_catalog (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL UNIQUE,
    duration_minutes INT NOT NULL,
    is_recurring BOOLEAN DEFAULT FALSE,
    recurring_interval ENUM('daily', 'weekly', 'monthly') NULL,
    buffer_time_minutes INT DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

-- Staff schedules
CREATE TABLE staff_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    day_of_week TINYINT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    effective_from DATE NOT NULL,
    effective_until DATE NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX (tenant_id, user_id, day_of_week)
);

-- Appointments
CREATE TABLE appointments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    store_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    appointment_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status ENUM('pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show') DEFAULT 'pending',
    notes TEXT NULL,
    sale_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX (tenant_id, store_id, appointment_date, status)
);

-- Appointment services (multiple services per appointment)
CREATE TABLE appointment_services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id BIGINT UNSIGNED NOT NULL,
    service_catalog_id BIGINT UNSIGNED NOT NULL,
    duration_minutes INT NOT NULL,
    price DECIMAL(15, 2) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
);
```

### 4.4 Modified Core Tables

```sql
-- products: add is_service column
ALTER TABLE products ADD COLUMN is_service BOOLEAN DEFAULT FALSE AFTER is_active;

-- sale_items: add metadata JSON column
ALTER TABLE sale_items ADD COLUMN metadata JSON NULL AFTER total;

-- sales: add optional context FKs
ALTER TABLE sales ADD COLUMN table_id BIGINT UNSIGNED NULL AFTER customer_id;
ALTER TABLE sales ADD COLUMN appointment_id BIGINT UNSIGNED NULL AFTER table_id;
ALTER TABLE sales ADD INDEX (table_id);
ALTER TABLE sales ADD INDEX (appointment_id);
```

---

## 5. MODEL ARCHITECTURE

### 5.1 New Models (8A)

| Model | Table | Traits | Key Relations |
|-------|-------|--------|--------------|
| `TableArea` | `table_areas` | BelongsToTenant | hasMany Tables, belongsTo Store |
| `RestaurantTable` | `tables` | BelongsToTenant | belongsTo TableArea, hasMany Reservations |
| `Reservation` | `reservations` | BelongsToTenant | belongsTo Customer, RestaurantTable |
| `KotHeader` | `kot_headers` | BelongsToTenant | hasMany KotItems, belongsTo Sale |
| `KotItem` | `kot_items` | — | belongsTo KotHeader, Product |
| `Modifier` | `modifiers` | BelongsToTenant | hasMany ModifierOptions |
| `ModifierOption` | `modifier_options` | — | belongsTo Modifier |
| `ProductModifier` | `product_modifiers` | — | belongsTo Product, Modifier |
| `Recipe` | `recipes` | BelongsToTenant | belongsTo Product, hasMany RecipeIngredients |
| `RecipeIngredient` | `recipe_ingredients` | — | belongsTo Recipe, Product |
| `BillSplit` | `bill_splits` | BelongsToTenant | belongsTo Sale, hasMany BillSplitItems |
| `BillSplitItem` | `bill_split_items` | — | belongsTo BillSplit |

### 5.2 New Models (8B)

| Model | Table | Traits | Key Relations |
|-------|-------|--------|--------------|
| `Promotion` | `promotions` | BelongsToTenant | hasMany PromotionRules |
| `PromotionRule` | `promotion_rules` | — | belongsTo Promotion |
| `LoyaltyProgram` | `loyalty_programs` | BelongsToTenant | hasMany LoyaltyTiers |
| `LoyaltyTier` | `loyalty_tiers` | — | belongsTo LoyaltyProgram |
| `LoyaltyTransaction` | `loyalty_transactions` | BelongsToTenant | belongsTo Customer |
| `PriceTagTemplate` | `price_tag_templates` | BelongsToTenant | — |

### 5.3 New Models (8C)

| Model | Table | Traits | Key Relations |
|-------|-------|--------|--------------|
| `ServiceCatalog` | `service_catalog` | BelongsToTenant | belongsTo Product |
| `StaffSchedule` | `staff_schedules` | BelongsToTenant | belongsTo User |
| `Appointment` | `appointments` | BelongsToTenant | belongsTo Customer, User, Sale; hasMany AppointmentServices |
| `AppointmentService` | `appointment_services` | — | belongsTo Appointment, ServiceCatalog |

### 5.4 Extended Core Models

- `Product` — add `is_service` to fillable, add `serviceCatalog()` relation
- `Sale` — add `table_id`, `appointment_id` to fillable, add `table()`, `appointment()` relations
- `SaleItem` — add `metadata` to fillable (JSON cast)

---

## 6. SERVICE LAYER

### 6.1 8A Services

```
TableService
  ├── createArea(storeId, name)
  ├── createTable(storeId, areaId, data)
  ├── updateTableStatus(tableId, status)
  ├── generateQrCode(tableId)
  └── linkSaleToTable(sale, tableId)
      └── sets sale.table_id, updates table.status = 'occupied'

ReservationService
  ├── create(storeId, customerId, date, time, partySize)
  ├── checkAvailability(storeId, date, time, partySize)
  ├── confirm(reservationId)
  ├── seat(reservationId, tableId)
  ├── complete(reservationId)
  └── cancel(reservationId)

KotService
  ├── generateFromSale(sale)
  │   └── creates KotHeader + KotItems for each sale_item
  ├── updateStatus(kotId, status)
  ├── updateItemStatus(itemId, status)
  └── getKdsQueue(storeId)
      └── returns KOTs sorted by priority desc, created_at asc

ModifierService
  ├── create(name, type, isRequired)
  ├── addOption(modifierId, name, priceDelta)
  ├── attachToProduct(productId, modifierId)
  └── resolveModifiers(modifierSelections)
      └── returns [totalDelta, modifierData]

RecipeService
  ├── create(productId, ingredients[])
  ├── update(recipeId, ingredients[])
  └── deductIngredientsForSale(sale, store)
      └── for each sale_item with recipe: deduct ingredient stock

BillSplitService
  ├── create(saleId, splitType)
  ├── splitEqual(saleId, personCount)
  ├── splitPerItem(saleId, itemAssignments)
  ├── splitPerPerson(saleId, personItems)
  └── processPayment(splitId, paymentData)
```

### 6.2 8B Services

```
PromotionService
  ├── create(data)
  ├── validate(cart, storeId)
  │   └── checks active promotions against cart contents
  │   └── returns applicable promotions with discount amounts
  ├── apply(sale, promotionIds)
  └── recordUsage(sale, promotionIds)

LoyaltyProgramService
  ├── createProgram(data)
  ├── createTier(programId, data)
  ├── earnPoints(customerId, amount, saleId)
  │   └── calculates points from active program
  ├── redeemPoints(customerId, points, saleId)
  ├── getBalance(customerId)
  └── getTier(customerId)

PriceTagService
  ├── createTemplate(data)
  ├── generate(productIds, templateId)
  │   └── generates PDF with price tags
  └── preview(templateId, sampleProduct)
```

### 6.3 8C Services

```
ServiceCatalogService
  ├── create(productId, durationMinutes, data)
  ├── update(serviceId, data)
  ├── listServices(storeId)
  └── checkAvailability(serviceId, date, time)

StaffScheduleService
  ├── create(userId, scheduleData)
  ├── update(scheduleId, data)
  ├── getAvailability(userId, date)
  │   └── checks staff_schedules + existing appointments
  └── getAvailableStaff(serviceId, date, time)

AppointmentService
  ├── create(storeId, customerId, services[], date, time)
  ├── confirm(appointmentId)
  ├── start(appointmentId)
  ├── complete(appointmentId)
  │   └── generates invoice (sale) from appointment services
  ├── cancel(appointmentId)
  ├── getCalendar(storeId, view, date)
  └── linkSaleToAppointment(sale, appointmentId)
```

---

## 7. ROUTE ARCHITECTURE

### 7.1 Route Registration Pattern

All Phase 8 routes follow the existing pattern with module + permission middleware:

```php
// 8A — Restaurant
Route::middleware(['module:tables', 'permission:tables.view'])->prefix('tables')->group(function () {
    Route::get('/', [TableController::class, 'index']);
    Route::post('/', [TableController::class, 'store'])
        ->middleware('permission:tables.manage');
    // ...
});

Route::middleware(['module:reservations', 'permission:reservations.view'])->prefix('reservations')->group(function () {
    // ...
});

Route::middleware(['module:kitchen', 'permission:kitchen.view'])->prefix('kot')->group(function () {
    // ...
});

Route::middleware(['module:kds', 'permission:kds.view'])->prefix('kds')->group(function () {
    // ...
});

// 8B — Retail
Route::middleware(['module:promotions', 'permission:promotions.view'])->prefix('promotions')->group(function () {
    // ...
});

Route::middleware(['module:customers', 'permission:loyalty.view'])->prefix('loyalty')->group(function () {
    // ...
});

// 8C — Service
Route::middleware(['module:appointments', 'permission:appointments.view'])->prefix('appointments')->group(function () {
    // ...
});
```

### 7.2 New Modules to Register

The following modules are already seeded in `ModuleSeeder`:
- `tables` (sort 110) ✅
- `reservations` (sort 115) ✅
- `kitchen` (sort 120) ✅
- `kds` (sort 125) ✅
- `barcode` (sort 140) ✅
- `appointments` (sort 145) ✅

**New modules to add to ModuleSeeder:**
- `promotions` (sort 148, dependencies: pos, sales)
- `loyalty` (sort 149, dependencies: customers, sales)
- `pricetags` (sort 152, dependencies: core)

---

## 8. FRONTEND ARCHITECTURE

### 8.1 New Pages

```
src/pages/
  ├── restaurant/
  │   ├── TablesPage.tsx          — floor plan + table CRUD
  │   ├── ReservationsPage.tsx    — reservation calendar + CRUD
  │   ├── KdsPage.tsx             — KDS queue display
  │   ├── ModifiersPage.tsx       — modifier management
  │   ├── RecipesPage.tsx         — recipe management
  │   └── BillSplitPage.tsx       — bill splitting interface
  ├── retail/
  │   ├── PromotionsPage.tsx      — promotion CRUD + rule builder
  │   ├── LoyaltyProgramPage.tsx  — loyalty program + tiers
  │   └── PriceTagsPage.tsx       — price tag templates + generation
  └── service/
      ├── AppointmentsPage.tsx    — appointment calendar + CRUD
      ├── ServiceCatalogPage.tsx  — service catalog management
      └── StaffSchedulesPage.tsx  — staff schedule management
```

### 8.2 New Services

```
src/services/
  ├── tables.ts
  ├── reservations.ts
  ├── kot.ts
  ├── kds.ts
  ├── modifiers.ts
  ├── recipes.ts
  ├── billSplit.ts
  ├── promotions.ts
  ├── loyaltyProgram.ts
  ├── priceTags.ts
  ├── appointments.ts
  ├── serviceCatalog.ts
  └── staffSchedules.ts
```

### 8.3 Navigation Extension

New nav groups added to `NAV_GROUPS` in `DashboardLayout.tsx`:

```typescript
{
  title: 'Restoran',
  items: [
    { to: '/tables', label: 'Meja', icon: 'tables', module: 'tables', permission: 'tables.view' },
    { to: '/reservations', label: 'Reservasi', icon: 'reservations', module: 'reservations', permission: 'reservations.view' },
    { to: '/kds', label: 'Kitchen Display', icon: 'kds', module: 'kds', permission: 'kds.view' },
    { to: '/modifiers', label: 'Modifiers', icon: 'modifiers', module: 'kitchen', permission: 'modifiers.view' },
    { to: '/recipes', label: 'Resep', icon: 'recipes', module: 'kitchen', permission: 'recipes.view' },
  ],
},
{
  title: 'Retail',
  items: [
    { to: '/promotions', label: 'Promosi', icon: 'promotions', module: 'promotions', permission: 'promotions.view' },
    { to: '/loyalty-program', label: 'Loyalty Program', icon: 'loyalty', module: 'loyalty', permission: 'loyalty.view' },
    { to: '/price-tags', label: 'Price Tags', icon: 'pricetags', module: 'pricetags', permission: 'pricetags.view' },
  ],
},
{
  title: 'Layanan',
  items: [
    { to: '/appointments', label: 'Janji Temu', icon: 'appointments', module: 'appointments', permission: 'appointments.view' },
    { to: '/service-catalog', label: 'Katalog Layanan', icon: 'serviceCatalog', module: 'appointments', permission: 'services.view' },
    { to: '/staff-schedules', label: 'Jadwal Staff', icon: 'staffSchedules', module: 'appointments', permission: 'staff.schedule.view' },
  ],
},
```

### 8.4 POS Integration

The existing `POSPage.tsx` will be extended with optional UI sections that appear based on enabled modules:

- **Table selector** (if `tables` module enabled) — select table before checkout
- **Modifier modal** (if product has modifiers) — show modifier selection when adding to cart
- **Promotion display** (if `promotions` module enabled) — show applicable promotions at checkout
- **Loyalty redemption** (if `loyalty` module enabled) — show points balance and redemption option

These are **conditional renders** based on `moduleConfig.hasModule()` / `moduleConfig.hasFeature()`.

---

## 9. KDS REAL-TIME STRATEGY

### MVP: Polling

```
Frontend (KDS Page)
  │
  ├── setInterval(pollKdsQueue, 5000)
  │     └── GET /api/v1/kds/queue?store_id=X
  │
  └── On status change:
        ├── POST /api/v1/kot/{id}/status
        └── Optimistic UI update + refetch
```

### Future: WebSocket (Phase 10)

```
Backend: Laravel Reverb / Pusher
  ├── Event: KotStatusChanged
  ├── Channel: private-tenant.{tenantId}.store.{storeId}.kds
  └── Frontend: Listen on channel, real-time update
```

---

## 10. SECURITY ARCHITECTURE

### 10.1 Tenant Isolation

All Phase 8 models use `BelongsToTenant` trait with global scope. No exceptions.

### 10.2 Module Gating

Every route is wrapped in `module:` middleware. A tenant without `tables` module enabled gets 403 on all table endpoints.

### 10.3 Feature Gating

Granular features (e.g., `tables.qr_ordering`, `recipes.auto_deduct`) are checked via `feature:` middleware or `ModuleService::isFeatureEnabled()` in services.

### 10.4 Store Authorization

Table, reservation, KOT, and appointment endpoints are scoped to the user's authorized stores (same pattern as Phase 7 `AuthorizedStoreScope`).

### 10.5 Input Validation

- All modifier selections are validated against the product's attached modifiers
- Promotion conditions are validated server-side (never trust client-calculated discounts)
- Recipe ingredient quantities are validated as positive decimals
- Appointment time slots are validated against staff availability

---

## 11. TECHNOLOGY DECISIONS

| Decision | Choice | Rationale |
|----------|--------|-----------|
| KDS real-time | Polling (5s) | MVP simplicity; WebSocket deferred to Phase 10 |
| QR code generation | `simplesoftwareio/simple-qrcode` | Laravel-native, lightweight |
| Price tag PDF | `dompdf/dompdf` (already used for reports) | Consistent with Phase 7 export |
| Promotion engine | Server-side validation | Never trust client-calculated discounts |
| Recipe deduction | Service-layer, within checkout transaction | Atomic with sale creation |
| Bill split payments | Separate payment records per split | Links to same sale, independent payment |

---

## 12. MIGRATION STRATEGY

1. All new tables created in a single migration batch (0001_01_01_000300+)
2. Core table modifications (`is_service`, `metadata`, `table_id`, `appointment_id`) in separate migration
3. All migrations are reversible (down method drops tables/columns)
4. Seeders updated: `ModuleSeeder` (new modules + features), `RbacSeeder` (new permissions), `BusinessTypeSeeder` (module defaults for restaurant/retail/service)
5. `E2ESeeder` updated with test data for Phase 8 E2E tests

---

*End of Phase 8 Architecture — DRAFT*
