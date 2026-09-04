# ERP TECHNICAL ARCHITECTURE

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-11  
**Depends On:** `00-MASTER_PDR.md`

---

## 1. SYSTEM ARCHITECTURE OVERVIEW

```
┌─────────────────────────────────────────────────────────┐
│                    FRONTEND (React)                      │
│                                                         │
│  Module-Aware Navigation                                │
│  Permission-Based UI Rendering                          │
│  Dynamic Route Loading                                  │
│  Store/Branch Switcher                                  │
│                                                         │
│  Zustand Stores: auth, cart, module-config, ui-state    │
└──────────────────────┬──────────────────────────────────┘
                       │ HTTPS / API
┌──────────────────────▼──────────────────────────────────┐
│                 BACKEND (Laravel API)                    │
│                                                         │
│  ┌─────────────────────────────────────────────────┐    │
│  │  Middleware Stack                                │    │
│  │  auth:sanctum → module.check → permission.check  │    │
│  │  → tenant.scope → store.scope                    │    │
│  └─────────────────────────────────────────────────┘    │
│                                                         │
│  ┌─────────────────────────────────────────────────┐    │
│  │  Service Layer                                   │    │
│  │  SaleService · InventoryService ·                │    │
│  │  PurchaseService · PaymentService ·              │    │
│  │  AccountingService · ModuleService               │    │
│  └─────────────────────────────────────────────────┘    │
│                                                         │
│  ┌─────────────────────────────────────────────────┐    │
│  │  Domain Models (BelongsToTenant)                 │    │
│  │  Tenant · BusinessProfile · Module · Feature     │    │
│  │  Store · User · Role · Permission                │    │
│  │  Product · Category · Inventory · Sale ·         │    │
│  │  Payment · Purchase · Customer · Supplier ·      │    │
│  │  JournalEntry · Ledger · Account                 │    │
│  └─────────────────────────────────────────────────┘    │
└──────────────────────┬──────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────┐
│                   MySQL Database                         │
│                                                         │
│  tenant_scoped tables    │  system tables               │
│  (BelongsToTenant)       │  (modules, plans)            │
└─────────────────────────────────────────────────────────┘
```

---

## 2. MULTI-TENANCY MODEL

### 2.1 Tenant Hierarchy

```
Tenant (organization)
  │
  ├── Business Profile
  │     ├── business_type (restaurant, cafe, retail, ...)
  │     ├── business_name
  │     ├── tax_id (NPWP)
  │     ├── address, phone, email
  │     ├── logo
  │     └── timezone, currency
  │
  ├── Module Instances (enabled modules)
  │     └── Feature Flags (enabled features per module)
  │
  ├── Stores / Branches
  │     └── Store-specific settings
  │
  ├── Users
  │     └── Role Assignments (tenant-level or store-level)
  │
  ├── Subscriptions
  │     └── Plan + billing
  │
  └── Financial Setup
        ├── Chart of Accounts
        ├── Cost Centers (stores)
        └── Fiscal Periods
```

### 2.2 Tenant Isolation (unchanged from current)

- `BelongsToTenant` trait with global scope
- `tenant_id` auto-set from `Auth::user()->tenant_id`
- `withoutTenantScope()` for system-level operations only
- `tenant_id` NEVER in `$fillable`, NEVER from request

---

## 3. MODULE SYSTEM DESIGN

### 3.1 Database Schema

```sql
-- Module registry (system-level, not tenant-scoped)
modules
  id
  slug            -- e.g., 'pos', 'inventory', 'kitchen'
  name            -- 'POS / Kasir'
  description
  is_core         -- true = cannot be disabled (e.g., 'core')
  dependencies    -- JSON array of module slugs
  sort_order
  icon            -- UI icon identifier
  timestamps

-- Feature registry (system-level)
features
  id
  module_id       -- FK → modules
  slug            -- e.g., 'pos.split_payment'
  name
  description
  is_default_enabled  -- boolean
  is_owner_toggleable -- boolean (some features always-on)
  timestamps

-- Tenant's enabled modules (tenant-scoped)
tenant_modules
  id
  tenant_id       -- FK → tenants
  module_id       -- FK → modules
  is_enabled      -- boolean
  enabled_at      -- timestamp
  timestamps
  UNIQUE(tenant_id, module_id)

-- Tenant's enabled features (tenant-scoped)
tenant_features
  id
  tenant_id       -- FK → tenants
  feature_id      -- FK → features
  is_enabled      -- boolean
  timestamps
  UNIQUE(tenant_id, feature_id)

-- Business type defaults (system-level)
business_types
  id
  slug            -- 'restaurant', 'cafe', 'retail', ...
  name
  description
  is_active
  timestamps

-- Module defaults per business type (system-level)
business_type_modules
  id
  business_type_id  -- FK → business_types
  module_id         -- FK → modules
  is_default_enabled
  timestamps
  UNIQUE(business_type_id, module_id)

-- Feature defaults per business type (system-level)
business_type_features
  id
  business_type_id  -- FK → business_types
  feature_id        -- FK → features
  is_default_enabled
  timestamps
  UNIQUE(business_type_id, feature_id)
```

