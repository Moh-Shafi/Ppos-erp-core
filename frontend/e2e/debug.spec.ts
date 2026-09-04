import { test, expect } from '@playwright/test'

test('debug: login and check API', async ({ page }) => {
  page.on('console', (msg) => console.log('CONSOLE:', msg.type(), msg.text()))
  page.on('requestfailed', (req) => console.log('REQ FAILED:', req.url(), req.failure()?.errorText))
  page.on('request', (req) => {
    if (req.url().includes('/api/')) {
      console.log('REQ:', req.method(), req.url().replace('http://localhost:5173', ''))
    }
  })
  page.on('response', (res) => {
    if (res.url().includes('/api/')) {
      console.log('API:', res.status(), res.url().replace('http://localhost:5173', ''))
    }
  })

  await page.goto('/login')
  await page.fill('input[type="email"]', 'e2e.owner@test.com')
  await page.fill('input[type="password"]', 'password123')
  await page.click('button[type="submit"]')
  await page.waitForURL('**/dashboard')
  await page.waitForLoadState('networkidle')

  const token = await page.evaluate(() => localStorage.getItem('auth_token'))
  console.log('AUTH TOKEN:', token ? 'present' : 'missing')

  const storeId = await page.evaluate(() => localStorage.getItem('current_store_id'))
  console.log('STORE ID:', storeId)

  // Navigate to POS
  await page.click('a[href="/pos"]')
  await page.waitForURL('**/pos')
  await page.waitForTimeout(15000)

  const bodyText = await page.locator('body').innerText()
  console.log('PAGE TEXT (first 1000):', bodyText.substring(0, 1000))

  // Check if products loaded
  const productButtons = await page.getByRole('button', { name: /E2E/ }).count()
  console.log('PRODUCT BUTTONS COUNT:', productButtons)
})

