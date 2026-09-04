# Phase 4 — POS Enhancement (ERP Integration) — API

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-13  
**Phase:** 4 — POS Enhancement (ERP Integration)  
**Depends On:** Phase 0, Phase 1, Phase 3

---

## 1. API Conventions

- Base URL: `/api/v1`
- Auth: Bearer token (Sanctum) — `Authorization: Bearer {token}`
- Content-Type: `application/json`
- All endpoints are tenant-scoped (tenant_id from authenticated user)
- Error format: `{ "message": "..." }` with appropriate HTTP status

---

## 2. Enhanced Endpoints

### 2.1 POST /sales/checkout (Enhanced)

**Middleware:** `auth:sanctum`, `permission:sales.manage`, `module:pos`

**Request Body:**
```json
{
  "store_id": 1,
  "customer_id": 5,
  "items": [
    {
      "product_id": 10,
      "variant_id": 3,
      "quantity": 2
    },
    {
      "product_id": 11,
      "quantity": 1
    }
  ],
  "payments": [
    {
      "payment_method": "cash",
      "amount": 50000,
      "idempotency_key": "pos-12345-uuid"
    }
  ],
  "discount": 5000,
  "tax": 0,
  "notes": "Test sale"
}
```

**Changes from existing:**
- `items.*.variant_id` — NEW, optional. Required when `product.has_variants = true`.
- Price resolution: backend resolves unit_price from price list → variant → product.
- Credit limit: if `sales.customer_credit` feature enabled and customer has `credit_limit`, backend checks before completing.
- Loyalty points: if `customers.loyalty_points` feature enabled, points earned automatically.

**Response (201):**
```json
{
  "id": 42,
  "store_id": 1,
  "cashier_id": 3,
  "customer_id": 5,
  "sale_number": "INV-20260813-0007",
  "status": "completed",
  "payment_status": "paid",
  "hold_status": "none",
  "refund_status": "none",
  "refunded_amount": "0.00",
  "price_list_id": 2,
  "sale_date": "2026-08-13T21:00:00.000000Z",
  "subtotal": "55000.00",
  "discount": "5000.00",
  "tax": "0.00",
  "total": "50000.00",
  "paid_amount": "50000.00",
  "change_amount": "0.00",
  "notes": "Test sale",
  "store": { "id": 1, "name": "Toko A", "receipt_settings": {...} },
  "cashier": { "id": 3, "name": "Cashier 1" },
  "customer": { "id": 5, "name": "John Doe" },
  "items": [
    {
      "id": 101,
      "product_id": 10,
      "variant_id": 3,
      "product_name": "Coffee",
      "sku": "COF-L",
      "quantity": 2,
      "unit_price": "15000.00",
      "original_price": "18000.00",
      "discount": "0.00",
      "tax": "0.00",
      "subtotal": "30000.00",
      "total": "30000.00",
      "variant": { "id": 3, "sku": "COF-L", "price_override": "15000.00" }
    }
  ],
  "payments": [
    { "id": 201, "payment_method": "cash", "amount": "50000.00", "status": "success" }
  ]
}
```

**Errors:**
| Status | Condition |
|--------|-----------|
| 401 | Not authenticated |
| 403 | Missing `sales.manage` permission or POS module disabled |
| 422 | Credit limit exceeded |
| 422 | Insufficient stock |
| 422 | Invalid variant for product |
| 422 | Duplicate products in cart |

---

### 2.2 GET /sales/{id} (Enhanced)

**Middleware:** `auth:sanctum`, `permission:sales.view`

**Response:** Same as checkout response, includes `refunds` relation:

```json
{
  "...": "...",
  "refunds": [
    {
      "id": 1,
      "type": "partial",
      "refund_reason": "Customer returned 1 item",
      "refund_amount": "15000.00",
      "status": "completed",
      "refunded_by": { "id": 1, "name": "Owner" },
      "refunded_at": "2026-08-13T22:00:00.000000Z",
      "items": [
        {
          "id": 1,
          "sale_item_id": 101,
          "product_id": 10,
          "quantity": 1,
          "unit_price": "15000.00",
          "refund_amount": "15000.00"
        }
      ]
    }
  ]
}
```

---

### 2.3 PUT /stores/{id} (Enhanced)

**Middleware:** `auth:sanctum`, `permission:settings.manage`

**Request Body (new field):**
```json
{
  "name": "Toko A",
  "receipt_settings": {
    "header_text": "Selamat Datang",
    "footer_text": "Barang yang dibeli tidak dapat ditukar",
    "show_cashier": true,
    "show_customer": true,
    "show_qr_code": false,
    "paper_width": "80mm",
    "logo_url": null
  }
}
```

**Response (200):** Store object with `receipt_settings`.

---

## 3. New Endpoints — Held Sales

### 3.1 GET /held-sales

**Middleware:** `auth:sanctum`, `permission:sales.view`, `module:pos`, `feature:pos.hold_sale`

