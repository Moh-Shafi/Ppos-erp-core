import { useState, useEffect, useCallback, useMemo } from 'react'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { inventoryService } from '@/services/inventory'
import { storeService } from '@/services/store'
import type { InventoryMovement, Store, PaginatedResponse } from '@/types'

/* ---------- Icons ---------- */

const IC = {
  search: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
  ),
  refresh: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
  ),
  store: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M3 9l1-5h16l1 5M4 9v11h16V9M9 20v-6h6v6"/></svg>
  ),
  filter: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
  ),
  arrowRight: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
  ),
  arrowUp: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
  ),
  arrowDown: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
  ),
  empty: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" className="h-12 w-12"><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><path d="M12 7v5l4 2"/></svg>
  ),
  history: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><path d="M12 7v5l4 2"/></svg>
  ),
  box: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
  ),
  user: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
  ),
  trending: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
  ),
  trendingDown: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg>
  ),
  exchange: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
  ),
}

/* ---------- Movement type config ---------- */

const MOVEMENT_CONFIG: Record<string, { label: string; bg: string; text: string; dot: string; icon: React.JSX.Element }> = {
  purchase: { label: 'Pembelian', bg: 'bg-[#16a34a]/10', text: 'text-[#16a34a]', dot: 'bg-[#16a34a]', icon: IC.arrowUp },
  sale: { label: 'Penjualan', bg: 'bg-[#dc2626]/10', text: 'text-[#dc2626]', dot: 'bg-[#dc2626]', icon: IC.arrowDown },
  sale_return: { label: 'Retur Jual', bg: 'bg-[#ca8a04]/10', text: 'text-[#ca8a04]', dot: 'bg-[#ca8a04]', icon: IC.arrowUp },
  purchase_return: { label: 'Retur Beli', bg: 'bg-[#ca8a04]/10', text: 'text-[#ca8a04]', dot: 'bg-[#ca8a04]', icon: IC.arrowDown },
  adjustment: { label: 'Penyesuaian', bg: 'bg-[#0a84ff]/10', text: 'text-[#0a84ff]', dot: 'bg-[#0a84ff]', icon: IC.exchange },
  transfer_in: { label: 'Transfer Masuk', bg: 'bg-[#16a34a]/10', text: 'text-[#16a34a]', dot: 'bg-[#16a34a]', icon: IC.arrowUp },
  transfer_out: { label: 'Transfer Keluar', bg: 'bg-[#dc2626]/10', text: 'text-[#dc2626]', dot: 'bg-[#dc2626]', icon: IC.arrowDown },
  initial: { label: 'Stok Awal', bg: 'bg-[#a8a29e]/10', text: 'text-[#a8a29e]', dot: 'bg-[#a8a29e]', icon: IC.box },
}

function getConfig(type: string) {
  return MOVEMENT_CONFIG[type] ?? { label: type, bg: 'bg-[#a8a29e]/10', text: 'text-[#a8a29e]', dot: 'bg-[#a8a29e]', icon: IC.history }
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}

function Skeleton({ className }: { className: string }) {
  return <div className={`animate-pulse rounded-xl bg-[#f0efec] ${className}`} />
}

/* ---------- Component ---------- */

