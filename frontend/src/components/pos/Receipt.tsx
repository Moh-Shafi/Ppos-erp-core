import type { Sale } from '@/types'
import { formatRupiah } from '@/lib/utils'
import { Button } from '@/components/ui/Button'

interface ReceiptProps {
  sale: Sale
  onClose: () => void
}

export function Receipt({ sale, onClose }: ReceiptProps) {
  return (
    <div className="mx-auto max-w-sm font-mono text-sm">
      <div className="text-center mb-4">
        <h2 className="font-bold text-base">{sale.store?.name ?? 'Toko'}</h2>
        <p className="text-xs text-muted-foreground">{sale.sale_number}</p>
        <p className="text-xs text-muted-foreground">
          {new Date(sale.sale_date).toLocaleString('id-ID')}
        </p>
        <p className="text-xs text-muted-foreground">Kasir: {sale.cashier?.name ?? '-'}</p>
        {sale.customer && (
          <p className="text-xs text-muted-foreground">Pelanggan: {sale.customer.name}</p>
        )}
      </div>

      <div className="border-t border-dashed border-border pt-2 mb-2">
        {sale.items?.map((item) => (
          <div key={item.id} className="flex justify-between py-0.5">
            <div>
              <span>{item.quantity}x </span>
              <span>{item.product_name}</span>
              <div className="text-xs text-muted-foreground">
                @ {formatRupiah(item.unit_price)}
              </div>
            </div>
            <span>{formatRupiah(item.total)}</span>
          </div>
        ))}
      </div>

      <div className="border-t border-dashed border-border pt-2 space-y-0.5">
        <div className="flex justify-between">
          <span>Subtotal</span>
          <span>{formatRupiah(sale.subtotal)}</span>
        </div>
        {parseFloat(sale.discount) > 0 && (
          <div className="flex justify-between">
            <span>Diskon</span>
            <span>-{formatRupiah(sale.discount)}</span>
          </div>
        )}
        {parseFloat(sale.tax) > 0 && (
          <div className="flex justify-between">
            <span>Pajak</span>
            <span>{formatRupiah(sale.tax)}</span>
          </div>
        )}
        <div className="flex justify-between font-bold text-base border-t border-dashed border-border pt-1">
          <span>Total</span>
          <span>{formatRupiah(sale.total)}</span>
        </div>
      </div>

      <div className="border-t border-dashed border-border pt-2 space-y-0.5">
        {sale.payments?.map((p) => (
          <div key={p.id} className="flex justify-between">
            <span className="capitalize">{p.payment_method}</span>
            <span>{formatRupiah(p.amount)}</span>
          </div>
        ))}
        <div className="flex justify-between">
          <span>Bayar</span>
          <span>{formatRupiah(sale.paid_amount)}</span>
        </div>
        {parseFloat(sale.change_amount) > 0 && (
          <div className="flex justify-between">
            <span>Kembalian</span>
            <span>{formatRupiah(sale.change_amount)}</span>
          </div>
        )}
        <div className="flex justify-between font-medium">
          <span>Status</span>
          <span className="capitalize">{sale.payment_status}</span>
        </div>
      </div>

      <div className="text-center mt-4 pt-2 border-t border-dashed border-border">
        <p className="text-xs text-muted-foreground">Terima kasih atas kunjungan Anda!</p>
      </div>

      <div className="mt-4 flex gap-2">
        <Button variant="outline" size="sm" className="flex-1" onClick={() => window.print()}>
          Cetak
        </Button>
        <Button size="sm" className="flex-1" onClick={onClose}>
          Transaksi Baru
        </Button>
      </div>
    </div>
  )
}
