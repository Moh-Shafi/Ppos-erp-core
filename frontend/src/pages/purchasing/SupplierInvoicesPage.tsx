import { useState, useEffect, useCallback } from 'react'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { PageHeader } from '@/components/common/PageHeader'
import { EmptyState, LoadingState, ErrorState } from '@/components/common/State'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Modal } from '@/components/ui/Modal'
import { Table, THead, TBody, TR, TH, TD } from '@/components/ui/Table'
import { Pagination } from '@/components/ui/Pagination'
import { supplierInvoiceService } from '@/services/supplierInvoice'
import { useAuthStore } from '@/stores/auth'
import type { SupplierInvoice } from '@/types'

const STATUS_COLORS: Record<string, 'default' | 'warning' | 'success' | 'danger'> = {
  pending: 'default',
  matched: 'success',
  mismatched: 'warning',
  approved: 'success',
  rejected: 'danger',
}

export function SupplierInvoicesPage() {
  const { user } = useAuthStore()
  const canManage = user?.role?.slug === 'owner' || user?.role?.slug === 'manager' || user?.role?.slug === 'accountant'

  const [invoices, setInvoices] = useState<SupplierInvoice[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [statusFilter, setStatusFilter] = useState('')
  const [selected, setSelected] = useState<SupplierInvoice | null>(null)
  const [actionLoading, setActionLoading] = useState(false)
  const [rejectOpen, setRejectOpen] = useState(false)
  const [rejectReason, setRejectReason] = useState('')

  const fetchInvoices = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params: Record<string, unknown> = { page, per_page: 20 }
      if (statusFilter) params.status = statusFilter
      const res = await supplierInvoiceService.list(params)
      setInvoices(res.data)
      setLastPage(res.last_page)
    } catch {
      setError('Failed to load invoices')
    } finally {
      setLoading(false)
    }
  }, [page, statusFilter])

  useEffect(() => {
    fetchInvoices()
  }, [fetchInvoices])

  const handleView = async (id: number) => {
    try {
      const inv = await supplierInvoiceService.show(id)
      setSelected(inv)
    } catch {
      // ignore
    }
  }

  const handleMatch = async () => {
    if (!selected) return
    setActionLoading(true)
    try {
      await supplierInvoiceService.match(selected.id)
      const updated = await supplierInvoiceService.show(selected.id)
      setSelected(updated)
      fetchInvoices()
    } catch {
      // ignore
    } finally {
      setActionLoading(false)
    }
  }

  const handleApprove = async () => {
    if (!selected) return
    setActionLoading(true)
    try {
      await supplierInvoiceService.approve(selected.id)
      const updated = await supplierInvoiceService.show(selected.id)
      setSelected(updated)
      fetchInvoices()
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
      await supplierInvoiceService.reject(selected.id, rejectReason)
      setRejectOpen(false)
      setRejectReason('')
      const updated = await supplierInvoiceService.show(selected.id)
      setSelected(updated)
      fetchInvoices()
    } catch {
      // ignore
    } finally {
      setActionLoading(false)
    }
  }

  return (
    <DashboardLayout>
      <PageHeader title="Supplier Invoices" description="3-way matching for supplier invoices" />

      <div className="mb-4 flex gap-3">
        <select
          value={statusFilter}
          onChange={(e) => { setStatusFilter(e.target.value); setPage(1) }}
          className="rounded-md border border-input bg-background px-3 py-2 text-sm"
        >
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="matched">Matched</option>
          <option value="mismatched">Mismatched</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>

      {loading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={error} onRetry={fetchInvoices} />
      ) : invoices.length === 0 ? (
        <EmptyState title="No invoices" description="No supplier invoices found." />
      ) : (
        <>
          <Table>
            <THead>
              <TR>
                <TH>Invoice #</TH>
                <TH>Supplier</TH>
                <TH>Total</TH>
                <TH>Status</TH>
                <TH>Date</TH>
                <TH>Actions</TH>
              </TR>
            </THead>
            <TBody>
              {invoices.map((inv) => (
                <TR key={inv.id}>
                  <TD className="font-medium">{inv.invoice_number}</TD>
                  <TD className="text-muted-foreground">{inv.supplier?.name ?? '-'}</TD>
                  <TD>{Number(inv.total).toLocaleString()}</TD>
                  <TD><Badge variant={STATUS_COLORS[inv.status] ?? 'default'}>{inv.status}</Badge></TD>
                  <TD className="text-xs">{new Date(inv.invoice_date).toLocaleDateString()}</TD>
                  <TD>
                    <Button variant="ghost" size="sm" onClick={() => handleView(inv.id)}>
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
        title={`Invoice ${selected?.invoice_number ?? ''}`}
        footer={
          <>
            <Button variant="outline" onClick={() => setSelected(null)}>Close</Button>
            {canManage && selected?.status === 'pending' && (
              <Button onClick={handleMatch} disabled={actionLoading}>
                {actionLoading ? 'Matching...' : 'Run 3-Way Match'}
              </Button>
            )}
            {canManage && (selected?.status === 'matched' || selected?.status === 'mismatched') && (
              <>
                <Button variant="outline" onClick={() => setRejectOpen(true)} disabled={actionLoading}>Reject</Button>
                <Button onClick={handleApprove} disabled={actionLoading}>Approve</Button>
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
              <div>
                <p className="text-xs text-muted-foreground">Total</p>
                <p className="text-sm font-medium">{Number(selected.total).toLocaleString()}</p>
              </div>
            </div>
            {selected.match_result && (
              <div className="border border-border rounded-md p-3 bg-muted/30">
                <p className="text-xs font-medium mb-2">Match Result</p>
                <pre className="text-xs whitespace-pre-wrap">
                  {JSON.stringify(selected.match_result, null, 2)}
                </pre>
              </div>
            )}
            {selected.rejection_reason && (
              <p className="text-sm text-destructive">Rejection: {selected.rejection_reason}</p>
            )}
          </div>
        )}
      </Modal>

      <Modal
        open={rejectOpen}
        onClose={() => setRejectOpen(false)}
        title="Reject Invoice"
        footer={
          <>
            <Button variant="outline" onClick={() => setRejectOpen(false)}>Cancel</Button>
            <Button variant="destructive" onClick={handleReject} disabled={actionLoading}>
              {actionLoading ? 'Rejecting...' : 'Reject'}
            </Button>
          </>
        }
      >
        <p className="text-sm text-muted-foreground">Reason for rejection:</p>
        <textarea
          value={rejectReason}
          onChange={(e) => setRejectReason(e.target.value)}
          className="mt-2 flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
          rows={3}
          placeholder="Enter rejection reason..."
        />
      </Modal>
    </DashboardLayout>
  )
}
