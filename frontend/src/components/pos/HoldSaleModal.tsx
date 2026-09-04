import { useState } from 'react'
import { useCartStore } from '@/stores/cart'
import { heldSaleService } from '@/services/heldSale'
import { useAuthStore } from '@/stores/auth'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'
import { formatRupiah } from '@/lib/utils'

interface HoldSaleModalProps {
  open: boolean
  onClose: () => void
  onHeld: () => void
}

export function HoldSaleModal({ open, onClose, onHeld }: HoldSaleModalProps) {
  const cart = useCartStore()
  const { user } = useAuthStore()
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  const handleHold = async () => {
    if (!cart.storeId) {
      setError('Pilih toko terlebih dahulu')
      return
    }
    if (cart.items.length === 0) {
      setError('Keranjang kosong')
      return
    }

    setLoading(true)
    setError('')
    try {
      const cartData = {
        items: cart.items.map((i) => ({
          product_id: i.product.id,
          variant_id: i.variant?.id ?? null,
          quantity: i.quantity,
          unit_price: i.unitPrice,
          original_price: i.originalPrice,
          product_name: i.product.name,
          sku: i.variant?.sku ?? i.product.sku,
        })),
        discount: cart.discount,
        tax: cart.tax,
        notes: cart.notes,
        cashier_id: user?.id,
      }
      await heldSaleService.hold({
        store_id: cart.storeId,
        customer_id: cart.customerId,
        cart_data: cartData,
      })
      cart.clearCart()
      onHeld()
      onClose()
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } }
      setError(e.response?.data?.message ?? 'Gagal menahan penjualan')
    } finally {
      setLoading(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Tahan Penjualan"
      size="sm"
      footer={
        <>
          <Button variant="outline" onClick={onClose}>
            Batal
          </Button>
          <Button onClick={handleHold} disabled={loading}>
            {loading ? 'Memproses...' : 'Tahan'}
          </Button>
        </>
      }
    >
      <div className="space-y-3">
        <p className="text-sm text-muted-foreground">
          Tahan penjualan saat ini untuk dilanjutkan nanti. Keranjang akan dikosongkan dan dapat dipanggil kembali.
        </p>
        <div className="rounded-md bg-muted p-3 space-y-1">
          <div className="flex justify-between text-sm">
            <span className="text-muted-foreground">Item</span>
            <span>{cart.totalItems()}</span>
          </div>
          <div className="flex justify-between text-sm">
            <span className="text-muted-foreground">Total</span>
            <span className="font-medium">{formatRupiah(cart.total())}</span>
          </div>
        </div>
        {error && (
          <div className="rounded-md bg-destructive/10 border border-destructive/20 p-3">
            <p className="text-sm text-destructive">{error}</p>
          </div>
        )}
      </div>
    </Modal>
  )
}
