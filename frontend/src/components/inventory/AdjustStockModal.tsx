import { useState, useEffect } from 'react'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import { Select } from '@/components/ui/Select'
import { inventoryService } from '@/services/inventory'
import { productService } from '@/services/product'
import { storeService } from '@/services/store'
import type { Product, Store } from '@/types'

interface AdjustModalProps {
  open: boolean
  onClose: () => void
  onSuccess: () => void
}

export function AdjustStockModal({ open, onClose, onSuccess }: AdjustModalProps) {
  const [stores, setStores] = useState<Store[]>([])
  const [products, setProducts] = useState<Product[]>([])
  const [storeId, setStoreId] = useState('')
  const [productId, setProductId] = useState('')
  const [delta, setDelta] = useState('')
  const [note, setNote] = useState('')
  const [loading, setLoading] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})

  useEffect(() => {
    if (open) {
      storeService.list().then(setStores).catch(() => {})
      productService.list({ per_page: 100 }).then((r) => setProducts(r.data)).catch(() => {})
      setErrors({})
      setStoreId('')
      setProductId('')
      setDelta('')
      setNote('')
    }
  }, [open])

  const handleSubmit = async () => {
    setErrors({})
    const deltaNum = parseInt(delta, 10)

    if (!storeId) {
      setErrors({ store_id: ['Store is required'] })
      return
    }
    if (!productId) {
      setErrors({ product_id: ['Product is required'] })
      return
    }
    if (!delta || isNaN(deltaNum) || deltaNum === 0) {
      setErrors({ delta: ['Adjustment must be a non-zero number'] })
      return
    }

    setLoading(true)
    try {
      await inventoryService.adjust({
        store_id: parseInt(storeId, 10),
        product_id: parseInt(productId, 10),
        delta: deltaNum,
        note: note || undefined,
      })
      onSuccess()
      onClose()
    } catch (err: any) {
      if (err.response?.data?.errors) {
        setErrors(err.response.data.errors)
      } else if (err.response?.data?.message) {
        setErrors({ delta: [err.response.data.message] })
      }
    } finally {
      setLoading(false)
    }
  }

  const storeOptions = stores.map((s) => ({ value: s.id, label: s.name }))
  const productOptions = products.map((p) => ({ value: p.id, label: p.name }))

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Adjust Stock"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={loading}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={loading}>
            {loading ? 'Adjusting...' : 'Adjust'}
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        <div className="space-y-1.5">
          <Label>Store *</Label>
          <Select
            value={storeId}
            onChange={(e) => setStoreId(e.target.value)}
            options={storeOptions}
            placeholder="Pilih toko"
          />
          {errors.store_id && <p className="text-xs text-destructive">{errors.store_id[0]}</p>}
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

        <div className="space-y-1.5">
          <Label>Adjustment (+/-) *</Label>
          <Input
            value={delta}
            onChange={(e) => setDelta(e.target.value)}
            placeholder="+10 atau -5"
            inputMode="numeric"
          />
          {errors.delta && <p className="text-xs text-destructive">{errors.delta[0]}</p>}
        </div>

        <div className="space-y-1.5">
          <Label>Note</Label>
          <textarea
            value={note}
            onChange={(e) => setNote(e.target.value)}
            placeholder="Stock count correction..."
            className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            rows={2}
          />
        </div>
      </div>
    </Modal>
  )
}
