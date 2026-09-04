# Phase 1 — Catalog & Product Enhancement — Testing

**Document Status:** DRAFT — Pending Approval  
**Created:** 2026-08-11  
**Phase:** 1 — Catalog & Product Enhancement  
**Depends On:** Phase 0 (ERP Architecture & Foundation — CLOSED)

---

## 1. Test Plan

### Testing Strategy

| Level | Tool | Scope |
|-------|------|-------|
| Unit Tests | PHPUnit (backend) | Service layer: CatalogService, CategoryService, PriceListService, UnitService |
| API Tests | PHPUnit (backend) | Controller endpoints: CRUD, validation, permissions, tenant isolation |
| Integration Tests | PHPUnit (backend) | Cross-module: product → inventory, product → sale_items, category → product |
| Migration Tests | PHPUnit (backend) | New tables exist, existing tables preserved, additive columns |
| E2E Tests | Playwright (frontend) | User flows: create product, manage variants, import CSV, category tree |
| Regression Tests | PHPUnit + Playwright | All existing 793 backend tests + 25 E2E tests must pass |

### Test Database

- Uses `pos_saas_testing` MySQL database (existing pattern)
- `migrate:fresh --seed` before each test class
- Auth pattern: `$this->actingAs($user, 'sanctum')` + `Auth::forgetGuards()`

---

## 2. Unit Tests (Service Layer)

### 2.1 CatalogService (Phase1CatalogTest.php)

| Test | Description |
|------|-------------|
| `test_create_simple_product` | Create product without variants, verify fields saved |
| `test_create_product_with_variants` | Create product with options + variants, verify all records created |
| `test_create_product_validates_tenant_category` | Category from another tenant → exception |
| `test_create_product_validates_unique_sku` | Duplicate SKU in same tenant → exception |
| `test_create_product_validates_unique_barcode` | Duplicate barcode in same tenant → exception |
| `test_update_product_preserves_variants` | Update base product info, variants unchanged |
| `test_update_product_add_variant` | Add new variant to existing product |
| `test_update_product_deactivate_variant` | Set variant is_active = false |
| `test_generate_variant_combinations` | Generate all combinations from option values |
| `test_generate_variant_combinations_partial` | Generate subset of combinations |
| `test_import_products_create` | Import CSV with new SKUs → products created |
| `test_import_products_update` | Import CSV with existing SKUs → products updated |
| `test_import_products_validation_error` | Import CSV with invalid row → error in result |
| `test_import_products_max_rows` | Import CSV with >1000 rows → exception |
| `test_export_products_csv` | Export products → valid CSV string |
| `test_non_trackable_product_excluded_from_inventory` | is_trackable=false → not in inventory queries |

### 2.2 CategoryService (Phase1CatalogTest.php — continued)

| Test | Description |
|------|-------------|
| `test_create_root_category` | Create category with no parent |
| `test_create_child_category` | Create category with parent_id |
| `test_create_category_cycle_prevention` | parent_id = own descendant → exception |
| `test_create_category_self_parent` | parent_id = own id → exception |
| `test_delete_category_with_children_blocked` | Category has sub-categories → 422 |
| `test_delete_category_with_products_blocked` | Category has products → 422 |
| `test_delete_leaf_category_success` | Category with no children and no products → deleted |
| `test_move_category_to_new_parent` | Update parent_id to valid new parent |
| `test_move_category_creates_cycle` | Update parent_id to descendant → 422 |
| `test_category_tree_building` | Get tree → nested structure with children |
| `test_category_slug_auto_generated` | Create category → slug matches name |
| `test_category_slug_unique_per_tenant` | Two categories same name in same tenant → unique slugs |

### 2.3 PriceListService (Phase1PriceListTest.php)

