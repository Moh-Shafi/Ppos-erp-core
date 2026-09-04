# Phase 0 — ERP Foundation & Architecture — ARCHITECTURE

**Phase:** 0  
**Status:** IN PROGRESS — Documentation Phase  
**Created:** 2026-08-11  
**Depends On:** `docs/phases/phase-00/PDR.md`, `docs/PDR/01-ERP_ARCHITECTURE.md`

---

## 1. CURRENT SYSTEM FREEZE

### 1.1 Frozen State

| Artifact | State | Reference |
|----------|-------|-----------|
| Git commit | `8483f83` | Phase 5.6: Payment Integration |
| Backend tests | 736 tests / 1,844 assertions | All PASS |
| Frontend E2E | 18 tests | All PASS |
| Database | 23 migrations | MySQL 8.0, `pos_saas` |
| Test database | Same migrations | MySQL, `pos_saas_testing` |

### 1.2 Migration Strategy

**Principle:** Additive-only. No existing table is dropped. No existing column is removed.

| Change Type | Allowed? | Example |
|------------|----------|---------|
| New table | Yes | `business_types`, `modules`, `features` |
| New column (nullable) | Yes | `roles.tenant_id` (nullable) |
| New column (with default) | Yes | `permissions.module_id` (nullable, default null) |
| New index | Yes | Index on `roles.tenant_id` |
| New relationship | Yes | `user_roles` table alongside existing `users.role_id` |
| Drop table | NO | — |
| Drop column | NO | — |
| Change column type | NO | — |
| Rename column | NO | — |

### 1.3 Backward Compatibility

- `users.role_id` remains functional — existing code using `$user->role_id` continues to work.
- `user_roles` table is additive — enables multi-role per store in future.
- `CheckPermission` middleware remains — `CheckModule` and `CheckFeature` are added alongside.
- Existing routes without module middleware continue to work (no module check = always pass).
- New routes use `module:` middleware alias.

---

## 2. ERP CORE ARCHITECTURE

### 2.1 Hierarchy

```
Platform
   │
   └── Tenant / Business
          │
          ├── Business Type (template)
          │
          ├── Business Profile (1:1 with tenant)
          │
          ├── Users
          │      └── Roles (tenant-scoped or system)
          │             └── Permissions (module-scoped)
          │
          ├── Stores / Branches
          │
          ├── Enabled Modules
          │      └── Features (per module)
          │
          └── Financial Account (future — Phase 6)
```

### 2.2 Platform-Level Entities (System, not tenant-scoped)

| Entity | Purpose |
|--------|---------|
| `modules` | Registry of all available modules in the system |
| `features` | Registry of all available features per module |
| `business_types` | Predefined business type templates |
| `business_type_modules` | Default module config per business type |
| `business_type_features` | Default feature config per business type |
| `plans` | Subscription plans (existing) |
| `permissions` | System permissions (modified: add `module_id`) |

### 2.3 Tenant-Level Entities (Tenant-scoped)

| Entity | Purpose |
|--------|---------|
| `tenants` | Tenant organization (existing) |
| `business_profiles` | Business details (1:1 with tenant, new) |
| `tenant_modules` | Enabled modules per tenant (new) |
| `tenant_features` | Enabled features per tenant (new) |
| `stores` | Store/branch locations (existing, extended) |
| `users` | Users (existing, extended) |
| `roles` | Roles (existing, extended with `tenant_id`) |
| `role_permissions` | Role-permission mapping (existing) |
| `user_roles` | Multi-role per user per store (new, additive) |
| `subscriptions` | Subscription lifecycle (existing) |

---

## 3. DATABASE DESIGN

### 3.1 New Tables

#### modules (system-level)

```sql
CREATE TABLE modules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    is_core BOOLEAN NOT NULL DEFAULT FALSE,
    dependencies JSON NULL,          -- ['core', 'inventory']
    sort_order INT NOT NULL DEFAULT 0,
    icon VARCHAR(50) NULL,           -- UI icon identifier
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

#### features (system-level)

```sql
CREATE TABLE features (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id BIGINT UNSIGNED NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,  -- 'pos.split_payment'
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    is_default_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    is_owner_toggleable BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
);
```

#### business_types (system-level)

```sql
CREATE TABLE business_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    icon VARCHAR(50) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