**Query Parameters:**
| Param | Type | Default | Description |
|-------|------|---------|-------------|
| store_id | int | required | Filter by store |
| status | string | held | Filter by status (held, recalled, expired) |
| per_page | int | 20 | Items per page (max 100) |

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "store_id": 1,
      "cashier_id": 3,
      "customer_id": 5,
      "hold_number": "HOLD-20260813-0001",
      "status": "held",
      "held_at": "2026-08-13T20:00:00.000000Z",
      "expires_at": "2026-08-14T20:00:00.000000Z",
      "cart_data": {
        "items": [
          { "product_id": 10, "variant_id": 3, "quantity": 2 },
          { "product_id": 11, "quantity": 1 }
        ],
        "customer_id": 5,
        "discount": 0,
        "tax": 0,
        "notes": ""
      },
      "cashier": { "id": 3, "name": "Cashier 1" },
      "customer": { "id": 5, "name": "John Doe" }
    }
  ],
  "current_page": 1,
  "last_page": 1,
  "per_page": 20,
  "total": 1
}
```

### 3.2 POST /held-sales

**Middleware:** `auth:sanctum`, `permission:sales.manage`, `module:pos`, `feature:pos.hold_sale`

**Request Body:**
```json
{
  "store_id": 1,
  "customer_id": 5,
  "cart_data": {
    "items": [
      { "product_id": 10, "variant_id": 3, "quantity": 2 },
      { "product_id": 11, "quantity": 1 }
    ],
    "customer_id": 5,
    "discount": 0,
    "tax": 0,
    "notes": "Customer will come back"
  }
}
```

**Response (201):**
```json
{
  "id": 1,
  "hold_number": "HOLD-20260813-0001",
  "status": "held",
  "held_at": "2026-08-13T20:00:00.000000Z",
  "expires_at": "2026-08-14T20:00:00.000000Z"
}
```

### 3.3 POST /held-sales/{id}/recall

**Middleware:** `auth:sanctum`, `permission:sales.manage`, `module:pos`, `feature:pos.hold_sale`

**Response (200):**
```json
{
  "id": 1,
  "status": "recalled",
  "recalled_at": "2026-08-13T20:30:00.000000Z",
  "cart_data": {
    "items": [
      { "product_id": 10, "variant_id": 3, "quantity": 2 },
      { "product_id": 11, "quantity": 1 }
    ],
    "customer_id": 5,
    "discount": 0,
    "tax": 0,
    "notes": "Customer will come back"
  }
}
```

**Errors:**
| Status | Condition |
|--------|-----------|
| 404 | Held sale not found |
| 422 | Held sale is expired or already recalled |

### 3.4 DELETE /held-sales/{id}

**Middleware:** `auth:sanctum`, `permission:sales.manage`, `module:pos`, `feature:pos.hold_sale`

**Response (204):** No content

---

## 4. New Endpoints — Refunds

### 4.1 GET /sales/{saleId}/refunds

**Middleware:** `auth:sanctum`, `permission:sales.view`, `module:pos`, `feature:pos.refund`

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "sale_id": 42,
      "type": "full",
      "refund_reason": "Customer cancelled order",
      "refund_amount": "50000.00",
      "status": "completed",
      "refunded_by": { "id": 1, "name": "Owner" },
      "refunded_at": "2026-08-13T22:00:00.000000Z",
      "items": [...]
    }
  ]
}
```

### 4.2 POST /sales/{saleId}/refunds

**Middleware:** `auth:sanctum`, `permission:pos.refund`, `module:pos`, `feature:pos.refund`

**Request Body — Full Refund:**
```json
{
  "type": "full",
  "reason": "Customer cancelled order"
}
```

**Request Body — Partial Refund:**
```json
{
  "type": "partial",
  "reason": "Customer returned 1 item",
  "items": [
    {
      "sale_item_id": 101,
      "quantity": 1
    }
  ]
}
```

**Response (201):**
```json
{
  "id": 1,
  "sale_id": 42,
  "type": "full",
  "refund_reason": "Customer cancelled order",
  "refund_amount": "50000.00",
  "status": "completed",
  "refunded_by": { "id": 1, "name": "Owner" },
  "refunded_at": "2026-08-13T22:00:00.000000Z",
  "items": [
    {
      "id": 1,
      "sale_item_id": 101,
      "product_id": 10,
      "quantity": 2,
      "unit_price": "15000.00",
      "refund_amount": "30000.00"
    },
    {
      "id": 2,
      "sale_item_id": 102,
      "product_id": 11,
      "quantity": 1,
      "unit_price": "25000.00",
      "refund_amount": "25000.00"
    }
  ]
}
```

**Errors:**
| Status | Condition |
|--------|-----------|
| 403 | Missing `pos.refund` permission |
| 404 | Sale not found |
| 422 | Sale is not completed (already cancelled/refunded) |
| 422 | Refund quantity exceeds original quantity |
| 422 | No items provided for partial refund |

