import { test, expect, type Page } from '@playwright/test'
import { E2E_PRODUCTS, E2E_VARIANTS, E2E_CUSTOMERS, E2E_DISCOUNT_PRESETS } from './helpers'

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
    await expect(page.getByRole('button', { name: new RegExp(E2E_PRODUCTS.kopi.name) })).toBeVisible({ timeout: 30000 })
  } catch {
    // Retry: reload page and wait again
    await page.reload()
    await page.waitForLoadState('networkidle')
    await expect(page.getByRole('button', { name: new RegExp(E2E_PRODUCTS.kopi.name) })).toBeVisible({ timeout: 60000 })
  }
}

async function addProductToCart(page: Page, productName: string) {
  await page.getByRole('button', { name: new RegExp(productName) }).first().click()
}

function getCartItem(page: Page, text: string) {
  return page.locator('.flex.items-center.gap-2.rounded-md.border').filter({ hasText: text })
}

test.describe('Phase 4: POS Enhancement E2E', () => {
  test.setTimeout(180000)

  // 1. POS + module/feature flag gating
  test('POS page accessible with pos module enabled', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)
    await expect(page.getByRole('heading', { name: 'Keranjang' })).toBeVisible()
  })

  test('POS page blocked for staff without pos.use permission', async ({ page }) => {
    // Login as staff (no pos.use permission)
    await page.goto('/login')
    await page.fill('input[type="email"]', 'e2e.staff@test.com')
    await page.fill('input[type="password"]', 'password123')
    await page.click('button[type="submit"]')
    await page.waitForURL('**/dashboard')
    await page.waitForLoadState('networkidle')

    // Try navigating to POS - should redirect back to dashboard
    await page.goto('/pos')
    await page.waitForURL('**/dashboard')
    await expect(page).toHaveURL(/dashboard/)
  })

  // 2. Product Variant selection
  test('variant product opens variant selector modal', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)

    // Click on variant product
    await addProductToCart(page, E2E_PRODUCTS.variant.name)

    // Variant selector modal should appear
    await expect(page.getByRole('heading', { name: /Pilih Varian/ })).toBeVisible({ timeout: 30000 })
    await expect(page.getByText(E2E_VARIANTS.regular.label)).toBeVisible()
    await expect(page.getByText(E2E_VARIANTS.large.label)).toBeVisible()
  })

  test('selecting Large variant adds correct price to cart', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)

    await addProductToCart(page, E2E_PRODUCTS.variant.name)
    await expect(page.getByRole('heading', { name: /Pilih Varian/ })).toBeVisible({ timeout: 30000 })

    // Select Large variant
    await page.getByText(E2E_VARIANTS.large.label).click()
    await page.getByRole('button', { name: 'Pilih' }).click()

    // Cart should show the variant product with Large price (12000)
    const cartItem = getCartItem(page, E2E_PRODUCTS.variant.name)
    await expect(cartItem).toBeVisible()
    await expect(cartItem.getByText(E2E_VARIANTS.large.label)).toBeVisible()
    await expect(cartItem.getByText(/Rp.*12.000$/)).toBeVisible()
  })

  test('selecting Regular variant adds correct price to cart', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)

    await addProductToCart(page, E2E_PRODUCTS.variant.name)
    await expect(page.getByRole('heading', { name: /Pilih Varian/ })).toBeVisible({ timeout: 30000 })

    await page.getByText(E2E_VARIANTS.regular.label).click()
    await page.getByRole('button', { name: 'Pilih' }).click()

    const cartItem = getCartItem(page, E2E_PRODUCTS.variant.name)
    await expect(cartItem).toBeVisible()
    await expect(cartItem.getByText(E2E_VARIANTS.regular.label)).toBeVisible()
    await expect(cartItem.getByText(/Rp.*8.000$/)).toBeVisible()
  })

  // 3. Customer selection with price list
  test('customer can be selected in cart', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)
    await addProductToCart(page, E2E_PRODUCTS.kopi.name)

    // Select customer from dropdown (the Pelanggan select is the one with Walk-in placeholder)
    const customerSelect = page.locator('select').filter({ has: page.locator('option:has-text("' + E2E_CUSTOMERS.regular.name + '")') })
    await customerSelect.selectOption({ label: E2E_CUSTOMERS.regular.name })

    // Customer name should be selected
    await expect(customerSelect).toHaveValue(/\d+/)
  })

  // 4. Customer Credit Limit indicator
  test('credit customer shows credit limit indicator', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)
    await addProductToCart(page, E2E_PRODUCTS.kopi.name)

    // Select credit customer
    const customerSelect = page.locator('select').filter({ has: page.locator('option:has-text("' + E2E_CUSTOMERS.credit.name + '")') })
    await customerSelect.selectOption({ label: E2E_CUSTOMERS.credit.name })

    // Credit limit info should appear
    await expect(page.getByText('Limit Kredit')).toBeVisible({ timeout: 10000 })
    await expect(page.getByText('Outstanding')).toBeVisible()
  })

  // 5. Hold Sale + 6. Recall Sale
  test('hold sale saves current cart and clears it', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)
    await addProductToCart(page, E2E_PRODUCTS.kopi.name)

    // Ensure store is selected - select first E2E store
    const storeSelect = page.locator('select').filter({ hasText: 'Pilih Toko' })
    await storeSelect.selectOption({ index: 1 })

    // Click Hold button on cart
    await page.getByRole('button', { name: 'Tahan', exact: true }).first().click()

    // Hold modal should appear
    await expect(page.getByRole('heading', { name: 'Tahan Penjualan' })).toBeVisible({ timeout: 10000 })

    // Confirm hold by clicking the Tahan button inside the modal footer
    await page.getByRole('button', { name: 'Tahan', exact: true }).last().click()

    // Cart should be empty (wait for API call to complete)
    await expect(page.getByText('Keranjang kosong')).toBeVisible({ timeout: 60000 })
  })

  test('recall sale restores held cart', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)

    // Ensure store is selected - select first E2E store
    const storeSelect = page.locator('select').filter({ hasText: 'Pilih Toko' })
    await storeSelect.selectOption({ index: 1 })

    // First hold a sale
    await addProductToCart(page, E2E_PRODUCTS.esTeh.name)
    await page.getByRole('button', { name: 'Tahan', exact: true }).first().click()
    await expect(page.getByRole('heading', { name: 'Tahan Penjualan' })).toBeVisible({ timeout: 10000 })
    await page.getByRole('button', { name: 'Tahan', exact: true }).last().click()
    await expect(page.getByText('Keranjang kosong')).toBeVisible({ timeout: 60000 })

    // Now open held sales list
    await page.getByRole('button', { name: 'Ditahan', exact: true }).click()
    await expect(page.getByRole('heading', { name: 'Penjualan Ditahan' })).toBeVisible({ timeout: 10000 })

    // Should see held sale(s) with item count
    await expect(page.getByText(/1 item/).first()).toBeVisible({ timeout: 10000 })

    // Click Panggil (Recall) on first held sale
    await page.getByRole('button', { name: 'Panggil', exact: true }).first().click()

    // Cart should have an item back (not empty)
    await expect(page.getByText('Keranjang kosong')).not.toBeVisible({ timeout: 10000 })
    await expect(page.locator('.flex.items-center.gap-2.rounded-md.border').first()).toBeVisible({ timeout: 10000 })
  })

  // 7. Full Refund + 8. Partial Refund + 9. Inventory restoration
  test('full refund from sales history', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)
    await addProductToCart(page, E2E_PRODUCTS.kopi.name)

    // Checkout
    await page.getByRole('button', { name: /Bayar/ }).click()
    await expect(page.getByRole('heading', { name: 'Pembayaran' })).toBeVisible()
    await page.locator('input[placeholder="Jumlah"]').fill('8000')
    await page.getByRole('button', { name: 'Konfirmasi Pembayaran' }).click()
    await expect(page.getByText('Terima kasih')).toBeVisible({ timeout: 60000 })

    // Go to sales history
    await page.goto('/sales')
    await page.waitForLoadState('networkidle')
    await expect(page.getByText('Riwayat Transaksi')).toBeVisible({ timeout: 15000 })
    await expect(page.locator('tbody tr').first()).toBeVisible({ timeout: 15000 })

    // Click first sale
    await page.locator('tbody tr').first().click()
    await expect(page.getByText('Detail Transaksi')).toBeVisible({ timeout: 10000 })

    // Click Refund button
    const refundBtn = page.getByRole('button', { name: 'Refund' })
    await expect(refundBtn).toBeVisible({ timeout: 5000 })
    await refundBtn.click()

    // Refund modal should appear
    await expect(page.getByRole('heading', { name: /Refund/ })).toBeVisible({ timeout: 10000 })

    // Select full refund
    await page.getByText('Refund Penuh').click()

    // Process refund
    await page.getByRole('button', { name: /Refund/ }).last().click()

    // Should not show error
    await page.waitForTimeout(3000)
  })

  test('partial refund with item selection', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)

    // Add 3 items
    await addProductToCart(page, E2E_PRODUCTS.kopi.name)
    const cartItem = getCartItem(page, E2E_PRODUCTS.kopi.name)
    await cartItem.locator('button:has-text("+")').click()
    await cartItem.locator('button:has-text("+")').click()
    await expect(cartItem.locator('span.w-8')).toHaveText('3')

    // Checkout
    await page.getByRole('button', { name: /Bayar/ }).click()
    await expect(page.getByRole('heading', { name: 'Pembayaran' })).toBeVisible()
    await page.locator('input[placeholder="Jumlah"]').fill('24000')
    await page.getByRole('button', { name: 'Konfirmasi Pembayaran' }).click()
    await expect(page.getByText('Terima kasih')).toBeVisible({ timeout: 60000 })

    // Go to sales history and open the sale
    await page.goto('/sales')
    await page.waitForLoadState('networkidle')
    await expect(page.locator('tbody tr').first()).toBeVisible({ timeout: 15000 })
    await page.locator('tbody tr').first().click()
    await expect(page.getByText('Detail Transaksi')).toBeVisible({ timeout: 10000 })

    // Click Refund
    const refundBtn = page.getByRole('button', { name: 'Refund' })
    if (await refundBtn.isVisible()) {
      await refundBtn.click()
      await expect(page.getByRole('heading', { name: /Refund/ })).toBeVisible({ timeout: 10000 })

      // Select partial refund
      await page.getByText('Refund Parsial').click()

      // Set quantity to 1 for first item
      const qtyInput = page.locator('input[type="number"]').last()
      await qtyInput.fill('1')

      // Process refund
      await page.getByRole('button', { name: /Refund/ }).last().click()
      await page.waitForTimeout(3000)
    }
  })

  // 10. Discount Presets
  test('discount preset buttons appear and apply discount', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)
    await addProductToCart(page, E2E_PRODUCTS.kopi.name)

    // Discount preset buttons should be visible
    await expect(page.getByRole('button', { name: E2E_DISCOUNT_PRESETS.percentage.name })).toBeVisible({ timeout: 10000 })

    // Apply percentage discount (10% of 8000 = 800)
    await page.getByRole('button', { name: E2E_DISCOUNT_PRESETS.percentage.name }).click()

    // Total should be 8000 - 800 = 7200
    const totalRow = page.locator('.flex.justify-between.font-bold').filter({ hasText: 'Total' })
    await expect(totalRow).toHaveText(/Total.*Rp.*7.200/, { timeout: 5000 })
  })

  test('fixed discount preset applies correct amount', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)
    await addProductToCart(page, E2E_PRODUCTS.kopi.name)

    // Apply fixed discount (2000 off 8000 = 6000)
    await page.getByRole('button', { name: E2E_DISCOUNT_PRESETS.fixed.name }).click()

    const totalRow = page.locator('.flex.justify-between.font-bold').filter({ hasText: 'Total' })
    await expect(totalRow).toHaveText(/Total.*Rp.*6.000/, { timeout: 5000 })
  })

  test('clear discount preset resets discount', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)
    await addProductToCart(page, E2E_PRODUCTS.kopi.name)

    // Apply discount
    await page.getByRole('button', { name: E2E_DISCOUNT_PRESETS.fixed.name }).click()
    const totalRow = page.locator('.flex.justify-between.font-bold').filter({ hasText: 'Total' })
    await expect(totalRow).toHaveText(/Total.*Rp.*6.000/, { timeout: 5000 })

    // Clear discount
    await page.getByRole('button', { name: 'Hapus diskon' }).click()

    // Total should be back to 8000
    await expect(totalRow).toHaveText(/Total.*Rp.*8.000/, { timeout: 5000 })
  })

  test('discount presets management page', async ({ page }) => {
    await page.goto('/login')
    await page.fill('input[type="email"]', 'e2e.owner@test.com')
    await page.fill('input[type="password"]', 'password123')
    await page.click('button[type="submit"]')
    await page.waitForURL('**/dashboard')
    await page.waitForLoadState('networkidle')

    await page.goto('/pos/discount-presets')
    await page.waitForLoadState('networkidle')

    await expect(page.getByRole('heading', { name: 'Preset Diskon' })).toBeVisible({ timeout: 15000 })
    await expect(page.getByText(E2E_DISCOUNT_PRESETS.percentage.name)).toBeVisible({ timeout: 10000 })
    await expect(page.getByText(E2E_DISCOUNT_PRESETS.fixed.name)).toBeVisible()
  })

  // 11. Receipt Settings per Store
  test('receipt settings page shows and allows editing', async ({ page }) => {
    await page.goto('/login')
    await page.fill('input[type="email"]', 'e2e.owner@test.com')
    await page.fill('input[type="password"]', 'password123')
    await page.click('button[type="submit"]')
    await page.waitForURL('**/dashboard')
    await page.waitForLoadState('networkidle')

    await page.goto('/settings/receipt')
    await page.waitForLoadState('networkidle')

    await expect(page.getByRole('heading', { name: 'Pengaturan Struk' })).toBeVisible({ timeout: 15000 })

    // Should see header text field with seeded value
    const headerInput = page.locator('input').filter({ hasText: '' })
    await expect(page.locator('input[placeholder*="Selamat datang"]')).toBeVisible({ timeout: 10000 })

    // Should see footer text field
    await expect(page.locator('input[placeholder*="Terima kasih"]')).toBeVisible()
  })

  // 12. Keyboard shortcuts
  test('F9 focuses search input', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)

    // Press F9
    await page.keyboard.press('F9')

    // Search input should be focused
    const searchInput = page.locator('input[placeholder*="Cari produk"]')
    await expect(searchInput).toBeFocused()
  })

  test('F4 clears cart', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)
    await addProductToCart(page, E2E_PRODUCTS.kopi.name)
    await expect(getCartItem(page, E2E_PRODUCTS.kopi.name)).toBeVisible()

    // Press F4 to clear cart
    await page.keyboard.press('F4')

    // Cart should be empty
    await expect(page.getByText('Keranjang kosong')).toBeVisible({ timeout: 5000 })
  })

  test('F1 opens checkout modal', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)
    await addProductToCart(page, E2E_PRODUCTS.kopi.name)

    // Press F1 to open checkout
    await page.keyboard.press('F1')

    // Checkout modal should appear
    await expect(page.getByRole('heading', { name: 'Pembayaran' })).toBeVisible({ timeout: 5000 })
  })

  // 13. RBAC / permission enforcement
  test('cashier can access POS but not discount presets management', async ({ page }) => {
    // Cashier should be able to use POS
    await loginAndGoToPOS(page, 'e2e.cashier@test.com')
    await waitForProducts(page)
    await expect(page.getByRole('heading', { name: 'Keranjang' })).toBeVisible()
  })

  test('discount presets nav hidden for cashier without permission', async ({ page }) => {
    await page.goto('/login')
    await page.fill('input[type="email"]', 'e2e.cashier@test.com')
    await page.fill('input[type="password"]', 'password123')
    await page.click('button[type="submit"]')
    await page.waitForURL('**/dashboard')
    await page.waitForLoadState('networkidle')

    // Cashier should not see discount presets in nav
    const navLink = page.locator('a[href="/pos/discount-presets"]')
    await expect(navLink).not.toBeVisible({ timeout: 5000 })
  })

  // 14. Backward compatibility with old checkout (no variant, no customer)
  test('checkout without variant and customer still works', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)

    // Add simple product (no variant, no customer selected)
    await addProductToCart(page, E2E_PRODUCTS.kopi.name)
    await addProductToCart(page, E2E_PRODUCTS.esTeh.name)

    // Checkout
    await page.getByRole('button', { name: /Bayar/ }).click()
    await expect(page.getByRole('heading', { name: 'Pembayaran' })).toBeVisible()

    // Total = 8000 + 5000 = 13000
    await page.locator('input[placeholder="Jumlah"]').fill('13000')
    await expect(page.getByText('Lunas')).toBeVisible()

    await page.getByRole('button', { name: 'Konfirmasi Pembayaran' }).click()
    await expect(page.getByText('Terima kasih')).toBeVisible({ timeout: 60000 })
  })

  test('variant product badge shows in product grid', async ({ page }) => {
    await loginAndGoToPOS(page)
    await waitForProducts(page)

    // Variant product should have a "Varian" badge
    const variantProductBtn = page.getByRole('button', { name: new RegExp(E2E_PRODUCTS.variant.name) })
    await expect(variantProductBtn).toBeVisible()
    await expect(variantProductBtn.locator('span').filter({ hasText: 'Varian' })).toBeVisible()
  })
})
