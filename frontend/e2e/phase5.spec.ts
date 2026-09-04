import { test, expect, type Page } from '@playwright/test'

async function loginAndGoToPOS(page: Page, email = 'e2e.owner@test.com') {
  await page.goto('/login')
  await page.fill('input[type="email"]', email)
  await page.fill('input[type="password"]', 'password123')
  await page.click('button[type="submit"]')
  await page.waitForURL('**/dashboard')
  await page.waitForLoadState('networkidle')
  await page.click('a[href="/pos"]')
  await page.waitForURL('**/pos')
}

async function waitForProducts(page: Page) {
  try {
    await expect(page.getByRole('button', { name: /E2E Kopi Susu/ })).toBeVisible({ timeout: 30000 })
  } catch {
    await page.reload()
    await page.waitForLoadState('networkidle')
    await expect(page.getByRole('button', { name: /E2E Kopi Susu/ })).toBeVisible({ timeout: 60000 })
  }
}

async function addProductToCart(page: Page, productName: string) {
  await page.getByRole('button', { name: new RegExp(productName) }).first().click()
}

test.describe('Phase 5 - Payment Infrastructure', () => {
  test.setTimeout(180000)

  test('cash payment completes and shows receipt', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)

    await addProductToCart(page, 'E2E Kopi Susu')

    await page.getByRole('button', { name: /Bayar/ }).click()
    await expect(page.getByRole('heading', { name: 'Pembayaran' })).toBeVisible()

    const totalText = await page.locator('text=Total Belanja').locator('..').locator('span').last().textContent()
    const total = parseInt(totalText!.replace(/\D/g, ''), 10)

    const amountInput = page.locator('input[type="number"]').first()
    await amountInput.fill(String(total))

    await page.getByRole('button', { name: 'Konfirmasi Pembayaran' }).click()

    await expect(page.locator('text=Terima kasih atas kunjungan Anda')).toBeVisible({ timeout: 30000 })
    await expect(page.getByRole('button', { name: 'Transaksi Baru' })).toBeVisible()
  })

  test('QRIS payment method can be selected at checkout', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)

    await addProductToCart(page, 'E2E Kopi Susu')

    await page.getByRole('button', { name: /Bayar/ }).click()
    await expect(page.getByRole('heading', { name: 'Pembayaran' })).toBeVisible()

    const totalText = await page.locator('text=Total Belanja').locator('..').locator('span').last().textContent()
    const total = parseInt(totalText!.replace(/\D/g, ''), 10)

    const methodSelect = page.locator('select:has(option[value="qris"])')
    await methodSelect.selectOption('qris')

    const amountInput = page.locator('input[type="number"]').first()
    await amountInput.fill(String(total))

    await page.getByRole('button', { name: 'Konfirmasi Pembayaran' }).click()

    await expect(page.locator('text=Terima kasih atas kunjungan Anda')).toBeVisible({ timeout: 30000 })
  })

  test('payment gateway account endpoint returns data', async ({ request }) => {
    const loginRes = await request.post('/api/v1/auth/login', {
      data: { email: 'e2e.owner@test.com', password: 'password123' },
    })
    expect(loginRes.ok()).toBeTruthy()
    const loginBody = await loginRes.json()
    const token = loginBody.token

    const res = await request.get('/api/v1/payment-gateway/account', {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.ok()).toBeTruthy()
    const body = await res.json()
    expect(body).toHaveProperty('data')
  })

  test('payments list endpoint returns data', async ({ request }) => {
    const loginRes = await request.post('/api/v1/auth/login', {
      data: { email: 'e2e.owner@test.com', password: 'password123' },
    })
    expect(loginRes.ok()).toBeTruthy()
    const loginBody = await loginRes.json()
    const token = loginBody.token

    const res = await request.get('/api/v1/payments', {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.ok()).toBeTruthy()
  })

  test('payments summary endpoint returns data', async ({ request }) => {
    const loginRes = await request.post('/api/v1/auth/login', {
      data: { email: 'e2e.owner@test.com', password: 'password123' },
    })
    expect(loginRes.ok()).toBeTruthy()
    const loginBody = await loginRes.json()
    const token = loginBody.token

    const res = await request.get('/api/v1/payments/summary', {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.ok()).toBeTruthy()
    const body = await res.json()
    expect(body).toHaveProperty('data')
    expect(body.data).toHaveProperty('total_payments')
  })

  test('cash drawer sessions endpoint returns data', async ({ request }) => {
    const loginRes = await request.post('/api/v1/auth/login', {
      data: { email: 'e2e.owner@test.com', password: 'password123' },
    })
    expect(loginRes.ok()).toBeTruthy()
    const loginBody = await loginRes.json()
    const token = loginBody.token

    const res = await request.get('/api/v1/cash-drawer/sessions', {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(res.ok()).toBeTruthy()
  })

  test('webhook endpoint rejects invalid token', async ({ request }) => {
    const res = await request.post('/api/v1/webhooks/xendit', {
      headers: { 'x-callback-token': 'invalid-token' },
      data: { event: 'payment.capture', data: { status: 'SUCCEEDED' } },
    })
    expect(res.status()).toBe(401)
  })

  test('webhook endpoint rejects missing token', async ({ request }) => {
    const res = await request.post('/api/v1/webhooks/xendit', {
      data: { event: 'payment.capture', data: { status: 'SUCCEEDED' } },
    })
    expect(res.status()).toBe(401)
  })

  test('payment endpoints require authentication', async ({ request }) => {
    const res = await request.get('/api/v1/payments')
    expect([401, 500]).toContain(res.status())
  })
})
