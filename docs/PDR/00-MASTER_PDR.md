# MASTER PDR — POS Restoran → ERP Platform

**Document Status:** APPROVED — Phase 0 Active  
**Created:** 2026-08-11  
**Updated:** 2026-08-11 — User refinements incorporated  
**Based On:** System Audit Report (`docs/SYSTEM_AUDIT_REPORT.md`)  
**Decision:** Freeze current POS implementation. Design ERP architecture before any further development.

---

## 1. VISION

Transform **POS Restoran** from a single-purpose restaurant POS into a **Multi-Tenant, Multi-Business ERP Platform** where:

1. A tenant selects a **Business Type** at registration — from predefined templates OR a custom type.
2. The system enables a **default set of Modules** based on business type template.
3. The **Owner** can enable/disable modules and features at any time.
4. The **Frontend dynamically renders** UI based on enabled modules + user permissions (RBAC 2.0).
5. The **Backend enforces** module access + RBAC at API level. Frontend security never replaces backend security — both are required.
6. **Double-entry accounting** is the foundation — every monetary transaction creates a balanced journal entry.
7. **Payment gateway is abstracted** — Xendit xenPlatform is the first implementation, but the architecture supports multiple gateways.
8. **Audit & security** are built from Phase 0 — not bolted on later.
9. **Business Types are unlimited** — predefined templates (Restaurant, Café, Retail, Pharmacy, Clinic, Salon, Hotel, etc.) are defaults, NOT architectural limits. Custom business types are supported.

---

## 2. BUSINESS TYPES (UNLIMITED)

Business Types are **templates**, not architectural constraints. The system supports:

- **Predefined Templates**: Restaurant, Café, Retail, Grocery, Pharmacy, Clinic, Salon, Hotel, Wholesale, Service, Manufacturing, General
- **Custom Business Types**: Owner can define a custom type and manually select modules

### Predefined Templates

| Template | Slug | Default Modules |
|----------|------|-----------------|
| Restaurant | restaurant | POS, Inventory, Purchasing, Customers, Tables, Kitchen, KDS, Reports, Finance |
| Café | cafe | POS, Inventory, Purchasing, Customers, Menu, Modifiers, Reports, Finance |
| Retail Shop | retail | POS, Inventory, Purchasing, Customers, Barcode, Reports, Finance |
| Grocery | grocery | POS, Inventory, Purchasing, Suppliers, Barcode, Reports, Finance |
| Pharmacy* | pharmacy | POS, Inventory, Purchasing, Customers, Batch/Expiry, Reports, Finance |
| Clinic* | clinic | Customers, Appointments, Invoices, Inventory, Reports, Finance |
| Salon | salon | Customers, Appointments, POS, Inventory, Reports, Finance |
| Hotel* | hotel | Reservations, POS, Inventory, Customers, Reports, Finance |
| Service Business | service | Customers, Appointments, Invoices, Staff, Reports, Finance |
| Wholesale | wholesale | Sales, Purchasing, Inventory, Customers, Price Lists, Reports, Finance |
| Manufacturing* | manufacturing | Inventory, Purchasing, Manufacturing, Sales, Reports, Finance |
| General | general | POS, Inventory, Purchasing, Customers, Reports, Finance |

*Regulated/specialized modules (Pharmacy, Clinic, Hotel, Manufacturing) will be designed with their own compliance/operational rules in later phases.

**Key Principle:** Business Type is ONLY a default configuration template. It does NOT limit architecture. The system is **Module + Feature + Configuration** driven. Any tenant can enable any module regardless of business type.

---

## 3. MODULE SYSTEM

### 3.1 Architecture

```
Tenant
  │
  ├── Business Type (sets module defaults)
  │
  ├── Enabled Modules (Owner can toggle)
  │      ├── POS
  │      ├── Inventory
  │      ├── Purchasing
  │      ├── Customers
  │      ├── Suppliers
  │      ├── Tables
  │      ├── Kitchen / KDS
  │      ├── Finance / Accounting
  │      ├── Reports
  │      ├── Barcode
  │      ├── Appointments
  │      └── ... (extensible)
  │
  ├── Enabled Features (granular toggles within modules)
  │      ├── POS → split_payment, multi_payment, receipt_printing
  │      ├── Inventory → batch_tracking, expiry_tracking, transfer
  │      └── ...
  │
  ├── Users
  │      └── Roles
  │             └── Permissions (module-scoped)
  │
  └── Stores / Branches
```

