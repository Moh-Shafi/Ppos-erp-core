import { useState, useEffect, useCallback, useMemo } from 'react'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { inventoryReportService } from '@/services/inventoryReport'
import { inventoryService } from '@/services/inventory'
import { useModuleConfigStore } from '@/stores/module-config'
import type { ValuationResult } from '@/types'

/* ---------- Icons ---------- */

const IC = {
  refresh: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
  ),
  store: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M3 9l1-5h16l1 5M4 9v11h16V9M9 20v-6h6v6"/></svg>
  ),
  calculator: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="8" y2="10"/><line x1="12" y1="10" x2="12" y2="10"/><line x1="16" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="8" y2="14"/><line x1="12" y1="14" x2="12" y2="14"/><line x1="16" y1="14" x2="16" y2="14"/><line x1="8" y1="18" x2="8" y2="18"/><line x1="12" y1="18" x2="12" y2="18"/><line x1="16" y1="18" x2="16" y2="18"/></svg>
  ),
  box: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
  ),
  dollar: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
  ),
  coins: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="M16.71 13.88l.7.71-2.82 2.82"/></svg>
  ),
  trendUp: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
  ),
  empty: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" className="h-12 w-12"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
  ),
  download: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
  ),
}

const METHOD_CONFIG: Record<string, { label: string; description: string }> = {
  fifo: { label: 'FIFO', description: 'First In, First Out' },
  lifo: { label: 'LIFO', description: 'Last In, First Out' },
  average: { label: 'Average Cost', description: 'Rata-rata Harga Beli' },
}

function Skeleton({ className }: { className: string }) {
  return <div className={`animate-pulse rounded-xl bg-[#f0efec] ${className}`} />
}

/* ---------- Component ---------- */

