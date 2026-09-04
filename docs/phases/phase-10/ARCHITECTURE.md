# Phase 10 — Production, Security & Monitoring — Architecture

**Document Status:** DRAFT  
**Created:** 2026-08-18  
**Phase:** 10 — Production, Security & Monitoring  
**Depends On:** All previous phases (0–9)

---

## 1. ARCHITECTURE OVERVIEW

Phase 10 does not introduce new business domains. Instead, it adds **cross-cutting infrastructure** that wraps the entire application. The architecture can be visualized as concentric layers around the existing ERP core:

```
┌─────────────────────────────────────────────────────────┐
│                    External Layer                        │
│  CORS · Rate Limiting · XSS Filter · TLS Termination     │
├─────────────────────────────────────────────────────────┤
│                  Authentication Layer                    │
│  Sanctum Token · 2FA/TOTP · Account Lockout · Password   │
├─────────────────────────────────────────────────────────┤
│                   Application Layer                      │
│  Controllers · Services · Models (Phases 0-9)            │
│  Audit Observers · Performance Monitor                   │
├─────────────────────────────────────────────────────────┤
│                   Observability Layer                    │
│  Sentry · Structured Logs · Health Checks · Audit Log    │
├─────────────────────────────────────────────────────────┤
│                    Data Layer                            │
│  MySQL · Redis (cache/session/queue) · Backups           │
├─────────────────────────────────────────────────────────┤
│                   CI/CD Layer                            │
│  GitHub Actions · Docker · Staging · Production          │
└─────────────────────────────────────────────────────────┘
```

---

## 2. COMPONENT ARCHITECTURE

### 2.1 Security Middleware Stack

**Request lifecycle (order matters):**

```
Request → CORS → XSS Sanitizer → Rate Limiter → Throttle
       → Account Lockout Check → Sanctum Auth → 2FA Check
       → Permission Check → Module Check → Feature Check
       → Controller
```

#### 2.1.1 XssSanitizer Middleware

```
App\Http\Middleware\XssSanitizer
```

- Runs before controller
- Iterates all input keys
- Strips: `<script>`, `on*=` attributes, `javascript:` URIs, `<iframe>`, `<object>`, `<embed>`
- Does NOT strip: `<`, `>` in isolation (escaped by JSON response)
- Configurable skip fields (rich text fields): `config('security.xss.skip_fields')`
- Returns sanitized input to controller

#### 2.1.2 AccountLockout Middleware

```
App\Http\Middleware\CheckAccountLockout
```

- Applied only to `POST /api/v1/auth/login`
- Checks `account_lockouts` table for username
- If `locked_until > now()`: return 423 Locked with `Retry-After`
- If not locked: pass through, login controller increments on failure
- Bypassed in testing environment

#### 2.1.3 TwoFactorAuth Middleware

```
App\Http\Middleware\RequireTwoFactor
```

- Applied to routes that require 2FA (admin endpoints, sensitive operations)
- Checks if user has `two_factor_enabled = true` and `two_factor_verified_at` within current session
- If 2FA enabled but not verified in this session: return 403 with `2fa_required: true`
- If 2FA not enabled: pass through (2FA is optional per user)
- Feature-flagged: only active when `2fa` feature is enabled for tenant

#### 2.1.4 Rate Limiter Enhancement

**Existing limiters (in `AppServiceProvider`):**
- `auth`: 5/min (disabled in testing)
- `api`: 60/min (disabled in testing)

**New limiters:**
- `tenant`: 1000/min per tenant_id (configurable via `RATE_LIMIT_TENANT_PER_MIN`)
- `user`: 200/min per user_id (configurable via `RATE_LIMIT_USER_PER_MIN`)
- `write`: 60/min per user for POST/PUT/PATCH/DELETE (configurable)
- `read`: 300/min per user for GET (configurable)

**Implementation:**
```php
RateLimiter::for('tenant', function (Request $request) {
    if (app()->environment('testing', 'local')) {
        return Limit::none();
    }
    $tenantId = $request->user()?->tenant_id ?? 'anonymous';
    return Limit::perMinute(config('security.rate_limit.tenant', 1000))
        ->by($tenantId);
});
```

**Applied in routes:**
```php
Route::middleware(['throttle:tenant', 'throttle:user'])->group(function () {
    // All API routes
});
```

### 2.2 Two-Factor Authentication (2FA)

#### 2.2.1 Service Design

