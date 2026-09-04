# Phase 1 — Catalog & Product Enhancement — Flow

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-11  
**Phase:** 1 — Catalog & Product Enhancement  
**Depends On:** Phase 0 (ERP Architecture & Foundation — CLOSED)

---

## 1. Primary Flows

### 1.1 Create Simple Product (no variants)

```
User (Manager/Owner)
  │
  ├── Navigate to /products → Click "+ Tambah Produk"
  │
  ├── Fill form: name, category, SKU, barcode, cost_price, selling_price, unit, is_active
  │     ├── Select category from dropdown (flat or tree)
  │     ├── Leave "has_variants" unchecked
  │     └── Optionally add image URLs
  │
  ├── Submit → POST /api/v1/products
  │
  ├── CatalogService::createProduct()
  │     ├── Validate category belongs to tenant
  │     ├── Validate SKU unique per tenant
  │     ├── Validate barcode unique per tenant
  │     ├── Create Product record
  │     ├── Save barcodes to product_barcodes (if multiple)
  │     ├── Save images to product_images (if provided)
  │     └── AuditService::log('product.created')
  │
  ├── Response 201: { message, product }
  │
  └── Product list refreshes → new product visible
```

### 1.2 Create Product with Variants

```
User (Manager/Owner)
  │
  ├── Navigate to /products → Click "+ Tambah Produk"
  │
  ├── Fill base product info: name, category, cost_price, selling_price (default)
  │
  ├── Check "has_variants" → Variant Manager panel appears
  │
  ├── Define variant options:
  │     ├── Option 1: "Size" → Values: S, M, L
  │     └── Option 2: "Color" → Values: Red, Blue
  │
  ├── Click "Generate Variants"
  │     ├── System generates all combinations:
  │     │   S+Red, S+Blue, M+Red, M+Blue, L+Red, L+Blue
  │     └── User selects which combinations to create (checkboxes)
  │
  ├── For each selected variant, fill:
  │     ├── SKU (unique per tenant)
  │     ├── Barcode (unique per tenant)
  │     ├── Price override (optional — inherits product selling_price if empty)
  │     └── Is active
  │
  ├── Submit → POST /api/v1/products (with variants array)
  │
  ├── CatalogService::createProduct()
  │     ├── DB::transaction
  │     ├── Create Product (has_variants = true)
  │     ├── Create ProductVariantOptions + OptionValues
  │     ├── Create ProductVariants (each with SKU, barcode, price)
  │     ├── Create ProductVariantValues (pivot: variant ↔ option_value)
  │     ├── Save barcodes to product_barcodes
  │     ├── Validate all SKUs/barcodes unique per tenant
  │     └── AuditService::log('product.created')
  │
  ├── Response 201: { message, product with variants }
  │
  └── Product list refreshes → product shows "has variants" badge
```

### 1.3 Create Category with Hierarchy

```
User (Manager/Owner)
  │
  ├── Navigate to /categories → Click "+ Tambah Kategori"
  │
  ├── Fill form: name, description, is_active
  │
  ├── Select parent category (optional — "None" = root)
  │     ├── Dropdown shows tree structure (indented)
  │     └── Cannot select self or own descendant (cycle prevention)
  │
  ├── Submit → POST /api/v1/categories
  │
  ├── CategoryService::createCategory()
  │     ├── Validate parent_id belongs to tenant (if set)
  │     ├── Validate no cycle (parent != self, parent not a descendant)
  │     ├── Generate slug from name (unique per tenant)
  │     ├── Create Category record
  │     └── AuditService::log('category.created')
  │
  ├── Response 201: { message, category }
  │
  └── Category tree refreshes → new category appears under parent
```

### 1.4 Create Price List and Add Items

```
User (Owner)
  │
  ├── Navigate to /price-lists → Click "+ Tambah Price List"
  │
  ├── Fill form: name (e.g., "Wholesale"), description, is_default
  │
  ├── Submit → POST /api/v1/price-lists
  │
  ├── PriceListService::createPriceList()
  │     ├── Validate name unique per tenant
  │     ├── If is_default = true, unset previous default
  │     ├── Create PriceList record
  │     └── AuditService::log('price_list.created')
  │
  ├── Response 201: { message, price_list }
  │
  ├── Click on price list → Add items
  │     ├── Select product (or variant)
  │     ├── Enter price
  │     └── Submit → POST /api/v1/price-lists/{id}/items
  │
  ├── PriceListService::addItem()
  │     ├── Validate product belongs to tenant
  │     ├── Validate variant belongs to product (if variant_id set)
  │     ├── Check unique (price_list_id + product_id + variant_id)
  │     └── Create PriceListItem record
  │
  └── Price list now has items → visible in price list detail
```