| Test | Description |
|------|-------------|
| `test_create_price_list` | Create price list with name and description |
| `test_set_default_price_list` | Set is_default=true → previous default unset |
| `test_only_one_default_per_tenant` | Two defaults → only one remains |
| `test_add_price_list_item` | Add item with product_id and price |
| `test_add_price_list_item_variant` | Add item with variant_id and price |
| `test_add_duplicate_item_rejected` | Same (price_list_id, product_id, variant_id) → exception |
| `test_resolve_price_from_list` | Product in price list → returns list price |
| `test_resolve_price_fallback_to_product` | Product not in list → returns product.selling_price |
| `test_resolve_price_variant_override` | Variant with price_override → returns override |
| `test_resolve_price_variant_no_override` | Variant without override → inherits product price |
| `test_delete_price_list_cascades_items` | Delete list → items also deleted |
| `test_cannot_deactivate_default_list` | is_default=true → is_active cannot be false |

### 2.4 UnitService (Phase1UnitTest.php)

| Test | Description |
|------|-------------|
| `test_create_unit` | Create unit with name and symbol |
| `test_create_unit_unique_symbol_per_tenant` | Duplicate symbol → exception |
| `test_add_conversion` | Add conversion factor between units |
| `test_convert_quantity` | Convert 2 dus to pcs (factor 12) → 24 |
| `test_convert_reverse` | Convert 24 pcs to dus (factor 1/12) → 2 |
| `test_convert_no_conversion_defined` | No conversion exists → exception |
| `test_delete_unit_in_use_blocked` | Unit assigned to product → 422 |
| `test_delete_unit_not_in_use` | Unit not assigned → deleted |

---

## 3. API Tests (Controller Level)

### 3.1 Product API (Phase1CatalogTest.php — API section)

| Test | Description |
|------|-------------|
| `test_api_list_products` | GET /products returns paginated list |
| `test_api_list_products_with_filters` | Filter by category, is_active, search |
| `test_api_show_product_with_variants` | GET /products/{id} includes variants, images, barcodes |
| `test_api_create_simple_product` | POST /products with basic fields → 201 |
| `test_api_create_product_with_variants` | POST /products with variant_options + variants → 201 |
| `test_api_create_product_validation_error` | Missing required fields → 422 |
| `test_api_update_product` | PUT /products/{id} → 200 |
| `test_api_delete_product` | DELETE /products/{id} → 200 |
| `test_api_products_permission_view` | Staff can GET /products → 200 |
| `test_api_products_permission_manage_blocked` | Cashier POST /products → 403 |
| `test_api_product_tenant_isolation` | Product from tenant B → 404 for tenant A |
| `test_api_product_export_csv` | GET /products/export → text/csv content type |
| `test_api_product_import_csv` | POST /products/import with file → 200 with summary |
| `test_api_product_import_max_rows` | CSV >1000 rows → 422 |
| `test_api_product_barcode_lookup` | GET /products/lookup?barcode=X → product found |
| `test_api_product_barcode_lookup_not_found` | GET /products/lookup?barcode=INVALID → 404 |
| `test_api_product_barcode_lookup_tenant_scoped` | Barcode from tenant B → 404 for tenant A |

### 3.2 Category API

| Test | Description |
|------|-------------|
| `test_api_list_categories` | GET /categories → paginated list |
| `test_api_category_tree` | GET /categories/tree → nested tree |
| `test_api_create_category_with_parent` | POST /categories with parent_id → 201 |
| `test_api_create_category_cycle` | POST /categories with parent_id = self → 422 |
| `test_api_update_category_move` | PUT /categories/{id} with new parent_id → 200 |
| `test_api_update_category_cycle` | PUT /categories/{id} parent_id = descendant → 422 |
| `test_api_delete_category_with_children` | DELETE /categories/{id} with sub-categories → 422 |
| `test_api_delete_category_with_products` | DELETE /categories/{id} with products → 422 |
| `test_api_delete_category_success` | DELETE /categories/{id} empty → 200 |
| `test_api_categories_permission_manage_blocked` | Cashier POST /categories → 403 |

### 3.3 Variant API

| Test | Description |
|------|-------------|
| `test_api_list_variants` | GET /products/{id}/variants → list |
| `test_api_create_variant` | POST /products/{id}/variants → 201 |
| `test_api_update_variant` | PUT /products/{id}/variants/{vid} → 200 |
| `test_api_delete_variant_with_sales` | DELETE variant with sale_items → 422 |
| `test_api_delete_variant_success` | DELETE variant with no sales → 200 |
| `test_api_generate_combinations` | POST /products/{id}/variants/generate → combinations |
| `test_api_variant_tenant_isolation` | Variants from tenant B product → 404 |

