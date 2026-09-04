# Phase 10 — Production, Security & Monitoring — Security

**Document Status:** DRAFT  
**Created:** 2026-08-18  
**Phase:** 10

---

## 1. SECURITY OVERVIEW

Phase 10 is the security hardening phase. This document covers all security measures, threat models, and verification procedures.

---

## 2. THREAT MODEL

### 2.1 OWASP Top 10 (2021) Coverage

| ID | Threat | Status in System | Phase 10 Action |
|----|--------|-----------------|-----------------|
| A01 | Broken Access Control | RBAC + tenant isolation in place | IDOR audit, verify all endpoints |
| A02 | Cryptographic Failures | AES-256 for credentials, bcrypt for passwords | Verify TLS, add password history hashing |
| A03 | Injection | Eloquent ORM (parameterized) | Static analysis to verify no raw SQL |
| A04 | Insecure Design | Multi-tenant architecture reviewed | Threat model documentation |
| A05 | Security Misconfiguration | Debug off in prod, env-based config | CORS hardening, header security |
| A06 | Vulnerable Components | composer audit | Add `composer audit` to CI |
| A07 | Auth Failures | Sanctum tokens, rate limiting | Add lockout, 2FA, password policy |
| A08 | Data Integrity Failures | Signed webhooks (Phase 9) | Verify CSRF on web routes |
| A09 | Logging Failures | AuditService exists | Comprehensive observers, structured logs |
| A10 | SSRF | Webhook URL validation (Phase 9) | No additional action |

### 2.2 Attack Vectors Addressed

| Vector | Mitigation |
|--------|-----------|
| Brute force login | Progressive account lockout (5/10/15 attempts → 15min/1hr/24hr) |
| Credential stuffing | Rate limiting on auth endpoints (5/min) |
| Weak passwords | Password complexity (12+ chars, mixed case, numbers, symbols) |
| Password reuse | Password history (last 5 passwords) |
| Session hijacking | Sanctum tokens, no cookie-based sessions for API |
| XSS | Input sanitization middleware, JSON API responses |
| CSRF | API uses Bearer tokens (not cookies), Sanctum stateful only for web |
| IDOR | Tenant isolation on all queries, explicit ownership checks |
| Mass assignment | `$fillable` on all models, verified by static analysis |
| Rate abuse | Per-tenant, per-user, per-endpoint rate limiting |
| Information disclosure | Debug off in production, error messages sanitized |
| Supply chain | `composer audit` in CI, pinned versions |

---

## 3. SECURITY MEASURES DETAIL

### 3.1 Password Policy