### 1.5 CSV Import Products

```
User (Owner)
  │
  ├── Navigate to /products → Click "Import CSV"
  │
  ├── Upload CSV file
  │     ├── Columns: name, category_name, sku, barcode, cost_price, selling_price, unit, is_active, description
  │     └── First row = header row
  │
  ├── Submit → POST /api/v1/products/import
  │
  ├── CatalogService::importProducts()
  │     ├── Parse CSV (row by row, max 1000 rows)
  │     ├── For each row:
  │     │   ├── Validate fields (name required, prices numeric, etc.)
  │     │   ├── Resolve category by name (create if not found? No — error if not found)
  │     │   ├── If SKU exists → update product
  │     │   ├── If SKU is null/new → create product
  │     │   ├── Validate SKU/barcode uniqueness per tenant
  │     │   └── Collect result (created/updated/error)
  │     ├── DB::transaction (batch commit)
  │     └── AuditService::log('product.imported', { created, updated, errors })
  │
  ├── Response 200: { created: N, updated: N, errors: [...] }
  │
  └── User sees summary → fixes errors if any → re-import
```

### 1.6 CSV Export Products

```
User (Owner/Manager)
  │
  ├── Navigate to /products → Click "Export CSV"
  │
  ├── GET /api/v1/products/export
  │
  ├── CatalogService::exportProducts()
  │     ├── Query all products (with category)
  │     ├── Generate CSV string with headers
  │     └── Return as download (Content-Type: text/csv)
  │
  └── Browser downloads products.csv
```

---

## 2. Alternative Flows

### 2.1 Edit Product with Variants

```
User clicks "Edit" on a product with variants
  │
  ├── Product form loads with variant tab active
  │     ├── Base product info editable (name, category, prices)
  │     ├── Variant options shown (can add/remove values)
  │     └── Existing variants listed with their SKU/barcode/price
  │
  ├── User can:
  │     ├── Add new variant option (e.g., "Material")
  │     ├── Add new option value (e.g., "Cotton" to "Material")
  │     ├── Generate new variant combinations
  │     ├── Edit existing variant SKU/barcode/price
  │     ├── Deactivate a variant (is_active = false)
  │     └── Cannot delete a variant that has sale items (soft delete only)
  │
  └── Submit → PUT /api/v1/products/{id} (with variants array)
```

### 2.2 Delete Category with Guard

```
User clicks "Delete" on a category
  │
  ├── DELETE /api/v1/categories/{id}
  │
  ├── CategoryService::deleteCategory()
  │     ├── Check if category has sub-categories → 422: "Cannot delete category with sub-categories"
  │     ├── Check if category has products → 422: "Cannot delete category with existing products"
  │     ├── If both checks pass → delete category
  │     └── AuditService::log('category.deleted')
  │
  └── If error → user sees message → must reassign/delete children first
```

### 2.3 Move Category to New Parent

```
User edits a category → changes parent_id
  │
  ├── PUT /api/v1/categories/{id}
  │
  ├── CategoryService::updateCategory()
  │     ├── Validate new parent belongs to tenant
  │     ├── Validate no cycle (new parent is not self or a descendant)
  │     │   └── If cycle detected → 422: "Cannot move category under its own descendant"
  │     ├── Update category
  │     └── AuditService::log('category.updated')
  │
  └── Category tree refreshes → category appears under new parent
```

### 2.4 Resolve Price for Product

```
Future consumer (Phase 4 POS or Phase 3 CRM) needs effective price
  │
  ├── PriceListService::resolvePrice(productId, variantId, priceListId)
  │     ├── If priceListId provided:
  │     │   ├── Search price_list_items for (price_list_id, product_id, variant_id)
  │     │   ├── If found → return that price
  │     │   └── If not found → fall through
  │     ├── If variantId provided and variant has price_override:
  │     │   └── Return variant.price_override
  │     └── Return product.selling_price (default)
  │
  └── Caller receives effective price
```

