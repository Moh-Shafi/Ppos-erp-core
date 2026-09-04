# Phase 1 — Catalog & Product Enhancement — PDR

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-11  
**Phase:** 1 — Catalog & Product Enhancement  
**Depends On:** Phase 0 (ERP Architecture & Foundation — CLOSED)  
**Baseline:** Phase 0 closed with 793 backend tests, 25 E2E tests, all passing

---

## 1. Problem Statement

The current product catalog is a flat, single-purpose structure designed for a restaurant POS:
- Products have a single SKU, single barcode, single selling price, and a single image string field.
- Categories are flat (no parent/child hierarchy).
- No product variants (size, color, flavor) — restaurants and retail businesses need them.
- No price lists (retail vs wholesale vs member pricing).
- No units of measure with conversion factors (1 case = 12 pcs).
- No CSV/Excel import/export for bulk product management.
- Products and Categories API routes lack permission middleware (audit gap from Phase 0).

The ERP platform must support multi-business-type catalogs where a grocery store has different product structure needs than a restaurant or a pharmacy.

---

## 2. Scope

### In Scope

| Feature | Description |
|---------|-------------|
| **Category Hierarchy** | Parent/child categories with unlimited depth (self-referencing `parent_id`) |
| **Product Variants** | Optional variants per product (e.g., size, color). Variant = combination of option values. Each variant has its own SKU, barcode, price override, and stock. |
| **Multiple Barcodes** | A product (or variant) can have multiple barcodes (table `product_barcodes`) |
| **Price Lists** | Multiple named price lists per tenant (retail, wholesale, member). Each price list has per-product price overrides. |
| **Units of Measure** | Define units per tenant (pcs, box, dus, kg). Conversion factors between units (1 dus = 12 pcs). Product has a base unit. |
| **Product Images** | Multiple images per product with sort order. Stored as file path/URL (no file upload service in this phase — path/URL only). |
| **Cost Price Tracking** | `cost_price` remains on product. Average cost field added for future FIFO/LIFO (Phase 2). |
| **Low Stock Threshold** | Per-product `min_stock` override (in addition to store-level minimum) |
| **Product Import/Export** | CSV import and export for bulk product management |
| **Permission Middleware** | Add `permission:products.view` / `products.manage` and `categories.view` / `categories.manage` to existing routes |
| **Module-Aware UI** | Product and Category pages check `core` module + permissions via ProtectedRoute |
| **Product Status** | `is_active` boolean (existing). Add `is_trackable` boolean (whether inventory is tracked for this product) |

### Out of Scope (Deferred to Later Phases)

| Feature | Phase | Rationale |
|---------|-------|-----------|
| Barcode image generation | Phase 8 | Needs barcode rendering library |
| File upload service (S3/local) | Phase 10 | Infrastructure concern, not catalog |
| Product kits/bundles | Phase 4 | POS enhancement scope |
| Recipe/ingredient mapping | Phase 8 | Restaurant-specific module |
| Modifiers/add-ons | Phase 8 | Restaurant-specific module |
| Stock valuation (FIFO/LIFO) | Phase 2 | Inventory enhancement scope |
| Product performance analytics | Phase 7 | Reports scope |
| Multi-currency pricing | Phase 10 | Production hardening |

---

## 3. User Stories

### Category Management
- As an **Owner**, I want to create parent and child categories so that I can organize my products hierarchically (e.g., Food > Beverages > Hot Drinks).
- As a **Manager**, I want to view categories in a tree structure so that I can understand the catalog organization.
- As an **Owner**, I want to prevent deleting a category that has sub-categories or products so that I don't accidentally break the catalog.

### Product Management
- As a **Manager**, I want to create a product with or without variants so that simple products don't require variant setup.
- As an **Owner**, I want to define variant options (e.g., Size: S/M/L, Color: Red/Blue) and have the system generate variant combinations so that I don't manually create each combination.
- As a **Manager**, I want each variant to have its own SKU, barcode, and price override so that I can track them independently.
- As a **Cashier**, I want to see variant options when selecting a product in POS so that I can pick the right variant (Phase 4 — POS integration).

### Pricing
- As an **Owner**, I want to create price lists (retail, wholesale, member) so that different customers get different prices.
- As a **Manager**, I want to set a product's price per price list so that the correct price is applied at checkout.
- As an **Owner**, I want to set a default price list per customer so that their price is automatically applied (Phase 3 — CRM).

