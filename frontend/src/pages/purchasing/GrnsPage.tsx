import { useState, useEffect, useCallback } from 'react'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { PageHeader } from '@/components/common/PageHeader'
import { EmptyState, LoadingState, ErrorState } from '@/components/common/State'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import { Badge } from '@/components/ui/Badge'
import { Modal } from '@/components/ui/Modal'
import { Table, THead, TBody, TR, TH, TD } from '@/components/ui/Table'
import { Pagination } from '@/components/ui/Pagination'
import { grnService } from '@/services/grn'
import { useAuthStore } from '@/stores/auth'
import type { GoodsReceiptNote, GrnItem } from '@/types'

const STATUS_COLORS: Record<string, 'default' | 'success' | 'danger'> = {
  draft: 'default',
  received: 'success',
  cancelled: 'danger',
}

export function GrnsPage() {
  const { user } = useAuthStore()
  const canManage = user?.role?.slug === 'owner' || user?.role?.slug === 'manager'

  const [grns, setGrns] = useState<GoodsReceiptNote[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [statusFilter, setStatusFilter] = useState('')
  const [selected, setSelected] = useState<GoodsReceiptNote | null>(null)
  const [receiveOpen, setReceiveOpen] = useState(false)
  const [receiveItems, setReceiveItems] = useState<GrnItem[]>([])
  const [receiveLoading, setReceiveLoading] = useState(false)

  const fetchGrns = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params: Record<string, unknown> = { page, per_page: 20 }
      if (statusFilter) params.status = statusFilter
      const res = await grnService.list(params)
      setGrns(res.data)
      setLastPage(res.last_page)
    } catch {
      setError('Failed to load GRNs')
    } finally {
      setLoading(false)
    }
  }, [page, statusFilter])

  useEffect(() => {
    fetchGrns()
  }, [fetchGrns])

  const handleView = async (id: number) => {
    try {
      const grn = await grnService.show(id)
      setSelected(grn)
    } catch {
      // ignore
    }
  }

  const handleReceiveOpen = () => {
    if (selected?.items) {
      setReceiveItems(selected.items.map(i => ({ ...i })))
    }
    setReceiveOpen(true)
  }

  const handleReceive = async () => {
    if (!selected) return
    setReceiveLoading(true)
    try {
      const items = receiveItems.map(i => ({
        id: i.id,
        quantity_received: i.quantity_received,
        quantity_rejected: i.quantity_rejected,
        rejection_reason: i.rejection_reason ?? undefined,
      }))
      await grnService.receive(selected.id, items)
      setReceiveOpen(false)
      const updated = await grnService.show(selected.id)
      setSelected(updated)
      fetchGrns()
    } catch {
      // ignore
    } finally {
      setReceiveLoading(false)
    }
  }

  const handleCancel = async () => {
    if (!selected) return
    try {
      await grnService.cancel(selected.id)
      const updated = await grnService.show(selected.id)
      setSelected(updated)
      fetchGrns()
    } catch {
      // ignore
    }
  }

  return (
    <DashboardLayout>
      <PageHeader title="Goods Receipt Notes" description="Manage goods receipt notes" />

      <div className="mb-4 flex gap-3">
        <select
          value={statusFilter}
          onChange={(e) => { setStatusFilter(e.target.value); setPage(1) }}
          className="rounded-md border border-input bg-background px-3 py-2 text-sm"
        >
          <option value="">All Status</option>
          <option value="draft">Draft</option>
          <option value="received">Received</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      {loading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={error} onRetry={fetchGrns} />
      ) : grns.length === 0 ? (
        <EmptyState title="No GRNs" description="No goods receipt notes found." />
      ) : (
        <>
          <Table>
            <THead>
              <TR>
                <TH>GRN Number</TH>
                <TH>Supplier</TH>
                <TH>Store</TH>
                <TH>Status</TH>
                <TH>Date</TH>
                <TH>Actions</TH>
              </TR>
            </THead>
            <TBody>
              {grns.map((g) => (
                <TR key={g.id}>
                  <TD className="font-medium">{g.grn_number}</TD>
                  <TD className="text-muted-foreground">{g.supplier?.name ?? '-'}</TD>
                  <TD className="text-muted-foreground">{g.store?.name ?? '-'}</TD>
                  <TD><Badge variant={STATUS_COLORS[g.status] ?? 'default'}>{g.status}</Badge></TD>
                  <TD className="text-xs">{g.received_date ?? new Date(g.created_at).toLocaleDateString()}</TD>
                  <TD>
                    <Button variant="ghost" size="sm" onClick={() => handleView(g.id)}>
                      View
                    </Button>
                  </TD>
                </TR>
              ))}
            </TBody>
          </Table>
          <Pagination currentPage={page} lastPage={lastPage} onPageChange={setPage} />
        </>
      )}

      <Modal
        open={selected !== null}
        onClose={() => setSelected(null)}
        title={`GRN ${selected?.grn_number ?? ''}`}
        footer={
          <>
            <Button variant="outline" onClick={() => setSelected(null)}>Close</Button>
            {canManage && selected?.status === 'draft' && (
              <>
                <Button variant="outline" onClick={handleCancel}>Cancel GRN</Button>
                <Button onClick={handleReceiveOpen}>Receive</Button>
              </>
            )}
          </>
        }
      >
        {selected && (
          <div className="space-y-4">
            <div className="flex gap-4">
              <div>
                <p className="text-xs text-muted-foreground">Status</p>
                <Badge variant={STATUS_COLORS[selected.status] ?? 'default'}>{selected.status}</Badge>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">Supplier</p>
                <p className="text-sm">{selected.supplier?.name ?? '-'}</p>
              </div>
              {selected.purchase && (
                <div>
                  <p className="text-xs text-muted-foreground">Linked PO</p>
                  <p className="text-sm">{selected.purchase.purchase_number}</p>
                </div>
              )}
            </div>
            {selected.note && <p className="text-sm text-muted-foreground">{selected.note}</p>}
            {selected.items && selected.items.length > 0 && (
              <Table>
                <THead>
                  <TR>
                    <TH>Product</TH>
                    <TH>Ordered</TH>
                    <TH>Received</TH>
                    <TH>Rejected</TH>
                    <TH>Unit Cost</TH>
                  </TR>
                </THead>
                <TBody>
                  {selected.items.map((item) => (
                    <TR key={item.id}>
                      <TD>{item.product?.name ?? `Product #${item.product_id}`}</TD>
                      <TD>{item.quantity_ordered}</TD>
                      <TD>{item.quantity_received}</TD>
                      <TD>{item.quantity_rejected}</TD>
                      <TD>{Number(item.unit_cost).toLocaleString()}</TD>
                    </TR>
                  ))}
                </TBody>
              </Table>
            )}
          </div>
        )}
      </Modal>

      <Modal
        open={receiveOpen}
        onClose={() => setReceiveOpen(false)}
        title="Receive GRN"
        footer={
          <>
            <Button variant="outline" onClick={() => setReceiveOpen(false)}>Cancel</Button>
            <Button onClick={handleReceive} disabled={receiveLoading}>
              {receiveLoading ? 'Receiving...' : 'Confirm Receipt'}
            </Button>
          </>
        }
      >
        <div className="space-y-4">
          {receiveItems.map((item, idx) => (
            <div key={item.id} className="border border-border rounded-md p-3 space-y-2">
              <p className="font-medium text-sm">{item.product?.name ?? `Product #${item.product_id}`}</p>
              <p className="text-xs text-muted-foreground">Ordered: {item.quantity_ordered}</p>
              <div className="grid grid-cols-2 gap-2">
                <div className="space-y-1">
                  <Label className="text-xs">Received Qty</Label>
                  <Input
                    type="number"
                    value={item.quantity_received}
                    onChange={(e) => {
                      const items = [...receiveItems]
                      items[idx] = { ...item, quantity_received: Number(e.target.value) }
                      setReceiveItems(items)
                    }}
                  />
                </div>
                <div className="space-y-1">
                  <Label className="text-xs">Rejected Qty</Label>
                  <Input
                    type="number"
                    value={item.quantity_rejected}
                    onChange={(e) => {
                      const items = [...receiveItems]
                      items[idx] = { ...item, quantity_rejected: Number(e.target.value) }
                      setReceiveItems(items)
                    }}
                  />
                </div>
              </div>
            </div>
          ))}
        </div>
      </Modal>
    </DashboardLayout>
  )
}