### 2.5 Barcode Lookup (for future POS integration)

```
Future POS scans a barcode
  │
  ├── Search product_barcodes WHERE barcode = X AND tenant_id = user.tenant_id
  │
  ├── If found:
  │     ├── If variant_id is set → return product + variant
  │     └── If variant_id is null → return product (no variants)
  │
  └── If not found → 404: product not found
```

---

## 3. State Machines

### 3.1 Product Variant Lifecycle

```
                    ┌─────────────┐
                    │  Created    │
                    │  is_active  │
                    │  = true     │
                    └──────┬──────┘
                           │
                    ┌──────▼──────┐
           ┌────────┤   Active    ├────────┐
           │        └─────────────┘        │
           │                                 │
    ┌──────▼──────┐                  ┌──────▼──────┐
    │  Deactivate │                  │   Delete    │
    │  is_active  │                  │  (soft)     │
    │  = false    │                  │             │
    └──────┬──────┘                  └─────────────┘
           │
    ┌──────▼──────┐
    │  Reactivate │
    │  is_active  │
    │  = true     │
    └─────────────┘

Rules:
- Cannot delete variant with existing sale_items → must deactivate instead
- Cannot delete variant with existing inventory → must deactivate instead
- Deactivated variants are hidden from POS product grid
- Deactivated variants still appear in product management (with "Inactive" badge)
```

### 3.2 Price List Lifecycle

```
    ┌─────────────┐
    │   Created   │
    │ is_active   │
    │  = true     │
    └──────┬──────┘
           │
    ┌──────▼──────┐
    │   Active    │◄────────┐
    └──────┬──────┘         │
           │                │
    ┌──────▼──────┐    ┌────┴───────┐
    │  Deactivate │───►│ Reactivate │
    │ is_active   │    └────────────┘
    │  = false    │
    └──────┬──────┘
           │
    ┌──────▼──────┐
    │   Delete    │
    │  (cascade   │
    │   items)    │
    └─────────────┘

Default Price List:
- Only one price list can be is_default = true per tenant
- Setting a new default automatically unsets the previous default
- Default price list cannot be deactivated (must set another as default first)
```

---

## 4. Sequence Diagrams

### 4.1 Create Product with Variants (Full Sequence)

```
Frontend          Backend              CatalogService       Database
    │                │                      │                   │
    │  POST /products│                      │                   │
    │  (with variants)│                     │                   │
    │───────────────►│                      │                   │
    │                │  createProduct()     │                   │
    │                │─────────────────────►│                   │
    │                │                      │  BEGIN TRANSACTION │
    │                │                      │──────────────────►│
    │                │                      │  INSERT products  │
    │                │                      │──────────────────►│
    │                │                      │  INSERT options   │
    │                │                      │──────────────────►│
    │                │                      │  INSERT values    │
    │                │                      │──────────────────►│
    │                │                      │  INSERT variants  │
    │                │                      │──────────────────►│
    │                │                      │  INSERT pivot     │
    │                │                      │──────────────────►│
    │                │                      │  INSERT barcodes  │
    │                │                      │──────────────────►│
    │                │                      │  Audit log        │
    │                │                      │──────────────────►│
    │                │                      │  COMMIT           │
    │                │                      │──────────────────►│
    │                │  Product + variants  │                   │
    │                │◄─────────────────────│                   │
    │  201 Created   │                      │                   │
    │◄───────────────│                      │                   │
    │                │                      │                   │
```

### 4.2 CSV Import (Error Handling)

```
Frontend          Backend              CatalogService       Database
    │                │                      │                   │
    │  POST /products/import                │                   │
    │  (CSV file)   │                      │                   │
    │───────────────►│                      │                   │
    │                │  importProducts()    │                   │
    │                │─────────────────────►│                   │
    │                │                      │  Parse CSV        │
    │                │                      │  Row 1: validate  │
    │                │                      │  Row 1: create    │
    │                │                      │──────────────────►│
    │                │                      │  Row 2: validate  │
    │                │                      │  Row 2: ERROR     │
    │                │                      │  (duplicate SKU)  │
    │                │                      │  Row 3: validate  │
    │                │                      │  Row 3: update    │
    │                │                      │──────────────────►│
    │                │                      │  COMMIT           │
    │                │                      │──────────────────►│
    │                │  { created: 1,       │                   │
    │                │    updated: 1,       │                   │
    │                │    errors: [row 2] } │                   │
    │                │◄─────────────────────│                   │
    │  200 + summary │                      │                   │
    │◄───────────────│                      │                   │
```

