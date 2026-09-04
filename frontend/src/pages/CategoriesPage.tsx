import { useState, useEffect, useCallback, useMemo } from 'react'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog, Modal } from '@/components/ui/Modal'
import { categoryService } from '@/services/category'
import { productService } from '@/services/product'
import { useAuthStore } from '@/stores/auth'
import type { Category, PaginatedResponse } from '@/types'

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
  tag: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
  ),
  layers: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
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
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" className="h-12 w-12"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
  ),
  package: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
  ),
  chevron: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><polyline points="6 9 12 15 18 9"/></svg>
  ),
  folder: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
  ),
}

/* ---------- Category color palette ---------- */

const CAT_COLORS = [
  { bg: 'bg-[#f54927]/10', text: 'text-[#f54927]', grad: 'from-[#f54927] to-[#ff6b4a]' },
  { bg: 'bg-[#0a84ff]/10', text: 'text-[#0a84ff]', grad: 'from-[#0a84ff] to-[#3b82f6]' },
  { bg: 'bg-[#16a34a]/10', text: 'text-[#16a34a]', grad: 'from-[#16a34a] to-[#22c55e]' },
  { bg: 'bg-[#8b5cf6]/10', text: 'text-[#8b5cf6]', grad: 'from-[#8b5cf6] to-[#a78bfa]' },
  { bg: 'bg-[#ca8a04]/10', text: 'text-[#ca8a04]', grad: 'from-[#ca8a04] to-[#facc15]' },
  { bg: 'bg-[#ec4899]/10', text: 'text-[#ec4899]', grad: 'from-[#ec4899] to-[#f472b6]' },
  { bg: 'bg-[#0891b2]/10', text: 'text-[#0891b2]', grad: 'from-[#0891b2] to-[#06b6d4]' },
  { bg: 'bg-[#d97706]/10', text: 'text-[#d97706]', grad: 'from-[#d97706] to-[#f59e0b]' },
]

function getCatColor(idx: number) {
  return CAT_COLORS[idx % CAT_COLORS.length]
}

function getInitials(name: string): string {
  const words = name.trim().split(/\s+/)
  if (words.length >= 2) return (words[0][0] + words[1][0]).toUpperCase()
  return words[0].slice(0, 2).toUpperCase()
}

function Skeleton({ className }: { className: string }) {
  return <div className={`animate-pulse rounded-xl bg-[#f0efec] ${className}`} />
}

/* ---------- Component ---------- */

