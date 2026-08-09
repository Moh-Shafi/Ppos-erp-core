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
import { purchaseReturnService } from '@/services/purchaseReturn'
import { purchaseService } from '@/services/purchase'
import { useAuthStore } from '@/stores/auth'
import { formatRupiah } from '@/lib/utils'
import type { PurchaseReturn, Purchase, PaginatedResponse } from '@/types'

const STATUS_VARIANTS: Record<string, 'default' | 'warning' | 'success' | 'danger'> = {
  draft: 'default',
  completed: 'success',
  cancelled: 'danger',
}

interface ReturnModalProps {
  open: boolean
  onClose: () => void
  onSuccess: () => void
}

function ReturnModal({ open, onClose, onSuccess }: ReturnModalProps) {
  const [purchases, setPurchases] = useState<Purchase[]>([])
  const [selectedPurchaseId, setSelectedPurchaseId] = useState('')
  const [selectedPurchase, setSelectedPurchase] = useState<Purchase | null>(null)
  const [returnDate, setReturnDate] = useState('')
  const [notes, setNotes] = useState('')
  const [items, setItems] = useState<{ product_id: string; quantity: string; unit_cost: string }[]>([
    { product_id: '', quantity: '', unit_cost: '' },
  ])
  const [loading, setLoading] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})

  useEffect(() => {
    if (!open) return
    setErrors({})
    setSelectedPurchaseId('')
    setSelectedPurchase(null)
    setReturnDate(new Date().toISOString().substring(0, 10))
    setNotes('')
    setItems([{ product_id: '', quantity: '', unit_cost: '' }])
    purchaseService.list({ per_page: 100, status: 'received' }).then((res: PaginatedResponse<Purchase>) => {
      setPurchases(res.data)
    }).catch(() => setPurchases([]))
  }, [open])

  const handlePurchaseChange = (id: string) => {
    setSelectedPurchaseId(id)
    const p = purchases.find((x) => x.id === Number(id))
    setSelectedPurchase(p ?? null)
    if (p?.items) {
      setItems(p.items.map((it) => ({
        product_id: String(it.product_id),
        quantity: '',
        unit_cost: it.unit_cost,
      })))
    } else {
      setItems([{ product_id: '', quantity: '', unit_cost: '' }])
    }
  }

  const handleSubmit = async () => {
    setLoading(true)
    setErrors({})
    try {
      await purchaseReturnService.create({
        purchase_id: Number(selectedPurchaseId),
        return_date: returnDate,
        notes: notes || undefined,
        items: items
          .filter((it) => it.product_id && it.quantity)
          .map((it) => ({
            product_id: Number(it.product_id),
            quantity: Number(it.quantity),
            unit_cost: Number(it.unit_cost),
          })),
      })
      onSuccess()
      onClose()
    } catch (err: unknown) {
      const e = err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } }
      if (e.response?.data?.errors) {
        setErrors(e.response.data.errors)
      } else if (e.response?.data?.message) {
        setErrors({ general: [e.response.data.message] })
      }
    } finally {
      setLoading(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="New Purchase Return"
      footer={
        <div className="flex gap-2">
          <Button variant="outline" onClick={onClose}>Cancel</Button>
          <Button onClick={handleSubmit} disabled={loading || !selectedPurchaseId}>
            {loading ? 'Saving...' : 'Create Return'}
          </Button>
        </div>
      }
    >
      <div className="space-y-4">
        {errors.general && <p className="text-sm text-destructive">{errors.general[0]}</p>}
        <div>
          <Label htmlFor="purchase">Purchase</Label>
          <Select
            id="purchase"
            value={selectedPurchaseId}
            onChange={(e) => handlePurchaseChange(e.target.value)}
            options={purchases.map((p) => ({ value: String(p.id), label: `${p.purchase_number} — ${p.supplier?.name ?? 'Unknown'}` }))}
            placeholder="Select received purchase"
          />
        </div>
        <div>
          <Label htmlFor="return_date">Return Date</Label>
          <Input id="return_date" type="date" value={returnDate} onChange={(e) => setReturnDate(e.target.value)} />
        </div>

        {selectedPurchase?.items && (
          <div>
            <Label>Items</Label>
            <Table>
              <THead>
                <TR>
                  <TH>Product</TH>
                  <TH>Purchased Qty</TH>
                  <TH>Return Qty</TH>
                  <TH>Unit Cost</TH>
                </TR>
              </THead>
              <TBody>
                {items.map((it, idx) => {
                  const purchaseItem = selectedPurchase.items?.find((pi) => String(pi.product_id) === it.product_id)
                  return (
                    <TR key={idx}>
                      <TD>{purchaseItem?.product?.name ?? `#${it.product_id}`}</TD>
                      <TD>{purchaseItem?.quantity}</TD>
                      <TD>
                        <Input
                          type="number"
                          value={it.quantity}
                          onChange={(e) => {
                            const next = [...items]
                            next[idx] = { ...next[idx], quantity: e.target.value }
                            setItems(next)
                          }}
                          className="w-24"
                          max={purchaseItem?.quantity}
                          min={1}
                        />
                      </TD>
                      <TD>
                        <Input
                          type="number"
                          value={it.unit_cost}
                          onChange={(e) => {
                            const next = [...items]
                            next[idx] = { ...next[idx], unit_cost: e.target.value }
                            setItems(next)
                          }}
                          className="w-28"
                        />
                      </TD>
                    </TR>
                  )
                })}
              </TBody>
            </Table>
          </div>
        )}

        <div>
          <Label htmlFor="notes">Notes</Label>
          <Input id="notes" value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="Optional notes" />
        </div>
      </div>
    </Modal>
  )
}

