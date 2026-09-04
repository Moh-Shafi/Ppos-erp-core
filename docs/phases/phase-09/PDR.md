# Phase 9 — Integration & Webhooks — PDR

**Document Status:** DRAFT  
**Created:** 2026-08-17  
**Phase:** 9 — Integration & Webhooks  
**Depends On:** Phase 5 (Payment Infrastructure), Phase 6 (Finance / Accounting), Phase 7 (Reports & Analytics)  
**Roadmap Reference:** `docs/PDR/02-PHASE_ROADMAP.md` — Phase 9 (scope revised from Subscription & Billing to Integration & Webhooks)

---

## 1. OBJECTIVE

Transform the ERP from a closed internal system into a **connectable platform** by building a robust integration and webhook layer. This enables third-party systems, external services, and custom client applications to securely interact with ERP data through a standardized API gateway, event-driven webhooks, and a pluggable provider architecture.

### 1.1 In Scope

**Integration Foundation:**
- Integration registry (system-level provider definitions)
- Provider configuration per tenant (credentials, settings, connection status)
- Enable / disable integrations per tenant
- Credential vault (encrypted storage for API keys, secrets, tokens)
- Connection health checks

**Webhook System:**
- Webhook endpoint registration (tenant configures receiver URLs)
- Event registry (system-defined events: sale.created, payment.received, inventory.low_stock, etc.)
- Event subscription (tenant subscribes endpoints to specific events)
- Signed payload delivery (HMAC-SHA256 signatures)
- Delivery attempts with retry policy (exponential backoff)
- Delivery logs (request/response, status, latency)
- Failed delivery handling (dead-letter, manual replay)

**Outbound Integrations:**
- Event-based outbound sync (ERP events trigger external API calls)
- Provider-specific adapters (generic HTTP, Xendit extensions, accounting sync)
- Request/response logging for audit trail
- Idempotency keys for outbound requests
- Rate-limit-aware retry logic

**API Integration Layer:**
- Versioned integration API (`/api/v1/integrations/*`)
- API key authentication (separate from user token auth)
- Per-key permission scopes (read-only, read-write, webhook-only)
- Rate limiting per integration key
- Tenant isolation enforced on all integration endpoints

**Monitoring:**
- Integration health dashboard (connection status, last sync, error count)
- Webhook delivery status board (success rate, failed deliveries, retry queue)
- Retry monitoring (pending retries, exhausted retries)
- Audit logs for all integration actions

### 1.2 Out of Scope (Deferred)

- Subscription & billing (original Phase 9 roadmap item) — deferred to Phase 10 or later
- Real-time WebSocket push for webhooks — MVP uses HTTP POST delivery
- MarketPlace integration (GrabFood, GoFood, Shopee) — future provider plugins
- E-commerce storefront sync — future provider plugin
- Accounting platform sync (Jurnal, Accurate) — future provider plugins
- BI tool connectors (Metabase, Superset) — future
- GraphQL API — future enhancement
- Bulk data export API — Phase 7 export covers current needs

### 1.3 Guiding Principle

> Integrations are **tenant-scoped, provider-pluggable, and event-driven**. The core ERP domain models (Sale, Product, Inventory, Payment) are never directly exposed to external systems without the integration abstraction layer. Every outbound call is logged, retried with idempotency, and rate-limited. Every inbound webhook is signature-verified and idempotent.

---

## 2. SHARED FOUNDATION ANALYSIS

### 2.1 What Exists and Is Reused

| Capability | From Phase | Usage in Phase 9 |
|-----------|-----------|------------------|
| Multi-tenant isolation | Phase 0 | All integration data is tenant-scoped |
| RBAC + module/feature system | Phase 0 | New `integrations` module + permissions |
| Payment webhooks (Xendit) | Phase 5 | Pattern reused for generic webhook system; Xendit becomes a provider plugin |
| Audit logs | Phase 0 | Integration actions logged to audit table |
| Sale/Payment/Inventory events | Phases 1-5 | Source events for webhook triggers |
| API authentication (Sanctum tokens) | Phase 0 | Extended with integration API keys |
| Rate limiting middleware | Phase 0 | Extended for per-key rate limiting |

### 2.2 What Is New

