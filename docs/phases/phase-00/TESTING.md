# Phase 0 — ERP Foundation & Architecture — TESTING

**Phase:** 0  
**Status:** IN PROGRESS — Documentation Phase  
**Created:** 2026-08-11  
**Depends On:** `docs/phases/phase-00/PDR.md`, `docs/phases/phase-00/ARCHITECTURE.md`, `docs/phases/phase-00/SECURITY.md`

---

## 1. TESTING STRATEGY

### 1.1 Principle

Phase 0 introduces foundational architecture changes. The testing strategy ensures:

1. **Zero regression** — All 736 existing backend tests + 18 E2E tests must pass
2. **New feature coverage** — All new endpoints, models, middleware, and frontend features tested
3. **Security verification** — Module/feature/permission enforcement tested at API + E2E level
4. **Migration safety** — Database migration tested with existing data

**Note on test counts:** The estimated test counts in this document (~73 backend, ~21 E2E) are **acceptance targets, not exact requirements**. If security or business logic requires more tests (e.g., 90 instead of 73), the higher count takes precedence. **Quality and coverage matter more than the number.**

### 1.2 Test Pyramid

```
         ┌───────────┐
         │   E2E     │  ← Playwright (user flows, RBAC, registration)
         ├───────────┤
         │ Integration│  ← PHPUnit Feature (API endpoints, cross-module)
         ├───────────┤
         │   Unit     │  ← PHPUnit Unit (services, models, middleware)
         └───────────┘
```

---

## 2. BACKEND TESTS

### 2.1 Regression Tests (Existing — Must Still Pass)

| Test File | Tests | Status |
|-----------|-------|--------|
| CategoryTest | 8 | Must pass |
| ProductTest | 21 | Must pass |
| InventoryModelTest | 24 | Must pass |
| InventoryServiceTest | 32 | Must pass |
| InventoryApiTest | 37 | Must pass |
| InventoryTransferTest | 36 | Must pass |
| AuthTest | 2 | Must pass (may need update for enhanced response) |
| CustomerTest | 30 | Must pass |
| SupplierTest | 37 | Must pass |
| PurchaseTest | 53 | Must pass |
| Phase4SecurityGateTest | 82 | Must pass |
| PurchaseReturnTest | 60 | Must pass |
| Phase4FinalGateTest | 38 | Must pass |
| SaleModelTest | 33 | Must pass |
| SaleServiceTest | 60 | Must pass |
| SaleApiTest | 48 | Must pass |
| PaymentServiceTest | 52 | Must pass |
| SaleSmokeTest | 4 | Must pass |
| Phase5SecurityTest | ~80 | Must pass |
| **Total** | **736** | **ALL MUST PASS** |

### 2.2 New Backend Tests

#### ModuleTest (Unit + Feature)

| # | Test | Description |
|---|------|-------------|
| 1 | module_registry_seeded | All modules exist in DB after seeding |
| 2 | feature_registry_seeded | All features exist in DB after seeding |
| 3 | module_has_dependencies | Module dependencies are correctly defined |
| 4 | core_module_cannot_be_disabled | Core module is always enabled |
| 5 | module_enable_creates_tenant_module | Enabling module creates tenant_modules record |
| 6 | module_disable_sets_is_enabled_false | Disabling module sets is_enabled = false |
| 7 | module_enable_with_missing_dependency_fails | Cannot enable module if dependency not enabled |
| 8 | module_disable_with_dependent_enabled_fails | Cannot disable module if dependent is enabled |
| 9 | feature_enable_requires_parent_module | Cannot enable feature if parent module disabled |
| 10 | feature_not_toggleable_cannot_be_changed | Non-toggleable feature cannot be changed |
| 11 | business_type_defaults_applied | Applying business type enables correct modules |
| 12 | business_type_feature_defaults_applied | Applying business type enables correct features |
| 13 | module_enable_logged_to_audit | Enabling module creates audit log entry |
| 14 | module_disable_logged_to_audit | Disabling module creates audit log entry |

#### BusinessTypeTest (Unit + Feature)

