# Phase 8 — Business-Specific Modules — Flow

**Document Status:** DRAFT  
**Created:** 2026-08-16  
**Phase:** 8 — Business-Specific Modules  
**PDR Reference:** `docs/phases/phase-08/PDR.md`  
**Architecture Reference:** `docs/phases/phase-08/ARCHITECTURE.md`

---

## 1. 8A RESTAURANT FLOWS

### 1.1 Dine-In Order Flow (Table → POS → KOT → KDS → Pay)

```
User selects table (status: available)
  │
  ├── POST /api/v1/tables/{id}/status → "occupied"
  │
  ├── User opens POS, selects products
  │     ├── If product has modifiers → modifier modal appears
  │     ├── User selects modifier options (e.g., Large + Extra Spicy)
  │     └── Price deltas calculated and shown in cart
  │
  ├── User clicks "Checkout"
  │     ├── POST /api/v1/sales/checkout
  │     │     ├── items[] with modifier selections in metadata
  │     │     ├── table_id: selected table ID
  │     │     └── payments[]: cash/qris/etc
  │     │
  │     ├── SaleService::checkout()
  │     │     ├── Standard checkout (validate, calculate, create sale + items)
  │     │     ├── Modifier price deltas added to sale_item unit_price
  │     │     ├── sale.table_id = tableId
  │     │     ├── POST-CHECKOUT HOOK: TableService::linkSaleToTable()
  │     │     │     └── table.status = 'occupied'
  │     │     ├── POST-CHECKOUT HOOK: KotService::generateFromSale()
  │     │     │     └── creates KotHeader + KotItems (if kitchen module enabled)
  │     │     └── POST-CHECKOUT HOOK: RecipeService::deductIngredientsForSale()
  │     │           └── deducts ingredient stock for recipe-linked products
  │     │
  │     └── Sale completed, KOT sent to kitchen
  │
  ├── Kitchen sees KOT in KDS queue
  │     ├── KDS polls GET /api/v1/kds/queue every 5s
  │     ├── KOT status: new → preparing → ready → served
  │     └── Kitchen staff taps to advance status
  │
  ├── Table status → "available" (after payment + clearing)
  │
  └── Flow complete
```

### 1.2 Reservation Flow

```
Customer calls / walks in
  │
  ├── User creates reservation
  │     ├── POST /api/v1/reservations
  │     │     ├── customer_id (existing or create new)
  │     │     ├── reservation_date, start_time, end_time
  │     │     ├── party_size
  │     │     └── table_id (optional — assign later)
  │     │
  │     └── Reservation created with status: "pending"
  │
  ├── Confirm reservation
  │     ├── POST /api/v1/reservations/{id}/confirm
  │     └── Status: "confirmed"
  │
  ├── Customer arrives → Seat
  │     ├── POST /api/v1/reservations/{id}/seat
  │     │     ├── table_id (assign or confirm pre-assigned)
  │     │     └── table.status = 'occupied'
  │     └── Status: "seated"
  │
  ├── Dine-in order flow (Section 1.1)
  │
  ├── Complete reservation
  │     ├── POST /api/v1/reservations/{id}/complete
  │     └── Status: "completed", table.status = 'cleaning'
  │
  └── Flow complete
```

### 1.3 QR Code Ordering Flow

```
Customer scans QR code on table
  │
  ├── QR code contains table token
  │
  ├── (Future: customer-facing ordering page)
  │     ├── For MVP: QR code links to a simple page showing table info
  │     └── Staff can scan to quickly select table in POS
  │
  └── Staff uses QR to identify table in POS
        ├── Scan → table_id auto-selected in POS
        └── Continue with dine-in order flow
```

### 1.4 Bill Splitting Flow

```
Table requests bill split
  │
  ├── User opens sale detail
  │     ├── POST /api/v1/sales/{id}/split
  │     │     ├── split_type: "equal" | "per_item" | "per_person" | "custom"
  │     │     └── split data (item assignments, person count, etc.)
  │     │
  │     └── BillSplit created with BillSplitItems
  │
  ├── Process payment per split
  │     ├── POST /api/v1/splits/{id}/pay
  │     │     └── payment_method, amount
  │     └── Split status: "paid"
  │
  ├── All splits paid → sale payment_status updated
  │
  └── Flow complete
```

### 1.5 KDS Workflow

