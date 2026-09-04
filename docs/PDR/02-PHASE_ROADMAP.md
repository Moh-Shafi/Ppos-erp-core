# ERP PHASE ROADMAP

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-11  
**Depends On:** `00-MASTER_PDR.md`, `01-ERP_ARCHITECTURE.md`

---

## OVERVIEW

This roadmap replaces the previous phase plan. The current POS implementation (Phase 1–5) is **frozen** and serves as the reference codebase. New phases are numbered from 0 to align with the ERP transformation.

**Previous phases (frozen, git commit 8483f83):**
- Phase 1: Foundation (multi-tenant, auth, RBAC)
- Phase 2: Products + Categories
- Phase 3: Inventory per Store + Movements (v0.3.0)
- Phase 4: Customers + Suppliers + Purchasing + Returns (v0.4.0)
- Phase 5: POS / Kasir — Sales + Payments + E2E

**New ERP phases start below.**

---

## PHASE 0 — ERP ARCHITECTURE & PDR

**Goal:** Transform the foundation from single-purpose POS to multi-business ERP platform.

**Deliverables:**
- Business Types system (restaurant, cafe, retail, grocery, pharmacy, service, wholesale, general)
- Business Profile (1:1 with tenant)
- Module Registry (system-level module definitions)
- Feature Registry (system-level feature definitions)
- Tenant Modules (enabled modules per tenant)
- Tenant Features (enabled features per tenant)
- Business Type Module/Feature defaults
- Enhanced RBAC (module-scoped permissions, frontend RBAC)
- Registration flow redesign (business type → module defaults → dashboard)
- `GET /api/v1/me` enhanced with modules, features, permissions, stores
- Frontend module-aware navigation + permission-based UI
- Store switcher (multi-store context)
- Dashboard with real data (basic stats from existing tables)

**Database Changes:**
- New: `business_types`, `business_type_modules`, `business_type_features`, `business_profiles`, `modules`, `features`, `tenant_modules`, `tenant_features`
- Modified: `roles` (add tenant_id nullable for custom roles), `permissions` (add module_id FK), `user_roles` (replace single role_id with multi-role per store)
- Seeders: ModuleSeeder, FeatureSeeder, BusinessTypeSeeder

**Acceptance Criteria:**
- [ ] Registration with business type selection works
- [ ] Modules enabled/disabled per tenant
- [ ] Features toggled per tenant
- [ ] Frontend shows only enabled module nav items
- [ ] Frontend hides UI elements user lacks permission for
- [ ] `GET /api/v1/me` returns full module/feature/permission config
- [ ] Store switcher works (changes store context)
- [ ] Dashboard shows real stats (product count, sales count, stock count)
- [ ] All existing tests still pass (regression)
- [ ] New tests for module/feature/RBAC system
- [ ] E2E test: register → select business type → see correct modules → dashboard

**Dependencies:** None (this is the foundation)

---

## PHASE 1 — CATALOG & PRODUCT ENHANCEMENT

**Goal:** Upgrade product/catalog system for multi-business-type support.

**Deliverables:**
- Product variants (size, color, flavor, etc.) — optional per tenant
- Product units (case, box, pcs, kg, liter) with conversion factors
- Product images (multiple images per product)
- Price lists (multiple selling prices per product — retail, wholesale, member)
- Cost tracking (average cost, last cost, FIFO/LIFO — configurable)
- Barcode support (multiple barcodes per product)
- Category hierarchy (parent/child categories)
- Category module-scoped (only visible if catalog module enabled)
- Product import/export (CSV/Excel)
- Low stock threshold per product (override store minimum)

**Database Changes:**
- New: `product_variants`, `product_variant_values`, `product_images`, `price_lists`, `price_list_items`, `unit_conversions`
- Modified: `products` (add variant support fields, category hierarchy), `categories` (add parent_id)

**Acceptance Criteria:**
- [ ] Product variants CRUD works
- [ ] Price lists CRUD works
- [ ] Multiple barcodes per product
- [ ] Category hierarchy (parent/child)
- [ ] Product images upload + display
- [ ] CSV import/export
- [ ] All existing product tests pass (regression)
- [ ] New tests for variants, price lists, images, hierarchy