| Component | Purpose |
|-----------|---------|
| Integration Registry | System-level provider definitions (slug, name, config_schema) |
| Tenant Integrations | Per-tenant provider configuration with encrypted credentials |
| Webhook Endpoints | Tenant-configured receiver URLs with event subscriptions |
| Webhook Deliveries | Delivery attempt records with retry tracking |
| Integration API Keys | Separate auth tokens for external system access |
| Event Dispatcher | Centralized event broadcast to subscribed webhook endpoints |
| Provider Adapters | Pluggable adapters for outbound integration calls |
| Integration Logs | Request/response audit trail for all integration calls |

### 2.3 Architecture Dependencies

```
Phase 9 — Integration & Webhooks
  │
  ├── Integration Foundation
  │     └── Module/Feature system (Phase 0)
  │
  ├── Webhook System
  │     ├── Payment webhook pattern (Phase 5)
  │     └── Event sources: Sales (Phase 4), Payments (Phase 5), Inventory (Phase 1-2)
  │
  ├── Outbound Integrations
  │     ├── HTTP client infrastructure
  │     └── Idempotency pattern (Phase 5 payments)
  │
  ├── API Integration Layer
  │     ├── Sanctum auth (Phase 0)
  │     ├── Rate limiting (Phase 0)
  │     └── Tenant scope (Phase 0)
  │
  └── Monitoring
        ├── Audit logs (Phase 0)
        └── Dashboard widgets (Phase 7)
```

---

## 3. ARCHITECTURE OVERVIEW

```
Shared ERP Foundation (Phases 0-8)
  │
  ├── Domain Events (sale.created, payment.received, inventory.low_stock, etc.)
  │
  ├── Phase 9 — Integration Layer
  │     │
  │     ├── Integration Registry (system-level)
  │     │     ├── Provider definitions (generic_http, xendit, custom)
  │     │     └── Config schemas (per provider)
  │     │
  │     ├── Tenant Integrations (per-tenant)
  │     │     ├── Provider configuration
  │     │     ├── Encrypted credentials vault
  │     │     ├── Connection status & health
  │     │     └── Enable / disable toggle
  │     │
  │     ├── Webhook Engine
  │     │     ├── Endpoint registration (URL, events, secret)
  │     │     ├── Event dispatcher (listen → enqueue → deliver)
  │     │     ├── HMAC-SHA256 signature
  │     │     ├── Delivery with retry (exponential backoff)
  │     │     ├── Delivery logs (request, response, status, latency)
  │     │     └── Dead-letter + manual replay
  │     │
  │     ├── Outbound Sync
  │     │     ├── Provider adapters (HTTP, Xendit, custom)
  │     │     ├── Idempotency keys
  │     │     ├── Rate-limit-aware retries
  │     │     └── Request/response logging
  │     │
  │     ├── Integration API Gateway
  │     │     ├── API key authentication
  │     │     ├── Permission scopes (read, write, webhook)
  │     │     ├── Rate limiting per key
  │     │     └── Tenant isolation
  │     │
  │     └── Monitoring
  │           ├── Integration health dashboard
  │           ├── Webhook delivery board
  │           ├── Retry queue monitor
  │           └── Audit trail
  │
  └── Frontend
        ├── Integrations page (configure providers)
        ├── Webhooks page (manage endpoints, view deliveries)
        ├── API Keys page (generate, manage, revoke)
        └── Integration Health dashboard
```

### 3.1 Design Rules

1. **Tenant isolation first** — every integration, webhook endpoint, API key, and delivery log is scoped to `tenant_id`
2. **No direct domain model exposure** — integration API uses resource classes that whitelist fields
3. **Encrypted credentials** — all provider credentials stored AES-256 encrypted; never returned in API responses
4. **Idempotent delivery** — webhook deliveries use event ID + endpoint ID composite key to prevent duplicates
5. **Signature verification** — every webhook payload signed with HMAC-SHA256 using endpoint-specific secret
6. **Retry with backoff** — failed deliveries retried at 30s, 2m, 10m, 1h, 6h (configurable); max 5 attempts
7. **Dead-letter on exhaustion** — after max retries, delivery marked `dead_lettered`; manual replay available
8. **Provider-pluggable** — outbound adapters implement `ProviderAdapterInterface`; new providers added via registration
9. **Event-driven, not polling** — webhooks dispatched via Laravel events; no cron-based polling for delivery
10. **Queue-based delivery** — webhook delivery dispatched to queue for async processing; doesn't block domain operations

