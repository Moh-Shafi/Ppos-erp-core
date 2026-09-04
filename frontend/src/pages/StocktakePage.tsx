import { useState, useEffect, useCallback, useMemo } from 'react'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { Modal } from '@/components/ui/Modal'
import { stocktakeService } from '@/services/stocktake'
import { adjustmentReasonService } from '@/services/adjustmentReason'
import { useModuleConfigStore } from '@/stores/module-config'
import { useAuthStore } from '@/stores/auth'
import type { StocktakeSession, StocktakeItem, StockAdjustmentReason, PaginatedResponse } from '@/types'

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
  store: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M3 9l1-5h16l1 5M4 9v11h16V9M9 20v-6h6v6"/></svg>
  ),
  clipboard: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
  ),
  refresh: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
  ),
  filter: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
  ),
  empty: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" className="h-12 w-12"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
  ),
  box: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
  ),
  check: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polyline points="20 6 9 17 4 12"/></svg>
  ),
  xCircle: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
  ),
  play: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polygon points="5 3 19 12 5 21 5 3"/></svg>
  ),
  scale: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M16 16l3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1z"/><path d="M2 16l3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/></svg>
  ),
  send: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
  ),
  edit: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
  ),
  arrowUp: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
  ),
  arrowDown: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
  ),
  fileText: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
  ),
  clock: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
  ),
  checkCircle: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
  ),
}

/* ---------- Status config ---------- */

