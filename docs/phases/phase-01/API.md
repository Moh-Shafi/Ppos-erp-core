# Phase 1 — Catalog & Product Enhancement — API

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-11  
**Phase:** 1 — Catalog & Product Enhancement  
**Depends On:** Phase 0 (ERP Architecture & Foundation — CLOSED)  
**Base URL:** `/api/v1`  
**Auth:** Sanctum Bearer token (all endpoints require `auth:sanctum`)

---

## 1. Endpoints Overview

### Categories (enhanced — existing routes + permission middleware)

| Method | Path | Permission | Description |
|--------|------|------------|-------------|
| GET | `/categories` | `categories.view` | List categories (paginated, with tree option) |
| GET | `/categories/tree` | `categories.view` | Get category tree (nested structure) |
| GET | `/categories/{id}` | `categories.view` | Show single category |
| POST | `/categories` | `categories.manage` | Create category |
| PUT | `/categories/{id}` | `categories.manage` | Update category |
| DELETE | `/categories/{id}` | `categories.manage` | Delete category (guarded) |

### Products (enhanced — existing routes + permission middleware + new fields)

| Method | Path | Permission | Description |
|--------|------|------------|-------------|
| GET | `/products` | `products.view` | List products (paginated, filterable) |
| GET | `/products/{id}` | `products.view` | Show product (with variants, images, barcodes) |
| POST | `/products` | `products.manage` | Create product (simple or with variants) |
| PUT | `/products/{id}` | `products.manage` | Update product (including variants) |
| DELETE | `/products/{id}` | `products.manage` | Delete product |
| GET | `/products/export` | `products.view` | Export products as CSV |
| POST | `/products/import` | `products.manage` | Import products from CSV |
| GET | `/products/lookup` | `products.view` | Lookup product by barcode |

### Product Variants (nested under product)

| Method | Path | Permission | Description |
|--------|------|------------|-------------|
| GET | `/products/{id}/variants` | `products.view` | List variants for a product |
| POST | `/products/{id}/variants` | `products.manage` | Create a variant |
| PUT | `/products/{id}/variants/{variantId}` | `products.manage` | Update a variant |
| DELETE | `/products/{id}/variants/{variantId}` | `products.manage` | Delete a variant (guarded) |
| POST | `/products/{id}/variants/generate` | `products.manage` | Generate variant combinations from options |

### Product Images (nested under product)

| Method | Path | Permission | Description |
|--------|------|------------|-------------|
| GET | `/products/{id}/images` | `products.view` | List images for a product |
| POST | `/products/{id}/images` | `products.manage` | Add image to product |
| PUT | `/products/{id}/images/{imageId}` | `products.manage` | Update image (sort order) |
| DELETE | `/products/{id}/images/{imageId}` | `products.manage` | Remove image |

### Product Barcodes (nested under product)

| Method | Path | Permission | Description |
|--------|------|------------|-------------|
| POST | `/products/{id}/barcodes` | `products.manage` | Add barcode to product |
| DELETE | `/products/{id}/barcodes/{barcodeId}` | `products.manage` | Remove barcode |

### Price Lists

| Method | Path | Permission | Description |
|--------|------|------------|-------------|
| GET | `/price-lists` | `products.view` | List price lists |
| GET | `/price-lists/{id}` | `products.view` | Show price list with items |
| POST | `/price-lists` | `products.manage` | Create price list |
| PUT | `/price-lists/{id}` | `products.manage` | Update price list |
| DELETE | `/price-lists/{id}` | `products.manage` | Delete price list (cascade items) |
| POST | `/price-lists/{id}/items` | `products.manage` | Add price list item |
| PUT | `/price-lists/{id}/items/{itemId}` | `products.manage` | Update price list item |
| DELETE | `/price-lists/{id}/items/{itemId}` | `products.manage` | Remove price list item |

### Units

| Method | Path | Permission | Description |
|--------|------|------------|-------------|
| GET | `/units` | `products.view` | List units |
| POST | `/units` | `products.manage` | Create unit |
| PUT | `/units/{id}` | `products.manage` | Update unit |
| DELETE | `/units/{id}` | `products.manage` | Delete unit (guarded) |
| POST | `/units/conversions` | `products.manage` | Add conversion factor |
| DELETE | `/units/conversions/{id}` | `products.manage` | Remove conversion factor |

