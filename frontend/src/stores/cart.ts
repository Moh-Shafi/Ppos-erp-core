import { create } from 'zustand'
import type { CartItem, Product, ProductVariant, DiscountPreset } from '@/types'

interface CartState {
  items: CartItem[]
  storeId: number | null
  customerId: number | null
  discount: number
  tax: number
  notes: string
  appliedPresetId: number | null
  setStoreId: (id: number | null) => void
  setCustomerId: (id: number | null) => void
  setDiscount: (value: number) => void
  setTax: (value: number) => void
  setNotes: (notes: string) => void
  addProduct: (product: Product, variant?: ProductVariant | null, unitPrice?: number, originalPrice?: number | null) => void
  addVariant: (product: Product, variant: ProductVariant, unitPrice: number, originalPrice: number | null) => void
  removeProduct: (productId: number, variantId?: number | null) => void
  updateQuantity: (productId: number, variantId: number | null, quantity: number) => void
  incrementQuantity: (productId: number, variantId: number | null) => void
  decrementQuantity: (productId: number, variantId: number | null) => void
  applyDiscountPreset: (preset: DiscountPreset, subtotal: number) => void
  clearDiscountPreset: () => void
  clearCart: () => void
  subtotal: () => number
  total: () => number
  totalItems: () => number
  restoreFromHeld: (items: CartItem[], customerId: number | null, storeId: number | null, notes: string) => void
}

const itemKey = (productId: number, variantId: number | null) =>
  `${productId}-${variantId ?? 'none'}`

export const useCartStore = create<CartState>((set, get) => ({
  items: [],
  storeId: null,
  customerId: null,
  discount: 0,
  tax: 0,
  notes: '',
  appliedPresetId: null,

  setStoreId: (id) => set({ storeId: id }),
  setCustomerId: (id) => set({ customerId: id }),
  setDiscount: (value) => set({ discount: Math.max(0, value), appliedPresetId: null }),
  setTax: (value) => set({ tax: Math.max(0, value) }),
  setNotes: (notes) => set({ notes }),

  addProduct: (product, variant = null, unitPrice, originalPrice = null) => {
    const price = unitPrice ?? parseFloat(product.selling_price)
    const orig = originalPrice ?? null
    const items = get().items
    const key = itemKey(product.id, variant?.id ?? null)
    const existing = items.find((i) => itemKey(i.product.id, i.variant?.id ?? null) === key)
    if (existing) {
      set({
        items: items.map((i) =>
          itemKey(i.product.id, i.variant?.id ?? null) === key
            ? { ...i, quantity: i.quantity + 1 }
            : i,
        ),
      })
    } else {
      set({ items: [...items, { product, variant, quantity: 1, unitPrice: price, originalPrice: orig }] })
    }
  },

  addVariant: (product, variant, unitPrice, originalPrice) => {
    get().addProduct(product, variant, unitPrice, originalPrice)
  },

  removeProduct: (productId, variantId = null) => {
    const key = itemKey(productId, variantId)
    set({ items: get().items.filter((i) => itemKey(i.product.id, i.variant?.id ?? null) !== key) })
  },

  updateQuantity: (productId, variantId, quantity) => {
    if (quantity <= 0) {
      get().removeProduct(productId, variantId)
      return
    }
    const key = itemKey(productId, variantId)
    set({
      items: get().items.map((i) =>
        itemKey(i.product.id, i.variant?.id ?? null) === key ? { ...i, quantity } : i,
      ),
    })
  },

  incrementQuantity: (productId, variantId) => {
    const key = itemKey(productId, variantId)
    set({
      items: get().items.map((i) =>
        itemKey(i.product.id, i.variant?.id ?? null) === key ? { ...i, quantity: i.quantity + 1 } : i,
      ),
    })
  },

  decrementQuantity: (productId, variantId) => {
    const items = get().items
    const key = itemKey(productId, variantId)
    const existing = items.find((i) => itemKey(i.product.id, i.variant?.id ?? null) === key)
    if (existing && existing.quantity <= 1) {
      set({ items: items.filter((i) => itemKey(i.product.id, i.variant?.id ?? null) !== key) })
    } else {
      set({
        items: items.map((i) =>
          itemKey(i.product.id, i.variant?.id ?? null) === key ? { ...i, quantity: i.quantity - 1 } : i,
        ),
      })
    }
  },

  applyDiscountPreset: (preset, subtotal) => {
    let discount = 0
    if (preset.type === 'percentage') {
      discount = Math.round((subtotal * parseFloat(preset.value)) / 100)
    } else {
      discount = parseFloat(preset.value)
    }
    set({ discount: Math.max(0, Math.min(discount, subtotal)), appliedPresetId: preset.id })
  },

  clearDiscountPreset: () => set({ appliedPresetId: null, discount: 0 }),

  clearCart: () =>
    set({
      items: [],
      customerId: null,
      discount: 0,
      tax: 0,
      notes: '',
      appliedPresetId: null,
    }),

  subtotal: () => {
    return get().items.reduce((sum, i) => sum + i.unitPrice * i.quantity, 0)
  },

  total: () => {
    const sub = get().subtotal()
    return Math.max(0, sub - get().discount + get().tax)
  },

  totalItems: () => {
    return get().items.reduce((sum, i) => sum + i.quantity, 0)
  },

  restoreFromHeld: (items, customerId, storeId, notes) => {
    set({ items, customerId, storeId, notes, discount: 0, tax: 0, appliedPresetId: null })
  },
}))