const STATUS_CONFIG: Record<string, { label: string; bg: string; text: string; dot: string; icon: React.JSX.Element }> = {
  draft: { label: 'Draft', bg: 'bg-[#a8a29e]/10', text: 'text-[#a8a29e]', dot: 'bg-[#a8a29e]', icon: IC.fileText },
  counting: { label: 'Menghitung', bg: 'bg-[#ca8a04]/10', text: 'text-[#ca8a04]', dot: 'bg-[#ca8a04]', icon: IC.clock },
  reconciling: { label: 'Rekonsiliasi', bg: 'bg-[#0a84ff]/10', text: 'text-[#0a84ff]', dot: 'bg-[#0a84ff]', icon: IC.scale },
  posted: { label: 'Diposting', bg: 'bg-[#16a34a]/10', text: 'text-[#16a34a]', dot: 'bg-[#16a34a]', icon: IC.checkCircle },
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

export function StocktakePage() {
  const { user } = useAuthStore()
  const canManage = user?.role?.slug === 'owner' || user?.role?.slug === 'manager'
  const moduleConfig = useModuleConfigStore()
  const stores = moduleConfig.stores

  const [sessions, setSessions] = useState<StocktakeSession[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [statusFilter, setStatusFilter] = useState('')

  const [createOpen, setCreateOpen] = useState(false)
  const [createForm, setCreateForm] = useState({ store_id: '', note: '' })
  const [createLoading, setCreateLoading] = useState(false)
  const [createError, setCreateError] = useState('')

  const [detailSession, setDetailSession] = useState<StocktakeSession | null>(null)
  const [detailLoading, setDetailLoading] = useState(false)
  const [editingItem, setEditingItem] = useState<StocktakeItem | null>(null)
  const [itemForm, setItemForm] = useState({ counted_quantity: '', note: '' })
  const [itemLoading, setItemLoading] = useState(false)

  const [reasons, setReasons] = useState<StockAdjustmentReason[]>([])
  const [postReasonId, setPostReasonId] = useState('')
  const [actionLoading, setActionLoading] = useState(false)

  const fetchSessions = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params: Record<string, unknown> = { page, per_page: 20 }
      if (statusFilter) params.status = statusFilter
      const res: PaginatedResponse<StocktakeSession> = await stocktakeService.list(params)
      setSessions(res.data)
      setLastPage(res.last_page)
      setTotal(res.total)
    } catch {
      setError('Failed to load stocktake sessions')
    } finally {
      setLoading(false)
    }
  }, [page, statusFilter])

  useEffect(() => { fetchSessions() }, [page, statusFilter])

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault()
    setCreateLoading(true)
    setCreateError('')
    try {
      await stocktakeService.create({
        store_id: parseInt(createForm.store_id, 10),
        note: createForm.note || undefined,
      })
      setCreateOpen(false)
      setCreateForm({ store_id: '', note: '' })
      fetchSessions()
    } catch (err: any) {
      setCreateError(err.response?.data?.message ?? 'Failed to create session')
    } finally {
      setCreateLoading(false)
    }
  }

  const handleViewDetail = async (id: number) => {
    setDetailLoading(true)
    try {
      const session = await stocktakeService.get(id)
      setDetailSession(session)
    } catch {
      setError('Failed to load session detail')
    } finally {
      setDetailLoading(false)
    }
  }

  const handleStartCounting = async (id: number) => {
    setActionLoading(true)
    try {
      await stocktakeService.start(id)
      const updated = await stocktakeService.get(id)
      setDetailSession(updated)
      fetchSessions()
    } catch (err: any) {
      setError(err.response?.data?.message ?? 'Failed to start counting')
    } finally {
      setActionLoading(false)
    }
  }

  const handleEditItem = (item: StocktakeItem) => {
    setEditingItem(item)
    setItemForm({
      counted_quantity: item.counted_quantity?.toString() ?? '',
      note: item.note ?? '',
    })
  }

  const handleItemSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!detailSession || !editingItem) return
    setItemLoading(true)
    try {
      await stocktakeService.updateItem(detailSession.id, editingItem.id, {
        counted_quantity: parseInt(itemForm.counted_quantity, 10),
        note: itemForm.note || undefined,
      })
      const updated = await stocktakeService.get(detailSession.id)
      setDetailSession(updated)
      setEditingItem(null)
    } catch (err: any) {
      setError(err.response?.data?.message ?? 'Failed to update item')
    } finally {
      setItemLoading(false)
    }
  }

  const handleReconcile = async (id: number) => {
    setActionLoading(true)
    try {
      await stocktakeService.reconcile(id)
      const updated = await stocktakeService.get(id)
      setDetailSession(updated)
      fetchSessions()
    } catch (err: any) {
      setError(err.response?.data?.message ?? 'Failed to reconcile')
    } finally {
      setActionLoading(false)
    }
  }

  const handlePost = async (id: number) => {
    if (!postReasonId) return
    setActionLoading(true)
    try {
      await stocktakeService.post(id, parseInt(postReasonId, 10))
      const updated = await stocktakeService.get(id)
      setDetailSession(updated)
      setPostReasonId('')
      fetchSessions()
    } catch (err: any) {
      setError(err.response?.data?.message ?? 'Failed to post session')
    } finally {
      setActionLoading(false)
    }
  }

  const handleCancel = async (id: number) => {
    setActionLoading(true)
    try {
      await stocktakeService.cancel(id)
      const updated = await stocktakeService.get(id)
      setDetailSession(updated)
      fetchSessions()
    } catch (err: any) {
      setError(err.response?.data?.message ?? 'Failed to cancel')
    } finally {
      setActionLoading(false)
    }
  }

  const loadReasons = async () => {
    try {
      const data = await adjustmentReasonService.list({ is_active: true })
      setReasons(data)
    } catch {
      // ignore
    }
  }

  const summary = useMemo(() => {
    const byStatus: Record<string, number> = {}
    sessions.forEach((s) => { byStatus[s.status] = (byStatus[s.status] ?? 0) + 1 })
    return {
      counting: byStatus['counting'] ?? 0,
      reconciling: byStatus['reconciling'] ?? 0,
      posted: byStatus['posted'] ?? 0,
      draft: byStatus['draft'] ?? 0,
    }
  }, [sessions])

  return (
    <DashboardLayout>
      <div className="space-y-5">
        {/* ===== Header ===== */}
        <div className="animate-fade-up flex items-center justify-between">
          <div>
            <h1 className="text-[22px] font-bold tracking-tight text-[#1c1917]">Stock Opname</h1>
            <p className="mt-1 text-[13px] text-[#78716c]">Sesi penghitungan stok fisik</p>
          </div>
          {canManage && (
            <button
              onClick={() => { setCreateForm({ store_id: stores[0]?.id?.toString() ?? '', note: '' }); setCreateError(''); setCreateOpen(true) }}
              className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-4 text-[13px] font-semibold text-white shadow-[0_4px_14px_rgba(245,73,39,0.3)] transition-all hover:shadow-[0_6px_20px_rgba(245,73,39,0.4)] active:scale-[0.97]"
            >
              {IC.plus} Sesi Baru
            </button>
          )}
        </div>

        {/* ===== Summary Cards ===== */}
        <div className="animate-fade-up grid grid-cols-2 gap-4 lg:grid-cols-4" style={{ animationDelay: '0.05s' }}>
          {loading && sessions.length === 0 ? (
            [...Array(4)].map((_, i) => <Skeleton key={i} className="h-[90px]" />)
          ) : (
            <>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Total Sesi</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#1c1917] tabular-nums">{total}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#0a84ff]/10 text-[#0a84ff]">{IC.clipboard}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Menghitung</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#ca8a04] tabular-nums">{summary.counting}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#ca8a04]/10 text-[#ca8a04]">{IC.clock}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Rekonsiliasi</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#0a84ff] tabular-nums">{summary.reconciling}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#0a84ff]/10 text-[#0a84ff]">{IC.scale}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Diposting</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#16a34a] tabular-nums">{summary.posted}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#16a34a]/10 text-[#16a34a]">{IC.checkCircle}</div>
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
            <button onClick={fetchSessions} className="mt-3 flex h-9 cursor-pointer items-center gap-2 rounded-xl border border-[#e7e5e4] bg-white px-4 text-[13px] font-medium text-[#78716c] transition-all hover:bg-[#f5f5f4]">
              {IC.refresh} Coba lagi
            </button>
          </div>
        ) : sessions.length === 0 ? (
          <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-[#e7e5e4] bg-white/50 py-20">
            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-[#f5f5f4] text-[#d6d3d1]">{IC.empty}</div>
            <p className="text-[15px] font-semibold text-[#78716c]">Belum ada sesi stock opname</p>
            <p className="mt-1 text-[12px] text-[#a8a29e]">Buat sesi baru untuk mulai menghitung stok fisik</p>
            {canManage && (
              <button onClick={() => setCreateOpen(true)} className="mt-4 flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-4 text-[13px] font-semibold text-white shadow-sm transition-all hover:shadow-md active:scale-[0.98]">
                {IC.plus} Sesi Baru
              </button>
            )}
          </div>
        ) : (
          /* ===== Cards ===== */
          <div className="animate-fade-up space-y-3" style={{ animationDelay: '0.15s' }}>
            {sessions.map((s) => {
              const status = getStatus(s.status)
              const itemCount = s.items?.length ?? 0
              const countedItems = s.items?.filter((i) => i.counted_quantity !== null).length ?? 0
              return (
                <div
                  key={s.id}
                  className="group flex items-center gap-4 rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all duration-200 hover:border-[#f54927]/20 hover:shadow-[0_4px_16px_rgba(0,0,0,0.04)]"
                >
                  {/* Status icon */}
                  <div className={`flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl ${status.bg} ${status.text}`}>
                    {status.icon}
                  </div>

                  {/* Content */}
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                      <p className="text-[14px] font-bold text-[#1c1917]">{s.session_number}</p>
                      <span className={`flex items-center gap-1 rounded-full ${status.bg} px-2 py-0.5 text-[9px] font-bold ${status.text}`}>
                        <span className={`h-1.5 w-1.5 rounded-full ${status.dot}`} />
                        {status.label}
                      </span>
                    </div>
                    <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-[#a8a29e]">
                      <span className="flex items-center gap-1 font-medium text-[#78716c]">{IC.store} {s.store?.name ?? `Store #${s.store_id}`}</span>
                      <span>·</span>
                      <span>{IC.box} {itemCount} item</span>
                      {itemCount > 0 && (
                        <>
                          <span>·</span>
                          <span className="font-medium text-[#16a34a]">{countedItems} terhitung</span>
                        </>
                      )}
                      <span>·</span>
                      <span>{formatDate(s.created_at)}</span>
                    </div>
                  </div>

                  {/* View button */}
                  <button
                    onClick={() => handleViewDetail(s.id)}
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
        title="Sesi Stock Opname Baru"
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
          {/* Icon header */}
          <div className="flex items-center gap-3 rounded-xl bg-gradient-to-r from-[#f54927]/5 to-transparent p-4">
            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-[#f54927] to-[#ff6b4a] text-white">
              {IC.clipboard}
            </div>
            <div>
              <p className="text-[13px] font-bold text-[#1c1917]">Buat Sesi Baru</p>
              <p className="text-[11px] text-[#a8a29e]">Pilih toko untuk memulai stock opname</p>
            </div>
          </div>

          <div className="space-y-1.5">
            <label className="flex items-center gap-1.5 text-[12px] font-semibold text-[#44403c]">
              {IC.store} Toko <span className="text-[#f54927]">*</span>
            </label>
            <select
              value={createForm.store_id}
              onChange={(e) => setCreateForm({ ...createForm, store_id: e.target.value })}
              className="h-10 w-full cursor-pointer appearance-none rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            >
              <option value="">Pilih toko...</option>
              {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
            </select>
          </div>

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
        open={detailSession !== null}
        onClose={() => setDetailSession(null)}
        title={`Detail: ${detailSession?.session_number ?? ''}`}
        size="lg"
      >
        {detailLoading ? (
          <div className="flex items-center justify-center py-12">
            <span className="h-8 w-8 animate-spin rounded-full border-2 border-[#f54927] border-t-transparent" />
          </div>
        ) : detailSession ? (
          <div className="space-y-5">
            {/* Status header */}
            <div className="flex items-center gap-3 rounded-xl bg-gradient-to-r from-[#f54927]/5 to-transparent p-4">
              {(() => {
                const status = getStatus(detailSession.status)
                return (
                  <div className={`flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl ${status.bg} ${status.text}`}>
                    {status.icon}
                  </div>
                )
              })()}
              <div className="min-w-0 flex-1">
                <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-bold ${getStatus(detailSession.status).bg} ${getStatus(detailSession.status).text}`}>
                  <span className={`h-1.5 w-1.5 rounded-full ${getStatus(detailSession.status).dot}`} />
                  {getStatus(detailSession.status).label}
                </span>
                <p className="mt-1.5 flex items-center gap-1.5 text-[13px] font-semibold text-[#1c1917]">
                  {IC.store} {detailSession.store?.name ?? `Store #${detailSession.store_id}`}
                </p>
              </div>
            </div>

            {/* Note */}
            {detailSession.note && (
              <div className="rounded-xl border border-[#e7e5e4] bg-[#fafaf9] p-3">
                <p className="text-[10px] font-bold tracking-wide text-[#a8a29e] uppercase">Catatan</p>
                <p className="mt-1 text-[13px] text-[#44403c]">{detailSession.note}</p>
              </div>
            )}

            {/* Items */}
            {detailSession.items && detailSession.items.length > 0 && (
              <div className="space-y-2">
                <p className="text-[12px] font-bold tracking-wide text-[#44403c] uppercase">Daftar Item</p>
                <div className="max-h-[350px] space-y-2 overflow-y-auto pr-1">
                  {detailSession.items.map((item) => {
                    const variance = item.variance
                    const hasVariance = variance !== null && variance !== 0
                    const isPositive = (variance ?? 0) > 0
                    return (
                      <div key={item.id} className="flex items-center gap-3 rounded-xl border border-[#e7e5e4] bg-white p-3 transition-all hover:border-[#f54927]/20">
                        <div className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-[#f54927]/10 text-[#f54927]">
                          {IC.box}
                        </div>
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-[13px] font-semibold text-[#1c1917]">{item.product?.name ?? `Product #${item.product_id}`}</p>
                          <div className="mt-0.5 flex items-center gap-2 text-[11px] text-[#a8a29e]">
                            <span>Sistem: <span className="font-semibold text-[#78716c] tabular-nums">{item.system_quantity}</span></span>
                            <span>·</span>
                            <span>Fisik: <span className="font-semibold text-[#78716c] tabular-nums">{item.counted_quantity ?? '—'}</span></span>
                          </div>
                        </div>
                        {/* Variance badge */}
                        {variance !== null ? (
                          <div className={`flex h-9 min-w-[60px] flex-col items-center justify-center rounded-lg px-2 ${
                            hasVariance
                              ? (isPositive ? 'bg-[#16a34a]/10 text-[#16a34a]' : 'bg-[#dc2626]/10 text-[#dc2626]')
                              : 'bg-[#a8a29e]/10 text-[#a8a29e]'
                          }`}>
                            <p className="text-[12px] font-bold leading-none tabular-nums">
                              {hasVariance ? (isPositive ? `+${variance}` : variance) : '0'}
                            </p>
                            <p className="mt-0.5 text-[8px] font-medium opacity-70">variance</p>
                          </div>
                        ) : null}
                        {/* Edit button */}
                        {canManage && detailSession.status === 'counting' && (
                          <button
                            onClick={() => handleEditItem(item)}
                            className="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-[#e7e5e4] text-[#78716c] transition-all hover:border-[#f54927]/30 hover:bg-[#f54927]/5 hover:text-[#f54927]"
                          >
                            {IC.edit}
                          </button>
                        )}
                      </div>
                    )
                  })}
                </div>
              </div>
            )}

            {/* Action buttons */}
            {canManage && (
              <div className="flex flex-wrap gap-2 border-t border-[#f5f5f4] pt-4">
                {detailSession.status === 'draft' && (
                  <button onClick={() => handleStartCounting(detailSession.id)} disabled={actionLoading} className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#ca8a04] to-[#eab308] px-4 text-[13px] font-semibold text-white shadow-sm transition-all hover:shadow-md active:scale-[0.98] disabled:opacity-50">
                    {IC.play} Mulai Hitung
                  </button>
                )}
                {detailSession.status === 'counting' && (
                  <button onClick={() => handleReconcile(detailSession.id)} disabled={actionLoading} className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#0a84ff] to-[#3b82f6] px-4 text-[13px] font-semibold text-white shadow-sm transition-all hover:shadow-md active:scale-[0.98] disabled:opacity-50">
                    {IC.scale} Rekonsiliasi
                  </button>
                )}
                {detailSession.status === 'reconciling' && (
                  <>
                    <select
                      value={postReasonId}
                      onChange={(e) => setPostReasonId(e.target.value)}
                      onClick={loadReasons}
                      className="h-10 cursor-pointer appearance-none rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] font-medium text-[#1c1917] outline-none transition-all focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
                    >
                      <option value="">Pilih alasan...</option>
                      {reasons.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
                    </select>
                    <button onClick={() => handlePost(detailSession.id)} disabled={actionLoading || !postReasonId} className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#16a34a] to-[#22c55e] px-4 text-[13px] font-semibold text-white shadow-sm transition-all hover:shadow-md active:scale-[0.98] disabled:opacity-50">
                      {IC.send} Post & Sesuaikan
                    </button>
                  </>
                )}
                {['draft', 'counting'].includes(detailSession.status) && (
                  <button onClick={() => handleCancel(detailSession.id)} disabled={actionLoading} className="flex h-10 cursor-pointer items-center gap-2 rounded-xl border border-[#dc2626]/30 bg-[#dc2626]/5 px-4 text-[13px] font-semibold text-[#dc2626] transition-all hover:bg-[#dc2626]/10 active:scale-[0.98] disabled:opacity-50">
                    {IC.xCircle} Batalkan
                  </button>
                )}
              </div>
            )}
          </div>
        ) : null}
      </Modal>

      {/* ===== Edit Item Modal ===== */}
      <Modal
        open={editingItem !== null}
        onClose={() => setEditingItem(null)}
        title="Hitung Stok Fisik"
        size="sm"
        footer={
          <div className="flex w-full items-center justify-between">
            <button
              onClick={() => setEditingItem(null)}
              disabled={itemLoading}
              className="flex h-10 cursor-pointer items-center gap-2 rounded-xl border border-[#e7e5e4] bg-white px-5 text-[13px] font-semibold text-[#78716c] transition-all hover:bg-[#f5f5f4] disabled:opacity-50"
            >
              Batal
            </button>
            <button
              onClick={handleItemSubmit}
              disabled={itemLoading}
              className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-5 text-[13px] font-semibold text-white shadow-sm transition-all hover:shadow-md active:scale-[0.98] disabled:opacity-50"
            >
              {itemLoading ? (
                <>
                  <span className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                  Menyimpan...
                </>
              ) : (
                <>{IC.check} Simpan</>
              )}
            </button>
          </div>
        }
      >
        <form onSubmit={handleItemSubmit} className="space-y-4">
          {/* Product info */}
          <div className="flex items-center gap-3 rounded-xl bg-gradient-to-r from-[#f54927]/5 to-transparent p-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#f54927]/10 text-[#f54927]">{IC.box}</div>
            <div>
              <p className="text-[13px] font-bold text-[#1c1917]">{editingItem?.product?.name ?? `Product #${editingItem?.product_id}`}</p>
              <p className="text-[11px] text-[#a8a29e]">Stok sistem: <span className="font-bold tabular-nums text-[#78716c]">{editingItem?.system_quantity}</span></p>
            </div>
          </div>

          <div className="space-y-1.5">
            <label className="text-[12px] font-semibold text-[#44403c]">Jumlah Fisik <span className="text-[#f54927]">*</span></label>
            <input
              type="number"
              value={itemForm.counted_quantity}
              onChange={(e) => setItemForm({ ...itemForm, counted_quantity: e.target.value })}
              placeholder="0"
              className="h-10 w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[14px] font-bold tabular-nums text-[#1c1917] outline-none transition-all placeholder:font-normal placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            />
          </div>

          <div className="space-y-1.5">
            <label className="text-[12px] font-semibold text-[#44403c]">Catatan</label>
            <input
              value={itemForm.note}
              onChange={(e) => setItemForm({ ...itemForm, note: e.target.value })}
              placeholder="Catatan opsional..."
              className="h-10 w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            />
          </div>
        </form>
      </Modal>
    </DashboardLayout>
  )
}
