import { useState, useEffect, useCallback } from 'react'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { PageHeader } from '@/components/common/PageHeader'
import { EmptyState, LoadingState, ErrorState } from '@/components/common/State'
import { Button } from '@/components/ui/Button'
import { Table, THead, TBody, TR, TH, TD } from '@/components/ui/Table'
import { autoReorderService } from '@/services/autoReorder'
import { useModuleConfigStore } from '@/stores/module-config'
import { useAuthStore } from '@/stores/auth'
import type { AutoReorderItem } from '@/types'

export function AutoReorderPage() {
  const { user } = useAuthStore()
  const canManage = user?.role?.slug === 'owner' || user?.role?.slug === 'manager'
  const moduleConfig = useModuleConfigStore()
  const storeId = moduleConfig.currentStore?.id

  const [items, setItems] = useState<AutoReorderItem[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [selected, setSelected] = useState<number[]>([])
  const [generating, setGenerating] = useState(false)

  const fetchReport = useCallback(async () => {
    if (!storeId) return
    setLoading(true)
    setError('')
    try {
      const res = await autoReorderService.report(storeId)
      setItems(res.data)
    } catch {
      setError('Failed to load auto-reorder report')
    } finally {
      setLoading(false)
    }
  }, [storeId])

  useEffect(() => {
    fetchReport()
  }, [fetchReport])

  const handleToggle = (productId: number) => {
    setSelected(prev =>
      prev.includes(productId)
        ? prev.filter(id => id !== productId)
        : [...prev, productId]
    )
  }

  const handleGenerate = async () => {
    if (!storeId || selected.length === 0) return
    setGenerating(true)
    try {
      await autoReorderService.generate(storeId, selected)
      setSelected([])
      fetchReport()
    } catch {
      // ignore
    } finally {
      setGenerating(false)
    }
  }

  return (
    <DashboardLayout>
      <PageHeader
        title="Auto Reorder"
        description="Low-stock products and suggested reorder quantities"
        action={
          canManage && selected.length > 0 && (
            <Button onClick={handleGenerate} disabled={generating}>
              {generating ? 'Generating...' : `Generate Requisition (${selected.length})`}
            </Button>
          )
        }
      />

      {loading ? (
        <LoadingState />
      ) : error ? (
        <ErrorState message={error} onRetry={fetchReport} />
      ) : items.length === 0 ? (
        <EmptyState
          title="All stock levels are healthy"
          description="No products below minimum quantity."
        />
      ) : (
        <Table>
          <THead>
            <TR>
              <TH>&nbsp;</TH>
              <TH>Product</TH>
              <TH>Current Stock</TH>
              <TH>Minimum</TH>
              <TH>Maximum</TH>
              <TH>Suggested Qty</TH>
              <TH>Est. Cost</TH>
            </TR>
          </THead>
          <TBody>
            {items.map((item) => (
              <TR key={item.product_id}>
                <TD>
                  <input
                    type="checkbox"
                    checked={selected.includes(item.product_id)}
                    onChange={() => handleToggle(item.product_id)}
                    className="h-4 w-4 rounded border-input"
                  />
                </TD>
                <TD className="font-medium">{item.product_name}</TD>
                <TD className="text-red-600">{item.current_stock}</TD>
                <TD>{item.minimum_quantity}</TD>
                <TD>{item.maximum_quantity ?? '-'}</TD>
                <TD className="font-medium">{item.suggested_qty}</TD>
                <TD>{item.estimated_cost.toLocaleString()}</TD>
              </TR>
            ))}
          </TBody>
        </Table>
      )}
    </DashboardLayout>
  )
}
