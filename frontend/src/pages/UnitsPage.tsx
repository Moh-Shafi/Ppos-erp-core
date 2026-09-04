import { useState, useEffect, useCallback, useMemo } from 'react'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { Button } from '@/components/ui/Button'
import { Modal, ConfirmDialog } from '@/components/ui/Modal'
import { unitService } from '@/services/unit'
import { useAuthStore } from '@/stores/auth'
import type { Unit, PaginatedResponse } from '@/types'

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
  ruler: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M21.3 8.7L8.7 21.3a1 1 0 0 1-1.4 0L2.7 16.7a1 1 0 0 1 0-1.4L15.3 2.7a1 1 0 0 1 1.4 0l4.6 4.6a1 1 0 0 1 0 1.4z"/><path d="M7.5 10.5l2 2M10.5 7.5l2 2M13.5 4.5l2 2M4.5 13.5l2 2"/></svg>
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
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" className="h-12 w-12"><path d="M21.3 8.7L8.7 21.3a1 1 0 0 1-1.4 0L2.7 16.7a1 1 0 0 1 0-1.4L15.3 2.7a1 1 0 0 1 1.4 0l4.6 4.6a1 1 0 0 1 0 1.4z"/></svg>
  ),
  arrowRight: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
  ),
  refresh: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
  ),
  layers: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
  ),
  anchor: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><circle cx="12" cy="5" r="3"/><line x1="12" y1="22" x2="12" y2="8"/><path d="M5 12H2a10 10 0 0 0 20 0h-3"/></svg>
  ),
  exchange: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
  ),
}

/* ---------- Unit color palette ---------- */

const UNIT_COLORS = [
  { bg: 'bg-[#f54927]/10', text: 'text-[#f54927]', grad: 'from-[#f54927] to-[#ff6b4a]' },
  { bg: 'bg-[#0a84ff]/10', text: 'text-[#0a84ff]', grad: 'from-[#0a84ff] to-[#3b82f6]' },
  { bg: 'bg-[#16a34a]/10', text: 'text-[#16a34a]', grad: 'from-[#16a34a] to-[#22c55e]' },
  { bg: 'bg-[#8b5cf6]/10', text: 'text-[#8b5cf6]', grad: 'from-[#8b5cf6] to-[#a78bfa]' },
  { bg: 'bg-[#ca8a04]/10', text: 'text-[#ca8a04]', grad: 'from-[#ca8a04] to-[#facc15]' },
  { bg: 'bg-[#ec4899]/10', text: 'text-[#ec4899]', grad: 'from-[#ec4899] to-[#f472b6]' },
  { bg: 'bg-[#0891b2]/10', text: 'text-[#0891b2]', grad: 'from-[#0891b2] to-[#06b6d4]' },
  { bg: 'bg-[#d97706]/10', text: 'text-[#d97706]', grad: 'from-[#d97706] to-[#f59e0b]' },
]

function getColor(idx: number) {
  return UNIT_COLORS[idx % UNIT_COLORS.length]
}

function Skeleton({ className }: { className: string }) {
  return <div className={`animate-pulse rounded-xl bg-[#f0efec] ${className}`} />
}

/* ---------- Component ---------- */

