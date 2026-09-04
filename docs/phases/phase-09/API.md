# Phase 9 — Integration & Webhooks — API

**Document Status:** DRAFT  
**Created:** 2026-08-17  
**Phase:** 9 — Integration & Webhooks  

---

## 1. AUTHENTICATION

### 1.1 User Token Auth (Management API)

All management endpoints (`/api/v1/integrations/*`, `/api/v1/webhooks/*`, `/api/v1/api-keys/*`) require:
```
Authorization: Bearer {sanctum_token}
```

### 1.2 Integration API Key Auth (External Access)

All integration API endpoints (`/api/v1/integration/*`) require:
```
X-Integration-Key: itg_{40_chars}
```

---

## 2. INTEGRATION MANAGEMENT

### 2.1 List Providers

```
GET /api/v1/integrations/providers
```

**Response 200:**
```json
{
  "data": [
    {
      "slug": "generic_http",
      "name": "Generic HTTP",
      "description": "Generic HTTP REST API integration",
      "config_schema": {
        "base_url": {"type": "string", "required": true},
        "timeout": {"type": "integer", "default": 30}
      },
      "is_active": true
    },
    {
      "slug": "xendit",
      "name": "Xendit Payment Gateway",
      "description": "Xendit xenPlatform integration",
      "config_schema": { ... },
      "is_active": true
    }
  ]
}
```

### 2.2 List Tenant Integrations

```
GET /api/v1/integrations
```

**Query params:** `status` (active, inactive, error, suspended), `page`, `per_page`

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "provider": "generic_http",
      "name": "Accounting Sync",
      "config": {"base_url": "https://api.example.com"},
      "credentials": "••••••••",
      "status": "active",
      "last_connected_at": "2026-08-17T10:00:00Z",
      "last_error": null,
      "created_at": "2026-08-17T09:00:00Z"
    }
  ],
  "meta": {"current_page": 1, "total": 1, "per_page": 15}
}
```

### 2.3 Create Integration

```
POST /api/v1/integrations
```

**Request:**
```json
{
  "provider_slug": "generic_http",
  "name": "Accounting Sync",
  "config": {
    "base_url": "https://api.example.com"
  },
  "credentials": {
    "api_key": "sk_live_abc123",
    "api_secret": "secret_xyz"
  }
}
```

**Response 201:**
```json
{
  "id": 1,
  "provider": "generic_http",
  "name": "Accounting Sync",
  "config": {"base_url": "https://api.example.com"},
  "credentials": "••••••••",
  "status": "inactive",
  "created_at": "2026-08-17T09:00:00Z"
}
```

**Validation errors (422):**
- `provider_slug` required, must exist in registry
- `name` required, max 100 chars
- `config.base_url` required (per provider schema)
- `credentials` required

### 2.4 Show Integration

```
GET /api/v1/integrations/{id}
```

**Response 200:** Same as list item, includes `logs` summary (last 5 calls).

### 2.5 Update Integration Config

```
PUT /api/v1/integrations/{id}
```

**Request:**
```json
{
  "name": "Updated Name",
  "config": {"base_url": "https://api.newurl.com", "timeout": 60}
}
```

**Response 200:** Updated integration.

### 2.6 Update Credentials

```
PUT /api/v1/integrations/{id}/credentials
```

**Request:**
```json
{
  "credentials": {
    "api_key": "sk_live_new_key",
    "api_secret": "new_secret"
  }
}
```

**Response 200:** Updated integration (credentials masked).

### 2.7 Test Connection

```
POST /api/v1/integrations/{id}/test
```

**Response 200:**
```json
{
  "success": true,
  "message": "Connection successful",
  "latency_ms": 245
}
```

**Response 200 (failure):**
```json
{
  "success": false,
  "message": "Authentication failed: 401 Unauthorized",
  "latency_ms": 120
}
```

### 2.8 Activate / Deactivate

```
POST /api/v1/integrations/{id}/activate
POST /api/v1/integrations/{id}/deactivate
```

**Response 200:** Updated integration with new status.

### 2.9 Delete Integration

```
DELETE /api/v1/integrations/{id}
```

**Response 204:** No content.

### 2.10 Integration Logs

```
GET /api/v1/integrations/{id}/logs
```

**Query params:** `direction` (inbound, outbound), `page`, `per_page`, `date_from`, `date_to`

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "direction": "outbound",
      "method": "POST",
      "url": "https://api.example.com/sync",
      "response_status": 200,
      "latency_ms": 150,
      "error_message": null,
      "idempotency_key": "itg-uuid-123",
      "created_at": "2026-08-17T10:00:00Z"
    }
  ],
  "meta": {...}
}
```

---

## 3. WEBHOOK ENDPOINTS

### 3.1 List Endpoints

