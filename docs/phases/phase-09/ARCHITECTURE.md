# Phase 9 — Integration & Webhooks — Architecture

**Document Status:** DRAFT  
**Created:** 2026-08-17  
**Phase:** 9 — Integration & Webhooks  
**Depends On:** PDR Phase 9  

---

## 1. ARCHITECTURE CONTEXT

Phase 9 adds an **integration abstraction layer** on top of the existing ERP domain. It does not modify core domain models (Sale, Product, Inventory, Payment) but listens to their events and exposes them through a controlled gateway.

### 1.1 Layer Diagram

```
┌─────────────────────────────────────────────────────────┐
│                    External Systems                      │
│  (Third-party apps, BI tools, accounting sync, custom)   │
└──────────┬──────────────────────────┬───────────────────┘
           │                          │
     Inbound API              Outbound Webhooks
  (X-Integration-Key)         (HMAC-SHA256 signed)
           │                          │
┌──────────▼──────────┐  ┌───────────▼───────────────────┐
│  Integration API    │  │  Webhook Delivery Engine       │
│  Gateway            │  │  (Queue-based, retry, backoff) │
│  - Auth (API key)   │  │  - Event dispatcher            │
│  - Scopes           │  │  - Signature generation        │
│  - Rate limiting    │  │  - Delivery logging            │
│  - Tenant scope     │  │  - Dead-letter handling        │
└──────────┬──────────┘  └───────────▲───────────────────┘
           │                          │
┌──────────▼──────────────────────────┴───────────────────┐
│              Integration Service Layer                    │
│                                                           │
│  IntegrationService    WebhookService    ApiKeyService    │
│  - CRUD integrations   - CRUD endpoints  - Generate keys  │
│  - Encrypt creds       - Dispatch events - Validate keys  │
│  - Test connections    - Retry logic     - Revoke/rotate  │
│  - Log calls           - Replay          - Scope check    │
│                                                           │
│  EventDispatcher          ProviderAdapterRegistry         │
│  - Listen domain events  - Resolve adapter by slug        │
│  - Map to webhook subs   - Execute outbound calls         │
│  - Enqueue deliveries    - Idempotency keys               │
│  - Filter by tenant      - Rate-limit aware               │
└──────────┬──────────────────────────────────────────────┘
           │
┌──────────▼──────────────────────────────────────────────┐
│              ERP Domain Layer (Phases 0-8)                │
│                                                           │
│  SaleService → sale.created event                        │
│  PaymentService → payment.received event                 │
│  InventoryService → inventory.low_stock event            │
│  PurchaseService → purchase.received event               │
│  CustomerService → customer.created event                │
│  Phase 8 modules → reservation/appointment events        │
└──────────────────────────────────────────────────────────┘
```

---

## 2. COMPONENT DESIGN

### 2.1 IntegrationService

**Responsibility:** Manage tenant integrations (provider config, credentials, health).

```php
class IntegrationService
{
    // Create integration with encrypted credentials
    public function create(int $tenantId, string $providerSlug, array $config, array $credentials): TenantIntegration;
    
    // Update config (non-sensitive)
    public function updateConfig(int $integrationId, array $config): TenantIntegration;
    
    // Update credentials (requires full credential set)
    public function updateCredentials(int $integrationId, array $credentials): TenantIntegration;
    
    // Test connection (calls provider adapter healthCheck)
    public function testConnection(int $integrationId): array;
    
    // Activate / deactivate
    public function activate(int $integrationId): TenantIntegration;
    public function deactivate(int $integrationId): TenantIntegration;
    
    // Get decrypted credentials (internal use only)
    public function getCredentials(int $integrationId): array;
    
    // Log integration call
    public function logCall(int $integrationId, string $direction, array $request, ?array $response, ?string $error): IntegrationLog;
}
```

**Encryption:** Uses Laravel's `Crypt::encryptString()` for credential fields. Credentials stored as JSON-then-encrypted blob in `encrypted_credentials` column.

### 2.2 WebhookService

**Responsibility:** Manage webhook endpoints, subscriptions, and delivery lifecycle.

