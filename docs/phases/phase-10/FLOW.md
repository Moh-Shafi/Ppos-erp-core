# Phase 10 — Production, Security & Monitoring — Flow

**Document Status:** DRAFT  
**Created:** 2026-08-18  
**Phase:** 10

---

## 1. USER FLOWS

### 1.1 Login with 2FA

```
Step 1: User submits credentials
  Client → POST /api/v1/auth/login { email, password }
  Server:
    → Check account lockout for email
    → If locked: return 423 { locked_until, retry_after }
    → Validate credentials
    → If invalid: increment failed_attempts, check thresholds, return 401
    → If valid:
      → Check if 2FA enabled for user
      → If 2FA enabled: return 200 { 2fa_required: true, temp_token }
      → If 2FA not enabled: issue Sanctum token, return 200 { user, token, modules, features }

Step 2 (only if 2FA required): User submits TOTP code
  Client → POST /api/v1/auth/login-2fa { temp_token, code }
  Server:
    → Validate temp_token (expires in 5 minutes)
    → Verify TOTP code (±1 window)
    → If valid: issue Sanctum token, mark two_factor_verified_at, return 200 { user, token }
    → If invalid: return 422 { message: "Invalid 2FA code" }
    → If backup code: verify, consume, issue token

Alternative: User uses backup code
  Client → POST /api/v1/auth/login-2fa { temp_token, code, is_backup: true }
  Server:
    → Check backup_codes array (hashed)
    → If match: remove code from array, issue token
    → If no match: return 422
```

### 1.2 Enable 2FA

```
Step 1: User requests 2FA enable
  Client → POST /api/v1/auth/2fa/enable
  Server:
    → Check if 2fa feature is enabled for tenant
    → Generate TOTP secret
    → Store encrypted secret (not yet enabled)
    → Generate QR code data URI
    → Return 200 { qr_code, secret, backup_codes }

Step 2: User scans QR code in authenticator app, enters code to verify
  Client → POST /api/v1/auth/2fa/verify { code }
  Server:
    → Verify TOTP code against stored secret
    → If valid:
      → Set two_factor_enabled = true
      → Set enabled_at = now
      → Store hashed backup codes
      → Log to audit: "2fa.enabled"
      → Return 200 { verified: true }
    → If invalid: return 422 { message: "Invalid code" }
```

### 1.3 Disable 2FA

```
  Client → POST /api/v1/auth/2fa/disable { code }
  Server:
    → Verify TOTP code (must be valid to disable)
    → If valid:
      → Set two_factor_enabled = false
      → Clear secret, backup_codes
      → Log to audit: "2fa.disabled"
      → Return 200 { disabled: true }
    → If invalid: return 422
```

### 1.4 Account Lockout Flow

```
Failed login attempt:
  → Check account_lockouts for username
  → If not locked:
    → Increment failed_attempts
    → If failed_attempts reaches threshold:
      → 5: locked_until = now + 15min, log "account.locked"
      → 10: locked_until = now + 1hr, log "account.locked"
      → 15: locked_until = now + 24hr, log "account.locked"
    → Return 401 { message: "Invalid credentials" }
  → If locked:
    → If locked_until > now: return 423 { retry_after: seconds }
    → If locked_until <= now: reset failed_attempts, proceed with login

Successful login:
  → Reset failed_attempts to 0
  → Clear locked_until
```

### 1.5 Password Change with History

```
  Client → PUT /api/v1/auth/profile { current_password, new_password }
  Server:
    → Verify current_password
    → Check new_password against complexity rules
    → Check new_password against last 5 password hashes in password_histories
    → If reuse detected: return 422 { message: "Cannot reuse recent passwords" }
    → If all checks pass:
      → Hash new password, update user
      → Store hash in password_histories
      → Log to audit: "password.changed"
      → Return 200 { updated: true }
```

### 1.6 Data Export (PDP Compliance)

```
  Client → GET /api/v1/account/export
  Server:
    → Verify authenticated user
    → Collect:
      → User profile (name, email, created_at)
      → Sales (last 12 months)
      → Payments (last 12 months)
      → Audit logs (last 90 days)
    → Generate JSON file
    → Log to audit: "account.exported"
    → Return file download (Content-Disposition: attachment)
```

