# Phase 1 — Catalog & Product Enhancement — Architecture

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-11  
**Phase:** 1 — Catalog & Product Enhancement  
**Depends On:** Phase 0 (ERP Architecture & Foundation — CLOSED)

---

## 1. Technical Design

Phase 1 extends the existing catalog system with additive schema changes and new service classes. The design preserves the current POS checkout flow — products without variants behave exactly as before.

### Design Principles
1. **Additive-only migrations** — no existing columns modified or dropped
2. **Backward-compatible** — existing POS, inventory, and sales code continues to work
3. **Tenant-scoped** — all new tables use `BelongsToTenant` trait
4. **Module-aware** — catalog routes require `core` module (always enabled)
5. **Service layer** — new `CatalogService` orchestrates variant/price-list/unit operations
6. **Feature-flagged** — variants and price lists are opt-in per tenant via features

---

## 2. Database Schema

### 2.1 Modified Table: `categories` (additive)

```sql
ALTER TABLE categories
  ADD COLUMN parent_id BIGINT UNSIGNED NULL AFTER slug,
  ADD COLUMN sort_order INT DEFAULT 0 AFTER is_active,
  ADD CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id)
    REFERENCES categories(id) ON DELETE RESTRICT;

CREATE INDEX idx_categories_parent ON categories(parent_id);
```

**Existing columns preserved:** id, tenant_id, name, slug, description, is_active, timestamps

### 2.2 Modified Table: `products` (additive)

```sql
ALTER TABLE products
  ADD COLUMN has_variants BOOLEAN DEFAULT FALSE AFTER is_active,
  ADD COLUMN is_trackable BOOLEAN DEFAULT TRUE AFTER has_variants,
  ADD COLUMN min_stock INT NULL AFTER is_trackable,
  ADD COLUMN base_unit_id BIGINT UNSIGNED NULL AFTER min_stock,
  ADD COLUMN purchase_unit_id BIGINT UNSIGNED NULL AFTER base_unit_id,
  ADD CONSTRAINT fk_products_base_unit FOREIGN KEY (base_unit_id)
    REFERENCES units(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_products_purchase_unit FOREIGN KEY (purchase_unit_id)
    REFERENCES units(id) ON DELETE SET NULL;

CREATE INDEX idx_products_has_variants ON products(tenant_id, has_variants);
CREATE INDEX idx_products_trackable ON products(tenant_id, is_trackable);
```

**Existing columns preserved:** id, tenant_id, category_id, name, sku, barcode, description, cost_price, selling_price, unit, image, is_active, timestamps

**Note:** `sku` and `barcode` on `products` table remain for products without variants. When `has_variants = true`, the product-level `sku`/`barcode` are ignored and variant-level values are used.

### 2.3 New Table: `units`

```sql
CREATE TABLE units (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(50) NOT NULL,
  symbol VARCHAR(20) NOT NULL,
  is_base_unit BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  UNIQUE (tenant_id, symbol)
);
```

### 2.4 New Table: `unit_conversions`

```sql
CREATE TABLE unit_conversions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  from_unit_id BIGINT UNSIGNED NOT NULL,
  to_unit_id BIGINT UNSIGNED NOT NULL,
  factor DECIMAL(15, 4) NOT NULL,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (from_unit_id) REFERENCES units(id) ON DELETE CASCADE,
  FOREIGN KEY (to_unit_id) REFERENCES units(id) ON DELETE CASCADE,
  UNIQUE (tenant_id, from_unit_id, to_unit_id)
);
```

**Example:** 1 dus = 12 pcs → `from_unit_id=dus, to_unit_id=pcs, factor=12`

### 2.5 New Table: `product_variant_options`

```sql
CREATE TABLE product_variant_options (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);
```

**Example:** product_id=1, name="Size", sort_order=0

### 2.6 New Table: `product_variant_option_values`

```sql
CREATE TABLE product_variant_option_values (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  option_id BIGINT UNSIGNED NOT NULL,
  value VARCHAR(100) NOT NULL,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (option_id) REFERENCES product_variant_options(id) ON DELETE CASCADE,
  UNIQUE (option_id, value)
);
```

**Example:** option_id=1, value="S", sort_order=0

### 2.7 New Table: `product_variants`

