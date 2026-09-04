import { useState, useEffect, useMemo, useCallback, useRef } from 'react'
import { useCartStore } from '@/stores/cart'
import { useAuthStore } from '@/stores/auth'
import { useModuleConfigStore } from '@/stores/module-config'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { productService } from '@/services/product'
import { storeService } from '@/services/store'
import { customerService } from '@/services/customer'
import { saleService } from '@/services/sale'
import { discountPresetService } from '@/services/discountPreset'
import { categoryService } from '@/services/category'
import type { Product, Store, Customer, Category, Sale, Payment, PaymentInput, DiscountPreset, ProductVariant } from '@/types'
import { v4 as uuidv4 } from 'uuid'
import { formatRupiah } from '@/lib/utils'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Select } from '@/components/ui/Select'
import { Badge } from '@/components/ui/Badge'
import { Modal } from '@/components/ui/Modal'
import { Receipt } from '@/components/pos/Receipt'
import { VariantSelector } from '@/components/pos/VariantSelector'
import { HoldSaleModal } from '@/components/pos/HoldSaleModal'
import { HeldSaleList } from '@/components/pos/HeldSaleList'
import { QRISPaymentModal } from '@/components/pos/QRISPaymentModal'

const PAYMENT_METHODS = [
  { value: 'cash', label: 'Tunai' },
  { value: 'qris', label: 'QRIS' },
  { value: 'card', label: 'Kartu' },
  { value: 'bank_transfer', label: 'Transfer Bank' },
]

/* ---------- Icons ---------- */

const IC = {
  search: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
  ),
  cart: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
  ),
  trash: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
  ),
  plus: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
  ),
  minus: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><line x1="5" y1="12" x2="19" y2="12"/></svg>
  ),
  pause: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
  ),
  list: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
  ),
  store: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M3 9l3-3h12l3 3"/><path d="M4 9v11a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V9"/><path d="M9 21V12h6v9"/></svg>
  ),
  user: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
  ),
  tag: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
  ),
  receipt: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1z"/><path d="M8 7h8"/><path d="M8 11h8"/><path d="M8 15h5"/></svg>
  ),
  wallet: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
  ),
  check: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polyline points="20 6 9 17 4 12"/></svg>
  ),
  package: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-12 w-12"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
  ),
  sparkles: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="h-3.5 w-3.5"><path d="M12 2l1.5 5.5L19 9l-5.5 1.5L12 16l-1.5-5.5L5 9l5.5-1.5L12 2z"/></svg>
  ),
  expand: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M15 3h6v6"/><path d="M9 21H3v-6"/><path d="M21 3l-7 7"/><path d="M3 21l7-7"/></svg>
  ),
  collapse: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M8 3v6a2 2 0 0 1-2 2H0"/><path d="M16 3v6a2 2 0 0 0 2 2h6"/><path d="M8 21v-6a2 2 0 0 0-2-2H0"/><path d="M16 21v-6a2 2 0 0 1 2-2h6"/></svg>
  ),
  moon: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
  ),
  sun: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
  ),
  x: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
  ),
  info: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
  ),
  printer: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
  ),
  volume: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
  ),
  volumeOff: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg>
  ),
}

/* ---------- Category icons ---------- */

const CATEGORY_ICONS: Record<string, React.ReactNode> = {
  makanan: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><path d="M3 11h18M3 11a2 2 0 0 0 2-2V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v3a2 2 0 0 0 2 2M5 11v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8"/></svg>
  ),
  minuman: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><path d="M6 8h12l-1.5 12a2 2 0 0 1-2 2h-5a2 2 0 0 1-2-2L6 8z"/><path d="M6 8l-1-4h14l-1 4"/></svg>
  ),
  default: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
  ),
}

function getCategoryIcon(name: string): React.ReactNode {
  const lower = name.toLowerCase()
  if (lower.includes('makan') || lower.includes('food')) return CATEGORY_ICONS.makanan
  if (lower.includes('minum') || lower.includes('drink') || lower.includes('beverage')) return CATEGORY_ICONS.minuman
  return CATEGORY_ICONS.default
}

/* ---------- Stock status ---------- */

function getStockStatus(product: Product): { level: 'out' | 'low' | 'ok' | 'unknown'; label: string; color: string; dot: string } {
  if (!product.is_trackable) return { level: 'unknown', label: '', color: '', dot: '' }
  const qty = product.stock_quantity
  if (qty === null || qty === undefined) return { level: 'unknown', label: '', color: '', dot: '' }
  if (qty <= 0) return { level: 'out', label: 'Habis', color: 'text-[#dc2626]', dot: 'bg-[#dc2626]' }
  const min = product.min_stock ?? 10
  if (qty <= min) return { level: 'low', label: `Sisa ${qty}`, color: 'text-[#ca8a04]', dot: 'bg-[#ca8a04]' }
  return { level: 'ok', label: 'Tersedia', color: 'text-[#16a34a]', dot: 'bg-[#16a34a]' }
}

/* ---------- Product color based on category ---------- */

const PRODUCT_COLORS = [
  'from-[#f54927] to-[#ff6b4a]',
  'from-[#0a84ff] to-[#3b82f6]',
  'from-[#16a34a] to-[#22c55e]',
  'from-[#8b5cf6] to-[#a78bfa]',
  'from-[#ca8a04] to-[#facc15]',
  'from-[#ec4899] to-[#f472b6]',
  'from-[#0891b2] to-[#06b6d4]',
  'from-[#dc2626] to-[#ef4444]',
]

function getProductColor(idx: number): string {
  return PRODUCT_COLORS[idx % PRODUCT_COLORS.length]
}

function getInitials(name: string): string {
  const words = name.trim().split(/\s+/)
  if (words.length >= 2) return (words[0][0] + words[1][0]).toUpperCase()
  return words[0].slice(0, 2).toUpperCase()
}

/* ---------- Product image with fallback ---------- */

