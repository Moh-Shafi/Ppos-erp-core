# Phase 0 — ERP Foundation & Architecture — SECURITY

**Phase:** 0  
**Status:** IN PROGRESS — Documentation Phase  
**Created:** 2026-08-11  
**Depends On:** `docs/phases/phase-00/PDR.md`, `docs/phases/phase-00/ARCHITECTURE.md`

---

## 1. SECURITY MODEL OVERVIEW

```
┌─────────────────────────────────────────────────────┐
│                   SECURITY LAYERS                     │
│                                                     │
│  1. Authentication (Sanctum tokens)                 │
│  2. Module Access Control (CheckModule middleware)  │
│  3. Permission Access Control (CheckPermission)     │
│  4. Feature Access Control (CheckFeature)           │
│  5. Tenant Isolation (BelongsToTenant global scope) │
│  6. Store Scoping (X-Store-Id header)               │
│  7. Input Validation (Laravel validators)           │
│  8. Rate Limiting (Throttle middleware)             │
│  9. Audit Logging (AuditService)                    │
│ 10. IDOR Protection (tenant_id ownership checks)    │
└─────────────────────────────────────────────────────┘
```

**Core Principle:** Frontend security NEVER replaces backend security. Both are required. Frontend hides/disables UI for UX; backend enforces for security.

---

## 2. AUTHENTICATION

### 2.1 Current (Preserved)

- Laravel Sanctum token-based authentication
- Token stored in `personal_access_tokens` table
- Token passed via `Authorization: Bearer {token}` header
- Token stored in `localStorage` on frontend

### 2.2 Enhancements (Phase 0)

| Enhancement | Description |
|------------|-------------|
| Rate limiting on login | 5 attempts per minute per IP |
| Rate limiting on register | 5 attempts per minute per IP |
| Login response includes module/feature/permission config | Reduces additional API call |
| Token abilities (future) | Tokens can be scoped to specific modules |

### 2.3 Token Security

- Tokens are plain-text only at creation, stored hashed in DB
- No token expiration (configurable in future per plan)
- Logout revokes current token
- 401 response → frontend clears token + redirects to login

---

## 3. AUTHORIZATION (RBAC 2.0)

### 3.1 Permission Resolution Chain

```
Request → auth:sanctum → module:check → permission:check → feature:check → execute
              │              │                │                  │
              ↓              ↓                ↓                  ↓
           401 if        403 if          403 if            403 if
          no token     module off      no perm          feature off
```

### 3.2 Module Access

- **Backend:** `CheckModule` middleware verifies `tenant_modules.is_enabled = true`
- **Frontend:** `hasModule(slug)` in Zustand store → hides nav items, blocks routes
- **Bypass protection:** Even if frontend is manipulated, backend rejects with 403

### 3.3 Permission Access

- **Backend:** `CheckPermission` middleware verifies user's role has the required permission
- **Frontend:** `hasPermission(slug)` in Zustand store → hides buttons, disables actions
- **Multi-role:** Users can have multiple roles per store via `user_roles` table

### 3.4 Feature Access

- **Backend:** `CheckFeature` middleware verifies `tenant_features.is_enabled = true`
- **Frontend:** `hasFeature(slug)` in Zustand store → hides feature-specific UI

### 3.5 System Roles

| Role | Can access | Cannot access |
|------|-----------|---------------|
| Owner | Everything | Nothing restricted |
| Manager | All operational modules | users.manage, settings.manage |
| Cashier | POS, sales, customers | Inventory manage, suppliers, purchases, settings |
| Staff | View products, categories, inventory, customers | POS, sales manage, any manage operation |
| Accountant | Finance, reports | POS, sales, inventory operations |

### 3.6 Custom Roles

- Owner can create custom roles with specific permission sets
- Custom roles are tenant-scoped (`roles.tenant_id` is set)
- System roles (`is_system = true`) cannot be deleted or modified
- Custom roles can be assigned per store via `user_roles`

---

## 4. TENANT ISOLATION

### 4.1 Current (Preserved)

- `BelongsToTenant` trait with Eloquent global scope
- Auto-filters all queries by `Auth::user()->tenant_id`
- `tenant_id` auto-set on model creation via `creating` event
- `withoutTenantScope()` for system-level operations only
- `tenant_id` NEVER in `$fillable`, NEVER from request

### 4.2 New Tenant-Scoped Tables

All new tenant-scoped tables use `BelongsToTenant`:
- `business_profiles`
- `tenant_modules`
- `tenant_features`
- `user_roles`
- `audit_logs`

### 4.3 Cross-Tenant Protection

