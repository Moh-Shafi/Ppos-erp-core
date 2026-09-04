# Phase 1 — Catalog & Product Enhancement — Security

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-11  
**Phase:** 1 — Catalog & Product Enhancement  
**Depends On:** Phase 0 (ERP Architecture & Foundation — CLOSED)

---

## 1. RBAC

### 1.1 Permission Matrix

| Permission | Owner | Manager | Cashier | Staff | Accountant |
|------------|-------|---------|---------|-------|------------|
| `products.view` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `products.manage` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `categories.view` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `categories.manage` | ✅ | ✅ | ❌ | ❌ | ❌ |

### 1.2 Module Requirement

All catalog endpoints require the `core` module to be enabled for the tenant. The `core` module is always enabled (it is a system module with `is_core = true`) and cannot be disabled. This is enforced by the existing `module.check` middleware.

### 1.3 Feature Flags

| Feature | Impact when disabled |
|---------|---------------------|
| `core.variants` | Variant-related endpoints return 403. `has_variants` field is forced to false on create. |
| `core.price_lists` | Price list endpoints return 403. |
| `core.units` | Unit endpoints return 403. `base_unit_id` and `purchase_unit_id` are ignored on product create/update. |
| `core.import_export` | Import/export endpoints return 403. |

### 1.4 Frontend RBAC

- Products page: `ProtectedRoute module="core" permission="products.view"`
- Categories page: `ProtectedRoute module="core" permission="categories.view"`
- Price lists page: `ProtectedRoute module="core" permission="products.view"`
- Units page: `ProtectedRoute module="core" permission="products.view"`
- Add/Edit/Delete buttons: visible only when user has `products.manage` or `categories.manage`
- Import/Export buttons: visible only when user has `products.manage` (import) or `products.view` (export)
- Variant manager UI: visible only when `core.variants` feature is enabled for tenant

---

## 2. Tenant Isolation

### 2.1 Existing Pattern (preserved)

All catalog models use the `BelongsToTenant` trait with global scope:
- `Product`, `Category` — already have `BelongsToTenant`
- New models (`ProductVariant`, `ProductVariantOption`, `ProductVariantOptionValue`, `ProductBarcode`, `ProductImage`, `PriceList`, `PriceListItem`, `Unit`, `UnitConversion`) — all use `BelongsToTenant`

### 2.2 Tenant Isolation for Variant-Related Models

Variant models (`ProductVariant`, `ProductVariantOption`, `ProductVariantOptionValue`, `ProductVariantValue`) do not have a direct `tenant_id` column. Tenant isolation is enforced transitively through the parent `Product`:

- Queries on variants are always scoped: `ProductVariant::whereHas('product', fn($q) => $q->where('tenant_id', $tenantId))`
- The `BelongsToTenant` trait is adapted for these models with a `tenantRelation` property pointing to `product.tenant`
- Global scope joins through `product_id` to check `tenant_id`

### 2.3 Tenant ID Never From Request

Consistent with Phase 0 architecture:
- `tenant_id` is never in `$fillable` for any model
- `tenant_id` is auto-set from `Auth::user()->tenant_id` on create
- `tenant_id` is never accepted from request input

### 2.4 SKU/Barcode Uniqueness

- SKU unique per tenant: `unique('products', 'sku') WHERE tenant_id = X AND sku IS NOT NULL`
- Barcode unique per tenant: `unique('product_barcodes', 'barcode') WHERE tenant_id = X`
- Variant SKU unique per tenant: validated at application level (check all variants across all products in tenant)
- Cross-tenant SKU/barcode collision is allowed (different tenants can have same SKU)

---

## 3. Input Validation

### 3.1 Products

| Field | Rule | Notes |
|-------|------|-------|
| `name` | required, string, max 255 | — |
| `category_id` | required, exists in tenant | — |
| `sku` | nullable, string, max 100, unique per tenant | NULL allowed (no SKU) |
| `barcode` | nullable, string, max 100, unique per tenant | NULL allowed |
| `cost_price` | required, numeric, min 0 | decimal(15,2) |
| `selling_price` | required, numeric, min 0 | decimal(15,2) |
| `unit` | required, string, max 50 | — |
| `is_active` | boolean | default true |
| `is_trackable` | boolean | default true |
| `min_stock` | nullable, integer, min 0 | — |
| `has_variants` | boolean | default false |
| `images.*.url` | required, string, max 500 | URL or file path |
| `barcodes.*` | string, max 100, unique per tenant | — |

### 3.2 Variants

| Field | Rule | Notes |
|-------|------|-------|
| `option_value_ids` | required, array, min 1 | Must match product's option values |
| `sku` | nullable, string, max 100, unique per tenant | — |
| `barcode` | nullable, string, max 100, unique per tenant | — |
| `price_override` | nullable, numeric, min 0 | NULL = inherit product price |
| `cost_price_override` | nullable, numeric, min 0 | NULL = inherit product cost |
| `is_active` | boolean | default true |

### 3.3 Categories

| Field | Rule | Notes |
|-------|------|-------|
| `name` | required, string, max 255 | — |
| `description` | nullable, string | — |
| `is_active` | boolean | default true |
| `parent_id` | nullable, exists in tenant, no cycle | Cycle check at service level |

### 3.4 Price Lists

