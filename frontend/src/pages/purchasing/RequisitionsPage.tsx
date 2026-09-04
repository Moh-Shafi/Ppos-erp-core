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
import { requisitionService } from '@/services/requisition'
import { useAuthStore } from '@/stores/auth'
import type { PurchaseRequisition } from '@/types'

const STATUS_COLORS: Record<string, 'default' | 'warning' | 'success' | 'danger'> = {
  draft: 'default',
  pending: 'warning',
  approved: 'success',
  rejected: 'danger',
  cancelled: 'default',
}

export function RequisitionsPage() {
  const { user } = useAuthStore()
  const canManage = user?.role?.slug === 'owner' || user?.role?.slug === 'manager'
  const canApprove = user?.role?.slug === 'owner' || user?.role?.slug === 'manager'
  const [requisitions, setRequisitions] = useState<PurchaseRequisition[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [statusFilter, setStatusFilter] = useState('')
  const [selected, setSelected] = useState<PurchaseRequisition | null>(null)
  const [actionLoading, setActionLoading] = useState(false)
  const [rejectOpen, setRejectOpen] = useState(false)
  const [rejectReason, setRejectReason] = useState('')

  const fetchRequisitions = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params: Record<string, unknown> = { page, per_page: 20 }
      if (statusFilter) params.status = statusFilter
      const res = await requisitionService.list(params)
      setRequisitions(res.data)
      setLastPage(res.last_page)
    } catch {
      setError('Failed to load requisitions')
    } finally {
      setLoading(false)
    }
  }, [page, statusFilter])

  useEffect(() => {
    fetchRequisitions()
  }, [fetchRequisitions])

  const handleAction = async (action: string, id: number) => {
    setActionLoading(true)
    try {
      switch (action) {
        case 'submit': await requisitionService.submit(id); break
        case 'approve': await requisitionService.approve(id); break
        case 'cancel': await requisitionService.cancel(id); break
      }
      if (selected?.id === id) {
        const updated = await requisitionService.show(id)
        setSelected(updated)
      }
      fetchRequisitions()
    } catch {
      // ignore
    } finally {
      setActionLoading(false)
    }
  }

  const handleReject = async () => {
    if (!selected) return
    setActionLoading(true)
    try {
      await requisitionService.reject(selected.id, rejectReason)
      setRejectOpen(false)
      setRejectReason('')
      const updated = await requisitionService.show(selected.id)
      setSelected(updated)
      fetchRequisitions()
    } catch {
      // ignore
    } finally {
      setActionLoading(false)
    }
  }

  return (
    <DashboardLayout>
      <PageHeader title="Purchase Requisitions" description="Manage purchase requisitions" />

      <div className="mb-4 flex gap-3">
        <select
          value={statusFilter}
          onChange={(e) => { setStatusFilter(e.target.value); setPage(1) }}
          className="rounded-md border border-input bg-background px-3 py-2 text-sm"
        >
          <option value="">All Status</option>
          <option value="draft">Draft</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      {loading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={error} onRetry={fetchRequisitions} />
      ) : requisitions.length === 0 ? (
        <EmptyState title="No requisitions" description="No purchase requisitions found." />
      ) : (
        <>
          <Table>
            <THead>
              <TR>
                <TH>Number</TH>
                <TH>Store</TH>
                <TH>Status</TH>
                <TH>Requested By</TH>
                <TH>Date</TH>
                <TH>Actions</TH>
              </TR>
            </THead>
            <TBody>
              {requisitions.map((r) => (
                <TR key={r.id}>
                  <TD className="font-medium">{r.request_number}</TD>
                  <TD className="text-muted-foreground">{r.store?.name ?? '-'}</TD>
                  <TD><Badge variant={STATUS_COLORS[r.status] ?? 'default'}>{r.status}</Badge></TD>
                  <TD className="text-muted-foreground">{r.requestedBy?.name ?? '-'}</TD>
                  <TD className="text-xs">{new Date(r.created_at).toLocaleDateString()}</TD>
                  <TD>
                    <Button variant="ghost" size="sm" onClick={() => setSelected(r)}>
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
        title={`Requisition ${selected?.request_number ?? ''}`}
        footer={
          <>
            <Button variant="outline" onClick={() => setSelected(null)}>Close</Button>
            {canManage && selected?.status === 'draft' && (
              <>
                <Button variant="outline" onClick={() => handleAction('cancel', selected.id)} disabled={actionLoading}>Cancel</Button>
                <Button onClick={() => handleAction('submit', selected.id)} disabled={actionLoading}>Submit</Button>
              </>
            )}
            {canApprove && selected?.status === 'pending' && (
              <>
                <Button variant="outline" onClick={() => setRejectOpen(true)} disabled={actionLoading}>Reject</Button>
                <Button onClick={() => handleAction('approve', selected.id)} disabled={actionLoading}>Approve</Button>
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
                <p className="text-xs text-muted-foreground">Store</p>
                <p className="text-sm">{selected.store?.name ?? '-'}</p>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">Requested By</p>
                <p className="text-sm">{selected.requestedBy?.name ?? '-'}</p>
              </div>
            </div>
            {selected.note && <p className="text-sm text-muted-foreground">{selected.note}</p>}
            {selected.rejection_reason && (
              <p className="text-sm text-destructive">Rejection: {selected.rejection_reason}</p>
            )}
            {selected.items && selected.items.length > 0 && (
              <Table>
                <THead>
                  <TR>
                    <TH>Product</TH>
                    <TH>Qty</TH>
                    <TH>Est. Cost</TH>
                  </TR>
                </THead>
                <TBody>
                  {selected.items.map((item) => (
                    <TR key={item.id}>
                      <TD>{item.product?.name ?? `Product #${item.product_id}`}</TD>
                      <TD>{item.quantity}</TD>
                      <TD>{item.estimated_cost ? Number(item.estimated_cost).toLocaleString() : '-'}</TD>
                    </TR>
                  ))}
                </TBody>
              </Table>
            )}
          </div>
        )}
      </Modal>

      <Modal
        open={rejectOpen}
        onClose={() => setRejectOpen(false)}
        title="Reject Requisition"
        footer={
          <>
            <Button variant="outline" onClick={() => setRejectOpen(false)}>Cancel</Button>
            <Button variant="destructive" onClick={handleReject} disabled={actionLoading}>
              {actionLoading ? 'Rejecting...' : 'Reject'}
            </Button>
          </>
        }
      >
        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label>Rejection Reason</Label>
            <Input
              value={rejectReason}
              onChange={(e) => setRejectReason(e.target.value)}
              placeholder="Reason for rejection..."
            />
          </div>
        </div>
      </Modal>
    </DashboardLayout>
  )
}
