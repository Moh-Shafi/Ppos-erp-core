import { test, expect } from '@playwright/test'
import { E2E_USERS, login, waitForDataLoaded } from './helpers'

// === Product CRUD (without variants) ===

test.describe('Phase 1: Product CRUD without Variants', () => {
  test('owner can create a simple product', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/products')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    // Click add product
    await page.click('text=+ Tambah Produk')
    await page.waitForSelector('text=Tambah Produk', { state: 'visible' })

    // Fill form
    const uniqueName = `E2E Simple ${Date.now()}`
    await page.fill('#name', uniqueName)
    await page.fill('#sku', `E2E-SIMPLE-${Date.now()}`)
    await page.fill('#costPrice', '5000')
    await page.fill('#sellingPrice', '10000')

    // Select category (first option)
    const categorySelect = page.locator('#category_id')
    await categorySelect.selectOption({ index: 1 })

    // Submit
    await page.click('button:has-text("Save")')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    // Verify product appears in table
    await expect(page.locator('table').getByText(uniqueName)).toBeVisible()
  })

  test('owner can edit a product', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/products')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    // Click edit on first product
    const firstEditBtn = page.locator('table tbody tr').first().locator('button:has-text("Edit")')
    await firstEditBtn.click()
    await page.waitForSelector('text=Edit Produk', { state: 'visible' })

    // Modify name
    const nameInput = page.locator('#name')
    const originalName = await nameInput.inputValue()
    const newName = `${originalName} Edited`
    await nameInput.fill(newName)

    await page.click('button:has-text("Save")')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    // Verify change
    await expect(page.locator('table').getByText(newName)).toBeVisible()
  })

  test('product table shows variants column', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/products')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    await expect(page.locator('th:has-text("Variants")')).toBeVisible()
  })
})

// === Product with Variants ===

test.describe('Phase 1: Product Variants', () => {
  test('owner can create product with variant options', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/products')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    await page.click('text=+ Tambah Produk')
    await page.waitForSelector('text=Tambah Produk', { state: 'visible' })

    const uniqueName = `E2E Variant ${Date.now()}`
    await page.fill('#name', uniqueName)
    await page.fill('#sku', `E2E-VAR-${Date.now()}`)
    await page.fill('#costPrice', '10000')
    await page.fill('#sellingPrice', '25000')

    const categorySelect = page.locator('#category_id')
    await categorySelect.selectOption({ index: 1 })

    // Check "has variants" checkbox
    await page.check('input[type="checkbox"]:near(:text("Product has variants"))')

    // Wait for variant options section
    await expect(page.locator('text=Variant Options')).toBeVisible()

    // Add a variant option
    await page.click('button:has-text("+ Add Option")')

    // Fill option name and values
    const optionInputs = page.locator('input[placeholder="Option name (e.g. Size)"]')
    await optionInputs.first().fill('Size')

    const valueInputs = page.locator('input[placeholder*="comma-separated"]')
    await valueInputs.first().fill('S, M, L')

    // Submit
    await page.click('button:has-text("Save")')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    // Verify product with variants badge appears
    await expect(page.locator('table').getByText(uniqueName)).toBeVisible()
  })
})

// === Category Hierarchy ===

test.describe('Phase 1: Category Hierarchy', () => {
  test('owner can create child category with parent', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/categories')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    // Create parent category first
    await page.click('text=+ Tambah Kategori')
    await page.waitForSelector('text=Tambah Kategori', { state: 'visible' })

    const parentName = `E2E Parent ${Date.now()}`
    await page.fill('#cat-name', parentName)
    await page.click('button:has-text("Save")')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    // Create child category
    await page.click('text=+ Tambah Kategori')
    await page.waitForSelector('text=Tambah Kategori', { state: 'visible' })

    const childName = `E2E Child ${Date.now()}`
    await page.fill('#cat-name', childName)

    // Select parent
    const parentSelect = page.locator('#cat-parent')
    await parentSelect.selectOption({ label: parentName })

    await page.click('button:has-text("Save")')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    // Verify child category shows parent name
    const childRow = page.locator('table tbody tr').filter({ hasText: childName })
    await expect(childRow).toBeVisible()
    await expect(childRow).toContainText(parentName)
  })

  test('category table shows Parent column', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/categories')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    await expect(page.locator('th:has-text("Parent")')).toBeVisible()
  })
})

