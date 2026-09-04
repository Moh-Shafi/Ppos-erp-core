# Phase 10 — Production, Security & Monitoring — API

**Document Status:** DRAFT  
**Created:** 2026-08-18  
**Phase:** 10

---

## 1. API CONVENTIONS

All endpoints follow existing conventions:
- Base URL: `/api/v1`
- Auth: Bearer token (Sanctum) or API Key (Integration)
- Content-Type: `application/json`
- Error format: `{ "message": "...", "errors": { "field": ["..."] } }`
- Pagination: `{ "data": [...], "meta": { "current_page", "last_page", "per_page", "total" } }`

---

## 2. NEW ENDPOINTS

### 2.1 Two-Factor Authentication

#### POST `/api/v1/auth/2fa/enable`
Enable 2FA for current user (generates QR code + backup codes).

**Auth:** Bearer token  
**Feature:** `2fa` must be enabled for tenant  
**Permission:** Any authenticated user

**Request:** No body

**Response 200:**
```json
{
  "qr_code": "data:image/png;base64,iVBOR...",
  "secret": "JBSWY3DPEHPK3PXP",
  "backup_codes": ["abc123", "def456", ...]
}
```

**Response 403:**
```json
{
  "message": "2FA feature is not enabled for your tenant"
}
```

---

#### POST `/api/v1/auth/2fa/verify`
Verify TOTP code and confirm 2FA activation.

**Auth:** Bearer token  
**Permission:** Any authenticated user

**Request:**
```json
{
  "code": "123456"
}
```

**Response 200:**
```json
{
  "verified": true
}
```

**Response 422:**
```json
{
  "message": "Invalid 2FA code",
  "errors": { "code": ["The provided code is invalid"] }
}
```

---

#### POST `/api/v1/auth/2fa/disable`
Disable 2FA (requires valid TOTP code).

**Auth:** Bearer token  
**Permission:** Any authenticated user

**Request:**
```json
{
  "code": "123456"
}
```

**Response 200:**
```json
{
  "disabled": true
}
```

**Response 422:**
```json
{
  "message": "Invalid 2FA code"
}
```

---

#### GET `/api/v1/auth/2fa/status`
Get current 2FA status.

**Auth:** Bearer token  
**Permission:** Any authenticated user

**Response 200:**
```json
{
  "enabled": true,
  "enabled_at": "2026-08-18T12:00:00Z",
  "backup_codes_remaining": 7,
  "last_used_at": "2026-08-18T14:30:00Z"
}
```

---

#### POST `/api/v1/auth/2fa/backup-codes`
Regenerate backup codes (requires valid TOTP code).

**Auth:** Bearer token  
**Permission:** Any authenticated user

**Request:**
```json
{
  "code": "123456"
}
```

**Response 200:**
```json
{
  "backup_codes": ["new1", "new2", ...]
}
```

---

#### POST `/api/v1/auth/login-2fa`
Complete login with 2FA after password verification.

**Auth:** None (public)  
**Rate Limited:** `auth` limiter (5/min)

**Request:**
```json
{
  "2fa_token": "temp_token_from_login",
  "code": "123456",
  "is_backup": false
}
```

**Response 200:**
```json
{
  "user": { "id": 1, "name": "...", "email": "..." },
  "token": "1|abcdef...",
  "modules": [...],
  "features": [...]
}
```

**Response 422:**
```json
{
  "message": "Invalid 2FA code"
}
```

---

### 2.2 Account Lockout Management

#### POST `/api/v1/admin/users/{id}/unlock`
Unlock a user account (admin only).

**Auth:** Bearer token  
**Permission:** `security.account.unlock`

**Response 200:**
```json
{
  "unlocked": true,
  "user_id": 5
}
```

**Response 403:**
```json
{
  "message": "You do not have permission to unlock accounts"
}
```

---

#### POST `/api/v1/admin/users/{id}/reset-2fa`
Reset 2FA for a user (admin only).

**Auth:** Bearer token  
**Permission:** `security.2fa.reset`

**Response 200:**
```json
{
  "reset": true,
  "user_id": 5
}
```

---

### 2.3 PDP Compliance — Account Data

#### GET `/api/v1/account/export`
Export all data for the current user.

**Auth:** Bearer token  
**Permission:** Any authenticated user

**Response 200:**
```
Content-Type: application/json
Content-Disposition: attachment; filename="account-export-{userId}-{date}.json"

{
  "user": { "id": 1, "name": "...", "email": "...", "created_at": "..." },
  "sales": [...],
  "payments": [...],
  "audit_logs": [...]
}
```

---

#### DELETE `/api/v1/account`
Request account deletion (soft delete + scheduled purge).

**Auth:** Bearer token  
**Permission:** Owner or self

**Request:**
```json
{
  "password": "current_password"
}
```

