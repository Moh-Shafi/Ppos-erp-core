# Phase 8 — Business-Specific Modules — API

**Document Status:** DRAFT  
**Created:** 2026-08-16  
**Phase:** 8 — Business-Specific Modules  
**Base Path:** `/api/v1`  
**Auth:** `Authorization: Bearer {token}` (Sanctum)  
**PDR Reference:** `docs/phases/phase-08/PDR.md`

---

## 1. CONVENTIONS

- All endpoints require `auth:sanctum`
- Module-scoped routes require `module:{slug}` middleware
- Permission-scoped routes require `permission:{slug}` middleware
- Feature-flagged routes require `feature:{slug}` middleware
- All responses follow existing ERP format: `{ "data": ..., "message": "..." }`
- Paginated responses: `{ "data": [...], "meta": { "current_page", "per_page", "total", "last_page" } }`
- Error responses: `{ "message": "...", "errors": { "field": [...] } }`

---

## 2. 8A RESTAURANT API

### 2.1 Tables

```
GET    /api/v1/tables
  Middleware: module:tables, permission:tables.view
  Query: ?store_id=&area_id=&status=&search=&per_page=&page=
  Response: paginated list of tables with area

POST   /api/v1/tables
  Middleware: module:tables, permission:tables.manage
  Body: { store_id, table_area_id, name, code, capacity }
  Response: { data: table }

GET    /api/v1/tables/{id}
  Middleware: module:tables, permission:tables.view
  Response: { data: table with area, current_sale (if occupied) }

PUT    /api/v1/tables/{id}
  Middleware: module:tables, permission:tables.manage
  Body: { name?, code?, capacity?, table_area_id? }
  Response: { data: table }

DELETE /api/v1/tables/{id}
  Middleware: module:tables, permission:tables.manage
  Response: 204

POST   /api/v1/tables/{id}/status
  Middleware: module:tables, permission:tables.manage
  Body: { status: "available"|"occupied"|"reserved"|"cleaning" }
  Response: { data: table }

POST   /api/v1/tables/{id}/qr-code
  Middleware: module:tables, permission:tables.manage, feature:tables.qr_ordering
  Response: { data: { qr_code: "token-string" } }
```

### 2.2 Table Areas

```
GET    /api/v1/tables/areas
  Middleware: module:tables, permission:tables.view
  Query: ?store_id=
  Response: { data: [ areas with table_count ] }

POST   /api/v1/tables/areas
  Middleware: module:tables, permission:tables.manage
  Body: { store_id, name, sort_order? }
  Response: { data: area }

PUT    /api/v1/tables/areas/{id}
  Middleware: module:tables, permission:tables.manage
  Body: { name?, sort_order? }
  Response: { data: area }

DELETE /api/v1/tables/areas/{id}
  Middleware: module:tables, permission:tables.manage
  Response: 204
```

### 2.3 Reservations

```
GET    /api/v1/reservations
  Middleware: module:reservations, permission:reservations.view
  Query: ?store_id=&date=&status=&customer_id=&per_page=&page=
  Response: paginated list with customer, table

POST   /api/v1/reservations
  Middleware: module:reservations, permission:reservations.manage
  Body: {
    store_id, customer_id, table_id?,
    reservation_date, start_time, end_time,
    party_size, notes?
  }
  Response: { data: reservation }

GET    /api/v1/reservations/{id}
  Middleware: module:reservations, permission:reservations.view
  Response: { data: reservation with customer, table, sale }

PUT    /api/v1/reservations/{id}
  Middleware: module:reservations, permission:reservations.manage
  Body: { table_id?, party_size?, notes?, start_time?, end_time? }
  Response: { data: reservation }

POST   /api/v1/reservations/{id}/confirm
  Middleware: module:reservations, permission:reservations.manage
  Response: { data: reservation, status: "confirmed" }

POST   /api/v1/reservations/{id}/seat
  Middleware: module:reservations, permission:reservations.manage
  Body: { table_id }
  Response: { data: reservation, status: "seated" }

POST   /api/v1/reservations/{id}/complete
  Middleware: module:reservations, permission:reservations.manage
  Response: { data: reservation, status: "completed" }

POST   /api/v1/reservations/{id}/cancel
  Middleware: module:reservations, permission:reservations.manage
  Body: { reason? }
  Response: { data: reservation, status: "cancelled" }

GET    /api/v1/reservations/availability
  Middleware: module:reservations, permission:reservations.view
  Query: ?store_id=&date=&start_time=&end_time=&party_size=
  Response: { data: [ { table_id, table_name, available: true } ] }
```