```
App\Services\TwoFactorService
```

**Methods:**
- `generateSecret(User $user): string` — Generate TOTP secret, store encrypted
- `getQrCode(User $user): string` — Return QR code data URI for authenticator app
- `verify(User $user, string $code): bool` — Verify TOTP code (±1 window)
- `enable(User $user, string $code): bool` — Enable 2FA after verification
- `disable(User $user, string $code): bool` — Disable 2FA (requires valid code)
- `generateBackupCodes(User $user): array` — Generate 10 single-use backup codes
- `verifyBackupCode(User $user, string $code): bool` — Verify and consume backup code
- `resetForUser(User $user): void` — Admin reset (clears 2FA, logs to audit)

**Packages:**
- `pragmarx/google2fa` (TOTP generation/verification)
- `bacon/bacon-qr-code` (QR code generation)

#### 2.2.2 Login Flow with 2FA

```
POST /api/v1/auth/login
  → Validate credentials (email + password)
  → Check account lockout
  → If credentials valid:
    → If user has 2FA enabled:
      → Return 200 with { "2fa_required": true, "2fa_token": "temp_token" }
    → Else:
      → Issue Sanctum token, return user + token
  → If credentials invalid:
    → Increment failed attempts
    → Check lockout thresholds
    → Return 401

POST /api/v1/auth/login-2fa
  → Validate { 2fa_token, code }
  → Verify TOTP code or backup code
  → If valid:
    → Issue Sanctum token, return user + token
    → Mark two_factor_verified_at
  → If invalid:
    → Return 422
```

#### 2.2.3 Data Model

```
users
  ├── two_factor_enabled (boolean, default false)
  ├── two_factor_verified_at (timestamp, nullable)
  └── two_factor_auth (1:1)
        ├── secret (encrypted text)
        ├── backup_codes (json, hashed)
        ├── enabled_at (timestamp)
        └── last_used_at (timestamp, nullable)
```

### 2.3 Audit Observer System

#### 2.3.1 Observer Architecture

```
App\Observers\AuditObserver (generic, applied to multiple models)
```

**Design:**
- Single observer class, reusable across models
- Registered in `AppServiceProvider::boot()` for each configured model
- Listens to: `created`, `updated`, `deleted`, `restored` events
- Delegates to `AuditService::logModelEvent()`
- Configurable model list in `config/audit.php`

**Configuration (`config/audit.php`):**
```php
return [
    'observed_models' => [
        Product::class,
        Category::class,
        Sale::class,
        Payment::class,
        Purchase::class,
        Inventory::class,
        Customer::class,
        Supplier::class,
        TenantIntegration::class,
        WebhookEndpoint::class,
        IntegrationApiKey::class,
    ],
    'retention_days' => env('AUDIT_RETENTION_DAYS', 90),
    'async' => env('AUDIT_ASYNC', false),
    'queue' => 'audit',
];
```

#### 2.3.2 Audit Log Schema (Enhanced)

```
audit_logs
  ├── id (bigint, PK)
  ├── tenant_id (bigint, FK)
  ├── user_id (bigint, FK, nullable)
  ├── action (string: created, updated, deleted, restored, login, logout, etc.)
  ├── entity_type (string: class name or label)
  ├── entity_id (bigint, nullable)
  ├── old_values (json, nullable)
  ├── new_values (json, nullable)
  ├── ip_address (string, nullable)
  ├── user_agent (string, nullable)
  ├── route (string, nullable) — NEW
  ├── method (string, nullable) — NEW
  ├── created_at (timestamp)
  └── updated_at (timestamp)
  
  Index: [tenant_id, created_at] — NEW
  Index: [entity_type, entity_id] — NEW
```

#### 2.3.3 Async Logging (Optional)

When `AUDIT_ASYNC=true`:
- Audit events dispatched to `audit` queue
- Queue worker processes and writes to DB
- Non-blocking for the main request
- Fallback to sync if queue worker not running

### 2.4 Sentry Integration

#### 2.4.1 Service Provider

```
App\Providers\SentryServiceProvider
```

**Registration:**
- If `SENTRY_DSN` is set, initialize Sentry SDK
- Set environment tag from `SENTRY_ENVIRONMENT`
- Set release tag from git commit SHA
- Configure sample rates from env

**Context:**
- Before any request: set user context (id, tenant_id — no email/name)
- Set request context (URL, method — no headers/body)
- Set tags: tenant_id, environment, php_version

