import { useState, useEffect, useCallback, useMemo } from 'react'
import { useNavigate } from 'react-router-dom'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { AdjustStockModal } from '@/components/inventory/AdjustStockModal'
import { inventoryService } from '@/services/inventory'
import { storeService } from '@/services/store'
import { useAuthStore } from '@/stores/auth'
import type { Inventory, Store, PaginatedResponse } from '@/types'

/* ---------- Icons ---------- */

const IC = {
  search: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
  ),
  refresh: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
  ),
  transfer: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
  ),
  adjust: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
  ),
  history: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><path d="M12 7v5l4 2"/></svg>
  ),
  box: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
  ),
  alert: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
  ),
  xCircle: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
  ),
  check: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
  ),
  store: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M3 9l1-5h16l1 5M4 9v11h16V9M9 20v-6h6v6"/></svg>
  ),
  filter: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
  ),
  empty: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" className="h-12 w-12"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
  ),
  arrowRight: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
  ),
}

function getStockStatus(qty: number, minQty: number) {
  if (qty === 0) return { label: 'Habis', color: 'red', icon: IC.xCircle, bg: 'bg-[#dc2626]/10', text: 'text-[#dc2626]', dot: 'bg-[#dc2626]' }
  if (qty <= minQty) return { label: 'Stok Rendah', color: 'yellow', icon: IC.alert, bg: 'bg-[#ca8a04]/10', text: 'text-[#ca8a04]', dot: 'bg-[#ca8a04]' }
  return { label: 'Tersedia', color: 'green', icon: IC.check, bg: 'bg-[#16a34a]/10', text: 'text-[#16a34a]', dot: 'bg-[#16a34a]' }
}

function Skeleton({ className }: { className: string }) {
  return <div className={`animate-pulse rounded-xl bg-[#f0efec] ${className}`} />
}

/* ---------- Component ---------- */

