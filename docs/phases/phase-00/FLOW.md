# Phase 0 — ERP Foundation & Architecture — FLOW

**Phase:** 0  
**Status:** IN PROGRESS — Documentation Phase  
**Created:** 2026-08-11  
**Depends On:** `docs/phases/phase-00/PDR.md`, `docs/phases/phase-00/ARCHITECTURE.md`

---

## 1. REGISTRATION FLOW

### 1.1 New Registration (with Business Type)

```
User opens /register
  │
  ├── Step 1: Account Info
  │     ├── Enter: name, email, password, store_name
  │     ├── Select: business_type (from GET /api/v1/business-types)
  │     └── POST /api/v1/auth/register
  │           │
  │           ├── Validate input
  │           ├── DB::transaction:
  │           │     ├── Create Tenant
  │           │     ├── Create BusinessProfile (tenant_id, business_type_id)
  │           │     ├── Create User (tenant_id, role_id = owner)
  │           │     ├── Create Store (default store)
  │           │     ├── Enable default modules (from business_type_modules)
  │           │     ├── Enable default features (from business_type_features)
  │           │     └── Assign Owner role to User
  │           ├── Create Sanctum token
  │           └── Return: { token, user, business_profile, modules, features }
  │
  ├── Step 2: Frontend receives config
  │     ├── Save token to localStorage
  │     ├── Save user to auth store
  │     ├── Save modules + features to module-config store
  │     └── Redirect to /dashboard
  │
  └── Dashboard renders based on enabled modules
```

### 1.2 Existing Registration (Backward Compatible)

```
POST /api/v1/auth/register (without business_type_id)
  │
  ├── Uses 'general' business type as default
  ├── Enables: core, pos, inventory, purchasing, customers, reports, finance
  └── All existing tests continue to pass
```

---

## 2. LOGIN FLOW

```
POST /api/v1/auth/login
  │
  ├── Validate: email, password
  ├── Find user by email
  ├── Verify password (Hash::check)
  ├── Create Sanctum token
  ├── Return: { token, user (with tenant, role) }
  │
  └── Frontend:
        ├── Save token to localStorage
        ├── Save user to auth store
        ├── GET /api/v1/me
        │     ├── Returns: user, tenant, business_profile, modules, features, permissions, stores
        │     └── Save to module-config store
        ├── Set default store (first store or last selected from localStorage)
        └── Redirect to /dashboard
```

---

## 3. MODULE ACCESS FLOW

```
API Request: GET /api/v1/sales
  │
  ├── 1. auth:sanctum
  │     ├── Extract token from Authorization header
  │     ├── Identify user + tenant
  │     └── Fail → 401 Unauthorized
  │
  ├── 2. module:sales
  │     ├── Check tenant_modules: is 'sales' module enabled?
  │     ├── Fail → 403 "Module not enabled"
  │     └── Pass ↓
  │
  ├── 3. permission:sales.view
  │     ├── Check user's role → role_permissions → permissions
  │     ├── Fail → 403 "Insufficient permissions"
  │     └── Pass ↓
  │
  ├── 4. tenant.scope (BelongsToTenant)
  │     ├── All queries auto-filtered by tenant_id
  │     └── Pass ↓
  │
  ├── 5. store.scope (optional)
  │     ├── If X-Store-Id header present, filter by store_id
  │     └── Pass ↓
  │
  └── Controller executes → returns data
```

---

## 4. MODULE TOGGLE FLOW

```
Owner opens Module Settings page
  │
  ├── GET /api/v1/tenant/modules
  │     └── Returns: list of all modules + enabled status + dependencies
  │
  ├── Owner toggles module (e.g., enable 'accounting')
  │     └── PUT /api/v1/tenant/modules/{module_id}
  │           │
  │           ├── ModuleService::enableModule(tenantId, 'accounting')
  │           ├── Validate dependencies (is 'core' enabled? yes)
  │           ├── Create/update tenant_modules record (is_enabled = true)
  │           ├── Enable default features for this module
  │           ├── Log to audit_logs
  │           └── Return: updated module list
  │
  ├── Owner toggles module off (e.g., disable 'kds')
  │     └── PUT /api/v1/tenant/modules/{module_id}
  │           │
  │           ├── ModuleService::disableModule(tenantId, 'kds')
  │           ├── Check: are there dependent modules enabled? (none)
  │           ├── Set tenant_modules.is_enabled = false
  │           ├── Set dependent tenant_features.is_enabled = false
  │           ├── Log to audit_logs
  │           └── Return: updated module list
  │
  └── Frontend refreshes module-config store → sidebar updates
```

---

## 5. FEATURE TOGGLE FLOW

```
Owner opens Module Settings → Feature flags
  │
  ├── GET /api/v1/tenant/features
  │     └── Returns: list of all features + enabled status + toggleable
  │
  ├── Owner toggles feature (e.g., enable 'pos.split_payment')
  │     └── PUT /api/v1/tenant/features/{feature_id}
  │           │
  │           ├── Check: is parent module enabled? (yes, 'pos' is enabled)
  │           ├── Check: is feature owner_toggleable? (yes)
  │           ├── Update tenant_features.is_enabled
  │           ├── Log to audit_logs
  │           └── Return: updated feature list
  │
  └── Frontend refreshes module-config store → UI updates
```

---

## 6. STORE SWITCH FLOW

```
User clicks Store Switcher
  │
  ├── GET /api/v1/me → returns stores list
  │
  ├── User selects Store B
  │     ├── setStore(Store B) in module-config store
  │     ├── Save store_id to localStorage
  │     ├── Set X-Store-Id header on all future API calls
  │     └── Refresh current page data (store-scoped)
  │
  └── All subsequent API calls include X-Store-Id: {store_b_id}
        └── Backend store.scope middleware filters by store_id
```

