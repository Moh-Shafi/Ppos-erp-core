# Phase 9 — Integration & Webhooks — Security

**Document Status:** DRAFT  
**Created:** 2026-08-17  
**Phase:** 9 — Integration & Webhooks  

---

## 1. CREDENTIAL ENCRYPTION

### 1.1 Storage

- All integration credentials encrypted using Laravel `Crypt::encryptString()` (AES-256-CBC)
- `APP_KEY` from `.env` used as encryption key
- Credentials stored as encrypted blob in `tenant_integrations.encrypted_credentials`
- Encryption happens in `IntegrationService::create()` and `updateCredentials()`

### 1.2 Access

- Decryption only occurs inside `IntegrationService::getCredentials()` (internal method)
- API responses never include decrypted credentials
- `TenantIntegrationResource` masks credentials as `"••••••••"`
- No mass-assignment path to `encrypted_credentials` column

### 1.3 Rotation

- `PUT /integrations/{id}/credentials` requires full credential set (no partial updates)
- Old encrypted blob replaced entirely
- No credential history retained (no stale secrets in DB)

---

## 2. WEBHOOK SIGNATURE VERIFICATION

### 2.1 Signature Generation

```
signature = HMAC-SHA256(
    key = endpoint.secret,
    message = payload_json + timestamp
)
```

### 2.2 Delivery Headers

```
X-Webhook-Signature: {hex_encoded_hmac}
X-Webhook-Timestamp: {unix_timestamp}
X-Webhook-Event: {event_type}
X-Webhook-Delivery: {delivery_uuid}
Content-Type: application/json
```

### 2.3 Receiver Verification (Recommended for consumers)

```python
import hmac, hashlib, json

def verify_webhook(payload_body, signature_header, timestamp_header, secret):
    # Check timestamp freshness (within 5 minutes)
    if abs(time.time() - int(timestamp_header)) > 300:
        return False
    
    # Compute expected signature
    expected = hmac.new(
        secret.encode(),
        (payload_body + timestamp_header).encode(),
        hashlib.sha256
    ).hexdigest()
    
    # Constant-time comparison
    return hmac.compare_digest(expected, signature_header)
```

### 2.4 Endpoint Secret

- Generated at endpoint creation: `whsec_` + 32 random alphanumeric chars
- Stored in `webhook_endpoints.secret`
- Returned in plaintext only at creation time
- Not retrievable after creation (must regenerate endpoint to get new secret)

---

## 3. API KEY SECURITY

### 3.1 Key Format

- Prefix: `itg_` (identifies as integration key)
- Body: 40 random alphanumeric characters (base62)
- Total length: 44 characters
- Entropy: ~238 bits

### 3.2 Storage

- Full key never stored in database
- `key_hash`: SHA-256 hash of full key (unique)
- `key_prefix`: first 12 characters for identification display
- Plaintext returned only once at generation/rotation time

### 3.3 Validation Flow

```
Request → X-Integration-Key header
  │
  ├── SHA-256(key) → hash
  ├── Query: integration_api_keys WHERE key_hash = hash AND is_revoked = false
  ├── Check expires_at (if set, must be future)
  ├── Update last_used_at
  └── Set tenant context
```

### 3.4 Scopes

| Scope | Permissions |
|-------|------------|
| `read` | GET endpoints only |
| `write` | GET + POST/PUT/DELETE (where available) |
| `webhook` | POST to inbound webhook receiver only |

- Scopes stored as JSON array in `integration_api_keys.scopes`
- Enforced by `IntegrationScope` middleware
- Default scope on generation: `read` (user must explicitly request write)

### 3.5 Rate Limiting

- Default: 100 requests/minute per API key
- Configurable per key (future enhancement)
- Enforced by `IntegrationRate` middleware
- 429 response includes `Retry-After` header

---

## 4. SSRF PROTECTION

### 4.1 Webhook Endpoint URL Validation

Before creating or updating a webhook endpoint, the URL is validated against:

| Check | Blocked |
|-------|---------|
| Non-HTTP(S) scheme | `ftp://`, `file://`, `gopher://`, etc. |
| Localhost | `localhost`, `127.0.0.1`, `::1`, `0.0.0.0` |
| Private IP ranges | `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16` |
| Reserved IP ranges | `169.254.0.0/16` (link-local), `0.0.0.0/8` |
| Cloud metadata | `169.254.169.254` (AWS/GCP), `metadata.google.internal` |
| IP literal in URL | `http://10.0.0.1/` blocked |

### 4.2 DNS Rebinding

- Hostname resolved to IP at validation time
- IP checked against blocklist
- Note: MVP does not pin resolved IP for subsequent deliveries (potential DNS rebinding risk; documented as known limitation)

---

## 5. TENANT ISOLATION

### 5.1 Data Isolation

- Every Phase 9 table includes `tenant_id` column
- All queries scoped by `tenant_id` from:
  - User token (management API)
  - API key (integration API)