| Field | Rule | Notes |
|-------|------|-------|
| `name` | required, string, max 100, unique per tenant | — |
| `description` | nullable, string | — |
| `is_default` | boolean | default false; only one default per tenant |
| `is_active` | boolean | default true |

### 3.5 Units

| Field | Rule | Notes |
|-------|------|-------|
| `name` | required, string, max 50 | — |
| `symbol` | required, string, max 20, unique per tenant | — |
| `is_base_unit` | boolean | default false |

### 3.6 CSV Import

| Rule | Notes |
|------|-------|
| File must be CSV format | MIME type check: text/csv, application/csv |
| Max file size: 2MB | — |
| Max rows: 1000 | Rows beyond 1000 are rejected |
| Header row required | First row must contain column names |
| Required columns: name, category_name, cost_price, selling_price, unit | — |
| Optional columns: sku, barcode, is_active, description | — |

---

## 4. Output Sanitization

### 4.1 No Sensitive Data in Responses

- Product responses never include `tenant_id` of other tenants
- Price list responses only include items for the authenticated user's tenant
- Category tree only includes categories for the authenticated user's tenant

### 4.2 Decimal Formatting

- All prices are returned as strings with 2 decimal places (e.g., `"15000.00"`)
- Frontend uses `formatRupiah()` for display and `parseRupiah()` for input

### 4.3 Error Messages

- Error messages do not leak internal system details (no SQL queries, no stack traces)
- Validation errors return field-specific messages: `{"errors": {"sku": ["SKU already exists"]}}`
- 404 errors do not confirm resource existence in other tenants (same response for "not found" and "not yours")

---

## 5. Audit Logging

All catalog mutations are logged via `AuditService` (from Phase 0):

| Action | Entity Type | Old Values | New Values |
|--------|-------------|------------|------------|
| `category.created` | Category | null | { name, parent_id, is_active } |
| `category.updated` | Category | { name, parent_id } | { name, parent_id } |
| `category.deleted` | Category | { name, parent_id } | null |
| `product.created` | Product | null | { name, sku, has_variants, ... } |
| `product.updated` | Product | { name, selling_price } | { name, selling_price } |
| `product.deleted` | Product | { name, sku } | null |
| `variant.created` | ProductVariant | null | { sku, price_override } |
| `variant.updated` | ProductVariant | { sku } | { sku } |
| `variant.deleted` | ProductVariant | { sku } | null |
| `price_list.created` | PriceList | null | { name, is_default } |
| `price_list.updated` | PriceList | { name } | { name } |
| `price_list.deleted` | PriceList | { name } | null |
| `product.imported` | Product | null | { created: N, updated: N, errors: N } |

Audit logs are visible to Owner only (`audit.view` permission) via the existing `GET /api/v1/audit-logs` endpoint.

---

## 6. Known Risks and Mitigations

| Risk | Severity | Mitigation |
|------|----------|------------|
| **IDOR on variant endpoints** — user from tenant A accesses variant from tenant B by ID | High | Global scope on variants joins through `product.tenant_id`. All queries are tenant-scoped. Direct `findOrFail()` on variant also checks tenant ownership via product relation. |
| **CSV injection** — formula injection via CSV import (e.g., `=cmd()`) | Medium | Sanitize CSV cell values: strip leading `=`, `+`, `-`, `@` characters. Use proper CSV parsing library (not naive split). |
| **Cycle in category hierarchy** — user creates circular parent reference | Medium | `CategoryService::validateNoCycle()` checks all descendants before allowing parent change. |
| **SKU/barcode race condition** — two concurrent requests create same SKU | Low | Database unique index on `(tenant_id, sku)` and `(tenant_id, barcode)` catches duplicates. Application catches `QueryException` and returns 422. |
| **Import memory exhaustion** — very large CSV file | Low | Max 1000 rows enforced. File size max 2MB. Row-by-row processing (not loading entire file into memory). |
| **Price list default race** — two requests set different defaults simultaneously | Low | DB transaction + `LOCK TABLES` or `lockForUpdate` on price_lists when setting default. |
| **XSS via product name/description** — malicious script in product name | Low | Frontend renders text (not HTML) by default. React auto-escapes. API returns raw strings (no HTML interpretation). |

---

## 7. Security Test Cases

| Test | Description |
|------|-------------|
| Tenant A cannot see Tenant B's products | GET /products returns only tenant A's products |
| Tenant A cannot access Tenant B's product by ID | GET /products/{id} returns 404 for tenant B's product |
| Staff cannot create products | POST /products returns 403 for Staff role |
| Cashier cannot delete categories | DELETE /categories/{id} returns 403 for Cashier role |
| Tenant A cannot import products into Tenant B's category | Import validates category belongs to tenant |
| Variant endpoint respects tenant isolation | GET /products/{id}/variants returns 404 if product belongs to another tenant |
| Price list items are tenant-scoped | GET /price-lists returns only tenant's lists |
| CSV import sanitizes formula injection | Cells starting with `=`, `+`, `-`, `@` are stripped |
| Category cycle prevention | PUT /categories/{id} with parent_id = descendant returns 422 |
| Barcode lookup is tenant-scoped | GET /products/lookup?barcode=X only finds products in tenant |

---

*End of Phase 1 Security*