```sql
CREATE TABLE product_variants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  sku VARCHAR(100) NULL,
  barcode VARCHAR(100) NULL,
  price_override DECIMAL(15, 2) NULL,
  cost_price_override DECIMAL(15, 2) NULL,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  UNIQUE (tenant_id, sku) -- Note: tenant_id via product relation, see note below
);
```

**Tenant isolation note:** `product_variants` does not have a direct `tenant_id` column. Tenant isolation is enforced through the parent `product_id` relationship. The `BelongsToTenant` trait is adapted to resolve tenant through `product.tenant_id`. The unique constraint on SKU is enforced at the application level by checking `product.tenant_id`.

### 2.8 New Table: `product_variant_values` (pivot: variant ↔ option_value)

```sql
CREATE TABLE product_variant_values (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  variant_id BIGINT UNSIGNED NOT NULL,
  option_value_id BIGINT UNSIGNED NOT NULL,
  FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
  FOREIGN KEY (option_value_id) REFERENCES product_variant_option_values(id) ON DELETE CASCADE,
  UNIQUE (variant_id, option_value_id)
);
```

### 2.9 New Table: `product_barcodes`

```sql
CREATE TABLE product_barcodes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NULL,
  barcode VARCHAR(100) NOT NULL,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
  UNIQUE (tenant_id, barcode)
);
```

**Note:** When `variant_id` is NULL, the barcode belongs to the product directly. When set, it belongs to a specific variant.

### 2.10 New Table: `product_images`

```sql
CREATE TABLE product_images (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  url VARCHAR(500) NOT NULL,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  INDEX (product_id, sort_order)
);
```

### 2.11 New Table: `price_lists`

```sql
CREATE TABLE price_lists (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  description TEXT NULL,
  is_default BOOLEAN DEFAULT FALSE,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  UNIQUE (tenant_id, slug)
);
```

### 2.12 New Table: `price_list_items`

```sql
CREATE TABLE price_list_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  price_list_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  variant_id BIGINT UNSIGNED NULL,
  price DECIMAL(15, 2) NOT NULL,
  FOREIGN KEY (price_list_id) REFERENCES price_lists(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
  UNIQUE (price_list_id, product_id, variant_id)
);
```

**Note:** When `variant_id` is NULL, the price applies to the product (no variants or default variant price). When set, it applies to a specific variant.

---

## 3. Model Relationships (ERD)

```
Tenant ──┬── Category ──┐ (self-ref: parent_id)
          │              └── Product
          │                   ├── ProductVariantOption ── ProductVariantOptionValue
          │                   ├── ProductVariant ──┐
          │                   │                    └── ProductVariantValue (pivot: variant ↔ option_value)
          │                   ├── ProductBarcode (variant_id nullable)
          │                   ├── ProductImage
          │                   └── PriceListItem (variant_id nullable)
          │
          ├── Unit ── UnitConversion
          │
          └── PriceList ── PriceListItem
```

### Key Relationships

```
Product
  belongsTo: Category, Tenant
  hasMany: Inventory, SaleItem, ProductVariant, ProductVariantOption, ProductBarcode, ProductImage, PriceListItem
  belongsTo: Unit (base_unit_id), Unit (purchase_unit_id)

Category
  belongsTo: Category (parent)
  hasMany: Category (children)
  hasMany: Product

ProductVariant
  belongsTo: Product
  hasMany: ProductVariantValue, ProductBarcode, PriceListItem
  belongsToMany: ProductVariantOptionValue (through ProductVariantValue)

ProductVariantOption
  belongsTo: Product
  hasMany: ProductVariantOptionValue

PriceList
  belongsTo: Tenant
  hasMany: PriceListItem

Unit
  belongsTo: Tenant
  hasMany: UnitConversion (as from_unit), UnitConversion (as to_unit)
```

---

## 4. Service Layer

### 4.1 CatalogService (new)

**Responsibilities:**
- Create/update products with variant support
- Generate variant combinations from option values
- Validate variant SKU/barcode uniqueness per tenant
- Manage product barcodes (add/remove)
- Manage product images (add/remove/reorder)
- CSV import/export logic

