import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import { Select } from '@/components/ui/Select'
import { inventoryService } from '@/services/inventory'
import { storeService } from '@/services/store'
import { productService } from '@/services/product'
import type { Product, Store } from '@/types'

export function TransferPage() {
  const navigate = useNavigate()

  const [stores, setStores] = useState<Store[]>([])
  const [products, setProducts] = useState<Product[]>([])
  const [fromStoreId, setFromStoreId] = useState('')
  const [toStoreId, setToStoreId] = useState('')
  const [productId, setProductId] = useState('')
  const [quantity, setQuantity] = useState('')
  const [note, setNote] = useState('')
  const [availableStock, setAvailableStock] = useState<number | null>(null)
  const [loading, setLoading] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [success, setSuccess] = useState('')

  useEffect(() => {
    storeService.list().then(setStores).catch(() => {})
    productService.list({ per_page: 100 }).then((r) => setProducts(r.data)).catch(() => {})
  }, [])

  useEffect(() => {
    setAvailableStock(null)
    if (fromStoreId && productId) {
      inventoryService
        .getByProduct(parseInt(productId, 10), parseInt(fromStoreId, 10))
        .then((invs) => {
          if (invs.length > 0) {
            setAvailableStock(invs[0].quantity)
          } else {
            setAvailableStock(0)
          }
        })
        .catch(() => setAvailableStock(0))
    }
  }, [fromStoreId, productId])

  const qtyNum = parseInt(quantity, 10)
  const showWarning = availableStock !== null && !isNaN(qtyNum) && qtyNum > availableStock

  const handleSubmit = async () => {
    setErrors({})
    setSuccess('')

    if (!fromStoreId) {
      setErrors({ from_store_id: ['Source store is required'] })
      return
    }
    if (!toStoreId) {
      setErrors({ to_store_id: ['Destination store is required'] })
      return
    }
    if (fromStoreId === toStoreId) {
      setErrors({ to_store_id: ['Destination must be different from source'] })
      return
    }
    if (!productId) {
      setErrors({ product_id: ['Product is required'] })
      return
    }
    if (!quantity || isNaN(qtyNum) || qtyNum <= 0) {
      setErrors({ quantity: ['Quantity must be greater than 0'] })
      return
    }

    setLoading(true)
    try {
      const res = await inventoryService.transfer({
        from_store_id: parseInt(fromStoreId, 10),
        to_store_id: parseInt(toStoreId, 10),
        product_id: parseInt(productId, 10),
        quantity: qtyNum,
        note: note || undefined,
      })
      setSuccess(`Transfer completed: ${res.out_movement.after_quantity} → ${res.in_movement.after_quantity}`)
      setQuantity('')
      setNote('')
      // Refresh available stock
      if (fromStoreId && productId) {
        inventoryService
          .getByProduct(parseInt(productId, 10), parseInt(fromStoreId, 10))
          .then((invs) => setAvailableStock(invs.length > 0 ? invs[0].quantity : 0))
      }
    } catch (err: any) {
      if (err.response?.data?.errors) {
        setErrors(err.response.data.errors)
      } else if (err.response?.data?.message) {
        setErrors({ quantity: [err.response.data.message] })
      }
    } finally {
      setLoading(false)
    }
  }

  const storeOptions = stores.map((s) => ({ value: s.id, label: s.name }))
  const productOptions = products.map((p) => ({ value: p.id, label: p.name }))

  return (
    <DashboardLayout>
      <PageHeader
        title="Transfer Stock"
        description="Move stock between stores"
        action={
          <Button variant="outline" onClick={() => navigate('/inventory')}>
            Back to Inventory
          </Button>
        }
      />

      <div className="max-w-lg space-y-4 rounded-lg border bg-card p-6">
        {success && (
          <div className="rounded-md bg-green-50 p-3 text-sm text-green-700">{success}</div>
        )}

        <div className="space-y-1.5">
          <Label>From Store *</Label>
          <Select
            value={fromStoreId}
            onChange={(e) => setFromStoreId(e.target.value)}
            options={storeOptions}
            placeholder="Pilih toko asal"
          />
          {errors.from_store_id && <p className="text-xs text-destructive">{errors.from_store_id[0]}</p>}
        </div>

        <div className="space-y-1.5">
          <Label>To Store *</Label>
          <Select
            value={toStoreId}
            onChange={(e) => setToStoreId(e.target.value)}
            options={storeOptions}
            placeholder="Pilih toko tujuan"
          />
          {errors.to_store_id && <p className="text-xs text-destructive">{errors.to_store_id[0]}</p>}
        </div>

        <div className="space-y-1.5">
          <Label>Product *</Label>
          <Select
            value={productId}
            onChange={(e) => setProductId(e.target.value)}
            options={productOptions}
            placeholder="Pilih produk"
          />
          {errors.product_id && <p className="text-xs text-destructive">{errors.product_id[0]}</p>}
        </div>

        {availableStock !== null && (
          <div className="rounded-md bg-muted p-3 text-sm">
            <span className="text-muted-foreground">Available Stock: </span>
            <span className="font-semibold">{availableStock}</span>
          </div>
        )}

        <div className="space-y-1.5">
          <Label>Quantity *</Label>
          <Input
            value={quantity}
            onChange={(e) => setQuantity(e.target.value)}
            placeholder="20"
            inputMode="numeric"
          />
          {showWarning && (
            <p className="text-xs text-destructive">
              Warning: Quantity exceeds available stock ({availableStock})
            </p>
          )}
          {errors.quantity && <p className="text-xs text-destructive">{errors.quantity[0]}</p>}
        </div>

        <div className="space-y-1.5">
          <Label>Note</Label>
          <textarea
            value={note}
            onChange={(e) => setNote(e.target.value)}
            placeholder="Restock branch..."
            className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            rows={2}
          />
        </div>

        <div className="flex justify-end gap-2 pt-2">
          <Button variant="outline" onClick={() => navigate('/inventory')} disabled={loading}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={loading || showWarning}>
            {loading ? 'Transferring...' : 'Transfer'}
          </Button>
        </div>
      </div>
    </DashboardLayout>
  )
}
