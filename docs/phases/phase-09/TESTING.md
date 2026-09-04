# Phase 9 — Integration & Webhooks — Testing Strategy

**Document Status:** DRAFT  
**Created:** 2026-08-17  
**Phase:** 9 — Integration & Webhooks  

---

## 1. TESTING LAYERS

| Layer | Tool | Scope |
|-------|------|-------|
| Backend Unit Tests | PHPUnit | Service logic, model relationships, encryption |
| Backend Feature Tests | PHPUnit | API endpoints, middleware, tenant isolation, RBAC |
| Frontend Build | Vite/tsc | TypeScript compilation, no build errors |
| E2E Tests | Playwright | Integration setup, webhook management, API key generation |

---

## 2. BACKEND TEST SUITES

### 2.1 IntegrationProviderTest

| Test | Description |
|------|-------------|
| providers are seeded | `generic_http` and `xendit` providers exist |
| provider config schema is valid JSON | Schema returns correct structure |
| inactive provider not available | Inactive provider excluded from list |

### 2.2 TenantIntegrationTest

| Test | Description |
|------|-------------|
| create integration | POST creates with encrypted credentials |
| create invalid provider rejected | Non-existent provider slug → 422 |
| create missing credentials rejected | Empty credentials → 422 |
| list integrations tenant scoped | Tenant A cannot see Tenant B integrations |
| show integration credentials masked | Response never includes plaintext credentials |
| update config | PUT updates non-sensitive config |
| update credentials | PUT credentials replaces encrypted blob |
| delete integration | DELETE removes integration |
| activate integration | POST activate sets status=active |
| deactivate integration | POST deactivate sets status=inactive |
| test connection success | POST test returns success for valid credentials |
| test connection failure | POST test returns failure for invalid credentials |
| integration logs paginated | GET logs returns paginated results |
| tenant isolation on logs | Tenant A cannot see Tenant B logs |
| owner can manage integrations | Owner role has full access |
| manager can view integrations | Manager can view but not manage |
| cashier cannot access integrations | Cashier gets 403 |
| staff cannot access integrations | Staff gets 403 |
| module disabled returns 403 | Without integrations module, all endpoints 403 |
| credentials encrypted at rest | DB contains encrypted blob, not plaintext |

### 2.3 WebhookEndpointTest

| Test | Description |
|------|-------------|
| create endpoint | POST creates with URL, events, generates secret |
| create invalid url rejected | Non-HTTP scheme → 422 |
| create private ip url rejected | `http://10.0.0.1` → 422 (SSRF) |
| create localhost url rejected | `http://localhost` → 422 (SSRF) |
| create metadata ip rejected | `http://169.254.169.254` → 422 (SSRF) |
| create invalid event rejected | Non-existent event slug → 422 |
| list endpoints tenant scoped | Tenant isolation enforced |
| update endpoint | PUT updates name, URL |
| delete endpoint | DELETE removes endpoint and subscriptions |
| activate endpoint | POST activate sets is_active=true |
| deactivate endpoint | POST deactivate sets is_active=false |
| test endpoint sends test payload | POST test sends HTTP request to URL |
| subscribe to event | POST subscription adds event to endpoint |
| unsubscribe from event | DELETE subscription removes event |
| secret shown only at creation | Secret not returned in subsequent GET requests |
| owner can manage endpoints | Full access |
| manager can view endpoints | View only, no manage |
| cashier cannot access | 403 |

### 2.4 WebhookDeliveryTest

| Test | Description |
|------|-------------|
| delivery created on domain event | Sale checkout creates pending delivery |
| delivery payload correct structure | Payload matches event registry schema |
| delivery signature valid | HMAC-SHA256 matches payload + secret |
| delivery headers correct | All required headers present |
| successful delivery marked delivered | 2xx response → status=delivered |
| failed delivery marked failed | 500 response → status=failed |
| delivery retry on failure | Failed delivery retried with backoff |
| delivery dead lettered after max attempts | 5 failed attempts → status=dead_lettered |
| duplicate delivery prevented | Same event_id + endpoint_id → no re-delivery |
| delivery list filterable | Filter by endpoint, event, status |
| delivery detail shows full request/response | GET delivery includes headers, payload, response |
| replay creates new delivery | POST replay creates new pending delivery |
| replay only for failed or dead lettered | Cannot replay delivered deliveries |
| tenant isolation on deliveries | Tenant A cannot see Tenant B deliveries |
| delivery stats correct | Stats endpoint returns accurate counts |
| event not delivered to inactive endpoint | Inactive endpoint excluded from delivery |
| event not delivered to unsubscribed endpoint | Only subscribed events delivered |

### 2.5 ApiKeyTest

| Test | Description |
|------|-------------|
| generate key returns plaintext | POST returns key once |
| key hash stored not plaintext | DB contains SHA-256 hash, not key |
| key prefix stored for identification | First 12 chars stored |
| list keys shows prefix not full key | GET returns prefix only |
| revoke key | DELETE sets is_revoked=true |
| revoked key rejected | Auth with revoked key → 401 |
| rotate key | POST rotate creates new key, revokes old |
| rotated old key rejected | Old key no longer works |
| expired key rejected | Past expires_at → 401 |
| key with read scope can GET | Read scope allows GET endpoints |
| key with read scope cannot POST | Read scope blocks write endpoints → 403 |
| key with write scope can POST | Write scope allows all |
| key with webhook scope can POST webhook | Webhook scope allows inbound receiver |
| key with webhook scope cannot GET | Webhook scope blocks data endpoints → 403 |
| rate limit enforced | 101st request in minute → 429 |
| tenant isolation via key | Key only accesses own tenant data |
| owner can manage keys | Full access |
| manager can view keys | View only |
| cashier cannot access | 403 |

