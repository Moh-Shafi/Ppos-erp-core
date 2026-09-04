import { useState } from 'react'
import type { Product, ProductVariant } from '@/types'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'
import { formatRupiah } from '@/lib/utils'

interface VariantSelectorProps {
  product: Product
  open: boolean
  onClose: () => void
  onSelect: (variant: ProductVariant) => void
}

export function VariantSelector({ product, open, onClose, onSelect }: VariantSelectorProps) {
  const [selectedId, setSelectedId] = useState<number | null>(null)

  const variants = product.variants?.filter((v) => v.is_active) ?? []

  const handleConfirm = () => {
    const variant = variants.find((v) => v.id === selectedId)
    if (variant) {
      onSelect(variant)
      setSelectedId(null)
      onClose()
    }
  }

  const handleClose = () => {
    setSelectedId(null)
    onClose()
  }

  return (
    <Modal
      open={open}
      onClose={handleClose}
      title={`Pilih Varian — ${product.name}`}
      size="md"
      footer={
        <>
          <Button variant="outline" onClick={handleClose}>
            Batal
          </Button>
          <Button onClick={handleConfirm} disabled={!selectedId}>
            Pilih
          </Button>
        </>
      }
    >
      <div className="space-y-2">
        {variants.length === 0 ? (
          <p className="text-sm text-muted-foreground">Tidak ada varian aktif</p>
        ) : (
          variants.map((variant) => {
            const label = variant.option_values?.map((ov) => ov.value).join(' / ') ?? `#${variant.id}`
            const price = variant.price_override
              ? parseFloat(variant.price_override)
              : parseFloat(product.selling_price)
            return (
              <button
                key={variant.id}
                onClick={() => setSelectedId(variant.id)}
                className={`w-full flex items-center justify-between rounded-md border p-3 text-left transition-all ${
                  selectedId === variant.id
                    ? 'border-primary bg-primary/5'
                    : 'border-border hover:border-primary/50'
                }`}
              >
                <div>
                  <p className="text-sm font-medium">{label}</p>
                  {variant.sku && (
                    <p className="text-xs text-muted-foreground">{variant.sku}</p>
                  )}
                </div>
                <span className="font-bold text-primary">{formatRupiah(price)}</span>
              </button>
            )
          })
        )}
      </div>
    </Modal>
  )
}
