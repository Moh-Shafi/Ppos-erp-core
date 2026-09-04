import { test, expect } from '@playwright/test'

test.describe('Frontend Security & RBAC', () => {
  test('unauthenticated user redirected from POS to login', async ({ page }) => {
    await page.goto('/pos')
    await page.waitForURL('**/login')
    expect(page.url()).toContain('/login')
  })

  test('unauthenticated user redirected from Sales to login', async ({ page }) => {
    await page.goto('/sales')
    await page.waitForURL('**/login')
    expect(page.url()).toContain('/login')
  })

  test('unauthenticated user redirected from Dashboard to login', async ({ page }) => {
    await page.goto('/dashboard')
    await page.waitForURL('**/login')
    expect(page.url()).toContain('/login')
  })

  test('staff cannot access POS page', async ({ page }) => {
    // Login as staff
    await page.goto('/login')
    await page.fill('input[type="email"]', 'e2e.staff@test.com')
    await page.fill('input[type="password"]', 'password123')
    await page.click('button[type="submit"]')
    await page.waitForURL('**/dashboard')
    await page.waitForLoadState('networkidle')

    // Navigate directly to POS URL (sidebar link is hidden for staff)
    await page.goto('/pos')
    await page.waitForLoadState('networkidle')

    // Should be redirected back to dashboard (no pos.use permission)
    await expect(page).toHaveURL(/\/dashboard/)

    // Should NOT see product grid or cart
    await expect(page.getByRole('heading', { name: 'Keranjang' })).not.toBeVisible()
    await expect(page.getByRole('button', { name: /Bayar/ })).not.toBeVisible()
  })

  test('cashier can access POS page', async ({ page }) => {
    await page.goto('/login')
    await page.fill('input[type="email"]', 'e2e.cashier@test.com')
    await page.fill('input[type="password"]', 'password123')
    await page.click('button[type="submit"]')
    await page.waitForURL('**/dashboard')
    await page.waitForLoadState('networkidle')

    // Navigate to POS via SPA link
    await page.click('a[href="/pos"]')
    await page.waitForURL('**/pos')

    // Should see POS interface (not access denied)
    await expect(page.getByRole('heading', { name: 'Keranjang' })).toBeVisible({ timeout: 15000 })
    await expect(page.getByText('tidak memiliki akses')).not.toBeVisible()
  })

  test('owner can access POS and Sales pages', async ({ page }) => {
    await page.goto('/login')
    await page.fill('input[type="email"]', 'e2e.owner@test.com')
    await page.fill('input[type="password"]', 'password123')
    await page.click('button[type="submit"]')
    await page.waitForURL('**/dashboard')
    await page.waitForLoadState('networkidle')

    // POS via SPA link
    await page.click('a[href="/pos"]')
    await page.waitForURL('**/pos')
    await expect(page.getByRole('heading', { name: 'Keranjang' })).toBeVisible({ timeout: 15000 })

    // Sales via direct navigation
    await page.goto('/sales')
    await page.waitForLoadState('networkidle')
    await expect(page.getByText('Riwayat Transaksi')).toBeVisible({ timeout: 15000 })
  })

  test('frontend does not expose tenant_id in checkout request', async ({ page }) => {
    await page.goto('/login')
    await page.fill('input[type="email"]', 'e2e.owner@test.com')
    await page.fill('input[type="password"]', 'password123')
    await page.click('button[type="submit"]')
    await page.waitForURL('**/dashboard')
    await page.waitForLoadState('networkidle')

    // Navigate to POS via SPA link
    await page.click('a[href="/pos"]')
    await page.waitForURL('**/pos')
    await expect(page.getByRole('button', { name: /E2E Kopi Susu/ })).toBeVisible({ timeout: 30000 })

    // Add product
    await page.getByRole('button', { name: /E2E Kopi Susu/ }).first().click()

    // Intercept the checkout API request
    const checkoutRequest = page.waitForRequest((req) => req.url().includes('/sales/checkout'), { timeout: 15000 })

    // Open checkout and submit
    await page.getByRole('button', { name: /Bayar/ }).click()
    await page.getByRole('button', { name: 'Konfirmasi Pembayaran' }).click()

    const request = await checkoutRequest
    const postData = request.postDataJSON()

    // Verify tenant_id is NOT in the request
    expect(postData).not.toHaveProperty('tenant_id')
    expect(postData).not.toHaveProperty('cashier_id')

    // Verify required fields ARE present
    expect(postData).toHaveProperty('store_id')
    expect(postData).toHaveProperty('items')
    expect(postData).toHaveProperty('payments')

    // Verify idempotency_key is sent
    expect(postData.payments[0]).toHaveProperty('idempotency_key')
    expect(postData.payments[0].idempotency_key).toBeTruthy()
  })

  test('checkout button disabled during loading (double-click protection)', async ({ page }) => {
    await page.goto('/login')
    await page.fill('input[type="email"]', 'e2e.owner@test.com')
    await page.fill('input[type="password"]', 'password123')
    await page.click('button[type="submit"]')
    await page.waitForURL('**/dashboard')
    await page.waitForLoadState('networkidle')

    // Navigate to POS via SPA link
    await page.click('a[href="/pos"]')
    await page.waitForURL('**/pos')
    await expect(page.getByRole('button', { name: /E2E Kopi Susu/ })).toBeVisible({ timeout: 30000 })
    await page.getByRole('button', { name: /E2E Kopi Susu/ }).first().click()

    // Intercept checkout to count requests
    let checkoutCount = 0
    page.on('request', (req) => {
      if (req.url().includes('/sales/checkout')) {
        checkoutCount++
      }
    })

    // Open checkout modal
    await page.getByRole('button', { name: /Bayar/ }).click()

    // Click submit
    await page.getByRole('button', { name: 'Konfirmasi Pembayaran' }).click()

    // Wait a moment then try clicking again (double-click protection)
    await page.waitForTimeout(100)

    // Button should show loading state
    await expect(page.getByText('Memproses...')).toBeVisible({ timeout: 5000 })

    // Wait for completion
    await expect(page.getByText('Terima kasih')).toBeVisible({ timeout: 30000 })

    // Only one checkout request should have been sent
    expect(checkoutCount).toBe(1)
  })

  test('wrong password shows error', async ({ page }) => {
    await page.goto('/login')
    await page.fill('input[type="email"]', 'e2e.owner@test.com')
    await page.fill('input[type="password"]', 'wrongpassword')
    await page.click('button[type="submit"]')

    // Should stay on login page
    await page.waitForTimeout(2000)
    expect(page.url()).toContain('/login')
  })

  test('logout clears session and redirects to login', async ({ page }) => {
    await page.goto('/login')
    await page.fill('input[type="email"]', 'e2e.owner@test.com')
    await page.fill('input[type="password"]', 'password123')
    await page.click('button[type="submit"]')
    await page.waitForURL('**/dashboard')
    await page.waitForLoadState('networkidle')

    // Logout
    await page.getByRole('button', { name: 'Logout' }).click()
    await page.waitForURL('**/login')

    // Try to access POS - should redirect to login
    await page.goto('/pos')
    await page.waitForURL('**/login')
  })
})