---

## 4. DATABASE CHANGES

### 4.1 New Tables

```sql
-- Integration providers (system-level registry, seeded)
integration_providers
  id, slug (unique), name, description, config_schema (JSON),
  is_active, is_system, timestamps

-- Tenant integrations (per-tenant provider configuration)
tenant_integrations
  id, tenant_id, integration_provider_id,
  name, config (JSON, non-sensitive settings),
  encrypted_credentials (TEXT, AES-256 encrypted),
  status (inactive, active, error, suspended),
  last_connected_at, last_error (nullable),
  timestamps

-- Webhook endpoints (tenant-configured receivers)
webhook_endpoints
  id, tenant_id, name, url, secret (unique per endpoint),
  is_active, description (nullable),
  timestamps

-- Webhook event subscriptions (which events each endpoint receives)
webhook_subscriptions
  id, webhook_endpoint_id, event_type,
  timestamps

-- Webhook deliveries (individual delivery attempts)
webhook_deliveries
  id, tenant_id, webhook_endpoint_id, event_type,
  event_id (UUID, unique per event occurrence),
  payload (JSON), signature,
  status (pending, delivered, failed, dead_lettered, replayed),
  attempt_count, last_attempt_at,
  request_headers (JSON), response_status (nullable),
  response_body (TEXT, nullable), error_message (nullable),
  latency_ms (nullable),
  timestamps

-- Integration API keys (for external system authentication)
integration_api_keys
  id, tenant_id, name, key_hash (unique),
  key_prefix (first 8 chars, for identification),
  scopes (JSON: ["read", "write", "webhook"]),
  last_used_at, expires_at (nullable),
  is_revoked, timestamps

-- Integration logs (audit trail for outbound calls)
integration_logs
  id, tenant_id, tenant_integration_id,
  direction (outbound, inbound),
  method, url, request_headers (JSON), request_body (TEXT, nullable),
  response_status, response_body (TEXT, nullable),
  latency_ms, error_message (nullable),
  idempotency_key (nullable),
  timestamps

-- Webhook events registry (system-defined available events)
webhook_events
  id, slug (unique), name, description, module,
  payload_description, is_active, timestamps
```

### 4.2 Modified Tables

- `modules` — add `integrations` module (seeded by ModuleSeeder)
- `features` — add integration-specific feature flags (seeded)
- `roles` / `permissions` — add integration permissions (seeded by RbacSeeder)

### 4.3 Indexes

- `tenant_integrations`: (tenant_id, integration_provider_id) unique, (tenant_id, status)
- `webhook_endpoints`: (tenant_id, is_active)
- `webhook_deliveries`: (tenant_id, status, last_attempt_at), (webhook_endpoint_id, event_id)
- `integration_api_keys`: (key_hash) unique, (tenant_id, is_revoked)
- `integration_logs`: (tenant_id, created_at), (tenant_integration_id, direction)

---

## 5. API DESIGN

### 5.1 Integration Management

```
Base: /api/v1

# Integration Providers (system registry, read-only)
GET    /integrations/providers              — list available providers
GET    /integrations/providers/{slug}       — show provider config schema

# Tenant Integrations
GET    /integrations                        — list tenant's integrations
POST   /integrations                        — create integration (provider_slug, config, credentials)
GET    /integrations/{id}                   — show integration (credentials masked)
PUT    /integrations/{id}                   — update integration config
DELETE /integrations/{id}                   — delete integration
POST   /integrations/{id}/activate          — activate integration
POST   /integrations/{id}/deactivate        — deactivate integration
POST   /integrations/{id}/test              — test connection (health check)
GET    /integrations/{id}/logs              — integration call logs (paginated)
```

### 5.2 Webhook Endpoints

```
# Webhook Endpoints
GET    /webhooks/endpoints                  — list endpoints
POST   /webhooks/endpoints                  — create endpoint (url, name, events[])
GET    /webhooks/endpoints/{id}             — show endpoint with subscriptions
PUT    /webhooks/endpoints/{id}             — update endpoint
DELETE /webhooks/endpoints/{id}             — delete endpoint
POST   /webhooks/endpoints/{id}/activate    — activate endpoint
POST   /webhooks/endpoints/{id}/deactivate  — deactivate endpoint
POST   /webhooks/endpoints/{id}/test        — send test payload

# Webhook Subscriptions
GET    /webhooks/endpoints/{id}/subscriptions       — list subscriptions
POST   /webhooks/endpoints/{id}/subscriptions       — subscribe to events
DELETE /webhooks/endpoints/{id}/subscriptions/{subId} — unsubscribe

# Webhook Deliveries
GET    /webhooks/deliveries                — list deliveries (filter by endpoint, event, status)
GET    /webhooks/deliveries/{id}           — show delivery detail (request, response, logs)
POST   /webhooks/deliveries/{id}/replay    — manually replay a delivery

# Webhook Events Registry
GET    /webhooks/events                     — list available event types
```

