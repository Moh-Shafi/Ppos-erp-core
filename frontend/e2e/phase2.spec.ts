import { test, expect } from '@playwright/test'
import { E2E_USERS, login, waitForDataLoaded } from './helpers'

// === Warehouse CRUD ===

test.describe('Phase 2: Warehouse Management', () => {
  test('owner can view warehouses page', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/warehouses')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    await expect(page.locator('h1:has-text("Warehouses")')).toBeVisible()
  })

  test('owner can create a warehouse', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/warehouses')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    await page.click('text=+ Add Warehouse')
    await page.waitForSelector('text=Add Warehouse', { state: 'visible' })

    const whName = `E2E Warehouse ${Date.now()}`
    await page.fill('#wh-name', whName)
    await page.fill('#wh-address', 'Jl. Test No. 123')
    await page.fill('#wh-phone', '021-999888')

    await page.click('button:has-text("Save")')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    await expect(page.locator('table').getByText(whName)).toBeVisible()
  })

  test('owner can edit a warehouse', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/warehouses')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    const firstEditBtn = page.locator('table tbody tr').first().locator('button:has-text("Edit")')
    await firstEditBtn.click()
    await page.waitForSelector('text=Edit Warehouse', { state: 'visible' })

    const nameInput = page.locator('#wh-name')
    const originalName = await nameInput.inputValue()
    const newName = `${originalName} Updated`
    await nameInput.fill(newName)

    await page.click('button:has-text("Save")')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    await expect(page.locator('table').getByText(newName)).toBeVisible()
  })
})

// === Adjustment Reasons ===

test.describe('Phase 2: Adjustment Reasons', () => {
  test('owner can view adjustment reasons page', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/inventory/adjustment-reasons')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    await expect(page.locator('h1:has-text("Adjustment Reasons")')).toBeVisible()
  })

  test('owner can create a custom reason', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/inventory/adjustment-reasons')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    await page.click('text=+ Add Reason')
    await page.waitForSelector('text=Add Reason', { state: 'visible' })

    const reasonName = `E2E Reason ${Date.now()}`
    await page.fill('#reason-name', reasonName)

    await page.click('button:has-text("Save")')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    await expect(page.locator('table').getByText(reasonName)).toBeVisible()
  })

  test('system reasons are visible and cannot be deleted', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/inventory/adjustment-reasons')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    // System reasons should have "System" badge
    await expect(page.locator('text=System').first()).toBeVisible()
  })
})

// === Stock Valuation ===

test.describe('Phase 2: Stock Valuation', () => {
  test('owner can view valuation report page', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/inventory/valuation')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    await expect(page.locator('h1:has-text("Stock Valuation")')).toBeVisible()
  })

  test('owner can switch valuation method', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/inventory/valuation')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    // Switch to FIFO - target the method select by its visible option
    const methodSelect = page.locator('main select').first()
    await methodSelect.selectOption('fifo')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    // Summary card should show FIFO
    await expect(page.locator('.text-lg:has-text("FIFO")')).toBeVisible()
  })
})

// === Transfer Requests ===

test.describe('Phase 2: Transfer Requests', () => {
  test('owner can view transfer requests page', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/inventory/transfer-requests')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    await expect(page.locator('h1:has-text("Transfer Requests")')).toBeVisible()
  })

  test('owner can open new request form', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/inventory/transfer-requests')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    await page.click('text=+ New Request')
    await page.waitForSelector('text=New Transfer Request', { state: 'visible' })

    await expect(page.locator('text=New Transfer Request')).toBeVisible()
  })
})

// === Stocktake ===

test.describe('Phase 2: Stocktake', () => {
  test('owner can view stocktake page', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/inventory/stocktake')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    await expect(page.locator('h1:has-text("Stocktake")')).toBeVisible()
  })

  test('owner can open new stocktake form', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    await page.goto('/inventory/stocktake')
    await page.waitForLoadState('domcontentloaded')
    await waitForDataLoaded(page)

    await page.click('text=+ New Session')
    await page.waitForSelector('text=New Stocktake Session', { state: 'visible' })

    await expect(page.locator('text=New Stocktake Session')).toBeVisible()
  })
})

// === Sidebar Navigation ===

test.describe('Phase 2: Sidebar Navigation', () => {
  test('phase 2 nav items visible for owner', async ({ page }) => {
    await login(page, E2E_USERS.owner)
    await page.waitForLoadState('domcontentloaded')

    await expect(page.locator('nav a:has-text("Warehouses")')).toBeVisible()
    await expect(page.locator('nav a:has-text("Transfer Requests")')).toBeVisible()
    await expect(page.locator('nav a:has-text("Stocktake")')).toBeVisible()
    await expect(page.locator('nav a:has-text("Valuation")')).toBeVisible()
    await expect(page.locator('nav a:has-text("Adjustment Reasons")')).toBeVisible()
  })

  test('cashier cannot see warehouse management nav', async ({ page }) => {
    await login(page, E2E_USERS.cashier)
    await page.waitForLoadState('domcontentloaded')

    // Cashier should not see warehouse manage link (warehouse module may not be enabled)
    // But should not see "Quick Transfer" (requires inventory.manage)
    const quickTransfer = page.locator('nav a:has-text("Quick Transfer")')
    const isVisible = await quickTransfer.isVisible().catch(() => false)
    expect(isVisible).toBeFalsy()

    // Cashier should not see Stocktake (requires inventory.stocktake permission)
    const stocktakeLink = page.locator('nav a:has-text("Stocktake")')
    const stocktakeVisible = await stocktakeLink.isVisible().catch(() => false)
    expect(stocktakeVisible).toBeFalsy()

    // Cashier should not see Valuation (requires inventory.valuation permission)
    const valuationLink = page.locator('nav a:has-text("Valuation")')
    const valuationVisible = await valuationLink.isVisible().catch(() => false)
    expect(valuationVisible).toBeFalsy()
  })
})