### 1.7 Account Deletion (PDP Compliance)

```
  Client → DELETE /api/v1/account { password }
  Server:
    → Verify password
    → Verify user is owner or self
    → Soft-delete user
    → Anonymize: name → "Deleted User", email → "deleted_{id}@local"
    → Schedule hard delete: 30 days from now
    → Log to audit: "account.deletion_requested"
    → Return 202 { message: "Account scheduled for deletion in 30 days" }

Daily scheduled command:
    → Find users soft-deleted > 30 days ago
    → Hard delete user records
    → Anonymize related customer records
    → Log to audit: "account.purged"
```

### 1.8 Audit Log Query

```
  Client → GET /api/v1/audit-logs?page=1&entity_type=Sale&action=created&date_from=2026-08-01
  Server:
    → Verify permission: security.audit.view
    → Filter by tenant_id (always)
    → Apply optional filters: entity_type, action, user_id, date_from, date_to
    → Paginate (20 per page)
    → Return 200 { data: [...], meta: { current_page, total, ... } }

  Client → GET /api/v1/audit-logs?export=csv
  Server:
    → Same query but return CSV file
    → Content-Disposition: attachment; filename="audit-logs-2026-08-18.csv"
```

### 1.9 Health Check

```
  Client → GET /api/v1/health
  Server:
    → Rate limited (60/min)
    → No auth required
    → Run checks:
      → Database: SELECT 1
      → Redis: PING (if configured)
      → Storage: is_writable(storage/app)
      → Queue: check queue worker heartbeat (if configured)
    → If all pass: return 200 { status: "healthy", checks: {...} }
    → If any fail: return 503 { status: "degraded", checks: {...} }
```

### 1.10 Backup & Restore

```
Scheduled (daily 02:00):
  → php artisan backup:database
  → mysqldump → gzip → storage/app/backups/backup-{timestamp}.sql.gz
  → Log backup creation
  → php artisan backup:cleanup
  → Delete old backups per retention policy

Manual restore:
  → php artisan backup:restore --file=backup-2026-08-18-020000.sql.gz
  → Confirm: "This will overwrite the current database. Continue? (yes/no)"
  → Decompress → mysql import
  → Log restore action
```

---

## 2. SYSTEM FLOWS

### 2.1 Request Middleware Pipeline

```
Incoming HTTP Request
  │
  ├── CORS Middleware (config/cors.php)
  │     → Check Origin against allowlist
  │     → Add Access-Control-Allow-Origin header
  │     → Handle preflight OPTIONS
  │
  ├── XssSanitizer Middleware
  │     → Strip dangerous HTML from all input
  │     → Skip configured fields (rich text)
  │
  ├── Throttle Middleware (rate limiting)
  │     → throttle:tenant (1000/min per tenant)
  │     → throttle:user (200/min per user)
  │     → Return 429 if exceeded
  │
  ├── CheckAccountLockout (auth routes only)
  │     → Check lockout table for username
  │     → Return 423 if locked
  │
  ├── Sanctum Auth
  │     → Validate Bearer token
  │     → Set Auth user
  │
  ├── RequireTwoFactor (sensitive routes only)
  │     → Check 2FA feature enabled for tenant
  │     → Check user has 2FA enabled
  │     → Check two_factor_verified_at in session
  │     → Return 403 if not verified
  │
  ├── CheckPermission
  │     → Verify user has required permission
  │     → Return 403 if denied
  │
  ├── CheckModule
  │     → Verify module is enabled for tenant
  │     → Return 403 if disabled
  │
  ├── CheckFeature
  │     → Verify feature is enabled for tenant
  │     → Return 403 if disabled
  │
  └── Controller
        → Business logic
        → Model operations trigger AuditObserver
        → Response
```

### 2.2 Audit Observer Flow

```
Model Event (created/updated/deleted/restored)
  │
  ├── AuditObserver::created(Model $model)
  │     → AuditService::logModelEvent('created', $model)
  │     → Store: tenant_id, user_id, action, entity_type, entity_id, new_values, route, method, ip, user_agent
  │
  ├── AuditObserver::updated(Model $model)
  │     → Get original (old) values
  │     → AuditService::logModelEvent('updated', $model, $old)
  │     → Store: old_values + new_values
  │
  ├── AuditObserver::deleted(Model $model)
  │     → AuditService::logModelEvent('deleted', $model)
  │     → Store: old_values (full snapshot)
  │
  └── AuditObserver::restored(Model $model)
        → AuditService::logModelEvent('restored', $model)
```