**Rules:**
- Minimum 12 characters
- At least 1 uppercase (A-Z)
- At least 1 lowercase (a-z)
- At least 1 digit (0-9)
- At least 1 special character (!@#$%^&*()-_=+[]{}|;:,.<>?)
- Not in common password blacklist (top 1000: password, 123456, qwerty, etc.)
- Not reused from last 5 passwords

**Implementation:**
```php
use Illuminate\Validation\Rules\Password;

Password::min(12)
    ->mixedCase()
    ->numbers()
    ->symbols()
    ->uncompromised()  // Checks against HaveIBeenPwned API (k-anonymity)
```

**Password History:**
- On password change: store hash in `password_histories`
- Keep only last 5 entries per user
- On new password: compare against all stored hashes
- If match: reject with "Cannot reuse recent passwords"

**Backward Compatibility:**
- Existing users are NOT forced to change passwords
- New policy applies only on: registration, password change, password reset
- No mass password reset

### 3.2 Account Lockout

**Lockout Table:**
- Keyed by username (email), not IP
- Prevents distributed brute force from multiple IPs

**Thresholds:**
| Failed Attempts | Lock Duration | Log Event |
|----------------|---------------|-----------|
| 5 | 15 minutes | `account.locked.level1` |
| 10 | 1 hour | `account.locked.level2` |
| 15 | 24 hours | `account.locked.level3` |

**Reset Conditions:**
- Successful login → reset counter to 0
- Lockout expires → reset counter to 0
- Admin manual unlock → reset counter to 0

**Response (423):**
```json
{
  "message": "Account is locked due to too many failed attempts",
  "locked_until": "2026-08-18T12:15:00Z",
  "retry_after": 900
}
```

**Security Considerations:**
- Lockout applies even if email doesn't exist (prevents user enumeration)
- Failed attempt counter increments for non-existent users too
- Lockout status not revealed in login response (same 401 for invalid creds)
- IP address logged for forensic analysis

### 3.3 Two-Factor Authentication (2FA)

**TOTP Implementation:**
- Algorithm: TOTP (RFC 6238)
- Hash: SHA-1 (standard for Google Authenticator, Authy, etc.)
- Digits: 6
- Period: 30 seconds
- Window: ±1 time step (allows 30s clock drift)

**Secret Storage:**
- Secret encrypted at rest using Laravel's `Crypt::encrypt()` (AES-256-CBC)
- Never returned in API responses after initial enable
- QR code contains: `otpauth://totp/{issuer}:{email}?secret={secret}&issuer={issuer}`

**Backup Codes:**
- 10 single-use codes generated on enable
- Stored as bcrypt hashes in `backup_codes` JSON column
- Each code: 8 characters, alphanumeric, case-insensitive
- Consumed on use (removed from array)
- Can be regenerated (requires valid TOTP)
- User can view remaining count (not the codes themselves after initial display)

**2FA Login Flow Security:**
- `2fa_token` (temp token) expires in 5 minutes
- `2fa_token` is single-use (consumed on successful or failed attempt)
- Rate limited: 5 attempts per temp_token
- After 5 failed 2FA attempts: temp_token invalidated, user must login again

**Feature Flagging:**
- `2fa` feature must be enabled for tenant
- If feature disabled after users enabled 2FA: 2FA still enforced for those users
- Feature flag controls whether NEW users can enable 2FA
- Admin can reset 2FA for any user (with audit log)

### 3.4 XSS Prevention

**Input Sanitization Middleware:**
- Strips: `<script>`, `</script>`, `javascript:`, `on*=` event handlers, `<iframe>`, `<object>`, `<embed>`, `<svg onload>`
- Does NOT strip: standalone `<` or `>` (escaped by JSON response encoding)
- Skip fields: configured in `config/security.xss.skip_fields` (rich text fields)
- Applied to all POST/PUT/PATCH input

**Output Encoding:**
- API responses are JSON (`Content-Type: application/json`)
- JSON encoding inherently escapes `<` and `>` as `\u003C` and `\u003E`
- No Blade views rendered for API (no `{{ }}` or `{!! !!}` risk)
- If Blade is used in future: always use `{{ }}` (never `{!! !!}`)

**Verification:**
- Test: submit `<script>alert(1)</script>` in product name → stored without script tags
- Test: submit `<img src=x onerror=alert(1)>` → `onerror=` stripped
- Test: submit `javascript:alert(1)` in URL field → stripped

### 3.5 CORS Configuration

**Production:**
```php
// config/cors.php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '')),
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers' => ['Authorization', 'Content-Type', 'X-Store-Id', 'X-Idempotency-Key'],
    'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'Retry-After'],
    'max_age' => 86400,
    'supports_credentials' => true,
];
```

**Security Rules:**
- No wildcard (`*`) in production
- Origins must be explicit (https://app.example.com)
- Credentials: true (required for Sanctum stateful API)
- Max age: 86400 (preflight cached for 24h)
- Exposed headers: only rate limit headers (no sensitive headers)

### 3.6 Rate Limiting

**Per-Tenant:**
- Key: `tenant:{tenant_id}`
- Limit: 1000 req/min (configurable)
- Prevents single tenant from overwhelming system

**Per-User:**
- Key: `user:{user_id}`
- Limit: 200 req/min (configurable)
- Prevents single user from abusing API

**Per-Endpoint:**
- Auth endpoints: 5/min (existing)
- Write endpoints (POST/PUT/PATCH/DELETE): 60/min per user
- Read endpoints (GET): 300/min per user

**Bypass:**
- Testing and local environments: no rate limiting
- Integration API keys: separate rate limit (Phase 9, 100/min)

**Headers:**
- `X-RateLimit-Limit`: max requests per window
- `X-RateLimit-Remaining`: remaining requests
- `Retry-After`: seconds until reset (on 429 only)

### 3.7 Audit Log Security

**Access Control:**
- `security.audit.view` permission required
- Only owner and manager roles have this permission by default
- Users can only see audit logs for their own tenant
- No cross-tenant access possible (tenant_id filter always applied)

**Data in Audit Log:**
- Stored: action, entity_type, entity_id, old_values, new_values, ip_address, user_agent, route, method
- Not stored: passwords, tokens, API keys, secrets
- Sensitive fields in old/new values are redacted:
  - `password`, `password_hash`, `secret`, `api_key`, `token`, `whsec_*`
  - Replaced with `[REDACTED]` in audit log

**Retention:**
- Default: 90 days
- Configurable via `AUDIT_RETENTION_DAYS`
- Auto-purge: daily scheduled command deletes logs older than retention
- Purge logged as system event

### 3.8 Sentry Security

**No PII sent to Sentry:**
- User context: `{ id, tenant_id }` only (no name, email, phone)
- Request context: `{ url, method }` (no headers, no body, no query params)
- No cookies, no authorization headers
- `send_default_pii: false` (Sentry config)

**Scrubbing:**
- Sentry SDK configured to scrub: `password`, `token`, `secret`, `api_key`, `authorization`
- Custom scrub: `whsec_*`, `itg_*` prefixed values

---

## 4. DATA PROTECTION

### 4.1 Encryption at Rest

| Data | Encryption | Key |
|------|-----------|-----|
| User passwords | bcrypt (cost 12) | N/A |
| Integration credentials | AES-256-CBC (Laravel Crypt) | APP_KEY |
| Webhook secrets | AES-256-CBC (Laravel Crypt) | APP_KEY |
| 2FA TOTP secrets | AES-256-CBC (Laravel Crypt) | APP_KEY |
| API keys | SHA-256 hash | N/A |
| Backup codes | bcrypt | N/A |
| Database | MySQL at-rest encryption (if configured) | MySQL keyring |

### 4.2 Encryption in Transit

- TLS 1.2+ required (enforced by Caddy reverse proxy)
- HSTS header: `Strict-Transport-Security: max-age=31536000; includeSubDomains`
- No HTTP → redirect to HTTPS (Caddy automatic)
- Internal Docker network: unencrypted (acceptable within trusted network)

### 4.3 Security Headers

| Header | Value | Purpose |
|--------|-------|---------|
| `X-Content-Type-Options` | `nosniff` | Prevent MIME sniffing |
| `X-Frame-Options` | `DENY` | Prevent clickjacking |
| `X-XSS-Protection` | `1; mode=block` | Legacy XSS protection |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | Force HTTPS |
| `Content-Security-Policy` | `default-src 'self'` | Prevent injected content |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Limit referrer leakage |

---

## 5. PDP COMPLIANCE (INDONESIA)

### 5.1 UU PDP No. 27/2022 Key Requirements

| Requirement | Implementation |
|-------------|---------------|
| Consent for data processing | Consent log at registration |
| Right to access data | `GET /api/v1/account/export` |
| Right to erasure | `DELETE /api/v1/account` (30-day grace period) |
| Data minimization | Only collect necessary data (review all forms) |
| Purpose limitation | Data used only for stated purpose (POS operations) |
| Data retention | Configurable per data type, default 90 days for logs |
| Security measures | Encryption, access control, audit logging |
| Breach notification | Sentry alerts + audit log for forensic analysis |
| Data processing register | Documented in `data_retention_policies` table |

### 5.2 Data Subject Rights

| Right | Endpoint | Response Time |
|-------|----------|---------------|
| Access | `GET /api/v1/account/export` | Immediate |
| Erasure | `DELETE /api/v1/account` | 30 days (grace period) |
| Rectification | `PUT /api/v1/auth/profile` | Immediate |
| Consent withdrawal | `DELETE /api/v1/account` | Triggers erasure |

---

## 6. SECURITY VERIFICATION CHECKLIST

### 6.1 Pre-Implementation
- [ ] Threat model documented
- [ ] OWASP Top 10 reviewed
- [ ] PDP requirements mapped

### 6.2 Implementation
- [ ] Password complexity enforced
- [ ] Password history working
- [ ] Account lockout progressive
- [ ] 2FA enable/disable/verify working
- [ ] 2FA backup codes working
- [ ] 2FA login flow working
- [ ] XSS middleware strips dangerous input
- [ ] CORS strict in production
- [ ] Rate limiting per-tenant/user/endpoint
- [ ] Audit observers on all key models
- [ ] Sensitive data redacted in audit logs
- [ ] Sentry captures errors without PII
- [ ] Security headers present

### 6.3 Post-Implementation
- [ ] PHPStan level 6 passes
- [ ] `composer audit` passes
- [ ] IDOR audit: all endpoints verify tenant ownership
- [ ] Mass assignment audit: all models have `$fillable`
- [ ] No raw SQL queries (Eloquent only)
- [ ] No hardcoded secrets in code
- [ ] No secrets in git history
- [ ] All tests pass (1197 + new)
- [ ] Load test passes (100 concurrent users)
- [ ] Security review document signed off

---

*End of Phase 10 Security*
