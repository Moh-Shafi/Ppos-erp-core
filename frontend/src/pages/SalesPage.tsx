import { useState, useEffect, useCallback, useMemo } from 'react'
import { saleService } from '@/services/sale'
import { storeService } from '@/services/store'
import { useModuleConfigStore } from '@/stores/module-config'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import type { Sale, Store, PaginatedResponse } from '@/types'
import { formatRupiah } from '@/lib/utils'
import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import { Pagination } from '@/components/ui/Pagination'
import { Receipt } from '@/components/pos/Receipt'
import { RefundModal } from '@/components/pos/RefundModal'

/* ---------- Icons ---------- */

const IC = {
  search: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
  ),
  receipt: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1z"/><path d="M8 7h8"/><path d="M8 11h8"/><path d="M8 15h5"/></svg>
  ),
  store: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><path d="M3 9l3-3h12l3 3"/><path d="M4 9v11a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V9"/><path d="M9 21V12h6v9"/></svg>
  ),
  user: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
  ),
  calendar: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
  ),
  cash: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><rect x="1" y="4" width="22" height="16" rx="2"/><circle cx="12" cy="12" r="3"/></svg>
  ),
  check: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3"><polyline points="20 6 9 17 4 12"/></svg>
  ),
  x: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
  ),
  rotate: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
  ),
  eye: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
  ),
  trendingUp: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
  ),
  shoppingBag: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
  ),
  wallet: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
  ),
  filter: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
  ),
  empty: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" className="h-12 w-12"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
  ),
  arrowRight: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
  ),
  printer: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
  ),
  ban: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
  ),
}

/* ---------- Status config ---------- */

const STATUS_CONFIG: Record<string, { label: string; bg: string; text: string; dot: string; icon: React.ReactNode }> = {
  completed: { label: 'Selesai', bg: 'bg-[#16a34a]/10', text: 'text-[#16a34a]', dot: 'bg-[#16a34a]', icon: IC.check },
  cancelled: { label: 'Dibatalkan', bg: 'bg-[#dc2626]/10', text: 'text-[#dc2626]', dot: 'bg-[#dc2626]', icon: IC.x },
  refunded: { label: 'Di-refund', bg: 'bg-[#ca8a04]/10', text: 'text-[#ca8a04]', dot: 'bg-[#ca8a04]', icon: IC.rotate },
}

const PAYMENT_CONFIG: Record<string, { label: string; bg: string; text: string }> = {
  paid: { label: 'Lunas', bg: 'bg-[#16a34a]/10', text: 'text-[#16a34a]' },
  partial: { label: 'Sebagian', bg: 'bg-[#ca8a04]/10', text: 'text-[#ca8a04]' },
  unpaid: { label: 'Belum Bayar', bg: 'bg-[#dc2626]/10', text: 'text-[#dc2626]' },
}

const PAYMENT_METHOD_LABELS: Record<string, string> = {
  cash: 'Tunai', qris: 'QRIS', card: 'Kartu', bank_transfer: 'Transfer',
}