- `TenantScope` global scope applied to all models
- Cross-tenant access returns 404 (not 403, to prevent information leakage)

### 5.2 Event Isolation

- Webhook deliveries only dispatched for events from the same tenant
- EventDispatcher filters subscriptions by `tenant_id`
- No tenant can subscribe to another tenant's events

### 5.3 API Key Isolation

- API key mapped to exactly one `tenant_id`
- All integration API queries scoped to that tenant
- Key cannot access data from other tenants

---

## 6. PERMISSION ENFORCEMENT

### 6.1 Management API Permissions

| Endpoint Group | Required Permission |
|---------------|-------------------|
| `GET /integrations/*` | `integrations.view` |
| `POST/PUT/DELETE /integrations/*` | `integrations.manage` |
| `GET /webhooks/*` | `webhooks.view` |
| `POST/PUT/DELETE /webhooks/*` | `webhooks.manage` |
| `GET /api-keys` | `apikeys.view` |
| `POST/DELETE /api-keys/*` | `apikeys.manage` |

### 6.2 Role Matrix

| Permission | Owner | Manager | Cashier | Staff |
|-----------|-------|---------|---------|-------|
| integrations.view | ✅ | ✅ | ❌ | ❌ |
| integrations.manage | ✅ | ❌ | ❌ | ❌ |
| webhooks.view | ✅ | ✅ | ❌ | ❌ |
| webhooks.manage | ✅ | ❌ | ❌ | ❌ |
| apikeys.view | ✅ | ✅ | ❌ | ❌ |
| apikeys.manage | ✅ | ❌ | ❌ | ❌ |

### 6.3 Module Gating

- All Phase 9 routes wrapped in `module:integrations` middleware
- If `integrations` module disabled for tenant, all endpoints return 403

---

## 7. AUDIT TRAIL

### 7.1 Audit Log Events

| Action | Audit Entry |
|--------|-------------|
| Integration created | `integration.created` |
| Integration updated | `integration.updated` |
| Integration credentials changed | `integration.credentials_changed` |
| Integration activated/deactivated | `integration.activated` / `integration.deactivated` |
| Integration deleted | `integration.deleted` |
| Webhook endpoint created | `webhook.endpoint_created` |
| Webhook endpoint deleted | `webhook.endpoint_deleted` |
| API key generated | `apikey.generated` |
| API key revoked | `apikey.revoked` |
| API key rotated | `apikey.rotated` |
| Webhook delivery replayed | `webhook.replayed` |

### 7.2 Integration Logs

- All outbound HTTP calls logged to `integration_logs`
- All inbound webhook receptions logged to `integration_logs`
- Logs include: method, URL, request/response headers (filtered), body, status, latency, error
- Sensitive headers (Authorization, X-Integration-Key) redacted in logs
- Log retention: 30 days (configurable)

---

## 8. IDEMPOTENCY

### 8.1 Webhook Delivery

- Each delivery has unique `event_id` (UUID v4)
- Composite uniqueness: `(webhook_endpoint_id, event_id)`
- If duplicate delivery attempted, existing record returned (no re-delivery)

### 8.2 Outbound Integration Calls

- Idempotency key generated per call: `itg-{uuid}`
- Key sent in `Idempotency-Key` header to external API
- Key stored in `integration_logs.idempotency_key`
- Retry uses same idempotency key

### 8.3 Inbound Webhook Reception

- Provider event ID extracted from payload
- Checked against `integration_logs` for duplicate
- If duplicate: return 200 OK (idempotent acknowledgment), no reprocessing

---

## 9. DATA EXPOSURE PREVENTION

### 9.1 Integration API Resources

- `IntegrationSaleResource`: excludes `tenant_id`, `cost_price`, `created_by`
- `IntegrationProductResource`: excludes `tenant_id`, `cost_price`, `supplier_id`
- `IntegrationCustomerResource`: excludes `tenant_id`, `credit_balance` (unless `write` scope)
- `IntegrationPaymentResource`: excludes `tenant_id`, `gateway_response` (raw), `metadata`

### 9.2 No Internal Field Leakage

- All integration API responses use dedicated resource classes
- Never use the same resource as internal API (which may expose more fields)
- `tenant_id` is never included in any integration API response

---

## 10. KNOWN LIMITATIONS (MVP)

| Limitation | Risk | Mitigation |
|-----------|------|------------|
| No DNS pinning for webhook URLs | DNS rebinding attack | Document; future: pin IP after first resolution |
| No IP allowlist for integration API | Brute force on keys | Rate limiting + SHA-256 hash makes brute force impractical |
| No webhook payload encryption | Payload interception | HTTPS enforced for endpoint URLs; consumers should use HTTPS |
| No per-key configurable rate limit | Abusive key impact | Global 100/min limit; future: per-key configurable |
| Credential rotation requires manual update | Stale credentials | No auto-rotation; documented as operational procedure |

---

*End of Phase 9 Security — DRAFT*