**Integration with Laravel:**
- `sentry/sentry-laravel` package
- Registered in `bootstrap/providers.php`
- Error handler captures all unhandled exceptions
- Performance monitoring via transaction middleware

#### 2.4.2 Configuration

```php
// config/sentry.php
return [
    'dsn' => env('SENTRY_DSN'),
    'environment' => env('SENTRY_ENVIRONMENT', app()->environment()),
    'release' => env('SENTRY_RELEASE', trim(exec('git rev-parse --short HEAD 2>/dev/null'))),
    'sample_rate' => env('SENTRY_SAMPLE_RATE', 1.0),
    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
    'send_default_pii' => false,
];
```

### 2.5 Health Check System

#### 2.5.1 Enhanced Health Endpoint

```
GET /api/v1/health
```

**Response (200 — all healthy):**
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

**Response (503 — degraded):**
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

**Implementation:**
- `App\Http\Controllers\HealthController`
- Checks run in parallel (or sequential with timeout)
- Each check has a 2-second timeout
- Rate limited: 60/min per IP
- No authentication required

### 2.6 Backup System

#### 2.6.1 Architecture

```
App\Console\Commands\BackupDatabaseCommand
```

- Uses `mysqldump` (available in Docker container)
- Output: gzip-compressed SQL file
- Destination: `storage/app/backups/` (local) or S3
- Naming: `backup-{Y-m-d-His}.sql.gz`
- Scheduled: daily at 02:00 via `routes/console.php`

#### 2.6.2 Retention Cleanup

```
App\Console\Commands\CleanupBackupsCommand
```

- Runs daily after backup
- Deletes backups older than retention policy:
  - Daily: keep last 7
  - Weekly: keep last 4 (Sunday backups)
  - Monthly: keep last 3 (1st of month backups)
- Logs cleanup actions

#### 2.6.3 Restore Command

```
App\Console\Commands\RestoreDatabaseCommand
```

- Argument: `--file=backup-2026-08-18-020000.sql.gz`
- Decompresses and imports SQL
- Confirmation prompt before overwrite
- Logs restore action to audit

### 2.7 OpenAPI Documentation

#### 2.7.1 Package Choice

- `darkaonline/l5-swagger` (Swagger UI integration)
- `zircote/swagger-php` (annotation parsing)

#### 2.7.2 Architecture

```
config/openapi.php
  ├── info (title, version, description)
  ├── servers (URLs per environment)
  ├── security schemes (Bearer, ApiKey)
  └── tags (grouped by module)

app/Http/Controllers/OpenApiController
  ├── swagger() — serves Swagger UI HTML
  └── spec() — serves JSON spec

Annotations on each controller method:
  @OA\Get, @OA\Post, @OA\Put, @OA\Delete
  @OA\Response, @OA\RequestBody, @OA\Schema
```

#### 2.7.3 Generation

- `php artisan l5-swagger:generate` — generates spec from annotations
- Spec cached in production (no runtime generation)
- Swagger UI served at `/api/docs`
- Raw spec at `/api/openapi.json`

### 2.8 Performance Optimization

#### 2.8.1 Index Strategy

**Audit missing indexes:**

| Table | Missing Index | Query Pattern |
|-------|--------------|---------------|
| audit_logs | `[tenant_id, created_at]` | List logs by tenant, sorted by date |
| audit_logs | `[entity_type, entity_id]` | Find logs for specific entity |
| sales | `[tenant_id, store_id, status]` | Filter sales by store and status |
| products | `[tenant_id, is_active, category_id]` | Filter products by category |
| inventory_movements | `[tenant_id, product_id, created_at]` | Movement history per product |
| webhook_deliveries | `[tenant_id, status, created_at]` | Failed deliveries list |
| integration_api_keys | `[tenant_id, is_active]` | List active keys |

**Migration approach:**
- Single migration file with all index additions
- Use regular migrations (brief lock acceptable in maintenance window)
- Document each index with the query it optimizes

#### 2.8.2 Eager Loading Audit

**Models with known N+1 risk:**

| Model | Relationship | Fix |
|-------|-------------|-----|
| Sale | items.product, items.variant, payments, customer, store, cashier | Add `$with` or `::with()` in list query |
| Purchase | items.product, supplier, store | Add eager loading in controller |
| Product | category, variants, barcodes, images | Add `$with` for list endpoint |
| Customer | sales, loyaltyTransactions | Conditional eager loading |
| AuditLog | user | Already has `with('user:id,name,email')` |