### 3.2 Module Registry

Each module is a self-contained unit with:
- **Slug** (e.g., `pos`, `inventory`, `kitchen`)
- **Name** (display name)
- **Description**
- **Default permissions** (what permissions this module brings)
- **Default features** (what features are enabled by default)
- **Dependencies** (e.g., `kitchen` depends on `pos` + `inventory`)
- **Business type defaults** (which business types enable this by default)

### 3.3 Feature Flags

Features are granular capabilities within a module:
- **Slug** (e.g., `pos.split_payment`, `inventory.batch_tracking`)
- **Module** (parent module)
- **Default enabled** (per business type)
- **Owner toggleable** (yes/no — some features are always-on)

---

## 4. ROLES & PERMISSIONS

### 4.1 Role Hierarchy

| Role | Scope | Description |
|------|-------|-------------|
| Owner | Tenant | Full access. Can manage modules, users, billing, settings. |
| Manager | Store/Tenant | Operational management. Cannot manage billing or users. |
| Cashier | Store | POS operations, sales, customers. |
| Staff | Store | Limited view access. No POS (unless enabled). |
| Accountant | Tenant | Finance & reports access. No POS. |
| Custom | Store/Tenant | Owner-defined roles with specific permissions. |

### 4.2 Permission Model

Permissions are **module-scoped**:

```
{module}.{action}

Examples:
  pos.use
  pos.manage
  sales.view
  sales.manage
  inventory.view
  inventory.manage
  finance.view
  finance.manage
  reports.view
  users.manage
  settings.manage
  kitchen.view
  kitchen.manage
```

### 4.3 Permission Resolution

```
User requests action
  │
  ├── 1. Is module enabled for tenant? → NO = 403
  ├── 2. Is feature enabled for tenant? → NO = 403
  ├── 3. Does user's role have permission? → NO = 403
  └── 4. Is user scoped to the correct store? → NO = 403
```

### 4.4 Frontend RBAC

The frontend MUST implement RBAC:

1. **Module-based navigation**: Sidebar shows only enabled modules.
2. **Permission-based UI**: Buttons, pages, sections hidden based on user permissions.
3. **Route guards**: `ProtectedRoute` enhanced with module + permission checks.
4. **API response handling**: 403 → show "Access Denied" or hide element.

---

## 5. FINANCIAL ARCHITECTURE

### 5.1 Principle

Every monetary transaction flows through a **double-entry accounting system**:

```
Sale → Journal Entry → Ledger → Financial Reports
Purchase → Journal Entry → Ledger → Financial Reports
Payment → Journal Entry → Ledger → Financial Reports
Expense → Journal Entry → Ledger → Financial Reports
Refund → Journal Entry → Ledger → Financial Reports
```

### 5.2 Core Accounting Entities

| Entity | Description |
|--------|-------------|
| Chart of Accounts | Account tree (Assets, Liabilities, Equity, Revenue, Expenses) |
| Journal Entries | Double-entry records (debit + credit, always balanced) |
| Ledger | Account-level transaction history |
| Cost Centers | Stores/branches as cost centers |
| Fiscal Periods | Monthly/quarterly/yearly accounting periods |

### 5.3 Accounts (Default Chart)

```
Assets
  ├── Cash (per store)
  ├── Bank Account
  ├── Accounts Receivable
  ├── Inventory (valuation)
  └── Fixed Assets

Liabilities
  ├── Accounts Payable
  ├── Tax Payable
  └── Accrued Expenses

Equity
  ├── Owner's Capital
  └── Retained Earnings

Revenue
  ├── Sales Revenue
  ├── Service Revenue
  └── Other Income

Expenses
  ├── Cost of Goods Sold (COGS)
  ├── Operating Expenses
  ├── Rent
  ├── Utilities
  ├── Salaries
  └── Fees (payment gateway)
```

### 5.4 Design Rule

Double-entry accounting will NOT be implemented hastily. Phase 0 must define:
- Account hierarchy model
- Journal entry structure
- How POS/Purchase/Payment map to journal entries
- Period closing rules
- Multi-currency support (future)