#### business_type_modules (system-level)

```sql
CREATE TABLE business_type_modules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_type_id BIGINT UNSIGNED NOT NULL,
    module_id BIGINT UNSIGNED NOT NULL,
    is_default_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY (business_type_id, module_id),
    FOREIGN KEY (business_type_id) REFERENCES business_types(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
);
```

#### business_type_features (system-level)

```sql
CREATE TABLE business_type_features (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_type_id BIGINT UNSIGNED NOT NULL,
    feature_id BIGINT UNSIGNED NOT NULL,
    is_default_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY (business_type_id, feature_id),
    FOREIGN KEY (business_type_id) REFERENCES business_types(id) ON DELETE CASCADE,
    FOREIGN KEY (feature_id) REFERENCES features(id) ON DELETE CASCADE
);
```

#### business_profiles (tenant-scoped, 1:1 with tenant)

```sql
CREATE TABLE business_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL UNIQUE,
    business_type_id BIGINT UNSIGNED NOT NULL,
    business_name VARCHAR(255) NOT NULL,
    tax_id VARCHAR(50) NULL,           -- NPWP (Indonesia)
    address TEXT NULL,
    city VARCHAR(100) NULL,
    province VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    phone VARCHAR(50) NULL,
    email VARCHAR(255) NULL,
    logo VARCHAR(255) NULL,
    timezone VARCHAR(50) NOT NULL DEFAULT 'Asia/Jakarta',
    currency VARCHAR(3) NOT NULL DEFAULT 'IDR',
    locale VARCHAR(10) NOT NULL DEFAULT 'id',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (business_type_id) REFERENCES business_types(id)
);
```

#### tenant_modules (tenant-scoped)

```sql
CREATE TABLE tenant_modules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    module_id BIGINT UNSIGNED NOT NULL,
    is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    enabled_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY (tenant_id, module_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
);
```

#### tenant_features (tenant-scoped)

```sql
CREATE TABLE tenant_features (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    feature_id BIGINT UNSIGNED NOT NULL,
    is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY (tenant_id, feature_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (feature_id) REFERENCES features(id) ON DELETE CASCADE
);
```

#### user_roles (tenant-scoped, multi-role per store)

```sql
CREATE TABLE user_roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    store_id BIGINT UNSIGNED NULL,    -- NULL = tenant-wide role
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY (user_id, role_id, store_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
);
```

#### audit_logs (tenant-scoped)

```sql
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(50) NOT NULL,      -- 'create', 'update', 'delete', 'login', 'logout'
    entity_type VARCHAR(100) NOT NULL, -- 'Sale', 'Product', 'User', ...
    entity_id BIGINT UNSIGNED NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX (tenant_id, entity_type, entity_id),
    INDEX (tenant_id, user_id),
    INDEX (tenant_id, created_at)
);
```

### 3.2 Modified Tables

#### roles (add tenant_id for custom roles)

```sql
ALTER TABLE roles
    ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id,
    ADD COLUMN is_system BOOLEAN NOT NULL DEFAULT TRUE AFTER slug,
    ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER is_system,
    ADD INDEX (tenant_id),
    ADD FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;
```

#### permissions (add module_id for module-scoped permissions)

```sql
ALTER TABLE permissions
    ADD COLUMN module_id BIGINT UNSIGNED NULL AFTER id,
    ADD INDEX (module_id),
    ADD FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE SET NULL;
```

#### stores (extend with settings + headquarters)

```sql
ALTER TABLE stores
    ADD COLUMN city VARCHAR(100) NULL AFTER address,
    ADD COLUMN province VARCHAR(100) NULL AFTER city,
    ADD COLUMN postal_code VARCHAR(20) NULL AFTER province,
    ADD COLUMN email VARCHAR(255) NULL AFTER phone,
    ADD COLUMN is_headquarters BOOLEAN NOT NULL DEFAULT FALSE AFTER is_active,
    ADD COLUMN settings JSON NULL AFTER is_headquarters;
```