export function PurchaseReturnsPage() {
  const { user } = useAuthStore()
  const canManage = user?.role?.slug === 'owner' || user?.role?.slug === 'manager'

  const [returns, setReturns] = useState<PurchaseReturn[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [modalOpen, setModalOpen] = useState(false)
  const [detailReturn, setDetailReturn] = useState<PurchaseReturn | null>(null)
  const [actionLoading, setActionLoading] = useState<number | null>(null)

  const fetchReturns = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params: Record<string, string | number> = { page, per_page: 20 }
      if (search) params.search = search
      if (statusFilter) params.status = statusFilter
      const res: PaginatedResponse<PurchaseReturn> = await purchaseReturnService.list(params)
      setReturns(res.data)
      setLastPage(res.last_page)
      setTotal(res.total)
    } catch {
      setError('Failed to load purchase returns')
    } finally {
      setLoading(false)
    }
  }, [page, search, statusFilter])

  useEffect(() => {
    fetchReturns()
  }, [fetchReturns])

  const handleAction = async (r: PurchaseReturn, action: 'complete' | 'cancel') => {
    setActionLoading(r.id)
    try {
      if (action === 'complete') await purchaseReturnService.complete(r.id)
      if (action === 'cancel') await purchaseReturnService.cancel(r.id)
      fetchReturns()
      if (detailReturn?.id === r.id) {
        const updated = await purchaseReturnService.show(r.id)
        setDetailReturn(updated)
      }
    } catch {
      // ignore
    } finally {
      setActionLoading(null)
    }
  }

  const handleDelete = async (r: PurchaseReturn) => {
    try {
      await purchaseReturnService.delete(r.id)
      fetchReturns()
    } catch {
      // ignore
    }
  }

  const statusOptions = [
    { value: 'draft', label: 'Draft' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
  ]

  return (
    <DashboardLayout>
      <PageHeader
        title="Purchase Returns"
        description={`${total} returns`}
        action={canManage && <Button onClick={() => setModalOpen(true)}>New Return</Button>}
      />

      <div className="mb-4 flex gap-3">
        <Input
          placeholder="Cari return number..."
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
        <ErrorState message={error} onRetry={fetchReturns} />
      ) : returns.length === 0 ? (
        <EmptyState title="No purchase returns yet" description="Create a return for a received purchase." />
      ) : (
        <>
          <Table>
            <THead>
              <TR>
                <TH>Return Number</TH>
                <TH>Purchase</TH>
                <TH>Store</TH>
                <TH>Date</TH>
                <TH>Total</TH>
                <TH>Status</TH>
                <TH>Actions</TH>
              </TR>
            </THead>
            <TBody>
              {returns.map((r) => (
                <TR key={r.id}>
                  <TD className="font-medium">{r.return_number}</TD>
                  <TD>{r.purchase?.purchase_number ?? `#${r.purchase_id}`}</TD>
                  <TD>{r.store?.name ?? `#${r.store_id}`}</TD>
                  <TD className="text-muted-foreground">{r.return_date?.substring(0, 10)}</TD>
                  <TD className="font-medium">{formatRupiah(r.total)}</TD>
                  <TD><Badge variant={STATUS_VARIANTS[r.status]}>{r.status}</Badge></TD>
                  <TD>
                    <div className="flex gap-1">
                      <Button variant="ghost" size="sm" onClick={() => setDetailReturn(r)}>Detail</Button>
                      {canManage && r.status === 'draft' && (
                        <>
                          <Button variant="ghost" size="sm" onClick={() => handleAction(r, 'complete')} disabled={actionLoading === r.id}>Complete</Button>
                          <Button variant="ghost" size="sm" onClick={() => handleAction(r, 'cancel')} disabled={actionLoading === r.id}>Cancel</Button>
                          <Button variant="ghost" size="sm" onClick={() => handleDelete(r)}>Delete</Button>
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

      <ReturnModal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        onSuccess={fetchReturns}
      />

      <Modal
        open={detailReturn !== null}
        onClose={() => setDetailReturn(null)}
        title={detailReturn ? `Return ${detailReturn.return_number}` : ''}
        footer={<Button variant="outline" onClick={() => setDetailReturn(null)}>Close</Button>}
      >
        {detailReturn && (
          <div className="space-y-3">
            <div className="grid grid-cols-2 gap-2 text-sm">
              <div><span className="text-muted-foreground">Purchase:</span> {detailReturn.purchase?.purchase_number}</div>
              <div><span className="text-muted-foreground">Store:</span> {detailReturn.store?.name}</div>
              <div><span className="text-muted-foreground">Date:</span> {detailReturn.return_date?.substring(0, 10)}</div>
              <div><span className="text-muted-foreground">Status:</span> <Badge variant={STATUS_VARIANTS[detailReturn.status]}>{detailReturn.status}</Badge></div>
              <div><span className="text-muted-foreground">Subtotal:</span> {formatRupiah(detailReturn.subtotal)}</div>
              <div><span className="text-muted-foreground">Discount:</span> {formatRupiah(detailReturn.discount)}</div>
              <div><span className="text-muted-foreground">Tax:</span> {formatRupiah(detailReturn.tax)}</div>
              <div><span className="text-muted-foreground">Total:</span> <span className="font-semibold">{formatRupiah(detailReturn.total)}</span></div>
            </div>
            {detailReturn.notes && <p className="text-sm text-muted-foreground">{detailReturn.notes}</p>}
            <Table>
              <THead>
                <TR><TH>Product</TH><TH>Qty</TH><TH>Unit Cost</TH><TH>Total</TH></TR>
              </THead>
              <TBody>
                {detailReturn.items?.map((it) => (
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
