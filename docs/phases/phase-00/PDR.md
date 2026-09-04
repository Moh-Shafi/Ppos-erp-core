# Phase 0 — ERP Foundation & Architecture — PDR

**Phase:** 0  
**Status:** IN PROGRESS — Documentation Phase  
**Created:** 2026-08-11  
**Depends On:** `docs/PDR/00-MASTER_PDR.md`, `docs/PDR/01-ERP_ARCHITECTURE.md`

---

## 1. PROBLEM STATEMENT

The current system is a single-purpose restaurant POS. It needs to become a **Multi-Tenant, Multi-Business ERP Platform** where:

- Any business type can register and get appropriate default modules
- Modules and features are dynamically enabled/disabled per tenant
- RBAC is enforced on both frontend AND backend
- The foundation for accounting, payments, and audit is laid from the start
- The existing POS functionality is preserved without regression

**Phase 0 is NOT about building features. It is about building the architectural foundation that all future phases depend on.**

---

## 2. SCOPE

### IN SCOPE

1. **Current System Freeze** — Document and freeze Phase 5 state
2. **ERP Core Architecture** — Platform → Tenant → Business Type → Modules → Features → RBAC
3. **Unlimited Business Type System** — Predefined templates + custom types
4. **Modular ERP System** — Module registry, feature registry, tenant module/feature config
5. **RBAC 2.0** — Module-scoped permissions, frontend + backend enforcement
6. **Financial Architecture Foundation** — Account model design (no implementation yet)
7. **Payment Architecture Foundation** — PaymentGatewayInterface contract + ManualPayment implementation (XenditGateway deferred to Phase 5)
8. **Audit & Security Foundation** — Audit log table, security model, rate limiting design
9. **Documentation Framework** — Per-phase documentation structure
10. **Migration Strategy** — How to evolve the DB schema without breaking existing POS

### OUT OF SCOPE

- Any new business feature (POS enhancement, accounting implementation)
- Frontend UI redesign (only module-aware navigation + RBAC)
- Double-entry accounting implementation
- Xendit gateway implementation (deferred to Phase 5 — requires actual API docs review)
- Reports implementation
- Business-specific modules (tables, KDS, etc.)

---

## 3. USER STORIES

### 3.1 Registration

> As a new business owner, I want to register and select my business type so that the system configures appropriate modules automatically.

> As a new business owner, I want to define a custom business type if my business doesn't fit any template so that I can manually select the modules I need.

### 3.2 Module Management

> As an Owner, I want to view enabled modules and toggle features so that I can customize the system for my business needs.

> As an Owner, I want to enable a new module (e.g., Accounting) so that I can expand my system capabilities over time.

### 3.3 RBAC

> As a Manager, I want the sidebar to only show modules I have access to so that I'm not confused by irrelevant options.

> As a Staff member, I want UI elements I can't use to be hidden so that I don't accidentally try to access restricted features.

> As an Owner, I want to assign roles to users per store so that a cashier at Store A doesn't have access to Store B.

### 3.4 Store Management

> As an Owner with multiple stores, I want to switch between stores so that I can view store-specific data and perform store-specific operations.

### 3.5 Dashboard

> As an Owner, I want to see real statistics on my dashboard so that I have an overview of my business performance.

---

## 4. BUSINESS RULES

### 4.1 Business Types

| # | Rule |
|---|------|
| BT-1 | Business types are templates, NOT architectural constraints |
| BT-2 | Any tenant can enable any module regardless of business type |
| BT-3 | Predefined templates provide default module + feature configuration |
| BT-4 | Custom business types allow manual module selection |
| BT-5 | Business type can be changed after registration (with module re-evaluation) |
| BT-6 | Changing business type does NOT disable already-enabled modules |

### 4.2 Modules

| # | Rule |
|---|------|
| MOD-1 | Core module is always enabled and cannot be disabled |
| MOD-2 | Module can only be enabled if all dependencies are enabled |
| MOD-3 | Disabling a module disables all dependent modules |
| MOD-4 | Disabling a module does NOT delete data — it hides access |
| MOD-5 | Re-enabling a module restores access to existing data |
| MOD-6 | Module enable/disable is logged in audit log |

### 4.3 Features