#### 2.8.3 Cache Strategy

```
Cache Keys:
  ├── tenant:{id}:modules — TTL: 1 hour, invalidated on module change
  ├── tenant:{id}:features — TTL: 1 hour, invalidated on feature change
  ├── tenant:{id}:permissions:{user_id} — TTL: 30 min, invalidated on role change
  ├── tenant:{id}:category_tree — TTL: 1 hour, invalidated on category CRUD
  └── tenant:{id}:business_profile — TTL: 1 hour, invalidated on profile update
```

**Implementation:**
- Use `Cache::remember()` with tags for bulk invalidation
- `Cache::tags(['tenant:{id}'])->flush()` on relevant model events
- Redis as cache backend in production
- Array cache in testing (existing)

### 2.9 CI/CD Architecture

#### 2.9.1 GitHub Actions Workflow

```
.github/workflows/
  ├── ci.yml — Continuous Integration (all branches)
  └── cd.yml — Continuous Deployment (main branch only)
```

#### 2.9.2 CI Pipeline (ci.yml)

```yaml
name: CI
on: [push, pull_request]
jobs:
  backend:
    runs-on: ubuntu-latest
    services:
      mysql: ...
    steps:
      - checkout
      - setup-php 8.3
      - composer install
      - cp .env.testing .env
      - php artisan key:generate
      - php artisan migrate --force
      - ./vendor/bin/pint --test
      - ./vendor/bin/phpstan analyse
      - php artisan test
  frontend:
    runs-on: ubuntu-latest
    steps:
      - checkout
      - setup-node 20
      - npm ci
      - npm run build
      - npx tsc --noEmit
```

#### 2.9.3 CD Pipeline (cd.yml)

```yaml
name: CD
on:
  push:
    branches: [main]
jobs:
  deploy-staging:
    needs: [ci]
    runs-on: ubuntu-latest
    steps:
      - build docker images
      - push to registry
      - ssh to staging
      - docker compose pull
      - docker compose up -d
      - php artisan migrate --force
      - run smoke tests
  deploy-production:
    needs: [deploy-staging]
    environment: production  # requires manual approval
    runs-on: ubuntu-latest
    steps:
      - ssh to production
      - docker compose pull
      - docker compose up -d
      - php artisan migrate --force
      - run smoke tests
```

### 2.10 Docker Production Architecture

#### 2.10.1 Production Dockerfile (Backend)

```dockerfile
# Multi-stage build
FROM php:8.3-fpm-alpine AS base
# Install extensions, composer

FROM base AS dependencies
COPY composer.* ./
RUN composer install --no-dev --optimize-autoloader

FROM base AS production
COPY --from=dependencies /var/www/vendor ./vendor
COPY . .
RUN php artisan config:cache && php artisan route:cache
USER nobody  # Non-root
EXPOSE 9000
```

#### 2.10.2 Production Docker Compose

```yaml
services:
  backend:
    image: ghcr.io/org/pos-saas-backend:latest
    environment:
      - APP_ENV=production
      - REDIS_HOST=redis
    depends_on: [mysql, redis]
  
  frontend:
    image: ghcr.io/org/pos-saas-frontend:latest
    build:
      args:
        - VITE_API_URL=https://api.example.com
  
  mysql:
    image: mysql:8.0
    volumes:
      - mysql_data:/var/lib/mysql
  
  redis:
    image: redis:7-alpine
    volumes:
      - redis_data:/data
  
  caddy:
    image: caddy:2-alpine
    ports: ["80:80", "443:443"]
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile
      - caddy_data:/data
```

### 2.11 PDP Compliance Architecture

#### 2.11.1 Data Inventory

| Data Type | Stored In | PII Level | Retention |
|-----------|----------|-----------|-----------|
| User name, email | users table | High | While account active + 30 days |
| User password (hashed) | users table | High | While account active |
| Customer name, phone, email | customers table | High | Configurable (default: while tenant active) |
| Sale records | sales, sale_items | Medium | 7 years (tax requirement) |
| Payment records | payments | Medium | 7 years (tax requirement) |
| Audit logs | audit_logs | Low | 90 days |
 IP addresses | audit_logs | Medium | 90 days |
| Integration credentials | tenant_integrations (encrypted) | High | While integration active |
| API keys | integration_api_keys (hashed) | High | While key active |