### 3.3 Migration Order

```
1. Create modules table
2. Create features table (depends on modules)
3. Create business_types table
4. Create business_type_modules table (depends on business_types, modules)
5. Create business_type_features table (depends on business_types, features)
6. Create business_profiles table (depends on tenants, business_types)
7. Create tenant_modules table (depends on tenants, modules)
8. Create tenant_features table (depends on tenants, features)
9. Alter roles table (add tenant_id, is_system, sort_order)
10. Alter permissions table (add module_id)
11. Create user_roles table (depends on users, roles, stores)
12. Alter stores table (add city, province, postal_code, email, is_headquarters, settings)
13. Create audit_logs table (depends on tenants, users)
14. Run ModuleSeeder (populate modules + features)
15. Run BusinessTypeSeeder (populate business types + defaults)
16. Run RbacMigrationSeeder (assign module_id to existing permissions)
17. Run TenantMigrationSeeder (create business_profiles + tenant_modules for existing tenants)
```

---

## 4. MIDDLEWARE STACK

### 4.1 New Middleware

#### CheckModule

```php
// app/Http/Middleware/CheckModule.php
class CheckModule
{
    public function handle(Request $request, Closure $next, string $moduleSlug): mixed
    {
        $tenantId = Auth::user()->tenant_id;
        $module = Module::where('slug', $moduleSlug)->first();

        if (!$module) {
            return response()->json(['message' => 'Module not found'], 404);
        }

        $tenantModule = TenantModule::where('tenant_id', $tenantId)
            ->where('module_id', $module->id)
            ->where('is_enabled', true)
            ->first();

        if (!$tenantModule) {
            return response()->json(['message' => 'Module not enabled'], 403);
        }

        return $next($request);
    }
}
```

#### CheckFeature

```php
// app/Http/Middleware/CheckFeature.php
class CheckFeature
{
    public function handle(Request $request, Closure $next, string $featureSlug): mixed
    {
        $tenantId = Auth::user()->tenant_id;
        $feature = Feature::where('slug', $featureSlug)->first();

        if (!$feature) {
            return response()->json(['message' => 'Feature not found'], 404);
        }

        $tenantFeature = TenantFeature::where('tenant_id', $tenantId)
            ->where('feature_id', $feature->id)
            ->where('is_enabled', true)
            ->first();

        if (!$tenantFeature) {
            return response()->json(['message' => 'Feature not enabled'], 403);
        }

        return $next($request);
    }
}
```

### 4.2 Full Middleware Stack

```
Request
  │
  ├── 1. auth:sanctum          (existing — identify user)
  ├── 2. module:{slug}         (new — check tenant has module enabled)
  ├── 3. permission:{slug}     (existing — check user has permission)
  ├── 4. feature:{slug}        (new, optional — check feature enabled)
  ├── 5. tenant.scope          (existing — BelongsToTenant global scope)
  └── 6. store.scope           (new — filter by X-Store-Id header)
```

### 4.3 Route Registration

```php
// routes/api.php

// No module check (system routes)
Route::prefix('auth')->group(...)
Route::get('me', ...)

// Module-scoped routes
Route::middleware(['module:pos', 'permission:pos.use'])->prefix('pos')->group(...)
Route::middleware(['module:sales', 'permission:sales.view'])->prefix('sales')->group(...)
Route::middleware(['module:inventory', 'permission:inventory.view'])->prefix('inventory')->group(...)

// Existing routes (backward compatible — no module check, still work)
Route::get('stores', [StoreController::class, 'index']);
Route::get('categories', [CategoryController::class, 'index']);
```

---

## 5. MODELS

### 5.1 New Models

| Model | Table | Traits |
|-------|-------|--------|
| Module | modules | HasFactory |
| Feature | features | HasFactory |
| BusinessType | business_types | HasFactory |
| BusinessProfile | business_profiles | BelongsToTenant, HasFactory |
| TenantModule | tenant_modules | BelongsToTenant, HasFactory |
| TenantFeature | tenant_features | BelongsToTenant, HasFactory |
| UserRole | user_roles | BelongsToTenant, HasFactory |
| AuditLog | audit_logs | BelongsToTenant, HasFactory |