### 2.4 KOT / KDS

```
GET    /api/v1/kot
  Middleware: module:kitchen, permission:kitchen.view
  Query: ?store_id=&status=&date=&per_page=&page=
  Response: paginated list with items, table

GET    /api/v1/kot/{id}
  Middleware: module:kitchen, permission:kitchen.view
  Response: { data: kot with items.product, items.modifiers }

POST   /api/v1/kot/{saleId}/generate
  Middleware: module:kitchen, permission:kitchen.manage
  Response: { data: kot header with items }

POST   /api/v1/kot/{id}/status
  Middleware: module:kitchen, permission:kitchen.manage
  Body: { status: "preparing"|"ready"|"served"|"cancelled" }
  Response: { data: kot }

POST   /api/v1/kot/items/{itemId}/status
  Middleware: module:kitchen, permission:kitchen.manage
  Body: { status: "queued"|"preparing"|"ready"|"served" }
  Response: { data: kot_item }

GET    /api/v1/kds/queue
  Middleware: module:kds, permission:kds.view
  Query: ?store_id=
  Response: {
    data: {
      new: [ kot... ],
      preparing: [ kot... ],
      ready: [ kot... ]
    }
  }
```

### 2.5 Modifiers

```
GET    /api/v1/modifiers
  Middleware: module:kitchen, permission:modifiers.view
  Query: ?search=&per_page=&page=
  Response: paginated list with options

POST   /api/v1/modifiers
  Middleware: module:kitchen, permission:modifiers.manage
  Body: { name, type: "single"|"multiple", is_required }
  Response: { data: modifier }

PUT    /api/v1/modifiers/{id}
  Middleware: module:kitchen, permission:modifiers.manage
  Body: { name?, type?, is_required? }
  Response: { data: modifier }

DELETE /api/v1/modifiers/{id}
  Middleware: module:kitchen, permission:modifiers.manage
  Response: 204

POST   /api/v1/modifiers/{id}/options
  Middleware: module:kitchen, permission:modifiers.manage
  Body: { name, price_delta, sort_order? }
  Response: { data: modifier_option }

PUT    /api/v1/modifiers/{id}/options/{optionId}
  Middleware: module:kitchen, permission:modifiers.manage
  Body: { name?, price_delta?, sort_order? }
  Response: { data: modifier_option }

DELETE /api/v1/modifiers/{id}/options/{optionId}
  Middleware: module:kitchen, permission:modifiers.manage
  Response: 204

GET    /api/v1/products/{id}/modifiers
  Middleware: module:kitchen, permission:modifiers.view
  Response: { data: [ modifiers with options ] }

POST   /api/v1/products/{id}/modifiers
  Middleware: module:kitchen, permission:modifiers.manage
  Body: { modifier_id }
  Response: { data: product_modifier }

DELETE /api/v1/products/{id}/modifiers/{modifierId}
  Middleware: module:kitchen, permission:modifiers.manage
  Response: 204
```

### 2.6 Recipes