| Scenario | Protection |
|----------|------------|
| User tries to access another tenant's data | Global scope filters by tenant_id → 404 |
| User tries to create resource for another tenant | `creating` event sets tenant_id from Auth → ignored request value |
| User tries to enable module for another tenant | `tenant_modules.tenant_id` from Auth → 403 |
| Admin endpoint without tenant scope | Explicit `withoutTenantScope()` + system-level check |

---

## 5. IDOR PROTECTION

### 5.1 Existing Pattern (Preserved)

All controllers validate resource ownership:
```php
$store = Store::where('tenant_id', Auth::user()->tenant_id)->findOrFail($id);
$product = Product::where('tenant_id', Auth::user()->tenant_id)->findOrFail($id);
```

### 5.2 New Endpoints

| Endpoint | IDOR Protection |
|----------|----------------|
| GET/PUT /tenant/modules/{module_id} | `tenant_modules.tenant_id` from Auth |
| GET/PUT /tenant/features/{feature_id} | `tenant_features.tenant_id` from Auth |
| GET/PUT /tenant/business-profile | `business_profiles.tenant_id` from Auth |
| GET /audit-logs | `audit_logs.tenant_id` from Auth + Owner permission |
| GET /dashboard | All queries scoped by tenant_id via global scope |

---

## 6. STORE SCOPING

### 6.1 X-Store-Id Header

- Frontend sends `X-Store-Id: {store_id}` header on store-scoped requests
- Backend `store.scope` middleware validates:
  1. Store exists
  2. Store belongs to tenant (tenant_id check)
  3. User has access to this store (via `user_roles.store_id` or tenant-wide role)
- If invalid → 403

### 6.2 Store Access Validation

```
User has role assignment:
  ├── user_roles.store_id = NULL → tenant-wide access (all stores)
  └── user_roles.store_id = {id} → store-specific access only

If X-Store-Id is set:
  ├── Check store belongs to tenant
  ├── Check user has access to this store
  └── Apply store_id filter to queries
```

---

## 7. INPUT VALIDATION

### 7.1 Registration

| Field | Rules |
|-------|-------|
| name | required, string, max:255 |
| email | required, email, unique:users |
| password | required, string, min:8, confirmed |
| store_name | required, string, max:255 |
| business_type_id | nullable, integer, exists:business_types,id |

### 7.2 Module Toggle

| Field | Rules |
|-------|-------|
| is_enabled | required, boolean |

### 7.3 Feature Toggle

| Field | Rules |
|-------|-------|
| is_enabled | required, boolean |

### 7.4 Business Profile Update

| Field | Rules |
|-------|-------|
| business_name | required, string, max:255 |
| tax_id | nullable, string, max:50 |
| address | nullable, string, max:500 |
| city | nullable, string, max:100 |
| province | nullable, string, max:100 |
| postal_code | nullable, string, max:20 |
| phone | nullable, string, max:50 |
| email | nullable, email, max:255 |

---

## 8. RATE LIMITING

### 8.1 Configuration

```php
// Rate limits per endpoint group
'auth' => ['limit' => 5, 'window' => 1],       // 5 per minute
'api' => ['limit' => 60, 'window' => 1],       // 60 per minute
'dashboard' => ['limit' => 30, 'window' => 1], // 30 per minute
'audit' => ['limit' => 10, 'window' => 1],     // 10 per minute
```

### 8.2 Implementation

```php
// routes/api.php
Route::middleware(['throttle:5,1'])->group(function () {
    Route::post('auth/login', ...);
    Route::post('auth/register', ...);
});

Route::middleware(['throttle:60,1'])->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        // All authenticated routes
    });
});
```

### 8.3 Response

HTTP 429 with `Retry-After` header:
```json
{
  "message": "Too many requests. Try again in 60 seconds.",
  "retry_after": 60
}
```

---

## 9. AUDIT LOGGING

### 9.1 What Is Logged

| Action | Entities | Trigger |
|--------|----------|---------|
| login | User | AuthController::login |
| logout | User | AuthController::logout |
| register | Tenant, User, Store | RegistrationService::register |
| create | Sale, Purchase, Product, Customer, ... | Model observer / service |
| update | Sale, Purchase, Product, Customer, ... | Model observer / service |
| delete | Product, Customer, Supplier, ... | Model observer / service |
| module.enable | TenantModule | ModuleService::enableModule |
| module.disable | TenantModule | ModuleService::disableModule |
| feature.enable | TenantFeature | ModuleService::enableFeature |
| feature.disable | TenantFeature | ModuleService::disableFeature |

### 9.2 What Is Stored

