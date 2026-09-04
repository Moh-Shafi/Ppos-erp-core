<div align="center">

# 🍽️ 🛒 Ppos-erp-core
### Modern Cloud POS & Multi-Tenant Modular ERP Platform

A next-generation, multi-tenant SaaS Point of Sale (POS) and Enterprise Resource Planning (ERP) platform built with **React 19**, **TypeScript**, **Vite**, **Tailwind CSS**, **Laravel 13**, **MySQL 8.0**, and **Redis**. Designed for extreme adaptability across 12+ business verticals—from restaurants and cafés to retail chains, pharmacies, grocery stores, salons, and service clinics.

[![React](https://img.shields.io/badge/React-19-61DAFB?style=for-the-badge&logo=react&logoColor=white)](https://react.dev)
[![TypeScript](https://img.shields.io/badge/TypeScript-6-3178C6?style=for-the-badge&logo=typescript&logoColor=white)](https://www.typescriptlang.org)
[![Vite](https://img.shields.io/badge/Vite-8-646CFF?style=for-the-badge&logo=vite&logoColor=white)](https://vite.dev)
[![TailwindCSS](https://img.shields.io/badge/Tailwind-4-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com)
[![Redis](https://img.shields.io/badge/Redis-7-DC382D?style=for-the-badge&logo=redis&logoColor=white)](https://redis.io)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=for-the-badge&logo=docker&logoColor=white)](https://www.docker.com)
[![Playwright](https://img.shields.io/badge/Playwright-E2E-45BA4B?style=for-the-badge&logo=playwright&logoColor=white)](https://playwright.dev)

</div>

---

## 🌟 Key Highlights & Core Capabilities

- 🏢 **Strict Multi-Tenancy:** Soft multi-tenant data isolation enforced through Eloquent global scopes (`BelongsToTenant`) and multi-store context via `X-Store-Id`.
- 🧩 **Dynamic Module & Feature Registry:** Business types automatically provision default modules. Owners can toggle modules (POS, Inventory, Kitchen/KDS, Accounting, CRM, etc.) and granular features at runtime.
- ⚡ **Atomic High-Speed POS:** Barcode scanning, item variants, composite modifiers, discount presets, park/hold cart sales, split bills, and thermal receipt generation.
- 💳 **Integrated & Idempotent Payments:** Cash drawer session audits, QRIS dynamic payments, card terminal tracking, and multi-gateway webhooks.
- 📦 **Enterprise Warehouse & Inventory:** Multi-warehouse stock tracking, batch/lot and expiry date tracking, categorized stock adjustments, and multi-step stocktake count cycles.
- 📑 **Procurement & 3-Way Matching:** Purchase requisitions, approval matrices, Goods Receipt Notes (GRN), and automated supplier invoice matching.
- 📊 **Double-Entry General Ledger:** Standard Chart of Accounts (COA), real-time debit/credit journal entries, fiscal period closings, trial balance, and automated P&L generation.
- 🍽️ **Restaurant Vertical Engine:** Floor & table management, QR-code ordering, Kitchen Order Tickets (KOT), Kitchen Display System (KDS) queue, and automated Recipe Bill of Materials (BOM) stock deduction.
- 🛡️ **Enterprise Security & PDP Compliance:** 2FA TOTP authentication, brute-force lockout defenses, rate-limiting, comprehensive audit trails, and Indonesian PDP Law (UU No. 27/2022) data export & erasure rights.

---

## 🏛️ Architecture Overview

```

                      Ppos-erp-core SaaS Platform                      

   Frontend (Vite SPA)                      Backend Core (Laravel 13)       
   localhost:5173                            localhost:8000                 
                                                                            
  React 19 + TypeScript 6                  RESTful API Core (PHP 8.4)       
  Tailwind CSS 4 + shadcn/ui               MySQL 8.0 (96 Migration Tables)  
  Zustand Stores (Auth, Cart, Config)      Redis 7 (Cache, Sessions, Queue) 
  React Router 7 (Module Guards)           Laravel Sanctum Token Security   
  Playwright E2E Test Suite                Docker Compose Multi-Container   

                                    │
                                    ▼
       ┌────────────────────────────────────────────────────────┐
       │             Dynamic Module & Security Engine           │
       │  • CheckModule Middleware    • CheckFeature Middleware │
       │  • RBAC 2.0 (Role/Permission)• BelongsToTenant Scope   │
       └────────────────────────────────────────────────────────┘
                                    │
    ┌───────────────┬───────────────┼───────────────┬───────────────┐
    ▼               ▼               ▼               ▼               ▼
┌─────────┐   ┌───────────┐   ┌───────────┐   ┌───────────┐   ┌───────────┐
│   POS   │   │ Inventory │   │Purchasing │   │Accounting │   │ Verticals │
│ Checkout│   │ Warehouses│   │Requisition│   │GL / Ledger│   │Restaurant │
│ Hold/Pay│   │ Batches   │   │GRN / 3-Way│   │Financials │   │Retail/Appt│
└─────────┘   └───────────┘   └───────────┘   └───────────┘   └───────────┘
```

---

## 🧰 Tech Stack — Full Detail

### Frontend Layer
| Technology | Version | Description & Role |
|:---|:---|:---|
| **React** | `19.2.8` | Core UI engine leveraging functional components and reactive state |
| **TypeScript** | `6.0.2` | Complete type-safety across models, services, forms, and responses |
| **Vite** | `8.2.0` | Ultra-fast build tool and developer hot-module replacement (HMR) |
| **Tailwind CSS** | `4.3.3` | Utility-first CSS framework for responsive layout design |
| **Zustand** | `5.0.0` | High-performance state stores (`auth`, `cart`, `module-config`) |
| **React Router** | `7.0.0` | Declarative routing with permission and module-gated protected routes |
| **Axios** | `1.7.0` | HTTP client configured with bearer auth and store-switching interceptors |
| **Playwright** | `1.62.1` | Automated end-to-end (E2E) browser flow test suites |
| **oxlint** | `1.75.0` | High-performance static analysis and linter |

### Backend Core
| Technology | Version | Description & Role |
|:---|:---|:---|
| **Laravel** | `13.8` | Enterprise PHP framework implementing Clean Service Architecture |
| **PHP** | `8.4` | Modern PHP with strict types, match expressions, and attributes |
| **MySQL** | `8.0` | Primary relational database with 96 migrations and indexed foreign keys |
| **Redis** | `7.0` | In-memory key-value store for queues, rate limiting, and caching |
| **Laravel Sanctum**| `4.3` | Secure stateless Bearer token authentication system |
| **PHPUnit** | `12.5.12` | Automated feature, integration, and unit testing (75+ test suites) |
| **k6 / Node.js** | - | Concurrent load testing for checkout and inventory concurrency |

### Infrastructure & DevOps
| Technology | Usage |
|:---|:---|
| **Docker & Docker Compose** | Multi-container environment managing MySQL, Redis, Backend, and Frontend |
| **Nginx** | High-performance reverse proxy and SSL termination config ready |
| **Supervisor** | Background daemon process management for queues and scheduled jobs |

---

## 📂 Project Structure

```
Ppos-erp-core/
├── backend/                             # Laravel API Core
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/             # 63 REST API Controllers
│   │   │   └── Middleware/              # 9 Custom Middlewares (RBAC, Tenant, 2FA, XSS)
│   │   ├── Models/                      # 106 Eloquent Data Models
│   │   ├── Services/                    # 53 Business Logic Services (Sale, Inventory, Accounting)
│   │   ├── Traits/                      # BelongsToTenant data isolation trait
│   │   └── Providers/                   # App and event service providers
│   ├── database/
│   │   ├── migrations/                  # 96 Database Migrations (Phases 0 - 10)
│   │   └── seeders/                     # Business Types, Modules, RBAC, and Demo Seeders
│   ├── routes/
│   │   └── api.php                      # 1000+ lines of comprehensive versioned API routes
│   └── tests/
│       ├── Feature/                     # 75+ Feature test suites covering every module
│       └── Unit/                        # Core unit and financial balance tests
│
├── frontend/                            # React + TypeScript SPA
│   ├── src/
│   │   ├── components/                  # UI, POS, Inventory, and Modals
│   │   ├── layouts/                     # DashboardLayout, AuthLayout
│   │   ├── lib/                         # Axios client, utilities
│   │   ├── pages/
│   │   │   ├── auth/                    # Login, Register, 2FA, PIN, QR login
│   │   │   ├── crm/                     # Loyalty Points, Customer Credit
│   │   │   ├── integrations/            # Webhooks, API Keys, Marketplace
│   │   │   ├── purchasing/              # Requisitions, GRN, 3-Way Invoices
│   │   │   ├── restaurant/              # Floor Tables, KDS, Kitchen Tickets
│   │   │   ├── retail/                  # Promotions, Coupons, Gift Cards
│   │   │   ├── service/                 # Appointments, Staff Schedules
│   │   │   ├── settings/                # Stores, Receipts, Security, Audit Logs
│   │   │   └── POSPage.tsx              # Cashier Terminal interface
│   │   ├── router/                      # ProtectedRoute with module-level authorization
│   │   ├── services/                    # 25+ Frontend API client wrappers
│   │   └── stores/                      # Zustand state managers
│   └── e2e/                             # Playwright browser end-to-end tests
│
├── docker/                              # Nginx, PHP, and Supervisor configs
├── docs/                                # Complete Architecture & Phase Reports (0 - 10)
│   ├── PDR/                             # Master PDR, ERP Architecture, Roadmap
│   ├── phase-reports/                   # Implementation reports per phase
│   └── phases/                          # Detailed architecture, security, and flow specs
├── tests/
│   └── load/                            # k6 load testing scripts
└── docker-compose.yml                   # Container orchestration config
```

---

## 🏢 Business Type Templates

When a tenant registers, they choose a business template which instantly toggles their starter module stack:

| Business Type | Default Enabled Modules |
|:---|:---|
| **Restaurant** | POS, Inventory, Purchasing, Customers, Tables, Kitchen/KOT, KDS, Recipes/BOM, Finance |
| **Café** | POS, Inventory, Purchasing, Customers, Menu Modifiers, Kitchen, Finance |
| **Retail Shop** | POS, Inventory, Purchasing, Customers, Barcode Engine, Price Lists, Finance |
| **Grocery** | POS, Inventory, Purchasing, Suppliers, Barcodes, Batch/Expiry, Finance |
| **Pharmacy** | POS, Inventory, Purchasing, Batch/Lot Tracking, Expiry Management, Finance |
| **Clinic / Medical**| Customers/Patients, Appointments, Invoices, Service Catalog, Inventory, Finance |
| **Salon & Spa** | Customers, Appointments, Staff Schedules, Service POS, Inventory, Finance |
| **Wholesale** | Sales, Purchasing, Warehouses, Multi-Units, Tiered Price Lists, Credit Limits, Finance |
| **General ERP** | POS, Inventory, Purchasing, Customers, Suppliers, Financial Reports |

---

## 🗄️ Database Architecture (Key Domains)

```sql
-- Multi-Tenancy & Governance
tenants                 → id · name · slug · plan_id · is_active
users                   → id · tenant_id · name · email · password · role_id · store_id
roles / permissions     → id · name · slug · module_id · description
modules / features      → id · name · slug · is_core · dependencies

-- Catalog & Products
categories              → id · tenant_id · parent_id · name · slug
products                → id · tenant_id · category_id · sku · barcode · name · cost_price · sell_price
product_variants        → id · product_id · sku · barcode · price · attributes
units / conversions     → id · tenant_id · name · symbol · base_unit_id · conversion_rate
price_lists / items     → id · tenant_id · name · product_id · price · min_quantity

-- Inventory & Logistics
warehouses              → id · tenant_id · store_id · code · name
inventories             → id · tenant_id · store_id · product_id · quantity · min_stock
stock_batches           → id · tenant_id · product_id · batch_number · expiry_date · quantity
inventory_movements     → id · tenant_id · store_id · product_id · type · quantity · before_qty · after_qty
stocktake_sessions      → id · tenant_id · store_id · status · counted_items · variances
transfer_requests       → id · tenant_id · source_store_id · destination_store_id · status

-- Procurement (3-Way Matching)
suppliers               → id · tenant_id · name · code · email · phone · rating
purchases / items       → id · tenant_id · supplier_id · status · po_number · grand_total
goods_receipt_notes     → id · tenant_id · purchase_id · grn_number · status · received_at
supplier_invoices       → id · tenant_id · purchase_id · grn_id · invoice_number · matched_status

-- Sales & Cashiering
sales / sale_items      → id · tenant_id · store_id · customer_id · sale_number · status · total_amount
payments                → id · tenant_id · sale_id · payment_method · amount · idempotency_key
held_sales              → id · tenant_id · store_id · hold_reference · cart_payload
cash_drawer_sessions    → id · tenant_id · store_id · user_id · opening_cash · closing_cash · status

-- Double-Entry Accounting
accounts                → id · tenant_id · code · name · type (asset|liability|equity|revenue|expense)
fiscal_periods          → id · tenant_id · name · start_date · end_date · is_closed
journal_entries / lines → id · tenant_id · period_id · entry_number · account_id · debit · credit

-- Vertical Extensions
restaurant_tables       → id · tenant_id · store_id · area_id · name · capacity · status · qr_code
kot_headers / kot_items → id · tenant_id · sale_id · table_id · order_number · station · status
recipes / ingredients   → id · tenant_id · product_id · ingredient_product_id · quantity_required
appointments            → id · tenant_id · store_id · customer_id · staff_id · scheduled_at · status
```

---

## 🚀 Quick Start & Installation

### Prerequisites
- [Docker Desktop](https://www.docker.com/products/docker-desktop) installed and running
- [Node.js](https://nodejs.org) (v20+ recommended)
- [PHP](https://www.php.net) (v8.3+ if running locally without Docker)
- [Composer](https://getcomposer.org) (v2+)

---

### Option 1: Quick Start via Docker (Recommended)

1. **Clone the repository:**
   ```bash
   git clone https://github.com/Moh-Shafi/Ppos-erp-core.git
   cd Ppos-erp-core
   ```

2. **Spin up all containers:**
   ```bash
   docker compose up -d --build
   ```

3. **Verify container health:**
   | Service | Container Name | Port / URL |
   |:---|:---|:---|
   | **Frontend Web App** | `pos_saas_frontend` | [http://localhost:5173](http://localhost:5173) |
   | **Laravel REST API** | `pos_saas_backend` | [http://localhost:8000](http://localhost:8000) |
   | **MySQL Database** | `pos_saas_mysql` | `localhost:3310` |
   | **Redis Cache** | `pos_saas_redis` | `localhost:6380` |

---

### Option 2: Local Manual Setup

#### Backend Setup
```bash
cd backend
cp .env.example .env

# Install dependencies
composer install

# Generate application encryption key
php artisan key:generate

# Run database migrations and seed default data
php artisan migrate --seed

# Start backend server
php artisan serve --port=8000
```

#### Frontend Setup
```bash
cd frontend

# Install npm packages
npm install

# Start development server with HMR
npm run dev
```

---

## 🔑 Demo & Test Accounts

Run the database seeders to populate initial test accounts (`php artisan db:seed --class=E2ESeeder`):

| Role | Email Address | Password | Permissions & Scope |
|:---|:---|:---|:---|
| **System Owner** | `e2e.owner@test.com` | `password123` | Full administrative tenant access |
| **Cashier** | `e2e.cashier@test.com` | `password123` | POS terminal, checkout, held sales |
| **Accountant** | `e2e.accountant@test.com` | `password123` | General ledger, journal entries, P&L reports |
| **Store Staff** | `e2e.staff@test.com` | `password123` | Inventory viewing and stock movement |

---

## 📡 Essential REST API Endpoints

All API endpoints are prefixed with `/api/v1`.

### 🔐 Authentication & Session
```http
POST   /api/v1/auth/register            Register user, tenant, and business type
POST   /api/v1/auth/login               Authenticate and retrieve Bearer token
POST   /api/v1/auth/login-2fa           Verify 2FA TOTP code
GET    /api/v1/auth/me                  Get user profile, enabled modules & permissions
POST   /api/v1/auth/logout              Invalidate active Sanctum token
```

### 🛒 Point of Sale & Cashier
```http
POST   /api/v1/sales/checkout           Execute atomic sale transaction & deduct stock
GET    /api/v1/sales                    List tenant sales history with filters
POST   /api/v1/sales/{id}/refunds       Process partial or full sale refund
GET    /api/v1/held-sales               List held/parked shopping carts
POST   /api/v1/cash-drawer/open         Open cash drawer shift
POST   /api/v1/cash-drawer/{id}/close   Reconcile and close cash drawer session
```

### 📦 Inventory & Warehousing
```http
GET    /api/v1/inventory                View stock levels across stores and warehouses
POST   /api/v1/inventory/adjust         Record categorized stock adjustment
POST   /api/v1/inventory/transfer       Direct stock transfer between locations
POST   /api/v1/stocktake                Initiate stock audit counting cycle
POST   /api/v1/transfer-requests        Create inter-branch stock requisition
```

### 🍽️ Restaurant & Kitchen Operations
```http
GET    /api/v1/tables                   View interactive table floor layout & status
POST   /api/v1/tables/{id}/qr-code      Generate dynamic table QR ordering code
GET    /api/v1/kds/queue                Kitchen Display System real-time ticket queue
POST   /api/v1/kot/{saleId}/generate    Generate Kitchen Order Ticket (KOT)
POST   /api/v1/sales/{id}/split         Split bill by item or guest seat
```

### 📒 Finance & Accounting
```http
GET    /api/v1/finance/accounts         List Chart of Accounts (COA)
POST   /api/v1/finance/journal-entries  Create double-entry debit/credit journal entry
GET    /api/v1/finance/reports/trial-balance
GET    /api/v1/finance/reports/profit-loss
GET    /api/v1/finance/reports/balance-sheet
```

---

## 🧪 Testing & Quality Assurance

The codebase maintains strict automated test coverage across all architectural layers:

```bash
# 1. Run all backend unit and feature tests
cd backend
php artisan test

# 2. Run end-to-end browser tests via Playwright
cd frontend
npm run test:e2e

# 3. Run frontend code linting
npm run lint

# 4. Run concurrent load tests
cd tests/load
node load_test.js
```

---

## 🔒 Security & Privacy Compliance

- **Rate Limiting:** Granular throttling on authentication, standard API endpoints, and webhook listeners.
- **XSS Protection:** Automatic input stripping and sanitized response outputs.
- **Account Protection:** Progressive lockout timers following failed password attempts.
- **Audit Logging:** Immutable audit logs capturing user actions, IP addresses, and state changes.
- **PDP Compliance:** Compliant with Indonesia Personal Data Protection Law (UU PDP No. 27/2022), providing end-to-end data portability exports and right-to-erasure workflows.

---

## 📄 License

This project is licensed under the **MIT License**. See the [LICENSE](LICENSE) file for more information.

---

<div align="center">
Built with ❤️ for multi-business SaaS scalability.
</div>