**Response 202:**
```json
{
  "message": "Account scheduled for deletion in 30 days",
  "scheduled_purge_at": "2026-09-18T00:00:00Z"
}
```

**Response 422:**
```json
{
  "message": "Password verification failed"
}
```

---

#### GET `/api/v1/account/consent`
View data processing consent log.

**Auth:** Bearer token  
**Permission:** Any authenticated user

**Response 200:**
```json
{
  "consented_at": "2026-08-11T10:00:00Z",
  "consent_type": "registration",
  "privacy_policy_version": "1.0",
  "data_types": ["profile", "sales", "payments", "audit_logs"]
}
```

---

### 2.4 Health Check

#### GET `/api/v1/health`
Detailed health check with dependency status.

**Auth:** None  
**Rate Limited:** 60/min per IP

**Response 200 (healthy):**
```json
{
  "status": "healthy",
  "timestamp": "2026-08-18T12:00:00Z",
  "checks": {
    "database": "ok",
    "redis": "ok",
    "storage": "ok",
    "queue": "ok"
  }
}
```

**Response 503 (degraded):**
```json
{
  "status": "degraded",
  "timestamp": "2026-08-18T12:00:00Z",
  "checks": {
    "database": "ok",
    "redis": "fail",
    "storage": "ok",
    "queue": "ok"
  }
}
```

---

### 2.5 OpenAPI Documentation

#### GET `/api/docs`
Swagger UI HTML page.

**Auth:** None (staging only, disabled in production)  
**Response:** `text/html`

---

#### GET `/api/openapi.json`
Raw OpenAPI 3.1 specification.

**Auth:** None (staging only)  
**Response:** `application/json` (OpenAPI spec)

---

## 3. MODIFIED ENDPOINTS

### 3.1 POST `/api/v1/auth/login`

**Added behavior:**
- Check account lockout before credential validation
- If locked: return 423 with `Retry-After` header
- If credentials valid and 2FA enabled: return 200 with `2fa_required` flag instead of token
- Increment failed attempts on invalid credentials

**New response variant (2FA required):**
```json
{
  "2fa_required": true,
  "2fa_token": "temp_token_abc123",
  "expires_in": 300
}
```

**New response (locked):**
```json
{
  "message": "Account is locked due to too many failed attempts",
  "locked_until": "2026-08-18T12:15:00Z",
  "retry_after": 900
}
```
Status: 423 Locked

---

### 3.2 POST `/api/v1/auth/register`

**Modified validation:**
- Password now requires: min 12 chars, 1 uppercase, 1 lowercase, 1 number, 1 symbol
- Uses `Password::min(12)->mixedCase()->numbers()->symbols()`

---

### 3.3 PUT `/api/v1/auth/profile`

**Added behavior:**
- If `new_password` is provided:
  - Verify `current_password`
  - Check new password against complexity rules
  - Check new password against last 5 password hashes
  - Store new hash in `password_histories`

---

### 3.4 GET `/api/v1/audit-logs`

**New query parameters:**
- `route` — filter by route name
- `method` — filter by HTTP method
- `export` — set to `csv` for CSV download

**CSV export response:**
```
Content-Type: text/csv
Content-Disposition: attachment; filename="audit-logs-{date}.csv"

id,tenant_id,user_id,action,entity_type,entity_id,ip_address,route,method,created_at
1,1,5,created,App\Models\Sale,42,192.168.1.1,api.v1.sales.store,POST,2026-08-18T12:00:00Z
```

---

## 4. RATE LIMIT HEADERS

All API responses include rate limit headers:

| Header | Description |
|--------|-------------|
| `X-RateLimit-Limit` | Maximum requests per window |
| `X-RateLimit-Remaining` | Remaining requests in current window |
| `Retry-After` | Seconds until window resets (only on 429) |

**429 Response:**
```json
{
  "message": "Too many requests. Try again in 60 seconds."
}
```
Headers: `Retry-After: 60`

---

## 5. ERROR CODES

| Code | Description | When |
|------|-------------|------|
| 423 | Locked | Account locked due to brute force |
| 429 | Too Many Requests | Rate limit exceeded |
| 403 | 2FA Required | Sensitive endpoint requires 2FA verification |

---

## 6. ENVIRONMENT-SPECIFIC BEHAVIOR

| Feature | Local | Testing | Staging | Production |
|---------|-------|---------|---------|------------|
| Rate limiting | Disabled | Disabled | Enabled | Enabled |
| Account lockout | Disabled | Disabled | Enabled | Enabled |
| Sentry | Disabled | Disabled | Enabled | Enabled |
| Swagger UI | Enabled | Disabled | Enabled | Disabled |
| Debug mode | On | Off | Off | Off |
| JSON logs | No | No | Yes | Yes |
| CORS wildcard | Yes | Yes | No | No |

---

*End of Phase 10 API*