### 3.4 Price List API

| Test | Description |
|------|-------------|
| `test_api_list_price_lists` | GET /price-lists → list |
| `test_api_create_price_list` | POST /price-lists → 201 |
| `test_api_set_default` | PUT /price-lists/{id} is_default=true → 200, previous unset |
| `test_api_add_price_list_item` | POST /price-lists/{id}/items → 201 |
| `test_api_update_price_list_item` | PUT /price-lists/{id}/items/{iid} → 200 |
| `test_api_delete_price_list_item` | DELETE /price-lists/{id}/items/{iid} → 200 |
| `test_api_delete_price_list` | DELETE /price-lists/{id} → 200, items cascaded |
| `test_api_price_list_tenant_isolation` | Price list from tenant B → 404 |

### 3.5 Unit API

| Test | Description |
|------|-------------|
| `test_api_list_units` | GET /units → list |
| `test_api_create_unit` | POST /units → 201 |
| `test_api_update_unit` | PUT /units/{id} → 200 |
| `test_api_delete_unit_in_use` | DELETE unit assigned to product → 422 |
| `test_api_delete_unit_success` | DELETE unit not in use → 200 |
| `test_api_add_conversion` | POST /units/conversions → 201 |
| `test_api_delete_conversion` | DELETE /units/conversions/{id} → 200 |

---

## 4. Integration Tests

| Test | Description |
|------|-------------|
| `test_product_with_variants_in_inventory` | Product with variants → inventory tracks per variant (Phase 2 prep — verify schema supports it) |
| `test_non_trackable_product_not_in_inventory` | is_trackable=false → excluded from inventory list |
| `test_product_min_stock_in_dashboard` | Product with min_stock → appears in low-stock dashboard widget |
| `test_category_hierarchy_in_product_filter` | Filter by parent category → includes products from child categories |
| `test_pos_checkout_with_variant_product` | POS checkout with variant product → sale_item records variant_id (verify schema, actual POS integration is Phase 4) |
| `test_existing_pos_checkout_unchanged` | POS checkout with simple product → same behavior as before Phase 1 |
| `test_audit_log_product_created` | Create product → audit_logs table has entry |
| `test_audit_log_product_imported` | Import CSV → audit_logs table has entry with summary |

---

## 5. Migration Tests (Phase1MigrationTest.php)

| Test | Description |
|------|-------------|
| `test_units_table_exists` | Table `units` exists with expected columns |
| `test_unit_conversions_table_exists` | Table `unit_conversions` exists with FKs |
| `test_products_has_new_columns` | `has_variants`, `is_trackable`, `min_stock`, `base_unit_id`, `purchase_unit_id` columns exist |
| `test_categories_has_new_columns` | `parent_id`, `sort_order` columns exist |
| `test_product_variant_options_table_exists` | Table exists with product_id FK |
| `test_product_variant_option_values_table_exists` | Table exists with option_id FK |
| `test_product_variants_table_exists` | Table exists with product_id FK |
| `test_product_variant_values_table_exists` | Pivot table exists with variant_id + option_value_id FKs |
| `test_product_barcodes_table_exists` | Table exists with tenant_id, product_id, variant_id FKs |
| `test_product_images_table_exists` | Table exists with product_id FK |
| `test_price_lists_table_exists` | Table exists with tenant_id FK |
| `test_price_list_items_table_exists` | Table exists with price_list_id, product_id, variant_id FKs |
| `test_existing_products_preserved` | Existing products still have correct data after migration |
| `test_existing_categories_preserved` | Existing categories still have correct data after migration |
| `test_existing_sku_unique_index_preserved` | unique(tenant_id, sku) still exists on products table |

---

## 6. E2E Tests (Playwright — phase1.spec.ts)

### Test Users

Uses existing `E2E_USERS.owner` and `E2E_USERS.staff` from `frontend/e2e/helpers.ts`.

### Test Specs