function ProductImage({ product, idx, size = 'card' }: { product: Product; idx: number; size?: 'card' | 'cart' }) {
  const [imgError, setImgError] = useState(false)
  const [imgLoaded, setImgLoaded] = useState(false)
  const gradient = getProductColor(idx)
  const initials = getInitials(product.name)

  if (size === 'cart') {
    return (
      <div className={`relative flex h-10 w-10 flex-shrink-0 items-center justify-center overflow-hidden rounded-lg bg-gradient-to-br ${gradient}`}>
        {product.image && !imgError ? (
          <>
            {!imgLoaded && <div className="absolute inset-0 animate-pulse bg-white/20" />}
            <img
              src={product.image}
              alt={product.name}
              loading="lazy"
              onLoad={() => setImgLoaded(true)}
              onError={() => setImgError(true)}
              className={`h-full w-full object-cover transition-opacity duration-300 ${imgLoaded ? 'opacity-100' : 'opacity-0'}`}
            />
          </>
        ) : (
          <span className="text-[11px] font-bold text-white/90">{initials}</span>
        )}
      </div>
    )
  }

  return (
    <div className={`relative flex h-24 items-center justify-center overflow-hidden bg-gradient-to-br ${gradient}`}>
      {product.image && !imgError ? (
        <>
          {!imgLoaded && <div className="absolute inset-0 animate-pulse bg-white/10" />}
          <img
            src={product.image}
            alt={product.name}
            loading="lazy"
            onLoad={() => setImgLoaded(true)}
            onError={() => setImgError(true)}
            className={`h-full w-full object-cover transition-all duration-500 ${imgLoaded ? 'scale-100 opacity-100' : 'scale-105 opacity-0'} group-hover:scale-110`}
          />
          {/* Dark overlay for text readability */}
          <div className="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent" />
        </>
      ) : (
        <span className="text-[24px] font-bold tracking-tight text-white/90">{initials}</span>
      )}
      {product.has_variants && (
        <span className="absolute top-2 right-2 z-10 rounded-full bg-white/25 px-2 py-0.5 text-[9px] font-bold text-white backdrop-blur-md">
          VAR
        </span>
      )}
      <div className="absolute inset-0 bg-black/0 transition-colors group-hover:bg-black/10" />
    </div>
  )
}

/* ---------- Component ---------- */

