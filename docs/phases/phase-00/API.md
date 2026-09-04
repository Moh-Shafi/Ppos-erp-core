# Phase 0 — ERP Foundation & Architecture — API

**Phase:** 0  
**Status:** IN PROGRESS — Documentation Phase  
**Created:** 2026-08-11  
**Depends On:** `docs/phases/phase-00/PDR.md`, `docs/phases/phase-00/ARCHITECTURE.md`

---

## 1. API CONVENTIONS

- **Base URL:** `/api/v1`
- **Auth:** `Authorization: Bearer {token}` (Sanctum)
- **Store Context:** `X-Store-Id: {store_id}` (optional header, for store-scoped operations)
- **Content-Type:** `application/json`
- **Rate Limit:** 5 req/min on auth endpoints, 60 req/min on authenticated endpoints

---

## 2. NEW ENDPOINTS

### 2.1 Business Types

```
GET /api/v1/business-types
```

**Auth:** Public (no token required)  
**Purpose:** List available business type templates for registration

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "slug": "restaurant",
      "name": "Restaurant",
      "description": "Full restaurant with POS, tables, kitchen",
      "icon": "utensils",
      "default_modules": ["pos", "inventory", "purchasing", "customers", "tables", "kitchen", "kds", "reports", "finance"]
    },
    {
      "id": 2,
      "slug": "cafe",
      "name": "Café",
      "description": "Café with POS and menu management",
      "icon": "coffee",
      "default_modules": ["pos", "inventory", "purchasing", "customers", "reports", "finance"]
    }
  ]
}
```

---

### 2.2 Enhanced Auth — Register

```
POST /api/v1/auth/register
```

**Auth:** Public  
**Rate Limit:** 5 req/min

**Request:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "store_name": "My Restaurant",
  "business_type_id": 1
}
```

| Field | Required | Validation |
|-------|----------|------------|
| name | Yes | string, max:255 |
| email | Yes | email, unique:users |
| password | Yes | string, min:8, confirmed |
| store_name | Yes | string, max:255 |
| business_type_id | No | integer, exists:business_types (default: 'general' type) |

**Response 201:**
```json
{
  "token": "1|abcdef...",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "tenant_id": 1,
    "role": { "id": 1, "slug": "owner", "name": "Owner" }
  },
  "tenant": {
    "id": 1,
    "name": "My Restaurant"
  },
  "business_profile": {
    "id": 1,
    "business_type_id": 1,
    "business_type": { "slug": "restaurant", "name": "Restaurant" },
    "business_name": "My Restaurant"
  },
  "modules": ["core", "pos", "inventory", "purchasing", "customers", "tables", "kitchen", "kds", "reports", "finance"],
  "features": ["pos.split_payment", "pos.multi_payment", "inventory.transfer", ...],
  "permissions": ["pos.use", "sales.view", "sales.manage", ...],
  "stores": [
    { "id": 1, "name": "Main Store", "code": "MAIN" }
  ]
}
```

---

### 2.3 Enhanced Auth — Me

```
GET /api/v1/me
```

**Auth:** Required

**Response 200:**
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "tenant_id": 1,
    "role": { "id": 1, "slug": "owner", "name": "Owner" }
  },
  "tenant": {
    "id": 1,
    "name": "My Restaurant"
  },
  "business_profile": {
    "id": 1,
    "business_type_id": 1,
    "business_type": { "slug": "restaurant", "name": "Restaurant" },
    "business_name": "My Restaurant",
    "tax_id": null,
    "address": null,
    "city": null,
    "timezone": "Asia/Jakarta",
    "currency": "IDR",
    "locale": "id"
  },
  "modules": ["core", "pos", "inventory", "purchasing", "customers", "tables", "kitchen", "kds", "reports", "finance"],
  "features": ["pos.split_payment", "pos.multi_payment", "inventory.transfer", ...],
  "permissions": ["pos.use", "sales.view", "sales.manage", "inventory.view", "inventory.manage", ...],
  "stores": [
    { "id": 1, "name": "Main Store", "code": "MAIN", "is_headquarters": true }
  ]
}
```

---

### 2.4 Tenant Modules

```
GET /api/v1/tenant/modules
```

**Auth:** Required  
**Permission:** `settings.manage` (Owner only)

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "slug": "core",
      "name": "Core",
      "is_core": true,
      "is_enabled": true,
      "can_disable": false,
      "dependencies": []
    },
    {
      "id": 2,
      "slug": "pos",
      "name": "POS / Kasir",
      "is_core": false,
      "is_enabled": true,
      "can_disable": true,
      "dependencies": ["core", "inventory"]
    },
    {
      "id": 10,
      "slug": "kds",
      "name": "Kitchen Display System",
      "is_core": false,
      "is_enabled": false,
      "can_disable": true,
      "dependencies": ["kitchen"]
    }
  ]
}
```