```
GET /api/v1/webhooks/endpoints
```

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "CRM Sync",
      "url": "https://crm.example.com/webhooks/erp",
      "is_active": true,
      "subscriptions": ["sale.created", "customer.created"],
      "delivery_stats": {
        "total": 150,
        "delivered": 145,
        "failed": 3,
        "dead_lettered": 2,
        "success_rate": 96.67
      },
      "created_at": "2026-08-17T09:00:00Z"
    }
  ]
}
```

### 3.2 Create Endpoint

```
POST /api/v1/webhooks/endpoints
```

**Request:**
```json
{
  "name": "CRM Sync",
  "url": "https://crm.example.com/webhooks/erp",
  "events": ["sale.created", "customer.created"],
  "description": "Sync sales and customers to CRM"
}
```

**Response 201:**
```json
{
  "id": 1,
  "name": "CRM Sync",
  "url": "https://crm.example.com/webhooks/erp",
  "secret": "whsec_abc123...",  // shown once
  "is_active": true,
  "subscriptions": ["sale.created", "customer.created"],
  "created_at": "2026-08-17T09:00:00Z"
}
```

**Validation (422):**
- `name` required, max 100
- `url` required, must be valid HTTP/HTTPS, not private IP
- `events` required, must be array of valid event slugs
- `url` must pass SSRF check

### 3.3 Update Endpoint

```
PUT /api/v1/webhooks/endpoints/{id}
```

**Request:** Same as create (partial update allowed).

### 3.4 Delete Endpoint

```
DELETE /api/v1/webhooks/endpoints/{id}
```

**Response 204.**

### 3.5 Test Endpoint

```
POST /api/v1/webhooks/endpoints/{id}/test
```

**Response 200:**
```json
{
  "success": true,
  "response_status": 200,
  "latency_ms": 85
}
```

### 3.6 Manage Subscriptions

```
POST /api/v1/webhooks/endpoints/{id}/subscriptions
Body: {"event_type": "payment.received"}

DELETE /api/v1/webhooks/endpoints/{id}/subscriptions/{subId}
```

---

## 4. WEBHOOK DELIVERIES

### 4.1 List Deliveries

```
GET /api/v1/webhooks/deliveries
```

**Query params:** `endpoint_id`, `event_type`, `status` (pending, delivered, failed, dead_lettered), `date_from`, `date_to`, `page`, `per_page`

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "endpoint_id": 1,
      "endpoint_name": "CRM Sync",
      "event_type": "sale.created",
      "event_id": "uuid-123",
      "status": "delivered",
      "attempt_count": 1,
      "response_status": 200,
      "latency_ms": 85,
      "last_attempt_at": "2026-08-17T10:00:00Z",
      "created_at": "2026-08-17T10:00:00Z"
    }
  ],
  "meta": {...}
}
```

### 4.2 Show Delivery Detail

```
GET /api/v1/webhooks/deliveries/{id}
```

**Response 200:**
```json
{
  "id": 1,
  "endpoint": {"id": 1, "name": "CRM Sync", "url": "https://..."},
  "event_type": "sale.created",
  "event_id": "uuid-123",
  "payload": {
    "event_id": "uuid-123",
    "event_type": "sale.created",
    "timestamp": "2026-08-17T10:00:00Z",
    "data": {"sale_id": 456, "sale_number": "INV-2026-0001", ...}
  },
  "signature": "hex_hmac_signature",
  "status": "delivered",
  "attempt_count": 1,
  "request_headers": {
    "Content-Type": "application/json",
    "X-Webhook-Signature": "hex...",
    "X-Webhook-Timestamp": "1723881600",
    "X-Webhook-Event": "sale.created",
    "X-Webhook-Delivery": "uuid-123"
  },
  "response_status": 200,
  "response_body": "{\"status\":\"ok\"}",
  "latency_ms": 85,
  "error_message": null,
  "created_at": "2026-08-17T10:00:00Z",
  "last_attempt_at": "2026-08-17T10:00:00Z"
}
```

### 4.3 Replay Delivery

```
POST /api/v1/webhooks/deliveries/{id}/replay
```

**Response 201:** New delivery record (status = pending).

---

## 5. WEBHOOK EVENTS

### 5.1 List Available Events

```
GET /api/v1/webhooks/events
```

**Response 200:**
```json
{
  "data": [
    {
      "slug": "sale.created",
      "name": "Sale Created",
      "description": "Triggered when a sale is completed at checkout",
      "module": "pos",
      "payload_description": {
        "sale_id": "integer",
        "sale_number": "string",
        "store_id": "integer",
        "total": "decimal",
        "customer_id": "integer|null"
      }
    },
    ...
  ]
}
```

---

## 6. WEBHOOK STATS

### 6.1 Delivery Statistics