**Dependencies:** Phase 0

---

## PHASE 2 — INVENTORY ENHANCEMENT

**Goal:** Upgrade inventory for warehouse, batch tracking, and expiry.

**Deliverables:**
- Warehouse support (separate from stores — stores can draw from warehouse)
- Batch/lot tracking (feature-flagged — enabled per tenant)
- Expiry date tracking (feature-flagged)
- Stock valuation (FIFO, LIFO, Average — configurable per tenant)
- Minimum/maximum stock levels per product per store
- Stock adjustment reasons (categorized)
- Inventory count / stocktake module
- Transfer requests (approval workflow for inter-store transfers)
- Inventory reports (stock summary, movement history, valuation)

**Database Changes:**
- New: `warehouses`, `warehouse_stocks`, `stock_batches`, `stocktake_sessions`, `stocktake_items`, `transfer_requests`
- Modified: `inventories` (add batch_id nullable, expiry_date nullable), `inventory_movements` (add batch_id nullable)

**Acceptance Criteria:**
- [ ] Batch tracking works (when feature enabled)
- [ ] Expiry tracking works (when feature enabled)
- [ ] Stocktake process works (create → count → reconcile → post)
- [ ] Transfer request approval workflow
- [ ] Stock valuation report
- [ ] All existing inventory tests pass (regression)
- [ ] New tests for batch, expiry, stocktake, transfer requests

**Dependencies:** Phase 1

---

## PHASE 3 — CRM & PURCHASING ENHANCEMENT

**Goal:** Upgrade customer/supplier/purchasing for ERP-level operations.

**Deliverables:**
- Customer loyalty points (feature-flagged)
- Customer credit limits + outstanding balance
- Customer price list assignment (customer-specific pricing)
- Supplier rating/evaluation
- Purchase requisition (request → approve → purchase order)
- Purchase approval workflow (configurable thresholds)
- Goods receipt note (GRN) — separate from purchase order
- Supplier invoice matching (3-way match: PO → GRN → Invoice)
- Auto-reorder (based on minimum stock levels)

**Database Changes:**
- New: `customer_loyalty_points`, `customer_balances`, `purchase_requisitions`, `purchase_requisition_items`, `goods_receipt_notes`, `grn_items`, `supplier_invoices`
- Modified: `customers` (add credit_limit, balance), `purchases` (add requisition_id, grn_id nullable)

**Acceptance Criteria:**
- [ ] Customer loyalty points accrual + redemption
- [ ] Customer credit limit enforcement at POS
- [ ] Purchase requisition → approval → PO flow
- [ ] GRN separate from PO
- [ ] 3-way matching (PO vs GRN vs Invoice)
- [ ] Auto-reorder report
- [ ] All existing customer/supplier/purchase tests pass (regression)
- [ ] New tests for all new features

**Dependencies:** Phase 2

---

## PHASE 4 — POS ENHANCEMENT (ERP Integration)

**Goal:** Connect existing POS to ERP module system + add advanced POS features.

**Deliverables:**
- POS connected to module system (pos module check)
- POS connected to feature flags (split_payment, hold_sale, refund)
- POS connected to store context (store switcher)
- POS connected to product variants
- POS connected to price lists (customer-specific pricing)
- POS connected to customer credit limits
- Hold/recall sale (feature-flagged)
- Refund processing (feature-flagged)
- POS discount presets (quick discount buttons)
- POS keyboard shortcuts
- Offline mode (future — not this phase)
- Receipt customization (per store settings)

**Database Changes:**
- New: `held_sales` (for hold/recall), `discount_presets`
- Modified: `sales` (add hold_status, held_at nullable), `stores` (add receipt_settings JSON)

**Acceptance Criteria:**
- [ ] POS respects module + feature flags
- [ ] Product variants selectable in POS
- [ ] Customer price list applied at checkout
- [ ] Customer credit limit enforced
- [ ] Hold/recall sale works
- [ ] Refund flow works (with inventory restore + journal reversal)
- [ ] Receipt format customizable per store
- [ ] All existing POS tests pass (regression)
- [ ] New tests for variants, price lists, hold/recall, refund