---

```
PUT /api/v1/tenant/modules/{module_id}
```

**Auth:** Required  
**Permission:** `settings.manage` (Owner only)

**Request:**
```json
{
  "is_enabled": true
}
```

**Response 200:**
```json
{
  "message": "Module enabled successfully",
  "module": {
    "id": 10,
    "slug": "kds",
    "name": "Kitchen Display System",
    "is_enabled": true
  }
}
```

**Errors:**
- `422` — Cannot enable: dependency 'kitchen' is not enabled
- `422` — Cannot disable: dependent module 'kds' is still enabled
- `422` — Cannot disable core module

---

### 2.5 Tenant Features

```
GET /api/v1/tenant/features
```

**Auth:** Required  
**Permission:** `settings.manage` (Owner only)

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "slug": "pos.split_payment",
      "name": "Split Payment",
      "module": { "slug": "pos", "name": "POS" },
      "is_enabled": true,
      "is_owner_toggleable": true
    },
    {
      "id": 5,
      "slug": "inventory.batch_tracking",
      "name": "Batch Tracking",
      "module": { "slug": "inventory", "name": "Inventory" },
      "is_enabled": false,
      "is_owner_toggleable": true
    }
  ]
}
```

---

```
PUT /api/v1/tenant/features/{feature_id}
```

**Auth:** Required  
**Permission:** `settings.manage` (Owner only)

**Request:**
```json
{
  "is_enabled": true
}
```

**Response 200:**
```json
{
  "message": "Feature updated successfully",
  "feature": {
    "id": 5,
    "slug": "inventory.batch_tracking",
    "is_enabled": true
  }
}
```

**Errors:**
- `422` — Cannot enable: parent module 'inventory' is not enabled
- `422` — Feature is not owner-toggleable

---

### 2.6 Business Profile

```
GET /api/v1/tenant/business-profile
```

**Auth:** Required

**Response 200:**
```json
{
  "data": {
    "id": 1,
    "business_type_id": 1,
    "business_type": { "slug": "restaurant", "name": "Restaurant" },
    "business_name": "My Restaurant",
    "tax_id": null,
    "address": "Jl. Sudirman No. 1",
    "city": "Jakarta",
    "province": "DKI Jakarta",
    "postal_code": "10110",
    "phone": "+62 21 1234567",
    "email": "contact@myrestaurant.com",
    "logo": null,
    "timezone": "Asia/Jakarta",
    "currency": "IDR",
    "locale": "id"
  }
}
```

---

```
PUT /api/v1/tenant/business-profile
```

**Auth:** Required  
**Permission:** `settings.manage` (Owner only)

**Request:**
```json
{
  "business_name": "My Restaurant Updated",
  "tax_id": "01.234.567.8-901.000",
  "address": "Jl. Sudirman No. 100",
  "city": "Jakarta",
  "province": "DKI Jakarta",
  "postal_code": "10110",
  "phone": "+62 21 1234567",
  "email": "contact@myrestaurant.com"
}
```

**Response 200:**
```json
{
  "message": "Business profile updated successfully",
  "data": { ... }
}
```

---

### 2.7 Dashboard

```
GET /api/v1/dashboard
```

**Auth:** Required  
**Headers:** `X-Store-Id` (optional — if set, stats scoped to store)

**Response 200:**
```json
{
  "stats": {
    "products": 50,
    "sales_today": 12,
    "sales_today_total": "1500000.00",
    "low_stock_count": 3,
    "customers": 25,
    "suppliers": 5
  },
  "recent_sales": [
    {
      "id": 1,
      "sale_number": "INV-20260811-0001",
      "total": "150000.00",
      "status": "completed",
      "payment_status": "paid",
      "sale_date": "2026-08-11T10:30:00.000000Z",
      "items_count": 3
    }
  ],
  "low_stock_items": [
    {
      "id": 1,
      "product": { "id": 5, "name": "Coffee Beans", "sku": "CFE-001" },
      "store": { "id": 1, "name": "Main Store" },
      "quantity": 5,
      "minimum_quantity": 10
    }
  ]
}
```

---

### 2.8 Audit Logs

```
GET /api/v1/audit-logs
```

**Auth:** Required  
**Permission:** `audit.view` (Owner only)

**Query Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| entity_type | string | Filter by entity (e.g., 'Sale') |
| user_id | int | Filter by user |
| action | string | Filter by action (create, update, delete) |
| date_from | date | Filter from date |
| date_to | date | Filter to date |
| per_page | int | Default 20, max 100 |

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "user": { "id": 1, "name": "John Doe" },
      "action": "create",
      "entity_type": "Sale",
      "entity_id": 42,
      "old_values": null,
      "new_values": { "sale_number": "INV-20260811-0001", "total": "150000.00" },
      "ip_address": "192.168.1.1",
      "created_at": "2026-08-11T10:30:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8
  }
}
```