| # | Test | Description |
|---|------|-------------|
| 1 | business_types_seeded | All predefined business types exist |
| 2 | business_type_has_default_modules | Each business type has default module config |
| 3 | business_type_has_default_features | Each business type has default feature config |
| 4 | custom_business_type_can_be_created | Custom business type can be created |
| 5 | business_type_modules_correct | Restaurant has pos, inventory, tables, kitchen, etc. |
| 6 | business_type_features_correct | Restaurant has pos.split_payment, etc. |

#### BusinessProfileTest (Feature)

| # | Test | Description |
|---|------|-------------|
| 1 | profile_created_on_registration | Business profile created when tenant registers |
| 2 | profile_defaults_set | Default timezone, currency, locale set correctly |
| 3 | profile_update_works | Owner can update business profile |
| 4 | profile_update_validation | Invalid data returns 422 |
| 5 | cross_tenant_profile_access_blocked | Cannot access another tenant's profile |
| 6 | non_owner_cannot_update_profile | Manager/Cashier/Staff cannot update profile |

#### EnhancedAuthTest (Feature)

| # | Test | Description |
|---|------|-------------|
| 1 | register_with_business_type | Registration with business_type_id works |
| 2 | register_without_business_type_defaults_general | Registration without business_type_id uses 'general' |
| 3 | register_response_includes_modules | Response includes enabled modules |
| 4 | register_response_includes_features | Response includes enabled features |
| 5 | register_response_includes_permissions | Response includes user permissions |
| 6 | register_response_includes_stores | Response includes stores list |
| 7 | login_response_enhanced | Login response includes config |
| 8 | me_endpoint_returns_full_config | GET /me returns modules, features, permissions, stores |
| 9 | rate_limit_login | 6th login attempt in 1 minute returns 429 |
| 10 | rate_limit_register | 6th register attempt in 1 minute returns 429 |

#### ModuleAccessTest (Feature — Security)

| # | Test | Description |
|---|------|-------------|
| 1 | api_returns_403_when_module_disabled | Endpoint with module: middleware returns 403 if module disabled |
| 2 | api_returns_403_when_feature_disabled | Endpoint with feature: middleware returns 403 if feature disabled |
| 3 | api_returns_401_when_unauthenticated | All protected endpoints return 401 without token |
| 4 | api_returns_403_when_permission_missing | Endpoint returns 403 when user lacks permission |
| 5 | module_check_bypasses_frontend_manipulation | Backend rejects even if frontend is manipulated |
| 6 | cross_tenant_module_access_blocked | Cannot toggle modules for another tenant |
| 7 | staff_cannot_access_pos | Staff role cannot access POS endpoints |
| 8 | cashier_cannot_access_settings | Cashier role cannot access settings endpoints |
| 9 | accountant_can_access_finance | Accountant role can access finance endpoints |
| 10 | accountant_cannot_access_pos | Accountant role cannot access POS endpoints |

#### StoreScopeTest (Feature)

| # | Test | Description |
|---|------|-------------|
| 1 | store_header_filters_data | X-Store-Id header filters query results |
| 2 | invalid_store_id_returns_403 | Non-existent store ID returns 403 |
| 3 | cross_tenant_store_returns_403 | Store from another tenant returns 403 |
| 4 | user_without_store_access_returns_403 | User assigned to Store A cannot access Store B |
| 5 | tenant_wide_role_accesses_all_stores | User with tenant-wide role accesses all stores |

#### DashboardTest (Feature)

| # | Test | Description |
|---|------|-------------|
| 1 | dashboard_returns_stats | GET /dashboard returns stats object |
| 2 | dashboard_stats_correct | Product count, sales count, etc. are accurate |
| 3 | dashboard_returns_recent_sales | Recent sales (last 5) returned |
| 4 | dashboard_returns_low_stock | Low stock items returned |
| 5 | dashboard_store_scoped | With X-Store-Id, stats scoped to store |
| 6 | dashboard_unauthenticated_returns_401 | Without token returns 401 |

#### AuditLogTest (Feature)

| # | Test | Description |
|---|------|-------------|
| 1 | audit_log_created_on_login | Login creates audit log entry |
| 2 | audit_log_created_on_logout | Logout creates audit log entry |
| 3 | audit_log_created_on_module_toggle | Module enable/disable logged |
| 4 | audit_log_created_on_feature_toggle | Feature enable/disable logged |
| 5 | audit_log_list_owner_only | Only Owner can list audit logs |
| 6 | audit_log_tenant_scoped | Cannot see other tenant's audit logs |
| 7 | audit_log_immutable | Cannot update or delete audit log entries |
| 8 | audit_log_filters_work | Filter by entity_type, user, action, date range |