### 5.3 Integration API Keys

```
# API Keys (for external systems)
GET    /api-keys                            — list API keys (masked)
POST   /api-keys                            — generate new key (returns plaintext once)
DELETE /api-keys/{id}                       — revoke key
POST   /api-keys/{id}/rotate                — rotate key (returns new plaintext)
```

### 5.4 Integration API (External System Access)

```
Base: /api/v1/integration

# Auth: X-Integration-Key header
# All endpoints tenant-scoped via key → tenant_id mapping

# Sales (read-only for external systems)
GET    /integration/sales                   — list sales (filters, pagination)
GET    /integration/sales/{id}              — show sale detail

# Products
GET    /integration/products                — list products
GET    /integration/products/{id}           — show product

# Inventory
GET    /integration/inventory               — list inventory (filter by store)

# Customers
GET    /integration/customers               — list customers
GET    /integration/customers/{id}          — show customer

# Payments
GET    /integration/payments                — list payments

# Inbound webhook receiver (for external → ERP events)
POST   /integration/webhooks/{provider}     — receive webhook from external provider
```

### 5.5 Monitoring

```
# Integration Health
GET    /integrations/health                 — all integrations health summary
GET    /integrations/{id}/health            — specific integration health

# Webhook Stats
GET    /webhooks/stats                      — delivery stats (success rate, failures, pending)
```

---

## 6. RBAC & FEATURES

### 6.1 New Module

- `integrations` — Integration & Webhooks module

### 6.2 New Permissions

- `integrations.view` — view integrations, logs, health
- `integrations.manage` — create/update/delete integrations, test connections
- `webhooks.view` — view webhook endpoints, deliveries
- `webhooks.manage` — create/update/delete endpoints, replay deliveries
- `apikeys.view` — view API keys
- `apikeys.manage` — generate, revoke, rotate API keys

### 6.3 New Feature Flags

- `integrations.outbound` — enable outbound integration sync
- `integrations.inbound` — enable inbound webhook receiver
- `webhooks.custom_events` — allow tenant to define custom event filters
- `apikeys.scoped` — enable per-key permission scopes

### 6.4 Role-Permission Mapping

| Permission | Owner | Manager | Cashier | Staff |
|-----------|-------|---------|---------|-------|
| integrations.view | ✅ | ✅ | ❌ | ❌ |
| integrations.manage | ✅ | ❌ | ❌ | ❌ |
| webhooks.view | ✅ | ✅ | ❌ | ❌ |
| webhooks.manage | ✅ | ❌ | ❌ | ❌ |
| apikeys.view | ✅ | ✅ | ❌ | ❌ |
| apikeys.manage | ✅ | ❌ | ❌ | ❌ |

---

## 7. EVENT REGISTRY

### 7.1 System-Defined Events

| Event Slug | Trigger | Payload Summary |
|-----------|---------|-----------------|
| `sale.created` | Sale checkout completed | sale_id, sale_number, store_id, total, customer_id |
| `sale.cancelled` | Sale cancelled | sale_id, sale_number, cancelled_by, reason |
| `sale.refunded` | Sale refunded | sale_id, refund_id, amount, items |
| `payment.received` | Payment recorded | payment_id, sale_id, method, amount, gateway |
| `payment.settled` | Gateway settlement confirmed | payment_id, settlement_amount, settled_at |
| `inventory.low_stock` | Stock drops below threshold | product_id, store_id, current_qty, threshold |
| `inventory.adjusted` | Manual stock adjustment | product_id, store_id, delta, reason |
| `inventory.transferred` | Inter-store transfer completed | product_id, from_store, to_store, quantity |
| `purchase.received` | Purchase order received | purchase_id, supplier_id, items |
| `customer.created` | New customer registered | customer_id, name, phone |
| `customer.updated` | Customer profile updated | customer_id, changed_fields |
| `reservation.created` | New reservation (Phase 8) | reservation_id, date, party_size |
| `appointment.created` | New appointment (Phase 8) | appointment_id, date, service |
| `module.enabled` | Tenant enabled a module | module_slug |
| `module.disabled` | Tenant disabled a module | module_slug |