### 3.2 Business Profile Schema

```sql
-- Extends tenant with business-specific config
business_profiles
  id
  tenant_id           -- FK → tenants (unique, 1:1)
  business_type_id    -- FK → business_types
  business_name
  tax_id              -- NPWP (Indonesia)
  address
  city
  province
  postal_code
  phone
  email
  logo               -- file path or URL
  timezone           -- default: 'Asia/Jakarta'
  currency           -- default: 'IDR'
  locale             -- default: 'id'
  is_active
  timestamps
```

### 3.3 Module Access Flow

```
API Request
  │
  ├── 1. auth:sanctum → identify user + tenant
  │
  ├── 2. module.check middleware
  │     ├── Route has module slug (e.g., Route::group(['module' => 'pos'], ...))
  │     ├── Check tenant_modules: is module enabled for this tenant?
  │     └── NO → 403 "Module not enabled"
  │
  ├── 3. permission.check middleware
  │     ├── Required permission: e.g., 'pos.use'
  │     ├── Check user's role → role_permissions
  │     └── NO → 403 "Insufficient permissions"
  │
  ├── 4. feature.check middleware (optional)
  │     ├── Required feature: e.g., 'pos.split_payment'
  │     ├── Check tenant_features: is feature enabled?
  │     └── NO → 403 "Feature not enabled"
  │
  ├── 5. tenant.scope (BelongsToTenant global scope)
  │     └── Auto-filter all queries by tenant_id
  │
  └── 6. store.scope (optional)
        └── Filter by current store context
```

### 3.4 Module Registry (Initial)

| Module | Slug | Core? | Dependencies |
|--------|------|-------|-------------|
| Core | core | Yes | — |
| POS | pos | No | core, inventory |
| Sales | sales | No | core |
| Inventory | inventory | No | core |
| Purchasing | purchasing | No | core, inventory |
| Customers | customers | No | core |
| Suppliers | suppliers | No | core |
| Finance | finance | No | core |
| Reports | reports | No | core |
| Tables | tables | No | pos |
| Kitchen | kitchen | No | pos, inventory |
| KDS | kds | No | kitchen |
| Barcode | barcode | No | pos, inventory |
| Appointments | appointments | No | customers |
| User Management | users | No | core |
| Settings | settings | No | core |

### 3.5 Feature Registry (Initial)

| Feature Slug | Module | Default | Toggleable |
|--------------|--------|---------|------------|
| pos.split_payment | pos | true | yes |
| pos.multi_payment | pos | true | yes |
| pos.receipt_printing | pos | true | yes |
| pos.hold_sale | pos | false | yes |
| pos.refund | pos | true | yes |
| inventory.batch_tracking | inventory | false | yes |
| inventory.expiry_tracking | inventory | false | yes |
| inventory.transfer | inventory | true | yes |
| inventory.low_stock_alert | inventory | true | yes |
| sales.discount | sales | true | yes |
| sales.tax | sales | true | yes |
| finance.double_entry | finance | true | no |
| finance.multi_currency | finance | false | yes |
| reports.export_pdf | reports | true | yes |
| reports.export_excel | reports | true | yes |
| reports.schedule | reports | false | yes |

---

## 4. RBAC ARCHITECTURE

### 4.1 Enhanced Permission Model

```sql
-- Roles (tenant-scoped — each tenant can have custom roles)
roles
  id
  tenant_id         -- nullable (system roles have null tenant_id)
  name
  slug
  is_system         -- true for owner/manager/cashier/staff/accountant
  sort_order
  timestamps

-- Permissions (system-level, module-scoped)
permissions
  id
  module_id         -- FK → modules
  slug              -- e.g., 'pos.use', 'inventory.manage'
  name
  description
  timestamps

-- Role-Permission mapping
role_permissions
  id
  role_id
  permission_id
  UNIQUE(role_id, permission_id)

-- User-Role mapping (user can have multiple roles per store)
user_roles
  id
  user_id
  role_id
  store_id          -- nullable (null = tenant-wide role)
  timestamps
  UNIQUE(user_id, role_id, store_id)
```