#### 2.11.2 Right to Erasure Flow

```
DELETE /api/v1/account
  → Verify user is owner (or self)
  → Soft-delete user account
  → Anonymize PII fields (name → "Deleted User", email → "deleted_{id}@local")
  → Schedule hard delete after 30 days
  → Log to audit
  → Return 202 Accepted

Scheduled command (daily):
  → Find users soft-deleted > 30 days ago
  → Hard delete user records
  → Anonymize related customer records if requested
  → Log cleanup to audit
```

#### 2.11.3 Data Export Flow

```
GET /api/v1/account/export
  → Verify user is owner (or self)
  → Collect: user profile, sales, payments, audit logs
  → Generate JSON file
  → Return as download (Content-Disposition: attachment)
  → Log export to audit
```

---

## 3. SEQUENCE DIAGRAMS

### 3.1 Login with 2FA

```
Client          Server          Database
  │               │               │
  │ POST /login   │               │
  │ (email, pass) │               │
  │──────────────>│               │
  │               │ Check lockout │
  │               │──────────────>│
  │               │<──────────────│
  │               │ Verify creds  │
  │               │──────────────>│
  │               │<──────────────│
  │               │               │
  │               │ 2FA enabled?  │
  │               │──────────────>│
  │               │<──────────────│
  │               │               │
  │ 200 {2fa_required, temp_token}│
  │<──────────────│               │
  │               │               │
  │ POST /login-2fa               │
  │ (temp_token, code)            │
  │──────────────>│               │
  │               │ Verify TOTP   │
  │               │──────────────>│
  │               │<──────────────│
  │               │ Issue token   │
  │               │──────────────>│
  │               │<──────────────│
  │ 200 {user, token}             │
  │<──────────────│               │
```

### 3.2 Audit Observer Flow

```
Controller      Model           Observer        AuditService    Database
  │               │               │               │               │
  │ $sale->save() │               │               │               │
  │──────────────>│ created event │               │               │
  │               │──────────────>│               │               │
  │               │               │ logModelEvent │               │
  │               │               │──────────────>│               │
  │               │               │               │ INSERT        │
  │               │               │               │──────────────>│
  │               │               │               │<──────────────│
  │               │               │               │               │
  │ 200 response  │               │               │               │
  │<──────────────│               │               │               │
```

### 3.3 CI/CD Flow

```
Developer       GitHub          CI Runner       Staging         Production
  │               │               │               │               │
  │ git push      │               │               │               │
  │──────────────>│               │               │               │
  │               │ trigger CI    │               │               │
  │               │──────────────>│               │               │
  │               │               │ lint+test     │               │
  │               │               │ build         │               │
  │               │<──────────────│               │               │
  │               │               │               │               │
  │               │ (if main)     │               │               │
  │               │ trigger CD    │               │               │
  │               │──────────────>│               │               │
  │               │               │ build images  │               │
  │               │               │ push to registry              │
  │               │               │──────────────>│               │
  │               │               │               │ pull + deploy │
  │               │               │               │ smoke tests   │
  │               │               │               │               │
  │               │               │ (manual approval)             │
  │               │               │───────────────────────────────>│
  │               │               │               │               │ deploy
  │               │               │               │               │ smoke tests
```

---

## 4. CONFIGURATION ARCHITECTURE

### 4.1 New Config Files

#### `config/security.php`

```php
return [
    'password' => [
        'min_length' => env('PASSWORD_MIN_LENGTH', 12),
        'require_mixed_case' => true,
        'require_numbers' => true,
        'require_symbols' => true,
        'history_count' => 5,
    ],
    'lockout' => [
        'thresholds' => [5, 10, 15],
        'durations' => [900, 3600, 86400], // seconds
    ],
    'rate_limit' => [
        'tenant' => env('RATE_LIMIT_TENANT_PER_MIN', 1000),
        'user' => env('RATE_LIMIT_USER_PER_MIN', 200),
        'write' => env('RATE_LIMIT_WRITE_PER_MIN', 60),
        'read' => env('RATE_LIMIT_READ_PER_MIN', 300),
    ],
    'xss' => [
        'skip_fields' => ['description', 'notes', 'receipt_footer'],
    ],
    'two_factor' => [
        'issuer' => env('TWO_FA_ISSUER', 'POS-SaaS'),
        'window' => env('TWO_FA_WINDOW', 1),
        'backup_codes_count' => 10,
    ],
];
```