### 5.2 Modified Models

| Model | Changes |
|-------|---------|
| Role | Add `tenant_id` fillable, `is_system` fillable, relationship to `Tenant` |
| Permission | Add `module_id` fillable, relationship to `Module` |
| Store | Add new fillable fields (city, province, postal_code, email, is_headquarters, settings) |
| User | Add `userRoles()` relationship (hasMany), keep `role()` relationship (belongsTo) |
| Tenant | Add `businessProfile()`, `tenantModules()`, `tenantFeatures()` relationships |

---

## 6. SERVICE LAYER

### 6.1 New Services

#### ModuleService

```php
class ModuleService
{
    public function getEnabledModules(int $tenantId): Collection;
    public function enableModule(int $tenantId, string $moduleSlug): TenantModule;
    public function disableModule(int $tenantId, string $moduleSlug): void;
    public function isModuleEnabled(int $tenantId, string $moduleSlug): bool;
    public function getEnabledFeatures(int $tenantId): Collection;
    public function enableFeature(int $tenantId, string $featureSlug): TenantFeature;
    public function disableFeature(int $tenantId, string $featureSlug): void;
    public function isFeatureEnabled(int $tenantId, string $featureSlug): bool;
    public function applyBusinessTypeDefaults(int $tenantId, int $businessTypeId): void;
    public function validateDependencies(int $tenantId, string $moduleSlug): bool;
}
```

#### RegistrationService

```php
class RegistrationService
{
    public function register(array $data): User;
    // Creates: Tenant, BusinessProfile, User (Owner), Store,
    //           TenantModules (from business type defaults),
    //           TenantFeatures (from business type defaults)
}
```

#### AuditService

```php
class AuditService
{
    public function log(string $action, string $entityType, ?int $entityId, ?array $old = null, ?array $new = null): void;
    public function listLogs(int $tenantId, array $filters): LengthAwarePaginator;
}
```

### 6.2 Modified Services

| Service | Changes |
|---------|---------|
| AuthController::register() | Replace inline registration with `RegistrationService::register()` |
| CheckPermission middleware | No change — continues to work as-is |

---

## 7. SEEDERS

### 7.1 New Seeders

| Seeder | Purpose |
|--------|---------|
| ModuleSeeder | Populate `modules` + `features` tables with all registered modules |
| BusinessTypeSeeder | Populate `business_types` + `business_type_modules` + `business_type_features` |
| RbacMigrationSeeder | Assign `module_id` to existing permissions, create new module-scoped permissions |
| TenantMigrationSeeder | Create `business_profiles` + `tenant_modules` + `tenant_features` for existing tenants |

### 7.2 Updated Seeders

| Seeder | Changes |
|--------|---------|
| DatabaseSeeder | Add ModuleSeeder, BusinessTypeSeeder before RbacSeeder |
| RbacSeeder | Add `module_id` to permissions, add Accountant role |
| E2ESeeder | Add business_profile, tenant_modules, tenant_features for test tenant |

---

## 8. FRONTEND ARCHITECTURE

### 8.1 New Zustand Store: module-config

```typescript
interface ModuleConfigStore {
  enabledModules: string[]
  enabledFeatures: string[]
  userPermissions: string[]
  stores: Store[]
  currentStore: Store | null

  // Actions
  loadConfig: () => Promise<void>     // Called after login
  setStore: (store: Store) => void
  refreshConfig: () => Promise<void>

  // Selectors
  hasModule: (slug: string) => boolean
  hasFeature: (slug: string) => boolean
  hasPermission: (slug: string) => boolean
  can: (permission: string) => boolean  // module + feature + permission check
}
```

### 8.2 Enhanced ProtectedRoute

