import { useState, useEffect, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { PageHeader } from '@/components/common/PageHeader'
import { EmptyState, LoadingState, ErrorState } from '@/components/common/State'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Select } from '@/components/ui/Select'
import { Badge } from '@/components/ui/Badge'
import { Table, THead, TBody, TR, TH, TD } from '@/components/ui/Table'
import { Pagination } from '@/components/ui/Pagination'
import { AdjustStockModal } from '@/components/inventory/AdjustStockModal'
import { inventoryService } from '@/services/inventory'
import { storeService } from '@/services/store'
import { useAuthStore } from '@/stores/auth'
import type { Inventory, Store, PaginatedResponse } from '@/types'

function getStockStatus(qty: number, minQty: number): { label: string; variant: 'success' | 'warning' | 'danger' } {
  if (qty === 0) return { label: 'Out of Stock', variant: 'danger' }
  if (qty <= minQty) return { label: 'Low Stock', variant: 'warning' }
  return { label: 'In Stock', variant: 'success' }
}

export function InventoryPage() {
  const { user } = useAuthStore()
  const canManage = user?.role?.slug === 'owner' || user?.role?.slug === 'manager'
  const navigate = useNavigate()

  const [inventories, setInventories] = useState<Inventory[]>([])
  const [stores, setStores] = useState<Store[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [storeId, setStoreId] = useState('')
  const [search, setSearch] = useState('')
  const [lowStock, setLowStock] = useState(false)

  const [adjustOpen, setAdjustOpen] = useState(false)

  const fetchInventory = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params: Record<string, unknown> = { page, per_page: 20 }
      if (storeId) params.store_id = storeId
      if (lowStock) params.low_stock = true
      const res: PaginatedResponse<Inventory> = await inventoryService.list(params)
      let filtered = res.data
      if (search) {
        const q = search.toLowerCase()
        filtered = filtered.filter(
          (inv) =>
            inv.product?.name?.toLowerCase().includes(q) ||
            inv.product?.sku?.toLowerCase().includes(q),
        )
      }
      setInventories(filtered)
      setLastPage(res.last_page)
      setTotal(res.total)
    } catch {
      setError('Failed to load inventory')
    } finally {
      setLoading(false)
    }
  }, [page, storeId, lowStock, search])

  useEffect(() => {
    storeService.list().then(setStores).catch(() => {})
  }, [])

  useEffect(() => {
    fetchInventory()
  }, [fetchInventory])

  const storeOptions = stores.map((s) => ({ value: s.id, label: s.name }))

  return (
    <DashboardLayout>
      <PageHeader
        title="Inventory"
        description={`${total} items`}
        action={
          <div className="flex gap-2">
            {canManage && (
              <>
                <Button variant="outline" onClick={() => setAdjustOpen(true)}>
                  Adjust Stock
                </Button>
                <Button onClick={() => navigate('/inventory/transfer')}>Transfer Stock</Button>
              </>
            )}
          </div>
        }
      />

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
        <Input
          placeholder="Cari produk..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="max-w-xs"
        />
        <Button
          variant={lowStock ? 'default' : 'outline'}
          onClick={() => {
            setLowStock(!lowStock)
            setPage(1)
          }}
        >
          Low Stock
        </Button>
      </div>

      {loading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={error} onRetry={fetchInventory} />
      ) : inventories.length === 0 ? (
        <EmptyState
          title="No inventory yet"
          description="Add products to your store to start tracking stock."
        />
      ) : (
        <>
          <Table>
            <THead>
              <TR>
                <TH>Product</TH>
                <TH>SKU</TH>
                <TH>Store</TH>
                <TH>Stock</TH>
                <TH>Minimum</TH>
                <TH>Status</TH>
                <TH>Actions</TH>
              </TR>
            </THead>
            <TBody>
              {inventories.map((inv) => {
                const status = getStockStatus(inv.quantity, inv.minimum_quantity)
                return (
                  <TR key={inv.id}>
                    <TD className="font-medium">{inv.product?.name ?? `Product #${inv.product_id}`}</TD>
                    <TD className="text-muted-foreground">{inv.product?.sku ?? '-'}</TD>
                    <TD>{inv.store?.name ?? `Store #${inv.store_id}`}</TD>
                    <TD className="font-medium">{inv.quantity}</TD>
                    <TD className="text-muted-foreground">{inv.minimum_quantity}</TD>
                    <TD>
                      <Badge variant={status.variant}>{status.label}</Badge>
                    </TD>
                    <TD>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => navigate('/inventory/movements')}
                      >
                        History
                      </Button>
                    </TD>
                  </TR>
                )
              })}
            </TBody>
          </Table>

          <Pagination currentPage={page} lastPage={lastPage} onPageChange={setPage} />
        </>
      )}

      <AdjustStockModal
        open={adjustOpen}
        onClose={() => setAdjustOpen(false)}
        onSuccess={fetchInventory}
      />
    </DashboardLayout>
  )
}
