# Phase 10 — Production, Security & Monitoring — PDR

**Document Status:** DRAFT  
**Created:** 2026-08-18  
**Phase:** 10 — Production, Security & Monitoring  
**Depends On:** All previous phases (0–9)  
**Roadmap Reference:** `docs/PDR/02-PHASE_ROADMAP.md` — Phase 10

---

## 1. OBJECTIVE

Transform the ERP from a development-stage application into a **production-ready, security-hardened, and monitored platform**. This phase addresses all non-functional requirements needed for real-world deployment: security hardening, observability, CI/CD automation, performance optimization, and compliance review.

### 1.1 In Scope

**Security Hardening:**
- API rate limiting (per endpoint, per user, per tenant — extending existing `auth` and `api` limiters)
- CORS configuration (strict origin allowlist, no wildcard in production)
- Input sanitization middleware (XSS prevention — strip scripts, encode output)
- Password complexity rules (min 12 chars, mixed case, numbers, symbols — using Laravel `Password::min()`)
- Account lockout / brute-force protection (progressive lockout after N failed attempts)
- 2FA / TOTP authentication (feature-flagged per plan, using `pragmarx/google2fa` or `spomky-labs/otphp`)

**Audit & Observability:**
- Comprehensive audit log (extend existing `AuditService` to cover all CRUD operations via model observers)
- Sentry error tracking integration (Laravel SDK)
- Uptime monitoring (health check endpoint enhancement + external monitor config)
- Structured logging (JSON format for production, log rotation)

**Backup & Disaster Recovery:**
- Automated database backup (scheduled `php artisan db:backup` or `spatie/laravel-backup`)
- Backup verification (tested restore procedure)
- Backup retention policy (daily × 7, weekly × 4, monthly × 3)

**CI/CD & Deployment:**
- GitHub Actions CI/CD pipeline (run tests on push, auto-deploy on main)
- Staging environment configuration (separate `.env.staging`)
- Production deployment guide (step-by-step runbook)
- Docker production images (optimized, non-root, multi-stage build)

**API Documentation:**
- OpenAPI / Swagger specification (auto-generated from routes via `darkaonline/l5-swagger` or manual spec)
- API documentation UI (Swagger UI or ReDoc)
- Endpoint annotations on all controllers

**Performance Optimization:**
- Query indexing (audit missing indexes on hot tables)
- Eager loading audit (eliminate N+1 queries on list endpoints)
- Cache strategy (Redis for session, cache, queue in production)
- Query optimization (review slow queries, add composite indexes)