### 4.3 Category Hierarchy with Cycle Prevention

```
Frontend          Backend              CategoryService      Database
    │                │                      │                   │
    │  PUT /categories/3                    │                   │
    │  { parent_id: 5 }│                    │                   │
    │───────────────►│                      │                   │
    │                │  updateCategory()    │                   │
    │                │─────────────────────►│                   │
    │                │                      │  Get descendants  │
    │                │                      │  of category 3    │
    │                │                      │──────────────────►│
    │                │                      │  [3, 4, 5, 6]     │
    │                │                      │◄──────────────────│
    │                │                      │  Is 5 in [3,4,5,6]?│
    │                │                      │  YES → cycle!     │
    │                │                      │  → throw 422      │
    │                │  422: "Cannot move   │                   │
    │                │  under descendant"   │                   │
    │                │◄─────────────────────│                   │
    │  422 + error   │                      │                   │
    │◄───────────────│                      │                   │
```

---

## 5. UI Flow

### 5.1 Products Page (Enhanced)

```
/products
  │
  ├── Page Header: "Produk" + total count
  │   ├── [+ Tambah Produk] (products.manage)
  │   ├── [Import CSV] (products.manage)
  │   └── [Export CSV] (products.view)
  │
  ├── Filters: search, category (tree select), status (active/inactive)
  │
  ├── Product Table
  │   ├── Columns: Name, SKU, Barcode, Category, Price, Variants badge, Status, Actions
  │   ├── If has_variants → show "N variants" badge
  │   └── Row actions: Edit, Delete (products.manage only)
  │
  └── Pagination
```

### 5.2 Product Form Modal (Enhanced)

```
Product Form Modal
  │
  ├── Tab 1: Basic Info
  │   ├── Name, Category (tree select), SKU, Barcode
  │   ├── Cost Price, Selling Price, Unit
  │   ├── is_active, is_trackable
  │   └── Description
  │
  ├── Tab 2: Variants (visible when has_variants checked)
  │   ├── Option Manager: add/remove options + values
  │   ├── Generate Combinations button
  │   └── Variant Table: SKU, Barcode, Price Override, Active toggle
  │
  ├── Tab 3: Images
  │   ├── URL input + Add button
  │   └── Image list with sort order (drag to reorder)
  │
  └── Tab 4: Barcodes (multiple)
      ├── Barcode input + Add button
      └── Barcode list with remove button
```

### 5.3 Categories Page (Enhanced)

```
/categories
  │
  ├── Page Header: "Kategori" + total count
  │   └── [+ Tambah Kategori] (categories.manage)
  │
  ├── Search input
  │
  ├── Category Tree View (indented hierarchy)
  │   ├── Each row: Name, Description, Status, # Products, Actions
  │   ├── Expand/Collapse children
  │   └── Row actions: Edit, Delete (categories.manage only)
  │
  └── Pagination (flat pagination, tree built client-side)
```

### 5.4 Price Lists Page (New)

```
/price-lists
  │
  ├── Page Header: "Price Lists" + total count
  │   └── [+ Tambah Price List] (products.manage)
  │
  ├── Price List Table
  │   ├── Columns: Name, Description, Default badge, Items count, Status, Actions
  │   └── Row actions: Edit, Manage Items, Delete
  │
  └── Price List Detail (items management)
      ├── Product/Variant selector
      ├── Price input
      └── Items table with remove button
```

### 5.5 Units Page (New)

```
/units
  │
  ├── Page Header: "Satuan" + total count
  │   └── [+ Tambah Satuan] (products.manage)
  │
  ├── Units Table
  │   ├── Columns: Name, Symbol, Base Unit badge, Actions
  │   └── Row actions: Edit, Delete
  │
  └── Conversions Section
      ├── From → To → Factor
      └── Add conversion form
```

---

*End of Phase 1 Flow*