```typescript
// router/ProtectedRoute.tsx
interface ProtectedRouteProps {
  module?: string      // e.g., 'pos'
  permission?: string  // e.g., 'pos.use'
  children: ReactNode
}

export function ProtectedRoute({ module, permission, children }: ProtectedRouteProps) {
  const { isAuthenticated } = useAuthStore()
  const moduleConfig = useModuleConfigStore()

  if (!isAuthenticated) return <Navigate to="/login" replace />
  if (module && !moduleConfig.hasModule(module)) return <Navigate to="/dashboard" replace />
  if (permission && !moduleConfig.hasPermission(permission)) return <Navigate to="/dashboard" replace />

  return <>{children}</>
}
```

### 8.3 Module-Aware Navigation

```typescript
// layouts/DashboardLayout.tsx
const ALL_NAV_ITEMS = [
  { to: '/dashboard', label: 'Dashboard', module: 'core', permission: null },
  { to: '/pos', label: 'Kasir / POS', module: 'pos', permission: 'pos.use' },
  { to: '/sales', label: 'Riwayat Transaksi', module: 'sales', permission: 'sales.view' },
  { to: '/products', label: 'Produk', module: 'core', permission: 'products.view' },
  { to: '/categories', label: 'Kategori', module: 'core', permission: 'categories.view' },
  { to: '/inventory', label: 'Inventory', module: 'inventory', permission: 'inventory.view' },
  { to: '/customers', label: 'Customers', module: 'customers', permission: 'customers.view' },
  { to: '/suppliers', label: 'Suppliers', module: 'suppliers', permission: 'suppliers.view' },
  { to: '/purchases', label: 'Purchases', module: 'purchasing', permission: 'purchases.view' },
  { to: '/purchase-returns', label: 'Returns', module: 'purchasing', permission: 'purchases.view' },
  { to: '/settings/store', label: 'Pengaturan Toko', module: 'settings', permission: 'settings.manage' },
  { to: '/settings/account', label: 'Akun', module: 'core', permission: null },
  // Future modules:
  { to: '/finance', label: 'Finance', module: 'finance', permission: 'finance.view' },
  { to: '/reports', label: 'Reports', module: 'reports', permission: 'reports.view' },
  { to: '/tables', label: 'Tables', module: 'tables', permission: 'tables.view' },
  { to: '/kds', label: 'KDS', module: 'kds', permission: 'kds.view' },
  { to: '/users', label: 'User Management', module: 'users', permission: 'users.manage' },
]

const navItems = ALL_NAV_ITEMS.filter(item =>
  moduleConfig.hasModule(item.module) &&
  (!item.permission || moduleConfig.hasPermission(item.permission))
)
```

### 8.4 Store Switcher

```typescript
// components/common/StoreSwitcher.tsx
export function StoreSwitcher() {
  const { stores, currentStore, setStore } = useModuleConfigStore()

  if (stores.length <= 1) return null

  return (
    <Select
      value={currentStore?.id}
      onChange={(e) => setStore(stores.find(s => s.id === e.target.value))}
      options={stores.map(s => ({ value: s.id, label: s.name }))}
    />
  )
}
```

### 8.5 API Client Enhancement

```typescript
// lib/api.ts — add store context header
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token')
  if (token) config.headers.Authorization = `Bearer ${token}`

  const storeId = localStorage.getItem('current_store_id')
  if (storeId) config.headers['X-Store-Id'] = storeId

  return config
})
```

---

## 9. REGISTRATION FLOW REDESIGN

### 9.1 Registration (Single-Step, Backward Compatible)

```
User opens /register
  │
  ├── GET /api/v1/business-types → list of templates (for UI dropdown)
  │
  ├── User fills form: name, email, password, store_name, business_type_id
  │
  ├── POST /api/v1/auth/register
  │     ├── DB::transaction:
  │     │     ├── Create Tenant
  │     │     ├── Create BusinessProfile (tenant_id, business_type_id)
  │     │     ├── Create User (tenant_id, role_id = owner)
  │     │     ├── Create Store (default store from store_name)
  │     │     ├── Enable default modules (from business_type_modules)
  │     │     ├── Enable default features (from business_type_features)
  │     │     └── Create Sanctum token
  │     └── Return: { token, user, tenant, business_profile, modules, features, permissions, stores }
  │
  ├── Frontend saves config to stores
  │
  └── Redirect to /dashboard
```