**Key methods:**
```php
createProduct(array $data): Product
updateProduct(int $id, array $data): Product
createVariants(int $productId, array $optionValueIds): array
generateVariantCombinations(int $productId): array
importProducts(UploadedFile $file): array  // {created, updated, errors}
exportProducts(): string  // CSV string
```

### 4.2 CategoryService (new)

**Responsibilities:**
- CRUD categories with hierarchy validation
- Cycle prevention (parent cannot be self or descendant)
- Tree building (flat list → nested structure)
- Deletion guard (block if has children or products)

**Key methods:**
```php
createCategory(array $data): Category
updateCategory(int $id, array $data): Category
deleteCategory(int $id): void  // throws if has children/products
getTree(): array  // nested category tree
validateNoCycle(int $categoryId, int $parentId): void
```

### 4.3 PriceListService (new)

**Responsibilities:**
- CRUD price lists
- Add/update/remove price list items
- Set default price list (only one default per tenant)
- Resolve effective price for a product/variant

**Key methods:**
```php
createPriceList(array $data): PriceList
setDefault(int $id): void
addItem(int $priceListId, array $data): PriceListItem
resolvePrice(int $productId, ?int $variantId, ?int $priceListId): decimal
```

### 4.4 UnitService (new)

**Responsibilities:**
- CRUD units
- Manage conversion factors
- Convert quantity between units

**Key methods:**
```php
createUnit(array $data): Unit
addConversion(int $fromUnitId, int $toUnitId, float $factor): UnitConversion
convert(float $quantity, int $fromUnitId, int $toUnitId): float
```

---

## 5. Middleware

### Existing (enhanced)

Product and Category routes currently use `apiResource` without permission middleware. Phase 1 adds:

```php
// Categories
Route::get('categories', [CategoryController::class, 'index'])->middleware('permission:categories.view');
Route::post('categories', [CategoryController::class, 'store'])->middleware('permission:categories.manage');
Route::put('categories/{id}', [CategoryController::class, 'update'])->middleware('permission:categories.manage');
Route::delete('categories/{id}', [CategoryController::class, 'destroy'])->middleware('permission:categories.manage');

// Products
Route::get('products', [ProductController::class, 'index'])->middleware('permission:products.view');
Route::post('products', [ProductController::class, 'store'])->middleware('permission:products.manage');
Route::put('products/{id}', [ProductController::class, 'update'])->middleware('permission:products.manage');
Route::delete('products/{id}', [ProductController::class, 'destroy'])->middleware('permission:products.manage');
```

### New Routes

New routes for variants, barcodes, images, price lists, units, import/export — all under `permission:products.manage` or `permission:categories.manage` as appropriate. See API.md for full route listing.

---

## 6. Configuration

### Feature Flags (registered in ModuleSeeder)

| Feature Slug | Module | Default Enabled | Description |
|--------------|--------|-----------------|-------------|
| `core.variants` | core | false | Product variants support |
| `core.price_lists` | core | false | Multiple price lists |
| `core.units` | core | true | Units of measure with conversions |
| `core.import_export` | core | true | CSV import/export |

### New Permissions (added to RbacSeeder)

| Permission | Module | Roles |
|------------|--------|-------|
| `products.view` | core | Owner, Manager, Cashier, Staff, Accountant |
| `products.manage` | core | Owner, Manager |
| `categories.view` | core | Owner, Manager, Cashier, Staff, Accountant |
| `categories.manage` | core | Owner, Manager |

---

## 7. Integration Points

### Existing Systems (must not break)

| System | Integration Point | Impact |
|--------|-------------------|--------|
| POS Checkout | Reads `product.selling_price` and `product.sku` for products without variants | No change — existing fields preserved |
| Inventory | Reads `product.id` for stock tracking | No change — inventory tracks by product_id. Phase 2 will add variant-level tracking. |
| Sales History | Displays product name and price from sale_items | No change — sale_items stores snapshot at time of sale |
| Dashboard | Counts total products | No change — `Product::count()` still works |

### New Systems (Phase 1 creates, future phases consume)

| Consumer | What They Consume | Phase |
|----------|-------------------|-------|
| POS (Phase 4) | Variants, price lists, barcodes for checkout | Phase 4 |
| Inventory (Phase 2) | Units, base_unit_id, min_stock, is_trackable | Phase 2 |
| CRM (Phase 3) | Price lists per customer | Phase 3 |
| Reports (Phase 7) | Category hierarchy for grouping | Phase 7 |