export function POSPage() {
  const { user } = useAuthStore()
  const cart = useCartStore()
  const moduleConfig = useModuleConfigStore()

  const [products, setProducts] = useState<Product[]>([])
  const [stores, setStores] = useState<Store[]>([])
  const [customers, setCustomers] = useState<Customer[]>([])
  const [categories, setCategories] = useState<Category[]>([])
  const [discountPresets, setDiscountPresets] = useState<DiscountPreset[]>([])
  const [search, setSearch] = useState('')
  const [selectedCategory, setSelectedCategory] = useState<number | ''>('')
  const [loading, setLoading] = useState(false)

  const [checkoutOpen, setCheckoutOpen] = useState(false)
  const [payments, setPayments] = useState<PaymentInput[]>([{ payment_method: 'cash', amount: 0 }])
  const [checkoutLoading, setCheckoutLoading] = useState(false)
  const [checkoutError, setCheckoutError] = useState('')
  const [completedSale, setCompletedSale] = useState<Sale | null>(null)

  const [qrisSale, setQrisSale] = useState<Sale | null>(null)
  const [qrisPayment, setQrisPayment] = useState<Payment | null>(null)
  const [qrisOpen, setQrisOpen] = useState(false)

  const [variantProduct, setVariantProduct] = useState<Product | null>(null)
  const [holdModalOpen, setHoldModalOpen] = useState(false)
  const [heldListOpen, setHeldListOpen] = useState(false)
  const [selectedCustomer, setSelectedCustomer] = useState<Customer | null>(null)
  const [isFullscreen, setIsFullscreen] = useState(false)
  const [isDark, setIsDark] = useState(false)
  const [soundOn, setSoundOn] = useState(true)
  const [cartBump, setCartBump] = useState(false)
  const [detailProduct, setDetailProduct] = useState<Product | null>(null)
  const [showReceiptPreview, setShowReceiptPreview] = useState(false)
  const [quickQtyProduct, setQuickQtyProduct] = useState<{ product: Product; x: number; y: number } | null>(null)
  const productsLoadedRef = useRef(false)

  const canManage = useMemo(() => {
    const role = user?.role?.slug
    return role === 'owner' || role === 'manager' || role === 'cashier'
  }, [user])

  const canHoldSale = moduleConfig.hasFeature('pos.hold_sale')
  const canDiscountPresets = moduleConfig.hasFeature('pos.discount_presets')
  const canCustomerCredit = moduleConfig.hasFeature('sales.customer_credit')

  useEffect(() => {
    storeService.list().then(setStores).catch(() => {})
    categoryService.all().then(setCategories).catch(() => {})
    customerService.list({ per_page: 100, is_active: true }).then((r) => setCustomers(r.data)).catch(() => {})
    if (canDiscountPresets) {
      discountPresetService.list().then(setDiscountPresets).catch(() => {})
    }
  }, [canDiscountPresets])

  useEffect(() => {
    if (stores.length > 0 && !cart.storeId) {
      const store = stores[0]
      cart.setStoreId(store.id)
      localStorage.setItem('current_store_id', String(store.id))
      moduleConfig.setStore(store)
    }
  }, [stores, cart, moduleConfig])

  const fetchProducts = useCallback(async (skipDebounce = false) => {
    if (!skipDebounce) setLoading(true)
    try {
      const res = await productService.list({
        per_page: 100,
        search: search || undefined,
        category_id: selectedCategory || undefined,
        is_active: true,
      })
      setProducts(res.data)
      productsLoadedRef.current = true
    } catch {
      // ignore
    } finally {
      setLoading(false)
    }
  }, [search, selectedCategory])

  // Initial load — no debounce
  useEffect(() => {
    if (!productsLoadedRef.current) {
      fetchProducts(true)
    }
  }, [fetchProducts])

  // Debounced search/category change
  useEffect(() => {
    if (!productsLoadedRef.current) return
    const timer = setTimeout(() => fetchProducts(), 200)
    return () => clearTimeout(timer)
  }, [fetchProducts])

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement || e.target instanceof HTMLSelectElement) {
        if (e.key === 'Escape') (e.target as HTMLElement).blur()
        return
      }
      if (completedSale || checkoutOpen || holdModalOpen || heldListOpen || variantProduct) return
      switch (e.key) {
        case 'F1': e.preventDefault(); if (cart.items.length > 0) openCheckout(); break
        case 'F2': e.preventDefault(); if (canHoldSale && cart.items.length > 0) setHoldModalOpen(true); break
        case 'F3': e.preventDefault(); if (canHoldSale) setHeldListOpen(true); break
        case 'F4': e.preventDefault(); if (cart.items.length > 0) cart.clearCart(); break
        case 'F9':
          e.preventDefault()
          const si = document.querySelector<HTMLInputElement>('input[placeholder*="Cari"]')
          si?.focus()
          break
      }
    }
    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [cart.items.length, completedSale, checkoutOpen, holdModalOpen, heldListOpen, variantProduct, canHoldSale])

  const cartTotal = cart.total()
  const totalPaid = payments.reduce((sum, p) => sum + (p.amount || 0), 0)
  const change = Math.max(0, totalPaid - cartTotal)

  /* ---------- Sound feedback ---------- */
  const playAddSound = useCallback(() => {
    if (!soundOn) return
    try {
      const AudioCtx = window.AudioContext || (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext
      const ctx = new AudioCtx()
      const osc = ctx.createOscillator()
      const gain = ctx.createGain()
      osc.connect(gain)
      gain.connect(ctx.destination)
      osc.frequency.setValueAtTime(880, ctx.currentTime)
      osc.frequency.exponentialRampToValueAtTime(1320, ctx.currentTime + 0.05)
      gain.gain.setValueAtTime(0.15, ctx.currentTime)
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.15)
      osc.start(ctx.currentTime)
      osc.stop(ctx.currentTime + 0.15)
    } catch { /* ignore */ }
  }, [soundOn])

  const triggerCartBump = useCallback(() => {
    setCartBump(true)
    setTimeout(() => setCartBump(false), 300)
  }, [])

  const handleAddProduct = async (product: Product) => {
    if (product.has_variants) {
      try {
        const detail = await productService.show(product.id)
        if (detail.variants && detail.variants.length > 0) {
          setVariantProduct(detail)
          return
        }
      } catch { /* fall through */ }
    }
    cart.addProduct(product)
    playAddSound()
    triggerCartBump()
  }

  const handleVariantSelect = (variant: ProductVariant) => {
    if (variantProduct) {
      const price = variant.price_override ? parseFloat(variant.price_override) : parseFloat(variantProduct.selling_price)
      const originalPrice = variant.price_override ? parseFloat(variantProduct.selling_price) : null
      cart.addProduct(variantProduct, variant, price, originalPrice)
    }
  }

  const handleCustomerChange = (customerId: number | null) => {
    cart.setCustomerId(customerId)
    const customer = customers.find((c) => c.id === customerId) ?? null
    setSelectedCustomer(customer)
  }

  const handleApplyPreset = (preset: DiscountPreset) => {
    cart.applyDiscountPreset(preset, cart.subtotal())
  }

  const openCheckout = () => {
    const total = cart.total()
    setPayments([{ payment_method: 'cash', amount: total }])
    setCheckoutError('')
    setCheckoutOpen(true)
  }

  const updatePayment = (index: number, field: keyof PaymentInput, value: string | number) => {
    setPayments((prev) => prev.map((p, i) => i === index ? { ...p, [field]: field === 'amount' ? Number(value) || 0 : value } : p))
  }

  const addPaymentMethod = () => {
    const remaining = cartTotal - totalPaid
    if (remaining <= 0) return
    setPayments((prev) => [...prev, { payment_method: 'cash', amount: remaining }])
  }

  const removePaymentMethod = (index: number) => {
    setPayments((prev) => prev.filter((_, i) => i !== index))
  }

  const handleCheckout = async () => {
    if (!cart.storeId) { setCheckoutError('Pilih toko terlebih dahulu'); return }
    if (cart.items.length === 0) { setCheckoutError('Keranjang kosong'); return }
    if (totalPaid < cartTotal) { setCheckoutError(`Pembayaran kurang: ${formatRupiah(cartTotal - totalPaid)}`); return }

    setCheckoutLoading(true)
    setCheckoutError('')
    const idempotencyKey = `pos-${Date.now()}-${uuidv4()}`
    try {
      const sale = await saleService.checkout({
        store_id: cart.storeId,
        customer_id: cart.customerId,
        items: cart.items.map((i) => ({ product_id: i.product.id, variant_id: i.variant?.id ?? null, quantity: i.quantity })),
        payments: payments.map((p, idx) => ({ ...p, amount: Number(p.amount), idempotency_key: idx === 0 ? idempotencyKey : `pos-${Date.now()}-${idx}-${uuidv4()}` })),
        discount: cart.discount || undefined,
        tax: cart.tax || undefined,
        notes: cart.notes || undefined,
      })
      const pendingPayment = sale.payments?.find((p: Payment) => p.status === 'pending' && ['qris', 'card', 'bank_transfer'].includes(p.payment_method))
      if (pendingPayment) {
        setQrisSale(sale); setQrisPayment(pendingPayment); setQrisOpen(true)
      } else {
        setCompletedSale(sale)
      }
      setCheckoutOpen(false)
      cart.clearCart()
      if (stores.length > 0) cart.setStoreId(stores[0].id)
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } } }
      setCheckoutError(error.response?.data?.message ?? 'Gagal memproses pembayaran')
    } finally {
      setCheckoutLoading(false)
    }
  }

  if (!user) {
    return <div className="flex h-full items-center justify-center"><p className="text-[#a8a29e]">Memuat...</p></div>
  }

  if (!canManage) {
    return <div className="flex h-full items-center justify-center"><p className="text-[#a8a29e]">Anda tidak memiliki akses ke halaman ini</p></div>
  }

  if (completedSale) {
    return (
      <DashboardLayout>
        <div className="mx-auto max-w-2xl">
          <div className="rounded-2xl border border-[#e7e5e4] bg-white p-6 shadow-sm">
            <Receipt sale={completedSale} onClose={() => setCompletedSale(null)} />
          </div>
        </div>
      </DashboardLayout>
    )
  }

  const posContent = (
    <div className={`flex gap-4 ${isFullscreen ? 'fixed inset-0 z-50 p-4' : 'h-[calc(100vh-8rem)]'} ${isDark ? 'dark bg-[#1c1917] text-[#f5f5f4]' : 'bg-[#fafaf8]'}`}>
      {/* ===== Product Selection - Left ===== */}
      <div className="flex min-w-0 flex-1 flex-col">
        {/* Search + Categories + Fullscreen toggle */}
        <div className="mb-3 flex gap-2">
          <div className="relative flex-1">
            <div className="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-[#a8a29e]">{IC.search}</div>
            <input
              type="text"
              placeholder="Cari produk atau scan barcode..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="h-11 w-full rounded-xl border border-[#e7e5e4] bg-white pr-4 pl-10 text-[14px] text-[#1c1917] transition-all outline-none placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            />
          </div>
          <select
            value={selectedCategory}
            onChange={(e) => setSelectedCategory(e.target.value ? Number(e.target.value) : '')}
            className="h-11 cursor-pointer rounded-xl border border-[#e7e5e4] bg-white px-3 text-[13px] font-medium text-[#1c1917] outline-none transition-all focus:border-[#f54927]"
          >
            <option value="">Semua Kategori</option>
            {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
          <button
            onClick={() => setIsFullscreen((f) => !f)}
            className="flex h-11 w-11 flex-shrink-0 cursor-pointer items-center justify-center rounded-xl border border-[#e7e5e4] bg-white text-[#78716c] transition-all hover:border-[#f54927]/30 hover:text-[#f54927]"
            title={isFullscreen ? 'Keluar dari layar penuh' : 'Layar penuh'}
          >
            {isFullscreen ? IC.collapse : IC.expand}
          </button>
        </div>

        {/* Category chips */}
        {categories.length > 0 && (
          <div className="mb-3 flex gap-2 overflow-x-auto pb-1">
            <button
              onClick={() => setSelectedCategory('')}
              className={`flex flex-shrink-0 cursor-pointer items-center gap-1.5 rounded-full px-3.5 py-1.5 text-[12px] font-semibold transition-all ${
                selectedCategory === '' ? 'bg-[#f54927] text-white shadow-sm' : 'bg-white text-[#78716c] border border-[#e7e5e4] hover:border-[#f54927]/30'
              }`}
            >
              {CATEGORY_ICONS.default} Semua
            </button>
            {categories.map((c) => (
              <button
                key={c.id}
                onClick={() => setSelectedCategory(c.id)}
                className={`flex flex-shrink-0 cursor-pointer items-center gap-1.5 rounded-full px-3.5 py-1.5 text-[12px] font-semibold transition-all ${
                  selectedCategory === c.id ? 'bg-[#f54927] text-white shadow-sm' : 'bg-white text-[#78716c] border border-[#e7e5e4] hover:border-[#f54927]/30'
                }`}
              >
                {getCategoryIcon(c.name)} {c.name}
              </button>
            ))}
          </div>
        )}

        {/* Products grid */}
        {loading ? (
          <div className="flex flex-1 items-center justify-center">
            <div className="flex flex-col items-center gap-3">
              <div className="h-8 w-8 animate-spin rounded-full border-2 border-[#f54927] border-t-transparent" />
              <p className="text-[13px] text-[#a8a29e]">Memuat produk...</p>
            </div>
          </div>
        ) : products.length === 0 ? (
          <div className="flex flex-1 flex-col items-center justify-center rounded-2xl border border-dashed border-[#e7e5e4] bg-white/50 py-20">
            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-[#f5f5f4] text-[#a8a29e]">{IC.package}</div>
            <p className="text-[15px] font-semibold text-[#78716c]">Tidak ada produk ditemukan</p>
            <p className="mt-1 text-[12px] text-[#a8a29e]">Coba ubah pencarian atau kategori</p>
          </div>
        ) : (
          <div className="flex-1 overflow-y-auto pr-1 [scrollbar-width:thin]">
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
              {products.map((product, idx) => {
                const stock = getStockStatus(product)
                return (
                <button
                  key={product.id}
                  onClick={() => handleAddProduct(product)}
                  onContextMenu={(e) => {
                    e.preventDefault()
                    setQuickQtyProduct({ product, x: e.clientX, y: e.clientY })
                  }}
                  className="group flex cursor-pointer flex-col overflow-hidden rounded-2xl border border-[#e7e5e4] bg-white text-left transition-all duration-200 hover:border-[#f54927]/30 hover:shadow-[0_8px_24px_rgba(245,73,39,0.08)] active:scale-[0.97]"
                >
                  {/* Product image */}
                  <ProductImage product={product} idx={idx} />
                  {/* Stock badge */}
                  {stock.level !== 'unknown' && (
                    <div className="absolute top-2 left-2 z-10 flex items-center gap-1 rounded-full bg-white/90 px-2 py-0.5 backdrop-blur-md">
                      <span className={`h-1.5 w-1.5 rounded-full ${stock.dot}`} />
                      <span className={`text-[9px] font-semibold ${stock.color}`}>{stock.label}</span>
                    </div>
                  )}
                  {/* Product info */}
                  <div className="flex flex-1 flex-col p-3">
                    <p
                      className="line-clamp-2 cursor-pointer text-[13px] font-semibold leading-snug text-[#1c1917] hover:text-[#f54927]"
                      onClick={(e) => { e.stopPropagation(); setDetailProduct(product) }}
                    >
                      {product.name}
                    </p>
                    <p className="mt-1 text-[10px] text-[#a8a29e]">{product.sku ?? '-'}</p>
                    <div className="mt-auto flex items-center justify-between pt-2">
                      <p className="text-[15px] font-bold text-[#f54927] tabular-nums">{formatRupiah(product.selling_price)}</p>
                      <span className="flex h-6 w-6 items-center justify-center rounded-full bg-[#f54927]/10 text-[#f54927] opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                        {IC.plus}
                      </span>
                    </div>
                  </div>
                </button>
                )
              })}
            </div>
          </div>
        )}
      </div>

      {/* ===== Cart - Right ===== */}
      <div className="flex w-[380px] flex-shrink-0 flex-col overflow-hidden rounded-2xl border border-[#e7e5e4] bg-white shadow-sm">
        {/* Cart header */}
        <div className="border-b border-[#f5f5f4] p-4">
          <div className="mb-3 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <div className={`flex h-8 w-8 items-center justify-center rounded-lg bg-[#f54927]/10 text-[#f54927] transition-transform duration-300 ${cartBump ? 'scale-125' : 'scale-100'}`}>
                {IC.cart}
              </div>
              <div>
                <h2 className="text-[14px] font-bold text-[#1c1917]">Keranjang</h2>
                <p className="text-[10px] text-[#a8a29e]">{cart.totalItems()} item</p>
              </div>
            </div>
            <div className="flex gap-1">
              {/* Sound toggle */}
              <button
                onClick={() => setSoundOn((s) => !s)}
                className={`flex cursor-pointer items-center justify-center rounded-lg p-2 transition-colors ${soundOn ? 'text-[#78716c] hover:bg-[#f5f5f4]' : 'text-[#d6d3d1] hover:bg-[#f5f5f4]'}`}
                title={soundOn ? 'Matikan suara' : 'Nyalakan suara'}
              >
                {soundOn ? IC.volume : IC.volumeOff}
              </button>
              {/* Dark mode toggle */}
              <button
                onClick={() => setIsDark((d) => !d)}
                className={`flex cursor-pointer items-center justify-center rounded-lg p-2 transition-colors ${isDark ? 'text-[#facc15] hover:bg-[#f5f5f4]' : 'text-[#78716c] hover:bg-[#f5f5f4]'}`}
                title={isDark ? 'Mode terang' : 'Mode gelap'}
              >
                {isDark ? IC.sun : IC.moon}
              </button>
              {canHoldSale && cart.items.length > 0 && (
                <button onClick={() => setHoldModalOpen(true)} className="flex cursor-pointer items-center gap-1 rounded-lg px-2.5 py-1.5 text-[11px] font-medium text-[#78716c] transition-colors hover:bg-[#f5f5f4]">
                  {IC.pause} Tahan
                </button>
              )}
              {canHoldSale && (
                <button onClick={() => setHeldListOpen(true)} className="flex cursor-pointer items-center gap-1 rounded-lg px-2.5 py-1.5 text-[11px] font-medium text-[#78716c] transition-colors hover:bg-[#f5f5f4]">
                  {IC.list} Ditahan
                </button>
              )}
              {cart.totalItems() > 0 && (
                <button onClick={() => cart.clearCart()} className="flex cursor-pointer items-center gap-1 rounded-lg px-2.5 py-1.5 text-[11px] font-medium text-[#dc2626] transition-colors hover:bg-[#fef2f2]">
                  {IC.trash} Kosongkan
                </button>
              )}
            </div>
          </div>
          {/* Store selector */}
          <div className="flex items-center gap-2 rounded-xl border border-[#e7e5e4] bg-[#fafaf9] px-3 py-2">
            <span className="text-[#a8a29e]">{IC.store}</span>
            <select
              value={cart.storeId ?? ''}
              onChange={(e) => {
                const storeId = Number(e.target.value)
                cart.setStoreId(storeId)
                const store = stores.find((s) => s.id === storeId)
                if (store) { localStorage.setItem('current_store_id', String(storeId)); moduleConfig.setStore(store) }
              }}
              className="flex-1 cursor-pointer bg-transparent text-[12px] font-medium text-[#1c1917] outline-none"
            >
              {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
            </select>
          </div>
        </div>

        {/* Cart items */}
        <div className="flex-1 overflow-y-auto p-3 [scrollbar-width:thin]">
          {cart.items.length === 0 ? (
            <div className="flex h-full flex-col items-center justify-center text-center">
              <div className="mb-3 flex h-16 w-16 items-center justify-center rounded-2xl bg-[#f5f5f4] text-[#d6d3d1]">{IC.cart}</div>
              <p className="text-[14px] font-semibold text-[#78716c]">Keranjang kosong</p>
              <p className="mt-1 text-[12px] text-[#a8a29e]">Pilih produk untuk memulai</p>
            </div>
          ) : (
            <div className="space-y-2">
              {cart.items.map((item) => {
                const key = `${item.product.id}-${item.variant?.id ?? 'none'}`
                const variantLabel = item.variant?.option_values?.map((ov) => ov.value).join(' / ')
                return (
                  <div key={key} className="group flex items-center gap-3 rounded-xl border border-[#f5f5f4] bg-white p-2.5 transition-all hover:border-[#e7e5e4] hover:shadow-sm">
                    <ProductImage product={item.product} idx={item.product.id % 8} size="cart" />
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-[13px] font-semibold text-[#1c1917]">{item.product.name}</p>
                      {variantLabel && <p className="text-[10px] font-medium text-[#f54927]">{variantLabel}</p>}
                      <p className="text-[11px] text-[#a8a29e] tabular-nums">{formatRupiah(item.unitPrice)} × {item.quantity}</p>
                    </div>
                    <div className="flex items-center gap-1">
                      <button
                        onClick={() => cart.decrementQuantity(item.product.id, item.variant?.id ?? null)}
                        className="flex h-7 w-7 cursor-pointer items-center justify-center rounded-lg border border-[#e7e5e4] text-[#78716c] transition-colors hover:border-[#f54927] hover:bg-[#f54927]/5 hover:text-[#f54927]"
                      >{IC.minus}</button>
                      <span className="w-7 text-center text-[13px] font-bold tabular-nums text-[#1c1917]">{item.quantity}</span>
                      <button
                        onClick={() => cart.incrementQuantity(item.product.id, item.variant?.id ?? null)}
                        className="flex h-7 w-7 cursor-pointer items-center justify-center rounded-lg border border-[#e7e5e4] text-[#78716c] transition-colors hover:border-[#f54927] hover:bg-[#f54927]/5 hover:text-[#f54927]"
                      >{IC.plus}</button>
                    </div>
                    <div className="text-right">
                      <p className="text-[13px] font-bold text-[#1c1917] tabular-nums">{formatRupiah(item.unitPrice * item.quantity)}</p>
                      <button
                        onClick={() => cart.removeProduct(item.product.id, item.variant?.id ?? null)}
                        className="text-[10px] font-medium text-[#dc2626] transition-colors hover:underline"
                      >Hapus</button>
                    </div>
                  </div>
                )
              })}
            </div>
          )}
        </div>

        {/* Cart footer */}
        {cart.items.length > 0 && (
          <div className="space-y-2.5 border-t border-[#f5f5f4] p-4">
            <div className="flex justify-between text-[13px]">
              <span className="text-[#78716c]">Subtotal</span>
              <span className="font-medium tabular-nums text-[#1c1917]">{formatRupiah(cart.subtotal())}</span>
            </div>

            {/* Discount presets */}
            {canDiscountPresets && discountPresets.length > 0 && (
              <div className="flex flex-wrap gap-1.5">
                {discountPresets.filter((p) => p.is_active).map((preset) => (
                  <button
                    key={preset.id}
                    onClick={() => handleApplyPreset(preset)}
                    className={`flex cursor-pointer items-center gap-1 rounded-lg border px-2.5 py-1 text-[11px] font-semibold transition-all ${
                      cart.appliedPresetId === preset.id ? 'border-[#f54927] bg-[#f54927]/10 text-[#f54927]' : 'border-[#e7e5e4] text-[#78716c] hover:border-[#f54927]/40'
                    }`}
                  >
                    {IC.tag} {preset.name}
                  </button>
                ))}
                {cart.appliedPresetId && (
                  <button onClick={() => cart.clearDiscountPreset()} className="cursor-pointer px-1 text-[11px] font-medium text-[#dc2626] hover:underline">Hapus diskon</button>
                )}
              </div>
            )}

            {/* Discount & Tax inputs */}
            <div className="grid grid-cols-2 gap-2">
              <div className="flex items-center gap-2 rounded-lg border border-[#e7e5e4] px-2.5 py-1.5">
                <span className="text-[11px] font-medium text-[#a8a29e]">Diskon</span>
                <input type="number" value={cart.discount || ''} onChange={(e) => cart.setDiscount(Number(e.target.value) || 0)} className="w-full bg-transparent text-right text-[12px] font-semibold tabular-nums text-[#1c1917] outline-none" placeholder="0" />
              </div>
              <div className="flex items-center gap-2 rounded-lg border border-[#e7e5e4] px-2.5 py-1.5">
                <span className="text-[11px] font-medium text-[#a8a29e]">Pajak</span>
                <input type="number" value={cart.tax || ''} onChange={(e) => cart.setTax(Number(e.target.value) || 0)} className="w-full bg-transparent text-right text-[12px] font-semibold tabular-nums text-[#1c1917] outline-none" placeholder="0" />
              </div>
            </div>

            {/* Customer selector */}
            <div className="flex items-center gap-2 rounded-lg border border-[#e7e5e4] px-2.5 py-1.5">
              <span className="text-[#a8a29e]">{IC.user}</span>
              <select
                value={cart.customerId ?? ''}
                onChange={(e) => handleCustomerChange(e.target.value ? Number(e.target.value) : null)}
                className="flex-1 cursor-pointer bg-transparent text-[12px] font-medium text-[#1c1917] outline-none"
              >
                <option value="">Walk-in</option>
                {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
              </select>
            </div>

            {/* Customer credit info */}
            {canCustomerCredit && selectedCustomer && selectedCustomer.credit_limit !== null && (
              <div className="space-y-1 rounded-lg bg-[#fafaf9] p-2.5 text-[11px]">
                <div className="flex justify-between"><span className="text-[#a8a29e]">Limit Kredit</span><span className="font-medium tabular-nums">{formatRupiah(selectedCustomer.credit_limit)}</span></div>
                <div className="flex justify-between"><span className="text-[#a8a29e]">Outstanding</span><span className="font-medium tabular-nums">{formatRupiah(selectedCustomer.outstanding_balance)}</span></div>
                <div className="flex justify-between"><span className="text-[#a8a29e]">Sisa</span><span className={`font-bold tabular-nums ${parseFloat(selectedCustomer.outstanding_balance) + cartTotal > (selectedCustomer.credit_limit ?? 0) ? 'text-[#dc2626]' : 'text-[#16a34a]'}`}>{formatRupiah((selectedCustomer.credit_limit ?? 0) - parseFloat(selectedCustomer.outstanding_balance) - cartTotal)}</span></div>
              </div>
            )}

            {/* Total */}
            <div className="flex items-center justify-between border-t border-[#f5f5f4] pt-2.5">
              <span className="text-[14px] font-bold text-[#1c1917]">Total</span>
              <span className="text-[20px] font-bold tabular-nums text-[#f54927]">{formatRupiah(cartTotal)}</span>
            </div>

            {/* Checkout buttons */}
            <div className="flex gap-2">
              <button
                onClick={() => setShowReceiptPreview(true)}
                disabled={cart.items.length === 0}
                className="flex h-12 w-12 flex-shrink-0 cursor-pointer items-center justify-center rounded-xl border border-[#e7e5e4] bg-white text-[#78716c] transition-all hover:border-[#f54927]/30 hover:text-[#f54927] disabled:cursor-not-allowed disabled:opacity-50"
                title="Pratinjau struk"
              >
                {IC.printer}
              </button>
              <button
                onClick={openCheckout}
                disabled={cart.items.length === 0}
                className="flex h-12 flex-1 cursor-pointer items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] text-[14px] font-bold text-white shadow-[0_4px_14px_rgba(245,73,39,0.3)] transition-all duration-200 hover:shadow-[0_6px_20px_rgba(245,73,39,0.4)] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50"
              >
                {IC.wallet}
                Bayar · {cart.totalItems()} item
                <kbd className="ml-1 rounded bg-white/20 px-1.5 py-0.5 text-[9px] font-bold">F1</kbd>
              </button>
            </div>
          </div>
        )}
      </div>

      {/* ===== Checkout Modal ===== */}
      <Modal open={checkoutOpen} onClose={() => setCheckoutOpen(false)} title="Pembayaran" size="md" footer={
        <>
          <Button variant="outline" onClick={() => setCheckoutOpen(false)}>Batal</Button>
          <Button onClick={handleCheckout} disabled={checkoutLoading}>{checkoutLoading ? 'Memproses...' : 'Konfirmasi Pembayaran'}</Button>
        </>
      }>
        <div className="space-y-4">
          <div className="flex items-center justify-between rounded-xl bg-gradient-to-r from-[#f54927]/5 to-transparent p-4">
            <div className="flex items-center gap-2">
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#f54927]/10 text-[#f54927]">{IC.receipt}</div>
              <span className="text-[13px] font-medium text-[#78716c]">Total Belanja</span>
            </div>
            <span className="text-[22px] font-bold tabular-nums text-[#f54927]">{formatRupiah(cartTotal)}</span>
          </div>

          {payments.map((payment, index) => (
            <div key={index} className="space-y-2 rounded-xl border border-[#e7e5e4] p-3">
              <div className="flex items-center justify-between">
                <span className="text-[13px] font-semibold text-[#1c1917]">Pembayaran {index + 1}</span>
                {payments.length > 1 && <button onClick={() => removePaymentMethod(index)} className="cursor-pointer text-[11px] font-medium text-[#dc2626] hover:underline">Hapus</button>}
              </div>
              <div className="grid grid-cols-2 gap-2">
                <Select value={payment.payment_method} onChange={(e) => updatePayment(index, 'payment_method', e.target.value)} options={PAYMENT_METHODS} />
                <Input type="number" value={payment.amount || ''} onChange={(e) => updatePayment(index, 'amount', e.target.value)} placeholder="Jumlah" />
              </div>
              {(payment.payment_method === 'qris' || payment.payment_method === 'card' || payment.payment_method === 'bank_transfer') && (
                <Input value={payment.payment_reference ?? ''} onChange={(e) => updatePayment(index, 'payment_reference', e.target.value)} placeholder="Referensi Pembayaran (opsional)" />
              )}
            </div>
          ))}

          {totalPaid < cartTotal && (
            <button onClick={addPaymentMethod} className="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-xl border border-dashed border-[#e7e5e4] py-2.5 text-[12px] font-semibold text-[#78716c] transition-colors hover:border-[#f54927]/40 hover:text-[#f54927]">
              {IC.plus} Tambah Metode Pembayaran
            </button>
          )}

          <div className="space-y-1.5 border-t border-[#f5f5f4] pt-3">
            <div className="flex justify-between text-[13px]"><span className="text-[#78716c]">Total Dibayar</span><span className="font-medium tabular-nums">{formatRupiah(totalPaid)}</span></div>
            <div className="flex justify-between text-[13px]"><span className="text-[#78716c]">Kembalian</span><span className={`font-medium tabular-nums ${change > 0 ? 'text-[#16a34a]' : ''}`}>{formatRupiah(change)}</span></div>
            {totalPaid < cartTotal && <div className="flex justify-between text-[13px]"><span className="text-[#dc2626]">Kurang</span><span className="font-bold tabular-nums text-[#dc2626]">{formatRupiah(cartTotal - totalPaid)}</span></div>}
          </div>

          {checkoutError && (
            <div className="rounded-xl border border-[#dc2626]/20 bg-[#fef2f2] p-3"><p className="text-[13px] font-medium text-[#dc2626]">{checkoutError}</p></div>
          )}

          <div className="flex items-center gap-2">
            <Badge variant={totalPaid >= cartTotal ? 'success' : 'warning'}>{totalPaid >= cartTotal ? 'Lunas' : 'Pembayaran Kurang'}</Badge>
            {totalPaid > cartTotal && <Badge variant="default">Kembalian: {formatRupiah(change)}</Badge>}
          </div>
        </div>
      </Modal>

      {/* Variant Selector */}
      {variantProduct && <VariantSelector product={variantProduct} open={!!variantProduct} onClose={() => setVariantProduct(null)} onSelect={handleVariantSelect} />}

      {/* Hold Sale */}
      <HoldSaleModal open={holdModalOpen} onClose={() => setHoldModalOpen(false)} onHeld={() => {}} />

      {/* Held Sale List */}
      <HeldSaleList open={heldListOpen} onClose={() => setHeldListOpen(false)} onRecall={() => {}} />

      {/* QRIS Payment */}
      <QRISPaymentModal sale={qrisSale} payment={qrisPayment} open={qrisOpen} onClose={() => setQrisOpen(false)} onSuccess={() => { if (qrisSale) setCompletedSale(qrisSale); setQrisOpen(false); setQrisSale(null); setQrisPayment(null) }} />

      {/* ===== Product Detail Popup ===== */}
      {detailProduct && (
        <div
          className="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 backdrop-blur-sm animate-fade-in"
          onClick={() => setDetailProduct(null)}
        >
          <div
            className="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl animate-scale-in"
            onClick={(e) => e.stopPropagation()}
          >
            <button
              onClick={() => setDetailProduct(null)}
              className="absolute top-3 right-3 z-10 flex h-8 w-8 cursor-pointer items-center justify-center rounded-full bg-white/80 text-[#78716c] backdrop-blur-md transition-colors hover:bg-white hover:text-[#1c1917]"
            >
              {IC.x}
            </button>
            <ProductImage product={detailProduct} idx={products.findIndex((p) => p.id === detailProduct.id)} />
            <div className="p-5">
              <h3 className="text-[18px] font-bold text-[#1c1917]">{detailProduct.name}</h3>
              <div className="mt-1 flex items-center gap-3 text-[12px] text-[#a8a29e]">
                <span>SKU: {detailProduct.sku ?? '-'}</span>
                {detailProduct.category && <span>· {detailProduct.category.name}</span>}
              </div>
              {detailProduct.description && (
                <p className="mt-3 text-[13px] leading-relaxed text-[#78716c]">{detailProduct.description}</p>
              )}
              <div className="mt-4 flex items-center justify-between border-t border-[#f5f5f4] pt-4">
                <div>
                  <p className="text-[11px] text-[#a8a29e]">Harga</p>
                  <p className="text-[22px] font-bold text-[#f54927] tabular-nums">{formatRupiah(detailProduct.selling_price)}</p>
                </div>
                {(() => {
                  const stock = getStockStatus(detailProduct)
                  if (stock.level === 'unknown') return null
                  return (
                    <div className="flex items-center gap-1.5 rounded-lg bg-[#f5f5f4] px-3 py-1.5">
                      <span className={`h-2 w-2 rounded-full ${stock.dot}`} />
                      <span className={`text-[12px] font-semibold ${stock.color}`}>{stock.label}</span>
                    </div>
                  )
                })()}
              </div>
              <button
                onClick={() => { handleAddProduct(detailProduct); setDetailProduct(null) }}
                className="mt-4 flex h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] text-[14px] font-bold text-white shadow-[0_4px_14px_rgba(245,73,39,0.3)] transition-all hover:shadow-[0_6px_20px_rgba(245,73,39,0.4)] active:scale-[0.98]"
              >
                {IC.plus} Tambah ke Keranjang
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ===== Quick Quantity Context Menu ===== */}
      {quickQtyProduct && (
        <>
          <div className="fixed inset-0 z-[55]" onClick={() => setQuickQtyProduct(null)} />
          <div
            className="fixed z-[56] w-44 overflow-hidden rounded-xl border border-[#e7e5e4] bg-white py-1 shadow-2xl animate-scale-in"
            style={{ left: Math.min(quickQtyProduct.x, window.innerWidth - 180), top: Math.min(quickQtyProduct.y, window.innerHeight - 200) }}
          >
            <p className="px-3 py-1.5 text-[10px] font-bold tracking-wide text-[#a8a29e] uppercase">Tambah cepat</p>
            {[2, 3, 5, 10].map((qty) => (
              <button
                key={qty}
                onClick={() => {
                  for (let i = 0; i < qty; i++) cart.addProduct(quickQtyProduct.product)
                  playAddSound()
                  triggerCartBump()
                  setQuickQtyProduct(null)
                }}
                className="flex w-full cursor-pointer items-center justify-between px-3 py-2 text-[13px] font-medium text-[#1c1917] transition-colors hover:bg-[#f54927]/5"
              >
                <span>{qty}x {quickQtyProduct.product.name}</span>
                <span className="text-[12px] font-bold text-[#f54927] tabular-nums">{formatRupiah(Number(quickQtyProduct.product.selling_price) * qty)}</span>
              </button>
            ))}
            <div className="my-1 border-t border-[#f5f5f4]" />
            <button
              onClick={() => { setDetailProduct(quickQtyProduct.product); setQuickQtyProduct(null) }}
              className="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-[13px] font-medium text-[#78716c] transition-colors hover:bg-[#f5f5f4]"
            >
              {IC.info} Detail produk
            </button>
          </div>
        </>
      )}

      {/* ===== Receipt Preview Modal ===== */}
      <Modal open={showReceiptPreview} onClose={() => setShowReceiptPreview(false)} title="Pratinjau Struk" size="sm" footer={
        <>
          <Button variant="outline" onClick={() => setShowReceiptPreview(false)}>Tutup</Button>
          <Button onClick={() => { setShowReceiptPreview(false); openCheckout() }}>Lanjut ke Pembayaran</Button>
        </>
      }>
        <div className="rounded-xl border border-dashed border-[#e7e5e4] bg-[#fafaf9] p-4">
          <div className="mb-3 text-center">
            <p className="text-[14px] font-bold text-[#1c1917]">{moduleConfig.businessProfile?.business_name ?? 'KasirPOS'}</p>
            <p className="text-[10px] text-[#a8a29e]">{stores.find((s) => s.id === cart.storeId)?.name ?? ''}</p>
            <p className="text-[10px] text-[#a8a29e]">{new Date().toLocaleString('id-ID')}</p>
          </div>
          <div className="border-t border-dashed border-[#e7e5e4] pt-2">
            {cart.items.map((item) => (
              <div key={`${item.product.id}-${item.variant?.id ?? 'n'}`} className="flex justify-between py-0.5 text-[11px]">
                <span className="text-[#1c1917]">{item.quantity}x {item.product.name}</span>
                <span className="tabular-nums text-[#1c1917]">{formatRupiah(item.unitPrice * item.quantity)}</span>
              </div>
            ))}
          </div>
          <div className="mt-2 space-y-0.5 border-t border-dashed border-[#e7e5e4] pt-2 text-[11px]">
            <div className="flex justify-between"><span className="text-[#a8a29e]">Subtotal</span><span className="tabular-nums">{formatRupiah(cart.subtotal())}</span></div>
            {cart.discount > 0 && <div className="flex justify-between"><span className="text-[#a8a29e]">Diskon</span><span className="tabular-nums">-{formatRupiah(cart.discount)}</span></div>}
            {cart.tax > 0 && <div className="flex justify-between"><span className="text-[#a8a29e]">Pajak</span><span className="tabular-nums">{formatRupiah(cart.tax)}</span></div>}
            <div className="flex justify-between border-t border-dashed border-[#e7e5e4] pt-1 text-[13px] font-bold">
              <span className="text-[#1c1917]">Total</span>
              <span className="tabular-nums text-[#f54927]">{formatRupiah(cartTotal)}</span>
            </div>
          </div>
          <p className="mt-3 text-center text-[9px] text-[#a8a29e]">*** Pratinjau — belum dibayar ***</p>
        </div>
      </Modal>
    </div>
  )

  if (isFullscreen) {
    return posContent
  }

  return <DashboardLayout>{posContent}</DashboardLayout>
}