### 4.2 System Roles (pre-seeded)

| Role | Slug | Permissions |
|------|------|-------------|
| Owner | owner | ALL permissions (all modules) |
| Manager | manager | All except users.manage, settings.manage |
| Cashier | cashier | pos.use, sales.view, sales.manage, customers.view, customers.manage |
| Staff | staff | products.view, categories.view, inventory.view, customers.view |
| Accountant | accountant | finance.view, finance.manage, reports.view |

### 4.3 Frontend RBAC Implementation

```typescript
// Zustand store: module-config
interface ModuleConfigStore {
  enabledModules: string[]
  enabledFeatures: string[]
  userPermissions: string[]
  currentStore: Store | null

  hasModule: (slug: string) => boolean
  hasFeature: (slug: string) => boolean
  hasPermission: (slug: string) => boolean
  can: (permission: string) => boolean
}

// Navigation: filter by enabled modules + permissions
const navItems = ALL_NAV_ITEMS.filter(item =>
  moduleConfig.hasModule(item.module) &&
  moduleConfig.hasPermission(item.permission)
)

// Route guard: <ProtectedRoute module="pos" permission="pos.use">
```

### 4.4 API Response: Module Config

```
GET /api/v1/me
{
  "user": { ... },
  "tenant": { ... },
  "business_profile": { ... },
  "modules": ["pos", "inventory", "purchasing", "customers", "reports", "finance"],
  "features": ["pos.split_payment", "pos.multi_payment", "inventory.transfer", ...],
  "permissions": ["pos.use", "sales.view", "sales.manage", "inventory.view", ...],
  "stores": [ { ... }, { ... } ],
  "current_store": { ... }
}
```

---

## 5. REGISTRATION FLOW

```
Step 1: Account Info
  ├── name, email, password
  └── → Create tenant + user (owner role)

Step 2: Business Type
  ├── Select: Restaurant / Cafe / Retail / Grocery / Service / Wholesale / General
  └── → Create business_profile
        → Enable default modules for business type
        → Enable default features for business type
        → Seed default chart of accounts

Step 3: Store Setup
  ├── store name, address, phone
  └── → Create store

Step 4: Module Confirmation
  ├── Show enabled modules (from business type defaults)
  ├── Owner can toggle modules on/off
  └── → Save tenant_modules + tenant_features

Step 5: Dashboard
  └── → Redirect to module-aware dashboard
```

---

## 6. API ARCHITECTURE

### 6.1 Versioning

- Current: `/api/v1/*`
- ERP: `/api/v1/*` (continue same version — no breaking changes to existing endpoints)
- New module endpoints: `/api/v1/{module-slug}/*`

### 6.2 Route Structure

```php
// System routes (no module check)
Route::prefix('auth')->group(...)
Route::get('me', ...)

// Module-scoped routes
Route::middleware(['module:pos', 'permission:pos.use'])->prefix('pos')->group(...)
Route::middleware(['module:sales', 'permission:sales.view'])->prefix('sales')->group(...)
Route::middleware(['module:inventory', 'permission:inventory.view'])->prefix('inventory')->group(...)
Route::middleware(['module:finance', 'permission:finance.view'])->prefix('finance')->group(...)
Route::middleware(['module:reports', 'permission:reports.view'])->prefix('reports')->group(...)
```

### 6.3 Standard API Patterns

```
List:   GET    /api/v1/{module}                    ?search=&filter=&per_page=&page=
Show:   GET    /api/v1/{module}/{id}
Create: POST   /api/v1/{module}
Update: PUT    /api/v1/{module}/{id}
Delete: DELETE /api/v1/{module}/{id}
Action: POST   /api/v1/{module}/{id}/{action}       (e.g., /sales/{id}/cancel)
```

### 6.4 Response Format

```json
// Success (single resource)
{
  "data": { ... },
  "message": "Resource created successfully"
}

// Success (paginated)
{
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8
  }
}

// Error
{
  "message": "Error description",
  "errors": {
    "field": ["Validation error message"]
  }
}
```

---

## 7. FINANCIAL ARCHITECTURE

### 7.1 Accounting Schema

