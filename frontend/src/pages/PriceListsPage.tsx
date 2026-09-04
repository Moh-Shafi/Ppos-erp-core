import { useState, useEffect, useCallback, useMemo } from 'react'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { Button } from '@/components/ui/Button'
import { Modal, ConfirmDialog } from '@/components/ui/Modal'
import { priceListService } from '@/services/priceList'
import { productService } from '@/services/product'
import { formatRupiah, parseRupiah } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth'
import type { PriceList, Product, PaginatedResponse } from '@/types'

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
  eye: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
  ),
  tag: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
  ),
  star: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="none" className="h-3 w-3"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
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
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" className="h-12 w-12"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/></svg>
  ),
  list: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
  ),
  layers: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
  ),
  power: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
  ),
  arrowRight: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
  ),
  close: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
  ),
}

/* ---------- Card color palette ---------- */

const PL_COLORS = [
  { bg: 'bg-[#f54927]/10', text: 'text-[#f54927]', grad: 'from-[#f54927] to-[#ff6b4a]' },
  { bg: 'bg-[#0a84ff]/10', text: 'text-[#0a84ff]', grad: 'from-[#0a84ff] to-[#3b82f6]' },
  { bg: 'bg-[#16a34a]/10', text: 'text-[#16a34a]', grad: 'from-[#16a34a] to-[#22c55e]' },
  { bg: 'bg-[#8b5cf6]/10', text: 'text-[#8b5cf6]', grad: 'from-[#8b5cf6] to-[#a78bfa]' },
  { bg: 'bg-[#ca8a04]/10', text: 'text-[#ca8a04]', grad: 'from-[#ca8a04] to-[#facc15]' },
  { bg: 'bg-[#ec4899]/10', text: 'text-[#ec4899]', grad: 'from-[#ec4899] to-[#f472b6]' },
]

function getColor(idx: number) {
  return PL_COLORS[idx % PL_COLORS.length]
}

function Skeleton({ className }: { className: string }) {
  return <div className={`animate-pulse rounded-xl bg-[#f0efec] ${className}`} />
}

/* ---------- Component ---------- */