| # | Rule |
|---|------|
| FEAT-1 | Features are granular toggles within a module |
| FEAT-2 | Some features are always-on (not owner-toggleable) |
| FEAT-3 | Feature can only be enabled if parent module is enabled |
| FEAT-4 | Disabling a feature does NOT delete data |

### 4.4 RBAC

| # | Rule |
|---|------|
| RBAC-1 | Every API request checks: module enabled → feature enabled → permission granted |
| RBAC-2 | Frontend renders UI based on enabled modules + user permissions |
| RBAC-3 | Frontend RBAC does NOT replace backend RBAC — both are required |
| RBAC-4 | Owner role has ALL permissions across ALL modules |
| RBAC-5 | Users can have different roles per store |
| RBAC-6 | System roles (Owner, Manager, Cashier, Staff, Accountant) cannot be deleted |
| RBAC-7 | Custom roles can be created by Owner with specific permission sets |

### 4.5 Store Context

| # | Rule |
|---|------|
| STORE-1 | All POS/Sales/Inventory operations are scoped to a selected store |
| STORE-2 | Store context is set via `X-Store-Id` header or query parameter |
| STORE-3 | User can only switch to stores they have access to |
| STORE-4 | Reports can be per-store or consolidated (tenant-wide) |

### 4.6 Migration

| # | Rule |
|---|------|
| MIG-1 | Existing POS functionality must NOT break during Phase 0 |
| MIG-2 | All 736 existing backend tests must pass after Phase 0 |
| MIG-3 | All 18 existing E2E tests must pass after Phase 0 |
| MIG-4 | New tables are additive — no existing table dropped |
| MIG-5 | Existing `roles` table gets `tenant_id` nullable column (backward compatible) |
| MIG-6 | Existing `permissions` table gets `module_id` nullable column (backward compatible) |
| MIG-7 | Existing `users.role_id` relationship preserved; new `user_roles` table is additive |

---

## 5. ASSUMPTIONS

1. The current POS codebase (frozen at `8483f83`) is stable and all tests pass.
2. The existing `BelongsToTenant` trait and `CheckPermission` middleware patterns are preserved.
3. The existing database schema is extended, not rewritten.
4. Laravel 13 + React 19 + MySQL 8.0 stack is maintained.
5. Docker development environment continues to be used.
6. Sanctum token authentication is preserved.

---

## 6. DEPENDENCIES

| Dependency | Type | Status |
|-----------|------|--------|
| System Audit Report | Internal | Complete |
| Master PDR | Internal | Approved |
| ERP Architecture | Internal | Approved |
| Phase Roadmap | Internal | Approved |
| Documentation Framework | Internal | Approved |
| Existing POS codebase | Internal | Frozen at `8483f83` |

---

## 7. RISKS

| # | Risk | Severity | Mitigation |
|---|------|----------|------------|
| R-1 | Breaking existing POS during migration | High | Additive-only schema changes, full regression test suite |
| R-2 | RBAC migration complexity (single role_id → multi-role) | Medium | Keep `role_id` column, add `user_roles` table alongside, migrate in steps |
| R-3 | Frontend RBAC performance (checking permissions on every render) | Medium | Cache module/feature/permission config in Zustand store, refresh on login |
| R-4 | Module dependency resolution complexity | Low | Validate dependencies in service layer, not in middleware |
| R-5 | Existing seeders breaking | Low | Update seeders to include new tables, keep E2ESeeder compatible |

---

## 8. DELIVERABLES

### 8.1 Documentation (This Phase)

| # | Document | Status |
|---|----------|--------|
| 1 | `docs/phases/phase-00/PDR.md` | This document |
| 2 | `docs/phases/phase-00/ARCHITECTURE.md` | ✅ Written |
| 3 | `docs/phases/phase-00/FLOW.md` | ✅ Written |
| 4 | `docs/phases/phase-00/API.md` | ✅ Written |
| 5 | `docs/phases/phase-00/SECURITY.md` | ✅ Written |
| 6 | `docs/phases/phase-00/TESTING.md` | ✅ Written |
| 7 | `docs/phases/phase-00/FINAL-REPORT.md` | At completion |

### 8.2 Implementation (After Documentation Approval)