### Units of Measure
- As a **Manager**, I want to define units and conversion factors so that I can buy in cases but sell in pieces.
- As a **Manager**, I want a product to have a base unit and a purchase unit so that the system converts quantities automatically.

### Import/Export
- As an **Owner**, I want to export all products to CSV so that I can edit them in bulk in Excel.
- As an **Owner**, I want to import products from CSV so that I can quickly set up my catalog.

### Images
- As a **Manager**, I want to add multiple image URLs to a product so that I can show product photos in the catalog and POS.

---

## 4. Business Rules

### BR-01: Category Hierarchy
1. Categories can have a parent category (`parent_id` nullable).
2. A category cannot be its own parent (circular reference prevention).
3. A category cannot be moved under one of its own descendants (cycle prevention).
4. Deleting a category with sub-categories is blocked (must delete children first).
5. Deleting a category with products is blocked (must reassign or delete products first).
6. Category slug is unique per tenant and auto-generated from name.
7. Category depth has no hard limit but UI displays a maximum of 3 levels in dropdowns.

### BR-02: Product Variants
1. Variants are optional per product. A product with `has_variants = false` uses the product's own SKU/barcode/price.
2. A product with `has_variants = true` must have at least one variant before it can be activated.
3. Variant options are defined at the product level (e.g., "Size", "Color"). Each option has values (e.g., "S", "M", "L").
4. A variant is a specific combination of option values (e.g., Size=S + Color=Red).
5. Not all combinations must exist — the manager selects which combinations to create.
6. Each variant has its own SKU (unique per tenant), barcode (unique per tenant), and optional price override.
7. If a variant has no price override, it inherits the product's selling price.
8. Inventory is tracked per variant (not per product) when variants are enabled.
9. A product cannot switch from `has_variants = true` back to `false` if variants exist.

### BR-03: Price Lists
1. A tenant can have multiple price lists (e.g., retail, wholesale, member).
2. One price list is marked as default (used when no customer-specific price list applies).
3. Each price list item specifies a product (or variant) and a price.
4. If a product is not in a price list, the product's `selling_price` is used.
5. Price lists are tenant-scoped, not store-scoped (same prices across all stores).
6. Price list prices are `decimal(15,2)` in the tenant's currency.

### BR-04: Units of Measure
1. Each tenant defines its own units (not global).
2. A unit has a name (pcs, box, dus, kg) and a symbol.
3. Conversion factors are defined between units (1 dus = 12 pcs).
4. A product has a `base_unit_id` (the smallest unit for inventory tracking).
5. A product can have a `purchase_unit_id` (the unit used when purchasing).
6. When a purchase is received in the purchase unit, the system converts to base unit for inventory.
7. Conversions are always relative to the base unit (1 purchase_unit = N base_units).

### BR-05: Product Barcodes
1. A product (without variants) can have multiple barcodes.
2. A variant can have multiple barcodes.
3. Barcodes are unique per tenant (across products and variants).
4. A barcode can be scanned in POS to find the product or variant instantly.

### BR-06: Product Images
1. A product can have multiple images (stored as URL/path string).
2. Images have a sort order (first image = primary/thumbnail).
3. Variant images are not supported in Phase 1 (product-level images only).
4. Image field stores a URL or file path — no file upload service in this phase.

### BR-07: Product Import/Export
1. Export produces a CSV file with all products and their primary attributes.
2. Import accepts a CSV file and creates/updates products by SKU (if SKU exists, update; else create).
3. Import validates all fields and returns a summary (created, updated, errors).
4. Import does not support variants or price lists (only base product fields).
5. Import is limited to 1000 rows per request (larger imports should be split).

### BR-08: Low Stock Threshold
1. Product has a `min_stock` field (nullable). If set, it overrides the store-level minimum for this product.
2. If `min_stock` is null, the store-level minimum is used.
3. Dashboard low-stock widget uses the effective minimum (product override or store default).

### BR-09: Product Status
1. `is_active` (existing) — inactive products are hidden from POS and product list by default.
2. `is_trackable` (new) — if false, the product is not inventory-tracked (e.g., a service or digital product).
3. Non-trackable products do not appear in inventory reports or stock adjustments.

