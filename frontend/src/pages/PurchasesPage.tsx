import { useState, useEffect, useCallback } from 'react'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { PageHeader } from '@/components/common/PageHeader'
import { EmptyState, LoadingState, ErrorState } from '@/components/common/State'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import { Select } from '@/components/ui/Select'
import { Badge } from '@/components/ui/Badge'
import { Modal } from '@/components/ui/Modal'
import { Table, THead, TBody, TR, TH, TD } from '@/components/ui/Table'
import { Pagination } from '@/components/ui/Pagination'
import { purchaseService } from '@/services/purchase'
import { supplierService } from '@/services/supplier'
import { storeService } from '@/services/store'
import { productService } from '@/services/product'
import { useAuthStore } from '@/stores/auth'
import { formatRupiah } from '@/lib/utils'
import type { Purchase, Supplier, Store, Product, PaginatedResponse } from '@/types'

const STATUS_VARIANTS: Record<string, 'default' | 'warning' | 'success' | 'danger'> = {
  draft: 'default',
  ordered: 'warning',
  received: 'success',
  cancelled: 'danger',
}

interface PurchaseModalProps {
  open: boolean
  onClose: () => void
  onSuccess: () => void
  purchase?: Purchase | null
}

function PurchaseModal({ open, onClose, onSuccess, purchase }: PurchaseModalProps) {
  const [suppliers, setSuppliers] = useState<Supplier[]>([])
  const [stores, setStores] = useState<Store[]>([])
  const [products, setProducts] = useState<Product[]>([])
  const [supplierId, setSupplierId] = useState('')
  const [storeId, setStoreId] = useState('')
  const [purchaseDate, setPurchaseDate] = useState('')
  const [expectedDate, setExpectedDate] = useState('')
  const [notes, setNotes] = useState('')
  const [items, setItems] = useState<{ product_id: string; quantity: string; unit_cost: string }[]>([
    { product_id: '', quantity: '', unit_cost: '' },
  ])
  const [loading, setLoading] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})

  useEffect(() => {
    if (open) {
      supplierService.list({ per_page: 100 }).then((r) => setSuppliers(r.data)).catch(() => {})
      storeService.list().then(setStores).catch(() => {})
      productService.list({ per_page: 100 }).then((r) => setProducts(r.data)).catch(() => {})
      setSupplierId(purchase?.supplier_id ? String(purchase.supplier_id) : '')
      setStoreId(purchase?.store_id ? String(purchase.store_id) : '')
      setPurchaseDate(purchase?.purchase_date ? purchase.purchase_date.substring(0, 10) : new Date().toISOString().substring(0, 10))
      setExpectedDate(purchase?.expected_date ? purchase.expected_date.substring(0, 10) : '')
      setNotes(purchase?.notes ?? '')
      if (purchase?.items?.length) {
        setItems(purchase.items.map((i) => ({
          product_id: String(i.product_id),
          quantity: String(i.quantity),
          unit_cost: String(i.unit_cost),
        })))
      } else {
        setItems([{ product_id: '', quantity: '', unit_cost: '' }])
      }
      setErrors({})
    }
  }, [open, purchase])

  const addItem = () => setItems([...items, { product_id: '', quantity: '', unit_cost: '' }])
  const removeItem = (idx: number) => setItems(items.filter((_, i) => i !== idx))
  const updateItem = (idx: number, field: string, value: string) =>
    setItems(items.map((it, i) => (i === idx ? { ...it, [field]: value } : it)))

  const handleSubmit = async () => {
    setErrors({})
    setLoading(true)
    try {
      const data = {
        supplier_id: parseInt(supplierId, 10),
        store_id: parseInt(storeId, 10),
        purchase_date: purchaseDate,
        expected_date: expectedDate || undefined,
        notes: notes || undefined,
        items: items
          .filter((it) => it.product_id && it.quantity && it.unit_cost)
          .map((it) => ({
            product_id: parseInt(it.product_id, 10),
            quantity: parseInt(it.quantity, 10),
            unit_cost: parseFloat(it.unit_cost),
          })),
      }
      if (purchase) {
        await purchaseService.update(purchase.id, data)
      } else {
        await purchaseService.create(data)
      }
      onSuccess()
      onClose()
    } catch (err: any) {
      if (err.response?.data?.errors) {
        setErrors(err.response.data.errors)
      } else if (err.response?.data?.message) {
        setErrors({ items: [err.response.data.message] })
      }
    } finally {
      setLoading(false)
    }
  }

  const supplierOptions = suppliers.map((s) => ({ value: s.id, label: s.name }))
  const storeOptions = stores.map((s) => ({ value: s.id, label: s.name }))
  const productOptions = products.map((p) => ({ value: p.id, label: `${p.name} (${p.sku})` }))

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={purchase ? 'Edit Purchase' : 'New Purchase'}
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={loading}>Cancel</Button>
          <Button onClick={handleSubmit} disabled={loading}>
            {loading ? 'Saving...' : 'Save'}
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-1.5">
            <Label>Supplier *</Label>
            <Select value={supplierId} onChange={(e) => setSupplierId(e.target.value)} options={supplierOptions} placeholder="Pilih supplier" />
            {errors.supplier_id && <p className="text-xs text-destructive">{errors.supplier_id[0]}</p>}
          </div>
          <div className="space-y-1.5">
            <Label>Store *</Label>
            <Select value={storeId} onChange={(e) => setStoreId(e.target.value)} options={storeOptions} placeholder="Pilih toko" />
            {errors.store_id && <p className="text-xs text-destructive">{errors.store_id[0]}</p>}
          </div>
        </div>
        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-1.5">
            <Label>Purchase Date *</Label>
            <Input type="date" value={purchaseDate} onChange={(e) => setPurchaseDate(e.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label>Expected Date</Label>
            <Input type="date" value={expectedDate} onChange={(e) => setExpectedDate(e.target.value)} />
          </div>
        </div>

        <div className="space-y-2">
          <Label>Items *</Label>
          {items.map((item, idx) => (
            <div key={idx} className="flex gap-2 items-end">
              <Select
                value={item.product_id}
                onChange={(e) => updateItem(idx, 'product_id', e.target.value)}
                options={productOptions}
                placeholder="Produk"
                className="flex-1"
              />
              <Input
                type="number"
                value={item.quantity}
                onChange={(e) => updateItem(idx, 'quantity', e.target.value)}
                placeholder="Qty"
                className="w-20"
              />
              <Input
                type="number"
                value={item.unit_cost}
                onChange={(e) => updateItem(idx, 'unit_cost', e.target.value)}
                placeholder="Cost"
                className="w-28"
              />
              {items.length > 1 && (
                <Button variant="ghost" size="sm" onClick={() => removeItem(idx)}>×</Button>
              )}
            </div>
          ))}
          <Button variant="outline" size="sm" onClick={addItem}>+ Add Item</Button>
          {errors.items && <p className="text-xs text-destructive">{errors.items[0]}</p>}
        </div>

        <div className="space-y-1.5">
          <Label>Notes</Label>
          <textarea
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            placeholder="Purchase notes..."
            className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            rows={2}
          />
        </div>
      </div>
    </Modal>
  )
}