---

## 2. Request Schemas

### 2.1 Create Category

```json
POST /categories
{
  "name": "Hot Drinks",
  "description": "Hot beverages",
  "is_active": true,
  "parent_id": 2
}
```

**Validation:**
- `name`: required, string, max 255
- `description`: nullable, string
- `is_active`: boolean, default true
- `parent_id`: nullable, integer, must exist in categories table for same tenant, must not create cycle

### 2.2 Create Simple Product

```json
POST /products
{
  "category_id": 3,
  "name": "Espresso 60ml",
  "sku": "ESP-60",
  "barcode": "8992761140011",
  "description": "Single shot espresso",
  "cost_price": 5000,
  "selling_price": 15000,
  "unit": "pcs",
  "is_active": true,
  "is_trackable": true,
  "min_stock": 10,
  "base_unit_id": null,
  "purchase_unit_id": null,
  "has_variants": false,
  "images": [
    { "url": "https://example.com/espresso.jpg", "sort_order": 0 }
  ],
  "barcodes": [
    "8992761140011",
    "8992761140028"
  ]
}
```

**Validation:**
- `category_id`: required, integer, must exist for tenant
- `name`: required, string, max 255
- `sku`: nullable, string, max 100, unique per tenant
- `barcode`: nullable, string, max 100, unique per tenant
- `cost_price`: required, numeric, min 0
- `selling_price`: required, numeric, min 0
- `unit`: required, string, max 50
- `is_active`: boolean, default true
- `is_trackable`: boolean, default true
- `min_stock`: nullable, integer, min 0
- `base_unit_id`: nullable, integer, must exist in units for tenant
- `purchase_unit_id`: nullable, integer, must exist in units for tenant
- `has_variants`: boolean, default false
- `images`: nullable, array of { url: string, sort_order: int }
- `barcodes`: nullable, array of strings, each unique per tenant

### 2.3 Create Product with Variants

```json
POST /products
{
  "category_id": 3,
  "name": "T-Shirt",
  "cost_price": 30000,
  "selling_price": 75000,
  "unit": "pcs",
  "has_variants": true,
  "variant_options": [
    {
      "name": "Size",
      "sort_order": 0,
      "values": [
        { "value": "S", "sort_order": 0 },
        { "value": "M", "sort_order": 1 },
        { "value": "L", "sort_order": 2 }
      ]
    },
    {
      "name": "Color",
      "sort_order": 1,
      "values": [
        { "value": "Red", "sort_order": 0 },
        { "value": "Blue", "sort_order": 1 }
      ]
    }
  ],
  "variants": [
    {
      "option_value_ids": [1, 4],
      "sku": "TSHIRT-S-RED",
      "barcode": "899001",
      "price_override": 70000,
      "cost_price_override": 28000,
      "is_active": true
    },
    {
      "option_value_ids": [1, 5],
      "sku": "TSHIRT-S-BLU",
      "barcode": "899002",
      "price_override": null,
      "cost_price_override": null,
      "is_active": true
    },
    {
      "option_value_ids": [2, 4],
      "sku": "TSHIRT-M-RED",
      "barcode": "899003",
      "price_override": 75000,
      "cost_price_override": null,
      "is_active": true
    }
  ]
}
```

**Validation:**
- `variant_options`: required when `has_variants = true`, array, min 1
- `variant_options[].name`: required, string, max 100
- `variant_options[].values`: required, array, min 1
- `variant_options[].values[].value`: required, string, max 100
- `variants`: required when `has_variants = true`, array, min 1
- `variants[].option_value_ids`: required, array of integers, must match option values
- `variants[].sku`: nullable, string, max 100, unique per tenant
- `variants[].barcode`: nullable, string, max 100, unique per tenant
- `variants[].price_override`: nullable, numeric, min 0
- `variants[].cost_price_override`: nullable, numeric, min 0
- `variants[].is_active`: boolean, default true

### 2.4 Generate Variant Combinations

```json
POST /products/{id}/variants/generate
{
  "option_value_ids": [
    [1, 2, 3],
    [4, 5]
  ]
}
```

