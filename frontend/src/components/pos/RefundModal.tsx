import { useState, useEffect } from 'react'
import type { Sale, SaleRefund } from '@/types'
import { saleService } from '@/services/sale'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Badge } from '@/components/ui/Badge'
import { formatRupiah } from '@/lib/utils'

interface RefundModalProps {
  sale: Sale
  open: boolean
  onClose: () => void
  onRefunded: (refund: SaleRefund) => void
}

export function RefundModal({ sale, open, onClose, onRefunded }: RefundModalProps) {
  const [refundType, setRefundType] = useState<'full' | 'partial'>('full')
  const [reason, setReason] = useState('')
  const [itemQuantities, setItemQuantities] = useState<Record<number, number>>({})
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    if (open && sale.items) {
      const initial: Record<number, number> = {}
      sale.items.forEach((item) => {
        initial[item.id] = 0
      })
      setItemQuantities(initial)
    }
  }, [open, sale])

  const refundableAmount = sale.items?.reduce((sum, item) => {
    const qty = refundType === 'full' ? item.quantity : itemQuantities[item.id] ?? 0
    return sum + parseFloat(item.unit_price) * qty
  }, 0) ?? 0

  const handleRefund = async () => {
    setLoading(true)
    setError('')
    try {
      let refund: SaleRefund
      if (refundType === 'full') {
        refund = await saleService.processRefund(sale.id, {
          type: 'full',
          reason: reason || undefined,
        })
      } else {
        const items = Object.entries(itemQuantities)
          .filter(([, qty]) => qty > 0)
          .map(([saleItemId, qty]) => ({
            sale_item_id: Number(saleItemId),
            quantity: qty,
          }))
        if (items.length === 0) {
          setError('Pilih minimal satu item untuk refund parsial')
          setLoading(false)
          return
        }
        refund = await saleService.processRefund(sale.id, {
          type: 'partial',
          reason: reason || undefined,
          items,
        })
      }
      onRefunded(refund)
      onClose()
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } }
      setError(e.response?.data?.message ?? 'Gagal memproses refund')
    } finally {
      setLoading(false)
    }
  }

  const isFullyRefunded = sale.refund_status === 'full'

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={`Refund — ${sale.sale_number}`}
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose}>
            Batal
          </Button>
          <Button
            variant="destructive"
            onClick={handleRefund}
            disabled={loading || isFullyRefunded || (refundType === 'partial' && refundableAmount === 0)}
          >
            {loading ? 'Memproses...' : `Refund ${formatRupiah(refundableAmount)}`}
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        {isFullyRefunded && (
          <div className="rounded-md bg-destructive/10 border border-destructive/20 p-3">
            <p className="text-sm text-destructive">Penjualan ini sudah sepenuhnya di-refund</p>
          </div>
        )}

        <div className="flex gap-2">
          <button
            onClick={() => setRefundType('full')}
            disabled={isFullyRefunded}
            className={`flex-1 rounded-md border p-3 text-center transition-all ${
              refundType === 'full'
                ? 'border-primary bg-primary/5 font-medium'
                : 'border-border hover:border-primary/50'
            } ${isFullyRefunded ? 'opacity-50 cursor-not-allowed' : ''}`}
          >
            Refund Penuh
          </button>
          <button
            onClick={() => setRefundType('partial')}
            disabled={isFullyRefunded}
            className={`flex-1 rounded-md border p-3 text-center transition-all ${
              refundType === 'partial'
                ? 'border-primary bg-primary/5 font-medium'
                : 'border-border hover:border-primary/50'
            } ${isFullyRefunded ? 'opacity-50 cursor-not-allowed' : ''}`}
          >
            Refund Parsial
          </button>
        </div>

        {sale.refund_status === 'partial' && (
          <Badge variant="warning">Sudah ada refund parsial: {formatRupiah(parseFloat(sale.refunded_amount))}</Badge>
        )}

        {refundType === 'partial' && sale.items && (
          <div className="space-y-2 max-h-64 overflow-y-auto">
            {sale.items.map((item) => {
              const maxQty = item.quantity
              return (
                <div key={item.id} className="flex items-center gap-3 rounded-md border border-border p-2">
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium truncate">{item.product_name}</p>
                    <p className="text-xs text-muted-foreground">
                      {formatRupiah(parseFloat(item.unit_price))} x {item.quantity} = {formatRupiah(parseFloat(item.total))}
                    </p>
                  </div>
                  <Input
                    type="number"
                    min={0}
                    max={maxQty}
                    value={itemQuantities[item.id] ?? 0}
                    onChange={(e) => {
                      const val = Math.min(Math.max(0, Number(e.target.value) || 0), maxQty)
                      setItemQuantities((prev) => ({ ...prev, [item.id]: val }))
                    }}
                    className="w-20 h-8 text-center"
                    placeholder="0"
                  />
                </div>
              )
            })}
          </div>
        )}

        {refundType === 'full' && (
          <div className="rounded-md bg-muted p-3">
            <div className="flex justify-between text-sm">
              <span className="text-muted-foreground">Total Refund</span>
              <span className="font-bold">{formatRupiah(parseFloat(sale.total) - parseFloat(sale.refunded_amount))}</span>
            </div>
          </div>
        )}

        <div>
          <label className="text-sm font-medium mb-1 block">Alasan Refund (opsional)</label>
          <Input
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            placeholder="Contoh: Barang rusak, customer kembalikan"
          />
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