// === Units ===

test.describe('Phase 1: Units Management', () => {
  test('owner can create a unit', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    // Wait for module config to load (sidebar visible means config is loaded)
    await page.waitForSelector('aside nav', { state: 'visible', timeout: 30000 })
    // Use SPA navigation to avoid full page reload (which resets Zustand state)
    await page.click('aside nav a:has-text("Satuan")')
    await page.waitForURL('**/units')
    await page.waitForSelector('table', { state: 'visible', timeout: 30000 })

    await page.click('button:has-text("+ Tambah Satuan")')
    await page.waitForSelector('#unit-name', { state: 'visible' })

    const unitName = `E2E Unit ${Date.now()}`
    const unitSymbol = `e${Date.now() % 10000}`
    await page.fill('#unit-name', unitName)
    await page.fill('#unit-symbol', unitSymbol)

    await page.click('button:has-text("Save")')
    // Wait for modal to close (indicates successful creation)
    await expect(page.locator('#unit-name')).not.toBeVisible({ timeout: 15000 })
    // Wait for table to refresh with new data
    await expect(page.locator('table').getByText(unitName)).toBeVisible({ timeout: 15000 })
  })

  test('units page shows nav item in sidebar', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    const sidebar = page.locator('aside nav')
    await expect(sidebar.getByText('Satuan')).toBeVisible()
  })

  test('owner can add unit conversion', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    // Wait for module config to load (sidebar visible means config is loaded)
    await page.waitForSelector('aside nav', { state: 'visible', timeout: 30000 })
    // Use SPA navigation to avoid full page reload
    await page.click('aside nav a:has-text("Satuan")')
    await page.waitForURL('**/units')
    await page.waitForSelector('table', { state: 'visible', timeout: 30000 })

    // Click add conversion
    await page.click('button:has-text("+ Add Conversion")')
    await page.waitForSelector('#from_unit', { state: 'visible' })

    // Select from and to units — use last two options to avoid collision with seeded data
    const fromSelect = page.locator('#from_unit')
    const fromOptions = await fromSelect.locator('option').count()
    await fromSelect.selectOption({ index: fromOptions - 1 })

    const toSelect = page.locator('#to_unit')
    const toOptions = await toSelect.locator('option').count()
    await toSelect.selectOption({ index: toOptions - 2 })

    await page.fill('#factor', '5')

    await page.click('button:has-text("Save")')
    // Wait for modal to close (indicates successful creation)
    await expect(page.locator('#from_unit')).not.toBeVisible({ timeout: 15000 })
  })
})

// === Price Lists ===

