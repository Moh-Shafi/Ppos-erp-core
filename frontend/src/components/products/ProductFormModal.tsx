import { useState, useEffect } from 'react'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import { Select } from '@/components/ui/Select'
import { formatRupiah, parseRupiah } from '@/lib/utils'
import { categoryService } from '@/services/category'
import type { Category, Product } from '@/types'

interface ProductFormModalProps {
  open: boolean
  onClose: () => void
  onSubmit: (data: {
    category_id: number
    name: string
    sku?: string
    barcode?: string
    description?: string
    cost_price: number
    selling_price: number
    unit: string
    is_active: boolean
  }) => Promise<void>
  product?: Product | null
  loading?: boolean
}

const UNITS = ['pcs', 'botol', 'dus', 'box', 'kg', 'gram', 'liter', 'pack']

export function ProductFormModal({
  open,
  onClose,
  onSubmit,
  product,
  loading = false,
}: ProductFormModalProps) {
  const [categories, setCategories] = useState<Category[]>([])
  const [form, setForm] = useState({
    category_id: '',
    name: '',
    sku: '',
    barcode: '',
    description: '',
    costPrice: '',
    sellingPrice: '',
    unit: 'pcs',
    is_active: true,
  })
  const [errors, setErrors] = useState<Record<string, string[]>>({})

  useEffect(() => {
    if (open) {
      categoryService.all().then(setCategories).catch(() => {})
      setErrors({})
    }
  }, [open])

  useEffect(() => {
    if (product) {
      setForm({
        category_id: String(product.category_id),
        name: product.name,
        sku: product.sku ?? '',
        barcode: product.barcode ?? '',
        description: product.description ?? '',
        costPrice: formatRupiah(product.cost_price),
        sellingPrice: formatRupiah(product.selling_price),
        unit: product.unit,
        is_active: product.is_active,
      })
    } else {
      setForm({
        category_id: '',
        name: '',
        sku: '',
        barcode: '',
        description: '',
        costPrice: '',
        sellingPrice: '',
        unit: 'pcs',
        is_active: true,
      })
    }
  }, [product, open])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setErrors({})

    if (!form.category_id) {
      setErrors({ category_id: ['Category is required'] })
      return
    }

    try {
      await onSubmit({
        category_id: parseInt(form.category_id, 10),
        name: form.name,
        sku: form.sku || undefined,
        barcode: form.barcode || undefined,
        description: form.description || undefined,
        cost_price: parseRupiah(form.costPrice),
        selling_price: parseRupiah(form.sellingPrice),
        unit: form.unit,
        is_active: form.is_active,
      })
    } catch (err: any) {
      if (err.response?.data?.errors) {
        setErrors(err.response.data.errors)
      }
    }
  }

  const categoryOptions = categories.map((c) => ({ value: c.id, label: c.name }))

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={product ? 'Edit Produk' : 'Tambah Produk'}
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={loading}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={loading}>
            {loading ? 'Saving...' : 'Save'}
          </Button>
        </>
      }
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-1.5">
            <Label htmlFor="name">Nama Produk *</Label>
            <Input
              id="name"
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              placeholder="Aqua 600ml"
            />
            {errors.name && <p className="text-xs text-destructive">{errors.name[0]}</p>}
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="category_id">Kategori *</Label>
            <Select
              id="category_id"
              value={form.category_id}
              onChange={(e) => setForm({ ...form, category_id: e.target.value })}
              options={categoryOptions}
              placeholder="Pilih kategori"
            />
            {errors.category_id && <p className="text-xs text-destructive">{errors.category_id[0]}</p>}
          </div>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-1.5">
            <Label htmlFor="sku">SKU</Label>
            <Input
              id="sku"
              value={form.sku}
              onChange={(e) => setForm({ ...form, sku: e.target.value })}
              placeholder="AQUA-600"
            />
            {errors.sku && <p className="text-xs text-destructive">{errors.sku[0]}</p>}
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="barcode">Barcode</Label>
            <Input
              id="barcode"
              value={form.barcode}
              onChange={(e) => setForm({ ...form, barcode: e.target.value })}
              placeholder="8992761141234"
            />
            {errors.barcode && <p className="text-xs text-destructive">{errors.barcode[0]}</p>}
          </div>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-1.5">
            <Label htmlFor="costPrice">Harga Modal *</Label>
            <Input
              id="costPrice"
              value={form.costPrice}
              onChange={(e) => setForm({ ...form, costPrice: formatRupiah(parseRupiah(e.target.value)) })}
              placeholder="Rp 0"
              inputMode="numeric"
            />
            {errors.cost_price && <p className="text-xs text-destructive">{errors.cost_price[0]}</p>}
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="sellingPrice">Harga Jual *</Label>
            <Input
              id="sellingPrice"
              value={form.sellingPrice}
              onChange={(e) => setForm({ ...form, sellingPrice: formatRupiah(parseRupiah(e.target.value)) })}
              placeholder="Rp 0"
              inputMode="numeric"
            />
            {errors.selling_price && <p className="text-xs text-destructive">{errors.selling_price[0]}</p>}
          </div>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-1.5">
            <Label htmlFor="unit">Satuan *</Label>
            <Select
              id="unit"
              value={form.unit}
              onChange={(e) => setForm({ ...form, unit: e.target.value })}
              options={UNITS.map((u) => ({ value: u, label: u }))}
            />
            {errors.unit && <p className="text-xs text-destructive">{errors.unit[0]}</p>}
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="is_active">Status</Label>
            <Select
              id="is_active"
              value={form.is_active ? '1' : '0'}
              onChange={(e) => setForm({ ...form, is_active: e.target.value === '1' })}
              options={[
                { value: '1', label: 'Active' },
                { value: '0', label: 'Inactive' },
              ]}
            />
          </div>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="description">Deskripsi</Label>
          <textarea
            id="description"
            value={form.description}
            onChange={(e) => setForm({ ...form, description: e.target.value })}
            placeholder="Product description..."
            className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            rows={3}
          />
          {errors.description && <p className="text-xs text-destructive">{errors.description[0]}</p>}
        </div>
      </form>
    </Modal>
  )
}