**Load Testing:**
- Load test with 100 concurrent users (using `wrk`, `k6`, or Laravel's benchmarking)
- Identify and fix bottlenecks
- Document performance baseline

**Security Penetration Testing:**
- Automated security scan (static analysis with `larastan` / `phpstan`)
- OWASP Top 10 review
- IDOR / mass-assignment audit (verify all endpoints)
- SQL injection check (verify parameterized queries)

**Compliance:**
- Indonesia PDP Law (UU PDP No. 27/2022) compliance review
- Data retention policy
- Right to erasure implementation
- Data export for user requests

### 1.2 Out of Scope (Deferred)

- Subscription & billing (deferred — not in Phase 10 scope)
- Desktop application (Phase 11)
- Mobile app (Phase 12)
- WebSocket real-time push (future)
- Multi-region deployment (future)
- Kubernetes orchestration (future — Docker Compose for now)
- APM tools (New Relic, Datadog — future, Sentry covers current needs)

### 1.3 Guiding Principle

> Production readiness is **not a feature** — it is a **quality gate**. Every change in Phase 10 must either improve security, observability, reliability, or performance. No new business features are added. The existing 1197-test baseline must remain green at every step.

---

## 2. SHARED FOUNDATION ANALYSIS

### 2.1 What Exists and Is Reused

| Capability | From Phase | Usage in Phase 10 |
|-----------|-----------|-------------------|
| Multi-tenant isolation | Phase 0 | All security measures are tenant-aware |
| RBAC + module/feature system | Phase 0 | 2FA feature-flagged via feature system |
| Rate limiters (`auth`, `api`) | Phase 0 | Extended with per-tenant, per-endpoint limiters |
| Audit logs (`AuditService`, `AuditLog` model) | Phase 0 | Extended with model observers for comprehensive logging |
| Sanctum token auth | Phase 0 | Token expiration + purge added |
| Integration API rate limiting | Phase 9 | Pattern reused for global rate limiting strategy |
| Health check endpoint (`/up`) | Laravel | Enhanced with dependency checks |
| Logging config | Laravel | Upgraded to structured JSON for production |

### 2.2 What Is New

| Component | Description |
|-----------|-------------|
| `PasswordPolicy` | Password complexity validation rules |
| `AccountLockout` middleware | Brute-force protection with progressive lockout |
| `TwoFactorAuth` service | TOTP generation, verification, backup codes |
| `XssSanitizer` middleware | Input sanitization for XSS prevention |
| `AuditObserver` (model observers) | Auto-log all create/update/delete on key models |
| `SentryServiceProvider` | Sentry SDK integration, error reporting |
| `BackupCommand` / scheduled backup | Automated DB backup with retention |
| `OpenAPIGenerator` | Auto-generate OpenAPI spec from route annotations |
| `PerformanceMonitor` | Query count, memory usage per request (dev/debug) |
| GitHub Actions workflow | CI pipeline: lint → test → build → deploy |
| Docker production Dockerfile | Multi-stage, non-root, optimized image |

### 2.3 What Is Modified

| Component | Change |
|-----------|--------|
| `AppServiceProvider` | Add per-tenant rate limiter, register observers |
| `bootstrap/app.php` | Add new middleware aliases (xss, lockout, 2fa) |
| `config/cors.php` (new) | Strict CORS configuration |
| `config/logging.php` | Add JSON channel for production, Sentry channel |
| `config/auth.php` | Add 2FA guard configuration |
| `composer.json` | Add Sentry, 2FA, OpenAPI, backup packages |
| `.env.example` | Add new environment variables |
| `routes/api.php` | Add OpenAPI annotations, apply new middleware |

---

## 3. FUNCTIONAL REQUIREMENTS

### 3.1 Security Hardening

#### 3.1.1 API Rate Limiting

**Current state:** Two limiters exist — `auth` (5/min) and `api` (60/min), both disabled in local/testing.

**Required:**
- Per-tenant rate limiting (configurable, default 1000 req/min per tenant)
- Per-user rate limiting (default 200 req/min per user)
- Per-endpoint rate limiting (auth endpoints: 5/min, write endpoints: 60/min, read endpoints: 300/min)
- Rate limit headers in responses (`X-RateLimit-Remaining`, `X-RateLimit-Limit`, `Retry-After`)
- Rate limit response: 429 with `Retry-After` header
- Configurable via environment variables
- Bypassed in testing environment

#### 3.1.2 CORS Configuration

**Current state:** No `config/cors.php` exists (Laravel 11 default is permissive in dev).

**Required:**
- `config/cors.php` with strict configuration
- Allowed origins: configurable via `CORS_ALLOWED_ORIGINS` env var
- No wildcard (`*`) in production
- Allowed methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
- Allowed headers: Authorization, Content-Type, X-Store-Id, X-Idempotency-Key
- Credentials: true (for Sanctum)
- Max age: 86400 (preflight cache)

#### 3.1.3 Input Sanitization (XSS Prevention)

**Required:**
- Global middleware that sanitizes all input (strip `<script>`, `on*=` attributes, `javascript:` URIs)
- Uses `e()` / `htmlspecialchars` for output encoding in Blade (if any)
- JSON API responses are inherently safe (Content-Type: application/json)
- Sanitization bypassed for fields explicitly marked as HTML content (rich text fields)
- Sanitization does not alter valid business data (product names with `<` or `>` are escaped, not stripped)

#### 3.1.4 Password Complexity

**Current state:** Registration uses `required|string|min:8` for password.

**Required:**
- Min 12 characters
- At least 1 uppercase letter
- At least 1 lowercase letter
- At least 1 number
- At least 1 special character
- Not in common password blacklist (top 1000)
- Password history check (cannot reuse last 5 passwords)
- Applied to: registration, password change, password reset
- Backward compatible: existing users not forced to change until next password update

#### 3.1.5 Account Lockout

**Current state:** Rate limiter on auth endpoints (5/min) but no progressive lockout.

**Required:**
- After 5 failed login attempts: lock account for 15 minutes
- After 10 failed attempts: lock account for 1 hour
- After 15 failed attempts: lock account for 24 hours
- Lockout is per-username (not per-IP, to prevent distributed attacks)
- Successful login resets the counter
- Locked accounts receive 423 Locked status with `Retry-After` header
- Admin can manually unlock
- Lockout events logged to audit log
- Bypassed in testing environment

#### 3.1.6 Two-Factor Authentication (2FA / TOTP)

**Required:**
- TOTP-based 2FA using `pragmarx/google2fa` or `spomky-labs/otphp`
- Feature-flagged: only available when `2fa` feature is enabled for tenant
- User can enable/disable 2FA from profile settings
- QR code generation for authenticator app setup
- Backup codes (10 single-use codes) generated on enable
- Login flow: username + password → if 2FA enabled, prompt for TOTP code → verify → issue token
- TOTP code window: ±1 time step (30s default)
- Backup codes can be used in place of TOTP (consumed on use)
- 2FA disable requires current TOTP code
- 2FA status shown in user profile
- Admin can reset 2FA for a user (with audit log)

### 3.2 Audit & Observability

#### 3.2.1 Comprehensive Audit Log

**Current state:** `AuditService` logs login/logout, module enable/disable. Not comprehensive.

**Required:**
- Model observers on all key models: Product, Category, Sale, Payment, Purchase, Inventory, Customer, Supplier, Integration, WebhookEndpoint, IntegrationApiKey
- Auto-log: create, update, delete, restore
- Log fields: tenant_id, user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent, route, method
- Configurable: which models to observe (config file)
- Performance: async logging via queue (optional, configurable)
- Retention: 90 days (configurable), auto-purge old logs
- API endpoint: `GET /api/v1/audit-logs` (existing, enhanced with new filters)
- Export: CSV export of audit logs

#### 3.2.2 Sentry Error Tracking

**Required:**
- `sentry/sentry-laravel` package installed
- DSN configurable via `SENTRY_DSN` env var
- Environment tagging (production, staging)
- Release tracking (git commit SHA)
- User context (user_id, tenant_id — no PII)
- Request context (URL, method, headers — sanitized)
- Performance monitoring (transactions, traces)
- Sample rate configurable (100% in staging, 10% in production)
- Disabled in testing environment
- Graceful degradation if DSN not set

#### 3.2.3 Uptime Monitoring

**Required:**
- Enhanced `/up` health check endpoint with dependency status:
  - Database connection
  - Redis connection (if configured)
  - Queue worker status
  - Storage disk writable
- `GET /api/v1/health` returns JSON with component status
- External monitor configuration guide (UptimeRobot, BetterStack)
- Alert webhook for health check failures

#### 3.2.4 Structured Logging

**Required:**
- JSON-formatted logs in production (machine-readable)
- Log channels: application, audit, security, integration, payment
- Log rotation: daily, keep 30 days
- Log levels: DEBUG (dev), INFO (staging), ERROR (production)
- Sensitive data redaction in logs (passwords, tokens, API keys, secrets)
- Log to file + Sentry (errors only)

### 3.3 Backup & Disaster Recovery

#### 3.3.1 Automated Database Backup

**Required:**
- `spatie/laravel-backup` package (or custom command)
- Scheduled daily backup at 02:00 AM
- Backup destination: local disk + optional S3-compatible storage
- Backup contents: database dump + application files (optional)
- Compression: gzip
- Encryption: optional (AES-256 for backup files)
- Monitoring: alert if backup fails

#### 3.3.2 Backup Retention

**Required:**
- Daily backups: keep 7
- Weekly backups: keep 4
- Monthly backups: keep 3
- Auto-cleanup of old backups
- Backup size monitoring

#### 3.3.3 Backup Restore

**Required:**
- `php artisan backup:restore` command
- Tested restore procedure (documented)
- Restore runbook (step-by-step guide)
- Quarterly restore test (scheduled reminder)

### 3.4 CI/CD & Deployment

#### 3.4.1 GitHub Actions CI Pipeline

**Required:**
- Trigger: push to any branch, PR to main
- Steps:
  1. Checkout code
  2. Setup PHP 8.3
  3. Install Composer dependencies
  4. Copy .env.testing
  5. Generate key
  6. Run migrations (testing DB)
  7. Run Pint (code style)
  8. Run PHPStan (static analysis)
  9. Run PHPUnit (all tests)
  10. Build frontend (npm ci + npm run build)
  11. Upload test results artifact
- Cache: Composer packages, npm packages
- Fail fast: stop on first error
- Status badge in README

#### 3.4.2 GitHub Actions CD Pipeline

**Required:**
- Trigger: push to main (after CI passes)
- Steps:
  1. Build production Docker images
  2. Push to container registry (GitHub Container Registry)
  3. SSH to staging server
  4. Pull new images
  5. Run database migrations
  6. Restart services
  7. Run smoke tests
  8. If staging passes, manual approval for production
  9. Deploy to production
  10. Run smoke tests on production

#### 3.4.3 Staging Environment

**Required:**
- `.env.staging` configuration
- Separate database (pos_saas_staging)
- Separate Redis (if used)
- Sentry DSN for staging
- Same Docker Compose setup, different env vars
- Data: sanitized copy of production (no real PII)

#### 3.4.4 Production Deployment Guide

**Required:**
- Step-by-step runbook document
- Prerequisites: server specs, Docker, MySQL, Redis
- Environment variable checklist
- SSL/TLS setup (Let's Encrypt / Caddy)
- Firewall rules
- First deploy procedure
- Update deploy procedure
- Rollback procedure
- Health verification checklist

### 3.5 API Documentation

#### 3.5.1 OpenAPI Specification

**Required:**
- OpenAPI 3.1 specification for all API endpoints
- Auto-generated from controller annotations (using `darkaonline/l5-swagger` or `zircote/swagger-php`)
- Schema definitions for all request/response bodies
- Authentication schemes documented (Bearer token, API Key)
- Error response schemas documented
- Versioned: `/api/v1` prefix
- Served at `/api/docs` (Swagger UI) and `/api/openapi.json` (raw spec)

#### 3.5.2 Controller Annotations

**Required:**
- `@OA\Info` on a base controller or config
- `@OA\Path` + `@OA\Operation` on each endpoint
- `@OA\Schema` for request/response DTOs
- `@OA\SecurityScheme` for Bearer + API Key auth
- `@OA\Tag` grouping by module

### 3.6 Performance Optimization

#### 3.6.1 Query Indexing

**Required:**
- Audit all database tables for missing indexes
- Add composite indexes on frequently queried columns
- Add indexes on foreign keys that lack them
- Add indexes on `tenant_id` + frequently filtered columns
- Document all index changes
- Migration with `CREATE INDEX CONCURRENTLY` equivalent (or accept brief lock)

#### 3.6.2 Eager Loading

**Required:**
- Audit all list endpoints for N+1 queries
- Add `$with` properties or `::with()` calls
- Use `load()` for conditional eager loading
- Verify with query count logging in tests

#### 3.6.3 Caching

**Required:**
- Redis configuration for production (session, cache, queue)
- Cache frequently accessed, rarely changing data:
  - Module/feature configuration per tenant
  - Business type defaults
  - Product categories tree
  - User permissions
- Cache invalidation on relevant model updates
- Cache tags for bulk invalidation
- Configurable cache TTLs

### 3.7 Load Testing

**Required:**
- Load test scenario: 100 concurrent users
- Tools: `k6` or `wrk` (preferred)
- Endpoints tested: login, product list, checkout, sale list, dashboard
- Metrics: response time (p50, p95, p99), throughput (req/s), error rate
- Target: p95 < 500ms, error rate < 0.1%
- Document bottlenecks found and fixes applied
- Load test script committed to repo

### 3.8 Security Penetration Testing

**Required:**
- Static analysis: `larastan/phpstan-neon` at level 6+
- OWASP Top 10 checklist:
  - A01: Broken Access Control — verify all endpoints have RBAC
  - A02: Cryptographic Failures — verify encryption at rest, TLS in transit
  - A03: Injection — verify parameterized queries, no raw SQL
  - A04: Insecure Design — verify threat model
  - A05: Security Misconfiguration — verify debug off, CORS strict, headers
  - A06: Vulnerable Components — `composer audit`
  - A07: Auth Failures — verify lockout, password policy, 2FA
  - A08: Data Integrity Failures — verify signed webhooks, CSRF
  - A09: Logging Failures — verify audit log coverage
  - A10: SSRF — verify webhook URL validation (Phase 9)
- IDOR audit: verify every show/update/delete checks tenant ownership
- Mass assignment audit: verify `$fillable` on all models
- Report: document findings and remediations

### 3.9 Compliance — Indonesia PDP Law

**Required:**
- Data inventory: what PII is collected, where stored, how used
- Data retention policy: define retention periods per data type
- Right to erasure: `DELETE /api/v1/account` endpoint (soft delete + purge after 30 days)
- Data export: `GET /api/v1/account/export` endpoint (JSON/CSV of all user data)
- Consent log: record when user consents to data processing (registration)
- Privacy policy document (template)
- Data processing register (what data, why, who accesses, how long)
- Encryption verification: PII encrypted at rest (credentials, API keys already encrypted)

---

## 4. DATA MODEL CHANGES

### 4.1 New Tables

#### `two_factor_auth`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned | PK |
| user_id | bigint unsigned | FK → users |
| secret | text | Encrypted TOTP secret |
| backup_codes | json | Array of hashed backup codes |
| enabled_at | timestamp | When 2FA was enabled |
| last_used_at | timestamp nullable | Last TOTP verification |
| created_at | timestamp | |
| updated_at | timestamp | |

#### `password_histories`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned | PK |
| user_id | bigint unsigned | FK → users |
| password_hash | string(255) | Hashed password |
| created_at | timestamp | |

#### `account_lockouts`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned | PK |
| user_id | bigint unsigned nullable | FK → users (null if username doesn't exist) |
| username | string(255) | Attempted username |
| failed_attempts | integer | Counter |
| locked_until | timestamp nullable | Lockout expiry |
| last_attempt_at | timestamp | Last failed attempt |
| ip_address | string(45) | IP of attempt |
| created_at | timestamp | |
| updated_at | timestamp | |

#### `data_retention_policies`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned | PK |
| tenant_id | bigint unsigned | FK → tenants |
| data_type | string(50) | e.g. 'sales', 'audit_logs', 'customers' |
| retention_days | integer | Days to retain |
| auto_purge | boolean | Whether to auto-delete |
| created_at | timestamp | |
| updated_at | timestamp | |

### 4.2 Modified Tables

| Table | Change |
|-------|--------|
| `users` | Add `two_factor_enabled` (boolean, default false), `two_factor_verified_at` (timestamp nullable) |
| `audit_logs` | Add `route` (string nullable), `method` (string nullable), add index on `[tenant_id, created_at]` |

### 4.3 New Config Files

| File | Purpose |
|------|---------|
| `config/cors.php` | CORS configuration |
| `config/security.php` | Password policy, lockout thresholds, 2FA settings |
| `config/audit.php` | Which models to observe, retention period |
| `config/backup.php` | Backup destination, schedule, retention |
| `config/sentry.php` | Sentry DSN, environment, sample rate |
| `config/openapi.php` | OpenAPI/Swagger configuration |

---

## 5. API ENDPOINT CHANGES

### 5.1 New Endpoints

#### 2FA Management
| Method | Path | Description | Permission |
|--------|------|-------------|------------|
| POST | `/api/v1/auth/2fa/enable` | Enable 2FA (returns QR code) | Authenticated |
| POST | `/api/v1/auth/2fa/verify` | Verify TOTP code and confirm 2FA | Authenticated |
| POST | `/api/v1/auth/2fa/disable` | Disable 2FA (requires TOTP) | Authenticated |
| GET | `/api/v1/auth/2fa/status` | Get 2FA status | Authenticated |
| POST | `/api/v1/auth/2fa/backup-codes` | Regenerate backup codes | Authenticated |
| POST | `/api/v1/auth/login-2fa` | Login with TOTP after password | Public |

#### Account Management (PDP Compliance)
| Method | Path | Description | Permission |
|--------|------|-------------|------------|
| GET | `/api/v1/account/export` | Export all user data | Authenticated |
| DELETE | `/api/v1/account` | Request account deletion | Owner |
| GET | `/api/v1/account/consent` | View consent log | Authenticated |

#### Health & Monitoring
| Method | Path | Description | Permission |
|--------|------|-------------|------------|
| GET | `/api/v1/health` | Detailed health check | Public (rate limited) |
| GET | `/api/docs` | Swagger UI | Public (staging only) |
| GET | `/api/openapi.json` | OpenAPI spec | Public (staging only) |

#### Admin Security
| Method | Path | Description | Permission |
|--------|------|-------------|------------|
| POST | `/api/v1/admin/users/{id}/unlock` | Unlock user account | Owner |
| POST | `/api/v1/admin/users/{id}/reset-2fa` | Reset user 2FA | Owner |

### 5.2 Modified Endpoints

| Endpoint | Change |
|----------|--------|
| `POST /api/v1/auth/login` | Add lockout check, return `2fa_required` flag if enabled |
| `POST /api/v1/auth/register` | Apply new password complexity rules |
| `PUT /api/v1/auth/profile` | Apply password history check on password change |
| `GET /api/v1/audit-logs` | Add route/method filters, add CSV export |

---

## 6. FRONTEND CHANGES

### 6.1 New Pages

- **Security Settings Page** (`/settings/security`): 2FA enable/disable, backup codes, password change with history
- **Audit Log Page** (`/settings/audit-logs`): Enhanced audit log viewer with filters and CSV export
- **Account Privacy Page** (`/settings/privacy`): Data export, account deletion request, consent log

### 6.2 Modified Pages

- **Login Page**: Add 2FA code input step (conditional)
- **Profile Page**: Add 2FA status, password change with new complexity rules
- **Dashboard**: Add system health indicator (if admin)

### 6.3 Navigation

- New "Settings" group with: Security, Audit Logs, Privacy
- RBAC: Security and Audit Logs require owner/manager, Privacy for all users

---

## 7. ACCEPTANCE CRITERIA

### 7.1 Security Hardening
- [ ] Rate limiting active on all API endpoints (per-tenant, per-user, per-endpoint)
- [ ] CORS configured with strict origins (no wildcard in production)
- [ ] XSS sanitization middleware active on all input
- [ ] Password complexity enforced (12+ chars, mixed case, numbers, symbols)
- [ ] Password history prevents reuse of last 5 passwords
- [ ] Account lockout after 5/10/15 failed attempts with progressive delays
- [ ] 2FA enable/disable/verify flow works
- [ ] 2FA backup codes work
- [ ] 2FA login flow works (password → TOTP → token)
- [ ] 2FA feature-flagged per tenant

### 7.2 Audit & Observability
- [ ] Model observers log all CRUD on key models
- [ ] Audit log includes route, method, IP, user agent
- [ ] Sentry captures errors (verified with test error)
- [ ] Health check endpoint reports all dependency statuses
- [ ] Structured JSON logging in production
- [ ] Sensitive data redacted in logs

### 7.3 Backup & Recovery
- [ ] Daily backup runs automatically (verified)
- [ ] Backup retention policy enforced
- [ ] Backup restore tested and documented
- [ ] Backup failure triggers alert

### 7.4 CI/CD
- [ ] GitHub Actions CI runs all tests on push
- [ ] CI includes Pint + PHPStan + PHPUnit
- [ ] CD pipeline deploys to staging on main push
- [ ] Production deploy requires manual approval
- [ ] Smoke tests run after deploy

### 7.5 API Documentation
- [ ] OpenAPI spec generated for all endpoints
- [ ] Swagger UI accessible at `/api/docs`
- [ ] All schemas documented (request + response)
- [ ] Authentication schemes documented

### 7.6 Performance
- [ ] All N+1 queries eliminated on list endpoints
- [ ] Database indexes added for hot queries
- [ ] Redis configured for production (session, cache, queue)
- [ ] Load test: 100 concurrent users, p95 < 500ms, error rate < 0.1%

### 7.7 Security Review
- [ ] PHPStan level 6 passes
- [ ] OWASP Top 10 review completed
- [ ] IDOR audit: all endpoints verify tenant ownership
- [ ] Mass assignment audit: all models have `$fillable`
- [ ] `composer audit` passes (no known vulnerabilities)

### 7.8 Compliance
- [ ] PDP data inventory completed
- [ ] Data retention policy documented
- [ ] Right to erasure endpoint works
- [ ] Data export endpoint works
- [ ] Consent log recorded at registration

### 7.9 Regression
- [ ] All 1197 existing tests pass
- [ ] New tests for all Phase 10 features
- [ ] Full regression suite green

---

## 8. RISKS & MITIGATIONS

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| 2FA breaks existing login flow | Medium | High | Feature-flagged, disabled by default, tested |
| Rate limiting breaks existing tests | High | Medium | Bypassed in testing environment |
| Audit observers slow down writes | Medium | Medium | Async logging via queue (configurable) |
| Backup storage fills up | Low | Medium | Retention policy + size monitoring |
| OpenAPI annotations are incomplete | Medium | Low | Auto-generation + manual review |
| Load test reveals deep performance issues | Medium | High | Time-boxed optimization, document findings |
| PDP compliance requires legal review | High | High | Template provided, legal review by client |

---

## 9. IMPLEMENTATION ORDER

1. **Security Hardening** (password policy, lockout, XSS, CORS, rate limiting)
2. **2FA** (TOTP, backup codes, login flow)
3. **Audit Observers** (model observers, enhanced logging)
4. **Sentry Integration** (error tracking, performance)
5. **Health Check Enhancement** (dependency monitoring)
6. **OpenAPI Documentation** (annotations, Swagger UI)
7. **Performance Optimization** (indexes, eager loading, caching)
8. **Backup Automation** (scheduled backup, retention, restore)
9. **CI/CD Pipeline** (GitHub Actions, staging, deployment guide)
10. **Load Testing** (k6 scripts, bottleneck fixes)
11. **Security Review** (PHPStan, OWASP, IDOR audit)
12. **PDP Compliance** (data export, erasure, consent)
13. **Frontend** (security settings, audit log, privacy pages)
14. **E2E Tests** (all new flows)
15. **Full Regression** (1197 + new tests)
16. **Audit & CLOSE**

---

## 10. ENVIRONMENT VARIABLES (NEW)

```env
# CORS
CORS_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com

# Security
PASSWORD_MIN_LENGTH=12
LOCKOUT_THRESHOLD_1=5
LOCKOUT_THRESHOLD_2=10
LOCKOUT_THRESHOLD_3=15
LOCKOUT_DURATION_1=900
LOCKOUT_DURATION_2=3600
LOCKOUT_DURATION_3=86400

# 2FA
TWO_FA_ISSUER=POS-SaaS
TWO_FA_WINDOW=1

# Sentry
SENTRY_DSN=
SENTRY_ENVIRONMENT=production
SENTRY_SAMPLE_RATE=0.1
SENTRY_TRACES_SAMPLE_RATE=0.1

# Backup
BACKUP_DISK=local
BACKUP_S3_BUCKET=
BACKUP_S3_REGION=
BACKUP_S3_KEY=
BACKUP_S3_SECRET=
BACKUP_NOTIFICATION_EMAIL=admin@example.com

# Redis (production)
REDIS_HOST=redis
REDIS_PASSWORD=
REDIS_PORT=6379

# OpenAPI
OPENAPI_HOST=api.example.com
OPENAPI_SCHEME=https
```

---

*End of Phase 10 PDR*