export function MovementsPage() {
  const [movements, setMovements] = useState<InventoryMovement[]>([])
  const [stores, setStores] = useState<Store[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [storeId, setStoreId] = useState('')
  const [type, setType] = useState('')

  const fetchMovements = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params: Record<string, unknown> = { page, per_page: 20 }
      if (storeId) params.store_id = storeId
      if (type) params.type = type
      const res: PaginatedResponse<InventoryMovement> = await inventoryService.movements(params)
      setMovements(res.data)
      setLastPage(res.last_page)
      setTotal(res.total)
    } catch {
      setError('Failed to load movements')
    } finally {
      setLoading(false)
    }
  }, [page, storeId, type])

  useEffect(() => {
    storeService.list().then(setStores).catch(() => {})
  }, [])

  useEffect(() => { fetchMovements() }, [fetchMovements])

  const summary = useMemo(() => {
    const stockIn = movements.filter((m) => m.quantity > 0).reduce((sum, m) => sum + m.quantity, 0)
    const stockOut = movements.filter((m) => m.quantity < 0).reduce((sum, m) => sum + Math.abs(m.quantity), 0)
    const byType: Record<string, number> = {}
    movements.forEach((m) => { byType[m.type] = (byType[m.type] ?? 0) + 1 })
    const topType = Object.entries(byType).sort((a, b) => b[1] - a[1])[0]
    return { stockIn, stockOut, net: stockIn - stockOut, topType: topType ? topType[0] : null }
  }, [movements])

  return (
    <DashboardLayout>
      <div className="space-y-5">
        {/* ===== Header ===== */}
        <div className="animate-fade-up">
          <h1 className="text-[22px] font-bold tracking-tight text-[#1c1917]">Riwayat Pergerakan</h1>
          <p className="mt-1 text-[13px] text-[#78716c]">Catatan semua pergerakan stok produk</p>
        </div>

        {/* ===== Summary Cards ===== */}
        <div className="animate-fade-up grid grid-cols-2 gap-4 lg:grid-cols-4" style={{ animationDelay: '0.05s' }}>
          {loading && movements.length === 0 ? (
            [...Array(4)].map((_, i) => <Skeleton key={i} className="h-[90px]" />)
          ) : (
            <>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Total Pergerakan</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#1c1917] tabular-nums">{total}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#0a84ff]/10 text-[#0a84ff]">{IC.history}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Stok Masuk</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#16a34a] tabular-nums">+{summary.stockIn}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#16a34a]/10 text-[#16a34a]">{IC.trending}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Stok Keluar</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#dc2626] tabular-nums">-{summary.stockOut}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#dc2626]/10 text-[#dc2626]">{IC.trendingDown}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Net Pergerakan</p>
                    <p className={`mt-1 text-[22px] font-bold tracking-tight tabular-nums ${summary.net >= 0 ? 'text-[#16a34a]' : 'text-[#dc2626]'}`}>
                      {summary.net >= 0 ? '+' : ''}{summary.net}
                    </p>
                  </div>
                  <div className={`flex h-9 w-9 items-center justify-center rounded-xl ${summary.net >= 0 ? 'bg-[#16a34a]/10 text-[#16a34a]' : 'bg-[#dc2626]/10 text-[#dc2626]'}`}>
                    {summary.net >= 0 ? IC.trending : IC.trendingDown}
                  </div>
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

          {/* Type filter */}
          <div className="relative">
            <div className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-[#a8a29e]">{IC.filter}</div>
            <select
              value={type}
              onChange={(e) => { setType(e.target.value); setPage(1) }}
              className="h-10 cursor-pointer appearance-none rounded-xl border border-[#e7e5e4] bg-white pr-8 pl-9 text-[13px] font-medium text-[#1c1917] outline-none transition-all focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            >
              <option value="">Semua Tipe</option>
              {Object.entries(MOVEMENT_CONFIG).map(([value, cfg]) => (
                <option key={value} value={value}>{cfg.label}</option>
              ))}
            </select>
          </div>

          {(storeId || type) && (
            <button
              onClick={() => { setStoreId(''); setType(''); setPage(1) }}
              className="flex h-10 cursor-pointer items-center gap-1.5 rounded-xl border border-[#e7e5e4] bg-white px-3 text-[12px] font-medium text-[#78716c] transition-all hover:border-[#dc2626]/30 hover:text-[#dc2626]"
            >
              {IC.filter} Reset
            </button>
          )}
        </div>

        {/* ===== Content ===== */}
        {loading ? (
          <div className="space-y-3">
            {[...Array(6)].map((_, i) => (
              <div key={i} className="rounded-2xl border border-[#e7e5e4] bg-white p-4">
                <div className="flex items-center gap-3">
                  <Skeleton className="h-10 w-10 rounded-xl" />
                  <div className="flex-1 space-y-2">
                    <Skeleton className="h-4 w-32" />
                    <Skeleton className="h-3 w-48" />
                  </div>
                  <Skeleton className="h-8 w-20" />
                </div>
              </div>
            ))}
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center rounded-2xl border border-[#dc2626]/20 bg-[#fef2f2] py-16">
            <p className="text-[15px] font-semibold text-[#dc2626]">{error}</p>
            <button onClick={fetchMovements} className="mt-3 flex h-9 cursor-pointer items-center gap-2 rounded-xl border border-[#e7e5e4] bg-white px-4 text-[13px] font-medium text-[#78716c] transition-all hover:bg-[#f5f5f4]">
              {IC.refresh} Coba lagi
            </button>
          </div>
        ) : movements.length === 0 ? (
          <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-[#e7e5e4] bg-white/50 py-20">
            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-[#f5f5f4] text-[#d6d3d1]">{IC.empty}</div>
            <p className="text-[15px] font-semibold text-[#78716c]">Belum ada pergerakan</p>
            <p className="mt-1 text-[12px] text-[#a8a29e]">Pergerakan stok akan muncul setelah penyesuaian inventory</p>
          </div>
        ) : (
          /* ===== Timeline Cards ===== */
          <div className="animate-fade-up space-y-3" style={{ animationDelay: '0.15s' }}>
            {movements.map((m) => {
              const cfg = getConfig(m.type)
              const isPositive = m.quantity > 0
              return (
                <div
                  key={m.id}
                  className="group flex items-center gap-4 rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all duration-200 hover:border-[#f54927]/20 hover:shadow-[0_4px_16px_rgba(0,0,0,0.04)]"
                >
                  {/* Type icon */}
                  <div className={`flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl ${cfg.bg} ${cfg.text}`}>
                    {cfg.icon}
                  </div>

                  {/* Content */}
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                      <p className="truncate text-[14px] font-bold text-[#1c1917]">{m.product?.name ?? `#${m.product_id}`}</p>
                      <span className={`flex items-center gap-1 rounded-full ${cfg.bg} px-2 py-0.5 text-[9px] font-bold ${cfg.text}`}>
                        <span className={`h-1.5 w-1.5 rounded-full ${cfg.dot}`} />
                        {cfg.label}
                      </span>
                    </div>
                    <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] text-[#a8a29e]">
                      <span className="flex items-center gap-1">{IC.store} {m.store?.name ?? `#${m.store_id}`}</span>
                      <span>·</span>
                      <span>{formatDate(m.created_at)}</span>
                      {m.user && (
                        <>
                          <span>·</span>
                          <span className="flex items-center gap-1">{IC.user} {m.user.name}</span>
                        </>
                      )}
                      {m.note && (
                        <>
                          <span>·</span>
                          <span className="truncate italic">"{m.note}"</span>
                        </>
                      )}
                    </div>
                  </div>

                  {/* Quantity + Before→After */}
                  <div className="flex flex-shrink-0 items-center gap-4">
                    {/* Before → After */}
                    <div className="hidden text-right sm:block">
                      <p className="text-[10px] font-medium text-[#a8a29e]">Sebelum → Sesudah</p>
                      <p className="mt-0.5 text-[12px] font-semibold text-[#78716c] tabular-nums">
                        {m.before_quantity} <span className="text-[#d6d3d1]">{IC.arrowRight}</span> <span className="text-[#1c1917]">{m.after_quantity}</span>
                      </p>
                    </div>

                    {/* Quantity badge */}
                    <div className={`flex h-12 min-w-[80px] flex-col items-center justify-center rounded-xl ${cfg.bg} ${cfg.text}`}>
                      <p className="text-[16px] font-bold leading-none tabular-nums">
                        {isPositive ? '+' : ''}{m.quantity}
                      </p>
                      <p className="mt-0.5 text-[9px] font-medium opacity-70">unit</p>
                    </div>
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
    </DashboardLayout>
  )
}