| # | Deliverable | Description |
|---|-------------|-------------|
| 1 | Business Types system | `business_types`, `business_type_modules`, `business_type_features` tables + seeder |
| 2 | Module Registry | `modules`, `features` tables + seeder |
| 3 | Tenant Module Config | `tenant_modules`, `tenant_features` tables + models |
| 4 | Business Profile | `business_profiles` table + model (1:1 with tenant) |
| 5 | Enhanced RBAC | `permissions.module_id`, `user_roles` table, updated middleware |
| 6 | Module Middleware | `CheckModule` + `CheckFeature` middleware |
| 7 | Registration Flow | Redesigned registration with business type selection |
| 8 | Enhanced `/api/v1/me` | Returns modules, features, permissions, stores |
| 9 | Frontend Module Store | Zustand store for module/feature/permission config |
| 10 | Frontend RBAC Navigation | Module-aware sidebar, permission-based UI |
| 11 | Store Switcher | Frontend store context switcher + `X-Store-Id` header |
| 12 | Real Dashboard | Dashboard with live stats from existing tables |
| 13 | Audit Log Table | `audit_logs` table + model (no implementation yet, just schema) |
| 14 | Rate Limiting | Laravel throttle middleware on auth + API endpoints |
| 15 | Migration of existing data | Update seeders, migrate existing tenants to new RBAC |
| 16 | PaymentGatewayInterface | `app/Contracts/PaymentGatewayInterface.php` — boundary contract |
| 17 | ManualPayment | `app/Payments/ManualPayment.php` — implements interface for cash/manual |
| 18 | PaymentServiceProvider | Service container binding for gateway resolution |
| 19 | payments config | `config/payments.php` with manual + xendit (xendit empty, Phase 5) |

---

## 9. ACCEPTANCE CRITERIA

### 9.1 Documentation Gate (Before Implementation)

| # | Criterion | Status |
|---|-----------|--------|
| DC-1 | PDR written and reviewed | ✅ Approved |
| DC-2 | Architecture document written | ✅ Written + consistency-checked |
| DC-3 | Flow diagrams created | ✅ 10 flow diagrams |
| DC-4 | API contract defined | ✅ 8 endpoint groups |
| DC-5 | Security model documented | ✅ 14 sections |
| DC-6 | Testing strategy defined | ✅ Strategy + test matrix |
| DC-7 | Migration strategy documented | ✅ Additive-only, 17-step order |
| DC-8 | UX rules defined | ✅ In PDR + Architecture |

### 9.2 Implementation Gate (After Documentation Approval)

| # | Criterion | Verification |
|---|-----------|--------------|
| IC-1 | Registration with business type works | E2E test |
| IC-2 | Modules enabled/disabled per tenant | API test |
| IC-3 | Features toggled per tenant | API test |
| IC-4 | Frontend shows only enabled module nav items | E2E test |
| IC-5 | Frontend hides UI elements user lacks permission for | E2E test |
| IC-6 | `GET /api/v1/me` returns full config | API test |
| IC-7 | Store switcher works | E2E test |
| IC-8 | Dashboard shows real stats | E2E test |
| IC-9 | All 736 existing backend tests pass | Regression |
| IC-10 | All 18 existing E2E tests pass | Regression |
| IC-11 | New tests for module/feature/RBAC system | Test suite |
| IC-12 | Audit log table exists | Migration test |
| IC-13 | Rate limiting active on auth endpoints | API test |

### 9.3 Phase Completion Gate

| # | Check | Status |
|---|-------|--------|
| PC-1 | PDR | ✅ |
| PC-2 | Architecture | ✅ |
| PC-3 | Database Design | ✅ |
| PC-4 | Flowcharts | ✅ |
| PC-5 | API Contract | ✅ |
| PC-6 | Security Model | ✅ |
| PC-7 | RBAC | ✅ |
| PC-8 | UX Rules | ✅ |
| PC-9 | Testing Strategy | ✅ |
| PC-10 | Migration Strategy | ✅ |
| PC-11 | Documentation | ✅ |
| PC-12 | Acceptance Criteria | ✅ |
| PC-13 | Final Audit | ⏳ At completion |

**If ANY check fails → Phase 0 is NOT COMPLETE.**

---

*End of Phase 0 PDR*