export function ValuationReportPage() {
  const moduleConfig = useModuleConfigStore()
  const stores = moduleConfig.stores

  const [method, setMethod] = useState<'fifo' | 'lifo' | 'average'>('average')
  const [storeId, setStoreId] = useState('')
  const [result, setResult] = useState<ValuationResult | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    inventoryService.getSettings().then((s) => {
      if (s.stock_valuation_method) setMethod(s.stock_valuation_method)
    }).catch(() => {})
  }, [])

  const fetchValuation = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params: Record<string, unknown> = { method }
      if (storeId) params.store_id = parseInt(storeId, 10)
      const data = await inventoryReportService.valuation(params)
      setResult(data)
    } catch {
      setError('Failed to load valuation report')
    } finally {
      setLoading(false)
    }
  }, [method, storeId])

  useEffect(() => { fetchValuation() }, [method, storeId])

  const formatRupiah = (value: number) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value)
  }

  const formatNumber = (value: number) => {
    return new Intl.NumberFormat('id-ID').format(value)
  }

  const stats = useMemo(() => {
    if (!result) return { totalProducts: 0, totalQty: 0, grandTotal: 0, avgCost: 0 }
    const totalProducts = result.data.length
    const totalQty = result.data.reduce((sum, item) => sum + item.quantity, 0)
    const grandTotal = result.grand_total
    const avgCost = totalQty > 0 ? grandTotal / totalQty : 0
    return { totalProducts, totalQty, grandTotal, avgCost }
  }, [result])

  const sortedData = useMemo(() => {
    if (!result) return []
    return [...result.data].sort((a, b) => b.total_value - a.total_value)
  }, [result])

  const handleExport = () => {
    if (!result) return
    const headers = ['Product', 'SKU', 'Quantity', 'Unit Cost', 'Total Value']
    const rows = sortedData.map((item) => [
      item.product.name,
      item.product.sku ?? '-',
      item.quantity,
      item.unit_cost,
      item.total_value,
    ])
    const csv = [headers, ...rows].map((row) => row.map((cell) => `"${cell}"`).join(',')).join('\n')
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `valuation-${method}-${new Date().toISOString().split('T')[0]}.csv`
    link.click()
    URL.revokeObjectURL(url)
  }

  return (
    <DashboardLayout>
      <div className="space-y-5">
        {/* ===== Header ===== */}
        <div className="animate-fade-up flex items-center justify-between">
          <div>
            <h1 className="text-[22px] font-bold tracking-tight text-[#1c1917]">Valuasi Stok</h1>
            <p className="mt-1 text-[13px] text-[#78716c]">Laporan nilai inventory (FIFO / LIFO / Average)</p>
          </div>
          <div className="flex gap-2">
            <button
              onClick={handleExport}
              disabled={loading || !result || result.data.length === 0}
              className="flex h-10 cursor-pointer items-center gap-2 rounded-xl border border-[#e7e5e4] bg-white px-4 text-[13px] font-semibold text-[#78716c] transition-all hover:border-[#16a34a]/30 hover:bg-[#16a34a]/5 hover:text-[#16a34a] disabled:cursor-not-allowed disabled:opacity-40"
            >
              {IC.download} Export CSV
            </button>
            <button
              onClick={fetchValuation}
              disabled={loading}
              className="flex h-10 cursor-pointer items-center gap-2 rounded-xl border border-[#e7e5e4] bg-white px-4 text-[13px] font-semibold text-[#78716c] transition-all hover:border-[#f54927]/30 hover:bg-[#fef8f6] hover:text-[#f54927] disabled:cursor-not-allowed disabled:opacity-40"
            >
              {IC.refresh} Refresh
            </button>
          </div>
        </div>

        {/* ===== Method + Store Filter ===== */}
        <div className="animate-fade-up flex flex-wrap items-center gap-2" style={{ animationDelay: '0.05s' }}>
          {/* Method selector */}
          <div className="flex rounded-xl border border-[#e7e5e4] bg-white p-1">
            {(Object.keys(METHOD_CONFIG) as Array<'fifo' | 'lifo' | 'average'>).map((m) => (
              <button
                key={m}
                onClick={() => {
                  setMethod(m)
                  inventoryService.updateSettings({ stock_valuation_method: m }).catch(() => {})
                }}
                className={`flex h-8 cursor-pointer items-center gap-1.5 rounded-lg px-3 text-[12px] font-semibold transition-all ${
                  method === m
                    ? 'bg-gradient-to-r from-[#f54927] to-[#ff6b4a] text-white shadow-sm'
                    : 'text-[#78716c] hover:bg-[#f5f5f4]'
                }`}
              >
                {METHOD_CONFIG[m].label}
              </button>
            ))}
          </div>

          {/* Store filter */}
          <div className="relative">
            <div className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-[#a8a29e]">{IC.store}</div>
            <select
              value={storeId}
              onChange={(e) => setStoreId(e.target.value)}
              className="h-10 cursor-pointer appearance-none rounded-xl border border-[#e7e5e4] bg-white pr-8 pl-9 text-[13px] font-medium text-[#1c1917] outline-none transition-all focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            >
              <option value="">Semua Toko</option>
              {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
            </select>
          </div>

          {/* Method description */}
          <div className="flex items-center gap-2 rounded-xl bg-[#0a84ff]/5 px-3 py-2 text-[11px] font-medium text-[#0a84ff]">
            {IC.calculator}
            <span>{METHOD_CONFIG[method].description}</span>
          </div>
        </div>

        {/* ===== Summary Cards ===== */}
        <div className="animate-fade-up grid grid-cols-2 gap-4 lg:grid-cols-4" style={{ animationDelay: '0.1s' }}>
          {loading && !result ? (
            [...Array(4)].map((_, i) => <Skeleton key={i} className="h-[100px]" />)
          ) : (
            <>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Total Produk</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#1c1917] tabular-nums">{stats.totalProducts}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#0a84ff]/10 text-[#0a84ff]">{IC.box}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Total Unit</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#1c1917] tabular-nums">{formatNumber(stats.totalQty)}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#ca8a04]/10 text-[#ca8a04]">{IC.coins}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Rata-rata Harga</p>
                    <p className="mt-1 text-[18px] font-bold tracking-tight text-[#78716c] tabular-nums">{formatRupiah(stats.avgCost)}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#78716c]/10 text-[#78716c]">{IC.dollar}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#f54927]/20 bg-gradient-to-br from-[#f54927]/5 to-transparent p-4 transition-all hover:shadow-[0_4px_20px_rgba(245,73,39,0.08)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Total Nilai</p>
                    <p className="mt-1 text-[18px] font-bold tracking-tight text-[#f54927] tabular-nums">{formatRupiah(stats.grandTotal)}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#f54927]/10 text-[#f54927]">{IC.trendUp}</div>
                </div>
              </div>
            </>
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
                    <Skeleton className="h-4 w-40" />
                    <Skeleton className="h-3 w-24" />
                  </div>
                  <Skeleton className="h-8 w-24" />
                </div>
              </div>
            ))}
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center rounded-2xl border border-[#dc2626]/20 bg-[#fef2f2] py-16">
            <p className="text-[15px] font-semibold text-[#dc2626]">{error}</p>
            <button onClick={fetchValuation} className="mt-3 flex h-9 cursor-pointer items-center gap-2 rounded-xl border border-[#e7e5e4] bg-white px-4 text-[13px] font-medium text-[#78716c] transition-all hover:bg-[#f5f5f4]">
              {IC.refresh} Coba lagi
            </button>
          </div>
        ) : !result || result.data.length === 0 ? (
          <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-[#e7e5e4] bg-white/50 py-20">
            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-[#f5f5f4] text-[#d6d3d1]">{IC.empty}</div>
            <p className="text-[15px] font-semibold text-[#78716c]">Belum ada stok untuk dinilai</p>
            <p className="mt-1 text-[12px] text-[#a8a29e]">Belum ada pergerakan inventory ditemukan</p>
          </div>
        ) : (
          /* ===== Valuation Cards ===== */
          <div className="animate-fade-up space-y-3" style={{ animationDelay: '0.15s' }}>
            {sortedData.map((item, idx) => {
              const pct = stats.grandTotal > 0 ? (item.total_value / stats.grandTotal) * 100 : 0
              const isTop = idx < 3
              return (
                <div
                  key={item.product_id}
                  className="group flex items-center gap-4 rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all duration-200 hover:border-[#f54927]/20 hover:shadow-[0_4px_16px_rgba(0,0,0,0.04)]"
                >
                  {/* Rank badge */}
                  <div className={`flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl text-[12px] font-bold tabular-nums ${
                    isTop ? 'bg-gradient-to-br from-[#f54927] to-[#ff6b4a] text-white' : 'bg-[#f5f5f4] text-[#a8a29e]'
                  }`}>
                    {idx + 1}
                  </div>

                  {/* Product info */}
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-[14px] font-bold text-[#1c1917]">{item.product.name}</p>
                    <div className="mt-0.5 flex items-center gap-2 text-[11px] text-[#a8a29e]">
                      <span className="font-mono font-semibold">SKU: {item.product.sku ?? '-'}</span>
                      <span>·</span>
                      <span><span className="font-semibold text-[#78716c] tabular-nums">{formatNumber(item.quantity)}</span> unit</span>
                      <span>·</span>
                      <span>@ {formatRupiah(item.unit_cost)}</span>
                    </div>
                    {/* Value bar */}
                    <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-[#f5f5f4]">
                      <div
                        className="h-full rounded-full bg-gradient-to-r from-[#f54927] to-[#ff6b4a] transition-all duration-500"
                        style={{ width: `${pct}%` }}
                      />
                    </div>
                  </div>

                  {/* Total value */}
                  <div className="flex flex-shrink-0 flex-col items-end">
                    <p className="text-[16px] font-bold tabular-nums text-[#1c1917]">{formatRupiah(item.total_value)}</p>
                    <p className="text-[10px] font-medium text-[#a8a29e] tabular-nums">{pct.toFixed(1)}% dari total</p>
                  </div>
                </div>
              )
            })}
          </div>
        )}
      </div>
    </DashboardLayout>
  )
}