```php
class WebhookService
{
    // Endpoint CRUD
    public function createEndpoint(int $tenantId, string $name, string $url, array $events): WebhookEndpoint;
    public function updateEndpoint(int $endpointId, array $data): WebhookEndpoint;
    public function deleteEndpoint(int $endpointId): void;
    
    // Subscription management
    public function subscribe(int $endpointId, string $eventType): WebhookSubscription;
    public function unsubscribe(int $subscriptionId): void;
    
    // Delivery management
    public function getDeliveries(int $tenantId, array $filters): LengthAwarePaginator;
    public function replayDelivery(int $deliveryId): WebhookDelivery;
    
    // Test endpoint
    public function sendTestPayload(int $endpointId): array;
    
    // Stats
    public function getDeliveryStats(int $tenantId): array;
}
```

### 2.3 EventDispatcher

**Responsibility:** Listen to domain events, match to webhook subscriptions, enqueue deliveries.

```php
class EventDispatcher
{
    // Called by Laravel event system
    public function handleDomainEvent(string $eventType, array $payload, int $tenantId): void;
    
    // Find matching subscriptions
    // Create delivery records (pending)
    // Dispatch WebhookDeliveryJob to queue
}
```

**Flow:**
1. Domain event fires (e.g., `SaleCheckoutCompleted`)
2. EventDispatcher receives event, constructs standardized payload
3. Queries `webhook_subscriptions` for matching `event_type` + active endpoint
4. Creates `webhook_deliveries` record with `pending` status
5. Dispatches `WebhookDeliveryJob` to queue

### 2.4 WebhookDeliveryJob

**Responsibility:** Execute HTTP POST to endpoint URL with signed payload, record result.

```php
class WebhookDeliveryJob implements ShouldQueue
{
    public $tries = 5;          // Max retry attempts
    public $backoff = [30, 120, 600, 3600, 21600]; // 30s, 2m, 10m, 1h, 6h
    
    public function handle(HttpClient $http, WebhookService $service): void
    {
        // 1. Load delivery record
        // 2. Construct payload with signature
        // 3. POST to endpoint URL (10s timeout)
        // 4. Record response status, body, latency
        // 5. If 2xx: mark delivered
        // 6. If non-2xx or exception: mark failed, will retry
        // 7. If max attempts reached: mark dead_lettered
    }
}
```

### 2.5 ProviderAdapterInterface

**Responsibility:** Standardized interface for outbound integration providers.

```php
interface ProviderAdapterInterface
{
    // Health check (test connection)
    public function healthCheck(array $credentials, array $config): array;
    
    // Execute outbound call
    public function execute(string $method, string $path, array $data, array $credentials, array $config, ?string $idempotencyKey = null): array;
    
    // Provider slug
    public static function slug(): string;
}
```

**Built-in adapters:**
- `GenericHttpAdapter` — generic HTTP REST calls
- `XenditAdapter` — extends existing Xendit payment integration (Phase 5)

### 2.6 ApiKeyService

**Responsibility:** Generate, validate, revoke integration API keys.

```php
class ApiKeyService
{
    // Generate new key (returns plaintext once)
    public function generate(int $tenantId, string $name, array $scopes): array;
    
    // Validate key from request header
    public function validate(string $key): ?IntegrationApiKey;
    
    // Revoke key
    public function revoke(int $keyId): void;
    
    // Rotate key (revoke old, generate new)
    public function rotate(int $keyId): array;
    
    // Check scope
    public function hasScope(IntegrationApiKey $key, string $scope): bool;
}
```

**Key format:** `itg_` + 40 random alphanumeric characters (base62).  
**Storage:** SHA-256 hash of full key stored in `key_hash`. First 12 chars stored in `key_prefix` for identification.

---

## 3. EVENT ARCHITECTURE

### 3.1 Event Flow

```
Domain Operation (e.g., SaleService::checkout)
  │
  ├── DB Transaction commits
  │
  ├── Laravel Event dispatched
  │     └── SaleCheckoutCompleted(sale)
  │
  ├── EventDispatcher listener
  │     ├── Constructs standardized payload
  │     ├── Queries webhook_subscriptions for 'sale.created'
  │     ├── For each matching endpoint:
  │     │     ├── Creates webhook_deliveries record (pending)
  │     │     └── Dispatches WebhookDeliveryJob
  │     └── Logs to integration_logs (inbound direction for event)
  │
  └── WebhookDeliveryJob (async, queued)
        ├── Loads delivery record
        ├── Constructs HTTP request:
        │     POST {endpoint.url}
        │     Headers:
        │       Content-Type: application/json
        │       X-Webhook-Signature: {hmac}
        │       X-Webhook-Timestamp: {unix}
        │       X-Webhook-Event: sale.created
        │       X-Webhook-Delivery: {delivery_uuid}
        │     Body: {event_payload}
        ├── Sends request (10s timeout)
        ├── Records response (status, body, latency)
        ├── If 2xx: status = delivered
        ├── If non-2xx: status = failed, release with backoff
        └── If max tries: status = dead_lettered
```