**Dependencies:** Phase 0, Phase 1, Phase 3

---

## PHASE 5 — PAYMENT INFRASTRUCTURE (Xendit xenPlatform)

**Goal:** Build payment infrastructure for the entire ERP, not just POS.

**Prerequisite:** Review actual Xendit xenPlatform API documentation before implementation.

**Deliverables:**
- Xendit xenPlatform integration (tenant sub-accounts)
- QRIS payment via Xendit (create charge → webhook → confirm)
- Payment webhook handling (idempotent, signature verified)
- Payment settlement tracking (T+1/T+2)
- Platform fee model (configurable per tenant/plan)
- Payment reconciliation (match Xendit reports with internal records)
- Cash payment enhancement (cash drawer management)
- Bank transfer payment (via Xendit virtual account)
- Card payment (via Xendit)
- Payment gateway account provisioning (onboarding flow for tenant)
- Payment dashboard (transaction history, settlement status)

**Database Changes:**
- New: `payment_gateway_accounts`, `payment_webhooks`, `payment_settlements`, `cash_drawer_sessions`
- Modified: `payments` (add gateway_transaction_id, gateway_status, gateway_response, settlement_amount, platform_fee, net_amount, settled_at)

**Acceptance Criteria:**
- [ ] Xendit sub-account provisioning works
- [ ] QRIS payment: create → customer pays → webhook → confirm
- [ ] Webhook idempotency (duplicate webhooks ignored)
- [ ] Webhook signature verification
- [ ] Settlement tracking + journal entries
- [ ] Platform fee deducted + recorded
- [ ] Reconciliation report
- [ ] Cash drawer session (open/close/reconcile)
- [ ] All existing payment tests pass (regression)
- [ ] New tests for gateway, webhook, settlement, reconciliation
- [ ] E2E test: QRIS checkout → webhook → confirmed

**Dependencies:** Phase 4, Xendit API documentation review

---

## PHASE 6 — FINANCE / ACCOUNTING

**Goal:** Implement double-entry accounting as the financial backbone of the ERP.

**Deliverables:**
- Chart of Accounts (seeded per tenant at registration, customizable)
- Journal Entries (double-entry, balanced, immutable once posted)
- Journal Lines (debit/credit per account)
- Automatic journal entry creation from:
  - Sale (checkout) → revenue + tax + COGS + inventory
  - Purchase (receive) → inventory + accounts payable
  - Payment (cash/gateway) → cash/bank + clearing
  - Expense → expense + cash/bank
  - Refund → reversing entry
- Cost centers (stores as cost centers)
- Fiscal periods (open/close/lock)
- Accounts Payable aging
- Accounts Receivable aging
- Trial balance
- Profit & Loss statement
- Balance sheet
- Cash flow statement
- Expense management (CRUD + approval workflow)
- Tax calculation (PPN 11% Indonesia — configurable)

**Database Changes:**
- New: `accounts`, `journal_entries`, `journal_lines`, `cost_centers`, `fiscal_periods`, `expenses`, `expense_categories`, `tax_rates`
- Modified: `sales`, `purchases`, `payments` (add journal_entry_id FK for traceability)

**Acceptance Criteria:**
- [ ] Every sale creates a balanced journal entry
- [ ] Every purchase receipt creates a balanced journal entry
- [ ] Every payment creates a balanced journal entry
- [ ] Every expense creates a balanced journal entry
- [ ] Refund creates reversing entry
- [ ] Trial balance is balanced (total debit = total credit)
- [ ] P&L, Balance Sheet, Cash Flow reports generate correctly
- [ ] AP/AR aging reports
- [ ] Fiscal period close prevents new entries
- [ ] All existing tests pass (regression)
- [ ] New tests for accounting (journal entries, reports, period close)
- [ ] E2E test: checkout → verify journal entry → P&L report

**Dependencies:** Phase 5

---

## PHASE 7 — REPORTS & ANALYTICS

**Goal:** Comprehensive reporting across all modules.