```
GET /api/v1/webhooks/stats
```

**Query params:** `period` (24h, 7d, 30d)

**Response 200:**
```json
{
  "period": "24h",
  "total": 150,
  "delivered": 145,
  "failed": 3,
  "pending": 0,
  "dead_lettered": 2,
  "success_rate": 96.67,
  "avg_latency_ms": 120,
  "by_event": {
    "sale.created": {"total": 80, "delivered": 78, "failed": 2},
    "payment.received": {"total": 70, "delivered": 67, "failed": 1}
  },
  "by_endpoint": {
    "1": {"name": "CRM Sync", "total": 100, "success_rate": 97.0},
    "2": {"name": "Analytics", "total": 50, "success_rate": 96.0}
  }
}
```

---

## 7. API KEYS

### 7.1 List API Keys

```
GET /api/v1/api-keys
```

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Mobile App",
      "key_prefix": "itg_abc12...",
      "scopes": ["read"],
      "last_used_at": "2026-08-17T08:00:00Z",
      "expires_at": null,
      "is_revoked": false,
      "created_at": "2026-08-17T09:00:00Z"
    }
  ]
}
```

### 7.2 Generate API Key

```
POST /api/v1/api-keys
```

**Request:**
```json
{
  "name": "Mobile App",
  "scopes": ["read"]
}
```

**Response 201:**
```json
{
  "id": 1,
  "name": "Mobile App",
  "key": "itg_abc123def456...",  // plaintext, shown once
  "key_prefix": "itg_abc12...",
  "scopes": ["read"],
  "is_revoked": false,
  "created_at": "2026-08-17T09:00:00Z"
}
```

### 7.3 Revoke API Key

```
DELETE /api/v1/api-keys/{id}
```

**Response 204.**

### 7.4 Rotate API Key

```
POST /api/v1/api-keys/{id}/rotate
```

**Response 201:**
```json
{
  "id": 2,
  "name": "Mobile App",
  "key": "itg_xyz789...",  // new plaintext
  "key_prefix": "itg_xyz78...",
  "scopes": ["read"],
  "is_revoked": false,
  "created_at": "2026-08-17T10:00:00Z"
}
```

---

## 8. INTEGRATION API (EXTERNAL ACCESS)

### 8.1 List Sales

```
GET /api/v1/integration/sales
Header: X-Integration-Key: itg_...
```

**Query params:** `store_id`, `status`, `date_from`, `date_to`, `page`, `per_page`

**Response 200:**
```json
{
  "data": [
    {
      "id": 456,
      "sale_number": "INV-2026-0001",
      "store_id": 1,
      "status": "completed",
      "total": "150000.00",
      "payment_status": "paid",
      "created_at": "2026-08-17T10:00:00Z"
    }
  ],
  "meta": {"current_page": 1, "total": 100, "per_page": 20}
}
```

### 8.2 Show Sale

```
GET /api/v1/integration/sales/{id}
```

**Response 200:** Full sale detail with items and payments (no tenant_id exposed).

### 8.3 List Products

```
GET /api/v1/integration/products
```

### 8.4 Show Product

```
GET /api/v1/integration/products/{id}
```

### 8.5 List Inventory

```
GET /api/v1/integration/inventory
Query: store_id (required)
```

### 8.6 List Customers

```
GET /api/v1/integration/customers
```

### 8.7 Show Customer

```
GET /api/v1/integration/customers/{id}
```

### 8.8 List Payments

```
GET /api/v1/integration/payments
```

### 8.9 Inbound Webhook Receiver

```
POST /api/v1/integration/webhooks/{provider}
Header: X-Integration-Key: itg_...
```

**Request body:** Provider-specific payload.

**Response 200:**
```json
{
  "status": "processed",
  "event_id": "uuid"
}
```

**Response 422 (invalid payload):**
```json
{
  "message": "Invalid payload format"
}
```

---

## 9. INTEGRATION HEALTH

### 9.1 All Integrations Health

```
GET /api/v1/integrations/health
```

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Accounting Sync",
      "provider": "generic_http",
      "status": "active",
      "last_connected_at": "2026-08-17T10:00:00Z",
      "error_count_24h": 0,
      "last_error": null
    }
  ]
}
```

---

## 10. ERROR RESPONSES

All endpoints use standard error format:

```json
{
  "message": "Error description",
  "errors": {
    "field": ["Validation error message"]
  }
}
```

| Status | Meaning |
|--------|---------|
| 401 | Invalid or missing authentication |
| 403 | Insufficient permissions or scope |
| 404 | Resource not found (or belongs to another tenant) |
| 422 | Validation error |
| 429 | Rate limit exceeded |
| 500 | Internal server error |

---

*End of Phase 9 API — DRAFT*