export function PurchasesPage() {
  const { user } = useAuthStore()
  const canManage = user?.role?.slug === 'owner' || user?.role?.slug === 'manager'

  const [purchases, setPurchases] = useState<Purchase[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [modalOpen, setModalOpen] = useState(false)
  const [editPurchase, setEditPurchase] = useState<Purchase | null>(null)
  const [detailPurchase, setDetailPurchase] = useState<Purchase | null>(null)
  const [actionLoading, setActionLoading] = useState<number | null>(null)

  const fetchPurchases = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params: Record<string, unknown> = { page, per_page: 20 }
      if (search) params.search = search
      if (statusFilter) params.status = statusFilter
      const res: PaginatedResponse<Purchase> = await purchaseService.list(params)
      setPurchases(res.data)
      setLastPage(res.last_page)
      setTotal(res.total)
    } catch {
      setError('Failed to load purchases')
    } finally {
      setLoading(false)
    }
  }, [page, search, statusFilter])

  useEffect(() => {
    fetchPurchases()
  }, [fetchPurchases])

  const handleEdit = (p: Purchase) => {
    setEditPurchase(p)
    setModalOpen(true)
  }

  const handleAdd = () => {
    setEditPurchase(null)
    setModalOpen(true)
  }

  const handleAction = async (p: Purchase, action: 'order' | 'receive' | 'cancel') => {
    setActionLoading(p.id)
    try {
      if (action === 'order') await purchaseService.order(p.id)
      if (action === 'receive') await purchaseService.receive(p.id)
      if (action === 'cancel') await purchaseService.cancel(p.id)
      fetchPurchases()
      if (detailPurchase?.id === p.id) {
        const updated = await purchaseService.show(p.id)
        setDetailPurchase(updated)
      }
    } catch {
      // ignore
    } finally {
      setActionLoading(null)
    }
  }

  const handleDelete = async (p: Purchase) => {
    try {
      await purchaseService.delete(p.id)
      fetchPurchases()
    } catch {
      // ignore
    }
  }

  const statusOptions = [
    { value: 'draft', label: 'Draft' },
    { value: 'ordered', label: 'Ordered' },
    { value: 'received', label: 'Received' },
    { value: 'cancelled', label: 'Cancelled' },
  ]

  return (
    <DashboardLayout>
      <PageHeader
        title="Purchases"
        description={`${total} purchases`}
        action={canManage && <Button onClick={handleAdd}>New Purchase</Button>}
      />

      <div className="mb-4 flex gap-3">
        <Input
          placeholder="Cari purchase number..."
          value={search}
          onChange={(e) => { setSearch(e.target.value); setPage(1) }}
          className="max-w-xs"
        />
        <Select
          value={statusFilter}
          onChange={(e) => { setStatusFilter(e.target.value); setPage(1) }}
          options={statusOptions}
          placeholder="Semua status"
          className="max-w-xs"
        />
      </div>

      {loading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={error} onRetry={fetchPurchases} />
      ) : purchases.length === 0 ? (
        <EmptyState title="No purchases yet" description="Create your first purchase order." />
      ) : (
        <>
          <Table>
            <THead>
              <TR>
                <TH>PO Number</TH>
                <TH>Supplier</TH>
                <TH>Store</TH>
                <TH>Date</TH>
                <TH>Total</TH>
                <TH>Status</TH>
                <TH>Actions</TH>
              </TR>
            </THead>
            <TBody>
              {purchases.map((p) => (
                <TR key={p.id}>
                  <TD className="font-medium">{p.purchase_number}</TD>
                  <TD>{p.supplier?.name ?? `#${p.supplier_id}`}</TD>
                  <TD>{p.store?.name ?? `#${p.store_id}`}</TD>
                  <TD className="text-muted-foreground">{p.purchase_date?.substring(0, 10)}</TD>
                  <TD className="font-medium">{formatRupiah(p.total)}</TD>
                  <TD><Badge variant={STATUS_VARIANTS[p.status]}>{p.status}</Badge></TD>
                  <TD>
                    <div className="flex gap-1">
                      <Button variant="ghost" size="sm" onClick={() => setDetailPurchase(p)}>Detail</Button>
                      {canManage && p.status === 'draft' && (
                        <>
                          <Button variant="ghost" size="sm" onClick={() => handleEdit(p)}>Edit</Button>
                          <Button variant="ghost" size="sm" onClick={() => handleAction(p, 'order')} disabled={actionLoading === p.id}>Order</Button>
                          <Button variant="ghost" size="sm" onClick={() => handleDelete(p)}>Delete</Button>
                        </>
                      )}
                      {canManage && p.status === 'ordered' && (
                        <>
                          <Button variant="ghost" size="sm" onClick={() => handleAction(p, 'receive')} disabled={actionLoading === p.id}>Receive</Button>
                          <Button variant="ghost" size="sm" onClick={() => handleAction(p, 'cancel')} disabled={actionLoading === p.id}>Cancel</Button>
                        </>
                      )}
                      {canManage && p.status === 'draft' && (
                        <Button variant="ghost" size="sm" onClick={() => handleAction(p, 'cancel')} disabled={actionLoading === p.id}>Cancel</Button>
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

      <PurchaseModal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        onSuccess={fetchPurchases}
        purchase={editPurchase}
      />

      <Modal
        open={detailPurchase !== null}
        onClose={() => setDetailPurchase(null)}
        title={detailPurchase ? `Purchase ${detailPurchase.purchase_number}` : ''}
        footer={<Button variant="outline" onClick={() => setDetailPurchase(null)}>Close</Button>}
      >
        {detailPurchase && (
          <div className="space-y-3">
            <div className="grid grid-cols-2 gap-2 text-sm">
              <div><span className="text-muted-foreground">Supplier:</span> {detailPurchase.supplier?.name}</div>
              <div><span className="text-muted-foreground">Store:</span> {detailPurchase.store?.name}</div>
              <div><span className="text-muted-foreground">Date:</span> {detailPurchase.purchase_date?.substring(0, 10)}</div>
              <div><span className="text-muted-foreground">Status:</span> <Badge variant={STATUS_VARIANTS[detailPurchase.status]}>{detailPurchase.status}</Badge></div>
              <div><span className="text-muted-foreground">Subtotal:</span> {formatRupiah(detailPurchase.subtotal)}</div>
              <div><span className="text-muted-foreground">Discount:</span> {formatRupiah(detailPurchase.discount)}</div>
              <div><span className="text-muted-foreground">Tax:</span> {formatRupiah(detailPurchase.tax)}</div>
              <div><span className="text-muted-foreground">Total:</span> <span className="font-semibold">{formatRupiah(detailPurchase.total)}</span></div>
            </div>
            {detailPurchase.notes && <p className="text-sm text-muted-foreground">{detailPurchase.notes}</p>}
            <Table>
              <THead>
                <TR><TH>Product</TH><TH>Qty</TH><TH>Unit Cost</TH><TH>Total</TH></TR>
              </THead>
              <TBody>
                {detailPurchase.items?.map((it) => (
                  <TR key={it.id}>
                    <TD>{it.product?.name ?? `#${it.product_id}`}</TD>
                    <TD>{it.quantity}</TD>
                    <TD>{formatRupiah(it.unit_cost)}</TD>
                    <TD>{formatRupiah(it.total)}</TD>
                  </TR>
                ))}
              </TBody>
            </Table>
          </div>
        )}
      </Modal>
    </DashboardLayout>
  )
}
