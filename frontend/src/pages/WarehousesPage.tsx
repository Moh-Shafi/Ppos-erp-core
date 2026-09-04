import { useState, useEffect, useCallback, useMemo } from 'react'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { Modal, ConfirmDialog } from '@/components/ui/Modal'
import { warehouseService } from '@/services/warehouse'
import { useAuthStore } from '@/stores/auth'
import type { Warehouse, PaginatedResponse } from '@/types'

/* ---------- Icons ---------- */

const IC = {
  plus: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
  ),
  search: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
  ),
  refresh: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
  ),
  warehouse: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M3 21V8l9-4 9 4v13"/><path d="M3 21h18"/><path d="M9 21v-6h6v6"/><path d="M9 11h6"/></svg>
  ),
  edit: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
  ),
  trash: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
  ),
  mapPin: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
  ),
  phone: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
  ),
  check: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polyline points="20 6 9 17 4 12"/></svg>
  ),
  xCircle: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
  ),
  empty: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" className="h-12 w-12"><path d="M3 21V8l9-4 9 4v13"/><path d="M3 21h18"/></svg>
  ),
  building: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M16 10h.01M16 14h.01M8 10h.01M8 14h.01"/></svg>
  ),
}

function Skeleton({ className }: { className: string }) {
  return <div className={`animate-pulse rounded-xl bg-[#f0efec] ${className}`} />
}

/* ---------- Component ---------- */

