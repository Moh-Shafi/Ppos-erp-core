import { test as base, expect, type Page } from '@playwright/test'

export interface E2EUser {
  email: string
  password: string
  name: string
}

export const E2E_USERS = {
  owner: { email: 'e2e.owner@test.com', password: 'password123', name: 'E2E Owner' },
  cashier: { email: 'e2e.cashier@test.com', password: 'password123', name: 'E2E Cashier' },
  staff: { email: 'e2e.staff@test.com', password: 'password123', name: 'E2E Staff' },
}

export const E2E_PRODUCTS = {
  kopi: { name: 'E2E Kopi Susu', sku: 'E2E-KS-001', price: 8000 },
  esTeh: { name: 'E2E Es Teh', sku: 'E2E-ET-002', price: 5000 },
  air: { name: 'E2E Air Mineral', sku: 'E2E-AM-003', price: 3000 },
  variant: { name: 'E2E Kopi Variant', sku: 'E2E-KV-001', price: 8000 },
}

export const E2E_VARIANTS = {
  regular: { sku: 'E2E-KV-001-R', label: 'Regular', price: 8000 },
  large: { sku: 'E2E-KV-001-L', label: 'Large', price: 12000 },
}

export const E2E_CUSTOMERS = {
  regular: { name: 'E2E Pelanggan', phone: '08123456789' },
  credit: { name: 'E2E Pelanggan Kredit', phone: '08123456790', creditLimit: 50000, outstanding: 10000 },
}

export const E2E_DISCOUNT_PRESETS = {
  percentage: { name: 'E2E Diskon 10%', type: 'percentage', value: 10 },
  fixed: { name: 'E2E Diskon Rp 2000', type: 'fixed', value: 2000 },
}

export async function login(page: Page, user: E2EUser) {
  await page.goto('/login')
  await page.fill('input[type="email"]', user.email)
  await page.fill('input[type="password"]', user.password)
  await page.click('button[type="submit"]')
  await page.waitForURL('**/dashboard', { timeout: 120000 })
  await page.waitForLoadState('domcontentloaded')
}

export async function waitForDataLoaded(page: Page, timeout = 30000) {
  // Wait for any loading indicators to disappear and content to be present
  const loading = page.locator('text=Loading...').first()
  try {
    const isVisible = await loading.isVisible().catch(() => false)
    if (isVisible) {
      await loading.waitFor({ state: 'hidden', timeout })
    }
  } catch {
    // Loading indicator was not present or already gone
  }
}

export async function logout(page: Page) {
  await page.click('text=Logout')
  await page.waitForURL('**/login')
}

export async function resetInventory() {
  // Call backend API to reset inventory via a test endpoint or re-seed
  // For E2E, we re-seed the inventory data
}

// Extended test fixture with auto-login
export const test = base.extend<{ authedPage: Page }>({
  authedPage: async ({ page }, use) => {
    await login(page, E2E_USERS.owner)
    await use(page)
  },
})

export { expect }