| Field | Value |
|-------|-------|
| tenant_id | From Auth |
| user_id | From Auth (null if not authenticated) |
| action | 'create', 'update', 'delete', 'login', 'logout', 'module.enable', etc. |
| entity_type | Model class name (e.g., 'Sale') |
| entity_id | Resource ID |
| old_values | JSON of previous values (for update/delete) |
| new_values | JSON of new values (for create/update) |
| ip_address | From request |
| user_agent | From request |

### 9.3 Access Control

- Only Owner can view audit logs (`audit.view` permission)
- Audit logs are tenant-scoped (cannot see other tenants' logs)
- Audit logs are immutable (no update/delete — append-only)

---

## 10. SENSITIVE DATA PROTECTION

### 10.1 Secrets

| Secret | Location | Protection |
|--------|----------|------------|
| DB password | `.env` | Never in code, never in git |
| Sanctum key | `.env` | Never in code, never in git |
| Xendit API keys (future) | `.env` | Never in code, never in git |
| Auth tokens | `personal_access_tokens` (hashed) | Hashed in DB |
| Passwords | `users.password` (hashed) | Bcrypt hash |

### 10.2 Data Exposure Prevention

- Passwords never returned in API responses (hidden in User model)
- `tenant_id` never accepted from request (auto-set from Auth)
- `cashier_id` never accepted from request (auto-set from Auth)
- `user_id` in audit logs never from request (auto-set from Auth)
- Financial totals always backend-calculated (never from request)

---

## 11. WEBHOOK SECURITY (Foundation for Phase 5)

### 11.1 Design (Not Implemented in Phase 0)

- Webhook endpoint: `POST /api/v1/webhooks/xendit`
- Signature verification: X-Hub-Signature header
- Idempotency: `payment_webhooks` table with unique `webhook_id`
- Replay prevention: Reject webhooks older than 5 minutes
- IP allowlist: Xendit IP ranges (configurable)

### 11.2 Phase 0 Preparation

- `audit_logs` table provides foundation for tracking webhook events
- Idempotency pattern from `payments.idempotency_key` serves as reference
- Webhook security documented for Phase 5 implementation

---

## 12. FINANCIAL TRANSACTION INTEGRITY

### 12.1 Existing Patterns (Preserved)

- `DB::transaction()` wraps all multi-step operations
- `lockForUpdate()` on inventory rows during stock changes
- Ordered locking (min → max store_id) for transfers
- Payment idempotency keys with unique constraints
- Atomic checkout: Sale + Items + Inventory + Movements + Payments

### 12.2 Phase 0 Foundation

- Audit log records all financial operations
- Module enable/disable is logged (cannot secretly enable finance module)
- Rate limiting prevents abuse of financial endpoints
- Store scoping ensures financial data isolation per branch

---

## 13. API SECURITY

### 13.1 CORS

```php
// config/cors.php
'paths' => ['api/*'],
'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
'allowed_origins' => [
    'http://localhost:5173',
    'http://frontend:5173',
    env('FRONTEND_URL', 'http://localhost:5173'),
],
'allowed_headers' => ['*'],
'exposed_headers' => [],
'max_age' => 0,
'supports_credentials' => true,
```

### 13.2 HTTPS

- Production: HTTPS only (enforced by reverse proxy / load balancer)
- Development: HTTP (Docker local)
- HSTS header in production (future, Phase 10)

### 13.3 Input Sanitization

- Laravel validators strip unexpected fields
- `$fillable` on models prevents mass assignment vulnerabilities
- JSON responses use `json_encode` (no XSS in JSON context)
- No raw SQL queries (all via Eloquent/query builder)

---

## 14. SECURITY TEST MATRIX

| Test | Description | Type |
|------|-------------|------|
| Unauthenticated access | All protected endpoints return 401 without token | API |
| Cross-tenant access | User A cannot access User B's tenant data | API |
| Module disabled access | API returns 403 when module is disabled | API |
| Feature disabled access | API returns 403 when feature is disabled | API |
| Permission denied | API returns 403 when user lacks permission | API |
| IDOR protection | User cannot access resource by ID from another tenant | API |
| Store scoping | User cannot access data from store they don't have access to | API |
| Rate limit login | 6th login attempt in 1 minute returns 429 | API |
| Rate limit API | 61st request in 1 minute returns 429 | API |
| Audit log immutability | Cannot update or delete audit log entries | API |
| Frontend RBAC | Staff user does not see POS link in sidebar | E2E |
| Frontend RBAC | Cashier cannot access settings page | E2E |
| Frontend RBAC | Module-disabled nav item not shown | E2E |
| Token theft scenario | 401 on invalid token, frontend redirects to login | E2E |

---

*End of Phase 0 Security*
