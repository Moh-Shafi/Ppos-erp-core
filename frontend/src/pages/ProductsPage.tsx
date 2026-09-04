import { useState, useEffect, useCallback, useMemo } from 'react'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { ConfirmDialog } from '@/components/ui/Modal'
import { ProductFormModal } from '@/components/products/ProductFormModal'
import { productService } from '@/services/product'
import { categoryService } from '@/services/category'
import { formatRupiah } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth'
import type { Product, Category, PaginatedResponse } from '@/types'

/* ---------- Icons ---------- */

const IC = {
  search: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
  ),
  plus: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
  ),
  edit: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
  ),
  trash: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
  ),
  package: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
  ),
  tag: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
  ),
  layers: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
  ),
  check: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3"><polyline points="20 6 9 17 4 12"/></svg>
  ),
  x: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
  ),
  filter: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
  ),
  empty: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" className="h-12 w-12"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
  ),
  grid: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
  ),
  list: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
  ),
  dollar: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><line x1="12" y1="2" x2="12" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
  ),
  boxes: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
  ),
}

/* ---------- Helpers ---------- */

function getInitials(name: string): string {
  const words = name.trim().split(/\s+/)
  if (words.length >= 2) return (words[0][0] + words[1][0]).toUpperCase()
  return words[0].slice(0, 2).toUpperCase()
}

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

function getColor(idx: number): string {
  return PRODUCT_COLORS[idx % PRODUCT_COLORS.length]
}

function Skeleton({ className }: { className: string }) {
  return <div className={`animate-pulse rounded-xl bg-[#f0efec] ${className}`} />
}

/* ---------- Product Image ---------- */

function ProductThumb({ product, idx }: { product: Product; idx: number }) {
  const [imgError, setImgError] = useState(false)
  const [imgLoaded, setImgLoaded] = useState(false)

  return (
    <div className={`relative flex h-12 w-12 flex-shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br ${getColor(idx)}`}>
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
        <span className="text-[13px] font-bold text-white/90">{getInitials(product.name)}</span>
      )}
    </div>
  )
}

/* ---------- Component ---------- */