export function UnitsPage() {
  const { user } = useAuthStore()
  const canManage = user?.role?.slug === 'owner' || user?.role?.slug === 'manager'

  const [units, setUnits] = useState<Unit[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [search, setSearch] = useState('')

  const [formOpen, setFormOpen] = useState(false)
  const [editUnit, setEditUnit] = useState<Unit | null>(null)
  const [formLoading, setFormLoading] = useState(false)
  const [form, setForm] = useState({ name: '', symbol: '', is_base_unit: false })
  const [formErrors, setFormErrors] = useState<Record<string, string[]>>({})

  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [deleteName, setDeleteName] = useState('')
  const [deleteLoading, setDeleteLoading] = useState(false)
  const [deleteError, setDeleteError] = useState('')

  const [convOpen, setConvOpen] = useState(false)
  const [convForm, setConvForm] = useState({ from_unit_id: '', to_unit_id: '', factor: '' })
  const [convLoading, setConvLoading] = useState(false)
  const [convError, setConvError] = useState('')

  const fetchUnits = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params: Record<string, unknown> = { page, per_page: 20 }
      if (search) params.search = search
      const res: PaginatedResponse<Unit> = await unitService.list(params)
      setUnits(res.data)
      setLastPage(res.last_page)
      setTotal(res.total)
    } catch {
      setError('Failed to load units')
    } finally {
      setLoading(false)
    }
  }, [page, search])

  useEffect(() => {
    const timer = setTimeout(() => { setPage(1); fetchUnits() }, 250)
    return () => clearTimeout(timer)
  }, [search])

  useEffect(() => { fetchUnits() }, [page])

  const handleAdd = () => {
    setEditUnit(null)
    setForm({ name: '', symbol: '', is_base_unit: false })
    setFormErrors({})
    setFormOpen(true)
  }

  const handleEdit = (unit: Unit) => {
    setEditUnit(unit)
    setForm({ name: unit.name, symbol: unit.symbol, is_base_unit: unit.is_base_unit })
    setFormErrors({})
    setFormOpen(true)
  }

  const handleFormSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setFormLoading(true)
    setFormErrors({})
    try {
      if (editUnit) {
        await unitService.update(editUnit.id, form)
      } else {
        await unitService.create(form)
      }
      setFormOpen(false)
      fetchUnits()
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
      await unitService.delete(deleteId)
      setDeleteId(null)
      fetchUnits()
    } catch (err: any) {
      setDeleteError(err.response?.data?.message ?? 'Failed to delete unit')
    } finally {
      setDeleteLoading(false)
    }
  }

  const handleConvSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setConvLoading(true)
    setConvError('')
    try {
      await unitService.createConversion({
        from_unit_id: parseInt(convForm.from_unit_id, 10),
        to_unit_id: parseInt(convForm.to_unit_id, 10),
        factor: parseFloat(convForm.factor),
      })
      setConvOpen(false)
      setConvForm({ from_unit_id: '', to_unit_id: '', factor: '' })
      fetchUnits()
    } catch (err: any) {
      setConvError(err.response?.data?.message ?? 'Failed to create conversion')
    } finally {
      setConvLoading(false)
    }
  }

  const summary = useMemo(() => {
    const baseUnits = units.filter((u) => u.is_base_unit).length
    const withConversions = units.filter((u) => u.conversions && u.conversions.length > 0).length
    return { baseUnits, withConversions, derived: units.length - baseUnits }
  }, [units])

  return (
    <DashboardLayout>
      <div className="space-y-5">
        {/* ===== Header ===== */}
        <div className="animate-fade-up flex items-center justify-between">
          <div>
            <h1 className="text-[22px] font-bold tracking-tight text-[#1c1917]">Satuan</h1>
            <p className="mt-1 text-[13px] text-[#78716c]">Kelola satuan dan konversi produk</p>
          </div>
          {canManage && (
            <div className="flex gap-2">
              <button
                onClick={() => { setConvForm({ from_unit_id: '', to_unit_id: '', factor: '' }); setConvError(''); setConvOpen(true) }}
                className="flex h-10 cursor-pointer items-center gap-2 rounded-xl border border-[#e7e5e4] bg-white px-4 text-[13px] font-semibold text-[#78716c] transition-all hover:border-[#f54927]/30 hover:bg-[#fef8f6] hover:text-[#f54927] active:scale-[0.97]"
              >
                {IC.exchange} Tambah Konversi
              </button>
              <button
                onClick={handleAdd}
                className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-4 text-[13px] font-semibold text-white shadow-[0_4px_14px_rgba(245,73,39,0.3)] transition-all hover:shadow-[0_6px_20px_rgba(245,73,39,0.4)] active:scale-[0.97]"
              >
                {IC.plus} Tambah Satuan
              </button>
            </div>
          )}
        </div>

        {/* ===== Summary Cards ===== */}
        <div className="animate-fade-up grid grid-cols-2 gap-4 lg:grid-cols-4" style={{ animationDelay: '0.05s' }}>
          {loading && units.length === 0 ? (
            [...Array(4)].map((_, i) => <Skeleton key={i} className="h-[90px]" />)
          ) : (
            <>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Total Satuan</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#1c1917] tabular-nums">{total}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#0a84ff]/10 text-[#0a84ff]">{IC.ruler}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Satuan Dasar</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#16a34a] tabular-nums">{summary.baseUnits}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#16a34a]/10 text-[#16a34a]">{IC.anchor}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Satuan Turunan</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#8b5cf6] tabular-nums">{summary.derived}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#8b5cf6]/10 text-[#8b5cf6]">{IC.layers}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Dengan Konversi</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#f54927] tabular-nums">{summary.withConversions}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#f54927]/10 text-[#f54927]">{IC.exchange}</div>
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
              placeholder="Cari satuan..."
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
            <button onClick={fetchUnits} className="mt-3 cursor-pointer rounded-xl border border-[#e7e5e4] bg-white px-4 py-2 text-[13px] font-medium text-[#78716c] transition-all hover:bg-[#f5f5f4]">
              Coba lagi
            </button>
          </div>
        ) : units.length === 0 ? (
          <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-[#e7e5e4] bg-white/50 py-20">
            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-[#f5f5f4] text-[#d6d3d1]">{IC.empty}</div>
            <p className="text-[15px] font-semibold text-[#78716c]">Belum ada satuan</p>
            <p className="mt-1 text-[12px] text-[#a8a29e]">Tambahkan satuan pertama Anda</p>
            {canManage && (
              <button onClick={handleAdd} className="mt-4 flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-4 text-[13px] font-semibold text-white shadow-sm transition-all hover:shadow-md active:scale-[0.98]">
                {IC.plus} Tambah Satuan
              </button>
            )}
          </div>
        ) : (
          /* ===== Grid Cards ===== */
          <div className="animate-fade-up grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" style={{ animationDelay: '0.15s' }}>
            {units.map((u, idx) => {
              const color = getColor(idx)
              return (
                <div
                  key={u.id}
                  className="group flex flex-col rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all duration-200 hover:border-[#f54927]/30 hover:shadow-[0_8px_24px_rgba(245,73,39,0.08)]"
                >
                  {/* Header */}
                  <div className="flex items-start gap-3">
                    <div className={`relative flex h-12 w-12 flex-shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br ${color.grad}`}>
                      <span className="text-[14px] font-bold text-white/90">{u.symbol.slice(0, 3).toUpperCase()}</span>
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-[14px] font-bold text-[#1c1917]">{u.name}</p>
                      <p className="mt-0.5 text-[11px] text-[#a8a29e]">Simbol: <span className="font-mono font-semibold text-[#78716c]">{u.symbol}</span></p>
                    </div>
                    {u.is_base_unit && (
                      <span className="flex items-center gap-1 rounded-full bg-[#16a34a]/10 px-2 py-0.5 text-[9px] font-bold text-[#16a34a]">
                        {IC.anchor} BASE
                      </span>
                    )}
                  </div>

                  {/* Conversions */}
                  {u.conversions && u.conversions.length > 0 && (
                    <div className="mt-3 space-y-1.5 border-t border-[#f5f5f4] pt-3">
                      <p className="text-[10px] font-bold tracking-wide text-[#a8a29e] uppercase">Konversi</p>
                      {u.conversions.slice(0, 3).map((conv) => (
                        <div key={conv.id} className="flex items-center gap-1.5 text-[11px] text-[#78716c]">
                          <span className="font-mono font-semibold text-[#1c1917]">1 {u.symbol}</span>
                          {IC.arrowRight}
                          <span className="font-mono font-semibold text-[#f54927]">{conv.factor} {conv.to_unit?.symbol ?? '?'}</span>
                        </div>
                      ))}
                      {u.conversions.length > 3 && (
                        <p className="text-[10px] text-[#a8a29e]">+{u.conversions.length - 3} lainnya</p>
                      )}
                    </div>
                  )}

                  {/* Actions */}
                  {canManage && (
                    <div className="mt-3 flex gap-2 border-t border-[#f5f5f4] pt-3">
                      <button
                        onClick={() => handleEdit(u)}
                        className="flex h-8 flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-[#e7e5e4] text-[12px] font-semibold text-[#78716c] transition-all hover:border-[#f54927]/30 hover:bg-[#f54927]/5 hover:text-[#f54927]"
                      >
                        {IC.edit} Edit
                      </button>
                      <button
                        onClick={() => { setDeleteId(u.id); setDeleteName(u.name); setDeleteError('') }}
                        className="flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-[#e7e5e4] text-[#78716c] transition-all hover:border-[#dc2626]/30 hover:bg-[#dc2626]/5 hover:text-[#dc2626]"
                      >
                        {IC.trash}
                      </button>
                    </div>
                  )}
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
        title={editUnit ? 'Edit Satuan' : 'Tambah Satuan'}
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
                  {IC.check} {editUnit ? 'Update' : 'Simpan'}
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
              <span className="text-[14px] font-bold">{(form.symbol || '?').slice(0, 3).toUpperCase()}</span>
            </div>
            <div>
              <p className="text-[13px] font-bold text-[#1c1917]">{form.name || 'Nama Satuan'}</p>
              <p className="text-[11px] text-[#a8a29e]">{form.is_base_unit ? 'Satuan Dasar' : 'Satuan Turunan'}</p>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <label className="flex items-center gap-1.5 text-[12px] font-semibold text-[#44403c]">
                {IC.ruler} Nama Satuan <span className="text-[#f54927]">*</span>
              </label>
              <input
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                placeholder="Kilogram"
                className="h-10 w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
              />
              {formErrors.name && <p className="text-[11px] font-medium text-[#dc2626]">{formErrors.name[0]}</p>}
            </div>

            <div className="space-y-1.5">
              <label className="text-[12px] font-semibold text-[#44403c]">Simbol <span className="text-[#f54927]">*</span></label>
              <input
                value={form.symbol}
                onChange={(e) => setForm({ ...form, symbol: e.target.value })}
                placeholder="kg"
                className="h-10 w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
              />
              {formErrors.symbol && <p className="text-[11px] font-medium text-[#dc2626]">{formErrors.symbol[0]}</p>}
            </div>
          </div>

          {/* Base unit toggle */}
          <label className={`flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition-all ${form.is_base_unit ? 'border-[#16a34a]/30 bg-[#16a34a]/5' : 'border-[#e7e5e4] bg-white hover:bg-[#fafaf9]'}`}>
            <div className={`flex h-5 w-5 items-center justify-center rounded-md transition-all ${form.is_base_unit ? 'bg-[#16a34a] text-white' : 'border border-[#e7e5e4] bg-white'}`}>
              {form.is_base_unit && IC.check}
            </div>
            <input type="checkbox" checked={form.is_base_unit} onChange={(e) => setForm({ ...form, is_base_unit: e.target.checked })} className="hidden" />
            <div>
              <p className="text-[12px] font-semibold text-[#1c1917]">Satuan Dasar</p>
              <p className="text-[10px] text-[#a8a29e]">Satuan referensi yang tidak bisa dikonversi</p>
            </div>
          </label>
        </form>
      </Modal>

      {/* ===== Conversion Modal ===== */}
      <Modal
        open={convOpen}
        onClose={() => setConvOpen(false)}
        title="Tambah Konversi Satuan"
        footer={
          <div className="flex w-full items-center justify-between">
            <Button variant="outline" onClick={() => setConvOpen(false)} disabled={convLoading}>
              Batal
            </Button>
            <Button onClick={handleConvSubmit} disabled={convLoading}>
              {convLoading ? (
                <span className="flex items-center gap-2">
                  <span className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                  Menyimpan...
                </span>
              ) : (
                <span className="flex items-center gap-2">
                  {IC.check} Simpan
                </span>
              )}
            </Button>
          </div>
        }
      >
        <form onSubmit={handleConvSubmit} className="space-y-5">
          {/* Visual preview */}
          {convForm.from_unit_id && convForm.to_unit_id && convForm.factor && (
            <div className="flex items-center justify-center gap-3 rounded-xl bg-gradient-to-r from-[#f54927]/5 to-transparent p-4">
              <div className="text-center">
                <p className="text-[10px] text-[#a8a29e]">Dari</p>
                <p className="text-[18px] font-bold text-[#1c1917]">1 {units.find((u) => u.id === parseInt(convForm.from_unit_id))?.symbol ?? '?'}</p>
              </div>
              {IC.arrowRight}
              <div className="text-center">
                <p className="text-[10px] text-[#a8a29e]">Faktor</p>
                <p className="text-[18px] font-bold text-[#f54927] tabular-nums">{convForm.factor}</p>
              </div>
              {IC.arrowRight}
              <div className="text-center">
                <p className="text-[10px] text-[#a8a29e]">Ke</p>
                <p className="text-[18px] font-bold text-[#1c1917]">{units.find((u) => u.id === parseInt(convForm.to_unit_id))?.symbol ?? '?'}</p>
              </div>
            </div>
          )}

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <label className="flex items-center gap-1.5 text-[12px] font-semibold text-[#44403c]">
                {IC.ruler} Dari Satuan <span className="text-[#f54927]">*</span>
              </label>
              <select
                value={convForm.from_unit_id}
                onChange={(e) => setConvForm({ ...convForm, from_unit_id: e.target.value })}
                className="h-10 w-full cursor-pointer rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
              >
                <option value="">Pilih satuan...</option>
                {units.map((u) => <option key={u.id} value={u.id}>{u.name} ({u.symbol})</option>)}
              </select>
            </div>

            <div className="space-y-1.5">
              <label className="flex items-center gap-1.5 text-[12px] font-semibold text-[#44403c]">
                {IC.ruler} Ke Satuan <span className="text-[#f54927]">*</span>
              </label>
              <select
                value={convForm.to_unit_id}
                onChange={(e) => setConvForm({ ...convForm, to_unit_id: e.target.value })}
                className="h-10 w-full cursor-pointer rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
              >
                <option value="">Pilih satuan...</option>
                {units.map((u) => <option key={u.id} value={u.id}>{u.name} ({u.symbol})</option>)}
              </select>
            </div>
          </div>

          <div className="space-y-1.5">
            <label className="text-[12px] font-semibold text-[#44403c]">Faktor Konversi <span className="text-[#f54927]">*</span></label>
            <input
              value={convForm.factor}
              onChange={(e) => setConvForm({ ...convForm, factor: e.target.value })}
              placeholder="cth: 1000 (1 kg = 1000 g)"
              inputMode="decimal"
              className="h-10 w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            />
            <p className="text-[11px] text-[#a8a29e]">1 satuan asal = faktor × satuan tujuan</p>
          </div>

          {convError && (
            <div className="rounded-xl border border-[#dc2626]/20 bg-[#fef2f2] p-3">
              <p className="text-[13px] font-medium text-[#dc2626]">{convError}</p>
            </div>
          )}
        </form>
      </Modal>

      {/* ===== Delete Confirm ===== */}
      <ConfirmDialog
        open={deleteId !== null}
        onClose={() => setDeleteId(null)}
        onConfirm={handleDelete}
        title="Hapus Satuan?"
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