```
GET    /api/v1/recipes
  Middleware: module:kitchen, permission:recipes.view
  Query: ?search=&per_page=&page=
  Response: paginated list with product, ingredients

POST   /api/v1/recipes
  Middleware: module:kitchen, permission:recipes.manage
  Body: {
    product_id, yield_quantity?, yield_unit_id?,
    ingredients: [ { ingredient_product_id, quantity, unit_id? } ]
  }
  Response: { data: recipe with ingredients }

GET    /api/v1/recipes/{id}
  Middleware: module:kitchen, permission:recipes.view
  Response: { data: recipe with ingredients.product }

PUT    /api/v1/recipes/{id}
  Middleware: module:kitchen, permission:recipes.manage
  Body: { yield_quantity?, yield_unit_id?, ingredients? }
  Response: { data: recipe }

DELETE /api/v1/recipes/{id}
  Middleware: module:kitchen, permission:recipes.manage
  Response: 204

POST   /api/v1/recipes/{id}/ingredients
  Middleware: module:kitchen, permission:recipes.manage
  Body: { ingredient_product_id, quantity, unit_id? }
  Response: { data: recipe_ingredient }

PUT    /api/v1/recipes/{id}/ingredients/{ingredientId}
  Middleware: module:kitchen, permission:recipes.manage
  Body: { quantity?, unit_id? }
  Response: { data: recipe_ingredient }

DELETE /api/v1/recipes/{id}/ingredients/{ingredientId}
  Middleware: module:kitchen, permission:recipes.manage
  Response: 204
```

### 2.7 Bill Splitting

```
POST   /api/v1/sales/{id}/split
  Middleware: module:tables, permission:billsplit.manage
  Body: {
    split_type: "equal"|"per_item"|"per_person"|"custom",
    data: {
      person_count?, (for equal)
      item_assignments?, (for per_item: [{ sale_item_id, bill_split_index }])
      person_items?, (for per_person: [{ person_index, sale_item_ids[] }])
      custom_splits? (for custom: [{ sale_item_id?, customer_id?, amount }])
    }
  }
  Response: { data: [ bill_splits with items ] }

GET    /api/v1/sales/{id}/splits
  Middleware: module:tables, permission:billsplit.view
  Response: { data: [ bill_splits with items, payments ] }

POST   /api/v1/splits/{id}/pay
  Middleware: module:tables, permission:billsplit.manage
  Body: { payment_method: "cash"|"qris"|"card"|"bank_transfer", amount }
  Response: { data: bill_split with payment, status: "paid" }
```

---

## 3. 8B RETAIL API

### 3.1 Promotions

```
GET    /api/v1/promotions
  Middleware: module:promotions, permission:promotions.view
  Query: ?is_active=&search=&per_page=&page=
  Response: paginated list with rules

POST   /api/v1/promotions
  Middleware: module:promotions, permission:promotions.manage
  Body: {
    name, description?, type: "percentage"|"fixed"|"buy_x_get_y"|"tiered",
    value, start_date, end_date, usage_limit?,
    conditions?,
    rules: [ { rule_type, rule_value } ]
  }
  Response: { data: promotion with rules }

GET    /api/v1/promotions/{id}
  Middleware: module:promotions, permission:promotions.view
  Response: { data: promotion with rules, usage_count }

PUT    /api/v1/promotions/{id}
  Middleware: module:promotions, permission:promotions.manage
  Body: { name?, description?, value?, start_date?, end_date?, usage_limit?, conditions? }
  Response: { data: promotion }

DELETE /api/v1/promotions/{id}
  Middleware: module:promotions, permission:promotions.manage
  Response: 204

POST   /api/v1/promotions/{id}/activate
  Middleware: module:promotions, permission:promotions.manage
  Response: { data: promotion, is_active: true }

POST   /api/v1/promotions/{id}/deactivate
  Middleware: module:promotions, permission:promotions.manage
  Response: { data: promotion, is_active: false }

POST   /api/v1/promotions/validate
  Middleware: module:promotions, permission:promotions.view
  Body: {
    store_id, customer_id?,
    items: [ { product_id, quantity, unit_price } ]
  }
  Response: {
    data: [ { promotion_id, name, type, discount_amount, description } ]
  }
```

### 3.2 Loyalty Program