**Note:** Multi-step registration (business type → store → module confirmation) is a **future UX enhancement**. For Phase 0, single-step registration is implemented. The Owner can adjust modules later via `GET /api/v1/tenant/modules` + `PUT /api/v1/tenant/modules/{module_id}`.

### 9.2 Backward Compatibility

Existing `POST /api/v1/auth/register` continues to work with optional `business_type_id` field:

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "store_name": "My Restaurant",
  "business_type_id": 1    // optional, defaults to 'general'
}
```

If `business_type_id` is provided, auto-enable default modules for that type.
If not provided, use 'general' template.

---

## 10. DASHBOARD REDESIGN

### 10.1 Real Stats

```php
// DashboardController
public function index()
{
    $tenantId = Auth::user()->tenant_id;
    $storeId = request()->header('X-Store-Id');

    return response()->json([
        'stats' => [
            'products' => Product::count(),
            'sales_today' => Sale::whereDate('sale_date', today())->count(),
            'sales_today_total' => Sale::whereDate('sale_date', today())->sum('total'),
            'low_stock_count' => Inventory::whereColumn('quantity', '<=', 'minimum_quantity')->count(),
            'customers' => Customer::count(),
            'suppliers' => Supplier::count(),
        ],
        'recent_sales' => Sale::with('items', 'payments')->latest()->limit(5)->get(),
        'low_stock_items' => Inventory::with('product', 'store')
            ->whereColumn('quantity', '<=', 'minimum_quantity')
            ->limit(5)->get(),
    ]);
}
```

### 10.2 Frontend Dashboard

- Stats cards: Products, Sales Today, Revenue Today, Low Stock Alerts
- Recent sales table (last 5)
- Low stock items list (top 5)
- Module-aware: only show widgets for enabled modules

---

## 11. RATE LIMITING

```php
// routes/api.php
Route::middleware(['throttle:5,1'])->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/register', [AuthController::class, 'register']);
});

Route::middleware(['throttle:60,1'])->group(function () {
    // All authenticated API routes
    Route::middleware('auth:sanctum')->group(function () {
        // ...
    });
});
```

---

## 12. PAYMENT GATEWAY INTERFACE (BOUNDARY CONTRACT ONLY)

### 12.1 Purpose

Define the payment gateway abstraction in Phase 0 as a **boundary contract** — no implementation. This ensures all future payment integrations (Xendit, Stripe, Midtrans, etc.) follow the same interface.

### 12.2 Interface Definition

```php
// app/Contracts/PaymentGatewayInterface.php

interface PaymentGatewayInterface
{
    /**
     * Create a payment charge via the gateway.
     *
     * @param array $paymentData {
     *     amount: float,
     *     currency: string,
     *     method: string,          // 'qris', 'card', 'bank_transfer', 'cash'
     *     reference: string,       // internal reference (sale_number, invoice_number)
     *     idempotency_key: string, // unique per payment attempt
     *     customer_info: ?array,   // optional customer data
     *     store_id: int,
     *     tenant_id: int,
     * }
     * @return array {
     *     gateway_transaction_id: string,
     *     status: string,          // 'pending', 'success', 'failed'
     *     gateway_response: array,
     *     expires_at: ?string,     // for QRIS/VA with expiry
     *     payment_url: ?string,    // for redirect-based payments
     *     qr_string: ?string,      // for QRIS
     * }
     */
    public function createCharge(array $paymentData): array;

    /**
     * Verify a webhook payload from the gateway.
     *
     * @param string $payload  Raw request body
     * @param array $headers   Request headers (for signature verification)
     * @return array {
     *     verified: bool,
     *     event_type: string,      // 'payment.succeeded', 'payment.failed', etc.
     *     gateway_transaction_id: string,
     *     amount: float,
     *     paid_at: ?string,
     * }
     */
    public function verifyWebhook(string $payload, array $headers): array;

    /**
     * Refund a payment via the gateway.
     *
     * @param string $gatewayTransactionId
     * @param float $amount
     * @param string $reason
     * @return array {
     *     refund_id: string,
     *     status: string,
     *     amount: float,
     * }
     */
    public function refund(string $gatewayTransactionId, float $amount, string $reason): array;