```sql
-- Chart of Accounts (tenant-scoped)
accounts
  id
  tenant_id
  code              -- e.g., '1000', '4001'
  name              -- 'Cash', 'Sales Revenue'
  type              -- enum: asset, liability, equity, revenue, expense
  subtype           -- e.g., 'current_asset', 'operating_expense'
  parent_id         -- FK → accounts (nullable, for hierarchy)
  is_active
  opening_balance   -- decimal(15,2)
  timestamps

-- Journal Entries (tenant-scoped)
journal_entries
  id
  tenant_id
  entry_number      -- e.g., 'JE-20260811-0001'
  entry_date
  description
  reference_type    -- polymorphic: Sale, Purchase, Payment, Expense, ...
  reference_id
  status            -- enum: draft, posted, reversed
  total_debit       -- decimal(15,2) (must equal total_credit)
  total_credit      -- decimal(15,2)
  created_by        -- FK → users
  timestamps

-- Journal Lines (tenant-scoped)
journal_lines
  id
  tenant_id
  journal_entry_id  -- FK → journal_entries
  account_id        -- FK → accounts
  debit             -- decimal(15,2) (either debit or credit, not both)
  credit            -- decimal(15,2)
  description
  timestamps

-- Cost Centers (stores as cost centers)
cost_centers
  id
  tenant_id
  store_id          -- FK → stores (nullable for tenant-level)
  name
  code
  timestamps

-- Fiscal Periods
fiscal_periods
  id
  tenant_id
  name              -- 'August 2026'
  start_date
  end_date
  status            -- enum: open, closed, locked
  timestamps
```

### 7.2 Transaction to Journal Entry Mapping

```
Sale (checkout)
  ├── Debit: Cash/Bank (asset)         ← payment amount
  ├── Credit: Sales Revenue (revenue)  ← sale total - tax
  ├── Credit: Tax Payable (liability)  ← tax amount
  ├── Debit: COGS (expense)            ← cost of items
  └── Credit: Inventory (asset)        ← cost of items

Purchase (receive)
  ├── Debit: Inventory (asset)              ← purchase total
  ├── Credit: Accounts Payable (liability) ← purchase total

Payment to Supplier
  ├── Debit: Accounts Payable (liability)
  └── Credit: Cash/Bank (asset)

Expense
  ├── Debit: Expense Account (expense)
  └── Credit: Cash/Bank (asset)

Refund (sale cancel)
  ├── Reverse original sale journal entry
  └── Create new reversing entry
```

### 7.3 Design Rules

1. Every monetary transaction creates a **balanced journal entry** (total_debit = total_credit).
2. Journal entries are **immutable once posted** (status = 'posted'). Corrections require reversing entries.
3. Fiscal periods can be **closed** (no new entries) or **locked** (no edits at all).
4. Chart of Accounts is **seeded per tenant** at registration based on business type.
5. Account codes follow a **standardized numbering scheme**:
   - 1000-1999: Assets
   - 2000-2999: Liabilities
   - 3000-3999: Equity
   - 4000-4999: Revenue
   - 5000-5999: Expenses

---

## 8. PAYMENT ARCHITECTURE (Xendit xenPlatform)

### 8.1 Schema Extension

```sql
-- Xendit tenant sub-account linkage
payment_gateway_accounts
  id
  tenant_id
  gateway             -- 'xendit'
  sub_account_id      -- Xendit sub-account ID
  status              -- enum: pending, active, suspended, closed
  metadata            -- JSON (gateway-specific data)
  timestamps

-- Payment transactions (extends current payments table)
-- Additional columns on payments:
  gateway_transaction_id   -- Xendit transaction ID
  gateway_status           -- Xendit status (pending, succeeded, failed)
  gateway_response         -- JSON (full gateway response)
  settlement_amount        -- decimal(15,2) (amount settled to tenant)
  platform_fee             -- decimal(15,2) (our platform fee)
  net_amount               -- decimal(15,2) (settlement - fee)
  settled_at               -- timestamp

-- Webhook log for idempotent webhook processing
payment_webhooks
  id
  tenant_id
  webhook_id          -- Xendit webhook ID (for idempotency)
  event_type          -- 'payment.succeeded', 'payment.failed', ...
  payload             -- JSON (full webhook payload)
  processed_at
  timestamps
  UNIQUE(tenant_id, webhook_id)
```

### 8.2 Payment Flow (QRIS via Xendit)