**Deliverables:**
- Dashboard widgets (real-time, module-aware, customizable)
- Sales reports (daily, weekly, monthly, custom range, per store, per cashier)
- Profit reports (gross profit, net profit, margin analysis)
- Inventory reports (stock summary, valuation, movement history, low stock)
- Purchasing reports (PO summary, supplier performance, spend analysis)
- Customer reports (top customers, purchase history, loyalty stats)
- Financial reports (P&L, balance sheet, cash flow — from Phase 6)
- Branch comparison reports (multi-store comparison)
- Product performance reports (best sellers, slow movers, profit per product)
- Payment reports (method breakdown, gateway settlement status)
- Export: PDF, Excel, CSV
- Scheduled reports (email delivery — future)
- Report filters: date range, store, category, product, customer, payment method

**Database Changes:**
- New: `report_configs` (saved report configurations), `dashboard_widgets` (user dashboard layout)

**Acceptance Criteria:**
- [ ] Dashboard shows real-time stats (sales today, stock alerts, recent transactions)
- [ ] All report types generate with correct data
- [ ] Reports filterable by date range, store, category
- [ ] Export to PDF and Excel works
- [ ] Multi-store comparison works
- [ ] All existing tests pass (regression)
- [ ] New tests for report generation (data accuracy)
- [ ] E2E test: view dashboard → generate sales report → export PDF

**Dependencies:** Phase 6

---

## PHASE 8 — BUSINESS-SPECIFIC MODULES

**Goal:** Implement modules that differentiate business types.

### 8A — Restaurant Modules

**Deliverables:**
- Table management (floor plan, table status, QR code per table)
- Reservations (booking, time slots, table assignment)
- Kitchen order tickets (KOT)
- Kitchen Display System (KDS) — order queue, status tracking
- Modifiers/add-ons (size, spice level, extra toppings)
- Recipes (ingredients per menu item → auto stock deduction)
- Ingredient mapping (product → ingredients → inventory items)
- Table orders (dine-in → table → order → kitchen → serve → pay)
- Bill splitting (per table, per person)

**Database Changes:**
- New: `tables`, `table_areas`, `reservations`, `kot_headers`, `kot_items`, `modifiers`, `modifier_options`, `recipes`, `recipe_ingredients`, `bill_splits`

### 8B — Retail Modules

**Deliverables:**
- Barcode scanning integration (camera + USB scanner)
- Quick sale (scan → add to cart → checkout)
- Price tag printing
- Promotional pricing (buy X get Y, percentage off, fixed amount off)
- Loyalty program (points per purchase, redemption)

**Database Changes:**
- New: `promotions`, `promotion_rules`, `loyalty_programs`, `loyalty_transactions`

### 8C — Service Business Modules

**Deliverables:**
- Appointment scheduling (calendar view, time slots)
- Staff assignment (service provider selection)
- Service catalog (services as products with duration)
- Invoice generation (from appointment → invoice → payment)
- Reminders (email/SMS — future)

**Database Changes:**
- New: `appointments`, `appointment_services`, `staff_schedules`, `service_catalog`

**Acceptance Criteria (all 8A/8B/8C):**
- [ ] Modules only visible if enabled for tenant
- [ ] Module-specific features work correctly
- [ ] Integration with POS (where applicable)
- [ ] Integration with inventory (recipes, auto-deduction)
- [ ] All existing tests pass (regression)
- [ ] New tests for each module
- [ ] E2E tests for primary flows

**Dependencies:** Phase 4, Phase 7

---

## PHASE 9 — SUBSCRIPTION & BILLING

**Goal:** Monetize the platform with subscription management.

**Deliverables:**
- Plan management (free, pro, enterprise — feature limits per plan)
- Subscription lifecycle (trial → active → past_due → cancelled)
- Trial period (configurable duration, auto-expire)
- Billing cycle (monthly, yearly)
- Payment via Xendit (subscription charges)
- Invoice generation (platform → tenant)
- Usage limits (stores, users, products, transactions per plan)
- Plan upgrade/downgrade
- Dunning (failed payment retry logic)
- Subscription dashboard (current plan, usage, billing history)

