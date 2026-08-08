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
import { customerService } from '@/services/customer'
import { useAuthStore } from '@/stores/auth'
import type { Customer, PaginatedResponse } from '@/types'

interface CustomerModalProps {
  open: boolean
  onClose: () => void
  onSuccess: () => void
  customer?: Customer | null
}

function CustomerModal({ open, onClose, onSuccess, customer }: CustomerModalProps) {
  const [name, setName] = useState('')
  const [phone, setPhone] = useState('')
  const [email, setEmail] = useState('')
  const [address, setAddress] = useState('')
  const [notes, setNotes] = useState('')
  const [isActive, setIsActive] = useState(true)
  const [loading, setLoading] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})

  useEffect(() => {
    if (open) {
      setName(customer?.name ?? '')
      setPhone(customer?.phone ?? '')
      setEmail(customer?.email ?? '')
      setAddress(customer?.address ?? '')
      setNotes(customer?.notes ?? '')
      setIsActive(customer?.is_active ?? true)
      setErrors({})
    }
  }, [open, customer])

  const handleSubmit = async () => {
    setErrors({})
    setLoading(true)
    try {
      const data = {
        name,
        phone: phone || undefined,
        email: email || undefined,
        address: address || undefined,
        notes: notes || undefined,
        is_active: isActive,
      }
      if (customer) {
        await customerService.update(customer.id, data)
      } else {
        await customerService.create(data)
      }
      onSuccess()
      onClose()
    } catch (err: any) {
      if (err.response?.data?.errors) {
        setErrors(err.response.data.errors)
      }
    } finally {
      setLoading(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={customer ? 'Edit Customer' : 'Add Customer'}
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={loading}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={loading}>
            {loading ? 'Saving...' : 'Save'}
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        <div className="space-y-1.5">
          <Label>Name *</Label>
          <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="John Doe" />
          {errors.name && <p className="text-xs text-destructive">{errors.name[0]}</p>}
        </div>
        <div className="space-y-1.5">
          <Label>Phone</Label>
          <Input value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="08123456789" />
          {errors.phone && <p className="text-xs text-destructive">{errors.phone[0]}</p>}
        </div>
        <div className="space-y-1.5">
          <Label>Email</Label>
          <Input value={email} onChange={(e) => setEmail(e.target.value)} placeholder="john@example.com" />
          {errors.email && <p className="text-xs text-destructive">{errors.email[0]}</p>}
        </div>
        <div className="space-y-1.5">
          <Label>Address</Label>
          <textarea
            value={address}
            onChange={(e) => setAddress(e.target.value)}
            placeholder="Jl. Sudirman 1"
            className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            rows={2}
          />
        </div>
        <div className="space-y-1.5">
          <Label>Notes</Label>
          <textarea
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            placeholder="VIP customer..."
            className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            rows={2}
          />
        </div>
        <div className="flex items-center gap-2">
          <input
            type="checkbox"
            id="is_active"
            checked={isActive}
            onChange={(e) => setIsActive(e.target.checked)}
            className="h-4 w-4 rounded border-input"
          />
          <Label htmlFor="is_active">Active</Label>
        </div>
      </div>
    </Modal>
  )
}

export function CustomersPage() {
  const { user } = useAuthStore()
  const canManage = user?.role?.slug === 'owner' || user?.role?.slug === 'manager' || user?.role?.slug === 'cashier'

  const [customers, setCustomers] = useState<Customer[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [search, setSearch] = useState('')
  const [modalOpen, setModalOpen] = useState(false)
  const [editCustomer, setEditCustomer] = useState<Customer | null>(null)
  const [deleteId, setDeleteId] = useState<number | null>(null)

  const fetchCustomers = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params: Record<string, unknown> = { page, per_page: 20 }
      if (search) params.search = search
      const res: PaginatedResponse<Customer> = await customerService.list(params)
      setCustomers(res.data)
      setLastPage(res.last_page)
      setTotal(res.total)
    } catch {
      setError('Failed to load customers')
    } finally {
      setLoading(false)
    }
  }, [page, search])

  useEffect(() => {
    fetchCustomers()
  }, [fetchCustomers])

  const handleEdit = (c: Customer) => {
    setEditCustomer(c)
    setModalOpen(true)
  }

  const handleAdd = () => {
    setEditCustomer(null)
    setModalOpen(true)
  }

  const handleDelete = async () => {
    if (!deleteId) return
    try {
      await customerService.delete(deleteId)
      setDeleteId(null)
      fetchCustomers()
    } catch {
      // ignore
    }
  }

  return (
    <DashboardLayout>
      <PageHeader
        title="Customers"
        description={`${total} customers`}
        action={
          canManage && (
            <Button onClick={handleAdd}>Add Customer</Button>
          )
        }
      />

      <div className="mb-4 flex gap-3">
        <Input
          placeholder="Cari nama, telepon, email..."
          value={search}
          onChange={(e) => {
            setSearch(e.target.value)
            setPage(1)
          }}
          className="max-w-xs"
        />
      </div>

      {loading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={error} onRetry={fetchCustomers} />
      ) : customers.length === 0 ? (
        <EmptyState
          title="No customers yet"
          description="Add your first customer to start tracking sales."
        />
      ) : (
        <>
          <Table>
            <THead>
              <TR>
                <TH>Name</TH>
                <TH>Phone</TH>
                <TH>Email</TH>
                <TH>Address</TH>
                <TH>Status</TH>
                <TH>Actions</TH>
              </TR>
            </THead>
            <TBody>
              {customers.map((c) => (
                <TR key={c.id}>
                  <TD className="font-medium">{c.name}</TD>
                  <TD className="text-muted-foreground">{c.phone ?? '-'}</TD>
                  <TD className="text-muted-foreground">{c.email ?? '-'}</TD>
                  <TD className="text-muted-foreground text-xs">{c.address ?? '-'}</TD>
                  <TD>
                    <Badge variant={c.is_active ? 'success' : 'danger'}>
                      {c.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                  </TD>
                  <TD>
                    <div className="flex gap-1">
                      {canManage && (
                        <>
                          <Button variant="ghost" size="sm" onClick={() => handleEdit(c)}>
                            Edit
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setDeleteId(c.id)}
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

      <CustomerModal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        onSuccess={fetchCustomers}
        customer={editCustomer}
      />

      <Modal
        open={deleteId !== null}
        onClose={() => setDeleteId(null)}
        title="Delete Customer"
        footer={
          <>
            <Button variant="outline" onClick={() => setDeleteId(null)}>
              Cancel
            </Button>
            <Button variant="destructive" onClick={handleDelete}>
              Delete
            </Button>
          </>
        }
      >
        <p className="text-sm text-muted-foreground">
          Are you sure you want to delete this customer? This action cannot be undone.
        </p>
      </Modal>
    </DashboardLayout>
  )
}
