import { useState, useEffect, useCallback, useMemo } from 'react'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { Modal } from '@/components/ui/Modal'
import { transferRequestService } from '@/services/transferRequest'
import { productService } from '@/services/product'
import { useModuleConfigStore } from '@/stores/module-config'
import { useAuthStore } from '@/stores/auth'
import type { TransferRequest, Product, PaginatedResponse } from '@/types'

/* ---------- Icons ---------- */

const IC = {
  plus: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
  ),
  x: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
  ),
  eye: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
  ),
  arrowRight: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
  ),
  store: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M3 9l1-5h16l1 5M4 9v11h16V9M9 20v-6h6v6"/></svg>
  ),
  exchange: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
  ),
  check: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polyline points="20 6 9 17 4 12"/></svg>
  ),
  xCircle: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
  ),
  truck: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
  ),
  refresh: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
  ),
  filter: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
  ),
  empty: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" className="h-12 w-12"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
  ),
  box: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
  ),
  user: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
  ),
  clock: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
  ),
  pending: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  ),
  fileText: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
  ),
}

/* ---------- Status config ---------- */

const STATUS_CONFIG: Record<string, { label: string; bg: string; text: string; dot: string; icon: React.JSX.Element }> = {
  draft: { label: 'Draft', bg: 'bg-[#a8a29e]/10', text: 'text-[#a8a29e]', dot: 'bg-[#a8a29e]', icon: IC.fileText },
  pending: { label: 'Menunggu', bg: 'bg-[#ca8a04]/10', text: 'text-[#ca8a04]', dot: 'bg-[#ca8a04]', icon: IC.pending },
  approved: { label: 'Disetujui', bg: 'bg-[#16a34a]/10', text: 'text-[#16a34a]', dot: 'bg-[#16a34a]', icon: IC.check },
  rejected: { label: 'Ditolak', bg: 'bg-[#dc2626]/10', text: 'text-[#dc2626]', dot: 'bg-[#dc2626]', icon: IC.xCircle },
  in_transit: { label: 'Dalam Pengiriman', bg: 'bg-[#0a84ff]/10', text: 'text-[#0a84ff]', dot: 'bg-[#0a84ff]', icon: IC.truck },
  completed: { label: 'Selesai', bg: 'bg-[#16a34a]/10', text: 'text-[#16a34a]', dot: 'bg-[#16a34a]', icon: IC.check },
  cancelled: { label: 'Dibatalkan', bg: 'bg-[#dc2626]/10', text: 'text-[#dc2626]', dot: 'bg-[#dc2626]', icon: IC.xCircle },
}