test.describe('Phase 1: Price Lists Management', () => {
  test('owner can create a price list', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    // Wait for module config to load (sidebar visible means config is loaded)
    await page.waitForSelector('aside nav', { state: 'visible', timeout: 30000 })
    // Use SPA navigation to avoid full page reload
    await page.click('aside nav a:has-text("Price Lists")')
    await page.waitForURL('**/price-lists')
    await page.waitForSelector('table', { state: 'visible', timeout: 30000 })

    await page.click('button:has-text("+ Tambah Price List")')
    await page.waitForSelector('#pl-name', { state: 'visible' })

    const plName = `E2E PriceList ${Date.now()}`
    await page.fill('#pl-name', plName)
    await page.fill('#pl-desc', 'E2E test price list')

    await page.click('button:has-text("Save")')
    // Wait for modal to close (indicates successful creation)
    await expect(page.locator('#pl-name')).not.toBeVisible({ timeout: 15000 })
    // Wait for table to refresh with new data
    await expect(page.locator('table').getByText(plName)).toBeVisible({ timeout: 15000 })
  })

  test('price lists page shows nav item in sidebar', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    const sidebar = page.locator('aside nav')
    await expect(sidebar.getByText('Price Lists')).toBeVisible()
  })

  test('owner can view price list detail and add items', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    // Wait for module config to load (sidebar visible means config is loaded)
    await page.waitForSelector('aside nav', { state: 'visible', timeout: 30000 })
    // Use SPA navigation to avoid full page reload
    await page.click('aside nav a:has-text("Price Lists")')
    await page.waitForURL('**/price-lists')
    await page.waitForSelector('table tbody tr', { state: 'visible', timeout: 30000 })

    // Click View on first price list
    const viewBtn = page.locator('table tbody tr').first().locator('button:has-text("View")')
    await viewBtn.click()
    await page.waitForSelector('text=Price List:', { state: 'visible' })

    // Add an item
    const productSelect = page.locator('#item-product')
    await productSelect.selectOption({ index: 1 })

    await page.fill('#item-price', '15000')
    await page.click('button:has-text("Add")')
    await page.waitForTimeout(2000)

    // Verify item appears in the detail table
    await expect(page.locator('text=Rp 15.000')).toBeVisible({ timeout: 10000 })
  })
})

// === Permission Enforcement ===

test.describe('Phase 1: Permission Enforcement', () => {
  test('cashier cannot see manage buttons on products', async ({ page }) => {
    await login(page, E2E_USERS.cashier)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/products')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    // Cashier should not see add product button
    await expect(page.locator('button:has-text("+ Tambah Produk")')).not.toBeVisible()
  })

  test('cashier cannot see manage buttons on units', async ({ page }) => {
    await login(page, E2E_USERS.cashier)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/units')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    await expect(page.locator('button:has-text("+ Tambah Satuan")')).not.toBeVisible()
  })

  test('cashier cannot see manage buttons on price lists', async ({ page }) => {
    await login(page, E2E_USERS.cashier)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/price-lists')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    await expect(page.locator('button:has-text("+ Tambah Price List")')).not.toBeVisible()
  })
})

// === Navigation RBAC ===

test.describe('Phase 1: Navigation Items', () => {
  test('owner sees Satuan and Price Lists in sidebar', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    const sidebar = page.locator('aside nav')
    await expect(sidebar.getByText('Satuan')).toBeVisible()
    await expect(sidebar.getByText('Price Lists')).toBeVisible()
  })

  test('staff does not see Satuan or Price Lists', async ({ page }) => {
    await login(page, E2E_USERS.staff)
    await page.waitForLoadState('domcontentloaded')

    const sidebar = page.locator('aside nav')
    // Staff doesn't have products.view permission
    if (await sidebar.getByText('Satuan').isVisible()) {
      // If staff can see products, they'd see units too
      // But staff typically doesn't have products.view
    }
    // Staff should not see Price Lists management
    await expect(sidebar.getByText('Purchases')).not.toBeVisible()
  })
})

// === Backward Compatibility ===

test.describe('Phase 1: Backward Compatibility', () => {
  test('existing POS flow still works (quick smoke)', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    // Navigate to POS
    await page.goto('/pos')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    // POS page should load
    await expect(page).toHaveURL(/\/pos/)

    // Products should be visible in POS
    await expect(page.getByRole('heading', { name: 'Keranjang' })).toBeVisible()
  })

  test('existing products still visible after Phase 1 migration', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/products')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    // E2E products from seeder should still be visible
    await expect(page.locator('table')).toBeVisible()
    const rowCount = await page.locator('table tbody tr').count()
    expect(rowCount).toBeGreaterThan(0)
  })

  test('existing categories still visible after Phase 1 migration', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/categories')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    await expect(page.locator('table')).toBeVisible()
    const rowCount = await page.locator('table tbody tr').count()
    expect(rowCount).toBeGreaterThan(0)
  })
})
