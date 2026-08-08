import { useState, useEffect, useCallback } from 'react'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { PageHeader } from '@/components/common/PageHeader'
import { EmptyState, LoadingState, ErrorState } from '@/components/common/State'
import { Select } from '@/components/ui/Select'
import { Badge } from '@/components/ui/Badge'
import { Table, THead, TBody, TR, TH, TD } from '@/components/ui/Table'
import { Pagination } from '@/components/ui/Pagination'
import { inventoryService } from '@/services/inventory'
import { storeService } from '@/services/store'
import type { InventoryMovement, Store, PaginatedResponse } from '@/types'

const MOVEMENT_TYPE_LABELS: Record<string, string> = {
  purchase: 'Purchase',
  sale: 'Sale',
  sale_return: 'Sale Return',
  purchase_return: 'Purchase Return',
  adjustment: 'Adjustment',
  transfer_in: 'Transfer In',
  transfer_out: 'Transfer Out',
  initial: 'Initial',
}

const MOVEMENT_TYPE_VARIANTS: Record<string, 'success' | 'danger' | 'warning' | 'default'> = {
  purchase: 'success',
  sale: 'danger',
  sale_return: 'warning',
  purchase_return: 'warning',
  adjustment: 'default',
  transfer_in: 'success',
  transfer_out: 'danger',
  initial: 'default',
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export function MovementsPage() {
  const [movements, setMovements] = useState<InventoryMovement[]>([])
  const [stores, setStores] = useState<Store[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [storeId, setStoreId] = useState('')
  const [type, setType] = useState('')

  const fetchMovements = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params: Record<string, unknown> = { page, per_page: 20 }
      if (storeId) params.store_id = storeId
      if (type) params.type = type
      const res: PaginatedResponse<InventoryMovement> = await inventoryService.movements(params)
      setMovements(res.data)
      setLastPage(res.last_page)
      setTotal(res.total)
    } catch {
      setError('Failed to load movements')
    } finally {
      setLoading(false)
    }
  }, [page, storeId, type])

  useEffect(() => {
    storeService.list().then(setStores).catch(() => {})
  }, [])

  useEffect(() => {
    fetchMovements()
  }, [fetchMovements])

  const storeOptions = stores.map((s) => ({ value: s.id, label: s.name }))
  const typeOptions = Object.entries(MOVEMENT_TYPE_LABELS).map(([value, label]) => ({ value, label }))

  return (
    <DashboardLayout>
      <PageHeader title="Movement History" description={`${total} movements`} />

      <div className="mb-4 flex gap-3">
        <Select
          value={storeId}
          onChange={(e) => {
            setStoreId(e.target.value)
            setPage(1)
          }}
          options={storeOptions}
          placeholder="Semua Toko"
          className="max-w-xs"
        />
        <Select
          value={type}
          onChange={(e) => {
            setType(e.target.value)
            setPage(1)
          }}
          options={typeOptions}
          placeholder="Semua Type"
          className="max-w-xs"
        />
      </div>

      {loading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={error} onRetry={fetchMovements} />
      ) : movements.length === 0 ? (
        <EmptyState
          title="No movements yet"
          description="Stock movements will appear here once you start adjusting inventory."
        />
      ) : (
        <>
          <Table>
            <THead>
              <TR>
                <TH>Date</TH>
                <TH>Product</TH>
                <TH>Store</TH>
                <TH>Type</TH>
                <TH>Quantity</TH>
                <TH>Before → After</TH>
                <TH>User</TH>
                <TH>Note</TH>
              </TR>
            </THead>
            <TBody>
              {movements.map((m) => (
                <TR key={m.id}>
                  <TD className="text-muted-foreground whitespace-nowrap">{formatDate(m.created_at)}</TD>
                  <TD className="font-medium">{m.product?.name ?? `#${m.product_id}`}</TD>
                  <TD>{m.store?.name ?? `#${m.store_id}`}</TD>
                  <TD>
                    <Badge variant={MOVEMENT_TYPE_VARIANTS[m.type] ?? 'default'}>
                      {MOVEMENT_TYPE_LABELS[m.type] ?? m.type}
                    </Badge>
                  </TD>
                  <TD className={m.quantity > 0 ? 'text-green-600 font-medium' : 'text-red-600 font-medium'}>
                    {m.quantity > 0 ? `+${m.quantity}` : m.quantity}
                  </TD>
                  <TD className="text-muted-foreground">
                    {m.before_quantity} → {m.after_quantity}
                  </TD>
                  <TD className="text-muted-foreground">{m.user?.name ?? '-'}</TD>
                  <TD className="text-muted-foreground text-xs">{m.note ?? '-'}</TD>
                </TR>
              ))}
            </TBody>
          </Table>

          <Pagination currentPage={page} lastPage={lastPage} onPageChange={setPage} />
        </>
      )}
    </DashboardLayout>
  )
}