function formatDate(s: string): string {
  return new Date(s).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function formatTime(s: string): string {
  return new Date(s).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
}

function formatDay(s: string): string {
  return new Date(s).toLocaleDateString('id-ID', { day: '2-digit', month: 'short' })
}

/* ---------- Skeleton ---------- */

function Skeleton({ className }: { className: string }) {
  return <div className={`animate-pulse rounded-xl bg-[#f0efec] ${className}`} />
}

/* ---------- Component ---------- */

export function SalesPage() {
  const moduleConfig = useModuleConfigStore()
  const [sales, setSales] = useState<PaginatedResponse<Sale> | null>(null)
  const [stores, setStores] = useState<Store[]>([])
  const [loading, setLoading] = useState(false)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [paymentFilter, setPaymentFilter] = useState('')
  const [storeFilter, setStoreFilter] = useState('')
  const [selectedSale, setSelectedSale] = useState<Sale | null>(null)
  const [detailLoading, setDetailLoading] = useState(false)
  const [cancelLoading, setCancelLoading] = useState(false)
  const [cancelError, setCancelError] = useState('')
  const [refundOpen, setRefundOpen] = useState(false)
  const [activeFilter, setActiveFilter] = useState<'all' | 'completed' | 'cancelled' | 'refunded'>('all')

  const canRefund = moduleConfig.hasFeature('pos.refund') && moduleConfig.hasPermission('pos.refund')

  useEffect(() => {
    storeService.list().then(setStores).catch(() => {})
  }, [])

  const fetchSales = useCallback(async () => {
    setLoading(true)
    try {
      const data = await saleService.list({
        page,
        per_page: 15,
        search: search || undefined,
        status: statusFilter || undefined,
        payment_status: paymentFilter || undefined,
        store_id: storeFilter ? Number(storeFilter) : undefined,
      })
      setSales(data)
    } catch { /* ignore */ } finally {
      setLoading(false)
    }
  }, [page, search, statusFilter, paymentFilter, storeFilter])

  useEffect(() => {
    const timer = setTimeout(fetchSales, 250)
    return () => clearTimeout(timer)
  }, [fetchSales])

  const showSale = async (id: number) => {
    setDetailLoading(true)
    try {
      const sale = await saleService.show(id)
      setSelectedSale(sale)
    } catch { /* ignore */ } finally {
      setDetailLoading(false)
    }
  }

  const handleCancel = async () => {
    if (!selectedSale) return
    setCancelLoading(true)
    setCancelError('')
    try {
      const updated = await saleService.cancel(selectedSale.id)
      setSelectedSale(updated)
      fetchSales()
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } } }
      setCancelError(error.response?.data?.message ?? 'Gagal membatalkan transaksi')
    } finally {
      setCancelLoading(false)
    }
  }

  /* ---------- Summary stats ---------- */
  const summary = useMemo(() => {
    if (!sales?.data) return { total: 0, count: 0, completed: 0, revenue: 0 }
    const items = sales.data
    const completed = items.filter((s) => s.status === 'completed')
    return {
      total: items.length,
      count: completed.length,
      completed: completed.length,
      revenue: completed.reduce((sum, s) => sum + Number(s.total), 0),
    }
  }, [sales])

  const filterTabs = [
    { key: 'all' as const, label: 'Semua', count: sales?.total ?? 0 },
    { key: 'completed' as const, label: 'Selesai', count: sales?.data?.filter((s) => s.status === 'completed').length ?? 0 },
    { key: 'cancelled' as const, label: 'Dibatalkan', count: sales?.data?.filter((s) => s.status === 'cancelled').length ?? 0 },
    { key: 'refunded' as const, label: 'Di-refund', count: sales?.data?.filter((s) => s.status === 'refunded').length ?? 0 },
  ]

  return (
    <DashboardLayout>
      <div className="space-y-5">
        {/* ===== Header ===== */}
        <div className="animate-fade-up">
          <h1 className="text-[22px] font-bold tracking-tight text-[#1c1917]">Riwayat Transaksi</h1>
          <p className="mt-1 text-[13px] text-[#78716c]">Pantau semua transaksi penjualan yang terjadi</p>
        </div>

        {/* ===== Summary Cards ===== */}
        <div className="animate-fade-up grid grid-cols-1 gap-4 sm:grid-cols-3" style={{ animationDelay: '0.05s' }}>
          {loading && !sales ? (
            <>
              <Skeleton className="h-[100px]" />
              <Skeleton className="h-[100px]" />
              <Skeleton className="h-[100px]" />
            </>
          ) : (
            <>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[12px] font-medium text-[#a8a29e]">Total Transaksi</p>
                    <p className="mt-1.5 text-[24px] font-bold tracking-tight text-[#1c1917] tabular-nums">{sales?.total ?? 0}</p>
                  </div>
                  <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#0a84ff]/10 text-[#0a84ff]">{IC.shoppingBag}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[12px] font-medium text-[#a8a29e]">Transaksi Selesai</p>
                    <p className="mt-1.5 text-[24px] font-bold tracking-tight text-[#16a34a] tabular-nums">{summary.completed}</p>
                  </div>
                  <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#16a34a]/10 text-[#16a34a]">{IC.check}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[12px] font-medium text-[#a8a29e]">Pendapatan (halaman ini)</p>
                    <p className="mt-1.5 text-[24px] font-bold tracking-tight text-[#f54927] tabular-nums">{formatRupiah(summary.revenue)}</p>
                  </div>
                  <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#f54927]/10 text-[#f54927]">{IC.wallet}</div>
                </div>
              </div>
            </>
          )}
        </div>

        {/* ===== Filter Tabs ===== */}
        <div className="animate-fade-up flex items-center gap-1 rounded-xl border border-[#e7e5e4] bg-white p-1" style={{ animationDelay: '0.1s' }}>
          {filterTabs.map((tab) => (
            <button
              key={tab.key}
              onClick={() => {
                setActiveFilter(tab.key)
                setStatusFilter(tab.key === 'all' ? '' : tab.key)
                setPage(1)
              }}
              className={`flex cursor-pointer items-center gap-2 rounded-lg px-3.5 py-2 text-[12px] font-semibold transition-all ${
                activeFilter === tab.key ? 'bg-[#f54927] text-white shadow-sm' : 'text-[#78716c] hover:bg-[#f5f5f4]'
              }`}
            >
              {tab.label}
              <span className={`rounded-full px-1.5 py-0.5 text-[10px] font-bold tabular-nums ${activeFilter === tab.key ? 'bg-white/20' : 'bg-[#f5f5f4] text-[#a8a29e]'}`}>
                {tab.count}
              </span>
            </button>
          ))}
        </div>

        {/* ===== Search + Filters ===== */}
        <div className="animate-fade-up flex flex-wrap items-center gap-2" style={{ animationDelay: '0.12s' }}>
          <div className="relative min-w-[240px] flex-1">
            <div className="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-[#a8a29e]">{IC.search}</div>
            <input
              type="text"
              placeholder="Cari nomor transaksi..."
              value={search}
              onChange={(e) => { setSearch(e.target.value); setPage(1) }}
              className="h-10 w-full rounded-xl border border-[#e7e5e4] bg-white pr-4 pl-10 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            />
          </div>
          <select
            value={paymentFilter}
            onChange={(e) => { setPaymentFilter(e.target.value); setPage(1) }}
            className="h-10 cursor-pointer rounded-xl border border-[#e7e5e4] bg-white px-3 text-[12px] font-medium text-[#1c1917] outline-none transition-all focus:border-[#f54927]"
          >
            <option value="">Semua Pembayaran</option>
            <option value="paid">Lunas</option>
            <option value="partial">Sebagian</option>
            <option value="unpaid">Belum Bayar</option>
          </select>
          <select
            value={storeFilter}
            onChange={(e) => { setStoreFilter(e.target.value); setPage(1) }}
            className="h-10 cursor-pointer rounded-xl border border-[#e7e5e4] bg-white px-3 text-[12px] font-medium text-[#1c1917] outline-none transition-all focus:border-[#f54927]"
          >
            <option value="">Semua Toko</option>
            {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
          </select>
          {(search || statusFilter || paymentFilter || storeFilter) && (
            <button
              onClick={() => { setSearch(''); setStatusFilter(''); setPaymentFilter(''); setStoreFilter(''); setActiveFilter('all'); setPage(1) }}
              className="flex h-10 cursor-pointer items-center gap-1.5 rounded-xl border border-[#e7e5e4] bg-white px-3 text-[12px] font-medium text-[#78716c] transition-all hover:border-[#dc2626]/30 hover:text-[#dc2626]"
            >
              {IC.filter} Reset
            </button>
          )}
        </div>

        {/* ===== Sales Table ===== */}
        <div className="animate-fade-up overflow-hidden rounded-2xl border border-[#e7e5e4] bg-white" style={{ animationDelay: '0.15s' }}>
          {loading ? (
            <div className="space-y-0">
              {[...Array(6)].map((_, i) => (
                <div key={i} className="flex items-center gap-4 border-b border-[#f5f5f4] p-4 last:border-0">
                  <Skeleton className="h-8 w-8 rounded-lg" />
                  <Skeleton className="h-4 w-32" />
                  <Skeleton className="h-4 w-24" />
                  <Skeleton className="h-4 flex-1" />
                  <Skeleton className="h-6 w-20 rounded-full" />
                  <Skeleton className="h-6 w-16 rounded-full" />
                  <Skeleton className="h-8 w-20 rounded-lg" />
                </div>
              ))}
            </div>
          ) : sales?.data.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-20">
              <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-[#f5f5f4] text-[#d6d3d1]">{IC.empty}</div>
              <p className="text-[15px] font-semibold text-[#78716c]">Tidak ada transaksi</p>
              <p className="mt-1 text-[12px] text-[#a8a29e]">Coba ubah filter atau lakukan transaksi baru</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-[#f5f5f4] bg-[#fafaf9]">
                    <th className="px-4 py-3 text-left text-[11px] font-bold tracking-wide text-[#a8a29e] uppercase">Transaksi</th>
                    <th className="px-4 py-3 text-left text-[11px] font-bold tracking-wide text-[#a8a29e] uppercase">Waktu</th>
                    <th className="hidden px-4 py-3 text-left text-[11px] font-bold tracking-wide text-[#a8a29e] uppercase md:table-cell">Toko</th>
                    <th className="hidden px-4 py-3 text-left text-[11px] font-bold tracking-wide text-[#a8a29e] uppercase lg:table-cell">Kasir</th>
                    <th className="px-4 py-3 text-right text-[11px] font-bold tracking-wide text-[#a8a29e] uppercase">Total</th>
                    <th className="px-4 py-3 text-center text-[11px] font-bold tracking-wide text-[#a8a29e] uppercase">Status</th>
                    <th className="hidden px-4 py-3 text-center text-[11px] font-bold tracking-wide text-[#a8a29e] uppercase sm:table-cell">Bayar</th>
                    <th className="px-4 py-3 text-center text-[11px] font-bold tracking-wide text-[#a8a29e] uppercase">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  {sales?.data.map((sale, idx) => {
                    const status = STATUS_CONFIG[sale.status] ?? STATUS_CONFIG.completed
                    const payment = PAYMENT_CONFIG[sale.payment_status] ?? PAYMENT_CONFIG.unpaid
                    return (
                      <tr
                        key={sale.id}
                        onClick={() => showSale(sale.id)}
                        className={`group cursor-pointer border-b border-[#f5f5f4] transition-all hover:bg-[#fafaf9] ${idx % 2 === 1 ? 'bg-[#fcfcfb]' : ''}`}
                      >
                        <td className="px-4 py-3.5">
                          <div className="flex items-center gap-3">
                            <div className={`flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl ${status.bg} ${status.text}`}>
                              {status.icon}
                            </div>
                            <div>
                              <p className="font-mono text-[12px] font-semibold text-[#1c1917]">{sale.sale_number}</p>
                              <p className="text-[10px] text-[#a8a29e]">{sale.customer?.name ?? 'Walk-in'}</p>
                            </div>
                          </div>
                        </td>
                        <td className="px-4 py-3.5">
                          <p className="text-[12px] font-medium text-[#1c1917]">{formatDay(sale.sale_date)}</p>
                          <p className="text-[10px] text-[#a8a29e]">{formatTime(sale.sale_date)}</p>
                        </td>
                        <td className="hidden px-4 py-3.5 md:table-cell">
                          <p className="text-[12px] text-[#78716c]">{sale.store?.name ?? '-'}</p>
                        </td>
                        <td className="hidden px-4 py-3.5 lg:table-cell">
                          <p className="text-[12px] text-[#78716c]">{sale.cashier?.name ?? '-'}</p>
                        </td>
                        <td className="px-4 py-3.5 text-right">
                          <p className="text-[14px] font-bold text-[#1c1917] tabular-nums">{formatRupiah(sale.total)}</p>
                          {sale.discount && Number(sale.discount) > 0 && (
                            <p className="text-[10px] text-[#ca8a04]">-{formatRupiah(sale.discount)} diskon</p>
                          )}
                        </td>
                        <td className="px-4 py-3.5 text-center">
                          <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-bold ${status.bg} ${status.text}`}>
                            <span className={`h-1.5 w-1.5 rounded-full ${status.dot}`} />
                            {status.label}
                          </span>
                        </td>
                        <td className="hidden px-4 py-3.5 text-center sm:table-cell">
                          <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-bold ${payment.bg} ${payment.text}`}>
                            {payment.label}
                          </span>
                        </td>
                        <td className="px-4 py-3.5 text-center">
                          <button
                            onClick={(e) => { e.stopPropagation(); showSale(sale.id) }}
                            className="flex cursor-pointer items-center gap-1 rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-[#f54927] transition-all hover:bg-[#f54927]/10"
                          >
                            {IC.eye} Detail
                          </button>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* ===== Pagination ===== */}
        {sales && sales.last_page > 1 && (
          <div className="animate-fade-up flex justify-center" style={{ animationDelay: '0.2s' }}>
            <Pagination currentPage={sales.current_page} lastPage={sales.last_page} onPageChange={setPage} />
          </div>
        )}

        {/* ===== Sale Detail Modal ===== */}
        <Modal
          open={!!selectedSale}
          onClose={() => { setSelectedSale(null); setCancelError('') }}
          title="Detail Transaksi"
          size="lg"
          footer={
            selectedSale && selectedSale.status === 'completed' ? (
              <div className="flex w-full items-center justify-between">
                <div className="flex gap-2">
                  {canRefund && selectedSale.refund_status !== 'full' && (
                    <Button variant="destructive" onClick={() => setRefundOpen(true)}>
                      Refund
                    </Button>
                  )}
                  <Button variant="outline" onClick={handleCancel} disabled={cancelLoading}>
                    {cancelLoading ? 'Memproses...' : 'Batalkan'}
                  </Button>
                </div>
                <Button variant="outline" onClick={() => { setSelectedSale(null); setCancelError('') }}>
                  Tutup
                </Button>
              </div>
            ) : (
              <Button variant="outline" onClick={() => { setSelectedSale(null); setCancelError('') }}>
                Tutup
              </Button>
            )
          }
        >
          {detailLoading ? (
            <div className="flex flex-col items-center py-12">
              <div className="h-8 w-8 animate-spin rounded-full border-2 border-[#f54927] border-t-transparent" />
              <p className="mt-3 text-[13px] text-[#a8a29e]">Memuat detail...</p>
            </div>
          ) : selectedSale ? (
            <div className="space-y-4">
              {cancelError && (
                <div className="rounded-xl border border-[#dc2626]/20 bg-[#fef2f2] p-3">
                  <p className="text-[13px] font-medium text-[#dc2626]">{cancelError}</p>
                </div>
              )}

              {/* Sale summary header */}
              <div className="flex items-center justify-between rounded-xl bg-gradient-to-r from-[#f54927]/5 to-transparent p-4">
                <div className="flex items-center gap-3">
                  <div className={`flex h-12 w-12 items-center justify-center rounded-xl ${STATUS_CONFIG[selectedSale.status]?.bg ?? 'bg-[#f5f5f4]'} ${STATUS_CONFIG[selectedSale.status]?.text ?? 'text-[#78716c]'}`}>
                    {STATUS_CONFIG[selectedSale.status]?.icon ?? IC.receipt}
                  </div>
                  <div>
                    <p className="font-mono text-[14px] font-bold text-[#1c1917]">{selectedSale.sale_number}</p>
                    <p className="text-[11px] text-[#a8a29e]">{formatDate(selectedSale.sale_date)}</p>
                  </div>
                </div>
                <div className="text-right">
                  <p className="text-[11px] text-[#a8a29e]">Total</p>
                  <p className="text-[20px] font-bold text-[#f54927] tabular-nums">{formatRupiah(selectedSale.total)}</p>
                </div>
              </div>

              {/* Meta info grid */}
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div className="rounded-xl border border-[#e7e5e4] bg-white p-3">
                  <p className="flex items-center gap-1 text-[10px] font-medium text-[#a8a29e]">{IC.store} Toko</p>
                  <p className="mt-1 truncate text-[12px] font-semibold text-[#1c1917]">{selectedSale.store?.name ?? '-'}</p>
                </div>
                <div className="rounded-xl border border-[#e7e5e4] bg-white p-3">
                  <p className="flex items-center gap-1 text-[10px] font-medium text-[#a8a29e]">{IC.user} Kasir</p>
                  <p className="mt-1 truncate text-[12px] font-semibold text-[#1c1917]">{selectedSale.cashier?.name ?? '-'}</p>
                </div>
                <div className="rounded-xl border border-[#e7e5e4] bg-white p-3">
                  <p className="flex items-center gap-1 text-[10px] font-medium text-[#a8a29e]">{IC.user} Pelanggan</p>
                  <p className="mt-1 truncate text-[12px] font-semibold text-[#1c1917]">{selectedSale.customer?.name ?? 'Walk-in'}</p>
                </div>
                <div className="rounded-xl border border-[#e7e5e4] bg-white p-3">
                  <p className="flex items-center gap-1 text-[10px] font-medium text-[#a8a29e]">{IC.cash} Pembayaran</p>
                  <p className="mt-1 truncate text-[12px] font-semibold text-[#1c1917]">
                    {selectedSale.payments?.map((p) => PAYMENT_METHOD_LABELS[p.payment_method] ?? p.payment_method).join(', ') ?? '-'}
                  </p>
                </div>
              </div>

              {/* Receipt */}
              <div className="rounded-xl border border-[#e7e5e4] bg-[#fafaf9] p-4">
                <Receipt sale={selectedSale} onClose={() => {}} />
              </div>
            </div>
          ) : null}
        </Modal>

        {/* ===== Refund Modal ===== */}
        {selectedSale && (
          <RefundModal
            sale={selectedSale}
            open={refundOpen}
            onClose={() => setRefundOpen(false)}
            onRefunded={async () => {
              const updated = await saleService.show(selectedSale.id)
              setSelectedSale(updated)
              fetchSales()
            }}
          />
        )}
      </div>
    </DashboardLayout>
  )
}