export function InventoryPage() {
  const { user } = useAuthStore()
  const canManage = user?.role?.slug === 'owner' || user?.role?.slug === 'manager'
  const navigate = useNavigate()

  const [inventories, setInventories] = useState<Inventory[]>([])
  const [stores, setStores] = useState<Store[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [storeId, setStoreId] = useState('')
  const [search, setSearch] = useState('')
  const [lowStock, setLowStock] = useState(false)

  const [adjustOpen, setAdjustOpen] = useState(false)

  const fetchInventory = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params: Record<string, unknown> = { page, per_page: 20 }
      if (storeId) params.store_id = storeId
      if (lowStock) params.low_stock = true
      const res: PaginatedResponse<Inventory> = await inventoryService.list(params)
      let filtered = res.data
      if (search) {
        const q = search.toLowerCase()
        filtered = filtered.filter(
          (inv) =>
            inv.product?.name?.toLowerCase().includes(q) ||
            inv.product?.sku?.toLowerCase().includes(q),
        )
      }
      setInventories(filtered)
      setLastPage(res.last_page)
    } catch {
      setError('Failed to load inventory')
    } finally {
      setLoading(false)
    }
  }, [page, storeId, lowStock, search])

  useEffect(() => {
    storeService.list().then(setStores).catch(() => {})
  }, [])

  useEffect(() => {
    const timer = setTimeout(() => fetchInventory(), 250)
    return () => clearTimeout(timer)
  }, [search])

  useEffect(() => { fetchInventory() }, [fetchInventory])

  const summary = useMemo(() => {
    const inStock = inventories.filter((i) => i.quantity > i.minimum_quantity).length
    const lowStockCount = inventories.filter((i) => i.quantity > 0 && i.quantity <= i.minimum_quantity).length
    const outOfStock = inventories.filter((i) => i.quantity === 0).length
    const totalUnits = inventories.reduce((sum, i) => sum + i.quantity, 0)
    return { inStock, lowStockCount, outOfStock, totalUnits }
  }, [inventories])

  return (
    <DashboardLayout>
      <div className="space-y-5">
        {/* ===== Header ===== */}
        <div className="animate-fade-up flex items-center justify-between">
          <div>
            <h1 className="text-[22px] font-bold tracking-tight text-[#1c1917]">Inventory</h1>
            <p className="mt-1 text-[13px] text-[#78716c]">Kelola stok produk di semua toko</p>
          </div>
          {canManage && (
            <div className="flex gap-2">
              <button
                onClick={() => setAdjustOpen(true)}
                className="flex h-10 cursor-pointer items-center gap-2 rounded-xl border border-[#e7e5e4] bg-white px-4 text-[13px] font-semibold text-[#78716c] transition-all hover:border-[#f54927]/30 hover:bg-[#fef8f6] hover:text-[#f54927] active:scale-[0.97]"
              >
                {IC.adjust} Adjust Stock
              </button>
              <button
                onClick={() => navigate('/inventory/transfer')}
                className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-4 text-[13px] font-semibold text-white shadow-[0_4px_14px_rgba(245,73,39,0.3)] transition-all hover:shadow-[0_6px_20px_rgba(245,73,39,0.4)] active:scale-[0.97]"
              >
                {IC.transfer} Transfer Stock
              </button>
            </div>
          )}
        </div>

        {/* ===== Summary Cards ===== */}
        <div className="animate-fade-up grid grid-cols-2 gap-4 lg:grid-cols-4" style={{ animationDelay: '0.05s' }}>
          {loading && inventories.length === 0 ? (
            [...Array(4)].map((_, i) => <Skeleton key={i} className="h-[90px]" />)
          ) : (
            <>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Tersedia</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#16a34a] tabular-nums">{summary.inStock}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#16a34a]/10 text-[#16a34a]">{IC.check}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Stok Rendah</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#ca8a04] tabular-nums">{summary.lowStockCount}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#ca8a04]/10 text-[#ca8a04]">{IC.alert}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Habis</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#dc2626] tabular-nums">{summary.outOfStock}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#dc2626]/10 text-[#dc2626]">{IC.xCircle}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Total Unit</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#f54927] tabular-nums">{summary.totalUnits}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#f54927]/10 text-[#f54927]">{IC.box}</div>
                </div>
              </div>
            </>
          )}
        </div>

        {/* ===== Filters ===== */}
        <div className="animate-fade-up flex flex-wrap items-center gap-2" style={{ animationDelay: '0.1s' }}>
          {/* Store filter */}
          <div className="relative">
            <div className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-[#a8a29e]">{IC.store}</div>
            <select
              value={storeId}
              onChange={(e) => { setStoreId(e.target.value); setPage(1) }}
              className="h-10 cursor-pointer appearance-none rounded-xl border border-[#e7e5e4] bg-white pr-8 pl-9 text-[13px] font-medium text-[#1c1917] outline-none transition-all focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            >
              <option value="">Semua Toko</option>
              {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
            </select>
          </div>

          {/* Search */}
          <div className="relative min-w-[200px] flex-1">
            <div className="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-[#a8a29e]">{IC.search}</div>
            <input
              type="text"
              placeholder="Cari produk atau SKU..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="h-10 w-full rounded-xl border border-[#e7e5e4] bg-white pr-4 pl-10 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            />
          </div>

          {/* Low stock toggle */}
          <button
            onClick={() => { setLowStock(!lowStock); setPage(1) }}
            className={`flex h-10 cursor-pointer items-center gap-2 rounded-xl border px-4 text-[13px] font-semibold transition-all active:scale-[0.97] ${
              lowStock
                ? 'border-[#ca8a04] bg-[#ca8a04]/10 text-[#ca8a04]'
                : 'border-[#e7e5e4] bg-white text-[#78716c] hover:border-[#ca8a04]/30 hover:bg-[#ca8a04]/5 hover:text-[#ca8a04]'
            }`}
          >
            {IC.alert} Stok Rendah
          </button>

          {(search || storeId || lowStock) && (
            <button
              onClick={() => { setSearch(''); setStoreId(''); setLowStock(false); setPage(1) }}
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
            <button onClick={fetchInventory} className="mt-3 flex h-9 cursor-pointer items-center gap-2 rounded-xl border border-[#e7e5e4] bg-white px-4 text-[13px] font-medium text-[#78716c] transition-all hover:bg-[#f5f5f4]">
              {IC.refresh} Coba lagi
            </button>
          </div>
        ) : inventories.length === 0 ? (
          <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-[#e7e5e4] bg-white/50 py-20">
            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-[#f5f5f4] text-[#d6d3d1]">{IC.empty}</div>
            <p className="text-[15px] font-semibold text-[#78716c]">Belum ada inventory</p>
            <p className="mt-1 text-[12px] text-[#a8a29e]">Tambahkan produk ke toko untuk mulai melacak stok</p>
          </div>
        ) : (
          /* ===== Grid Cards ===== */
          <div className="animate-fade-up grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" style={{ animationDelay: '0.15s' }}>
            {inventories.map((inv) => {
              const status = getStockStatus(inv.quantity, inv.minimum_quantity)
              const stockPct = inv.minimum_quantity > 0
                ? Math.min(100, Math.round((inv.quantity / (inv.minimum_quantity * 2)) * 100))
                : inv.quantity > 0 ? 100 : 0
              return (
                <div
                  key={inv.id}
                  className="group flex flex-col rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all duration-200 hover:border-[#f54927]/30 hover:shadow-[0_8px_24px_rgba(245,73,39,0.08)]"
                >
                  {/* Header */}
                  <div className="flex items-start gap-3">
                    <div className={`flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl ${status.bg} ${status.text}`}>
                      {IC.box}
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-[14px] font-bold text-[#1c1917]">{inv.product?.name ?? `Product #${inv.product_id}`}</p>
                      <p className="mt-0.5 text-[11px] text-[#a8a29e]">SKU: <span className="font-mono font-semibold text-[#78716c]">{inv.product?.sku ?? '-'}</span></p>
                    </div>
                  </div>

                  {/* Store */}
                  <div className="mt-3 flex items-center gap-1.5 text-[11px] text-[#78716c]">
                    {IC.store}
                    <span className="truncate font-medium">{inv.store?.name ?? `Store #${inv.store_id}`}</span>
                  </div>

                  {/* Stock bar */}
                  <div className="mt-3 border-t border-[#f5f5f4] pt-3">
                    <div className="mb-1.5 flex items-center justify-between">
                      <span className="text-[10px] font-bold tracking-wide text-[#a8a29e] uppercase">Stok</span>
                      <span className="text-[18px] font-bold tabular-nums text-[#1c1917]">{inv.quantity}</span>
                    </div>
                    <div className="h-1.5 w-full overflow-hidden rounded-full bg-[#f5f5f4]">
                      <div
                        className={`h-full rounded-full transition-all ${status.dot}`}
                        style={{ width: `${stockPct}%` }}
                      />
                    </div>
                    <div className="mt-1.5 flex items-center justify-between text-[10px] text-[#a8a29e]">
                      <span>Min: {inv.minimum_quantity}</span>
                      <span className={`flex items-center gap-1 font-semibold ${status.text}`}>
                        <span className={`h-1.5 w-1.5 rounded-full ${status.dot}`} />
                        {status.label}
                      </span>
                    </div>
                  </div>

                  {/* Action */}
                  <button
                    onClick={() => navigate('/inventory/movements')}
                    className="mt-3 flex h-8 cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-[#e7e5e4] text-[12px] font-semibold text-[#78716c] transition-all hover:border-[#0a84ff]/30 hover:bg-[#0a84ff]/5 hover:text-[#0a84ff]"
                  >
                    {IC.history} Riwayat
                  </button>
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
              <span className="px-3 text-[12px] font-medium text-[#78716c] tabular-nums">{page} / {lastPage}</span>
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

      <AdjustStockModal
        open={adjustOpen}
        onClose={() => setAdjustOpen(false)}
        onSuccess={fetchInventory}
      />
    </DashboardLayout>
  )
}