---

## 3. EXISTING ENDPOINTS (MODIFIED)

### 3.1 Auth — Login (Enhanced Response)

```
POST /api/v1/auth/login
```

**Response 200 (enhanced):**
```json
{
  "token": "1|abcdef...",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "tenant_id": 1,
    "role": { "id": 1, "slug": "owner", "name": "Owner" }
  },
  "tenant": {
    "id": 1,
    "name": "My Restaurant"
  },
  "business_profile": {
    "business_type": "restaurant"
  },
  "modules": ["core", "pos", "inventory", ...],
  "features": ["pos.split_payment", ...],
  "permissions": ["pos.use", "sales.view", ...],
  "stores": [{ "id": 1, "name": "Main Store" }]
}
```

**Note:** Frontend can use login response directly OR call `GET /api/v1/me` after login.

### 3.2 Stores (Extended Fields)

```
GET /api/v1/stores
```

**Response 200 (extended):**
```json
{
  "stores": [
    {
      "id": 1,
      "name": "Main Store",
      "code": "MAIN",
      "address": "Jl. Sudirman No. 1",
      "city": "Jakarta",
      "province": "DKI Jakarta",
      "postal_code": "10110",
      "phone": "+62 21 1234567",
      "email": "store@myrestaurant.com",
      "is_headquarters": true,
      "is_active": true,
      "settings": {
        "tax_rate": "0.11",
        "receipt_footer": "Terima kasih!"
      }
    }
  ]
}
```

---

## 4. ERROR RESPONSES

### 4.1 Standard Error Format

```json
{
  "message": "Human readable error",
  "errors": {
    "field": ["Validation error 1", "Validation error 2"]
  }
}
```

### 4.2 Common Error Codes

| Code | Scenario |
|------|----------|
| 401 | Not authenticated |
| 403 | Module not enabled / Permission denied / Feature not enabled |
| 404 | Resource not found |
| 422 | Validation error / Dependency not met |
| 429 | Rate limit exceeded |
| 500 | Server error |

### 4.3 Module-Specific Errors

```json
// Module not enabled
{
  "message": "Module not enabled",
  "error_code": "MODULE_NOT_ENABLED",
  "module": "kds"
}

// Permission denied
{
  "message": "Insufficient permissions",
  "error_code": "PERMISSION_DENIED",
  "required_permission": "sales.manage"
}

// Feature not enabled
{
  "message": "Feature not enabled",
  "error_code": "FEATURE_NOT_ENABLED",
  "feature": "pos.split_payment"
}

// Dependency not met
{
  "message": "Cannot enable module: dependency 'kitchen' is not enabled",
  "error_code": "DEPENDENCY_NOT_MET",
  "dependency": "kitchen"
}
```

---

## 5. RATE LIMITING

| Endpoint Group | Limit | Window |
|---------------|-------|--------|
| POST /auth/login | 5 | per minute |
| POST /auth/register | 5 | per minute |
| All authenticated endpoints | 60 | per minute |
| GET /dashboard | 30 | per minute |
| GET /audit-logs | 10 | per minute |

**Response 429:**
```json
{
  "message": "Too many requests",
  "retry_after": 60
}
```

---

*End of Phase 0 API*