#### MigrationTest (Feature)

| # | Test | Description |
|---|------|-------------|
| 1 | migration_creates_all_new_tables | All new tables exist after migration |
| 2 | migration_alters_roles_table | roles table has tenant_id, is_system, sort_order |
| 3 | migration_alters_permissions_table | permissions table has module_id |
| 4 | migration_alters_stores_table | stores table has new columns |
| 5 | existing_data_preserved | All existing data intact after migration |
| 6 | rbac_migration_seeder_assigns_module_id | Permissions have correct module_id |
| 7 | tenant_migration_seeder_creates_profiles | Existing tenants get business_profiles |
| 8 | tenant_migration_seeder_enables_modules | Existing tenants get correct modules enabled |

### 2.3 Estimated New Backend Test Count

| Test File | Estimated Tests |
|-----------|----------------|
| ModuleTest | 14 |
| BusinessTypeTest | 6 |
| BusinessProfileTest | 6 |
| EnhancedAuthTest | 10 |
| ModuleAccessTest | 10 |
| StoreScopeTest | 5 |
| DashboardTest | 6 |
| AuditLogTest | 8 |
| MigrationTest | 8 |
| **Total New** | **~73** |
| **Total with Regression** | **~809** |

---

## 3. FRONTEND E2E TESTS

### 3.1 Regression Tests (Existing — Must Still Pass)

| Test File | Tests | Status |
|-----------|-------|--------|
| pos-flow.spec.ts | 8 | Must pass |
| security.spec.ts | 10 | Must pass |
| **Total** | **18** | **ALL MUST PASS** |

### 3.2 New E2E Tests

#### registration-flow.spec.ts

| # | Test | Description |
|---|------|-------------|
| 1 | register with restaurant business type | Select Restaurant → see POS, Tables, Kitchen in sidebar |
| 2 | register with retail business type | Select Retail → see POS, Barcode in sidebar, no Tables |
| 3 | register with general business type | Select General → see basic modules |
| 4 | register without business type | Default to General → basic modules |
| 5 | login after registration shows correct modules | Modules persist after logout/login |

#### module-rbac.spec.ts

| # | Test | Description |
|---|------|-------------|
| 1 | staff cannot see POS link | Staff sidebar does not show POS |
| 2 | staff cannot access POS page | Navigating to /pos redirects to /dashboard |
| 3 | cashier cannot see settings link | Cashier sidebar does not show Settings |
| 4 | cashier cannot access settings page | Navigating to /settings/store redirects to /dashboard |
| 5 | owner sees all enabled modules | Owner sidebar shows all enabled module links |
| 6 | accountant sees finance and reports | Accountant sidebar shows Finance, Reports |
| 7 | accountant cannot access POS | Accountant cannot see or access POS |
| 8 | disabled module not in sidebar | After disabling module, it disappears from sidebar |

#### store-switcher.spec.ts

| # | Test | Description |
|---|------|-------------|
| 1 | store switcher visible with multiple stores | Header shows store switcher |
| 2 | store switcher hidden with single store | No store switcher when only 1 store |
| 3 | switching store updates data | Data refreshes after store switch |
| 4 | store switcher persists selection | Selected store persists after page reload |

#### dashboard.spec.ts

| # | Test | Description |
|---|------|-------------|
| 1 | dashboard shows real stats | Stats cards show non-zero values |
| 2 | dashboard shows recent sales | Recent sales table has data |
| 3 | dashboard shows low stock alerts | Low stock section shows items |
| 4 | dashboard module-aware | Only shows widgets for enabled modules |

### 3.3 Estimated New E2E Test Count

| Test File | Tests |
|-----------|-------|
| registration-flow.spec.ts | 5 |
| module-rbac.spec.ts | 8 |
| store-switcher.spec.ts | 4 |
| dashboard.spec.ts | 4 |
| **Total New** | **21** |
| **Total with Regression** | **39** |

---

## 4. TEST DATA

### 4.1 Updated E2ESeeder

The existing `E2ESeeder` must be updated to include:

