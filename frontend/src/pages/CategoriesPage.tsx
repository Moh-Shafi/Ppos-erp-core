import { useState, useEffect, useCallback } from 'react'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { PageHeader } from '@/components/common/PageHeader'
import { EmptyState, LoadingState, ErrorState } from '@/components/common/State'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import { Badge } from '@/components/ui/Badge'
import { Table, THead, TBody, TR, TH, TD } from '@/components/ui/Table'
import { Pagination } from '@/components/ui/Pagination'
import { Modal, ConfirmDialog } from '@/components/ui/Modal'
import { categoryService } from '@/services/category'
import { useAuthStore } from '@/stores/auth'
import type { Category, PaginatedResponse } from '@/types'

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
  const [form, setForm] = useState({ name: '', description: '', is_active: true })
  const [formErrors, setFormErrors] = useState<Record<string, string[]>>({})

  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [deleteName, setDeleteName] = useState('')
  const [deleteLoading, setDeleteLoading] = useState(false)
  const [deleteError, setDeleteError] = useState('')

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
    const timer = setTimeout(() => {
      setPage(1)
      fetchCategories()
    }, 300)
    return () => clearTimeout(timer)
  }, [search])

  useEffect(() => {
    fetchCategories()
  }, [page])

  const handleAdd = () => {
    setEditCategory(null)
    setForm({ name: '', description: '', is_active: true })
    setFormErrors({})
    setFormOpen(true)
  }

  const handleEdit = (cat: Category) => {
    setEditCategory(cat)
    setForm({ name: cat.name, description: cat.description ?? '', is_active: cat.is_active })
    setFormErrors({})
    setFormOpen(true)
  }

  const handleFormSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setFormLoading(true)
    setFormErrors({})
    try {
      if (editCategory) {
        await categoryService.update(editCategory.id, form)
      } else {
        await categoryService.create(form)
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
      const msg = err.response?.data?.message ?? 'Failed to delete category'
      setDeleteError(msg)
    } finally {
      setDeleteLoading(false)
    }
  }

  return (
    <DashboardLayout>
      <PageHeader
        title="Kategori"
        description={`${total} kategori total`}
        action={canManage && <Button onClick={handleAdd}>+ Tambah Kategori</Button>}
      />

      <div className="mb-4">
        <Input
          placeholder="Cari kategori..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="max-w-xs"
        />
      </div>

      {loading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={error} onRetry={fetchCategories} />
      ) : categories.length === 0 ? (
        <EmptyState
          title="Belum ada kategori"
          description="Tambahkan kategori pertama Anda"
          action={canManage && <Button onClick={handleAdd}>+ Tambah Kategori</Button>}
        />
      ) : (
        <>
          <Table>
            <THead>
              <TR>
                <TH>Name</TH>
                <TH>Description</TH>
                <TH>Status</TH>
                <TH>Actions</TH>
              </TR>
            </THead>
            <TBody>
              {categories.map((c) => (
                <TR key={c.id}>
                  <TD className="font-medium">{c.name}</TD>
                  <TD className="text-muted-foreground">{c.description ?? '-'}</TD>
                  <TD>
                    <Badge variant={c.is_active ? 'success' : 'danger'}>
                      {c.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                  </TD>
                  <TD>
                    <div className="flex gap-2">
                      {canManage && (
                        <>
                          <Button variant="ghost" size="sm" onClick={() => handleEdit(c)}>
                            Edit
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            className="text-destructive"
                            onClick={() => {
                              setDeleteId(c.id)
                              setDeleteName(c.name)
                              setDeleteError('')
                            }}
                          >
                            Delete
                          </Button>
                        </>
                      )}
                    </div>
                  </TD>
                </TR>
              ))}
            </TBody>
          </Table>

          <Pagination currentPage={page} lastPage={lastPage} onPageChange={setPage} />
        </>
      )}

      <Modal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        title={editCategory ? 'Edit Kategori' : 'Tambah Kategori'}
        footer={
          <>
            <Button variant="outline" onClick={() => setFormOpen(false)} disabled={formLoading}>
              Cancel
            </Button>
            <Button onClick={handleFormSubmit} disabled={formLoading}>
              {formLoading ? 'Saving...' : 'Save'}
            </Button>
          </>
        }
      >
        <form onSubmit={handleFormSubmit} className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="cat-name">Nama Kategori *</Label>
            <Input
              id="cat-name"
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              placeholder="Minuman"
            />
            {formErrors.name && <p className="text-xs text-destructive">{formErrors.name[0]}</p>}
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="cat-desc">Deskripsi</Label>
            <textarea
              id="cat-desc"
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
              placeholder="Category description..."
              className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              rows={3}
            />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="cat-active">Status</Label>
            <select
              id="cat-active"
              value={form.is_active ? '1' : '0'}
              onChange={(e) => setForm({ ...form, is_active: e.target.value === '1' })}
              className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
            >
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </select>
          </div>
        </form>
      </Modal>

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
        <div className="mt-2 rounded-md bg-destructive/10 p-3 text-sm text-destructive">
          {deleteError}
        </div>
      )}
    </DashboardLayout>
  )
}