### 2.6 IntegrationApiTest

| Test | Description |
|------|-------------|
| list sales with valid key | GET /integration/sales returns paginated sales |
| list sales no key | 401 |
| list sales invalid key | 401 |
| list sales revoked key | 401 |
| show sale detail | GET /integration/sales/{id} returns detail |
| show sale from other tenant | 404 |
| list products | GET /integration/products works |
| list inventory with store filter | GET /integration/inventory?store_id=1 |
| list inventory without store filter | 422 (store_id required) |
| list customers | GET /integration/customers works |
| show customer | GET /integration/customers/{id} works |
| list payments | GET /integration/payments works |
| no tenant_id in response | Responses never include tenant_id |
| no cost_price in response | Product/sale responses exclude cost_price |
| rate limit returns 429 | Exceeding limit returns 429 with Retry-After |

### 2.7 EventDispatcherTest

| Test | Description |
|------|-------------|
| sale.created event dispatched | Checkout triggers event |
| payment.received event dispatched | Payment triggers event |
| inventory.low_stock event dispatched | Stock drop triggers event |
| event matches subscription | Subscribed endpoint receives delivery |
| event no matching subscription | No delivery created |
| event payload structure correct | Payload has event_id, event_type, timestamp, data |
| multiple endpoints receive same event | All subscribed endpoints get delivery |
| event from tenant a not delivered to tenant b | Tenant isolation in event dispatch |

### 2.8 ProviderAdapterTest

| Test | Description |
|------|-------------|
| generic http adapter health check | Returns success for reachable URL |
| generic http adapter health check failure | Returns failure for unreachable URL |
| generic http adapter execute | POST to external URL works |
| generic http adapter idempotency | Same idempotency key → same response |
| unknown provider adapter rejected | Unregistered slug → exception |
| adapter registered correctly | Registry resolves slug to adapter class |

### 2.9 WebhookEventTest

| Test | Description |
|------|-------------|
| events are seeded | All system events exist in registry |
| event list returns all events | GET /webhooks/events returns full list |
| event has payload description | Each event includes payload_description |
| event associated with module | Each event has module field |

### 2.10 IntegrationHealthTest

| Test | Description |
|------|-------------|
| health summary returns all integrations | GET /integrations/health works |
| health shows status correctly | Active/error/suspended reflected |
| health shows error count | 24h error count from logs |
| health tenant scoped | Only own tenant integrations |

---

## 3. E2E TESTS (Playwright)

### 3.1 Integration Setup Flow

```
1. Login as owner
2. Navigate to Integrations page
3. Verify "Integrations" nav item visible
4. Select "Generic HTTP" provider
5. Fill config form (name, base_url)
6. Fill credentials (api_key, api_secret)
7. Save integration
8. Verify integration appears in list with "inactive" status
9. Click "Test Connection"
10. Verify status changes to "active"
11. Verify credentials show as "••••••••"
```

### 3.2 Webhook Endpoint Management

```
1. Login as owner
2. Navigate to Webhooks page
3. Create new endpoint (name, URL, select events)
4. Verify endpoint appears in list
5. Verify secret is shown once
6. Navigate to delivery board
7. Verify empty state
8. Trigger a sale (if possible via UI)
9. Verify delivery appears in board
```

### 3.3 API Key Generation

```
1. Login as owner
2. Navigate to API Keys page
3. Click "Generate Key"
4. Fill name and select scopes
5. Verify plaintext key shown with copy button
6. Verify warning message displayed
7. Verify key appears in list with prefix only
8. Revoke key
9. Verify key marked as revoked
```

### 3.4 Staff Access Denied

```
1. Login as staff user
2. Verify "Integrations" nav item NOT visible
3. Directly navigate to /integrations
4. Verify 403 or redirect to dashboard
```

---

## 4. REGRESSION GATES

| Gate | Test Count | Must Pass |
|------|-----------|-----------|
| Phase 0-8 backend regression | 1153+ | 100% |
| Phase 9 backend tests | ~130 | 100% |
| Phase 8 E2E | 5 | 100% |
| Phase 9 E2E | 4 | 100% |
| Frontend build | — | Success |

---

## 5. TEST INFRASTRUCTURE

### 5.1 Test Helpers

```php
// Creates a tenant integration with test credentials
trait CreateIntegrationTrait {
    function createIntegration($tenantId, $providerSlug = 'generic_http'): TenantIntegration;
}

// Creates a webhook endpoint with subscriptions
trait CreateWebhookEndpointTrait {
    function createWebhookEndpoint($tenantId, $events = ['sale.created']): WebhookEndpoint;
}

// Generates an API key for integration API tests
trait GenerateApiKeyTrait {
    function generateApiKey($tenantId, $scopes = ['read']): array;
}

// Mocks HTTP client for outbound calls
trait MockHttpClientTrait {
    function mockHttpClient(): void;
}
```

### 5.2 Queue Configuration in Tests

- `QUEUE_CONNECTION=sync` in `phpunit.xml` (jobs execute immediately in tests)
- WebhookDeliveryJob tested with mocked HTTP client
- Retry/backoff tested by manually releasing job with modified attempt count

### 5.3 Database

- Uses `RefreshDatabase` trait (migrate:fresh per test)
- Test database: `pos_saas_testing` (via `tests/bootstrap.php`)
- Seeders run via `$this->seed()` in test setUp

---

*End of Phase 9 Testing — DRAFT*
