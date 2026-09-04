import { useState, useEffect, useCallback } from 'react'
import type { HeldSale } from '@/types'
import { heldSaleService } from '@/services/heldSale'
import { useCartStore } from '@/stores/cart'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { formatRupiah } from '@/lib/utils'

interface HeldSaleListProps {
  open: boolean
  onClose: () => void
  onRecall: () => void
}

export function HeldSaleList({ open, onClose, onRecall }: HeldSaleListProps) {
  const cart = useCartStore()
  const [heldSales, setHeldSales] = useState<HeldSale[]>([])
  const [loading, setLoading] = useState(false)

  const fetchHeldSales = useCallback(async () => {
    if (!open || !cart.storeId) return
    setLoading(true)
    try {
      const res = await heldSaleService.list({ status: 'held', store_id: cart.storeId })
      setHeldSales(res.data)
    } catch {
      // ignore
    } finally {
      setLoading(false)
    }
  }, [open, cart.storeId])

  useEffect(() => {
    fetchHeldSales()
  }, [fetchHeldSales])

  const handleRecall = async (heldSale: HeldSale) => {
    try {
      const recalled = await heldSaleService.recall(heldSale.id)
      const cartData = recalled.cart_data as { items?: Array<{ product_id: number; variant_id: number | null; quantity: number; unit_price: number; original_price: number | null; product_name: string; sku: string | null }> }

      if (cartData?.items) {
        const items = cartData.items.map((i) => ({
          product: {
            id: i.product_id,
            tenant_id: 0,
            category_id: 0,
            name: i.product_name,
            sku: i.sku,
            barcode: null,
            description: null,
            cost_price: '0',
            selling_price: String(i.unit_price),
            unit: '',
            image: null,
            is_active: true,
            has_variants: false,
            is_trackable: false,
            min_stock: null,
            base_unit_id: null,
            purchase_unit_id: null,
            created_at: '',
            updated_at: '',
          },
          variant: null,
          quantity: i.quantity,
          unitPrice: i.unit_price,
          originalPrice: i.original_price,
        }))
        cart.restoreFromHeld(items, recalled.customer_id, recalled.store_id, '')
      }
      onRecall()
      onClose()
    } catch {
      // ignore
    }
  }

  const handleDelete = async (id: number) => {
    try {
      await heldSaleService.delete(id)
      setHeldSales((prev) => prev.filter((h) => h.id !== id))
    } catch {
      // ignore
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Penjualan Ditahan"
      size="md"
      footer={
        <Button variant="outline" onClick={onClose}>
          Tutup
        </Button>
      }
    >
      <div className="space-y-2 max-h-96 overflow-y-auto">
        {loading ? (
          <p className="text-sm text-muted-foreground text-center py-4">Memuat...</p>
        ) : heldSales.length === 0 ? (
          <p className="text-sm text-muted-foreground text-center py-4">Tidak ada penjualan ditahan</p>
        ) : (
          heldSales.map((held) => {
            const cartData = held.cart_data as { items?: Array<{ product_name: string; quantity: number; unit_price: number }> }
            const itemCount = cartData?.items?.reduce((sum, i) => sum + i.quantity, 0) ?? 0
            const total = cartData?.items?.reduce((sum, i) => sum + i.unit_price * i.quantity, 0) ?? 0
            return (
              <div key={held.id} className="rounded-md border border-border p-3 space-y-2">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium">{held.hold_number}</p>
                    <p className="text-xs text-muted-foreground">
                      {itemCount} item — {formatRupiah(total)}
                    </p>
                  </div>
                  <Badge variant="warning">Ditahan</Badge>
                </div>
                <div className="flex gap-2">
                  <Button size="sm" onClick={() => handleRecall(held)} className="flex-1">
                    Panggil
                  </Button>
                  <Button size="sm" variant="destructive" onClick={() => handleDelete(held.id)}>
                    Hapus
                  </Button>
                </div>
              </div>
            )
          })
        )}
      </div>
    </Modal>
  )
}