---

## 7. RBAC RESOLUTION FLOW

### 7.1 Backend

```
User requests action (e.g., POST /api/v1/sales/checkout)
  │
  ├── 1. Is 'sales' module enabled for tenant? → NO = 403
  ├── 2. Is 'pos.use' permission in user's role? → NO = 403
  ├── 3. Is 'pos.split_payment' feature enabled? (if applicable) → NO = 403
  ├── 4. Does store belong to tenant? → NO = 403
  ├── 5. Does user have access to this store? → NO = 403
  └── 6. Execute → 200 OK
```

### 7.2 Frontend

```
User navigates to /pos
  │
  ├── ProtectedRoute checks:
  │     ├── isAuthenticated? → NO = redirect /login
  │     ├── hasModule('pos')? → NO = redirect /dashboard
  │     └── hasPermission('pos.use')? → NO = redirect /dashboard
  │
  ├── DashboardLayout renders sidebar:
  │     ├── Filter ALL_NAV_ITEMS by hasModule + hasPermission
  │     └── Only show items user can access
  │
  ├── POSPage renders:
  │     ├── hasFeature('pos.split_payment')? → show split payment UI
  │     ├── hasPermission('sales.manage')? → show checkout button
  │     └── !hasPermission('sales.manage')? → hide checkout, show view-only
  │
  └── API call: POST /api/v1/sales/checkout
        └── Backend re-checks everything (frontend security ≠ backend security)
```

---

## 8. MIGRATION FLOW (Existing Tenants)

```
Phase 0 Implementation — Migration of existing data
  │
  ├── 1. Run new migrations (create new tables, alter existing)
  │
  ├── 2. Run ModuleSeeder
  │     └── Populate modules + features tables (system-level)
  │
  ├── 3. Run BusinessTypeSeeder
  │     └── Populate business_types + business_type_modules + business_type_features
  │
  ├── 4. Run RbacMigrationSeeder
  │     ├── Assign module_id to existing permissions
  │     │     ├── products.* → module: core
  │     │     ├── categories.* → module: core
  │     │     ├── sales.* → module: sales
  │     │     ├── pos.use → module: pos
  │     │     ├── inventory.* → module: inventory
  │     │     ├── customers.* → module: customers
  │     │     ├── suppliers.* → module: suppliers
  │     │     ├── purchases.* → module: purchasing
  │     │     ├── reports.* → module: reports
  │     │     ├── settings.* → module: settings
  │     │     └── users.* → module: users
  │     └── Add 'Accountant' role with finance.view, finance.manage, reports.view
  │
  ├── 5. Run TenantMigrationSeeder
  │     ├── For each existing tenant:
  │     │     ├── Create BusinessProfile (business_type_id = 'general')
  │     │     ├── Enable all modules that tenant has data for
  │     │     │     ├── core (always)
  │     │     │     ├── pos (if sales exist)
  │     │     │     ├── inventory (if inventories exist)
  │     │     │     ├── purchasing (if purchases exist)
  │     │     │     ├── customers (if customers exist)
  │     │     │     ├── suppliers (if suppliers exist)
  │     │     │     ├── sales (if sales exist)
  │     │     │     └── reports (always)
  │     │     └── Enable all features that are default_enabled
  │     └── Mark all existing roles as is_system = true
  │
  └── 6. Run E2ESeeder (updated)
        ├── Create test tenant with business_profile
        ├── Enable modules for test tenant
        ├── Create users with roles
        └── All existing E2E tests continue to work
```

---

## 9. DASHBOARD DATA FLOW

```
User opens /dashboard
  │
  ├── GET /api/v1/dashboard
  │     ├── auth:sanctum → identify user + tenant
  │     ├── Check X-Store-Id header → scope to store (or all stores)
  │     ├── Query:
  │     │     ├── Product::count() (if core module)
  │     │     ├── Sale::whereDate('sale_date', today())->count() (if sales module)
  │     │     ├── Sale::whereDate('sale_date', today())->sum('total') (if sales module)
  │     │     ├── Inventory::whereColumn('qty', '<=', 'min_qty')->count() (if inventory module)
  │     │     ├── Customer::count() (if customers module)
  │     │     └── Supplier::count() (if suppliers module)
  │     ├── Recent sales (last 5, if sales module)
  │     ├── Low stock items (top 5, if inventory module)
  │     └── Return: { stats, recent_sales, low_stock_items }
  │
  └── Frontend renders:
        ├── Stats cards (only for enabled modules)
        ├── Recent sales table (if sales module enabled)
        └── Low stock alerts (if inventory module enabled)
```

---

## 10. AUDIT LOG FLOW

```
Any CRUD operation on a model
  │
  ├── Model observer or service-level call:
  │     └── AuditService::log(action, entityType, entityId, oldValues, newValues)
  │           │
  │           ├── Create audit_logs record:
  │           │     ├── tenant_id (from Auth)
  │           │     ├── user_id (from Auth)
  │           │     ├── action ('create' | 'update' | 'delete')
  │           │     ├── entity_type (e.g., 'Sale')
  │           │     ├── entity_id
  │           │     ├── old_values (JSON, for update/delete)
  │           │     ├── new_values (JSON, for create/update)
  │           │     ├── ip_address (from request)
  │           │     └── user_agent (from request)
  │           └── Done (async in future, sync for now)
  │
  └── GET /api/v1/audit-logs (Owner only)
        └── Paginated list with filters (entity_type, user, date range)
```

---

*End of Phase 0 Flow*