export function WarehousesPage() {
  const { user } = useAuthStore()
  const canManage = user?.role?.slug === 'owner' || user?.role?.slug === 'manager'

  const [warehouses, setWarehouses] = useState<Warehouse[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [search, setSearch] = useState('')

  const [formOpen, setFormOpen] = useState(false)
  const [editWH, setEditWH] = useState<Warehouse | null>(null)
  const [formLoading, setFormLoading] = useState(false)
  const [form, setForm] = useState({ name: '', address: '', phone: '', is_active: true })
  const [formErrors, setFormErrors] = useState<Record<string, string[]>>({})

  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [deleteName, setDeleteName] = useState('')
  const [deleteLoading, setDeleteLoading] = useState(false)
  const [deleteError, setDeleteError] = useState('')

  const fetchWarehouses = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params: Record<string, unknown> = { page, per_page: 20 }
      if (search) params.search = search
      const res: PaginatedResponse<Warehouse> = await warehouseService.list(params)
      setWarehouses(res.data)
      setLastPage(res.last_page)
      setTotal(res.total)
    } catch {
      setError('Failed to load warehouses')
    } finally {
      setLoading(false)
    }
  }, [page, search])

  useEffect(() => {
    const timer = setTimeout(() => {
      setPage(1)
      fetchWarehouses()
    }, 300)
    return () => clearTimeout(timer)
  }, [search])

  useEffect(() => { fetchWarehouses() }, [page])

  const handleAdd = () => {
    setEditWH(null)
    setForm({ name: '', address: '', phone: '', is_active: true })
    setFormErrors({})
    setFormOpen(true)
  }

  const handleEdit = (wh: Warehouse) => {
    setEditWH(wh)
    setForm({ name: wh.name, address: wh.address ?? '', phone: wh.phone ?? '', is_active: wh.is_active })
    setFormErrors({})
    setFormOpen(true)
  }

  const handleFormSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setFormLoading(true)
    setFormErrors({})
    try {
      if (editWH) {
        await warehouseService.update(editWH.id, form)
      } else {
        await warehouseService.create(form)
      }
      setFormOpen(false)
      fetchWarehouses()
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
      await warehouseService.delete(deleteId)
      setDeleteId(null)
      fetchWarehouses()
    } catch (err: any) {
      const msg = err.response?.data?.message ?? 'Failed to delete warehouse'
      setDeleteError(msg)
    } finally {
      setDeleteLoading(false)
    }
  }

  const summary = useMemo(() => {
    const active = warehouses.filter((w) => w.is_active).length
    const inactive = warehouses.length - active
    return { active, inactive, total }
  }, [warehouses, total])

  return (
    <DashboardLayout>
      <div className="space-y-5">
        {/* ===== Header ===== */}
        <div className="animate-fade-up flex items-center justify-between">
          <div>
            <h1 className="text-[22px] font-bold tracking-tight text-[#1c1917]">Gudang</h1>
            <p className="mt-1 text-[13px] text-[#78716c]">Kelola gudang penyimpanan stok</p>
          </div>
          {canManage && (
            <button
              onClick={handleAdd}
              className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-4 text-[13px] font-semibold text-white shadow-[0_4px_14px_rgba(245,73,39,0.3)] transition-all hover:shadow-[0_6px_20px_rgba(245,73,39,0.4)] active:scale-[0.97]"
            >
              {IC.plus} Tambah Gudang
            </button>
          )}
        </div>

        {/* ===== Summary Cards ===== */}
        <div className="animate-fade-up grid grid-cols-3 gap-4" style={{ animationDelay: '0.05s' }}>
          {loading && warehouses.length === 0 ? (
            [...Array(3)].map((_, i) => <Skeleton key={i} className="h-[90px]" />)
          ) : (
            <>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Total Gudang</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#1c1917] tabular-nums">{summary.total}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#0a84ff]/10 text-[#0a84ff]">{IC.warehouse}</div>
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
                    <p className="text-[11px] font-medium text-[#a8a29e]">Nonaktif</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#a8a29e] tabular-nums">{summary.inactive}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#a8a29e]/10 text-[#a8a29e]">{IC.xCircle}</div>
                </div>
              </div>
            </>
          )}
        </div>

        {/* ===== Search ===== */}
        <div className="animate-fade-up flex flex-wrap items-center gap-2" style={{ animationDelay: '0.1s' }}>
          <div className="relative min-w-[200px] flex-1">
            <div className="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-[#a8a29e]">{IC.search}</div>
            <input
              type="text"
              placeholder="Cari gudang..."
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
              Reset
            </button>
          )}
        </div>

        {/* ===== Content ===== */}
        {loading ? (
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {[...Array(6)].map((_, i) => (
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
            <button onClick={fetchWarehouses} className="mt-3 flex h-9 cursor-pointer items-center gap-2 rounded-xl border border-[#e7e5e4] bg-white px-4 text-[13px] font-medium text-[#78716c] transition-all hover:bg-[#f5f5f4]">
              {IC.refresh} Coba lagi
            </button>
          </div>
        ) : warehouses.length === 0 ? (
          <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-[#e7e5e4] bg-white/50 py-20">
            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-[#f5f5f4] text-[#d6d3d1]">{IC.empty}</div>
            <p className="text-[15px] font-semibold text-[#78716c]">Belum ada gudang</p>
            <p className="mt-1 text-[12px] text-[#a8a29e]">Tambahkan gudang pertama Anda</p>
            {canManage && (
              <button onClick={handleAdd} className="mt-4 flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-4 text-[13px] font-semibold text-white shadow-sm transition-all hover:shadow-md active:scale-[0.98]">
                {IC.plus} Tambah Gudang
              </button>
            )}
          </div>
        ) : (
          /* ===== Grid Cards ===== */
          <div className="animate-fade-up grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3" style={{ animationDelay: '0.15s' }}>
            {warehouses.map((wh) => (
              <div
                key={wh.id}
                className="group flex flex-col rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all duration-200 hover:border-[#f54927]/30 hover:shadow-[0_8px_24px_rgba(245,73,39,0.08)]"
              >
                {/* Header */}
                <div className="flex items-start gap-3">
                  <div className={`flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl ${wh.is_active ? 'bg-[#0a84ff]/10 text-[#0a84ff]' : 'bg-[#a8a29e]/10 text-[#a8a29e]'}`}>
                    {IC.warehouse}
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-[14px] font-bold text-[#1c1917]">{wh.name}</p>
                    <span className={`mt-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[9px] font-bold ${
                      wh.is_active ? 'bg-[#16a34a]/10 text-[#16a34a]' : 'bg-[#dc2626]/10 text-[#dc2626]'
                    }`}>
                      <span className={`h-1.5 w-1.5 rounded-full ${wh.is_active ? 'bg-[#16a34a]' : 'bg-[#dc2626]'}`} />
                      {wh.is_active ? 'Aktif' : 'Nonaktif'}
                    </span>
                  </div>
                </div>

                {/* Details */}
                <div className="mt-3 space-y-1.5 border-t border-[#f5f5f4] pt-3">
                  <div className="flex items-start gap-2 text-[12px] text-[#78716c]">
                    <span className="mt-0.5 flex-shrink-0 text-[#a8a29e]">{IC.mapPin}</span>
                    <span className="min-w-0 truncate">{wh.address || 'Tidak ada alamat'}</span>
                  </div>
                  <div className="flex items-center gap-2 text-[12px] text-[#78716c]">
                    <span className="flex-shrink-0 text-[#a8a29e]">{IC.phone}</span>
                    <span>{wh.phone || 'Tidak ada telepon'}</span>
                  </div>
                </div>

                {/* Actions */}
                {canManage && (
                  <div className="mt-3 flex gap-2 border-t border-[#f5f5f4] pt-3">
                    <button
                      onClick={() => handleEdit(wh)}
                      className="flex h-8 flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-[#e7e5e4] text-[12px] font-semibold text-[#78716c] transition-all hover:border-[#0a84ff]/30 hover:bg-[#0a84ff]/5 hover:text-[#0a84ff]"
                    >
                      {IC.edit} Edit
                    </button>
                    <button
                      onClick={() => { setDeleteId(wh.id); setDeleteName(wh.name); setDeleteError('') }}
                      className="flex h-8 flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-[#e7e5e4] text-[12px] font-semibold text-[#78716c] transition-all hover:border-[#dc2626]/30 hover:bg-[#dc2626]/5 hover:text-[#dc2626]"
                    >
                      {IC.trash} Hapus
                    </button>
                  </div>
                )}
              </div>
            ))}
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

      {/* ===== Form Modal ===== */}
      <Modal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        title={editWH ? 'Edit Gudang' : 'Tambah Gudang'}
        footer={
          <div className="flex w-full items-center justify-between">
            <button
              onClick={() => setFormOpen(false)}
              disabled={formLoading}
              className="flex h-10 cursor-pointer items-center gap-2 rounded-xl border border-[#e7e5e4] bg-white px-5 text-[13px] font-semibold text-[#78716c] transition-all hover:bg-[#f5f5f4] disabled:opacity-50"
            >
              Batal
            </button>
            <button
              onClick={handleFormSubmit}
              disabled={formLoading}
              className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-5 text-[13px] font-semibold text-white shadow-[0_4px_14px_rgba(245,73,39,0.3)] transition-all hover:shadow-[0_6px_20px_rgba(245,73,39,0.4)] active:scale-[0.97] disabled:cursor-not-allowed disabled:opacity-50"
            >
              {formLoading ? (
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
        <form onSubmit={handleFormSubmit} className="space-y-5">
          {/* Icon header */}
          <div className="flex items-center gap-3 rounded-xl bg-gradient-to-r from-[#f54927]/5 to-transparent p-4">
            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-[#f54927] to-[#ff6b4a] text-white">
              {IC.warehouse}
            </div>
            <div>
              <p className="text-[13px] font-bold text-[#1c1917]">{editWH ? 'Edit Gudang' : 'Gudang Baru'}</p>
              <p className="text-[11px] text-[#a8a29e]">{editWH ? 'Perbarui informasi gudang' : 'Tambahkan gudang penyimpanan'}</p>
            </div>
          </div>

          <div className="space-y-1.5">
            <label className="text-[12px] font-semibold text-[#44403c]">Nama <span className="text-[#f54927]">*</span></label>
            <input
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              placeholder="Gudang Utama"
              className="h-10 w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            />
            {formErrors.name && <p className="text-[11px] font-medium text-[#dc2626]">{formErrors.name[0]}</p>}
          </div>

          <div className="space-y-1.5">
            <label className="flex items-center gap-1.5 text-[12px] font-semibold text-[#44403c]">
              {IC.mapPin} Alamat
            </label>
            <input
              value={form.address}
              onChange={(e) => setForm({ ...form, address: e.target.value })}
              placeholder="Jl. Industri No. 1"
              className="h-10 w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            />
          </div>

          <div className="space-y-1.5">
            <label className="flex items-center gap-1.5 text-[12px] font-semibold text-[#44403c]">
              {IC.phone} Telepon
            </label>
            <input
              value={form.phone}
              onChange={(e) => setForm({ ...form, phone: e.target.value })}
              placeholder="021-1234567"
              className="h-10 w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            />
          </div>

          {/* Active toggle */}
          <div className="flex items-center justify-between rounded-xl border border-[#e7e5e4] bg-[#fafaf9] p-3">
            <div>
              <p className="text-[13px] font-semibold text-[#1c1917]">Status Aktif</p>
              <p className="text-[11px] text-[#a8a29e]">Gudang aktif dapat digunakan untuk transaksi</p>
            </div>
            <button
              type="button"
              onClick={() => setForm({ ...form, is_active: !form.is_active })}
              className={`relative h-6 w-11 cursor-pointer rounded-full transition-all ${form.is_active ? 'bg-[#16a34a]' : 'bg-[#d6d3d1]'}`}
            >
              <span className={`absolute top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-all ${form.is_active ? 'left-[22px]' : 'left-0.5'}`} />
            </button>
          </div>
        </form>
      </Modal>

      {/* ===== Delete Confirm ===== */}
      <ConfirmDialog
        open={deleteId !== null}
        onClose={() => setDeleteId(null)}
        onConfirm={handleDelete}
        title="Hapus Gudang?"
        message={`Apakah Anda yakin ingin menghapus "${deleteName}"?`}
        confirmLabel="Hapus"
        cancelLabel="Batal"
        loading={deleteLoading}
      />

      {deleteError && (
        <div className="fixed bottom-4 right-4 z-50 rounded-xl border border-[#dc2626]/20 bg-white p-4 shadow-lg">
          <p className="text-[13px] font-medium text-[#dc2626]">{deleteError}</p>
        </div>
      )}
    </DashboardLayout>
  )
}
