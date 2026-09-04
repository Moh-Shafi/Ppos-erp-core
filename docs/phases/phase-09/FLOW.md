# Phase 9 — Integration & Webhooks — Flow

**Document Status:** DRAFT  
**Created:** 2026-08-17  
**Phase:** 9 — Integration & Webhooks  

---

## 1. WEBHOOK DELIVERY FLOW

```
User completes a sale (POS checkout)
  │
  ▼
SaleService::checkout()
  ├── Creates sale, sale_items, payments in DB transaction
  ├── Commits transaction
  │
  ▼
Event::dispatch(new SaleCheckoutCompleted($sale))
  │
  ▼
EventDispatcher::handleDomainEvent('sale.created', $payload, $tenantId)
  ├── Constructs standardized event payload (see PDR §7.2)
  ├── Queries webhook_subscriptions WHERE event_type = 'sale.created'
  │   JOIN webhook_endpoints WHERE is_active = true AND tenant_id = $tenantId
  │
  ├── For each matching endpoint:
  │   ├── Generate delivery UUID
  │   ├── Compute HMAC-SHA256 signature: hash(endpoint.secret, payload + timestamp)
  │   ├── Create webhook_deliveries record (status = pending)
  │   └── Dispatch WebhookDeliveryJob($deliveryId) to 'webhooks' queue
  │
  ▼
WebhookDeliveryJob::handle()
  ├── Load delivery record (with endpoint)
  ├── Check if already delivered (idempotency: event_id + endpoint_id)
  ├── Construct HTTP POST request:
  │   URL: endpoint.url
  │   Headers:
  │     Content-Type: application/json
  │     X-Webhook-Signature: {hex_hmac}
  │     X-Webhook-Timestamp: {unix_timestamp}
  │     X-Webhook-Event: sale.created
  │     X-Webhook-Delivery: {delivery_uuid}
  │   Body: JSON event payload
  │   Timeout: 10 seconds
  │
  ├── Send request
  │
  ├── On 2xx response:
  │   ├── Update delivery: status = delivered, response_status, response_body, latency_ms
  │   └── Done
  │
  ├── On non-2xx or exception:
  │   ├── Update delivery: status = failed, response_status, error_message
  │   ├── Increment attempt_count
  │   ├── If attempt_count < 5: release job with backoff delay
  │   └── If attempt_count >= 5: status = dead_lettered
  │
  ▼
Delivery visible in webhook delivery board (frontend)
  ├── Delivered (green)
  ├── Failed (yellow, will retry)
  └── Dead-lettered (red, manual replay needed)
```

---

## 2. WEBHOOK REPLAY FLOW

```
User views dead-lettered delivery in frontend
  │
  ▼
POST /api/v1/webhooks/deliveries/{id}/replay
  │
  ▼
WebhookService::replayDelivery($deliveryId)
  ├── Verify delivery belongs to tenant
  ├── Verify delivery status is failed or dead_lettered
  ├── Create new delivery record:
  │   same event_type, same payload, same endpoint
  │   new delivery UUID, status = pending, attempt_count = 0
  │   original_delivery_id = $deliveryId
  ├── Dispatch new WebhookDeliveryJob
  └── Return new delivery record
```

---

## 3. INTEGRATION SETUP FLOW

```
User navigates to Integrations page
  │
  ▼
GET /api/v1/integrations/providers
  ├── Returns system-level provider catalog
  │   (generic_http, xendit, custom)
  │
  ▼
User selects provider, clicks "Configure"
  │
  ▼
POST /api/v1/integrations
  Body: { provider_slug, name, config, credentials }
  │
  ▼
IntegrationService::create()
  ├── Validate provider exists and is active
  ├── Validate config against provider's config_schema
  ├── Encrypt credentials: Crypt::encryptString(json_encode(credentials))
  ├── Create tenant_integrations record (status = inactive)
  └── Return integration (credentials masked)
  │
  ▼
User clicks "Test Connection"
  │
  ▼
POST /api/v1/integrations/{id}/test
  │
  ▼
IntegrationService::testConnection()
  ├── Decrypt credentials
  ├── Resolve provider adapter (ProviderAdapterRegistry)
  ├── Call adapter::healthCheck(credentials, config)
  ├── Log call to integration_logs
  ├── If success: update status = active, last_connected_at = now
  └── If failure: update status = error, last_error = message
  │
  ▼
User clicks "Activate"
  │
  ▼
POST /api/v1/integrations/{id}/activate
  ├── Set status = active
  └── Integration now available for outbound sync
```

