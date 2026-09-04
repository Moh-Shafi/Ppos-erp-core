import { test, expect } from '@playwright/test'
import { E2E_USERS, login } from './helpers'

test.describe('Phase 0: Registration with Business Type', () => {
  test('register page shows business type dropdown', async ({ page }) => {
    await page.goto('/register')
    await page.waitForLoadState('networkidle')
    await expect(page.locator('#business_type_id')).toBeVisible()
  })

  test('register with restaurant business type', async ({ page }) => {
    await page.goto('/register')
    await page.waitForLoadState('networkidle')

    const uniqueEmail = `phase0.resto.${Date.now()}@test.com`

    await page.fill('#name', 'Phase0 Resto Owner')
    await page.fill('#email', uniqueEmail)
    await page.fill('#store_name', 'Phase0 Resto Test')
    await page.selectOption('#business_type_id', { label: 'Restaurant' })
    await page.fill('#password', 'password123')
    await page.fill('#password_confirmation', 'password123')
    await page.click('button[type="submit"]')

    await page.waitForURL('**/dashboard', { timeout: 30000 })
    await page.waitForLoadState('networkidle')

    // Dashboard main area should show business profile info
    await expect(page.locator('main').getByText('Phase0 Resto Test')).toBeVisible()
  })

  test('register with general business type', async ({ page }) => {
    await page.goto('/register')
    await page.waitForLoadState('networkidle')

    const uniqueEmail = `phase0.general.${Date.now()}@test.com`

    await page.fill('#name', 'Phase0 General Owner')
    await page.fill('#email', uniqueEmail)
    await page.fill('#store_name', 'Phase0 General Test')
    await page.selectOption('#business_type_id', { label: 'General' })
    await page.fill('#password', 'password123')
    await page.fill('#password_confirmation', 'password123')
    await page.click('button[type="submit"]')

    await page.waitForURL('**/dashboard', { timeout: 30000 })
    await page.waitForLoadState('networkidle')
    await expect(page.locator('main').getByText('Phase0 General Test')).toBeVisible()
  })
})

test.describe('Phase 0: Module-Aware Navigation (RBAC)', () => {
  test('owner sees all restaurant modules in sidebar', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('networkidle')

    const sidebar = page.locator('aside nav')
    await expect(sidebar.getByText('Kasir / POS')).toBeVisible()
    await expect(sidebar.getByText('Inventory')).toBeVisible()
    await expect(sidebar.getByText('Customers')).toBeVisible()
    await expect(sidebar.getByText('Dashboard')).toBeVisible()
  })

  test('staff sees limited nav items', async ({ page }) => {
    await login(page, E2E_USERS.staff)
    await page.waitForLoadState('networkidle')

    const sidebar = page.locator('aside nav')

    // Staff should see Dashboard (core module, no permission required)
    await expect(sidebar.getByText('Dashboard')).toBeVisible()

    // Staff should NOT see POS (no pos.use permission)
    await expect(sidebar.getByText('Kasir / POS')).not.toBeVisible()

    // Staff should NOT see Purchases (no purchases.view permission)
    await expect(sidebar.getByText('Purchases')).not.toBeVisible()

    // Staff should NOT see Suppliers (no suppliers.view permission)
    await expect(sidebar.getByText('Suppliers')).not.toBeVisible()
  })
})

test.describe('Phase 0: Store Switcher & Dashboard', () => {
  test('owner can switch stores and see real dashboard data', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('networkidle')

    // --- Store Switcher ---
    const storeSelect = page.locator('aside select')
    await expect(storeSelect).toBeVisible()

    const options = await storeSelect.locator('option').allTextContents()
    expect(options.length).toBeGreaterThanOrEqual(2)
    expect(options.some((o) => o.includes('Utama'))).toBeTruthy()
    expect(options.some((o) => o.includes('Cabang'))).toBeTruthy()

    // Select second store
    await storeSelect.selectOption({ label: 'E2E Store Cabang' })
    await expect(storeSelect).toHaveValue(/.+/)

    // Switch back to first store for dashboard data
    await storeSelect.selectOption({ label: 'E2E Store Utama' })

    // --- Dashboard Stats ---
    await expect(page.locator('main').getByText('Penjualan Hari Ini')).toBeVisible()
    await expect(page.locator('main').getByText('Transaksi Hari Ini')).toBeVisible()
    await expect(page.locator('main').getByText('Total Produk')).toBeVisible()
    await expect(page.locator('main').getByText('Total Customers')).toBeVisible()

    // --- Business Profile ---
    await expect(page.locator('main').getByText('E2E Test Toko')).toBeVisible()
  })
})

test.describe('Phase 0: Business Types API (Public)', () => {
  test('business types endpoint returns data without auth', async ({ request }) => {
    const response = await request.get('/api/v1/business-types')
    expect(response.ok()).toBeTruthy()
    const body = await response.json()
    expect(body.data.length).toBeGreaterThan(0)
    expect(body.data.some((bt: { name: string }) => bt.name === 'Restaurant')).toBeTruthy()
    expect(body.data.some((bt: { name: string }) => bt.name === 'General')).toBeTruthy()
  })
})