### 3.2 Event Registration

Events are registered in `WebhookEventSeeder`:

```php
$events = [
    ['slug' => 'sale.created', 'name' => 'Sale Created', 'module' => 'pos', ...],
    ['slug' => 'sale.cancelled', 'name' => 'Sale Cancelled', 'module' => 'pos', ...],
    ['slug' => 'payment.received', 'name' => 'Payment Received', 'module' => 'payments', ...],
    // ... (full list in PDR §7.1)
];
```

### 3.3 Event-to-Listener Mapping

Domain events are mapped to webhook event types via a central registry:

```php
// config/integration.php
return [
    'event_map' => [
        \App\Events\SaleCheckoutCompleted::class => 'sale.created',
        \App\Events\SaleCancelled::class => 'sale.cancelled',
        \App\Events\PaymentReceived::class => 'payment.received',
        \App\Events\InventoryLowStock::class => 'inventory.low_stock',
        // ...
    ],
];
```

---

## 4. DATA MODEL

### 4.1 ER Diagram

```
integration_providers (system)
  │ 1
  │
  │ many
tenant_integrations
  │ 1                          │ 1
  │ many                       │ many
integration_logs          webhook_endpoints
                               │ 1
                               │ many
                          webhook_subscriptions
                               │ (event_type filter)

webhook_events (system)    webhook_deliveries
  │                          │ 1
  │ (reference)              │ many
  │                     (delivery attempts)
  │
integration_api_keys
  │ (tenant-scoped auth)
```

### 4.2 Model Relationships

```
TenantIntegration
  belongsTo Tenant
  belongsTo IntegrationProvider
  hasMany IntegrationLog

WebhookEndpoint
  belongsTo Tenant
  hasMany WebhookSubscription
  hasMany WebhookDelivery

WebhookSubscription
  belongsTo WebhookEndpoint

WebhookDelivery
  belongsTo Tenant
  belongsTo WebhookEndpoint

IntegrationApiKey
  belongsTo Tenant

IntegrationLog
  belongsTo Tenant
  belongsTo TenantIntegration

WebhookEvent (system reference, no FK relationships)
IntegrationProvider (system reference, no FK relationships)
```

---

## 5. MIDDLEWARE STACK

### 5.1 Integration API Middleware

```
Route::prefix('integration')->middleware(['integration.key', 'integration.scope', 'integration.rate'])->group(...);
```

| Middleware | Purpose |
|-----------|---------|
| `integration.key` | Validate `X-Integration-Key` header, load key record, set tenant context |
| `integration.scope` | Check key has required scope for endpoint (read/write) |
| `integration.rate` | Rate limit per key (100 req/min default) |

### 5.2 Module Middleware

All integration management routes wrapped in `module:integrations` middleware.

### 5.3 Permission Middleware

- `permission:integrations.manage` — create/update/delete integrations
- `permission:webhooks.manage` — create/update/delete webhook endpoints
- `permission:apikeys.manage` — generate/revoke API keys

---

## 6. QUEUE CONFIGURATION

### 6.1 Queue Connections

```php
// config/queue.php — add 'webhooks' connection
'webhooks' => [
    'driver' => env('QUEUE_CONNECTION', 'database'), // database for MVP
    'queue' => 'webhooks',
    'retry_after' => 300,
    'after_commit' => true, // Critical: only dispatch after DB commit
],
```

### 6.2 Job Configuration

| Job | Queue | Tries | Backoff | Timeout |
|-----|-------|-------|---------|---------|
| `WebhookDeliveryJob` | webhooks | 5 | 30s, 2m, 10m, 1h, 6h | 30s |
| `OutboundSyncJob` | integrations | 3 | 60s, 5m, 30m | 60s |

### 6.3 Queue Worker Command

```bash
php artisan queue:work --queue=webhooks,integrations,default --tries=1 --max-time=3600
```

Note: `--tries=1` at worker level; retry logic handled inside job via `backoff` property.

---

## 7. CREDENTIAL ENCRYPTION

### 7.1 Encryption Flow