### 2.3 CI/CD Pipeline Flow

```
Developer pushes to feature branch
  │
  ├── GitHub Actions CI triggered
  │     ├── Backend job:
  │     │     → Setup PHP 8.3 + MySQL 8.0
  │     │     → composer install
  │     │     → php artisan key:generate
  │     │     → php artisan migrate --force
  │     │     → ./vendor/bin/pint --test (code style)
  │     │     → ./vendor/bin/phpstan analyse (static analysis)
  │     │     → php artisan test (all tests)
  │     │
  │     └── Frontend job:
  │           → Setup Node 20
  │           → npm ci
  │           → npm run build
  │           → npx tsc --noEmit
  │
  ├── PR created → CI must pass before merge
  │
  ├── PR merged to main
  │     ├── CD triggered
  │     │     ├── Build Docker images
  │     │     ├── Push to GHCR
  │     │     ├── Deploy to staging
  │     │     ├── Run smoke tests on staging
  │     │     ├── If smoke tests pass:
  │     │     │     → Request manual approval for production
  │     │     │     → Deploy to production
  │     │     │     → Run smoke tests on production
  │     │     │     → Verify health endpoint
  │     │     └── If smoke tests fail:
  │     │           → Alert team, do not deploy to production
```

### 2.4 Error Tracking Flow (Sentry)

```
Application throws exception
  │
  ├── Laravel exception handler
  │     → Sentry SDK captures exception
  │     → Add context:
  │     │     ├── user: { id, tenant_id }
  │     │     ├── request: { url, method }
  │     │     ├── tags: { tenant_id, environment }
  │     │     └── extra: { route, controller }
  │     → Send to Sentry (async, non-blocking)
  │     → Also log to local log file (JSON format)
  │
  ├── Sentry dashboard
  │     → Group similar errors
  │     → Alert on new errors or high volume
  │     → Track release health
  │
  └── Response to client
        → 500 { message: "Server Error" } (no stack trace in production)
        → 400 { message: "Bad Request", errors: {...} } (validation)
```

### 2.5 OpenAPI Generation Flow

```
Developer writes/updates controller annotations
  │
  ├── Local: php artisan l5-swagger:generate
  │     → Scan all controller files for @OA annotations
  │     → Generate openapi.json
  │     → Save to storage/app/openapi.json
  │
  ├── CI: annotation generation step
  │     → Generate spec
  │     → Verify spec is valid
  │     → Compare with committed spec (detect drift)
  │
  ├── Production: spec cached
  │     → /api/docs serves Swagger UI + cached spec
  │     → /api/openapi.json serves raw spec
  │
  └── Staging: same as production but accessible
```

---

## 3. DATA FLOWS

### 3.1 Rate Limiting Data Flow

```
Request arrives
  │
  ├── Extract rate limit key:
  │     ├── tenant: $request->user()->tenant_id
  │     ├── user: $request->user()->id
  │     └── ip: $request->ip()
  │
  ├── Check RateLimiter::tooManyAttempts($key, $max)
  │     ├── If exceeded:
  │     │     → Return 429
  │     │     → Headers: X-RateLimit-Limit, X-RateLimit-Remaining, Retry-After
  │     └── If not exceeded:
  │           → RateLimiter::hit($key, $decaySeconds)
  │           → Proceed with request
  │
  └── Response includes rate limit headers
```

### 3.2 Cache Invalidation Flow

```
Model updated/deleted
  │
  ├── Model event fires
  │     → Cache invalidation listener
  │     → Determine affected cache keys:
  │       ├── Product updated → flush: tenant:{id}:category_tree (if category changed)
  │       ├── Module toggled → flush: tenant:{id}:modules, tenant:{id}:features
  │       ├── Role updated → flush: tenant:{id}:permissions:{user_id}
  │       └── BusinessProfile updated → flush: tenant:{id}:business_profile
  │
  └── Cache::tags(['tenant:{id}'])->forget($key)
```

---

*End of Phase 10 Flow*
