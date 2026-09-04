import { useState, useEffect, useCallback } from 'react'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { PageHeader } from '@/components/common/PageHeader'
import { EmptyState, LoadingState, ErrorState } from '@/components/common/State'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import { Badge } from '@/components/ui/Badge'
import { Table, THead, TBody, TR, TH, TD } from '@/components/ui/Table'
import { Modal, ConfirmDialog } from '@/components/ui/Modal'
import { adjustmentReasonService } from '@/services/adjustmentReason'
import { useAuthStore } from '@/stores/auth'
import type { StockAdjustmentReason } from '@/types'

const CATEGORIES = ['damaged', 'lost', 'found', 'recount', 'initial', 'other']

export function AdjustmentReasonsPage() {
  const { user } = useAuthStore()
  const canManage = user?.role?.slug === 'owner' || user?.role?.slug === 'manager'

  const [reasons, setReasons] = useState<StockAdjustmentReason[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [categoryFilter, setCategoryFilter] = useState('')

  const [formOpen, setFormOpen] = useState(false)
  const [editReason, setEditReason] = useState<StockAdjustmentReason | null>(null)
  const [formLoading, setFormLoading] = useState(false)
  const [form, setForm] = useState({ name: '', category: 'damaged', is_active: true })
  const [formErrors, setFormErrors] = useState<Record<string, string[]>>({})

  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [deleteName, setDeleteName] = useState('')
  const [deleteLoading, setDeleteLoading] = useState(false)
  const [deleteError, setDeleteError] = useState('')

  const fetchReasons = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params: Record<string, unknown> = {}
      if (categoryFilter) params.category = categoryFilter
      const data = await adjustmentReasonService.list(params)
      setReasons(data)
    } catch {
      setError('Failed to load adjustment reasons')
    } finally {
      setLoading(false)
    }
  }, [categoryFilter])

  useEffect(() => {
    fetchReasons()
  }, [categoryFilter])

  const handleAdd = () => {
    setEditReason(null)
    setForm({ name: '', category: 'damaged', is_active: true })
    setFormErrors({})
    setFormOpen(true)
  }

  const handleEdit = (reason: StockAdjustmentReason) => {
    setEditReason(reason)
    setForm({ name: reason.name, category: reason.category, is_active: reason.is_active })
    setFormErrors({})
    setFormOpen(true)
  }

  const handleFormSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setFormLoading(true)
    setFormErrors({})
    try {
      if (editReason) {
        await adjustmentReasonService.update(editReason.id, {
          name: form.name,
          is_active: form.is_active,
        })
      } else {
        await adjustmentReasonService.create(form)
      }
      setFormOpen(false)
      fetchReasons()
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
      await adjustmentReasonService.delete(deleteId)
      setDeleteId(null)
      fetchReasons()
    } catch (err: any) {
      const msg = err.response?.data?.message ?? 'Failed to delete reason'
      setDeleteError(msg)
    } finally {
      setDeleteLoading(false)
    }
  }

  return (
    <DashboardLayout>
      <PageHeader
        title="Adjustment Reasons"
        description={`${reasons.length} reason(s) total`}
        action={canManage && <Button onClick={handleAdd}>+ Add Reason</Button>}
      />

      <div className="mb-4">
        <select
          value={categoryFilter}
          onChange={(e) => setCategoryFilter(e.target.value)}
          className="flex h-10 w-48 rounded-md border border-input bg-background px-3 py-2 text-sm"
        >
          <option value="">All Categories</option>
          {CATEGORIES.map((c) => (
            <option key={c} value={c}>{c}</option>
          ))}
        </select>
      </div>

      {loading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={error} onRetry={fetchReasons} />
      ) : reasons.length === 0 ? (
        <EmptyState
          title="No adjustment reasons"
          description="Add your first adjustment reason"
          action={canManage && <Button onClick={handleAdd}>+ Add Reason</Button>}
        />
      ) : (
        <Table>
          <THead>
            <TR>
              <TH>Name</TH>
              <TH>Category</TH>
              <TH>Type</TH>
              <TH>Status</TH>
              <TH>Actions</TH>
            </TR>
          </THead>
          <TBody>
            {reasons.map((r) => (
              <TR key={r.id}>
                <TD className="font-medium">{r.name}</TD>
                <TD className="text-muted-foreground">{r.category}</TD>
                <TD>
                  <Badge variant={r.is_system ? 'default' : 'success'}>
                    {r.is_system ? 'System' : 'Custom'}
                  </Badge>
                </TD>
                <TD>
                  <Badge variant={r.is_active ? 'success' : 'danger'}>
                    {r.is_active ? 'Active' : 'Inactive'}
                  </Badge>
                </TD>
                <TD>
                  <div className="flex gap-2">
                    {canManage && (
                      <>
                        <Button variant="ghost" size="sm" onClick={() => handleEdit(r)}>
                          Edit
                        </Button>
                        {!r.is_system && (
                          <Button
                            variant="ghost"
                            size="sm"
                            className="text-destructive"
                            onClick={() => {
                              setDeleteId(r.id)
                              setDeleteName(r.name)
                              setDeleteError('')
                            }}
                          >
                            Delete
                          </Button>
                        )}
                      </>
                    )}
                  </div>
                </TD>
              </TR>
            ))}
          </TBody>
        </Table>
      )}

      <Modal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        title={editReason ? 'Edit Reason' : 'Add Reason'}
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
            <Label htmlFor="reason-name">Name *</Label>
            <Input
              id="reason-name"
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              placeholder="Damaged Goods"
              disabled={editReason?.is_system}
            />
            {formErrors.name && <p className="text-xs text-destructive">{formErrors.name[0]}</p>}
          </div>
          {!editReason && (
            <div className="space-y-1.5">
              <Label htmlFor="reason-category">Category *</Label>
              <select
                id="reason-category"
                value={form.category}
                onChange={(e) => setForm({ ...form, category: e.target.value })}
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              >
                {CATEGORIES.map((c) => (
                  <option key={c} value={c}>{c}</option>
                ))}
              </select>
            </div>
          )}
          <div className="space-y-1.5">
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={form.is_active}
                onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                className="rounded border-input"
              />
              Active
            </label>
          </div>
        </form>
      </Modal>

      <ConfirmDialog
        open={deleteId !== null}
        onClose={() => setDeleteId(null)}
        onConfirm={handleDelete}
        title="Delete Reason?"
        message={`Are you sure you want to delete "${deleteName}"?`}
        confirmLabel="Delete"
        cancelLabel="Cancel"
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
