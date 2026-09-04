import { test, expect, type Page } from '@playwright/test'
import { E2E_PRODUCTS } from './helpers'

async function loginAndGoToPOS(page: Page, email = 'e2e.owner@test.com') {
  await page.goto('/login')
  await page.fill('input[type="email"]', email)
  await page.fill('input[type="password"]', 'password123')
  await page.click('button[type="submit"]')
  await page.waitForURL('**/dashboard')
  // Wait for user to load in the store (auth.me() call)
  await page.waitForLoadState('networkidle')
  // Navigate to POS via SPA link (preserves user state in memory)
  await page.click('a[href="/pos"]')
  await page.waitForURL('**/pos')
}

async function waitForProducts(page: Page) {
  await expect(page.getByRole('button', { name: new RegExp(E2E_PRODUCTS.kopi.name) })).toBeVisible({ timeout: 30000 })
}

async function addProductToCart(page: Page, productName: string) {
  await page.getByRole('button', { name: new RegExp(productName) }).first().click()
}

function getCartItem(page: Page, productName: string) {
  return page.locator('.flex.items-center.gap-2.rounded-md.border').filter({ hasText: productName })
}

test.describe('POS Full Flow E2E', () => {
  test('login → POS → add product → checkout → receipt → sales history → cancel', async ({ page }) => {
    test.setTimeout(180000)
    await loginAndGoToPOS(page)
    await waitForProducts(page)

    // Add product to cart
    await addProductToCart(page, E2E_PRODUCTS.kopi.name)

    // Verify cart heading is visible
    await expect(page.getByRole('heading', { name: 'Keranjang' })).toBeVisible()

    // Verify item appears in cart
    await expect(getCartItem(page, E2E_PRODUCTS.kopi.name)).toBeVisible()

    // Increase quantity to 3
    const cartItem = getCartItem(page, E2E_PRODUCTS.kopi.name)
    const plusBtn = cartItem.locator('button:has-text("+")')
    await plusBtn.click()
    await plusBtn.click()

    // Verify quantity shows 3
    await expect(cartItem.locator('span.w-8')).toHaveText('3')

    // Add another product
    await addProductToCart(page, E2E_PRODUCTS.esTeh.name)
    await expect(getCartItem(page, E2E_PRODUCTS.esTeh.name)).toBeVisible()

    // Click checkout button
    await page.getByRole('button', { name: /Bayar/ }).click()

    // Checkout modal should appear
    await expect(page.getByRole('heading', { name: 'Pembayaran' })).toBeVisible()
    await expect(page.getByText('Total Belanja')).toBeVisible()

    // Set exact payment amount (3x8000 + 1x5000 = 29000)
    const amountInput = page.locator('input[placeholder="Jumlah"]')
    await amountInput.fill('29000')

    // Verify "Lunas" badge appears
    await expect(page.getByText('Lunas')).toBeVisible()

    // Submit checkout
    await page.getByRole('button', { name: 'Konfirmasi Pembayaran' }).click()

    // Wait for receipt to appear (backend may be slow)
    await expect(page.getByText('Terima kasih')).toBeVisible({ timeout: 30000 })

    // Verify receipt content
    await expect(page.getByText('Subtotal')).toBeVisible()
    await expect(page.getByText('Total', { exact: true })).toBeVisible()
    await expect(page.getByText('cash')).toBeVisible()

    // Start new transaction
    await page.getByRole('button', { name: 'Transaksi Baru' }).click()

    // Cart should be empty now
    await expect(page.getByText('Keranjang kosong')).toBeVisible()

    // Navigate to Sales History
    await page.goto('/sales')
    await page.waitForLoadState('networkidle')

    // Should see the sale we just created
    await expect(page.getByText('Riwayat Transaksi')).toBeVisible({ timeout: 15000 })
    // Wait for table to have data (not loading state)
    await expect(page.locator('tbody tr').first()).toBeVisible({ timeout: 15000 })
    await expect(page.locator('table')).toBeVisible()

    // Click on the first sale row to see detail
    const firstRow = page.locator('tbody tr').first()
    await firstRow.click()

    // Detail modal should appear with receipt
    await expect(page.getByText('Detail Transaksi')).toBeVisible({ timeout: 10000 })

    // Cancel the sale
    const cancelBtn = page.getByRole('button', { name: 'Batalkan Transaksi' })
    await expect(cancelBtn).toBeVisible()
    await cancelBtn.click()

    // Wait for status to update - the sale should show "Dibatalkan"
    await expect(page.locator('table span').filter({ hasText: 'Dibatalkan' }).first()).toBeVisible({ timeout: 15000 })

    // Close modal
    await page.getByRole('button', { name: 'Tutup' }).click()
  })

  test('search and filter products in POS', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)

    // Search for a specific product
    await page.fill('input[placeholder*="Cari produk"]', 'Kopi')
    // Wait for search results (debounce 300ms + slow API)
    await expect(page.getByRole('button', { name: /E2E Kopi Susu/ })).toBeVisible({ timeout: 15000 })

    // Should only show Kopi in the product grid
    await expect(page.getByRole('button', { name: /E2E Es Teh/ })).not.toBeVisible()
    await expect(page.getByRole('button', { name: /E2E Air Mineral/ })).not.toBeVisible()

    // Clear search
    await page.fill('input[placeholder*="Cari produk"]', '')
    // Wait for all products to reappear
    await expect(page.getByRole('button', { name: /E2E Es Teh/ })).toBeVisible({ timeout: 30000 })

    // All products should be visible again
    await expect(page.getByRole('button', { name: /E2E Kopi Susu/ })).toBeVisible()
  })

  test('cart operations: add, increment, decrement, remove, clear', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)

    // Add two products
    await addProductToCart(page, E2E_PRODUCTS.kopi.name)
    await addProductToCart(page, E2E_PRODUCTS.esTeh.name)

    // Verify both in cart
    await expect(getCartItem(page, E2E_PRODUCTS.kopi.name)).toBeVisible()
    await expect(getCartItem(page, E2E_PRODUCTS.esTeh.name)).toBeVisible()

    // Increment kopi
    const kopiItem = getCartItem(page, E2E_PRODUCTS.kopi.name)
    await kopiItem.locator('button:has-text("+")').click()

    // Decrement esTeh (should remove it since qty=1)
    const tehItem = getCartItem(page, E2E_PRODUCTS.esTeh.name)
    await tehItem.locator('button:has-text("-")').click()

    // esTeh should be removed from cart
    await expect(getCartItem(page, E2E_PRODUCTS.esTeh.name)).not.toBeVisible()

    // Clear cart
    await page.getByRole('button', { name: 'Kosongkan' }).click()
    await expect(page.getByText('Keranjang kosong')).toBeVisible()
  })

  test('discount and tax applied to cart total', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)
    await addProductToCart(page, E2E_PRODUCTS.kopi.name)

    // Apply discount (first input with placeholder "0")
    const inputs = page.locator('input[placeholder="0"]')
    await inputs.nth(0).fill('1000')

    // Apply tax (second input with placeholder "0")
    await inputs.nth(1).fill('500')

    // Total should be 8000 - 1000 + 500 = 7500
    // Check the cart total (bold text in the total row)
    const totalRow = page.locator('.flex.justify-between.font-bold').filter({ hasText: 'Total' })
    await expect(totalRow).toHaveText(/Total.*Rp.*7.500/)

    // Open checkout
    await page.getByRole('button', { name: /Bayar/ }).click()
    await expect(page.getByRole('heading', { name: 'Pembayaran' })).toBeVisible()
  })

  test('split payment with cash and QRIS', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)
    await addProductToCart(page, E2E_PRODUCTS.kopi.name)

    // Total = 8000
    await page.getByRole('button', { name: /Bayar/ }).click()

    // First payment: cash 5000
    const amountInput = page.locator('input[placeholder="Jumlah"]')
    await amountInput.fill('5000')

    // Add second payment method
    await page.getByRole('button', { name: /Tambah Metode Pembayaran/ }).click()

    // Second payment: QRIS 3000
    // Scope to the checkout modal to avoid matching sidebar selects
    const modal = page.locator('.fixed.inset-0.z-50')
    const paymentMethods = modal.locator('select')
    await paymentMethods.nth(1).selectOption('qris')
    const amountInputs = modal.locator('input[placeholder="Jumlah"]')
    await amountInputs.nth(1).fill('3000')

    // Total paid = 8000, should be Lunas
    await expect(page.getByText('Lunas')).toBeVisible()

    // Submit
    await page.getByRole('button', { name: 'Konfirmasi Pembayaran' }).click()

    // Receipt should show both payment methods
    await expect(page.getByText('Terima kasih')).toBeVisible({ timeout: 30000 })
    await expect(page.getByText('cash')).toBeVisible()
    await expect(page.getByText('qris')).toBeVisible()
  })

  test('cash overpayment shows change', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)
    await addProductToCart(page, E2E_PRODUCTS.kopi.name)

    await page.getByRole('button', { name: /Bayar/ }).click()

    // Pay 10000 for 8000 total
    const amountInput = page.locator('input[placeholder="Jumlah"]')
    await amountInput.fill('10000')

    // Change should be 2000
    await expect(page.getByText('Kembalian', { exact: true })).toBeVisible()
    const changeRow = page.locator('.flex.justify-between.text-sm').filter({ hasText: 'Kembalian' })
    await expect(changeRow).toHaveText(/Kembalian.*Rp.*2.000/)

    // Submit
    await page.getByRole('button', { name: 'Konfirmasi Pembayaran' }).click()

    // Receipt should show change
    await expect(page.getByText('Terima kasih')).toBeVisible({ timeout: 30000 })
  })

  test('insufficient payment blocked', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)
    await addProductToCart(page, E2E_PRODUCTS.kopi.name)

    await page.getByRole('button', { name: /Bayar/ }).click()

    // Pay only 5000 for 8000 total
    const amountInput = page.locator('input[placeholder="Jumlah"]')
    await amountInput.fill('5000')

    // Should show "Pembayaran Kurang" badge
    await expect(page.getByText('Pembayaran Kurang')).toBeVisible()

    // Try to submit - should show error
    await page.getByRole('button', { name: 'Konfirmasi Pembayaran' }).click()

    // Should show error message about insufficient payment
    await expect(page.getByText('Pembayaran kurang:')).toBeVisible({ timeout: 5000 })

    // Receipt should NOT appear
    await expect(page.getByText('Terima kasih')).not.toBeVisible()
  })

  test('empty cart shows empty message and no checkout button', async ({ page }) => {
    await loginAndGoToPOS(page)

    // Wait for products to load (means POS page is fully rendered)
    await waitForProducts(page)

    // Cart should be empty
    await expect(page.getByText('Keranjang kosong')).toBeVisible()

    // Bayar button should not exist (only renders when cart has items)
    await expect(page.getByRole('button', { name: /Bayar/ })).not.toBeVisible()
  })
})