```
1. POS Checkout (QRIS selected)
   │
   ├── POST /api/v1/sales/checkout
   │     ├── SaleService::checkout()
   │     ├── Create Sale + SaleItems + Inventory decrease
   │     ├── Create Payment (status = 'pending', gateway = 'xendit')
   │     └── Call Xendit API: create QRIS charge on tenant sub-account
   │
   ├── Return QR string to frontend
   │
2. Customer scans QR → pays
   │
3. Xendit webhook → our endpoint
   │
   ├── POST /api/v1/webhooks/xendit
   │     ├── Verify webhook signature
   │     ├── Check payment_webhooks for idempotency
   │     ├── Update payment status: 'success'
   │     ├── Update sale payment_status: 'paid'
   │     ├── Create journal entry (Debit: Bank, Credit: Revenue)
   │     └── Return 200 OK to Xendit
   │
4. Settlement (T+1 or T+2)
   │
   ├── Xendit settles to tenant bank account
   ├── Webhook: 'payment.settled'
   │     ├── Update payment: settled_at, settlement_amount, platform_fee, net_amount
   │     └── Create settlement journal entry
   │
5. Reconciliation
   │
   ├── Daily/weekly: match Xendit reports with internal records
   └── Discrepancies flagged for review
```

### 8.3 Platform Fee Model

```
Payment amount:     Rp 100,000
Xendit fee:         Rp 700 (0.7%)
Platform fee:       Rp 500 (configurable per tenant/plan)
Net to tenant:      Rp 98,800

Journal Entry:
  Debit: Bank              98,800
  Debit: Platform Fee Expense  500  (tenant expense)
  Debit: Gateway Fee Expense    700  (tenant expense)
  Credit: Sales Revenue    100,000
```

---

## 9. STORE / BRANCH ARCHITECTURE

### 9.1 Multi-Store Model

- Each tenant can have multiple stores/branches.
- Each store has its own inventory, sales, and cost center.
- Users can be assigned to specific stores or tenant-wide.
- Reports can be per-store or consolidated.
- POS operations are always scoped to a selected store.

### 9.2 Store Context

```
Frontend: Store switcher in header
  │
  ├── GET /api/v1/me → returns stores list
  ├── User selects store → stored in Zustand + localStorage
  ├── All API calls include X-Store-Id header
  └── Backend middleware: store.scope → filter queries by store_id
```

### 9.3 Store Schema (extended from current)

```sql
stores
  id
  tenant_id
  name
  code
  address
  city
  province
  postal_code
  phone
  email
  is_headquarters      -- boolean
  is_active
  settings             -- JSON (store-specific config: tax rate, receipt format, etc.)
  timestamps
```

---

## 10. NOTIFICATION ARCHITECTURE (Future)

```
Notification Triggers
  ├── Low stock alert (inventory <= minimum_quantity)
  ├── New sale completed
  ├── Payment received
  ├── Purchase order received
  ├── Daily sales summary
  ├── Subscription expiring
  └── Custom alerts

Notification Channels
  ├── In-app (bell icon in header)
  ├── Email
  ├── WhatsApp (future)
  └── Push notification (future, mobile app)

notifications
  id
  tenant_id
  user_id             -- nullable (null = tenant-wide)
  type                -- 'low_stock', 'sale_completed', ...
  title
  message
  data                -- JSON (additional context)
  read_at             -- nullable
  timestamps
```

---

## 11. AUDIT ARCHITECTURE (Future)

```
audit_logs
  id
  tenant_id
  user_id
  action              -- 'create', 'update', 'delete', 'login', 'logout'
  entity_type         -- 'Sale', 'Product', 'User', ...
  entity_id
  old_values          -- JSON (nullable)
  new_values          -- JSON (nullable)
  ip_address
  user_agent
  timestamps
```

---

## 12. TECHNOLOGY DECISIONS

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Backend | Laravel 13 (continue) | Existing codebase, team familiarity, PHP 8.4 |
| Frontend | React 19 + TypeScript (continue) | Existing codebase, type safety |
| State | Zustand (continue) | Lightweight, no boilerplate |
| Database | MySQL 8.0 (continue) | Supports JSON, transactions, row-level locking |
| Auth | Sanctum tokens (continue) | Working, tested |
| E2E | Playwright (continue) | Working, tested |
| Queue | Laravel Horizon + Redis (new) | Async jobs for webhooks, notifications, reports |
| Cache | Redis (new) | Session, cache, queue |
| Search | MySQL FULLTEXT (initial) then Meilisearch (future) | Start simple, upgrade when needed |
| File Storage | Local then S3 (future) | Receipts, logos, product images |
| Monitoring | Sentry (future) | Error tracking |
| CI/CD | GitHub Actions (future) | Automated testing + deployment |

---

*End of ERP Technical Architecture*