```
KDS Screen (kitchen staff)
  │
  ├── GET /api/v1/kds/queue?store_id=X (polled every 5s)
  │     └── Returns KOTs sorted by:
  │           1. priority (rush first)
  │           2. created_at (oldest first)
  │
  ├── Display: columns by status
  │     ┌──────────┬────────────┬──────────┐
  │     │ New      │ Preparing  │ Ready    │
  │     │ (queued) │ (cooking)  │ (serve!) │
  │     └──────────┴────────────┴──────────┘
  │
  ├── Kitchen taps KOT card to advance status:
  │     ├── new → preparing: POST /api/v1/kot/{id}/status {status: "preparing"}
  │     ├── preparing → ready: POST /api/v1/kot/{id}/status {status: "ready"}
  │     └── ready → served: POST /api/v1/kot/{id}/status {status: "served"}
  │
  ├── Individual item status (optional):
  │     └── POST /api/v1/kot/items/{itemId}/status
  │
  └── KOT served → table can be cleared
```

### 1.6 Recipe Auto-Deduction Flow

```
Sale checkout with recipe-linked product
  │
  ├── SaleService::checkout() → post-checkout hook
  │     └── RecipeService::deductIngredientsForSale(sale, store)
  │           │
  │           ├── For each sale_item:
  │           │     ├── Check if product has a recipe
  │           │     ├── If YES and recipes.auto_deduct enabled:
  │           │     │     ├── Skip standard product stock deduction
  │           │     │     ├── For each recipe_ingredient:
  │           │     │     │     └── InventoryService::decrease(
  │           │     │     │           store, ingredient_product,
  │           │     │     │           ingredient_qty × sale_item_qty,
  │           │     │     │           'recipe_consumption', sale)
  │           │     │     └── Record movement in inventory_movements
  │           │     └── If NO: standard product deduction (existing behavior)
  │           │
  │           └── All within same DB::transaction as checkout
  │
  └── Flow complete (transparent to user)
```

---

## 2. 8B RETAIL FLOWS

### 2.1 Barcode Quick Sale Flow

```
Cashier in POS (barcode mode)
  │
  ├── User clicks "Scan Mode" (if barcode module enabled)
  │     └── Full-screen scan interface appears
  │
  ├── Scan product barcode
  │     ├── Frontend: lookup product by barcode
  │     ├── Auto-add to cart with quantity 1
  │     ├── If product already in cart → increment quantity
  │     └── Continue scanning
  │
  ├── Click "Checkout"
  │     ├── POST /api/v1/sales/checkout
  │     │     └── Standard checkout flow
  │     └── Sale completed
  │
  └── Flow complete
```

### 2.2 Promotion Application Flow

```
Customer checkout with active promotions
  │
  ├── Before checkout: validate cart against promotions
  │     ├── POST /api/v1/promotions/validate
  │     │     ├── cart items, store_id, customer_id
  │     │     └── Returns applicable promotions + discount amounts
  │     │
  │     └── Frontend displays applicable promotions
  │
  ├── User selects promotion(s) to apply
  │
  ├── Checkout with promotion_ids
  │     ├── POST /api/v1/sales/checkout
  │     │     ├── promotion_ids[] in data
  │     │     └── SaleService calculates discount from promotions
  │     │
  │     └── POST-CHECKOUT HOOK: PromotionService::recordUsage()
  │           ├── Increment promotion.usage_count
  │           └── Record promotion-sale linkage
  │
  └── Flow complete
```

### 2.3 Promotion Validation Logic

```
PromotionService::validate(cart, storeId, customerId)
  │
  ├── Load active promotions for tenant
  │     └── WHERE is_active = true AND start_date <= today AND end_date >= today
  │
  ├── For each promotion:
  │     ├── Check usage_limit not exceeded
  │     ├── Evaluate promotion_rules:
  │     │     ├── min_purchase: cart subtotal >= rule_value
  │     │     ├── min_quantity: total item count >= rule_value
  │     │     ├── category: cart contains items from specified category
  │     │     ├── product: cart contains specified product
  │     │     └── customer_group: customer belongs to specified group
  │     │
  │     ├── If all rules pass:
  │     │     ├── Calculate discount:
  │     │     │     ├── percentage: subtotal × (value / 100)
  │     │     │     ├── fixed: value (flat amount)
  │     │     │     ├── buy_x_get_y: find qualifying items, apply free/discounted items
  │     │     │     └── tiered: apply tier matching cart value
  │     │     │
  │     │     └── Add to applicable promotions list
  │     └── If rules fail: skip
  │
  └── Return applicable promotions with calculated discounts
```