---

## 5. Assumptions

- The existing `products` and `categories` tables are preserved. Changes are additive (new columns, new tables).
- The existing POS checkout flow continues to work without modification — products without variants use the existing `selling_price` field.
- Price list application at POS checkout is Phase 4 scope. Phase 1 only creates the price list data structure and management UI.
- Variant selection in POS is Phase 4 scope. Phase 1 only creates the variant data structure and management UI.
- Unit conversion at purchase receiving is Phase 2 scope (Inventory Enhancement). Phase 1 only defines the unit data structure.
- Image upload (file storage) is Phase 10 scope. Phase 1 stores image URLs/paths only.
- The `core` module (always enabled for all tenants) governs catalog features.

---

## 6. Dependencies

| Dependency | Type | Description |
|------------|------|-------------|
| Phase 0 | Hard | Module registry, RBAC, tenant isolation, ProtectedRoute |
| Existing POS | Soft | Product CRUD must not break POS checkout, sales history, or inventory |
| Phase 4 (future) | Consumer | POS will consume variants, price lists |
| Phase 2 (future) | Consumer | Inventory will consume units, min_stock |
| Phase 3 (future) | Consumer | CRM will consume price lists per customer |

---

## 7. Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Breaking existing POS checkout | High | All schema changes are additive. Existing `selling_price` and `sku` columns remain. POS reads from product directly when no variants. |
| Performance with deep category trees | Medium | Index `parent_id`. Limit UI dropdown to 3 levels. API returns flat list with `parent_id` for client-side tree building. |
| Variant complexity overwhelming users | Medium | Variants are opt-in (`has_variants` defaults false). Simple products work exactly as before. |
| Import CSV edge cases (encoding, delimiters) | Medium | Use standard CSV parsing. Validate row-by-row. Return detailed error report. |
| Price list migration for existing tenants | Low | Existing products keep `selling_price` as default. No price list required to function. |

---

## 8. Acceptance Criteria

- [ ] Category hierarchy (parent/child) CRUD works with cycle prevention
- [ ] Product variants CRUD works (create options, generate combinations, per-variant SKU/barcode/price)
- [ ] Multiple barcodes per product/variant work
- [ ] Price lists CRUD works (create list, add items, set default)
- [ ] Units of measure CRUD works with conversion factors
- [ ] Product images (multiple, ordered) display in UI
- [ ] CSV import/export works with validation
- [ ] Low stock threshold per product works
- [ ] `is_trackable` field works (non-trackable products excluded from inventory)
- [ ] Permission middleware added to product/category routes
- [ ] Module-aware UI (ProtectedRoute checks `core` module + permissions)
- [ ] All existing 793 backend tests pass (regression)
- [ ] All existing 25 E2E tests pass (regression)
- [ ] New Phase 1 tests pass (unit, API, integration, E2E)
- [ ] Documentation complete (PDR, Architecture, Flow, API, Security, Testing)

---

## 9. Database Changes Summary

### New Tables (10)
- `units` — Units of measure per tenant
- `unit_conversions` — Conversion factors between units
- `product_variant_options` — Option definitions per product (e.g., "Size")
- `product_variant_option_values` — Option values (e.g., "S", "M", "L")
- `product_variants` — Variant combinations per product
- `product_variant_values` — Pivot: variant ↔ option_value
- `product_barcodes` — Multiple barcodes per product/variant
- `product_images` — Multiple images per product with sort order
- `price_lists` — Named price lists per tenant
- `price_list_items` — Per-product/variant price in a price list

### Modified Tables (2 — additive columns only)
- `products` — Add: `has_variants` (bool, default false), `is_trackable` (bool, default true), `min_stock` (int, nullable), `base_unit_id` (FK nullable), `purchase_unit_id` (FK nullable)
- `categories` — Add: `parent_id` (FK self, nullable), `sort_order` (int, default 0)

### New Permissions (4)
- `products.view` — View product catalog
- `products.manage` — Create/update/delete products
- `categories.view` — View categories
- `categories.manage` — Create/update/delete categories

---

## 10. Approval

This PDR is submitted for user approval. **No implementation will begin until this document and the accompanying Architecture, Flow, API, Security, and Testing documents are reviewed and approved.**

---

*End of Phase 1 PDR*