    /**
     * Get payment status from the gateway.
     *
     * @param string $gatewayTransactionId
     * @return array {
     *     status: string,
     *     amount: float,
     *     paid_at: ?string,
     *     settlement_amount: ?float,
     *     platform_fee: ?float,
     *     net_amount: ?float,
     * }
     */
    public function getStatus(string $gatewayTransactionId): array;

    /**
     * Provision a sub-account for a tenant (for xenPlatform-type gateways).
     *
     * @param array $tenantInfo {
     *     tenant_id: int,
     *     business_name: string,
     *     email: string,
     *     phone: ?string,
     *     bank_account: ?array,
     * }
     * @return array {
     *     gateway_account_id: string,
     *     status: string,
     * }
     */
    public function provisionSubAccount(array $tenantInfo): array;
}
```

### 12.3 Phase 0 Implementations

| Implementation | Status | Description |
|---------------|--------|-------------|
| `ManualPayment` | Phase 0 | Cash payments, manual bank transfers — no gateway API calls |
| `XenditGateway` | Phase 5 (future) | Xendit xenPlatform — review actual API docs before implementation |

### 12.4 ManualPayment Implementation (Phase 0)

```php
// app/Payments/ManualPayment.php

class ManualPayment implements PaymentGatewayInterface
{
    public function createCharge(array $paymentData): array
    {
        // No gateway call — just record the payment
        return [
            'gateway_transaction_id' => 'MANUAL-' . uniqid(),
            'status' => 'success',  // Cash is immediate
            'gateway_response' => ['method' => 'manual'],
            'expires_at' => null,
            'payment_url' => null,
            'qr_string' => null,
        ];
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        // No webhooks for manual payments
        return ['verified' => false, 'event_type' => 'none'];
    }

    public function refund(string $gatewayTransactionId, float $amount, string $reason): array
    {
        // Manual refund — just record it
        return [
            'refund_id' => 'MANUAL-REF-' . uniqid(),
            'status' => 'success',
            'amount' => $amount,
        ];
    }

    public function getStatus(string $gatewayTransactionId): array
    {
        return [
            'status' => 'success',
            'amount' => 0, // Looked up from internal records, not gateway
            'paid_at' => now()->toIso8601String(),
            'settlement_amount' => null,
            'platform_fee' => null,
            'net_amount' => null,
        ];
    }

    public function provisionSubAccount(array $tenantInfo): array
    {
        // No sub-account needed for manual payments
        return [
            'gateway_account_id' => 'MANUAL-' . $tenantInfo['tenant_id'],
            'status' => 'active',
        ];
    }
}
```

### 12.5 Design Rules

1. **XenditGateway is NOT implemented in Phase 0** — only the interface and ManualPayment.
2. **Xendit implementation requires reviewing actual Xendit xenPlatform API documentation** — not assumptions from PDR.
3. **The interface is the contract** — all future gateways must implement it.
4. **PaymentService uses the interface** — not a concrete implementation. The gateway is resolved via Laravel service container binding.
5. **Gateway selection is per-tenant** — each tenant can have a different gateway (or manual only).

### 12.6 Service Container Binding

```php
// app/Providers/PaymentServiceProvider.php

public function register(): void
{
    $this->app->bind(PaymentGatewayInterface::class, function ($app) {
        $gateway = config('payments.default_gateway', 'manual');

        return match ($gateway) {
            'manual' => new ManualPayment(),
            'xendit' => new XenditGateway(), // Phase 5 — not yet implemented
            default => new ManualPayment(),
        };
    });
}
```

```php
// config/payments.php

return [
    'default_gateway' => env('PAYMENT_GATEWAY', 'manual'),
    'gateways' => [
        'manual' => [
            'driver' => 'manual',
        ],
        'xendit' => [
            'driver' => 'xendit',
            'api_key' => env('XENDIT_API_KEY', ''),
            'webhook_token' => env('XENDIT_WEBHOOK_TOKEN', ''),
            // These will be populated in Phase 5 after API review
        ],
    ],
];
```

---

*End of Phase 0 Architecture*