```
User submits credentials (API request)
  │
  ├── IntegrationService::create()
  │     ├── json_encode(credentials_array)
  │     ├── Crypt::encryptString(json)
  │     └── Store in encrypted_credentials column
  │
  └── When needed (outbound call):
        ├── Crypt::decryptString(encrypted_credentials)
        ├── json_decode → credentials_array
        └── Pass to ProviderAdapter
```

### 7.2 Key Derivation

Laravel's `Crypt` facade uses `APP_KEY` from `.env` with AES-256-CBC cipher. No custom key management needed for MVP.

### 7.3 Credential Masking in Responses

```php
// TenantIntegrationResource
public function toArray($request): array
{
    return [
        'id' => $this->id,
        'provider' => $this->provider->slug,
        'name' => $this->name,
        'config' => $this->config,
        'credentials' => $this->hasCredentials() ? '••••••••' : null,
        'status' => $this->status,
        // encrypted_credentials NEVER included
    ];
}
```

---

## 8. SSRF PROTECTION

### 8.1 URL Validation

Before creating or updating a webhook endpoint, the URL is validated:

```php
class WebhookUrlValidator
{
    public function validate(string $url): bool
    {
        $parsed = parse_url($url);
        
        // Must be HTTP or HTTPS
        if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
            return false;
        }
        
        // Resolve hostname
        $ip = gethostbyname($parsed['host']);
        
        // Block private/reserved IP ranges
        if ($this->isPrivateIp($ip)) {
            return false;
        }
        
        // Block localhost
        if (in_array($parsed['host'], ['localhost', '127.0.0.1', '0.0.0.0'])) {
            return false;
        }
        
        // Block cloud metadata endpoints
        if (in_array($parsed['host'], ['169.254.169.254', 'metadata.google.internal'])) {
            return false;
        }
        
        return true;
    }
    
    private function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
```

---

## 9. TESTING ARCHITECTURE

### 9.1 Test Categories

| Category | What | Count (est.) |
|----------|------|-------------|
| Integration CRUD | Create/update/delete tenant integrations | 15 |
| Credential encryption | Encrypt/decrypt, masking in responses | 5 |
| Webhook endpoint CRUD | Create/update/delete endpoints | 12 |
| Webhook subscriptions | Subscribe/unsubscribe, event filtering | 8 |
| Webhook delivery | Delivery success, failure, retry, dead-letter | 15 |
| Webhook signature | HMAC generation, verification | 5 |
| Webhook replay | Manual replay of failed deliveries | 5 |
| API key management | Generate, validate, revoke, rotate | 12 |
| API key scopes | Scope enforcement (read-only blocked from write) | 6 |
| Integration API | External access endpoints, tenant isolation | 15 |
| Provider adapter | Generic HTTP adapter, health check | 5 |
| Event dispatcher | Event → subscription matching, payload structure | 8 |
| Rate limiting | Per-key rate limit enforcement | 3 |
| SSRF protection | Private IP blocked, localhost blocked | 4 |
| Tenant isolation | Cross-tenant access denied | 10 |
| RBAC | Role-based permission enforcement | 8 |
| **Total** | | **~130** |

### 9.2 Test Helpers

- `CreateIntegrationTrait` — creates tenant integration with test credentials
- `CreateWebhookEndpointTrait` — creates endpoint with subscriptions
- `MockHttpClient` — mocks outbound HTTP calls for delivery tests
- `GenerateApiKeyTrait` — generates API key for integration API tests

---

## 10. FRONTEND ARCHITECTURE

### 10.1 Page Structure

```
src/pages/integrations/
  IntegrationsPage.tsx          — provider catalog + active integrations
  IntegrationDetailPage.tsx     — config form, credentials, logs, health
  WebhooksPage.tsx              — endpoint list + delivery board
  WebhookEndpointDetailPage.tsx — endpoint detail, subscriptions, deliveries
  WebhookDeliveryDetailPage.tsx — delivery inspection (request/response)
  ApiKeysPage.tsx               — API key management
  IntegrationHealthPage.tsx     — health dashboard
```

### 10.2 Service Layer

```
src/services/
  integrationService.ts         — CRUD integrations, test connection
  webhookService.ts             — CRUD endpoints, deliveries, replay
  apiKeyService.ts              — generate, revoke, rotate keys
```

### 10.3 State Management

Zustand stores:
- `useIntegrationStore` — integration list, selected integration, health
- `useWebhookStore` — endpoints, deliveries, stats
- `useApiKeyStore` — key list, generated key (one-time display)

---

*End of Phase 9 Architecture — DRAFT*