function getStatus(status: string) {
  return STATUS_CONFIG[status] ?? { label: status, bg: 'bg-[#a8a29e]/10', text: 'text-[#a8a29e]', dot: 'bg-[#a8a29e]', icon: IC.fileText }
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

function Skeleton({ className }: { className: string }) {
  return <div className={`animate-pulse rounded-xl bg-[#f0efec] ${className}`} />
}

/* ---------- Component ---------- */

export function TransferRequestsPage() {
  const { user } = useAuthStore()
  const canManage = user?.role?.slug === 'owner' || user?.role?.slug === 'manager'
  const moduleConfig = useModuleConfigStore()
  const stores = moduleConfig.stores

  const [requests, setRequests] = useState<TransferRequest[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [statusFilter, setStatusFilter] = useState('')

  const [detailReq, setDetailReq] = useState<TransferRequest | null>(null)
  const [detailLoading, setDetailLoading] = useState(false)
  const [actionLoading, setActionLoading] = useState(false)
  const [rejectOpen, setRejectOpen] = useState(false)
  const [rejectReason, setRejectReason] = useState('')

  const [createOpen, setCreateOpen] = useState(false)
  const [createForm, setCreateForm] = useState({ from_store_id: '', to_store_id: '', note: '' })
  const [createItems, setCreateItems] = useState<{ product_id: string; quantity: string }[]>([{ product_id: '', quantity: '' }])
  const [createLoading, setCreateLoading] = useState(false)
  const [createError, setCreateError] = useState('')
  const [products, setProducts] = useState<Product[]>([])

  const fetchRequests = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params: Record<string, unknown> = { page, per_page: 20 }
      if (statusFilter) params.status = statusFilter
      const res: PaginatedResponse<TransferRequest> = await transferRequestService.list(params)
      setRequests(res.data)
      setLastPage(res.last_page)
      setTotal(res.total)
    } catch {
      setError('Failed to load transfer requests')
    } finally {
      setLoading(false)
    }
  }, [page, statusFilter])

  useEffect(() => { fetchRequests() }, [page, statusFilter])

  const handleViewDetail = async (id: number) => {
    setDetailLoading(true)
    try {
      const req = await transferRequestService.get(id)
      setDetailReq(req)
    } catch {
      setError('Failed to load transfer request')
    } finally {
      setDetailLoading(false)
    }
  }

  const handleAction = async (action: string, id: number) => {
    setActionLoading(true)
    try {
      switch (action) {
        case 'submit': await transferRequestService.submit(id); break
        case 'approve': await transferRequestService.approve(id); break
        case 'transit': await transferRequestService.transit(id); break
        case 'complete': await transferRequestService.complete(id); break
      }
      const updated = await transferRequestService.get(id)
      setDetailReq(updated)
      fetchRequests()
    } catch (err: any) {
      setError(err.response?.data?.message ?? `Failed to ${action} request`)
    } finally {
      setActionLoading(false)
    }
  }

  const handleReject = async () => {
    if (!detailReq) return
    setActionLoading(true)
    try {
      await transferRequestService.reject(detailReq.id, rejectReason || undefined)
      const updated = await transferRequestService.get(detailReq.id)
      setDetailReq(updated)
      setRejectOpen(false)
      setRejectReason('')
      fetchRequests()
    } catch (err: any) {
      setError(err.response?.data?.message ?? 'Failed to reject')
    } finally {
      setActionLoading(false)
    }
  }

  const handleCancel = async (id: number) => {
    setActionLoading(true)
    try {
      await transferRequestService.cancel(id)
      const updated = await transferRequestService.get(id)
      setDetailReq(updated)
      fetchRequests()
    } catch (err: any) {
      setError(err.response?.data?.message ?? 'Failed to cancel')
    } finally {
      setActionLoading(false)
    }
  }

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault()
    setCreateLoading(true)
    setCreateError('')
    try {
      const items = createItems
        .filter((i) => i.product_id && i.quantity)
        .map((i) => ({ product_id: parseInt(i.product_id, 10), quantity: parseInt(i.quantity, 10) }))
      if (items.length === 0) {
        setCreateError('Minimal satu item diperlukan')
        setCreateLoading(false)
        return
      }
      await transferRequestService.create({
        from_store_id: parseInt(createForm.from_store_id, 10) || undefined,
        to_store_id: parseInt(createForm.to_store_id, 10) || undefined,
        items,
        note: createForm.note || undefined,
      })
      setCreateOpen(false)
      setCreateForm({ from_store_id: '', to_store_id: '', note: '' })
      setCreateItems([{ product_id: '', quantity: '' }])
      fetchRequests()
    } catch (err: any) {
      setCreateError(err.response?.data?.message ?? 'Failed to create request')
    } finally {
      setCreateLoading(false)
    }
  }

  const openCreate = () => {
    setCreateForm({ from_store_id: '', to_store_id: '', note: '' })
    setCreateItems([{ product_id: '', quantity: '' }])
    setCreateError('')
    setCreateOpen(true)
    productService.list({ per_page: 100 }).then((r) => setProducts(r.data)).catch(() => {})
  }

  const summary = useMemo(() => {
    const byStatus: Record<string, number> = {}
    requests.forEach((r) => { byStatus[r.status] = (byStatus[r.status] ?? 0) + 1 })
    return {
      pending: byStatus['pending'] ?? 0,
      inTransit: byStatus['in_transit'] ?? 0,
      completed: byStatus['completed'] ?? 0,
      totalItems: requests.reduce((sum, r) => sum + (r.items?.length ?? 0), 0),
    }
  }, [requests])

  return (
    <DashboardLayout>
      <div className="space-y-5">
        {/* ===== Header ===== */}
        <div className="animate-fade-up flex items-center justify-between">
          <div>
            <h1 className="text-[22px] font-bold tracking-tight text-[#1c1917]">Permintaan Transfer</h1>
            <p className="mt-1 text-[13px] text-[#78716c]">Kelola permintaan transfer stok antar lokasi</p>
          </div>
          {canManage && (
            <button
              onClick={openCreate}
              className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-4 text-[13px] font-semibold text-white shadow-[0_4px_14px_rgba(245,73,39,0.3)] transition-all hover:shadow-[0_6px_20px_rgba(245,73,39,0.4)] active:scale-[0.97]"
            >
              {IC.plus} Buat Permintaan
            </button>
          )}
        </div>

        {/* ===== Summary Cards ===== */}
        <div className="animate-fade-up grid grid-cols-2 gap-4 lg:grid-cols-4" style={{ animationDelay: '0.05s' }}>
          {loading && requests.length === 0 ? (
            [...Array(4)].map((_, i) => <Skeleton key={i} className="h-[90px]" />)
          ) : (
            <>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Total Permintaan</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#1c1917] tabular-nums">{total}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#0a84ff]/10 text-[#0a84ff]">{IC.exchange}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Menunggu</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#ca8a04] tabular-nums">{summary.pending}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#ca8a04]/10 text-[#ca8a04]">{IC.pending}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Dalam Pengiriman</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#0a84ff] tabular-nums">{summary.inTransit}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#0a84ff]/10 text-[#0a84ff]">{IC.truck}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Selesai</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#16a34a] tabular-nums">{summary.completed}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#16a34a]/10 text-[#16a34a]">{IC.check}</div>
                </div>
              </div>
            </>
          )}
        </div>

        {/* ===== Filter ===== */}
        <div className="animate-fade-up flex flex-wrap items-center gap-2" style={{ animationDelay: '0.1s' }}>
          <div className="relative">
            <div className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-[#a8a29e]">{IC.filter}</div>
            <select
              value={statusFilter}
              onChange={(e) => { setStatusFilter(e.target.value); setPage(1) }}
              className="h-10 cursor-pointer appearance-none rounded-xl border border-[#e7e5e4] bg-white pr-8 pl-9 text-[13px] font-medium text-[#1c1917] outline-none transition-all focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            >
              <option value="">Semua Status</option>
              {Object.entries(STATUS_CONFIG).map(([value, cfg]) => (
                <option key={value} value={value}>{cfg.label}</option>
              ))}
            </select>
          </div>
          {statusFilter && (
            <button
              onClick={() => { setStatusFilter(''); setPage(1) }}
              className="flex h-10 cursor-pointer items-center gap-1.5 rounded-xl border border-[#e7e5e4] bg-white px-3 text-[12px] font-medium text-[#78716c] transition-all hover:border-[#dc2626]/30 hover:text-[#dc2626]"
            >
              {IC.filter} Reset
            </button>
          )}
        </div>

        {/* ===== Content ===== */}
        {loading ? (
          <div className="space-y-3">
            {[...Array(5)].map((_, i) => (
              <div key={i} className="rounded-2xl border border-[#e7e5e4] bg-white p-4">
                <div className="flex items-center gap-3">
                  <Skeleton className="h-10 w-10 rounded-xl" />
                  <div className="flex-1 space-y-2">
                    <Skeleton className="h-4 w-32" />
                    <Skeleton className="h-3 w-48" />
                  </div>
                  <Skeleton className="h-8 w-24" />
                </div>
              </div>
            ))}
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center rounded-2xl border border-[#dc2626]/20 bg-[#fef2f2] py-16">
            <p className="text-[15px] font-semibold text-[#dc2626]">{error}</p>
            <button onClick={fetchRequests} className="mt-3 flex h-9 cursor-pointer items-center gap-2 rounded-xl border border-[#e7e5e4] bg-white px-4 text-[13px] font-medium text-[#78716c] transition-all hover:bg-[#f5f5f4]">
              {IC.refresh} Coba lagi
            </button>
          </div>
        ) : requests.length === 0 ? (
          <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-[#e7e5e4] bg-white/50 py-20">
            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-[#f5f5f4] text-[#d6d3d1]">{IC.empty}</div>
            <p className="text-[15px] font-semibold text-[#78716c]">Belum ada permintaan transfer</p>
            <p className="mt-1 text-[12px] text-[#a8a29e]">Buat permintaan transfer untuk memindahkan stok antar lokasi</p>
            {canManage && (
              <button onClick={openCreate} className="mt-4 flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-4 text-[13px] font-semibold text-white shadow-sm transition-all hover:shadow-md active:scale-[0.98]">
                {IC.plus} Buat Permintaan
              </button>
            )}
          </div>
        ) : (
          /* ===== Cards ===== */
          <div className="animate-fade-up space-y-3" style={{ animationDelay: '0.15s' }}>
            {requests.map((r) => {
              const status = getStatus(r.status)
              const fromName = r.fromStore?.name ?? r.fromWarehouse?.name ?? '—'
              const toName = r.toStore?.name ?? r.toWarehouse?.name ?? '—'
              const itemCount = r.items?.length ?? 0
              return (
                <div
                  key={r.id}
                  className="group flex items-center gap-4 rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all duration-200 hover:border-[#f54927]/20 hover:shadow-[0_4px_16px_rgba(0,0,0,0.04)]"
                >
                  {/* Status icon */}
                  <div className={`flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl ${status.bg} ${status.text}`}>
                    {status.icon}
                  </div>

                  {/* Content */}
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                      <p className="text-[14px] font-bold text-[#1c1917]">{r.request_number}</p>
                      <span className={`flex items-center gap-1 rounded-full ${status.bg} px-2 py-0.5 text-[9px] font-bold ${status.text}`}>
                        <span className={`h-1.5 w-1.5 rounded-full ${status.dot}`} />
                        {status.label}
                      </span>
                    </div>
                    <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-[#a8a29e]">
                      <span className="flex items-center gap-1 font-medium text-[#78716c]">{fromName}</span>
                      <span className="text-[#f54927]">{IC.arrowRight}</span>
                      <span className="flex items-center gap-1 font-medium text-[#78716c]">{toName}</span>
                      <span>·</span>
                      <span className="flex items-center gap-1">{IC.box} {itemCount} item</span>
                      <span>·</span>
                      <span>{formatDate(r.created_at)}</span>
                    </div>
                  </div>

                  {/* View button */}
                  <button
                    onClick={() => handleViewDetail(r.id)}
                    className="flex h-9 cursor-pointer items-center gap-1.5 rounded-lg border border-[#e7e5e4] px-3 text-[12px] font-semibold text-[#78716c] transition-all hover:border-[#0a84ff]/30 hover:bg-[#0a84ff]/5 hover:text-[#0a84ff]"
                  >
                    {IC.eye} Detail
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

      {/* ===== Create Modal ===== */}
      <Modal
        open={createOpen}
        onClose={() => setCreateOpen(false)}
        title="Buat Permintaan Transfer"
        size="lg"
        footer={
          <div className="flex w-full items-center justify-between">
            <button
              onClick={() => setCreateOpen(false)}
              disabled={createLoading}
              className="flex h-10 cursor-pointer items-center gap-2 rounded-xl border border-[#e7e5e4] bg-white px-5 text-[13px] font-semibold text-[#78716c] transition-all hover:bg-[#f5f5f4] disabled:opacity-50"
            >
              Batal
            </button>
            <button
              onClick={handleCreate}
              disabled={createLoading}
              className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-5 text-[13px] font-semibold text-white shadow-[0_4px_14px_rgba(245,73,39,0.3)] transition-all hover:shadow-[0_6px_20px_rgba(245,73,39,0.4)] active:scale-[0.97] disabled:cursor-not-allowed disabled:opacity-50"
            >
              {createLoading ? (
                <>
                  <span className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                  Membuat...
                </>
              ) : (
                <>{IC.check} Buat</>
              )}
            </button>
          </div>
        }
      >
        <form onSubmit={handleCreate} className="space-y-5">
          {/* From → To visual */}
          <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-3">
            <div className={`rounded-xl border p-3 transition-all ${createForm.from_store_id ? 'border-[#f54927]/30 bg-[#f54927]/5' : 'border-[#e7e5e4] bg-[#fafaf9]'}`}>
              <p className="mb-1.5 text-[10px] font-bold tracking-wide text-[#a8a29e] uppercase">Dari Toko</p>
              <select
                value={createForm.from_store_id}
                onChange={(e) => setCreateForm({ ...createForm, from_store_id: e.target.value })}
                className="h-9 w-full cursor-pointer appearance-none rounded-lg border border-[#e7e5e4] bg-white px-3 text-[13px] font-semibold text-[#1c1917] outline-none transition-all focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
              >
                <option value="">Pilih toko...</option>
                {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
              </select>
            </div>
            <div className="text-[#f54927]">{IC.arrowRight}</div>
            <div className={`rounded-xl border p-3 transition-all ${createForm.to_store_id ? 'border-[#16a34a]/30 bg-[#16a34a]/5' : 'border-[#e7e5e4] bg-[#fafaf9]'}`}>
              <p className="mb-1.5 text-[10px] font-bold tracking-wide text-[#a8a29e] uppercase">Ke Toko</p>
              <select
                value={createForm.to_store_id}
                onChange={(e) => setCreateForm({ ...createForm, to_store_id: e.target.value })}
                className="h-9 w-full cursor-pointer appearance-none rounded-lg border border-[#e7e5e4] bg-white px-3 text-[13px] font-semibold text-[#1c1917] outline-none transition-all focus:border-[#16a34a] focus:ring-2 focus:ring-[#16a34a]/10"
              >
                <option value="">Pilih toko...</option>
                {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
              </select>
            </div>
          </div>

          {/* Items */}
          <div className="space-y-2">
            <label className="text-[12px] font-bold tracking-wide text-[#44403c] uppercase">Item Produk</label>
            {createItems.map((item, idx) => (
              <div key={idx} className="flex gap-2">
                <select
                  value={item.product_id}
                  onChange={(e) => {
                    const items = [...createItems]
                    items[idx] = { ...items[idx], product_id: e.target.value }
                    setCreateItems(items)
                  }}
                  className="h-10 flex-1 cursor-pointer appearance-none rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
                >
                  <option value="">Pilih produk...</option>
                  {products.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                </select>
                <input
                  type="number"
                  placeholder="Qty"
                  value={item.quantity}
                  onChange={(e) => {
                    const items = [...createItems]
                    items[idx] = { ...items[idx], quantity: e.target.value }
                    setCreateItems(items)
                  }}
                  className="h-10 w-24 rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] font-semibold text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
                />
                {createItems.length > 1 && (
                  <button
                    type="button"
                    onClick={() => setCreateItems(createItems.filter((_, i) => i !== idx))}
                    className="flex h-10 w-10 cursor-pointer items-center justify-center rounded-xl border border-[#e7e5e4] text-[#a8a29e] transition-all hover:border-[#dc2626]/30 hover:bg-[#dc2626]/5 hover:text-[#dc2626]"
                  >
                    {IC.x}
                  </button>
                )}
              </div>
            ))}
            <button
              type="button"
              onClick={() => setCreateItems([...createItems, { product_id: '', quantity: '' }])}
              className="flex h-9 cursor-pointer items-center gap-2 rounded-xl border border-dashed border-[#e7e5e4] px-4 text-[12px] font-semibold text-[#78716c] transition-all hover:border-[#f54927]/30 hover:bg-[#f54927]/5 hover:text-[#f54927]"
            >
              {IC.plus} Tambah Item
            </button>
          </div>

          {/* Note */}
          <div className="space-y-1.5">
            <label className="text-[12px] font-semibold text-[#44403c]">Catatan</label>
            <input
              value={createForm.note}
              onChange={(e) => setCreateForm({ ...createForm, note: e.target.value })}
              placeholder="Catatan opsional..."
              className="h-10 w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            />
          </div>

          {createError && (
            <div className="rounded-xl border border-[#dc2626]/20 bg-[#fef2f2] p-3">
              <p className="text-[13px] font-medium text-[#dc2626]">{createError}</p>
            </div>
          )}
        </form>
      </Modal>

      {/* ===== Detail Modal ===== */}
      <Modal
        open={detailReq !== null}
        onClose={() => setDetailReq(null)}
        title={`Detail: ${detailReq?.request_number ?? ''}`}
        size="lg"
      >
        {detailLoading ? (
          <div className="flex items-center justify-center py-12">
            <span className="h-8 w-8 animate-spin rounded-full border-2 border-[#f54927] border-t-transparent" />
          </div>
        ) : detailReq ? (
          <div className="space-y-5">
            {/* Status + route */}
            <div className="flex items-center gap-3 rounded-xl bg-gradient-to-r from-[#f54927]/5 to-transparent p-4">
              {(() => {
                const status = getStatus(detailReq.status)
                return (
                  <div className={`flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl ${status.bg} ${status.text}`}>
                    {status.icon}
                  </div>
                )
              })()}
              <div className="min-w-0 flex-1">
                <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-bold ${getStatus(detailReq.status).bg} ${getStatus(detailReq.status).text}`}>
                  <span className={`h-1.5 w-1.5 rounded-full ${getStatus(detailReq.status).dot}`} />
                  {getStatus(detailReq.status).label}
                </span>
                <p className="mt-1.5 flex items-center gap-2 text-[13px] font-semibold text-[#1c1917]">
                  {detailReq.fromStore?.name ?? detailReq.fromWarehouse?.name ?? '—'}
                  <span className="text-[#f54927]">{IC.arrowRight}</span>
                  {detailReq.toStore?.name ?? detailReq.toWarehouse?.name ?? '—'}
                </p>
              </div>
            </div>

            {/* Notes */}
            {detailReq.note && (
              <div className="rounded-xl border border-[#e7e5e4] bg-[#fafaf9] p-3">
                <p className="text-[10px] font-bold tracking-wide text-[#a8a29e] uppercase">Catatan</p>
                <p className="mt-1 text-[13px] text-[#44403c]">{detailReq.note}</p>
              </div>
            )}
            {detailReq.rejection_reason && (
              <div className="rounded-xl border border-[#dc2626]/20 bg-[#fef2f2] p-3">
                <p className="text-[10px] font-bold tracking-wide text-[#dc2626] uppercase">Alasan Penolakan</p>
                <p className="mt-1 text-[13px] text-[#dc2626]">{detailReq.rejection_reason}</p>
              </div>
            )}

            {/* Items */}
            {detailReq.items && detailReq.items.length > 0 && (
              <div className="space-y-2">
                <p className="text-[12px] font-bold tracking-wide text-[#44403c] uppercase">Daftar Item</p>
                <div className="max-h-[300px] space-y-2 overflow-y-auto pr-1">
                  {detailReq.items.map((item) => (
                    <div key={item.id} className="flex items-center gap-3 rounded-xl border border-[#e7e5e4] bg-white p-3 transition-all hover:border-[#f54927]/20">
                      <div className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-[#f54927]/10 text-[#f54927]">
                        {IC.box}
                      </div>
                      <div className="min-w-0 flex-1">
                        <p className="truncate text-[13px] font-semibold text-[#1c1917]">{item.product?.name ?? `Product #${item.product_id}`}</p>
                        <p className="text-[11px] text-[#a8a29e]">ID: {item.product_id}</p>
                      </div>
                      <div className="flex h-9 min-w-[60px] items-center justify-center rounded-lg bg-[#0a84ff]/10 px-3">
                        <span className="text-[14px] font-bold tabular-nums text-[#0a84ff]">{item.quantity}</span>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Action buttons */}
            {canManage && (
              <div className="flex flex-wrap gap-2 border-t border-[#f5f5f4] pt-4">
                {detailReq.status === 'draft' && (
                  <button onClick={() => handleAction('submit', detailReq.id)} disabled={actionLoading} className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-4 text-[13px] font-semibold text-white shadow-sm transition-all hover:shadow-md active:scale-[0.98] disabled:opacity-50">
                    {IC.check} Submit untuk Approval
                  </button>
                )}
                {detailReq.status === 'pending' && (
                  <>
                    <button onClick={() => handleAction('approve', detailReq.id)} disabled={actionLoading} className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#16a34a] to-[#22c55e] px-4 text-[13px] font-semibold text-white shadow-sm transition-all hover:shadow-md active:scale-[0.98] disabled:opacity-50">
                      {IC.check} Setujui
                    </button>
                    <button onClick={() => { setRejectReason(''); setRejectOpen(true) }} disabled={actionLoading} className="flex h-10 cursor-pointer items-center gap-2 rounded-xl border border-[#dc2626]/30 bg-[#dc2626]/5 px-4 text-[13px] font-semibold text-[#dc2626] transition-all hover:bg-[#dc2626]/10 active:scale-[0.98] disabled:opacity-50">
                      {IC.xCircle} Tolak
                    </button>
                  </>
                )}
                {detailReq.status === 'approved' && (
                  <button onClick={() => handleAction('transit', detailReq.id)} disabled={actionLoading} className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#0a84ff] to-[#3b82f6] px-4 text-[13px] font-semibold text-white shadow-sm transition-all hover:shadow-md active:scale-[0.98] disabled:opacity-50">
                    {IC.truck} Mulai Pengiriman
                  </button>
                )}
                {detailReq.status === 'in_transit' && (
                  <button onClick={() => handleAction('complete', detailReq.id)} disabled={actionLoading} className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#16a34a] to-[#22c55e] px-4 text-[13px] font-semibold text-white shadow-sm transition-all hover:shadow-md active:scale-[0.98] disabled:opacity-50">
                    {IC.check} Selesai
                  </button>
                )}
                {!['completed', 'rejected', 'cancelled'].includes(detailReq.status) && (
                  <button onClick={() => handleCancel(detailReq.id)} disabled={actionLoading} className="flex h-10 cursor-pointer items-center gap-2 rounded-xl border border-[#e7e5e4] bg-white px-4 text-[13px] font-semibold text-[#78716c] transition-all hover:bg-[#f5f5f4] active:scale-[0.98] disabled:opacity-50">
                    {IC.x} Batalkan
                  </button>
                )}
              </div>
            )}
          </div>
        ) : null}
      </Modal>

      {/* ===== Reject Modal ===== */}
      <Modal
        open={rejectOpen}
        onClose={() => setRejectOpen(false)}
        title="Tolak Permintaan Transfer"
        size="sm"
        footer={
          <div className="flex w-full items-center justify-between">
            <button
              onClick={() => setRejectOpen(false)}
              disabled={actionLoading}
              className="flex h-10 cursor-pointer items-center gap-2 rounded-xl border border-[#e7e5e4] bg-white px-5 text-[13px] font-semibold text-[#78716c] transition-all hover:bg-[#f5f5f4] disabled:opacity-50"
            >
              Batal
            </button>
            <button
              onClick={handleReject}
              disabled={actionLoading}
              className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#dc2626] to-[#ef4444] px-5 text-[13px] font-semibold text-white shadow-sm transition-all hover:shadow-md active:scale-[0.98] disabled:opacity-50"
            >
              {actionLoading ? (
                <>
                  <span className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                  Menolak...
                </>
              ) : (
                <>{IC.xCircle} Tolak</>
              )}
            </button>
          </div>
        }
      >
        <div className="space-y-3">
          <div className="flex items-center gap-3 rounded-xl bg-[#dc2626]/5 p-3">
            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-[#dc2626] text-white">{IC.xCircle}</div>
            <p className="text-[13px] font-semibold text-[#dc2626]">Anda akan menolak permintaan ini</p>
          </div>
          <div className="space-y-1.5">
            <label className="text-[12px] font-semibold text-[#44403c]">Alasan (opsional)</label>
            <textarea
              value={rejectReason}
              onChange={(e) => setRejectReason(e.target.value)}
              placeholder="Alasan penolakan..."
              rows={3}
              className="w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 py-2.5 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            />
          </div>
        </div>
      </Modal>
    </DashboardLayout>
  )
}