```php
// Create business profile for test tenant
BusinessProfile::create([
    'tenant_id' => $tenant->id,
    'business_type_id' => 1, // restaurant
    'business_name' => 'Test Restaurant',
    'timezone' => 'Asia/Jakarta',
    'currency' => 'IDR',
]);

// Enable modules for test tenant (restaurant defaults)
$moduleService->applyBusinessTypeDefaults($tenant->id, 1);

// Create Accountant user
$accountantRole = Role::where('slug', 'accountant')->first();
User::create([
    'name' => 'E2E Accountant',
    'email' => 'e2e.accountant@test.com',
    'password' => Hash::make('password123'),
    'tenant_id' => $tenant->id,
    'role_id' => $accountantRole->id,
]);

// Create second store for switcher tests
Store::create([
    'tenant_id' => $tenant->id,
    'name' => 'Branch Store',
    'code' => 'BRANCH',
    'is_active' => true,
]);
```

### 4.2 Test Credentials (Updated)

| Role | Email | Password |
|------|-------|----------|
| Owner | e2e.owner@test.com | password123 |
| Cashier | e2e.cashier@test.com | password123 |
| Staff | e2e.staff@test.com | password123 |
| Accountant | e2e.accountant@test.com | password123 |

### 4.3 New Seeders

| Seeder | Purpose |
|--------|---------|
| ModuleSeeder | Populate modules + features |
| BusinessTypeSeeder | Populate business types + defaults |
| RbacMigrationSeeder | Assign module_id to existing permissions |
| TenantMigrationSeeder | Create profiles + enable modules for existing tenants |

---

## 5. TEST EXECUTION

### 5.1 Backend

```bash
# Run all tests (regression + new)
php artisan test

# Run only Phase 0 tests
php artisan test --filter=ModuleTest
php artisan test --filter=BusinessTypeTest
php artisan test --filter=BusinessProfileTest
php artisan test --filter=EnhancedAuthTest
php artisan test --filter=ModuleAccessTest
php artisan test --filter=StoreScopeTest
php artisan test --filter=DashboardTest
php artisan test --filter=AuditLogTest
php artisan test --filter=MigrationTest

# Run regression only (verify no breakage)
php artisan test --filter=CategoryTest
php artisan test --filter=ProductTest
# ... etc
```

### 5.2 Frontend E2E

```bash
# Run all E2E tests (regression + new)
npx playwright test

# Run only Phase 0 E2E tests
npx playwright test registration-flow.spec.ts
npx playwright test module-rbac.spec.ts
npx playwright test store-switcher.spec.ts
npx playwright test dashboard.spec.ts

# Run regression only
npx playwright test pos-flow.spec.ts
npx playwright test security.spec.ts
```

### 5.3 Build Verification

```bash
# Frontend build
npm run build

# Backend (no build step, but verify no syntax errors)
php artisan route:list
php artisan migrate:status
```

---

## 6. COVERAGE TARGETS

| Area | Target | Verification |
|------|--------|--------------|
| New API endpoints | 100% | Every endpoint has at least 1 test |
| Module/Feature toggle | 100% | Enable + disable tested |
| RBAC enforcement | 100% | Every role × every module access tested |
| Security (IDOR, cross-tenant) | 100% | Every new endpoint tested for IDOR |
| Migration safety | 100% | Migration test with existing data |
| Frontend RBAC | 100% | Every role × sidebar visibility tested |
| Regression | 0% regression | All 736 existing tests pass |

---

## 7. PHASE 0 TEST COMPLETION GATE

| # | Check | Criteria |
|---|-------|----------|
| TC-1 | All existing backend tests pass | 736/736 |
| TC-2 | All existing E2E tests pass | 18/18 |
| TC-3 | All new backend tests pass | ~73/73 |
| TC-4 | All new E2E tests pass | ~21/21 |
| TC-5 | Frontend build passes | `npm run build` exit 0 |
| TC-6 | No console errors on key pages | Dashboard, POS, Settings |
| TC-7 | Migration runs cleanly | `php artisan migrate:fresh --seed` works |
| TC-8 | No breaking API changes | Existing API contracts preserved |

**If ANY check fails → Phase 0 is NOT COMPLETE.**

---

*End of Phase 0 Testing*