### 4.3 GET /sales/{saleId}/refunds/{refundId}

**Middleware:** `auth:sanctum`, `permission:sales.view`, `module:pos`, `feature:pos.refund`

**Response (200):** Single refund object with items.

---

## 5. New Endpoints — Discount Presets

### 5.1 GET /discount-presets

**Middleware:** `auth:sanctum`, `permission:sales.view`, `module:pos`, `feature:pos.discount_presets`

**Query Parameters:**
| Param | Type | Default | Description |
|-------|------|---------|-------------|
| is_active | bool | — | Filter active presets only |

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Diskon Member 5%",
      "type": "percentage",
      "value": "5.00",
      "is_active": true,
      "sort_order": 1
    },
    {
      "id": 2,
      "name": "Diskon Rp 10.000",
      "type": "fixed",
      "value": "10000.00",
      "is_active": true,
      "sort_order": 2
    }
  ]
}
```

### 5.2 POST /discount-presets

**Middleware:** `auth:sanctum`, `permission:pos.discount_presets`, `module:pos`, `feature:pos.discount_presets`

**Request Body:**
```json
{
  "name": "Diskon Member 5%",
  "type": "percentage",
  "value": 5,
  "is_active": true,
  "sort_order": 1
}
```

**Validation:**
- `name`: required, string, max 100
- `type`: required, enum('percentage', 'fixed')
- `value`: required, numeric, min 0.01; if percentage, max 100
- `is_active`: boolean, default true
- `sort_order`: integer, default 0

**Response (201):** Created discount preset object.

### 5.3 PUT /discount-presets/{id}

**Middleware:** `auth:sanctum`, `permission:pos.discount_presets`, `module:pos`, `feature:pos.discount_presets`

**Request Body:** Same as create, all fields optional.

**Response (200):** Updated discount preset object.

### 5.4 DELETE /discount-presets/{id}

**Middleware:** `auth:sanctum`, `permission:pos.discount_presets`, `module:pos`, `feature:pos.discount_presets`

**Response (204):** No content.

---

## 6. New Endpoints — Store Receipt Settings

### 6.1 GET /stores/{id}/receipt-settings

**Middleware:** `auth:sanctum`, `permission:settings.manage`

**Response (200):**
```json
{
  "header_text": "Selamat Datang",
  "footer_text": "Barang yang dibeli tidak dapat ditukar",
  "show_cashier": true,
  "show_customer": true,
  "show_qr_code": false,
  "paper_width": "80mm",
  "logo_url": null
}
```

### 6.2 PUT /stores/{id}/receipt-settings

**Middleware:** `auth:sanctum`, `permission:settings.manage`

**Request Body:**
```json
{
  "header_text": "Selamat Datang",
  "footer_text": "Barang yang dibeli tidak dapat ditukar",
  "show_cashier": true,
  "show_customer": true,
  "show_qr_code": false,
  "paper_width": "80mm",
  "logo_url": null
}
```

**Response (200):** Updated receipt settings object.

---

## 7. Endpoint Summary

| Method | Path | Permission | Feature Flag | Description |
|--------|------|-----------|-------------|-------------|
| POST | /sales/checkout | sales.manage | — | Enhanced checkout (variants, price list, credit) |
| GET | /sales/{id} | sales.view | — | Enhanced sale detail (refunds) |
| PUT | /stores/{id} | settings.manage | — | Enhanced store update (receipt_settings) |
| GET | /stores/{id}/receipt-settings | settings.manage | — | Get receipt settings |
| PUT | /stores/{id}/receipt-settings | settings.manage | — | Update receipt settings |
| GET | /held-sales | sales.view | pos.hold_sale | List held sales |
| POST | /held-sales | sales.manage | pos.hold_sale | Hold a sale |
| POST | /held-sales/{id}/recall | sales.manage | pos.hold_sale | Recall a held sale |
| DELETE | /held-sales/{id} | sales.manage | pos.hold_sale | Delete a held sale |
| GET | /sales/{id}/refunds | sales.view | pos.refund | List refunds for a sale |
| POST | /sales/{id}/refunds | pos.refund | pos.refund | Process a refund |
| GET | /sales/{id}/refunds/{rid} | sales.view | pos.refund | Show refund detail |
| GET | /discount-presets | sales.view | pos.discount_presets | List discount presets |
| POST | /discount-presets | pos.discount_presets | pos.discount_presets | Create preset |
| PUT | /discount-presets/{id} | pos.discount_presets | pos.discount_presets | Update preset |
| DELETE | /discount-presets/{id} | pos.discount_presets | pos.discount_presets | Delete preset |

---

## 8. Rate Limiting

All Phase 4 endpoints use the existing `throttle:api` middleware. No additional rate limiting needed for this phase.

---

*End of Phase 4 API*
