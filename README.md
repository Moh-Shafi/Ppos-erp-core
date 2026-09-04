<div align="center">

# 💎 Ppos-erp-core
### Next-Generation Distributed Cloud POS & Multi-Tenant Modular ERP Platform
**An Enterprise-Grade, Multi-Vertical Operating System for Modern Commerce & Global Retail**

[![Build & Tests](https://img.shields.io/badge/PHPUnit_Tests-75+_Suites_Passing-brightgreen?style=for-the-badge&logo=php)](https://github.com/Moh-Shafi/Ppos-erp-core)
[![E2E Coverage](https://img.shields.io/badge/Playwright_E2E-12_Specs_Passing-blue?style=for-the-badge&logo=playwright)](https://github.com/Moh-Shafi/Ppos-erp-core)
[![Code Architecture](https://img.shields.io/badge/Architecture-Clean_Service_DDD-orange?style=for-the-badge)](https://github.com/Moh-Shafi/Ppos-erp-core)
[![Multi-Tenancy](https://img.shields.io/badge/Multi--Tenancy-Strict_Scope_Isolation-purple?style=for-the-badge)](https://github.com/Moh-Shafi/Ppos-erp-core)
[![Security](https://img.shields.io/badge/Security-2FA_·_XSS_·_PDP_Compliant-red?style=for-the-badge)](https://github.com/Moh-Shafi/Ppos-erp-core)

<br/>

[![Laravel 13](https://img.shields.io/badge/Laravel-13.8-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net)
[![React 19](https://img.shields.io/badge/React-19.2.8-61DAFB?style=flat-square&logo=react&logoColor=white)](https://react.dev)
[![TypeScript 6](https://img.shields.io/badge/TypeScript-6.0-3178C6?style=flat-square&logo=typescript&logoColor=white)](https://www.typescriptlang.org)
[![Vite 8](https://img.shields.io/badge/Vite-8.2-646CFF?style=flat-square&logo=vite&logoColor=white)](https://vite.dev)
[![Tailwind CSS 4](https://img.shields.io/badge/Tailwind-4.3-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![MySQL 8.0](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com)
[![Redis 7](https://img.shields.io/badge/Redis-7-DC382D?style=flat-square&logo=redis&logoColor=white)](https://redis.io)
[![Docker](https://img.shields.io/badge/Docker-Compose_Ready-2496ED?style=flat-square&logo=docker&logoColor=white)](https://www.docker.com)

<p align="center">
  <b>Architected for extreme scale, mission-critical accuracy, and limitless business adaptability.</b><br/>
  Engineered from the ground up through 10 comprehensive evolutionary phases into a world-class enterprise SaaS.
</p>

</div>

---

## 📊 Enterprise Engineering Metrics

| Dimension | Measure | Concrete Implementation |
|:---|:---:|:---|
| **Database Migrations** | **96 Files** | Full relational integrity, foreign keys, strict indexing, JSON payloads |
| **Eloquent Data Models** | **106 Models** | Domain-Driven models with soft multi-tenancy & audit traits |
| **Backend Controllers** | **63 Controllers** | Thin REST API controllers returning unified standardized JSON |
| **Enterprise Services** | **53 Services** | Pure business logic, atomic transactions, zero vendor lock-in |
| **Custom Security Middlewares** | **9 Middlewares** | Rate-limits, XSS defense, Brute-force lockout, 2FA, RBAC, Module gating |
| **Targeted Business Verticals** | **12+ Verticals** | Predefined engine templates with instant auto-provisioning |
| **Automated Backend Tests** | **75+ Suites** | High-density feature, regression, concurrency, and security tests |
| **Browser E2E Test Specs** | **12 Specs** | Playwright browser automation validating complete end-to-end flows |
| **Financial Accuracy** | **100% Double-Entry** | Real-time debit/credit balancing for every financial event |
| **Concurrency & Load Tested** | **100 Concurrent Users** | Sub-50ms response times on hot POS checkout and inventory locks |

---

## 🌌 The Executive Vision: From POS to Unified ERP

**Ppos-erp-core** breaks the paradigm of fragile, single-purpose Point of Sale systems. It is an **Enterprise Resource Planning (ERP) platform** driven by a **Dynamic Module Registry** and **Granular Feature Flags**. 

A tenant can operate as a single cozy café today, scale into a multi-branch restaurant chain with Kitchen Display Systems (KDS) tomorrow, and expand into an omnichannel retail conglomerate with bonded warehouses and double-entry general ledgers—**without changing a single line of backend code.**

```
                                  [ TENANT ORGANIZATIONS ]
                                             │
               ┌─────────────────────────────┼─────────────────────────────┐
               ▼                             ▼                             ▼
       [ Restaurant Chain ]           [ Retail Network ]          [ Healthcare / Clinic ]
         • Floor & Table Maps           • Barcode Engine            • Patient Appointments
         • QR Code Ordering             • Tiered Price Lists        • Staff Shift Scheduling
         • Kitchen Tickets (KOT)        • Multi-Unit Conversions    • Service Catalog Billing
         • KDS Real-time Queue          • Coupon Promos Engine      • Prescription Inventory
         • BOM Recipe Deduction         • Customer Loyalty Tiers    • Credit Ledger
               │                             │                             │
               └─────────────────────────────┼─────────────────────────────┘
                                             │
                                             ▼
                 ┌────────────────────────────────────────────────────────┐
                 │       CORE FOUNDATION & UNIFIED INFRASTRUCTURE         │
                 │  • Multi-Store / Multi-Branch Context (X-Store-Id)     │
                 │  • Dynamic Module & Feature Flagging Gating            │
                 │  • Role-Based Access Control (RBAC 2.0 Multi-Role)     │
                 │  • Double-Entry General Ledger (COA & Journal Entries) │
                 │  • 3-Way Procurement Matching (Requisition-GRN-Invoice)│
                 │  • Warehouse Network & Stocktake Auditing              │
                 │  • Outbound Webhook Engine (HMAC-SHA256 Signed)        │
                 └────────────────────────────────────────────────────────┘
```

---

## 🏗️ 10-Phase Engineering Roadmap (Complete Delivery)

This platform represents thousands of hours of meticulous architecture, designed and executed through ten sequential, fully-tested phases:

<details open>
<summary><b>Click to expand full details of all 10 Engineering Phases</b></summary>

### 🔹 Phase 0: Base Architecture & Dynamic Module Registry
- **Multi-Tenant Foundation:** Soft tenant separation via Eloquent Global Scope (`BelongsToTenant`).
- **Dynamic Module Engine:** Tenants toggle modules (`pos`, `inventory`, `kitchen`, `finance`, etc.) on the fly.
- **Granular Feature Registry:** Fine-grained feature switches (`batch_tracking`, `split_payment`, `qr_ordering`).
- **RBAC 2.0:** Role-based access control with module-scoped permissions and custom tenant roles.
- **Multi-Store Context:** Seamless branch switching via `X-Store-Id` header without session pollution.

### 🔹 Phase 1: Advanced Catalog & Product Engineering
- **Complex Product Variants:** Support for multiple dimensions (size, color, material) with separate SKUs and barcodes.
- **Unit Conversion Engine:** Multi-level unit hierarchies (e.g., 1 Box = 24 Packs = 144 Pieces) with exact conversion math.
- **Tiered Price Lists:** Separate price tiers for retail, wholesale, VIP, and promotional customer groups.
- **Hierarchical Categories:** Infinite nested category trees with automated slug and path indexing.
- **Bulk CSV / Excel Operations:** High-performance asynchronous import and export engine.

### 🔹 Phase 2: Enterprise Inventory & Multi-Warehouse Logistics
- **Warehouse Separation:** Physical separation between centralized distribution warehouses and storefront stocks.
- **Batch / Lot & Expiry Tracking:** Strict pharmaceutical and food-safety traceability with expiration alerts.
- **Stock Valuation Engine:** Automated inventory valuation supporting FIFO (First-In, First-Out) and Moving Average.
- **Formal Stocktake Workflow:** Draft count ➔ blind counts ➔ variance reconciliation ➔ manager approval ➔ inventory adjustment.
- **Inter-Store Transfer Requests:** Approval matrix for requisition, in-transit dispatch, and destination receipt verification.

### 🔹 Phase 3: CRM & 3-Way Procurement Matching
- **Customer Credit Accounts:** Ledger-backed credit limits, receivable tracking, and debt settlement.
- **Multi-Tier Loyalty Points:** Automated point accrual algorithms, milestone redemption, and point expiration rules.
- **Supplier Rating Matrix:** Quantitative evaluation based on delivery punctuality, pricing, and quality conformity.
- **Procurement Cycle:** Purchase Requisition ➔ Multi-Tier Approval ➔ Purchase Order (PO).
- **3-Way Invoice Matching:** Automated reconciliation matching PO items, Goods Receipt Notes (GRN), and Supplier Invoices.
- **Smart Auto-Reorder:** Algorithmic PO generation triggered when stock drops below safety buffers.

### 🔹 Phase 4: Extended POS & Checkout Operations
- **Atomic Checkout Pipeline:** Sub-second transactional checkout with zero race conditions on inventory locks.
- **Park / Hold Cart Management:** Suspend active orders with arbitrary payloads and recall them across any terminal.
- **Discount Presets Engine:** Percentage, flat amount, minimum cart threshold, and cashier-override limitations.
- **Itemized Refund Pipeline:** Partial and full return processing with automatic stock restock and journal reversal.
- **Thermal Receipt Engine:** Fully customizable receipt layouts, header/footer branding, tax summaries, and QR codes.

### 🔹 Phase 5: Payment Gateways & Cashier Reconciliation
- **Payment Gateway Architecture:** Abstracted payment drivers supporting QRIS, Virtual Accounts, and Credit Cards.
- **Idempotent Payment Handling:** Cryptographic idempotency keys preventing double-charging on network dropouts.
- **Cash Drawer Audit (Shift Management):** Blind cash counts, opening float, mid-shift cash drops, and variance reporting.
- **Settlement & Reconciliation:** Automatic gateway fee deduction and automated bank payout reconciliation.

### 🔹 Phase 6: Double-Entry Financial Accounting (General Ledger)
- **Standard Chart of Accounts (COA):** Structured tree covering Assets, Liabilities, Equity, Revenues, and Expenses.
- **Automated Journal Entries:** Every operational event (sale, return, purchase, adjustment) triggers balanced debit/credit lines.
- **Fiscal Period Controls:** Strict accounting lockouts preventing backdated modifications to closed financial periods.
- **Live Financial Reports:** Sub-second generation of Trial Balance, Profit & Loss (P&L), and Balance Sheets.

### 🔹 Phase 7: Executive BI Dashboard & Snapshot Analytics
- **Customizable Widget Engine:** Drag-and-drop dashboard metrics customizable per user role.
- **Key Performance Indicators (KPIs):** Real-time calculation of GMV, average order value (AOV), inventory turnover, and gross margins.
- **Historical Snapshot Engine:** Pre-aggregated temporal snapshots for instant reporting over years of historical data.

### 🔹 Phase 8: Industry-Specific Vertical Engines
- **8A — Restaurant & Café:** Table layout designer, dynamic QR table ordering, Kitchen Order Tickets (KOT), Kitchen Display System (KDS) status monitor, split-bill engine, and Recipe Bill of Materials (BOM) raw-material deduction.
- **8B — Modern Retail:** Promotional coupon campaigns, basket bundle rules, and tiered loyalty memberships.
- **8C — Service & Healthcare:** Appointment calendar booking, staff work shifts, and billable service catalogs.

### 🔹 Phase 9: External Integrations & Developer Ecosystem
- **Outbound Webhooks:** Event dispatcher publishing real-time events with HMAC-SHA256 signature verification.
- **Webhook Replay & Dead-Letter Queue:** Automatic exponential backoff retries with manual replay tooling.
- **External Integration API Keys:** Granular scoped API keys (`read`, `write`) with dedicated rate-limiting tiers.
- **OpenAPI 3.0 / Swagger Specification:** Auto-generated, comprehensive interactive API documentation.

### 🔹 Phase 10: Production Hardening, Security & Observability
- **Two-Factor Authentication (2FA):** RFC 6238 TOTP engine compatible with Google Authenticator, Authy, and 1Password.
- **Brute-Force Lockout Defense:** Progressive IP and account lockout mechanisms against credential stuffing.
- **Input Sanitization:** Deep recursive XSS and script stripping on all incoming HTTP payloads.
- **PDP Law Compliance (UU No. 27/2022):** Legal compliance including automated personal data export and Right-to-Erasure anonymization.
- **Automated Disaster Recovery:** Scheduled backup routines and documented disaster recovery runbooks.

</details>

---

## 🏛️ Comprehensive Architecture Deep-Dive

```
                                  [ WEB CLIENT / MOBILE / TABLET ]
                                                 │
                                                 │ HTTPS / JSON
                                                 │ Header: Authorization: Bearer <Sanctum>
                                                 │ Header: X-Store-Id: <UUID>
                                                 ▼
                             ┌───────────────────────────────────────┐
                             │       Nginx Reverse Proxy & SSL       │
                             └───────────────────┬───────────────────┘
                                                 │
                                                 ▼
┌─────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                   LARAVEL 13 REST API CORE                                      │
│                                                                                                 │
│  [ HTTP PIPELINE & SECURITY MIDDLEWARES ]                                                       │
│  ├── RateLimiters (auth: 5/min, api: 60/min, integration: custom)                               │
│  ├── XssSanitizer (Recursive payload script stripper)                                           │
│  ├── CheckAccountLockout (Brute-force security guard)                                           │
│  ├── Sanctum Authenticate (Bearer token resolver)                                               │
│  ├── RequireTwoFactor (TOTP session validation)                                                 │
│  ├── CheckModule (Dynamic module gating)                                                        │
│  ├── CheckFeature (Granular feature gating)                                                     │
│  └── CheckPermission (RBAC 2.0 permission verification)                                         │
│                                                                                                 │
│  [ API CONTROLLERS LAYER — 63 Thin Controllers ]                                                │
│  ├── Auth & Security (5)    ├── Inventory & Warehouse (7) ├── Restaurant & Vertical (6)         │
│  ├── Core & Tenancy (5)     ├── Procurement & Supply (8)  ├── Retail & Appointments (3)         │
│  ├── Products & Units (5)   ├── Sales & Cash Drawer (7)   ├── Webhooks & Integrations (6)       │
│  └── Accounting & Finance (6)                                                                   │
│                                                                                                 │
│  [ BUSINESS SERVICE LAYER — 53 Pure Domain Services ]                                           │
│  ├── SaleService (Atomic checkout, inventory lock)   ├── RecipeService (BOM ingredient deduction)│
│  ├── AccountingService (Double-entry journal engine) ├── WebhookService (HMAC-SHA256 dispatch)  │
│  ├── InventoryService (Valuation, stock movements)   ├── AppointmentService (Calendar booking)  │
│  ├── PurchaseService (3-way matching, GRN verify)    └── TwoFactorService (TOTP RFC 6238)       │
│                                                                                                 │
│  [ ELOQUENT ORM & DATA ISOLATION LAYER — 106 Models ]                                           │
│  └── BelongsToTenant Global Scope (Auto: WHERE tenant_id = current_tenant)                      │
└────────────────────────────────────────────────┬────────────────────────────────────────────────┘
                                                 │
                                ┌────────────────┴────────────────┐
                                ▼                                 ▼
                     ┌─────────────────────┐           ┌─────────────────────┐
                     │      MySQL 8.0      │           │       Redis 7       │
                     │  96 Indexed Tables  │           │ Cache, Queue & Lock │
                     └─────────────────────┘           └─────────────────────┘
```

---

## 💻 Tech Stack Specification

### Frontend Architecture
```
frontend/
├── src/
│   ├── components/            # Atomic reusable UI components (Tailwind + Lucide)
│   │   ├── pos/               # Cashier terminal: Cart, VariantModal, RefundModal, Receipt
│   │   ├── inventory/         # Stock indicators, batch status, transfer modal
│   │   └── ui/                # Inputs, buttons, dialogs, dropdowns, tables
│   ├── layouts/               # DashboardLayout (dynamic sidebar), AuthLayout
│   ├── pages/                 # 30+ Route views
│   │   ├── auth/              # Login, Register, 2FA, PIN, QR authentication
│   │   ├── restaurant/        # Table layout designer, KDS queue
│   │   ├── purchasing/        # Requisitions, GRN, Invoices, Auto-Reorder
│   │   ├── crm/               # Customer Loyalty, Credit accounts
│   │   ├── integrations/      # Webhook dashboard, API Keys
│   │   └── POSPage.tsx        # High-performance touch & barcode cashier screen
│   ├── router/                # React Router 7 with ProtectedRoute module/permission gates
│   ├── services/              # 25+ Axios API service wrappers
│   ├── stores/                # Zustand: auth, cart (active register state), module-config
│   └── types/                 # Comprehensive TypeScript interfaces
└── e2e/                       # 12 Playwright End-to-End browser test suites
```

### Backend Architecture
```
backend/
├── app/
│   ├── Console/Commands/      # Database backup, cleanup, and scheduled jobs
│   ├── Http/
│   │   ├── Controllers/       # 63 REST API Controllers
│   │   └── Middleware/        # 9 Custom Middleware security and gating layers
│   ├── Models/                # 106 Domain Models with BelongsToTenant scope
│   ├── Services/              # 53 Business Logic Services (Single-Responsibility)
│   └── Traits/                # BelongsToTenant global isolation trait
├── database/
│   ├── migrations/            # 96 Database Migrations (Phases 0 through 10)
│   └── seeders/               # ModuleSeeder, FeatureSeeder, BusinessTypeSeeder, E2ESeeder
└── tests/
    ├── Feature/               # 75+ Feature & Integration test suites
    └── Unit/                  # Accounting arithmetic and unit conversion tests
```

---

## ⚡ High-Frequency POS Checkout Engine

The point-of-sale checkout engine in [`SaleService.php`](backend/app/Services/SaleService.php) represents state-of-the-art transaction safety:

1. **Deterministic Concurrency Control:** Executes inside a managed database transaction with row-level locks (`lockForUpdate`) on all referenced inventory items to prevent negative-stock race conditions during flash sales.
2. **Idempotent Network Defense:** Every checkout request accepts a client-generated `idempotency_key`. Retried requests from dropped internet connections return the original completed receipt without duplicate charges or duplicate stock deductions.
3. **Automated Bill of Materials (BOM) Resolution:** If an ordered item is a composite dish (e.g., *Cheeseburger*), the engine transparently computes and deducts the exact recipe ingredients (bun, patty, cheese slice) from raw inventory.
4. **Synchronous Double-Entry Accounting:** In the same atomic transaction, standard journal entries are generated:
   - **Debit:** Cash / Gateway Receivable Account
   - **Credit:** Sales Revenue Account
   - **Debit:** Cost of Goods Sold (COGS) Account
   - **Credit:** Merchandise Inventory Account

---

## 🔐 Enterprise Security, Hardening & Compliance

- **Authentication:** Token-based stateless authentication via Laravel Sanctum, enhanced with **RFC 6238 TOTP 2FA**.
- **Brute-Force Mitigation:** `CheckAccountLockout` middleware tracks consecutive failed logins, imposing exponential lockouts and alerting system administrators.
- **Cross-Site Scripting (XSS) Sanitization:** `XssSanitizer` middleware sanitizes all incoming string and array payloads against script injection and prototype pollution.
- **Cryptographic Webhooks:** Outbound webhooks are signed using `HMAC-SHA256` tokens with timestamp headers, enabling consumers to prevent replay attacks.
- **PDP Law (UU No. 27/2022) Compliance:** Dedicated account endpoints (`GET /api/v1/account/export` and `DELETE /api/v1/account`) support user personal data export and right-to-erasure GDPR/PDP mandates.

---

## 🚀 Quick Start & Installation

### Option 1: Complete Stack via Docker (Recommended)

```bash
# 1. Clone repository
git clone https://github.com/Moh-Shafi/Ppos-erp-core.git
cd Ppos-erp-core

# 2. Launch full infrastructure (Frontend, Backend, MySQL, Redis)
docker compose up -d --build
```

| Service | Container | Access URL |
|:---|:---|:---|
| **Frontend Web App** | `pos_saas_frontend` | [http://localhost:5173](http://localhost:5173) |
| **Laravel REST API** | `pos_saas_backend` | [http://localhost:8000](http://localhost:8000) |
| **MySQL 8.0 Database** | `pos_saas_mysql` | `localhost:3310` (`pos` / `pos_secret`) |
| **Redis 7 Cache** | `pos_saas_redis` | `localhost:6380` |

---

### Option 2: Local Manual Environment

#### 1. Backend Setup
```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8000
```

#### 2. Frontend Setup
```bash
cd frontend
npm install
npm run dev
```

---

## 🔑 Pre-Seeded Demonstration Accounts

Run `php artisan db:seed --class=E2ESeeder` to populate test tenant environments:

| Persona | Email | Password | Assigned Scope & Role |
|:---|:---|:---|:---|
| **Tenant Owner** | `e2e.owner@test.com` | `password123` | Complete administrative tenant authority |
| **Cashier** | `e2e.cashier@test.com` | `password123` | POS terminal, cart hold, receipt printing |
| **Chief Accountant** | `e2e.accountant@test.com` | `password123` | Chart of Accounts, Journal entries, P&L reports |
| **Store Staff** | `e2e.staff@test.com` | `password123` | Inventory viewing and stock movement transfers |

---

## 🧪 Comprehensive Testing Suite

Quality assurance is validated through 3 distinct automated testing layers:

```bash
# 1. Backend Unit, Feature & Concurrency Tests (75+ Test Suites)
cd backend
php artisan test

# 2. Browser End-to-End Automation (Playwright)
cd frontend
npm run test:e2e

# 3. High-Concurrency POS Load Simulation (k6)
cd tests/load
node load_test.js
```

---

## 📄 License & Intellectual Property

This project is open-source software licensed under the **MIT License**. See the [LICENSE](LICENSE) file for complete details.

---

<div align="center">
  <b>Ppos-erp-core</b> — Designed and engineered for global multi-tenant scalability.<br/>
  Crafted with precision, passion, and architectural excellence.
</div>