---

## 6. PAYMENT ARCHITECTURE

### 6.1 Payment Gateway Abstraction

Payment Gateway is **decoupled from business logic** via an interface:

```
ERP Platform
  │
  └── Payment Gateway Interface (abstract)
       ├── Xendit (xenPlatform) — primary implementation
       ├── Future Gateway (Stripe, Midtrans, etc.)
       └── Manual / Cash — always available
```

### 6.2 Xendit xenPlatform Model

```
                 OUR PLATFORM (ERP)
                       │
                 Xendit Platform
                   (Master Account)
                       │
          ┌────────────┼────────────┐
          │            │            │
       Tenant A    Tenant B    Tenant C
       Xendit      Xendit      Xendit
      Sub-account  Sub-account  Sub-account
          │            │            │
       Bank A        Bank B        Bank C
```

**NOT this:**
```
Customer → Our Xendit Account → Our Bank → Tenant
```

### 6.3 Payment Flow

```
Checkout
  │
  ├── Cash → Direct record (no gateway)
  │
  ├── QRIS → Xendit xenPlatform
  │     ├── Create payment on tenant's sub-account
  │     ├── Customer pays via QRIS
  │     ├── Xendit webhook → confirm payment
  │     └── Settlement to tenant's bank
  │
  ├── Card → Xendit xenPlatform (future)
  │
  └── Bank Transfer → Xendit xenPlatform (future)
```

### 6.4 Design Requirements

- Each tenant gets a **Xendit sub-account** (or sub-account equivalent)
- Platform takes a **fee** per transaction (configurable)
- **Webhook** handles async payment confirmation
- **Reconciliation** matches Xendis reports with internal records
- **Refund** flows through Xendit API
- Must review **actual Xendit API documentation** before implementation

### 6.5 Implementation Rule

Xendit integration is **Phase 6**. Before implementation:
1. Review Xendit xenPlatform API documentation (current version)
2. Design sub-account provisioning flow
3. Design webhook handling + idempotency
4. Design reconciliation process
5. Design platform fee model
6. Get user approval on architecture

---

## 7. CURRENT STATE FREEZE

**Effective immediately:**

- ✅ Current POS implementation is **frozen** at git commit `8483f83` (Phase 5.6)
- ✅ No new features will be added to the current codebase until PDR is approved
- ✅ Bug fixes are allowed (if critical)
- ✅ The current system serves as the **reference implementation** for:
  - Multi-tenant isolation pattern
  - RBAC middleware pattern
  - Service layer pattern
  - Inventory locking pattern
  - Payment idempotency pattern
  - Atomic checkout pattern

### What carries forward to ERP:
- `BelongsToTenant` trait + global scope
- `CheckPermission` middleware (enhanced with module checks)
- Service layer architecture
- `DB::transaction()` + `lockForUpdate()` patterns
- Payment idempotency design
- PHPUnit + Playwright test infrastructure
- Docker development environment

### What gets redesigned:
- Registration flow (adds business type + module selection)
- Frontend navigation (module-driven, not hardcoded)
- Dashboard (real data, module-aware widgets)
- RBAC (module-scoped permissions, frontend enforcement)
- Database schema (adds modules, features, business_profiles, accounting tables)
- API structure (module-aware route groups)

---

## 8. DOCUMENTATION-FIRST RULE

### 8.1 Per-Phase Documentation Requirements

Every phase MUST produce documentation BEFORE implementation:

```
PDR / Architecture
       ↓
Business Rules
       ↓
Database Design
       ↓
API Design
       ↓
UI/UX Flow
       ↓
Flowchart
       ↓
Implementation
       ↓
Security Tests
       ↓
API Tests
       ↓
Integration Tests
       ↓
Smoke Tests
       ↓
E2E / UI Tests
       ↓
UX Verification
       ↓
Documentation
       ↓
Final Audit
       ↓
Phase COMPLETE
```

### 8.2 Documentation Folder Structure

