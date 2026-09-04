import { test, expect, login, E2E_USERS } from './helpers'

test.describe('Phase 8 - Business-Specific Modules', () => {
  test('Phase 8 menu items are visible for owner', async ({ authedPage }) => {
    await authedPage.goto('/dashboard')
    await expect(authedPage.locator('text=Restoran')).toBeVisible()
    await expect(authedPage.locator('text=Retail')).toBeVisible()
    await expect(authedPage.locator('text=Service')).toBeVisible()
    await expect(authedPage.locator('text=Tables')).toBeVisible()
    await expect(authedPage.locator('text=Promotions')).toBeVisible()
    await expect(authedPage.locator('text=Appointments')).toBeVisible()
  })

  test('Tables page loads', async ({ authedPage }) => {
    await authedPage.goto('/tables')
    await expect(authedPage.locator('h1:has-text("Tables")')).toBeVisible({ timeout: 30000 })
  })

  test('Promotions page loads', async ({ authedPage }) => {
    await authedPage.goto('/promotions')
    await expect(authedPage.locator('h1:has-text("Promotions")')).toBeVisible({ timeout: 30000 })
  })

  test('Appointments page loads', async ({ authedPage }) => {
    await authedPage.goto('/appointments')
    await expect(authedPage.locator('h1:has-text("Appointments")')).toBeVisible({ timeout: 30000 })
  })

  test('staff cannot access promotions page', async ({ page }) => {
    await login(page, E2E_USERS.staff)
    await page.goto('/promotions')
    await page.waitForURL('**/dashboard', { timeout: 30000 })
  })
})
