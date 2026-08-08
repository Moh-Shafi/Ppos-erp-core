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
import { supplierService } from '@/services/supplier'
import { useAuthStore } from '@/stores/auth'
import type { Supplier, PaginatedResponse } from '@/types'

interface SupplierModalProps {
  open: boolean
  onClose: () => void
  onSuccess: () => void
  supplier?: Supplier | null
}

function SupplierModal({ open, onClose, onSuccess, supplier }: SupplierModalProps) {
  const [name, setName] = useState('')
  const [contactPerson, setContactPerson] = useState('')
  const [phone, setPhone] = useState('')
  const [email, setEmail] = useState('')
  const [address, setAddress] = useState('')
  const [taxNumber, setTaxNumber] = useState('')
  const [notes, setNotes] = useState('')
  const [isActive, setIsActive] = useState(true)
  const [loading, setLoading] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})

  useEffect(() => {
    if (open) {
      setName(supplier?.name ?? '')
      setContactPerson(supplier?.contact_person ?? '')
      setPhone(supplier?.phone ?? '')
      setEmail(supplier?.email ?? '')
      setAddress(supplier?.address ?? '')
      setTaxNumber(supplier?.tax_number ?? '')
      setNotes(supplier?.notes ?? '')
      setIsActive(supplier?.is_active ?? true)
      setErrors({})
    }
  }, [open, supplier])

  const handleSubmit = async () => {
    setErrors({})
    setLoading(true)
    try {
      const data = {
        name,
        contact_person: contactPerson || undefined,
        phone: phone || undefined,
        email: email || undefined,
        address: address || undefined,
        tax_number: taxNumber || undefined,
        notes: notes || undefined,
        is_active: isActive,
      }
      if (supplier) {
        await supplierService.update(supplier.id, data)
      } else {
        await supplierService.create(data)
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
      title={supplier ? 'Edit Supplier' : 'Add Supplier'}
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
          <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="PT Supplier Jaya" />
          {errors.name && <p className="text-xs text-destructive">{errors.name[0]}</p>}
        </div>
        <div className="space-y-1.5">
          <Label>Contact Person</Label>
          <Input value={contactPerson} onChange={(e) => setContactPerson(e.target.value)} placeholder="Budi" />
        </div>
        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-1.5">
            <Label>Phone</Label>
            <Input value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="0211234567" />
          </div>
          <div className="space-y-1.5">
            <Label>Email</Label>
            <Input value={email} onChange={(e) => setEmail(e.target.value)} placeholder="budi@jaya.com" />
            {errors.email && <p className="text-xs text-destructive">{errors.email[0]}</p>}
          </div>
        </div>
        <div className="space-y-1.5">
          <Label>Tax Number (NPWP)</Label>
          <Input value={taxNumber} onChange={(e) => setTaxNumber(e.target.value)} placeholder="01.234.567.8-901" />
        </div>
        <div className="space-y-1.5">
          <Label>Address</Label>
          <textarea
            value={address}
            onChange={(e) => setAddress(e.target.value)}
            placeholder="Jl. Industri 1"
            className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            rows={2}
          />
        </div>
        <div className="space-y-1.5">
          <Label>Notes</Label>
          <textarea
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            placeholder="Preferred supplier..."
            className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            rows={2}
          />
        </div>
        <div className="flex items-center gap-2">
          <input
            type="checkbox"
            id="supplier_active"
            checked={isActive}
            onChange={(e) => setIsActive(e.target.checked)}
            className="h-4 w-4 rounded border-input"
          />
          <Label htmlFor="supplier_active">Active</Label>
        </div>
      </div>
    </Modal>
  )
}

export function SuppliersPage() {
  const { user } = useAuthStore()
  const canManage = user?.role?.slug === 'owner' || user?.role?.slug === 'manager'

  const [suppliers, setSuppliers] = useState<Supplier[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [search, setSearch] = useState('')
  const [modalOpen, setModalOpen] = useState(false)
  const [editSupplier, setEditSupplier] = useState<Supplier | null>(null)
  const [deleteId, setDeleteId] = useState<number | null>(null)

  const fetchSuppliers = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params: Record<string, unknown> = { page, per_page: 20 }
      if (search) params.search = search
      const res: PaginatedResponse<Supplier> = await supplierService.list(params)
      setSuppliers(res.data)
      setLastPage(res.last_page)
      setTotal(res.total)
    } catch {
      setError('Failed to load suppliers')
    } finally {
      setLoading(false)
    }
  }, [page, search])

  useEffect(() => {
    fetchSuppliers()
  }, [fetchSuppliers])

  const handleEdit = (s: Supplier) => {
    setEditSupplier(s)
    setModalOpen(true)
  }

  const handleAdd = () => {
    setEditSupplier(null)
    setModalOpen(true)
  }

  const handleDelete = async () => {
    if (!deleteId) return
    try {
      await supplierService.delete(deleteId)
      setDeleteId(null)
      fetchSuppliers()
    } catch {
      // ignore
    }
  }

  return (
    <DashboardLayout>
      <PageHeader
        title="Suppliers"
        description={`${total} suppliers`}
        action={
          canManage && (
            <Button onClick={handleAdd}>Add Supplier</Button>
          )
        }
      />

      <div className="mb-4 flex gap-3">
        <Input
          placeholder="Cari supplier, kontak, telepon..."
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
        <ErrorState message={error} onRetry={fetchSuppliers} />
      ) : suppliers.length === 0 ? (
        <EmptyState
          title="No suppliers yet"
          description="Add your first supplier to start purchasing."
        />
      ) : (
        <>
          <Table>
            <THead>
              <TR>
                <TH>Supplier</TH>
                <TH>Contact Person</TH>
                <TH>Phone</TH>
                <TH>Email</TH>
                <TH>Status</TH>
                <TH>Actions</TH>
              </TR>
            </THead>
            <TBody>
              {suppliers.map((s) => (
                <TR key={s.id}>
                  <TD className="font-medium">{s.name}</TD>
                  <TD className="text-muted-foreground">{s.contact_person ?? '-'}</TD>
                  <TD className="text-muted-foreground">{s.phone ?? '-'}</TD>
                  <TD className="text-muted-foreground">{s.email ?? '-'}</TD>
                  <TD>
                    <Badge variant={s.is_active ? 'success' : 'danger'}>
                      {s.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                  </TD>
                  <TD>
                    <div className="flex gap-1">
                      {canManage && (
                        <>
                          <Button variant="ghost" size="sm" onClick={() => handleEdit(s)}>
                            Edit
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setDeleteId(s.id)}
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

      <SupplierModal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        onSuccess={fetchSuppliers}
        supplier={editSupplier}
      />

      <Modal
        open={deleteId !== null}
        onClose={() => setDeleteId(null)}
        title="Delete Supplier"
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
          Are you sure you want to delete this supplier? This action cannot be undone.
        </p>
      </Modal>
    </DashboardLayout>
  )
}