### 2.4 Loyalty Points Flow

```
Sale checkout with loyalty program
  │
  ├── Customer identified (customer_id in sale)
  │
  ├── Points earning (existing, extended):
  │     ├── POST-CHECKOUT HOOK: LoyaltyProgramService::earnPoints()
  │     │     ├── Get active loyalty program for tenant
  │     │     ├── Calculate points: floor(paid_amount × points_per_currency)
  │     │     ├── Create LoyaltyTransaction (type: 'earn')
  │     │     ├── Update customer balance
  │     │     └── Check if customer qualifies for tier upgrade
  │     └── Points visible in customer profile
  │
  ├── Points redemption (at checkout):
  │     ├── User enters points to redeem
  │     ├── POST /api/v1/loyalty/redeem
  │     │     ├── Validate customer has sufficient points
  │     │     ├── Calculate discount: points × currency_per_point
  │     │     ├── Create LoyaltyTransaction (type: 'redeem')
  │     │     └── Apply as sale discount
  │     └── Checkout with reduced total
  │
  └── Flow complete
```

### 2.5 Price Tag Generation Flow

```
User selects products for price tags
  │
  ├── Navigate to Price Tags page
  │     ├── Select price tag template
  │     ├── Select products (checkbox or category filter)
  │     └── Click "Generate"
  │
  ├── POST /api/v1/price-tags/generate
  │     ├── product_ids[], template_id
  │     ├── Backend generates PDF with price tag layout
  │     └── Returns PDF download
  │
  ├── User prints PDF
  │
  └── Flow complete
```

---

## 3. 8C SERVICE FLOWS

### 3.1 Appointment Booking Flow

```
Customer requests appointment
  │
  ├── User checks availability
  │     ├── GET /api/v1/staff/availability?date=X&service_id=Y
  │     │     ├── Load staff schedules for day_of_week
  │     │     ├── Load existing appointments for date
  │     │     ├── Calculate free slots (schedule − existing appointments − buffer)
  │     │     └── Return available time slots + staff members
  │     │
  │     └── Frontend shows calendar with available slots
  │
  ├── User creates appointment
  │     ├── POST /api/v1/appointments
  │     │     ├── customer_id
  │     │     ├── services[] (service_catalog_id × quantity)
  │     │     ├── appointment_date, start_time
  │     │     ├── user_id (assigned staff)
  │     │     └── end_time (auto-calculated from service durations + buffer)
  │     │
  │     └── Status: "pending"
  │
  ├── Confirm appointment
  │     ├── POST /api/v1/appointments/{id}/confirm
  │     └── Status: "confirmed"
  │
  ├── Customer arrives → Start service
  │     ├── POST /api/v1/appointments/{id}/start
  │     └── Status: "in_progress"
  │
  ├── Service completed → Complete appointment
  │     ├── POST /api/v1/appointments/{id}/complete
  │     │     ├── Generate invoice (sale) from appointment services
  │     │     │     ├── Create sale with appointment services as items
  │     │     │     ├── sale.appointment_id = appointmentId
  │     │     │     └── Standard checkout flow (payment processing)
  │     │     └── Status: "completed"
  │     │
  │     └── Sale linked to appointment
  │
  └── Flow complete
```

### 3.2 Appointment → Invoice → Payment Flow

```
Appointment completed
  │
  ├── AppointmentService::complete()
  │     ├── Create sale from appointment services:
  │     │     ├── sale.store_id = appointment.store_id
  │     │     ├── sale.customer_id = appointment.customer_id
  │     │     ├── sale.appointment_id = appointment.id
  │     │     ├── sale_items from appointment_services
  │     │     │     └── unit_price = service price, quantity = 1
  │     │     └── Standard SaleService::checkout() with payment
  │     │
  │     └── Appointment status: "completed", sale_id linked
  │
  ├── Payment processed (standard payment flow)
  │
  └── Invoice visible in sales history with appointment reference
```

### 3.3 Recurring Appointment Flow

```
User creates recurring appointment
  │
  ├── POST /api/v1/appointments
  │     ├── is_recurring: true
  │     ├── recurring_interval: "weekly"
  │     ├── start_date, end_date (range for recurrence)
  │     └── services[], staff, time
  │
  ├── Backend generates appointment series:
  │     ├── Create parent appointment
  │     ├── Generate child appointments at interval:
  │     │     ├── weekly: same day_of_week, same time
  │     │     └── until end_date
  │     └── All appointments status: "pending"
  │
  ├── Each appointment follows standard confirm → start → complete flow
  │
  └── User can cancel individual or entire series
```