### Audit Logging

All catalog CRUD operations are logged via `AuditService` (from Phase 0):
- `product.created`, `product.updated`, `product.deleted`
- `category.created`, `category.updated`, `category.deleted`
- `variant.created`, `variant.updated`, `variant.deleted`
- `price_list.created`, `price_list.updated`, `price_list.deleted`
- `product.imported` (with row count summary)

---

## 8. Migration Strategy

### Existing Data

1. `categories.parent_id` defaults to NULL (all existing categories are root-level)
2. `categories.sort_order` defaults to 0
3. `products.has_variants` defaults to FALSE (all existing products are simple)
4. `products.is_trackable` defaults to TRUE (all existing products are inventory-tracked)
5. `products.min_stock` defaults to NULL (use store-level minimum)
6. `products.base_unit_id` and `purchase_unit_id` default to NULL (no unit conversion until configured)
7. Existing `products.sku` and `products.barcode` remain in place and continue to function
8. Existing `products.image` (string) remains — new `product_images` table is for multiple images. The old `image` field is still used as fallback if no images in the new table.

### Seeder Updates

1. `RbacSeeder` — Add `products.view`, `products.manage`, `categories.view`, `categories.manage` permissions
2. `ModuleSeeder` — Add `core.variants`, `core.price_lists`, `core.units`, `core.import_export` features
3. `E2ESeeder` — Add test categories with hierarchy, a product with variants, a price list, and units

---

## 9. File Structure (New Files)

### Backend

```
backend/
  app/
    Models/
      Unit.php
      UnitConversion.php
      ProductVariant.php
      ProductVariantOption.php
      ProductVariantOptionValue.php
      ProductVariantValue.php
      ProductBarcode.php
      ProductImage.php
      PriceList.php
      PriceListItem.php
    Services/
      CatalogService.php
      CategoryService.php
      PriceListService.php
      UnitService.php
    Http/Controllers/
      UnitController.php
      PriceListController.php
      ProductVariantController.php
      ProductImageController.php
      ProductBarcodeController.php
      ProductImportExportController.php
    Http/Requests/
      StoreProductRequest.php
      UpdateProductRequest.php
      StoreCategoryRequest.php
      UpdateCategoryRequest.php
      StorePriceListRequest.php
      StoreUnitRequest.php
  database/
    migrations/
      0001_01_01_000031_create_units_table.php
      0001_01_01_000032_create_unit_conversions_table.php
      0001_01_01_000033_add_variant_fields_to_products_table.php
      0001_01_01_000034_add_parent_id_to_categories_table.php
      0001_01_01_000035_create_product_variant_options_table.php
      0001_01_01_000036_create_product_variant_option_values_table.php
      0001_01_01_000037_create_product_variants_table.php
      0001_01_01_000038_create_product_variant_values_table.php
      0001_01_01_000039_create_product_barcodes_table.php
      0001_01_01_000040_create_product_images_table.php
      0001_01_01_000041_create_price_lists_table.php
      0001_01_01_000042_create_price_list_items_table.php
  tests/
    Feature/
      Phase1CatalogTest.php
      Phase1VariantTest.php
      Phase1PriceListTest.php
      Phase1UnitTest.php
      Phase1ImportExportTest.php
      Phase1MigrationTest.php
```

### Frontend

```
frontend/
  src/
    services/
      unit.ts
      priceList.ts
    types/
      index.ts  (extended with new types)
    components/
      products/
        ProductFormModal.tsx  (enhanced — variant tab, images, barcodes)
        VariantManager.tsx     (new)
        PriceListManager.tsx   (new)
        UnitManager.tsx        (new)
        ProductImportModal.tsx (new)
      categories/
        CategoryTreeSelect.tsx (new)
    pages/
      ProductsPage.tsx  (enhanced)
      CategoriesPage.tsx (enhanced — tree view)
      PriceListsPage.tsx (new)
      UnitsPage.tsx      (new)
  e2e/
    phase1.spec.ts       (new)
```

---

*End of Phase 1 Architecture*