| # | Test | Description |
|---|------|-------------|
| 1 | `owner can create simple product` | Login as owner → navigate to /products → click add → fill form → submit → product appears in list |
| 2 | `owner can create category with parent` | Login as owner → navigate to /categories → click add → fill name + select parent → submit → category appears in tree |
| 3 | `owner can create product with variants` | Login as owner → navigate to /products → click add → check has_variants → define options → generate combinations → fill variant SKUs → submit → product shows variant badge |
| 4 | `owner can import products via CSV` | Login as owner → navigate to /products → click import → upload CSV → see summary with created count |
| 5 | `owner can export products` | Login as owner → navigate to /products → click export → CSV file downloads |
| 6 | `owner can manage price lists` | Login as owner → navigate to /price-lists → create list → add items → verify items visible |
| 7 | `owner can manage units` | Login as owner → navigate to /units → create unit → add conversion → verify conversion visible |
| 8 | `staff can view products but not manage` | Login as staff → navigate to /products → see list → no add/edit/delete buttons |
| 9 | `staff cannot access price list management` | Login as staff → navigate to /price-lists → no add button visible |
| 10 | `category tree displays hierarchy` | Login as owner → navigate to /categories → see indented tree structure |

### E2E Test Considerations

- Tests merged where possible to stay under auth rate limiter (5 req/min)
- `waitForLoadState('networkidle')` after navigation
- Locators scoped to `page.locator('main')` to avoid strict mode violations
- CSV import test uses a pre-generated temp file
- CSV export test verifies download by checking response content-type

---

## 7. Test Data

### Seeders

| Seeder | Purpose |
|--------|---------|
| `RbacSeeder` (updated) | Add `products.view`, `products.manage`, `categories.view`, `categories.manage` permissions |
| `ModuleSeeder` (updated) | Add `core.variants`, `core.price_lists`, `core.units`, `core.import_export` features |
| `E2ESeeder` (updated) | Add: 3-level category hierarchy, 1 product with variants (2 options, 4 variants), 1 price list with 3 items, 2 units with 1 conversion |

### Factories

| Factory | Usage |
|---------|-------|
| `ProductFactory` (updated) | Support `has_variants`, `is_trackable`, `min_stock` fields |
| `CategoryFactory` (updated) | Support `parent_id` field |
| `UnitFactory` (new) | Generate test units |
| `PriceListFactory` (new) | Generate test price lists |
| `ProductVariantFactory` (new) | Generate test variants |

---

## 8. Coverage Target

| Area | Target | Verification |
|------|--------|--------------|
| Service layer | 90% | Unit tests cover all public methods |
| API endpoints | 100% | Every endpoint has at least one test |
| Validation rules | 90% | Each validation rule has a pass + fail test |
| Permission checks | 100% | Each role tested for each endpoint |
| Tenant isolation | 100% | Cross-tenant access tested for each entity |
| Migration | 100% | All new tables/columns verified |
| E2E flows | 80% | Primary user flows covered |
| Regression | 100% | All existing tests pass unchanged |

---

## 9. Regression Test Strategy

### Backend (793 existing tests)

- Run full suite: `php artisan test --env=testing`
- All 793 existing tests must pass without modification
- If any existing test breaks → Phase 1 is NOT COMPLETE
- Existing tests that hardcode product/category fields may need update if they assert on response structure (but test logic should not change)

### E2E (25 existing tests)

- Run full suite: `npx playwright test e2e/pos-flow.spec.ts e2e/security.spec.ts e2e/phase0.spec.ts --reporter=list`
- All 25 existing E2E tests must pass
- POS flow tests must still work (products without variants behave identically)
- Security tests must still work (permission middleware now on product/category routes — verify staff still gets 403/redirect)

### Pre-Implementation Check

Before any code changes:
1. Run `php artisan test` → confirm 793 pass
2. Run `npx playwright test` → confirm 25 pass
3. Record baseline

### Post-Implementation Check

After all Phase 1 implementation:
1. Run `php artisan test` → confirm 793 existing + new Phase 1 tests pass
2. Run `npx playwright test` → confirm 25 existing + new Phase 1 E2E tests pass
3. Run `php artisan migrate:fresh --seed` → confirm clean seed works
4. Verify frontend build: `npm run build` → success

---

*End of Phase 1 Testing*
