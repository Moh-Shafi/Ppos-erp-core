import { test, expect, login, E2E_USERS } from './helpers'

test.describe('Phase 7 - Reports', () => {
  test('reports dashboard loads for owner', async ({ authedPage }) => {
    await authedPage.goto('/reports')
    await expect(authedPage.locator('h1:has-text("Reports")')).toBeVisible()
    await expect(authedPage.locator('text=Select a report to begin')).toBeVisible()
  })

  test('sales report loads and CSV export triggers download', async ({ authedPage }) => {
    await authedPage.goto('/reports/sales')
    await expect(authedPage.locator('h1:has-text("Reports")')).toBeVisible()

    // Wait for the report table to appear (initial load with no filters)
    await authedPage.waitForSelector('table', { timeout: 60000 })

    // Trigger CSV export
    const [download] = await Promise.all([
      authedPage.waitForEvent('download'),
      authedPage.click('button:has-text("CSV")'),
    ])

    expect(download.suggestedFilename()).toMatch(/sales\.(csv|xlsx|pdf)/)
  })

  test('staff is redirected away from reports', async ({ page }) => {
    await login(page, E2E_USERS.staff)
    await page.goto('/reports/sales')
    await page.waitForURL('**/dashboard')
  })
})