**Response:**
```json
{
  "combinations": [
    { "option_value_ids": [1, 4], "label": "S / Red" },
    { "option_value_ids": [1, 5], "label": "S / Blue" },
    { "option_value_ids": [2, 4], "label": "M / Red" },
    { "option_value_ids": [2, 5], "label": "M / Blue" },
    { "option_value_ids": [3, 4], "label": "L / Red" },
    { "option_value_ids": [3, 5], "label": "L / Blue" }
  ]
}
```

### 2.5 Create Price List

```json
POST /price-lists
{
  "name": "Wholesale",
  "description": "Bulk pricing for resellers",
  "is_default": false,
  "is_active": true
}
```

### 2.6 Add Price List Item

```json
POST /price-lists/{id}/items
{
  "product_id": 5,
  "variant_id": null,
  "price": 60000
}
```

**Validation:**
- `product_id`: required, integer, must exist for tenant
- `variant_id`: nullable, integer, must belong to product
- `price`: required, numeric, min 0
- Unique: (price_list_id, product_id, variant_id) combination

### 2.7 Create Unit

```json
POST /units
{
  "name": "Pieces",
  "symbol": "pcs",
  "is_base_unit": true
}
```

### 2.8 Add Unit Conversion

```json
POST /units/conversions
{
  "from_unit_id": 2,
  "to_unit_id": 1,
  "factor": 12
}
```

**Meaning:** 1 unit of `from_unit` (e.g., dus) = 12 units of `to_unit` (e.g., pcs)

### 2.9 Import Products (CSV)

```
POST /products/import
Content-Type: multipart/form-data

file: <CSV file>
```

**CSV format:**
```csv
name,category_name,sku,barcode,cost_price,selling_price,unit,is_active,description
Espresso 60ml,Hot Drinks,ESP-60,8992761140011,5000,15000,pcs,1,Single shot espresso
Cappuccino,Hot Drinks,CAP-01,8992761140028,7000,20000,pcs,1,Classic cappuccino
```

### 2.10 Barcode Lookup

```
GET /products/lookup?barcode=8992761140011
```

---

## 3. Response Schemas

### 3.1 Product (with variants, images, barcodes)

```json
{
  "product": {
    "id": 1,
    "tenant_id": 1,
    "category_id": 3,
    "category": {
      "id": 3,
      "name": "Hot Drinks",
      "parent_id": 2
    },
    "name": "T-Shirt",
    "sku": null,
    "barcode": null,
    "description": null,
    "cost_price": "30000.00",
    "selling_price": "75000.00",
    "unit": "pcs",
    "image": null,
    "is_active": true,
    "has_variants": true,
    "is_trackable": true,
    "min_stock": null,
    "base_unit_id": null,
    "purchase_unit_id": null,
    "created_at": "2026-08-11T12:00:00.000000Z",
    "updated_at": "2026-08-11T12:00:00.000000Z",
    "variant_options": [
      {
        "id": 1,
        "name": "Size",
        "sort_order": 0,
        "values": [
          { "id": 1, "value": "S", "sort_order": 0 },
          { "id": 2, "value": "M", "sort_order": 1 },
          { "id": 3, "value": "L", "sort_order": 2 }
        ]
      }
    ],
    "variants": [
      {
        "id": 1,
        "sku": "TSHIRT-S-RED",
        "barcode": "899001",
        "price_override": "70000.00",
        "cost_price_override": "28000.00",
        "is_active": true,
        "option_values": [
          { "option_name": "Size", "value": "S" },
          { "option_name": "Color", "value": "Red" }
        ]
      }
    ],
    "images": [
      { "id": 1, "url": "https://example.com/tshirt.jpg", "sort_order": 0 }
    ],
    "barcodes": [
      { "id": 1, "barcode": "899001", "variant_id": 1 }
    ]
  }
}
```

### 3.2 Category Tree

```json
GET /categories/tree
{
  "tree": [
    {
      "id": 1,
      "name": "Beverages",
      "parent_id": null,
      "sort_order": 0,
      "is_active": true,
      "product_count": 5,
      "children": [
        {
          "id": 3,
          "name": "Hot Drinks",
          "parent_id": 1,
          "sort_order": 0,
          "is_active": true,
          "product_count": 3,
          "children": []
        }
      ]
    }
  ]
}
```

### 3.3 Import Result