---

## 4. API KEY GENERATION FLOW

```
User navigates to API Keys page
  │
  ▼
POST /api/v1/api-keys
  Body: { name, scopes: ["read", "write"] }
  │
  ▼
ApiKeyService::generate()
  ├── Generate random key: 'itg_' + 40 alphanumeric chars
  ├── Compute SHA-256 hash
  ├── Extract prefix (first 12 chars)
  ├── Create integration_api_keys record:
  │   key_hash = hash, key_prefix = prefix
  │   scopes = JSON, is_revoked = false
  ├── Log to audit_logs
  └── Return { id, key: 'itg_...', prefix, scopes }
  │
  ▼
Frontend displays plaintext key ONCE with copy button
  └── Warning: "This key will not be shown again"
```

---

## 5. INTEGRATION API ACCESS FLOW

```
External system sends request:
  GET /api/v1/integration/sales
  Header: X-Integration-Key: itg_abc123...
  │
  ▼
IntegrationKey middleware
  ├── Extract key from header
  ├── Compute SHA-256 hash
  ├── Query integration_api_keys WHERE key_hash = hash AND is_revoked = false
  ├── If not found: return 401 Unauthorized
  ├── If expired: return 401 Unauthorized
  ├── Set tenant context: $request->attributes->set('tenant_id', $key->tenant_id)
  ├── Update last_used_at
  └── Continue to next middleware
  │
  ▼
IntegrationScope middleware
  ├── Determine required scope for route (read or write)
  ├── Check if key has required scope in scopes JSON
  ├── If not: return 403 Forbidden
  └── Continue
  │
  ▼
IntegrationRate middleware
  ├── Rate limit: 100 requests/minute per key
  ├── If exceeded: return 429 Too Many Requests
  └── Continue
  │
  ▼
Controller (e.g., IntegrationSaleController::index)
  ├── Query sales WHERE tenant_id = $key->tenant_id
  ├── Apply filters (date range, store, status)
  ├── Return paginated results via IntegrationSaleResource
  └── Response (no tenant_id exposed in data)
```

---

## 6. INBOUND WEBHOOK RECEIVER FLOW

```
External provider sends webhook to ERP:
  POST /api/v1/integration/webhooks/{provider}
  Headers: provider-specific signature
  Body: provider-specific payload
  │
  ▼
IntegrationKey middleware (validates integration API key)
  │
  ▼
IntegrationWebhookController::receive($provider)
  ├── Resolve provider adapter
  ├── Verify provider-specific signature
  ├── Parse payload into standardized format
  ├── Check idempotency (provider event ID)
  ├── Process event:
  │   ├── Update payment status (if payment webhook)
  │   ├── Update inventory (if supplier sync)
  │   └── Create/update customer (if CRM sync)
  ├── Log to integration_logs (direction = inbound)
  ├── Dispatch domain events if needed
  └── Return 200 OK (or 422 if invalid)
```

---

## 7. OUTBOUND SYNC FLOW

```
Domain event fires (e.g., sale.created)
  │
  ▼
EventDispatcher checks for active outbound integrations
  ├── Query tenant_integrations WHERE status = active
  │   AND integration_provider has outbound capability
  │
  ├── For each matching integration:
  │   ├── Resolve provider adapter
  │   ├── Create OutboundSyncJob
  │   └── Dispatch to 'integrations' queue
  │
  ▼
OutboundSyncJob::handle()
  ├── Load integration, decrypt credentials
  ├── Construct outbound request (method, path, data)
  ├── Generate idempotency key
  ├── Call adapter::execute()
  ├── Log to integration_logs (direction = outbound)
  ├── If success: done
  └── If failure: retry with backoff (max 3 attempts)
```

---

## 8. MONITORING FLOW

```
User navigates to Integration Health dashboard
  │
  ▼
GET /api/v1/integrations/health
  ├── For each active integration:
  │   ├── Status (active, error, suspended)
  │   ├── Last connected at
  │   ├── Error count (last 24h from integration_logs)
  │   └── Last error message
  │
  ▼
GET /api/v1/webhooks/stats
  ├── Total deliveries (last 24h/7d/30d)
  ├── Success rate
  ├── Failed count
  ├── Pending count
  ├── Dead-lettered count
  └── Per-endpoint breakdown
  │
  ▼
Frontend renders:
  ├── Health cards (integration status)
  ├── Delivery success rate chart
  ├── Failed deliveries table (with replay button)
  └── Retry queue status
```

---

*End of Phase 9 Flow — DRAFT*