### 3.4 Staff Schedule Management Flow

```
Manager sets staff schedules
  │
  ├── Navigate to Staff Schedules
  │     ├── Select staff member (user)
  │     ├── Set weekly availability:
  │     │     ├── Day of week (0-6)
  │     │     ├── Start time, end time
  │     │     └── Is available (toggle)
  │     └── Set effective date range
  │
  ├── POST /api/v1/staff/schedules
  │     └── Schedule created
  │
  ├── When checking appointment availability:
  │     ├── StaffScheduleService::getAvailability(userId, date)
  │     │     ├── Find schedule for date's day_of_week
  │     │     ├── Check effective_from/effective_until
  │     │     ├── Subtract existing appointments for that date
  │     │     └── Return free time slots
  │     └── Frontend shows only available slots
  │
  └── Flow complete
```

---

## 4. CROSS-CUTTING FLOWS

### 4.1 Module Enable/Disable Flow

```
Owner enables "tables" module
  │
  ├── PUT /api/v1/tenant/modules/{moduleId} {is_enabled: true}
  │     ├── TenantModule record updated
  │     └── Module now available for tenant
  │
  ├── Frontend: GET /api/v1/me refreshes module config
  │     ├── modules[] now includes "tables"
  │     └── Navigation shows "Restoran" group with Tables
  │
  ├── Backend: module:tables middleware now passes
  │
  └── User can access tables functionality
```

### 4.2 Permission-Based UI Gating

```
User with "cashier" role logs in
  │
  ├── GET /api/v1/me returns permissions[]
  │     ├── cashier has: pos.use, sales.view, sales.manage, tables.view, kds.view
  │     └── cashier does NOT have: tables.manage, recipes.manage, promotions.manage
  │
  ├── Frontend navigation:
  │     ├── "Meja" (Tables) page visible (tables.view)
  │     ├── "Kitchen Display" visible (kds.view)
  │     ├── "Modifiers" hidden (no modifiers.view)
  │     └── "Promotions" hidden (no promotions.view)
  │
  ├── Within Tables page:
  │     ├── Can view tables (tables.view)
  │     ├── Cannot create/edit tables (no tables.manage)
  │     └── Create/Edit buttons hidden
  │
  └── Backend enforces same: POST /tables returns 403 without tables.manage
```

### 4.3 Multi-Store Context Flow

```
User switches store context
  │
  ├── Frontend: store switcher in header
  │     ├── User selects Store B
  │     └── moduleConfig.setStore(Store B)
  │
  ├── All subsequent API calls include X-Store-Id header
  │
  ├── Backend: store.scope middleware filters by selected store
  │     ├── Tables: only tables for Store B
  │     ├── Reservations: only reservations for Store B
  │     ├── KDS: only KOTs for Store B
  │     └── Appointments: only appointments for Store B
  │
  └── User sees only current store's data
```

---

## 5. ERROR HANDLING FLOWS

### 5.1 Insufficient Ingredient Stock

```
Checkout with recipe-linked product
  │
  ├── RecipeService::deductIngredientsForSale()
  │     ├── Check ingredient stock levels
  │     ├── If any ingredient insufficient:
  │     │     ├── Throw DomainException
  │     │     │   "Insufficient ingredient: Rice. Available: 3, Required: 5"
  │     │     └── Entire checkout transaction rolls back
  │     └── Sale is NOT created
  │
  ├── Frontend shows error message
  └── User must restock ingredient or adjust order
```

### 5.2 Double Booking Prevention

```
User creates appointment
  │
  ├── AppointmentService::create()
  │     ├── Check staff availability for requested time
  │     ├── If staff has overlapping appointment:
  │     │     └── Throw DomainException
  │     │         "Staff member has conflicting appointment at this time"
  │     └── Appointment NOT created
  │
  ├── Frontend shows error, suggests alternative slots
  └── User selects different time or staff
```

### 5.3 Promotion Expiry During Checkout

```
Promotion was active when cart was built, but expired before checkout
  │
  ├── POST /api/v1/sales/checkout with promotion_ids
  │     ├── PromotionService validates promotion is still active
  │     ├── If promotion expired (end_date < today):
  │     │     └── Remove from applicable, checkout proceeds without that promotion
  │     └── Sale completes with adjusted discount
  │
  └── Frontend notified that promotion was no longer valid
```

---

*End of Phase 8 Flow — DRAFT*