```
GET    /api/v1/loyalty/programs
  Middleware: module:loyalty, permission:loyalty.view
  Response: { data: [ programs with tiers ] }

POST   /api/v1/loyalty/programs
  Middleware: module:loyalty, permission:loyalty.manage
  Body: { name, points_per_currency, currency_per_point, is_active? }
  Response: { data: program }

PUT    /api/v1/loyalty/programs/{id}
  Middleware: module:loyalty, permission:loyalty.manage
  Body: { name?, points_per_currency?, currency_per_point?, is_active? }
  Response: { data: program }

GET    /api/v1/loyalty/tiers
  Middleware: module:loyalty, permission:loyalty.view
  Query: ?program_id=
  Response: { data: [ tiers ] }

POST   /api/v1/loyalty/tiers
  Middleware: module:loyalty, permission:loyalty.manage
  Body: { loyalty_program_id, name, min_points, discount_percentage? }
  Response: { data: tier }

GET    /api/v1/loyalty/customers/{customerId}/balance
  Middleware: module:loyalty, permission:loyalty.view
  Response: { data: { balance, tier, program } }

GET    /api/v1/loyalty/customers/{customerId}/transactions
  Middleware: module:loyalty, permission:loyalty.view
  Query: ?per_page=&page=
  Response: paginated loyalty transactions

POST   /api/v1/loyalty/redeem
  Middleware: module:loyalty, permission:loyalty.manage
  Body: { customer_id, points, sale_id }
  Response: { data: { transaction, discount_amount } }
```

### 3.3 Price Tags

```
GET    /api/v1/price-tags/templates
  Middleware: module:pricetags, permission:pricetags.view
  Response: { data: [ templates ] }

POST   /api/v1/price-tags/templates
  Middleware: module:pricetags, permission:pricetags.manage
  Body: { name, layout: { paper_size, fields, font_sizes } }
  Response: { data: template }

PUT    /api/v1/price-tags/templates/{id}
  Middleware: module:pricetags, permission:pricetags.manage
  Body: { name?, layout? }
  Response: { data: template }

DELETE /api/v1/price-tags/templates/{id}
  Middleware: module:pricetags, permission:pricetags.manage
  Response: 204

POST   /api/v1/price-tags/generate
  Middleware: module:pricetags, permission:pricetags.view
  Body: { product_ids: [], template_id }
  Response: PDF file download (Content-Type: application/pdf)
```

---

## 4. 8C SERVICE API

### 4.1 Service Catalog

```
GET    /api/v1/services
  Middleware: module:appointments, permission:services.view
  Query: ?search=&category_id=&per_page=&page=
  Response: paginated list (products with is_service=true + service_catalog)

POST   /api/v1/services
  Middleware: module:appointments, permission:services.manage
  Body: {
    name, category_id, selling_price,
    duration_minutes, is_recurring?, recurring_interval?,
    buffer_time_minutes?, description?
  }
  Response: { data: service_catalog with product }

PUT    /api/v1/services/{id}
  Middleware: module:appointments, permission:services.manage
  Body: { name?, selling_price?, duration_minutes?, buffer_time_minutes? }
  Response: { data: service_catalog }

DELETE /api/v1/services/{id}
  Middleware: module:appointments, permission:services.manage
  Response: 204
```

### 4.2 Staff Schedules

```
GET    /api/v1/staff/schedules
  Middleware: module:appointments, permission:staff.schedule.view
  Query: ?user_id=&day_of_week=&per_page=&page=
  Response: paginated list with user

POST   /api/v1/staff/schedules
  Middleware: module:appointments, permission:staff.schedule.manage
  Body: {
    user_id, day_of_week, start_time, end_time,
    is_available?, effective_from, effective_until?
  }
  Response: { data: schedule }

PUT    /api/v1/staff/schedules/{id}
  Middleware: module:appointments, permission:staff.schedule.manage
  Body: { start_time?, end_time?, is_available?, effective_until? }
  Response: { data: schedule }

DELETE /api/v1/staff/schedules/{id}
  Middleware: module:appointments, permission:staff.schedule.manage
  Response: 204

GET    /api/v1/staff/availability
  Middleware: module:appointments, permission:appointments.view
  Query: ?date=&service_id=&store_id=
  Response: {
    data: [ { user_id, user_name, slots: [ { start_time, end_time } ] } ]
  }
```

### 4.3 Appointments