### 7.2 Event Payload Structure

```json
{
  "event_id": "uuid-v4",
  "event_type": "sale.created",
  "timestamp": "2026-08-17T12:00:00Z",
  "tenant_id": 123,
  "data": {
    "sale_id": 456,
    "sale_number": "INV-2026-0001",
    "store_id": 1,
    "total": "150000.00",
    "customer_id": null
  },
  "metadata": {
    "source": "pos",
    "store_name": "Main Store"
  }
}
```

---

## 8. FRONTEND UX

### 8.1 Integrations Page

- **Provider Catalog**: card grid of available integration providers
- **Active Integrations**: list of configured integrations with status badge (active, error, suspended)
- **Integration Detail**: config form, credentials (masked), connection test button, log viewer
- **Health Dashboard**: summary cards showing connection status, last sync time, error count

### 8.2 Webhooks Page

- **Endpoint List**: table with URL, status, event subscriptions, delivery success rate
- **Endpoint Detail**: subscription manager, recent deliveries, delivery detail viewer
- **Delivery Board**: filterable list of deliveries with status (delivered, failed, dead_lettered), replay button
- **Delivery Detail**: full request/response inspection, headers, payload, latency chart
- **Event Catalog**: reference list of available events with payload descriptions

### 8.3 API Keys Page

- **Key List**: table with name, prefix, scopes, last used, status (active/revoked)
- **Generate Key**: modal with name, scope selection; plaintext key shown once with copy button
- **Revoke Confirmation**: destructive action with confirmation dialog

### 8.4 Navigation

New nav group "Integrations" (gated by `integrations` module):
- Integrations (provider config)
- Webhooks (endpoints, deliveries)
- API Keys (external access keys)

---

## 9. SECURITY

### 9.1 Credential Storage

- All provider credentials encrypted with AES-256-CBC using `APP_KEY` derivative
- Credentials never returned in API responses (masked as `••••••••`)
- Credential update requires re-entering full credential set (no partial updates)

### 9.2 Webhook Signature Verification

```
Signature = HMAC-SHA256(endpoint.secret, payload + timestamp)
Header: X-Webhook-Signature: <hex_signature>
Header: X-Webhook-Timestamp: <unix_timestamp>
Header: X-Webhook-Event: <event_type>
Header: X-Webhook-Delivery: <delivery_uuid>
```

### 9.3 API Key Security

- Keys generated as `itg_` + 40 random alphanumeric chars
- Stored as SHA-256 hash (never plaintext in DB)
- Key prefix (first 8 chars) stored for identification
- Rotation generates new key, marks old as revoked
- Revoked keys immediately rejected

### 9.4 Rate Limiting

- Integration API: 100 requests/minute per key (configurable)
- Webhook delivery: 10 concurrent deliveries per endpoint
- Outbound integration calls: provider-specific rate limits

### 9.5 Tenant Isolation

- Every query scoped by `tenant_id` from API key mapping
- Webhook endpoints only receive events from their own tenant
- Integration logs scoped to tenant
- No cross-tenant data access possible via integration API

---

## 10. IMPLEMENTATION ORDER

### Phase 9 — Complete Cycle

1. **Backend: Migrations** — all new tables
2. **Backend: Models** — Eloquent models with relationships, encryption traits
3. **Backend: Services** — IntegrationService, WebhookService, EventDispatcher, ProviderAdapter, ApiKeyService
4. **Backend: Controllers + Routes** — REST endpoints with module/permission middleware
5. **Backend: Seeders** — ModuleSeeder (integrations module), RbacSeeder (permissions), WebhookEventSeeder (event registry)
6. **Backend: Event Integration** — wire domain events to EventDispatcher
7. **Backend: Queue Jobs** — WebhookDeliveryJob with retry/backoff, OutboundSyncJob
8. **Backend: Tests** — feature tests for all modules
9. **Frontend: Pages** — Integrations, Webhooks, API Keys, Health Dashboard
10. **Frontend: Services** — API clients
11. **Frontend: Navigation** — module-aware nav items
12. **E2E Tests** — Playwright specs for primary flows
13. **Full Regression** — backend + frontend
14. **Security Audit** — tenant isolation, key security, webhook signatures
15. **Documentation** — finalize all docs
16. **Phase 9 CLOSED**