#### `config/audit.php`

```php
return [
    'observed_models' => [
        \App\Models\Product::class,
        \App\Models\Category::class,
        \App\Models\Sale::class,
        \App\Models\Payment::class,
        \App\Models\Purchase::class,
        \App\Models\Inventory::class,
        \App\Models\Customer::class,
        \App\Models\Supplier::class,
        \App\Models\TenantIntegration::class,
        \App\Models\WebhookEndpoint::class,
        \App\Models\IntegrationApiKey::class,
    ],
    'retention_days' => env('AUDIT_RETENTION_DAYS', 90),
    'async' => env('AUDIT_ASYNC', false),
    'queue' => 'audit',
];
```

#### `config/backup.php`

```php
return [
    'disk' => env('BACKUP_DISK', 'local'),
    'destination' => 'backups',
    'retention' => [
        'daily' => 7,
        'weekly' => 4,
        'monthly' => 3,
    ],
    'notification_email' => env('BACKUP_NOTIFICATION_EMAIL'),
];
```

---

## 5. DEPENDENCY ADDITIONS

### 5.1 Composer Packages

| Package | Purpose | Version |
|---------|---------|---------|
| `sentry/sentry-laravel` | Error tracking | ^4.0 |
| `pragmarx/google2fa` | TOTP 2FA | ^8.0 |
| `bacon/bacon-qr-code` | QR code generation | ^3.0 |
| `darkaonline/l5-swagger` | OpenAPI/Swagger UI | ^9.0 |
| `zircote/swagger-php` | OpenAPI annotations | ^4.0 |
| `larastan/phpstan-neon` | Static analysis | ^3.0 (dev) |

### 5.2 npm Packages

| Package | Purpose | Version |
|---------|---------|---------|
| `qrcode.react` | QR code display in 2FA setup | ^4.0 |

### 5.3 Infrastructure

| Component | Purpose |
|-----------|---------|
| Redis 7 | Session, cache, queue backend |
| Caddy 2 | Reverse proxy + auto TLS |
| GitHub Container Registry | Docker image hosting |

---

## 6. MIGRATION STRATEGY

### 6.1 Database Migrations

**Single migration file:** `2026_08_18_000001_phase10_security_and_monitoring.php`

Contents:
1. Create `two_factor_auth` table
2. Create `password_histories` table
3. Create `account_lockouts` table
4. Create `data_retention_policies` table
5. Add columns to `users` table
6. Add columns + indexes to `audit_logs` table
7. Add performance indexes to existing tables

### 6.2 Seeder Updates

- `ModuleSeeder`: Add `security` module (core, always enabled)
- `FeatureSeeder`: Add `2fa` feature (toggleable, under security module)
- `PermissionSeeder`: Add new permissions:
  - `security.2fa.manage` — Manage own 2FA
  - `security.audit.view` — View audit logs
  - `security.account.unlock` — Unlock user accounts (owner)
  - `security.2fa.reset` — Reset user 2FA (owner)
  - `security.account.export` — Export account data
  - `security.account.delete` — Delete account (owner)

---

## 7. TESTING ARCHITECTURE

### 7.1 Test Categories

| Category | Tests | Count (est.) |
|----------|-------|-------------|
| Password Policy | Registration, change, history, complexity | 8 |
| Account Lockout | Progressive lockout, reset, bypass in testing | 6 |
| 2FA | Enable, disable, verify, backup codes, login flow | 12 |
| XSS Sanitization | Script stripping, skip fields, valid data | 6 |
| CORS | Preflight, allowed origins, credentials | 4 |
| Rate Limiting | Per-tenant, per-user, per-endpoint, headers | 8 |
| Audit Observers | Create, update, delete on observed models | 10 |
| Sentry | Initialization, context, graceful degradation | 3 |
| Health Check | All dependencies, degraded state, timeout | 4 |
| Backup | Create, retention, restore | 5 |
| OpenAPI | Spec generation, Swagger UI, schemas | 4 |
| Performance | N+1 detection, cache hit/miss | 6 |
| PDP Compliance | Data export, erasure, consent log | 6 |
| E2E | Full security flow, 2FA login, audit trail | 8 |
| **Total new** | | **~90** |

### 7.2 Regression

- All 1197 existing tests must pass
- No existing test should need modification (except rate limiting bypass in testing — already handled)
- New tests are additive

---

*End of Phase 10 Architecture*