export function ProductsPage() {
  const { user } = useAuthStore()
  const canManage = user?.role?.slug === 'owner' || user?.role?.slug === 'manager'

  const [products, setProducts] = useState<Product[]>([])
  const [categories, setCategories] = useState<Category[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [search, setSearch] = useState('')
  const [categoryId, setCategoryId] = useState('')
  const [isActive, setIsActive] = useState('')
  const [view, setView] = useState<'grid' | 'list'>('list')

  const [formOpen, setFormOpen] = useState(false)
  const [editProduct, setEditProduct] = useState<Product | null>(null)
  const [formLoading, setFormLoading] = useState(false)

  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [deleteName, setDeleteName] = useState('')
  const [deleteLoading, setDeleteLoading] = useState(false)
  const [deleteError, setDeleteError] = useState('')

  const fetchProducts = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params: Record<string, unknown> = { page, per_page: 20 }
      if (search) params.search = search
      if (categoryId) params.category_id = categoryId
      if (isActive) params.is_active = isActive
      const res: PaginatedResponse<Product> = await productService.list(params)
      setProducts(res.data)
      setLastPage(res.last_page)
      setTotal(res.total)
    } catch {
      setError('Failed to load products')
    } finally {
      setLoading(false)
    }
  }, [page, search, categoryId, isActive])

  useEffect(() => {
    categoryService.all().then(setCategories).catch(() => {})
  }, [])

  useEffect(() => {
    const timer = setTimeout(() => { setPage(1); fetchProducts() }, 250)
    return () => clearTimeout(timer)
  }, [search, categoryId, isActive])

  useEffect(() => { fetchProducts() }, [page])

  const handleAdd = () => { setEditProduct(null); setFormOpen(true) }
  const handleEdit = (product: Product) => { setEditProduct(product); setFormOpen(true) }

  const handleFormSubmit = async (data: {
    category_id: number; name: string; sku?: string; barcode?: string; description?: string
    cost_price: number; selling_price: number; unit: string; is_active: boolean
    is_trackable?: boolean; min_stock?: number | null; has_variants?: boolean
    variant_options?: { name: string; values: { value: string }[] }[]
  }) => {
    setFormLoading(true)
    try {
      if (editProduct) { await productService.update(editProduct.id, data) }
      else { await productService.create(data) }
      setFormOpen(false)
      fetchProducts()
    } finally {
      setFormLoading(false)
    }
  }

  const handleDelete = async () => {
    if (!deleteId) return
    setDeleteLoading(true)
    setDeleteError('')
    try {
      await productService.delete(deleteId)
      setDeleteId(null)
      fetchProducts()
    } catch (err: any) {
      setDeleteError(err.response?.data?.message ?? 'Failed to delete product')
    } finally {
      setDeleteLoading(false)
    }
  }

  /* ---------- Summary stats ---------- */
  const summary = useMemo(() => {
    const active = products.filter((p) => p.is_active).length
    const withVariants = products.filter((p) => p.has_variants).length
    const totalValue = products.reduce((sum, p) => sum + Number(p.selling_price), 0)
    return { active, withVariants, totalValue, inactive: products.length - active }
  }, [products])

  return (
    <DashboardLayout>
      <div className="space-y-5">
        {/* ===== Header ===== */}
        <div className="animate-fade-up flex items-center justify-between">
          <div>
            <h1 className="text-[22px] font-bold tracking-tight text-[#1c1917]">Produk</h1>
            <p className="mt-1 text-[13px] text-[#78716c]">Kelola katalog produk Anda</p>
          </div>
          {canManage && (
            <button
              onClick={handleAdd}
              className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-4 text-[13px] font-semibold text-white shadow-[0_4px_14px_rgba(245,73,39,0.3)] transition-all hover:shadow-[0_6px_20px_rgba(245,73,39,0.4)] active:scale-[0.98]"
            >
              {IC.plus} Tambah Produk
            </button>
          )}
        </div>

        {/* ===== Summary Cards ===== */}
        <div className="animate-fade-up grid grid-cols-2 gap-4 lg:grid-cols-4" style={{ animationDelay: '0.05s' }}>
          {loading && products.length === 0 ? (
            [...Array(4)].map((_, i) => <Skeleton key={i} className="h-[90px]" />)
          ) : (
            <>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Total Produk</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#1c1917] tabular-nums">{total}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#0a84ff]/10 text-[#0a84ff]">{IC.boxes}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Aktif</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#16a34a] tabular-nums">{summary.active}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#16a34a]/10 text-[#16a34a]">{IC.check}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Dengan Varian</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#8b5cf6] tabular-nums">{summary.withVariants}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#8b5cf6]/10 text-[#8b5cf6]">{IC.layers}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Nilai Katalog</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#f54927] tabular-nums">{formatRupiah(summary.totalValue)}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#f54927]/10 text-[#f54927]">{IC.dollar}</div>
                </div>
              </div>
            </>
          )}
        </div>

        {/* ===== Search + Filters ===== */}
        <div className="animate-fade-up flex flex-wrap items-center gap-2" style={{ animationDelay: '0.1s' }}>
          <div className="relative min-w-[240px] flex-1">
            <div className="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-[#a8a29e]">{IC.search}</div>
            <input
              type="text"
              placeholder="Cari produk..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="h-10 w-full rounded-xl border border-[#e7e5e4] bg-white pr-4 pl-10 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            />
          </div>
          <select
            value={categoryId}
            onChange={(e) => setCategoryId(e.target.value)}
            className="h-10 cursor-pointer rounded-xl border border-[#e7e5e4] bg-white px-3 text-[12px] font-medium text-[#1c1917] outline-none transition-all focus:border-[#f54927]"
          >
            <option value="">Semua Kategori</option>
            {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
          <select
            value={isActive}
            onChange={(e) => setIsActive(e.target.value)}
            className="h-10 cursor-pointer rounded-xl border border-[#e7e5e4] bg-white px-3 text-[12px] font-medium text-[#1c1917] outline-none transition-all focus:border-[#f54927]"
          >
            <option value="">Semua Status</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
          {(search || categoryId || isActive) && (
            <button
              onClick={() => { setSearch(''); setCategoryId(''); setIsActive('') }}
              className="flex h-10 cursor-pointer items-center gap-1.5 rounded-xl border border-[#e7e5e4] bg-white px-3 text-[12px] font-medium text-[#78716c] transition-all hover:border-[#dc2626]/30 hover:text-[#dc2626]"
            >
              {IC.filter} Reset
            </button>
          )}
          {/* View toggle */}
          <div className="flex items-center gap-0.5 rounded-xl border border-[#e7e5e4] bg-white p-0.5">
            <button
              onClick={() => setView('list')}
              className={`flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg transition-all ${view === 'list' ? 'bg-[#f54927]/10 text-[#f54927]' : 'text-[#a8a29e] hover:bg-[#f5f5f4]'}`}
              title="Tampilan list"
            >{IC.list}</button>
            <button
              onClick={() => setView('grid')}
              className={`flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg transition-all ${view === 'grid' ? 'bg-[#f54927]/10 text-[#f54927]' : 'text-[#a8a29e] hover:bg-[#f5f5f4]'}`}
              title="Tampilan grid"
            >{IC.grid}</button>
          </div>
        </div>

        {/* ===== Content ===== */}
        {loading ? (
          <div className="space-y-2">
            {[...Array(6)].map((_, i) => (
              <div key={i} className="flex items-center gap-4 rounded-2xl border border-[#e7e5e4] bg-white p-4">
                <Skeleton className="h-12 w-12 rounded-xl" />
                <Skeleton className="h-4 flex-1" />
                <Skeleton className="h-4 w-24" />
                <Skeleton className="h-6 w-20 rounded-full" />
                <Skeleton className="h-8 w-20 rounded-lg" />
              </div>
            ))}
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center rounded-2xl border border-[#dc2626]/20 bg-[#fef2f2] py-16">
            <p className="text-[15px] font-semibold text-[#dc2626]">{error}</p>
            <button onClick={fetchProducts} className="mt-3 cursor-pointer rounded-xl border border-[#e7e5e4] bg-white px-4 py-2 text-[13px] font-medium text-[#78716c] transition-all hover:bg-[#f5f5f4]">
              Coba lagi
            </button>
          </div>
        ) : products.length === 0 ? (
          <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-[#e7e5e4] bg-white/50 py-20">
            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-[#f5f5f4] text-[#d6d3d1]">{IC.empty}</div>
            <p className="text-[15px] font-semibold text-[#78716c]">Belum ada produk</p>
            <p className="mt-1 text-[12px] text-[#a8a29e]">Tambahkan produk pertama Anda</p>
            {canManage && (
              <button onClick={handleAdd} className="mt-4 flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-4 text-[13px] font-semibold text-white shadow-sm transition-all hover:shadow-md active:scale-[0.98]">
                {IC.plus} Tambah Produk
              </button>
            )}
          </div>
        ) : view === 'grid' ? (
          /* ===== Grid View ===== */
          <div className="animate-fade-up grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5" style={{ animationDelay: '0.15s' }}>
            {products.map((p, idx) => (
              <div key={p.id} className="group flex flex-col overflow-hidden rounded-2xl border border-[#e7e5e4] bg-white transition-all duration-200 hover:border-[#f54927]/30 hover:shadow-[0_8px_24px_rgba(245,73,39,0.08)]">
                {/* Image */}
                <div className={`relative flex h-28 items-center justify-center overflow-hidden bg-gradient-to-br ${getColor(idx)}`}>
                  {p.image ? (
                    <img src={p.image} alt={p.name} loading="lazy" className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110" onError={(e) => { e.currentTarget.style.display = 'none' }} />
                  ) : (
                    <span className="text-[28px] font-bold text-white/90">{getInitials(p.name)}</span>
                  )}
                  {p.has_variants && (
                    <span className="absolute top-2 right-2 rounded-full bg-white/25 px-2 py-0.5 text-[9px] font-bold text-white backdrop-blur-md">
                      {p.variants?.length ?? 0} VAR
                    </span>
                  )}
                  <span className={`absolute top-2 left-2 flex items-center gap-1 rounded-full px-2 py-0.5 text-[9px] font-bold backdrop-blur-md ${p.is_active ? 'bg-[#16a34a]/80 text-white' : 'bg-[#dc2626]/80 text-white'}`}>
                    {p.is_active ? IC.check : IC.x}
                  </span>
                </div>
                {/* Info */}
                <div className="flex flex-1 flex-col p-3">
                  <p className="line-clamp-2 text-[13px] font-semibold leading-snug text-[#1c1917]">{p.name}</p>
                  <p className="mt-1 text-[10px] text-[#a8a29e]">{p.sku ?? '-'} · {p.category?.name ?? '-'}</p>
                  <div className="mt-auto flex items-center justify-between pt-2">
                    <p className="text-[15px] font-bold text-[#f54927] tabular-nums">{formatRupiah(p.selling_price)}</p>
                    {canManage && (
                      <div className="flex gap-1">
                        <button onClick={() => handleEdit(p)} className="flex h-7 w-7 cursor-pointer items-center justify-center rounded-lg text-[#78716c] transition-all hover:bg-[#f54927]/10 hover:text-[#f54927]">{IC.edit}</button>
                        <button onClick={() => { setDeleteId(p.id); setDeleteName(p.name); setDeleteError('') }} className="flex h-7 w-7 cursor-pointer items-center justify-center rounded-lg text-[#78716c] transition-all hover:bg-[#dc2626]/10 hover:text-[#dc2626]">{IC.trash}</button>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        ) : (
          /* ===== List View ===== */
          <div className="animate-fade-up overflow-hidden rounded-2xl border border-[#e7e5e4] bg-white" style={{ animationDelay: '0.15s' }}>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-[#f5f5f4] bg-[#fafaf9]">
                    <th className="px-4 py-3 text-left text-[11px] font-bold tracking-wide text-[#a8a29e] uppercase">Produk</th>
                    <th className="hidden px-4 py-3 text-left text-[11px] font-bold tracking-wide text-[#a8a29e] uppercase md:table-cell">SKU</th>
                    <th className="hidden px-4 py-3 text-left text-[11px] font-bold tracking-wide text-[#a8a29e] uppercase lg:table-cell">Kategori</th>
                    <th className="px-4 py-3 text-right text-[11px] font-bold tracking-wide text-[#a8a29e] uppercase">Harga Jual</th>
                    <th className="hidden px-4 py-3 text-center text-[11px] font-bold tracking-wide text-[#a8a29e] uppercase sm:table-cell">Varian</th>
                    <th className="px-4 py-3 text-center text-[11px] font-bold tracking-wide text-[#a8a29e] uppercase">Status</th>
                    {canManage && <th className="px-4 py-3 text-center text-[11px] font-bold tracking-wide text-[#a8a29e] uppercase">Aksi</th>}
                  </tr>
                </thead>
                <tbody>
                  {products.map((p, idx) => (
                    <tr key={p.id} className={`group border-b border-[#f5f5f4] transition-all hover:bg-[#fafaf9] last:border-0 ${idx % 2 === 1 ? 'bg-[#fcfcfb]' : ''}`}>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-3">
                          <ProductThumb product={p} idx={idx} />
                          <div className="min-w-0">
                            <p className="truncate text-[13px] font-semibold text-[#1c1917]">{p.name}</p>
                            <p className="truncate text-[10px] text-[#a8a29e] md:hidden">{p.sku ?? '-'}</p>
                          </div>
                        </div>
                      </td>
                      <td className="hidden px-4 py-3 md:table-cell">
                        <p className="font-mono text-[12px] text-[#78716c]">{p.sku ?? '-'}</p>
                      </td>
                      <td className="hidden px-4 py-3 lg:table-cell">
                        <span className="inline-flex items-center gap-1 rounded-lg bg-[#f5f5f4] px-2 py-1 text-[11px] font-medium text-[#78716c]">
                          {IC.tag} {p.category?.name ?? '-'}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-right">
                        <p className="text-[14px] font-bold text-[#1c1917] tabular-nums">{formatRupiah(p.selling_price)}</p>
                        <p className="text-[10px] text-[#a8a29e] tabular-nums">modal {formatRupiah(p.cost_price)}</p>
                      </td>
                      <td className="hidden px-4 py-3 text-center sm:table-cell">
                        {p.has_variants ? (
                          <span className="inline-flex items-center gap-1 rounded-full bg-[#8b5cf6]/10 px-2.5 py-1 text-[10px] font-bold text-[#8b5cf6]">
                            {IC.layers} {p.variants?.length ?? 0}
                          </span>
                        ) : (
                          <span className="text-[11px] text-[#d6d3d1]">-</span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-center">
                        <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-bold ${p.is_active ? 'bg-[#16a34a]/10 text-[#16a34a]' : 'bg-[#dc2626]/10 text-[#dc2626]'}`}>
                          <span className={`h-1.5 w-1.5 rounded-full ${p.is_active ? 'bg-[#16a34a]' : 'bg-[#dc2626]'}`} />
                          {p.is_active ? 'Active' : 'Inactive'}
                        </span>
                      </td>
                      {canManage && (
                        <td className="px-4 py-3">
                          <div className="flex items-center justify-center gap-1">
                            <button onClick={() => handleEdit(p)} className="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-[#78716c] transition-all hover:bg-[#f54927]/10 hover:text-[#f54927]" title="Edit">
                              {IC.edit}
                            </button>
                            <button onClick={() => { setDeleteId(p.id); setDeleteName(p.name); setDeleteError('') }} className="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg text-[#78716c] transition-all hover:bg-[#dc2626]/10 hover:text-[#dc2626]" title="Hapus">
                              {IC.trash}
                            </button>
                          </div>
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* ===== Pagination ===== */}
        {lastPage > 1 && (
          <div className="animate-fade-up flex justify-center" style={{ animationDelay: '0.2s' }}>
            <div className="flex items-center gap-1">
              <button
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page === 1}
                className="flex h-9 cursor-pointer items-center gap-1 rounded-lg border border-[#e7e5e4] bg-white px-3 text-[12px] font-medium text-[#78716c] transition-all hover:bg-[#f5f5f4] disabled:cursor-not-allowed disabled:opacity-40"
              >
                Sebelumnya
              </button>
              <span className="px-3 text-[12px] font-medium text-[#78716c] tabular-nums">
                {page} / {lastPage}
              </span>
              <button
                onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
                disabled={page === lastPage}
                className="flex h-9 cursor-pointer items-center gap-1 rounded-lg border border-[#e7e5e4] bg-white px-3 text-[12px] font-medium text-[#78716c] transition-all hover:bg-[#f5f5f4] disabled:cursor-not-allowed disabled:opacity-40"
              >
                Berikutnya
              </button>
            </div>
          </div>
        )}
      </div>

      {/* ===== Product Form Modal ===== */}
      <ProductFormModal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        onSubmit={handleFormSubmit}
        product={editProduct}
        loading={formLoading}
      />

      {/* ===== Delete Confirm ===== */}
      <ConfirmDialog
        open={deleteId !== null}
        onClose={() => setDeleteId(null)}
        onConfirm={handleDelete}
        title="Hapus Produk?"
        message={`Apakah Anda yakin ingin menghapus "${deleteName}"?`}
        confirmLabel="Hapus"
        cancelLabel="Batal"
        loading={deleteLoading}
      />

      {deleteError && (
        <div className="fixed bottom-4 right-4 z-50 rounded-xl border border-[#dc2626]/20 bg-[#fef2f2] p-3 shadow-lg">
          <p className="text-[13px] font-medium text-[#dc2626]">{deleteError}</p>
        </div>
      )}
    </DashboardLayout>
  )
}
