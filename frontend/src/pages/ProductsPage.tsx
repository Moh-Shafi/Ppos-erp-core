import { useState, useEffect, useCallback } from 'react'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { PageHeader } from '@/components/common/PageHeader'
import { EmptyState, LoadingState, ErrorState } from '@/components/common/State'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Select } from '@/components/ui/Select'
import { Badge } from '@/components/ui/Badge'
import { Table, THead, TBody, TR, TH, TD } from '@/components/ui/Table'
import { Pagination } from '@/components/ui/Pagination'
import { ConfirmDialog } from '@/components/ui/Modal'
import { ProductFormModal } from '@/components/products/ProductFormModal'
import { productService } from '@/services/product'
import { categoryService } from '@/services/category'
import { formatRupiah } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth'
import type { Product, Category, PaginatedResponse } from '@/types'

export function ProductsPage() {
  const { user } = useAuthStore()
  const canManage = user?.role?.slug === 'owner' || user?.role?.slug === 'manager'

  const [products, setProducts] = useState<Product[]>([])
  const [categories, setCategories] = useState<Category[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [search, setSearch] = useState('')
  const [categoryId, setCategoryId] = useState('')
  const [isActive, setIsActive] = useState('')

  const [formOpen, setFormOpen] = useState(false)
  const [editProduct, setEditProduct] = useState<Product | null>(null)
  const [formLoading, setFormLoading] = useState(false)

  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [deleteName, setDeleteName] = useState('')
  const [deleteLoading, setDeleteLoading] = useState(false)
  const [deleteError, setDeleteError] = useState('')

  const fetchProducts = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params: Record<string, unknown> = { page, per_page: 20 }
      if (search) params.search = search
      if (categoryId) params.category_id = categoryId
      if (isActive) params.is_active = isActive
      const res: PaginatedResponse<Product> = await productService.list(params)
      setProducts(res.data)
      setLastPage(res.last_page)
      setTotal(res.total)
    } catch {
      setError('Failed to load products')
    } finally {
      setLoading(false)
    }
  }, [page, search, categoryId, isActive])

  useEffect(() => {
    categoryService.all().then(setCategories).catch(() => {})
  }, [])

  useEffect(() => {
    const timer = setTimeout(() => {
      setPage(1)
      fetchProducts()
    }, 300)
    return () => clearTimeout(timer)
  }, [search, categoryId, isActive])

  useEffect(() => {
    fetchProducts()
  }, [page])

  const handleAdd = () => {
    setEditProduct(null)
    setFormOpen(true)
  }

  const handleEdit = (product: Product) => {
    setEditProduct(product)
    setFormOpen(true)
  }

  const handleFormSubmit = async (data: {
    category_id: number
    name: string
    sku?: string
    barcode?: string
    description?: string
    cost_price: number
    selling_price: number
    unit: string
    is_active: boolean
  }) => {
    setFormLoading(true)
    try {
      if (editProduct) {
        await productService.update(editProduct.id, data)
      } else {
        await productService.create(data)
      }
      setFormOpen(false)
      fetchProducts()
    } finally {
      setFormLoading(false)
    }
  }

  const handleDelete = async () => {
    if (!deleteId) return
    setDeleteLoading(true)
    setDeleteError('')
    try {
      await productService.delete(deleteId)
      setDeleteId(null)
      fetchProducts()
    } catch (err: any) {
      const msg = err.response?.data?.message ?? 'Failed to delete product'
      setDeleteError(msg)
    } finally {
      setDeleteLoading(false)
    }
  }

  const categoryOptions = categories.map((c) => ({ value: c.id, label: c.name }))

  return (
    <DashboardLayout>
      <PageHeader
        title="Produk"
        description={`${total} produk total`}
        action={canManage && <Button onClick={handleAdd}>+ Tambah Produk</Button>}
      />

      <div className="mb-4 flex gap-3">
        <Input
          placeholder="Cari produk..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="max-w-xs"
        />
        <Select
          value={categoryId}
          onChange={(e) => setCategoryId(e.target.value)}
          options={categoryOptions}
          placeholder="Semua Kategori"
          className="max-w-xs"
        />
        <Select
          value={isActive}
          onChange={(e) => setIsActive(e.target.value)}
          options={[
            { value: '1', label: 'Active' },
            { value: '0', label: 'Inactive' },
          ]}
          placeholder="Semua Status"
          className="max-w-xs"
        />
      </div>

      {loading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={error} onRetry={fetchProducts} />
      ) : products.length === 0 ? (
        <EmptyState
          title="Belum ada produk"
          description="Tambahkan produk pertama Anda"
          action={canManage && <Button onClick={handleAdd}>+ Tambah Produk</Button>}
        />
      ) : (
        <>
          <Table>
            <THead>
              <TR>
                <TH>Product</TH>
                <TH>SKU</TH>
                <TH>Barcode</TH>
                <TH>Category</TH>
                <TH>Selling Price</TH>
                <TH>Status</TH>
                <TH>Actions</TH>
              </TR>
            </THead>
            <TBody>
              {products.map((p) => (
                <TR key={p.id}>
                  <TD className="font-medium">{p.name}</TD>
                  <TD className="text-muted-foreground">{p.sku ?? '-'}</TD>
                  <TD className="text-muted-foreground">{p.barcode ?? '-'}</TD>
                  <TD>{p.category?.name ?? '-'}</TD>
                  <TD className="font-medium">{formatRupiah(p.selling_price)}</TD>
                  <TD>
                    <Badge variant={p.is_active ? 'success' : 'danger'}>
                      {p.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                  </TD>
                  <TD>
                    <div className="flex gap-2">
                      {canManage && (
                        <>
                          <Button variant="ghost" size="sm" onClick={() => handleEdit(p)}>
                            Edit
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            className="text-destructive"
                            onClick={() => {
                              setDeleteId(p.id)
                              setDeleteName(p.name)
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

      <ProductFormModal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        onSubmit={handleFormSubmit}
        product={editProduct}
        loading={formLoading}
      />

      <ConfirmDialog
        open={deleteId !== null}
        onClose={() => setDeleteId(null)}
        onConfirm={handleDelete}
        title="Hapus Produk?"
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