```json
{
  "created": 15,
  "updated": 3,
  "errors": [
    {
      "row": 5,
      "sku": "DUPLICATE-001",
      "error": "SKU already exists for another product"
    }
  ]
}
```

### 3.4 Barcode Lookup

```json
{
  "product": {
    "id": 5,
    "name": "Espresso 60ml",
    "selling_price": "15000.00",
    "has_variants": false
  },
  "variant": null
}
```

Or with variant:
```json
{
  "product": {
    "id": 10,
    "name": "T-Shirt",
    "selling_price": "75000.00",
    "has_variants": true
  },
  "variant": {
    "id": 3,
    "sku": "TSHIRT-M-RED",
    "price_override": "75000.00",
    "option_values": [
      { "option_name": "Size", "value": "M" },
      { "option_name": "Color", "value": "Red" }
    ]
  }
}
```

---

## 4. Error Codes

| HTTP Status | Code | Description |
|-------------|------|-------------|
| 401 | — | Not authenticated |
| 403 | — | Missing permission or module not enabled |
| 404 | — | Resource not found (or not in tenant scope) |
| 422 | `CATEGORY_HAS_CHILDREN` | Cannot delete category with sub-categories |
| 422 | `CATEGORY_HAS_PRODUCTS` | Cannot delete category with existing products |
| 422 | `CATEGORY_CYCLE` | Cannot move category under its own descendant |
| 422 | `SKU_NOT_UNIQUE` | SKU already exists for another product/variant in tenant |
| 422 | `BARCODE_NOT_UNIQUE` | Barcode already exists for another product/variant in tenant |
| 422 | `VARIANT_HAS_SALES` | Cannot delete variant with existing sale items |
| 422 | `VARIANT_HAS_INVENTORY` | Cannot delete variant with existing inventory records |
| 422 | `DEFAULT_PRICE_LIST` | Cannot deactivate the default price list |
| 422 | `IMPORT_LIMIT_EXCEEDED` | CSV import exceeds 1000 row limit |
| 422 | `IMPORT_VALIDATION_ERROR` | One or more rows failed validation (see errors array) |
| 422 | `UNIT_IN_USE` | Cannot delete unit that is assigned to products |

---

## 5. Rate Limits

| Endpoint Group | Rate Limit |
|----------------|------------|
| GET (list/show) | 60 req/min (standard protected) |
| POST/PUT/DELETE | 60 req/min (standard protected) |
| POST /products/import | 5 req/min (heavy operation) |
| GET /products/export | 10 req/min (heavy operation) |

---

## 6. Query Parameters (List Endpoints)

### GET /products

| Parameter | Type | Description |
|-----------|------|-------------|
| `search` | string | Search by name, SKU, or barcode |
| `category_id` | int | Filter by category (includes sub-categories) |
| `is_active` | bool | Filter by active status |
| `has_variants` | bool | Filter by variant support |
| `is_trackable` | bool | Filter by inventory tracking |
| `per_page` | int | Items per page (max 100, default 20) |
| `page` | int | Page number |
| `with` | string | Comma-separated relations: `variants,images,barcodes,category` |

### GET /categories

| Parameter | Type | Description |
|-----------|------|-------------|
| `search` | string | Search by name or slug |
| `is_active` | bool | Filter by active status |
| `parent_id` | int/null | Filter by parent (null = root categories) |
| `per_page` | int | Items per page (max 100, default 20) |
| `with_product_count` | bool | Include product count per category |

---

## 7. Backward Compatibility

### Existing API behavior preserved:
- `GET /products` still returns paginated products with the same structure (new fields added as extra keys)
- `POST /products` still accepts the same fields (new fields are optional with defaults)
- `GET /categories` still returns paginated categories (new `parent_id` and `sort_order` fields added)
- POS checkout (`POST /sales/checkout`) continues to read `product.selling_price` for products without variants
- Inventory endpoints continue to work with `product_id` (no variant_id required until Phase 2)

### New fields in responses (additive, non-breaking):
- Product responses now include: `has_variants`, `is_trackable`, `min_stock`, `base_unit_id`, `purchase_unit_id`, `variant_options`, `variants`, `images`, `barcodes`
- Category responses now include: `parent_id`, `sort_order`

---

*End of Phase 1 API*