---

## 11. ACCEPTANCE CRITERIA

### Integration Foundation
- [ ] Integration provider registry seeded with at least `generic_http` and `xendit` providers
- [ ] Tenant can create, update, delete integrations
- [ ] Credentials encrypted at rest, never exposed in API responses
- [ ] Connection test (health check) works for generic HTTP provider
- [ ] Integration logs capture all outbound/inbound calls

### Webhook System
- [ ] Tenant can register webhook endpoints with event subscriptions
- [ ] Domain events (sale.created, payment.received, etc.) trigger webhook delivery
- [ ] Payloads signed with HMAC-SHA256, signature verifiable
- [ ] Successful delivery recorded with status, response, latency
- [ ] Failed delivery retried with exponential backoff (5 attempts max)
- [ ] Exhausted deliveries marked `dead_lettered`
- [ ] Manual replay of failed/dead-lettered deliveries works
- [ ] Duplicate event delivery prevented (idempotency via event_id)

### Outbound Integrations
- [ ] Provider adapter interface defined and implemented for generic HTTP
- [ ] Outbound calls include idempotency key
- [ ] Rate-limit-aware retry logic works
- [ ] Request/response logged to integration_logs

### API Integration Layer
- [ ] API key generation returns plaintext once, stores hash
- [ ] Integration API endpoints accessible with `X-Integration-Key` header
- [ ] Permission scopes enforced (read-only key cannot write)
- [ ] Rate limiting per key enforced
- [ ] Tenant isolation on all integration API endpoints

### Monitoring
- [ ] Integration health dashboard shows status, last sync, errors
- [ ] Webhook delivery stats show success rate, pending, failed
- [ ] Retry queue visible with pending/processing counts
- [ ] Audit trail for all integration actions

### Cross-Cutting
- [ ] All existing tests pass (1153+ regression)
- [ ] New Phase 9 backend tests pass
- [ ] New Phase 9 E2E tests pass
- [ ] Tenant isolation on every endpoint
- [ ] Permission and feature flags enforced (backend + frontend)
- [ ] Phase 8 baseline remains green
- [ ] Documentation: ARCHITECTURE, API, FLOW, SECURITY, TESTING, Final Report

---

## 12. DEPENDENCIES

- Phase 0 (Module/Feature system) — CLOSED ✅
- Phase 5 (Payment Infrastructure) — CLOSED ✅ (webhook pattern, Xendit provider)
- Phase 6 (Finance / Accounting) — CLOSED ✅
- Phase 7 (Reports & Analytics) — CLOSED ✅ (dashboard widgets)
- Phase 8 (Business-Specific Modules) — CLOSED ✅

---

## 13. RISKS & MITIGATIONS

| Risk | Mitigation |
|------|------------|
| Webhook delivery blocks domain operations | Queue-based async delivery; events dispatched to queue, not synchronous |
| Credential leakage | AES-256 encryption at rest; never returned in responses; rotation support |
| External endpoint is slow/unreachable | Timeout (10s), retry with backoff, dead-letter after max attempts |
| API key abuse | Rate limiting per key, scope enforcement, revocation, rotation |
| Event payload schema changes break consumers | Versioned payload structure; `event_type` includes version suffix when breaking changes occur |
| Queue overflow from high event volume | Batch delivery for high-volume events; configurable event filtering per endpoint |
| Duplicate deliveries | Idempotency via `event_id` + `endpoint_id` composite unique key |
| Tenant configures endpoint to internal IPs (SSRF) | URL validation: block private IP ranges, localhost, metadata endpoints |

---

## 14. DEFINITION OF DONE

- PDR approved.
- All architecture, API, flow, security, and testing documents written.
- Implementation complete for Integration Foundation, Webhook System, Outbound Integrations, API Gateway, and Monitoring.
- Backend tests, E2E tests, full backend regression, and security gate passing.
- Final report and phase marked CLOSED.
- Phase 8 baseline remains green (1153 tests + 5 E2E).

---

*End of Phase 9 PDR — DRAFT*