**Database Changes:**
- New: `plan_limits`, `plan_features`, `billing_invoices`, `billing_transactions`
- Modified: `subscriptions` (add billing_cycle, current_period_start/end, trial_ends_at)

**Acceptance Criteria:**
- [ ] Trial period starts on registration
- [ ] Plan limits enforced (stores, users, etc.)
- [ ] Subscription charge via Xendit works
- [ ] Invoice generated and emailed
- [ ] Plan upgrade/downgrade works
- [ ] Expired subscription restricts access
- [ ] All existing tests pass (regression)
- [ ] New tests for subscription lifecycle

**Dependencies:** Phase 5, Phase 6

---

## PHASE 10 — PRODUCTION, SECURITY & MONITORING

**Goal:** Production-ready platform with security hardening and monitoring.

**Deliverables:**
- API rate limiting (per endpoint, per user, per tenant)
- CORS configuration
- Input sanitization (XSS prevention)
- Password complexity rules
- Account lockout (brute force protection)
- 2FA (TOTP — feature-flagged per plan)
- Audit log (all CRUD operations, login/logout)
- Sentry error tracking
- Uptime monitoring
- Database backup automation
- CI/CD pipeline (GitHub Actions)
- Staging environment
- Production deployment guide
- API documentation (OpenAPI/Swagger)
- Performance optimization (query indexing, caching, eager loading)
- Load testing
- Security penetration testing
- GDPR/PDP compliance review (Indonesia PDP Law)

**Acceptance Criteria:**
- [ ] Rate limiting active
- [ ] 2FA works (when enabled)
- [ ] Audit log captures all operations
- [ ] Sentry integrated
- [ ] Backups automated + tested restore
- [ ] CI/CD pipeline runs all tests on push
- [ ] OpenAPI spec generated
- [ ] Load test passes (target: 100 concurrent users)
- [ ] Security review passed
- [ ] All existing tests pass (regression)

**Dependencies:** All previous phases

---

## PHASE 11 — DESKTOP & PRINTER (Future)

**Goal:** Desktop application + thermal printer support.

**Deliverables:**
- Tauri desktop app (Windows, macOS, Linux)
- Thermal printer integration (ESC/POS commands)
- Receipt printing (58mm, 80mm)
- Kitchen printer (order tickets)
- Offline mode (sync when online)
- Auto-update mechanism

**Dependencies:** Phase 10

---

## PHASE 12 — MOBILE APP (Future)

**Goal:** Mobile companion app for owners/managers.

**Deliverables:**
- React Native / Expo app
- Dashboard (real-time stats)
- Sales history
- Inventory check
- Reports view
- Push notifications
- Biometric auth

**Dependencies:** Phase 10

---

## PHASE SUMMARY

| Phase | Name | Status | Est. Complexity |
|-------|------|--------|----------------|
| 0 | ERP Architecture & PDR | DRAFT | High |
| 1 | Catalog & Product Enhancement | Planned | Medium |
| 2 | Inventory Enhancement | Planned | Medium |
| 3 | CRM & Purchasing Enhancement | Planned | Medium |
| 4 | POS Enhancement (ERP Integration) | Planned | Medium |
| 5 | Payment Infrastructure (Xendit) | Planned | High |
| 6 | Finance / Accounting | CLOSED | High |
| 7 | Reports & Analytics | Planned | Medium |
| 8 | Business-Specific Modules | Planned | High |
| 9 | Subscription & Billing | Planned | Medium |
| 10 | Production, Security & Monitoring | Planned | High |
| 11 | Desktop & Printer | Future | Medium |
| 12 | Mobile App | Future | High |

---

## PHASE EXECUTION RULES

1. **No implementation without approved PDR** — each phase starts with documentation.
2. **Phase = complete feature/module** — not divided into hundreds of micro-steps.
3. **Agent manages internal tasks** — user reviews at phase boundaries.
4. **All existing tests must pass** — no regressions allowed.
5. **Documentation updated per phase** — per `03-DOCUMENTATION_FRAMEWORK.md`.
6. **Phase completion checklist** — all items must pass before marking COMPLETE.

---

*End of Phase Roadmap*