```
/docs/
  SYSTEM_AUDIT_REPORT.md              ← Current state audit (done)
  PDR/                                 ← Master PDR documents
    00-MASTER_PDR.md                   ← This document
    01-ERP_ARCHITECTURE.md             ← Technical architecture
    02-PHASE_ROADMAP.md                ← Phase-by-phase roadmap
    03-DOCUMENTATION_FRAMEWORK.md      ← Documentation standards
  architecture/                        ← Per-module architecture docs
  business-rules/                      ← Business rules per module
  database/                            ← DB design per module
  api/                                 ← API specs per module
  flows/                               ← Flowcharts per module
  security/                            ← Security docs per module
  testing/                             ← Test plans per module
  modules/                             ← Module-specific documentation
```

### 8.3 Per-Module Documentation Example

```
/docs/modules/payment/
  payment-pdr.md           ← What + Why
  payment-architecture.md  ← How (technical)
  payment-flow.md          ← Flowchart + sequence
  payment-api.md           ← API endpoints + contracts
  payment-security.md      ← Security considerations
  payment-testing.md       ← Test plan + coverage
```

### 8.4 Phase Completion Checklist

A phase is NOT complete until ALL of the following pass:

| Check | Status |
|-------|--------|
| Implementation | ☐ |
| Database | ☐ |
| API | ☐ |
| Security | ☐ |
| Smoke Tests | ☐ |
| Integration Tests | ☐ |
| E2E Tests | ☐ |
| UI Tests | ☐ |
| UX Verification | ☐ |
| Documentation | ☐ |
| Regression (no existing tests broken) | ☐ |

**If ANY check fails → Phase is NOT COMPLETE.**

---

## 9. KEY ARCHITECTURAL DECISIONS

| # | Decision | Rationale |
|---|----------|-----------|
| 1 | Module-driven architecture | Business type sets defaults; Owner can toggle. System is not limited to one business type. |
| 2 | Feature flags within modules | Granular control (e.g., split_payment, batch_tracking) without new modules. |
| 3 | Frontend RBAC required | Backend-only RBAC is insufficient for ERP. UI must reflect user's actual access. |
| 4 | Double-entry accounting | Foundation for financial integrity. All transactions map to journal entries. |
| 5 | Xendit xenPlatform (sub-accounts) | Each tenant gets own Xendit sub-account. Platform fee model. Not a single merchant account. |
| 6 | Documentation-first workflow | No implementation without approved PDR. Prevents rework. |
| 7 | Phase = complete feature/module | Not divided into hundreds of micro-steps. Agent manages internal tasks. |
| 8 | Current POS code is reference | Patterns carry forward. Schema gets extended, not rewritten. |
| 9 | Review Xendit API before implementation | Design based on actual API docs, not assumptions. |
| 10 | Regulated modules deferred | Pharmacy and similar regulated business types get specialized design later. |

---

## 10. AUDIT & SECURITY FOUNDATION (FROM PHASE 0)

Security is NOT bolted on later. From Phase 0, the following are foundational:

| Security Layer | Description |
|---------------|-------------|
| Audit Logs | All CRUD operations, login/logout, financial transactions logged |
| Authentication | Sanctum tokens (existing), 2FA (future, feature-flagged) |
| Authorization | RBAC 2.0 — module + feature + permission checks, frontend + backend |
| Tenant Isolation | BelongsToTenant global scope (existing, preserved) |
| IDOR Protection | All resource access checks tenant_id ownership |
| Rate Limiting | API rate limits per endpoint, per user, per tenant |
| Sensitive Data Protection | Encryption at rest, secrets in env, no hardcoded keys |
| Webhook Security | Signature verification, idempotency, replay prevention |
| Idempotency | Payment idempotency keys (existing pattern, extended to all financial ops) |
| API Security | Input validation, output sanitization, CORS, HTTPS only |
| Financial Transaction Integrity | DB transactions, lockForUpdate, balanced journal entries |

---

## 11. APPROVAL

This PDR is **APPROVED**. Phase 0 documentation is being produced.

**Current Step:** Producing Phase 0 documentation set (`docs/phases/phase-00/`).

**Next Steps:**
1. ✅ Master PDR approved
2. ✅ ERP Architecture approved
3. ✅ Phase Roadmap approved
4. ✅ Documentation Framework approved
5. → Producing Phase 0 docs (PDR, Architecture, Flow, API, Security, Testing)
6. → Phase 0 acceptance gate review
7. → Phase 0 implementation

---

*End of Master PDR*