export function CategoriesPage() {
  const { user } = useAuthStore()
  const canManage = user?.role?.slug === 'owner' || user?.role?.slug === 'manager'

  const [categories, setCategories] = useState<Category[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [search, setSearch] = useState('')

  const [formOpen, setFormOpen] = useState(false)
  const [editCategory, setEditCategory] = useState<Category | null>(null)
  const [formLoading, setFormLoading] = useState(false)
  const [form, setForm] = useState({ name: '', description: '', is_active: true, parent_id: '', sort_order: 0 })
  const [formErrors, setFormErrors] = useState<Record<string, string[]>>({})
  const [allCategories, setAllCategories] = useState<Category[]>([])

  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [deleteName, setDeleteName] = useState('')
  const [deleteLoading, setDeleteLoading] = useState(false)
  const [deleteError, setDeleteError] = useState('')

  const [productCounts, setProductCounts] = useState<Record<number, number>>({})

  const fetchCategories = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params: Record<string, unknown> = { page, per_page: 20 }
      if (search) params.search = search
      const res: PaginatedResponse<Category> = await categoryService.list(params)
      setCategories(res.data)
      setLastPage(res.last_page)
      setTotal(res.total)
    } catch {
      setError('Failed to load categories')
    } finally {
      setLoading(false)
    }
  }, [page, search])

  useEffect(() => {
    const timer = setTimeout(() => { setPage(1); fetchCategories() }, 250)
    return () => clearTimeout(timer)
  }, [search])

  useEffect(() => { fetchCategories() }, [page])

  useEffect(() => {
    categoryService.all().then(setAllCategories).catch(() => {})
  }, [categories])

  // Fetch product counts per category
  useEffect(() => {
    if (categories.length === 0) return
    Promise.all(
      categories.map((c) =>
        productService.list({ category_id: c.id, per_page: 1 }).then((r) => ({ id: c.id, count: r.total })).catch(() => ({ id: c.id, count: 0 }))
      )
    ).then((results) => {
      const map: Record<number, number> = {}
      results.forEach((r) => { map[r.id] = r.count })
      setProductCounts(map)
    })
  }, [categories])

  const handleAdd = () => {
    setEditCategory(null)
    setForm({ name: '', description: '', is_active: true, parent_id: '', sort_order: 0 })
    setFormErrors({})
    setFormOpen(true)
  }

  const handleEdit = (cat: Category) => {
    setEditCategory(cat)
    setForm({
      name: cat.name,
      description: cat.description ?? '',
      is_active: cat.is_active,
      parent_id: cat.parent_id ? String(cat.parent_id) : '',
      sort_order: cat.sort_order ?? 0,
    })
    setFormErrors({})
    setFormOpen(true)
  }

  const handleFormSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setFormLoading(true)
    setFormErrors({})
    try {
      if (editCategory) {
        await categoryService.update(editCategory.id, {
          ...form,
          parent_id: form.parent_id ? parseInt(form.parent_id, 10) : null,
        })
      } else {
        await categoryService.create({
          ...form,
          parent_id: form.parent_id ? parseInt(form.parent_id, 10) : null,
        })
      }
      setFormOpen(false)
      fetchCategories()
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
      await categoryService.delete(deleteId)
      setDeleteId(null)
      fetchCategories()
    } catch (err: any) {
      setDeleteError(err.response?.data?.message ?? 'Failed to delete category')
    } finally {
      setDeleteLoading(false)
    }
  }

  const summary = useMemo(() => {
    const active = categories.filter((c) => c.is_active).length
    const withParent = categories.filter((c) => c.parent_id).length
    const totalProducts = Object.values(productCounts).reduce((a, b) => a + b, 0)
    return { active, withParent, totalProducts, inactive: categories.length - active }
  }, [categories, productCounts])

  return (
    <DashboardLayout>
      <div className="space-y-5">
        {/* ===== Header ===== */}
        <div className="animate-fade-up flex items-center justify-between">
          <div>
            <h1 className="text-[22px] font-bold tracking-tight text-[#1c1917]">Kategori</h1>
            <p className="mt-1 text-[13px] text-[#78716c]">Kelola kategori produk Anda</p>
          </div>
          {canManage && (
            <button
              onClick={handleAdd}
              className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-4 text-[13px] font-semibold text-white shadow-[0_4px_14px_rgba(245,73,39,0.3)] transition-all hover:shadow-[0_6px_20px_rgba(245,73,39,0.4)] active:scale-[0.98]"
            >
              {IC.plus} Tambah Kategori
            </button>
          )}
        </div>

        {/* ===== Summary Cards ===== */}
        <div className="animate-fade-up grid grid-cols-2 gap-4 lg:grid-cols-4" style={{ animationDelay: '0.05s' }}>
          {loading && categories.length === 0 ? (
            [...Array(4)].map((_, i) => <Skeleton key={i} className="h-[90px]" />)
          ) : (
            <>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Total Kategori</p>
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
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#16a34a]/10 text-[#16a34a]">{IC.check}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Sub-kategori</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#8b5cf6] tabular-nums">{summary.withParent}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#8b5cf6]/10 text-[#8b5cf6]">{IC.layers}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Total Produk</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#f54927] tabular-nums">{summary.totalProducts}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#f54927]/10 text-[#f54927]">{IC.package}</div>
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
              placeholder="Cari kategori..."
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
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {[...Array(6)].map((_, i) => (
              <div key={i} className="rounded-2xl border border-[#e7e5e4] bg-white p-4">
                <div className="flex items-center gap-3">
                  <Skeleton className="h-12 w-12 rounded-xl" />
                  <div className="flex-1 space-y-2">
                    <Skeleton className="h-4 w-32" />
                    <Skeleton className="h-3 w-20" />
                  </div>
                </div>
                <Skeleton className="mt-3 h-8 w-full" />
              </div>
            ))}
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center rounded-2xl border border-[#dc2626]/20 bg-[#fef2f2] py-16">
            <p className="text-[15px] font-semibold text-[#dc2626]">{error}</p>
            <button onClick={fetchCategories} className="mt-3 cursor-pointer rounded-xl border border-[#e7e5e4] bg-white px-4 py-2 text-[13px] font-medium text-[#78716c] transition-all hover:bg-[#f5f5f4]">
              Coba lagi
            </button>
          </div>
        ) : categories.length === 0 ? (
          <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-[#e7e5e4] bg-white/50 py-20">
            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-[#f5f5f4] text-[#d6d3d1]">{IC.empty}</div>
            <p className="text-[15px] font-semibold text-[#78716c]">Belum ada kategori</p>
            <p className="mt-1 text-[12px] text-[#a8a29e]">Tambahkan kategori pertama Anda</p>
            {canManage && (
              <button onClick={handleAdd} className="mt-4 flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-4 text-[13px] font-semibold text-white shadow-sm transition-all hover:shadow-md active:scale-[0.98]">
                {IC.plus} Tambah Kategori
              </button>
            )}
          </div>
        ) : (
          /* ===== Grid Cards ===== */
          <div className="animate-fade-up grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3" style={{ animationDelay: '0.15s' }}>
            {categories.map((c, idx) => {
              const color = getCatColor(idx)
              const parent = c.parent_id ? allCategories.find((p) => p.id === c.parent_id) : null
              const productCount = productCounts[c.id] ?? 0
              return (
                <div
                  key={c.id}
                  className="group flex flex-col rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all duration-200 hover:border-[#f54927]/30 hover:shadow-[0_8px_24px_rgba(245,73,39,0.08)]"
                >
                  {/* Header */}
                  <div className="flex items-start gap-3">
                    <div className={`relative flex h-12 w-12 flex-shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br ${color.grad}`}>
                      <span className="text-[15px] font-bold text-white/90">{getInitials(c.name)}</span>
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-[14px] font-bold text-[#1c1917]">{c.name}</p>
                      <div className="mt-0.5 flex items-center gap-2">
                        {parent ? (
                          <span className="flex items-center gap-1 text-[10px] text-[#a8a29e]">
                            {IC.chevron}
                            <span className="truncate">{parent.name}</span>
                          </span>
                        ) : (
                          <span className="text-[10px] text-[#a8a29e]">Top-level</span>
                        )}
                      </div>
                    </div>
                    <span className={`flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full ${c.is_active ? 'bg-[#16a34a]/10 text-[#16a34a]' : 'bg-[#dc2626]/10 text-[#dc2626]'}`}>
                      {c.is_active ? IC.check : IC.x}
                    </span>
                  </div>

                  {/* Description */}
                  {c.description && (
                    <p className="mt-3 line-clamp-2 text-[12px] leading-relaxed text-[#78716c]">{c.description}</p>
                  )}

                  {/* Stats */}
                  <div className="mt-3 flex items-center gap-3 border-t border-[#f5f5f4] pt-3">
                    <div className="flex items-center gap-1.5">
                      <span className={`flex h-7 w-7 items-center justify-center rounded-lg ${color.bg} ${color.text}`}>{IC.package}</span>
                      <span className="text-[12px] font-bold text-[#1c1917] tabular-nums">{productCount}</span>
                      <span className="text-[10px] text-[#a8a29e]">produk</span>
                    </div>
                    {c.sort_order > 0 && (
                      <div className="ml-auto flex items-center gap-1 text-[10px] text-[#a8a29e]">
                        <span>Urutan</span>
                        <span className="font-bold text-[#78716c] tabular-nums">{c.sort_order}</span>
                      </div>
                    )}
                  </div>

                  {/* Actions */}
                  {canManage && (
                    <div className="mt-3 flex gap-2">
                      <button
                        onClick={() => handleEdit(c)}
                        className="flex h-8 flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-[#e7e5e4] text-[12px] font-semibold text-[#78716c] transition-all hover:border-[#f54927]/30 hover:bg-[#f54927]/5 hover:text-[#f54927]"
                      >
                        {IC.edit} Edit
                      </button>
                      <button
                        onClick={() => { setDeleteId(c.id); setDeleteName(c.name); setDeleteError('') }}
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
        title={editCategory ? 'Edit Kategori' : 'Tambah Kategori'}
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
                  {IC.check} {editCategory ? 'Update' : 'Simpan'}
                </span>
              )}
            </Button>
          </div>
        }
      >
        <form onSubmit={handleFormSubmit} className="space-y-5">
          {/* Icon preview */}
          <div className="flex items-center gap-3 rounded-xl bg-gradient-to-r from-[#f54927]/5 to-transparent p-4">
            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-[#f54927] to-[#ff6b4a] text-white">
              {IC.tag}
            </div>
            <div>
              <p className="text-[13px] font-bold text-[#1c1917]">{form.name || 'Nama Kategori'}</p>
              <p className="text-[11px] text-[#a8a29e]">{form.is_active ? 'Active' : 'Inactive'}</p>
            </div>
          </div>

          <div className="space-y-1.5">
            <label className="flex items-center gap-1.5 text-[12px] font-semibold text-[#44403c]">
              {IC.tag} Nama Kategori <span className="text-[#f54927]">*</span>
            </label>
            <input
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              placeholder="Minuman"
              className="h-10 w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            />
            {formErrors.name && <p className="text-[11px] font-medium text-[#dc2626]">{formErrors.name[0]}</p>}
          </div>

          <div className="space-y-1.5">
            <label className="flex items-center gap-1.5 text-[12px] font-semibold text-[#44403c]">
              {IC.folder} Parent Category
            </label>
            <select
              value={form.parent_id}
              onChange={(e) => setForm({ ...form, parent_id: e.target.value })}
              className="h-10 w-full cursor-pointer rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            >
              <option value="">None (Top-level)</option>
              {allCategories.filter((c) => c.id !== editCategory?.id).map((c) => (
                <option key={c.id} value={c.id}>{c.name}</option>
              ))}
            </select>
            {formErrors.parent_id && <p className="text-[11px] font-medium text-[#dc2626]">{formErrors.parent_id[0]}</p>}
          </div>

          <div className="space-y-1.5">
            <label className="text-[12px] font-semibold text-[#44403c]">Deskripsi</label>
            <textarea
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
              placeholder="Deskripsi kategori..."
              rows={3}
              className="w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 py-2.5 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <label className="text-[12px] font-semibold text-[#44403c]">Status</label>
              <select
                value={form.is_active ? '1' : '0'}
                onChange={(e) => setForm({ ...form, is_active: e.target.value === '1' })}
                className="h-10 w-full cursor-pointer rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
              >
                <option value="1">Active</option>
                <option value="0">Inactive</option>
              </select>
            </div>
            <div className="space-y-1.5">
              <label className="text-[12px] font-semibold text-[#44403c]">Urutan</label>
              <input
                type="number"
                value={form.sort_order}
                onChange={(e) => setForm({ ...form, sort_order: parseInt(e.target.value) || 0 })}
                placeholder="0"
                className="h-10 w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
              />
            </div>
          </div>
        </form>
      </Modal>

      {/* ===== Delete Confirm ===== */}
      <ConfirmDialog
        open={deleteId !== null}
        onClose={() => setDeleteId(null)}
        onConfirm={handleDelete}
        title="Hapus Kategori?"
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