```
GET    /api/v1/appointments
  Middleware: module:appointments, permission:appointments.view
  Query: ?store_id=&date=&from_date=&to_date=&status=&staff_id=&customer_id=&per_page=&page=
  Response: paginated list with customer, staff, services

POST   /api/v1/appointments
  Middleware: module:appointments, permission:appointments.manage
  Body: {
    store_id, customer_id, user_id?,
    appointment_date, start_time,
    services: [ { service_catalog_id, quantity? } ],
    notes?, is_recurring?, recurring_interval?, recurring_end_date?
  }
  Response: { data: appointment with services }

GET    /api/v1/appointments/{id}
  Middleware: module:appointments, permission:appointments.view
  Response: { data: appointment with customer, staff, services, sale }

PUT    /api/v1/appointments/{id}
  Middleware: module:appointments, permission:appointments.manage
  Body: { user_id?, notes?, start_time?, end_time? }
  Response: { data: appointment }

POST   /api/v1/appointments/{id}/confirm
  Middleware: module:appointments, permission:appointments.manage
  Response: { data: appointment, status: "confirmed" }

POST   /api/v1/appointments/{id}/start
  Middleware: module:appointments, permission:appointments.manage
  Response: { data: appointment, status: "in_progress" }

POST   /api/v1/appointments/{id}/complete
  Middleware: module:appointments, permission:appointments.manage
  Body: { payments: [ { payment_method, amount } ] }
  Response: { data: appointment, status: "completed", sale: { ... } }

POST   /api/v1/appointments/{id}/cancel
  Middleware: module:appointments, permission:appointments.manage
  Body: { reason? }
  Response: { data: appointment, status: "cancelled" }

GET    /api/v1/appointments/calendar
  Middleware: module:appointments, permission:appointments.view
  Query: ?store_id=&view=day|week|month&date=
  Response: {
    data: {
      date_range: { start, end },
      appointments: [ { ...appointment, services: [...] } ]
    }
  }
```

---

## 5. CHECKOUT EXTENSION

### 5.1 Extended Checkout Request

The existing `POST /api/v1/sales/checkout` endpoint accepts additional optional fields:

```
POST /api/v1/sales/checkout
  Body: {
    store_id, customer_id?, items[], payments[], notes?, discount?, tax?,
    
    -- Phase 8 extensions (all optional) --
    table_id?,                    -- 8A: link sale to table
    appointment_id?,              -- 8C: link sale to appointment
    promotion_ids?,               -- 8B: applied promotions
    loyalty_redeem_points?,       -- 8B: loyalty points to redeem
    items[].modifiers?,           -- 8A: modifier selections per item
      -- modifiers: [ { modifier_id, option_ids: [] } ]
  }
```

### 5.2 Extended Sale Response

The sale response now includes optional relations:

```json
{
  "data": {
    "id": 1,
    "sale_number": "INV-20260816-0001",
    "table_id": null,
    "appointment_id": null,
    "table": null,
    "appointment": null,
    "items": [
      {
        "id": 1,
        "product_id": 5,
        "metadata": {
          "modifiers": [
            { "modifier_id": 1, "modifier_name": "Size", "options": [{ "id": 2, "name": "Large", "price_delta": 5000 }] }
          ]
        }
      }
    ]
  }
}
```

---

## 6. ERROR CODES

| HTTP | Condition | Message Pattern |
|------|-----------|----------------|
| 403 | Module not enabled | "Module {slug} is not enabled for your tenant" |
| 403 | Feature not enabled | "Feature {slug} is not enabled" |
| 403 | Permission denied | "Insufficient permissions" |
| 404 | Table not found | "Table not found" |
| 404 | Reservation not found | "Reservation not found" |
| 422 | Validation error | Field-specific errors |
| 409 | Double booking | "Staff has conflicting appointment" |
| 409 | Table occupied | "Table is already occupied" |
| 422 | Insufficient ingredient | "Insufficient ingredient: {name}" |
| 422 | Promotion expired | "Promotion {name} is no longer active" |
| 422 | Insufficient loyalty points | "Insufficient loyalty points. Available: {x}, Requested: {y}" |

---

*End of Phase 8 API — DRAFT*