export function PriceListsPage() {
  const { user } = useAuthStore()
  const canManage = user?.role?.slug === 'owner' || user?.role?.slug === 'manager'

  const [priceLists, setPriceLists] = useState<PriceList[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [search, setSearch] = useState('')

  const [formOpen, setFormOpen] = useState(false)
  const [editList, setEditList] = useState<PriceList | null>(null)
  const [formLoading, setFormLoading] = useState(false)
  const [form, setForm] = useState({ name: '', description: '', is_default: false, is_active: true })
  const [formErrors, setFormErrors] = useState<Record<string, string[]>>({})

  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [deleteName, setDeleteName] = useState('')
  const [deleteLoading, setDeleteLoading] = useState(false)
  const [deleteError, setDeleteError] = useState('')

  const [detailList, setDetailList] = useState<PriceList | null>(null)
  const [products, setProducts] = useState<Product[]>([])
  const [itemForm, setItemForm] = useState({ product_id: '', price: '' })
  const [itemLoading, setItemLoading] = useState(false)

  const fetchPriceLists = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params: Record<string, unknown> = { page, per_page: 20 }
      if (search) params.search = search
      const res: PaginatedResponse<PriceList> = await priceListService.list(params)
      setPriceLists(res.data)
      setLastPage(res.last_page)
      setTotal(res.total)
    } catch {
      setError('Failed to load price lists')
    } finally {
      setLoading(false)
    }
  }, [page, search])

  useEffect(() => {
    const timer = setTimeout(() => { setPage(1); fetchPriceLists() }, 250)
    return () => clearTimeout(timer)
  }, [search])

  useEffect(() => { fetchPriceLists() }, [page])

  const handleAdd = () => {
    setEditList(null)
    setForm({ name: '', description: '', is_default: false, is_active: true })
    setFormErrors({})
    setFormOpen(true)
  }

  const handleEdit = (pl: PriceList) => {
    setEditList(pl)
    setForm({ name: pl.name, description: pl.description ?? '', is_default: pl.is_default, is_active: pl.is_active })
    setFormErrors({})
    setFormOpen(true)
  }

  const handleFormSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setFormLoading(true)
    setFormErrors({})
    try {
      if (editList) {
        await priceListService.update(editList.id, form)
      } else {
        await priceListService.create(form)
      }
      setFormOpen(false)
      fetchPriceLists()
    } catch (err: any) {
      if (err.response?.data?.errors) {
        setFormErrors(err.response.data.errors)
      }
    } finally {
      setFormLoading(false)
    }
  }

  const handleDelete = async () => {
    if (!deleteId) return
    setDeleteLoading(true)
    setDeleteError('')
    try {
      await priceListService.delete(deleteId)
      setDeleteId(null)
      fetchPriceLists()
    } catch (err: any) {
      setDeleteError(err.response?.data?.message ?? 'Failed to delete price list')
    } finally {
      setDeleteLoading(false)
    }
  }

  const handleViewDetail = async (pl: PriceList) => {
    try {
      const detail = await priceListService.show(pl.id)
      setDetailList(detail)
      productService.list({ per_page: 100 }).then((res) => setProducts(res.data)).catch(() => {})
      setItemForm({ product_id: '', price: '' })
    } catch {
      // ignore
    }
  }

  const handleAddItem = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!detailList || !itemForm.product_id) return
    setItemLoading(true)
    try {
      await priceListService.addItem(detailList.id, {
        product_id: parseInt(itemForm.product_id, 10),
        price: parseRupiah(itemForm.price),
      })
      const detail = await priceListService.show(detailList.id)
      setDetailList(detail)
      setItemForm({ product_id: '', price: '' })
    } catch {
      // ignore
    } finally {
      setItemLoading(false)
    }
  }

  const handleDeleteItem = async (itemId: number) => {
    if (!detailList) return
    try {
      await priceListService.deleteItem(detailList.id, itemId)
      const detail = await priceListService.show(detailList.id)
      setDetailList(detail)
    } catch {
      // ignore
    }
  }

  const summary = useMemo(() => {
    const active = priceLists.filter((p) => p.is_active).length
    const defaults = priceLists.filter((p) => p.is_default).length
    const totalItems = priceLists.reduce((sum, p) => sum + (p.items?.length ?? 0), 0)
    return { active, defaults, totalItems, inactive: priceLists.length - active }
  }, [priceLists])

  return (
    <DashboardLayout>
      <div className="space-y-5">
        {/* ===== Header ===== */}
        <div className="animate-fade-up flex items-center justify-between">
          <div>
            <h1 className="text-[22px] font-bold tracking-tight text-[#1c1917]">Daftar Harga</h1>
            <p className="mt-1 text-[13px] text-[#78716c]">Kelola daftar harga dan item produk</p>
          </div>
          {canManage && (
            <button
              onClick={handleAdd}
              className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-4 text-[13px] font-semibold text-white shadow-[0_4px_14px_rgba(245,73,39,0.3)] transition-all hover:shadow-[0_6px_20px_rgba(245,73,39,0.4)] active:scale-[0.97]"
            >
              {IC.plus} Tambah Price List
            </button>
          )}
        </div>

        {/* ===== Summary Cards ===== */}
        <div className="animate-fade-up grid grid-cols-2 gap-4 lg:grid-cols-4" style={{ animationDelay: '0.05s' }}>
          {loading && priceLists.length === 0 ? (
            [...Array(4)].map((_, i) => <Skeleton key={i} className="h-[90px]" />)
          ) : (
            <>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Total Price List</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#1c1917] tabular-nums">{total}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#0a84ff]/10 text-[#0a84ff]">{IC.tag}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Aktif</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#16a34a] tabular-nums">{summary.active}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#16a34a]/10 text-[#16a34a]">{IC.power}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Default</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#ca8a04] tabular-nums">{summary.defaults}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#ca8a04]/10 text-[#ca8a04]">{IC.star}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Total Item</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#f54927] tabular-nums">{summary.totalItems}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#f54927]/10 text-[#f54927]">{IC.list}</div>
                </div>
              </div>
            </>
          )}
        </div>

        {/* ===== Search ===== */}
        <div className="animate-fade-up flex flex-wrap items-center gap-2" style={{ animationDelay: '0.1s' }}>
          <div className="relative min-w-[240px] flex-1">
            <div className="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-[#a8a29e]">{IC.search}</div>
            <input
              type="text"
              placeholder="Cari daftar harga..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="h-10 w-full rounded-xl border border-[#e7e5e4] bg-white pr-4 pl-10 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            />
          </div>
          {search && (
            <button
              onClick={() => setSearch('')}
              className="flex h-10 cursor-pointer items-center gap-1.5 rounded-xl border border-[#e7e5e4] bg-white px-3 text-[12px] font-medium text-[#78716c] transition-all hover:border-[#dc2626]/30 hover:text-[#dc2626]"
            >
              {IC.filter} Reset
            </button>
          )}
        </div>

        {/* ===== Content ===== */}
        {loading ? (
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {[...Array(8)].map((_, i) => (
              <div key={i} className="rounded-2xl border border-[#e7e5e4] bg-white p-4">
                <div className="flex items-center gap-3">
                  <Skeleton className="h-12 w-12 rounded-xl" />
                  <div className="flex-1 space-y-2">
                    <Skeleton className="h-4 w-24" />
                    <Skeleton className="h-3 w-16" />
                  </div>
                </div>
                <Skeleton className="mt-3 h-8 w-full" />
              </div>
            ))}
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center rounded-2xl border border-[#dc2626]/20 bg-[#fef2f2] py-16">
            <p className="text-[15px] font-semibold text-[#dc2626]">{error}</p>
            <button onClick={fetchPriceLists} className="mt-3 cursor-pointer rounded-xl border border-[#e7e5e4] bg-white px-4 py-2 text-[13px] font-medium text-[#78716c] transition-all hover:bg-[#f5f5f4]">
              Coba lagi
            </button>
          </div>
        ) : priceLists.length === 0 ? (
          <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-[#e7e5e4] bg-white/50 py-20">
            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-[#f5f5f4] text-[#d6d3d1]">{IC.empty}</div>
            <p className="text-[15px] font-semibold text-[#78716c]">Belum ada daftar harga</p>
            <p className="mt-1 text-[12px] text-[#a8a29e]">Tambahkan daftar harga pertama Anda</p>
            {canManage && (
              <button onClick={handleAdd} className="mt-4 flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-4 text-[13px] font-semibold text-white shadow-sm transition-all hover:shadow-md active:scale-[0.98]">
                {IC.plus} Tambah Price List
              </button>
            )}
          </div>
        ) : (
          /* ===== Grid Cards ===== */
          <div className="animate-fade-up grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" style={{ animationDelay: '0.15s' }}>
            {priceLists.map((pl, idx) => {
              const color = getColor(idx)
              const itemCount = pl.items?.length ?? 0
              return (
                <div
                  key={pl.id}
                  className="group flex flex-col rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all duration-200 hover:border-[#f54927]/30 hover:shadow-[0_8px_24px_rgba(245,73,39,0.08)]"
                >
                  {/* Header */}
                  <div className="flex items-start gap-3">
                    <div className={`relative flex h-12 w-12 flex-shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br ${color.grad}`}>
                      <span className="text-[14px] font-bold text-white/90">{pl.name.slice(0, 2).toUpperCase()}</span>
                      {pl.is_default && (
                        <div className="absolute top-0 right-0 flex h-4 w-4 items-center justify-center rounded-bl-lg bg-[#ca8a04] text-white">
                          {IC.star}
                        </div>
                      )}
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-[14px] font-bold text-[#1c1917]">{pl.name}</p>
                      <p className="mt-0.5 truncate text-[11px] text-[#a8a29e]">{pl.description ?? 'Tanpa deskripsi'}</p>
                    </div>
                  </div>

                  {/* Status badges */}
                  <div className="mt-3 flex flex-wrap gap-1.5">
                    <span className={`flex items-center gap-1 rounded-full px-2 py-0.5 text-[9px] font-bold ${pl.is_active ? 'bg-[#16a34a]/10 text-[#16a34a]' : 'bg-[#a8a29e]/10 text-[#a8a29e]'}`}>
                      <span className={`h-1.5 w-1.5 rounded-full ${pl.is_active ? 'bg-[#16a34a]' : 'bg-[#a8a29e]'}`} />
                      {pl.is_active ? 'AKTIF' : 'NONAKTIF'}
                    </span>
                    {pl.is_default && (
                      <span className="flex items-center gap-1 rounded-full bg-[#ca8a04]/10 px-2 py-0.5 text-[9px] font-bold text-[#ca8a04]">
                        {IC.star} DEFAULT
                      </span>
                    )}
                    <span className="flex items-center gap-1 rounded-full bg-[#0a84ff]/10 px-2 py-0.5 text-[9px] font-bold text-[#0a84ff]">
                      {IC.list} {itemCount} ITEM
                    </span>
                  </div>

                  {/* Items preview */}
                  {pl.items && pl.items.length > 0 && (
                    <div className="mt-3 space-y-1.5 border-t border-[#f5f5f4] pt-3">
                      <p className="text-[10px] font-bold tracking-wide text-[#a8a29e] uppercase">Item Termahal</p>
                      {pl.items.slice(0, 2).map((item) => (
                        <div key={item.id} className="flex items-center justify-between text-[11px]">
                          <span className="truncate text-[#78716c]">{item.product?.name ?? `#${item.product_id}`}</span>
                          <span className="ml-2 flex-shrink-0 font-mono font-semibold text-[#f54927]">{formatRupiah(item.price)}</span>
                        </div>
                      ))}
                      {pl.items.length > 2 && (
                        <p className="text-[10px] text-[#a8a29e]">+{pl.items.length - 2} item lainnya</p>
                      )}
                    </div>
                  )}

                  {/* Actions */}
                  <div className="mt-3 flex gap-2 border-t border-[#f5f5f4] pt-3">
                    <button
                      onClick={() => handleViewDetail(pl)}
                      className="flex h-8 flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-[#e7e5e4] text-[12px] font-semibold text-[#78716c] transition-all hover:border-[#0a84ff]/30 hover:bg-[#0a84ff]/5 hover:text-[#0a84ff]"
                    >
                      {IC.eye} Detail
                    </button>
                    {canManage && (
                      <>
                        <button
                          onClick={() => handleEdit(pl)}
                          className="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-[#e7e5e4] text-[#78716c] transition-all hover:border-[#f54927]/30 hover:bg-[#f54927]/5 hover:text-[#f54927]"
                        >
                          {IC.edit}
                        </button>
                        <button
                          onClick={() => { setDeleteId(pl.id); setDeleteName(pl.name); setDeleteError('') }}
                          className="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-[#e7e5e4] text-[#78716c] transition-all hover:border-[#dc2626]/30 hover:bg-[#dc2626]/5 hover:text-[#dc2626]"
                        >
                          {IC.trash}
                        </button>
                      </>
                    )}
                  </div>
                </div>
              )
            })}
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

      {/* ===== Form Modal ===== */}
      <Modal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        title={editList ? 'Edit Price List' : 'Tambah Price List'}
        footer={
          <div className="flex w-full items-center justify-between">
            <Button variant="outline" onClick={() => setFormOpen(false)} disabled={formLoading}>
              Batal
            </Button>
            <Button onClick={handleFormSubmit} disabled={formLoading}>
              {formLoading ? (
                <span className="flex items-center gap-2">
                  <span className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                  Menyimpan...
                </span>
              ) : (
                <span className="flex items-center gap-2">
                  {IC.check} {editList ? 'Update' : 'Simpan'}
                </span>
              )}
            </Button>
          </div>
        }
      >
        <form onSubmit={handleFormSubmit} className="space-y-5">
          {/* Preview */}
          <div className="flex items-center gap-3 rounded-xl bg-gradient-to-r from-[#f54927]/5 to-transparent p-4">
            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-[#f54927] to-[#ff6b4a] text-white">
              <span className="text-[14px] font-bold">{(form.name || '?').slice(0, 2).toUpperCase()}</span>
            </div>
            <div>
              <p className="text-[13px] font-bold text-[#1c1917]">{form.name || 'Nama Price List'}</p>
              <p className="text-[11px] text-[#a8a29e]">{form.is_active ? 'Aktif' : 'Nonaktif'}{form.is_default ? ' · Default' : ''}</p>
            </div>
          </div>

          <div className="space-y-1.5">
            <label className="flex items-center gap-1.5 text-[12px] font-semibold text-[#44403c]">
              {IC.tag} Nama Price List <span className="text-[#f54927]">*</span>
            </label>
            <input
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              placeholder="Retail Price"
              className="h-10 w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            />
            {formErrors.name && <p className="text-[11px] font-medium text-[#dc2626]">{formErrors.name[0]}</p>}
          </div>

          <div className="space-y-1.5">
            <label className="text-[12px] font-semibold text-[#44403c]">Deskripsi</label>
            <textarea
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
              placeholder="Deskripsi price list..."
              rows={3}
              className="w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 py-2.5 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            />
          </div>

          {/* Toggle switches */}
          <div className="grid grid-cols-2 gap-3">
            <label className={`flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition-all ${form.is_default ? 'border-[#ca8a04]/30 bg-[#ca8a04]/5' : 'border-[#e7e5e4] bg-white hover:bg-[#fafaf9]'}`}>
              <div className={`flex h-5 w-5 items-center justify-center rounded-md transition-all ${form.is_default ? 'bg-[#ca8a04] text-white' : 'border border-[#e7e5e4] bg-white'}`}>
                {form.is_default && IC.check}
              </div>
              <input type="checkbox" checked={form.is_default} onChange={(e) => setForm({ ...form, is_default: e.target.checked })} className="hidden" />
              <div>
                <p className="text-[12px] font-semibold text-[#1c1917]">Default</p>
                <p className="text-[10px] text-[#a8a29e]">Price list utama</p>
              </div>
            </label>

            <label className={`flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition-all ${form.is_active ? 'border-[#16a34a]/30 bg-[#16a34a]/5' : 'border-[#e7e5e4] bg-white hover:bg-[#fafaf9]'}`}>
              <div className={`flex h-5 w-5 items-center justify-center rounded-md transition-all ${form.is_active ? 'bg-[#16a34a] text-white' : 'border border-[#e7e5e4] bg-white'}`}>
                {form.is_active && IC.check}
              </div>
              <input type="checkbox" checked={form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} className="hidden" />
              <div>
                <p className="text-[12px] font-semibold text-[#1c1917]">Aktif</p>
                <p className="text-[10px] text-[#a8a29e]">Price list aktif</p>
              </div>
            </label>
          </div>
        </form>
      </Modal>

      {/* ===== Detail Modal ===== */}
      <Modal
        open={detailList !== null}
        onClose={() => setDetailList(null)}
        title={`Detail: ${detailList?.name ?? ''}`}
        size="lg"
      >
        <div className="space-y-5">
          {/* Stats bar */}
          {detailList && (
            <div className="grid grid-cols-3 gap-3">
              <div className="rounded-xl bg-[#f5f5f4] p-3 text-center">
                <p className="text-[10px] font-medium text-[#a8a29e]">Status</p>
                <p className={`mt-1 text-[13px] font-bold ${detailList.is_active ? 'text-[#16a34a]' : 'text-[#a8a29e]'}`}>{detailList.is_active ? 'Aktif' : 'Nonaktif'}</p>
              </div>
              <div className="rounded-xl bg-[#f5f5f4] p-3 text-center">
                <p className="text-[10px] font-medium text-[#a8a29e]">Default</p>
                <p className={`mt-1 text-[13px] font-bold ${detailList.is_default ? 'text-[#ca8a04]' : 'text-[#a8a29e]'}`}>{detailList.is_default ? 'Ya' : 'Tidak'}</p>
              </div>
              <div className="rounded-xl bg-[#f5f5f4] p-3 text-center">
                <p className="text-[10px] font-medium text-[#a8a29e]">Total Item</p>
                <p className="mt-1 text-[13px] font-bold text-[#f54927] tabular-nums">{detailList.items?.length ?? 0}</p>
              </div>
            </div>
          )}

          {/* Add item form */}
          {canManage && (
            <form onSubmit={handleAddItem} className="rounded-xl border border-[#e7e5e4] bg-[#fafaf9] p-4">
              <p className="mb-3 text-[12px] font-bold tracking-wide text-[#44403c] uppercase">Tambah Item</p>
              <div className="flex flex-wrap items-end gap-2">
                <div className="min-w-[200px] flex-1 space-y-1.5">
                  <label className="text-[11px] font-medium text-[#78716c]">Produk</label>
                  <select
                    value={itemForm.product_id}
                    onChange={(e) => setItemForm({ ...itemForm, product_id: e.target.value })}
                    className="h-10 w-full cursor-pointer rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
                  >
                    <option value="">Pilih produk...</option>
                    {products.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                  </select>
                </div>
                <div className="w-40 space-y-1.5">
                  <label className="text-[11px] font-medium text-[#78716c]">Harga</label>
                  <input
                    value={itemForm.price}
                    onChange={(e) => setItemForm({ ...itemForm, price: formatRupiah(parseRupiah(e.target.value)) })}
                    placeholder="Rp 0"
                    inputMode="numeric"
                    className="h-10 w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
                  />
                </div>
                <button
                  type="submit"
                  disabled={itemLoading || !itemForm.product_id}
                  className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-4 text-[13px] font-semibold text-white shadow-sm transition-all hover:shadow-md active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50"
                >
                  {itemLoading ? (
                    <span className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                  ) : IC.plus}
                  Tambah
                </button>
              </div>
            </form>
          )}

          {/* Items list */}
          {detailList?.items && detailList.items.length > 0 ? (
            <div className="space-y-2">
              <p className="text-[12px] font-bold tracking-wide text-[#44403c] uppercase">Daftar Item</p>
              <div className="max-h-[400px] space-y-2 overflow-y-auto pr-1">
                {detailList.items.map((item, idx) => {
                  const color = getColor(idx)
                  return (
                    <div key={item.id} className="group flex items-center gap-3 rounded-xl border border-[#e7e5e4] bg-white p-3 transition-all hover:border-[#f54927]/20 hover:shadow-sm">
                      <div className={`flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg ${color.bg} ${color.text}`}>
                        <span className="text-[11px] font-bold">{(item.product?.name ?? '?').slice(0, 2).toUpperCase()}</span>
                      </div>
                      <div className="min-w-0 flex-1">
                        <p className="truncate text-[13px] font-semibold text-[#1c1917]">{item.product?.name ?? `#${item.product_id}`}</p>
                        <p className="text-[11px] text-[#a8a29e]">ID: {item.product_id}</p>
                      </div>
                      <div className="flex items-center gap-3">
                        <span className="font-mono text-[14px] font-bold text-[#f54927] tabular-nums">{formatRupiah(item.price)}</span>
                        {canManage && (
                          <button
                            onClick={() => handleDeleteItem(item.id)}
                            className="flex h-7 w-7 cursor-pointer items-center justify-center rounded-lg text-[#a8a29e] transition-all hover:bg-[#dc2626]/10 hover:text-[#dc2626]"
                          >
                            {IC.trash}
                          </button>
                        )}
                      </div>
                    </div>
                  )
                })}
              </div>
            </div>
          ) : (
            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-[#e7e5e4] py-12">
              <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-[#f5f5f4] text-[#d6d3d1]">{IC.list}</div>
              <p className="text-[13px] font-semibold text-[#78716c]">Belum ada item</p>
              <p className="mt-1 text-[11px] text-[#a8a29e]">Tambahkan produk ke daftar harga ini</p>
            </div>
          )}
        </div>
      </Modal>

      {/* ===== Delete Confirm ===== */}
      <ConfirmDialog
        open={deleteId !== null}
        onClose={() => setDeleteId(null)}
        onConfirm={handleDelete}
        title="Hapus Price List?"
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
