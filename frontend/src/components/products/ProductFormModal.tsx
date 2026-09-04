import { useState, useEffect, useRef } from 'react'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'
import { formatRupiah, parseRupiah } from '@/lib/utils'
import { categoryService } from '@/services/category'
import { productService } from '@/services/product'
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
    image?: string
    is_active: boolean
    is_trackable?: boolean
    min_stock?: number | null
    has_variants?: boolean
    variant_options?: { name: string; values: { value: string }[] }[]
    variants?: {
      option_value_ids: string[]
      sku?: string
      price_override?: number | null
      is_active?: boolean
    }[]
  }) => Promise<void>
  product?: Product | null
  loading?: boolean
}

const UNITS = ['pcs', 'botol', 'dus', 'box', 'kg', 'gram', 'liter', 'pack']

/* ---------- Icons ---------- */

const IC = {
  upload: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-6 w-6"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
  ),
  image: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
  ),
  trash: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
  ),
  plus: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
  ),
  x: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
  ),
  package: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
  ),
  tag: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
  ),
  barcode: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M3 5v14M7 5v14M11 5v14M15 5v14M19 5v14M21 5v14"/></svg>
  ),
  dollar: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><line x1="12" y1="2" x2="12" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
  ),
  layers: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
  ),
  box: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
  ),
  check: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><polyline points="20 6 9 17 4 12"/></svg>
  ),
  file: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
  ),
}

/* ---------- Image Upload Zone ---------- */

function ImageUploadZone({
  image,
  onImageChange,
  onImageRemove,
}: {
  image: string
  onImageChange: (url: string) => void
  onImageRemove: () => void
}) {
  const fileInputRef = useRef<HTMLInputElement>(null)
  const [uploading, setUploading] = useState(false)
  const [error, setError] = useState('')
  const [dragOver, setDragOver] = useState(false)

  const handleFile = async (file: File) => {
    if (!file.type.startsWith('image/')) {
      setError('File harus berupa gambar')
      return
    }
    if (file.size > 5 * 1024 * 1024) {
      setError('Ukuran gambar maksimal 5MB')
      return
    }
    setError('')
    setUploading(true)
    try {
      const result = await productService.uploadImage(file)
      onImageChange(result.url)
    } catch (err: any) {
      setError(err.response?.data?.message ?? 'Gagal mengunggah gambar')
    } finally {
      setUploading(false)
    }
  }

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault()
    setDragOver(false)
    const file = e.dataTransfer.files[0]
    if (file) handleFile(file)
  }

  if (image) {
    return (
      <div className="group relative h-[140px] w-full overflow-hidden rounded-2xl border border-[#e7e5e4] bg-[#fafaf9]">
        <img src={image} alt="Product preview" className="h-full w-full object-cover" />
        {/* Overlay */}
        <div className="absolute inset-0 flex items-center justify-center gap-2 bg-black/40 opacity-0 transition-opacity duration-200 group-hover:opacity-100">
          <button
            type="button"
            onClick={() => fileInputRef.current?.click()}
            className="flex h-9 cursor-pointer items-center gap-1.5 rounded-lg bg-white/90 px-3 text-[12px] font-semibold text-[#1c1917] transition-all hover:bg-white"
          >
            {IC.file} Ganti
          </button>
          <button
            type="button"
            onClick={onImageRemove}
            className="flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg bg-[#dc2626]/90 text-white transition-all hover:bg-[#dc2626]"
          >
            {IC.trash}
          </button>
        </div>
        <input
          ref={fileInputRef}
          type="file"
          accept="image/jpeg,image/png,image/webp,image/gif"
          className="hidden"
          onChange={(e) => { const f = e.target.files?.[0]; if (f) handleFile(f); e.target.value = '' }}
        />
      </div>
    )
  }

  return (
    <div
      onClick={() => !uploading && fileInputRef.current?.click()}
      onDragOver={(e) => { e.preventDefault(); setDragOver(true) }}
      onDragLeave={() => setDragOver(false)}
      onDrop={handleDrop}
      className={`flex h-[140px] w-full cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed transition-all ${
        dragOver
          ? 'border-[#f54927] bg-[#f54927]/5'
          : 'border-[#e7e5e4] bg-[#fafaf9] hover:border-[#f54927]/40 hover:bg-[#fef8f6]'
      }`}
    >
      {uploading ? (
        <>
          <div className="h-7 w-7 animate-spin rounded-full border-2 border-[#f54927] border-t-transparent" />
          <p className="text-[12px] font-medium text-[#78716c]">Mengunggah...</p>
        </>
      ) : (
        <>
          <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-[#f54927]/10 text-[#f54927]">{IC.upload}</div>
          <p className="text-[12px] font-semibold text-[#1c1917]">Klik atau drag gambar ke sini</p>
          <p className="text-[10px] text-[#a8a29e]">JPG, PNG, WEBP · maks 5MB</p>
        </>
      )}
      {error && <p className="mt-1 text-[11px] font-medium text-[#dc2626]">{error}</p>}
      <input
        ref={fileInputRef}
        type="file"
        accept="image/jpeg,image/png,image/webp,image/gif"
        className="hidden"
        onChange={(e) => { const f = e.target.files?.[0]; if (f) handleFile(f); e.target.value = '' }}
      />
    </div>
  )
}

/* ---------- Premium Field Wrapper ---------- */

function Field({
  label,
  required,
  error,
  icon,
  children,
}: {
  label: string
  required?: boolean
  error?: string
  icon?: React.ReactNode
  children: React.ReactNode
}) {
  return (
    <div className="space-y-1.5">
      <label className="flex items-center gap-1.5 text-[12px] font-semibold text-[#44403c]">
        {icon && <span className="text-[#a8a29e]">{icon}</span>}
        {label}
        {required && <span className="text-[#f54927]">*</span>}
      </label>
      {children}
      {error && <p className="text-[11px] font-medium text-[#dc2626]">{error}</p>}
    </div>
  )
}

/* ---------- Premium Input ---------- */

function PremiumInput(props: React.InputHTMLAttributes<HTMLInputElement>) {
  return (
    <input
      {...props}
      className={`h-10 w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10 ${props.className ?? ''}`}
    />
  )
}

function PremiumSelect(props: React.SelectHTMLAttributes<HTMLSelectElement>) {
  return (
    <select
      {...props}
      className={`h-10 w-full cursor-pointer rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10 ${props.className ?? ''}`}
    />
  )
}

/* ---------- Section Divider ---------- */

function SectionLabel({ icon, children }: { icon: React.ReactNode; children: React.ReactNode }) {
  return (
    <div className="flex items-center gap-2 pt-2">
      <span className="text-[#f54927]">{icon}</span>
      <span className="text-[11px] font-bold tracking-wide text-[#a8a29e] uppercase">{children}</span>
      <div className="h-px flex-1 bg-[#f5f5f4]" />
    </div>
  )
}

/* ---------- Component ---------- */

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
    is_trackable: true,
    min_stock: '',
    has_variants: false,
    image: '',
  })
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [variantOptions, setVariantOptions] = useState<{ name: string; values: string }[]>([])

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
        is_trackable: product.is_trackable ?? true,
        min_stock: product.min_stock != null ? String(product.min_stock) : '',
        has_variants: product.has_variants ?? false,
        image: product.image ?? '',
      })
      if (product.variant_options && product.variant_options.length > 0) {
        setVariantOptions(product.variant_options.map((opt) => ({
          name: opt.name,
          values: opt.values?.map((v) => v.value).join(', ') ?? '',
        })))
      } else {
        setVariantOptions([])
      }
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
        is_trackable: true,
        min_stock: '',
        has_variants: false,
        image: '',
      })
      setVariantOptions([])
    }
  }, [product, open])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setErrors({})

    if (!form.category_id) {
      setErrors({ category_id: ['Kategori wajib dipilih'] })
      return
    }

    try {
      const data: Record<string, unknown> = {
        category_id: parseInt(form.category_id, 10),
        name: form.name,
        sku: form.sku || undefined,
        barcode: form.barcode || undefined,
        description: form.description || undefined,
        cost_price: parseRupiah(form.costPrice),
        selling_price: parseRupiah(form.sellingPrice),
        unit: form.unit,
        image: form.image || undefined,
        is_active: form.is_active,
        is_trackable: form.is_trackable,
        min_stock: form.min_stock ? parseInt(form.min_stock, 10) : null,
      }

      if (form.has_variants && variantOptions.length > 0) {
        data.has_variants = true
        data.variant_options = variantOptions.map((opt) => ({
          name: opt.name,
          values: opt.values.split(',').map((v) => v.trim()).filter(Boolean).map((value) => ({ value })),
        }))
      } else {
        data.has_variants = false
      }

      await onSubmit(data as Parameters<typeof onSubmit>[0])
    } catch (err: any) {
      if (err.response?.data?.errors) {
        setErrors(err.response.data.errors)
      }
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={product ? 'Edit Produk' : 'Tambah Produk'}
      size="lg"
      footer={
        <div className="flex w-full items-center justify-between">
          <Button variant="outline" onClick={onClose} disabled={loading}>
            Batal
          </Button>
          <Button onClick={handleSubmit} disabled={loading}>
            {loading ? (
              <span className="flex items-center gap-2">
                <span className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                Menyimpan...
              </span>
            ) : (
              <span className="flex items-center gap-2">
                {IC.check} {product ? 'Update' : 'Simpan'}
              </span>
            )}
          </Button>
        </div>
      }
    >
      <form onSubmit={handleSubmit} className="space-y-5">
        {/* ===== Image Upload ===== */}
        <SectionLabel icon={IC.image}>Gambar Produk</SectionLabel>
        <ImageUploadZone
          image={form.image}
          onImageChange={(url) => setForm({ ...form, image: url })}
          onImageRemove={() => setForm({ ...form, image: '' })}
        />

        {/* ===== Basic Info ===== */}
        <SectionLabel icon={IC.package}>Informasi Dasar</SectionLabel>
        <div className="grid grid-cols-2 gap-4">
          <Field label="Nama Produk" required error={errors.name?.[0]} icon={IC.package}>
            <PremiumInput
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              placeholder="Aqua 600ml"
            />
          </Field>
          <Field label="Kategori" required error={errors.category_id?.[0]} icon={IC.tag}>
            <PremiumSelect
              value={form.category_id}
              onChange={(e) => setForm({ ...form, category_id: e.target.value })}
            >
              <option value="">Pilih kategori</option>
              {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </PremiumSelect>
          </Field>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <Field label="SKU" error={errors.sku?.[0]} icon={IC.tag}>
            <PremiumInput
              value={form.sku}
              onChange={(e) => setForm({ ...form, sku: e.target.value })}
              placeholder="AQUA-600"
            />
          </Field>
          <Field label="Barcode" error={errors.barcode?.[0]} icon={IC.barcode}>
            <PremiumInput
              value={form.barcode}
              onChange={(e) => setForm({ ...form, barcode: e.target.value })}
              placeholder="8992761141234"
            />
          </Field>
        </div>

        <Field label="Deskripsi" error={errors.description?.[0]}>
          <textarea
            value={form.description}
            onChange={(e) => setForm({ ...form, description: e.target.value })}
            placeholder="Deskripsi produk..."
            rows={3}
            className="w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 py-2.5 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
          />
        </Field>

        {/* ===== Pricing ===== */}
        <SectionLabel icon={IC.dollar}>Harga</SectionLabel>
        <div className="grid grid-cols-2 gap-4">
          <Field label="Harga Modal" required error={errors.cost_price?.[0]} icon={IC.dollar}>
            <PremiumInput
              value={form.costPrice}
              onChange={(e) => setForm({ ...form, costPrice: formatRupiah(parseRupiah(e.target.value)) })}
              placeholder="Rp 0"
              inputMode="numeric"
            />
          </Field>
          <Field label="Harga Jual" required error={errors.selling_price?.[0]} icon={IC.dollar}>
            <PremiumInput
              value={form.sellingPrice}
              onChange={(e) => setForm({ ...form, sellingPrice: formatRupiah(parseRupiah(e.target.value)) })}
              placeholder="Rp 0"
              inputMode="numeric"
            />
          </Field>
        </div>
        {form.costPrice && form.sellingPrice && parseRupiah(form.sellingPrice) > parseRupiah(form.costPrice) && (
          <div className="flex items-center gap-2 rounded-xl bg-[#16a34a]/5 px-3 py-2">
            <span className="text-[#16a34a]">{IC.check}</span>
            <p className="text-[12px] font-medium text-[#16a34a]">
              Margin: {formatRupiah(parseRupiah(form.sellingPrice) - parseRupiah(form.costPrice))}
              {' '}
              ({Math.round(((parseRupiah(form.sellingPrice) - parseRupiah(form.costPrice)) / parseRupiah(form.costPrice)) * 100) || 0}%)
            </p>
          </div>
        )}

        {/* ===== Inventory ===== */}
        <SectionLabel icon={IC.box}>Inventory</SectionLabel>
        <div className="grid grid-cols-3 gap-4">
          <Field label="Satuan" required error={errors.unit?.[0]} icon={IC.box}>
            <PremiumSelect value={form.unit} onChange={(e) => setForm({ ...form, unit: e.target.value })}>
              {UNITS.map((u) => <option key={u} value={u}>{u}</option>)}
            </PremiumSelect>
          </Field>
          <Field label="Min. Stock">
            <PremiumInput
              value={form.min_stock}
              onChange={(e) => setForm({ ...form, min_stock: e.target.value.replace(/[^0-9]/g, '') })}
              placeholder="0"
              inputMode="numeric"
            />
          </Field>
          <Field label="Status">
            <PremiumSelect
              value={form.is_active ? '1' : '0'}
              onChange={(e) => setForm({ ...form, is_active: e.target.value === '1' })}
            >
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </PremiumSelect>
          </Field>
        </div>

        {/* ===== Toggles ===== */}
        <div className="grid grid-cols-2 gap-3">
          <label className={`flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition-all ${form.is_trackable ? 'border-[#f54927]/30 bg-[#f54927]/5' : 'border-[#e7e5e4] bg-white hover:bg-[#fafaf9]'}`}>
            <div className={`flex h-5 w-5 items-center justify-center rounded-md transition-all ${form.is_trackable ? 'bg-[#f54927] text-white' : 'border border-[#e7e5e4] bg-white'}`}>
              {form.is_trackable && IC.check}
            </div>
            <input type="checkbox" checked={form.is_trackable} onChange={(e) => setForm({ ...form, is_trackable: e.target.checked })} className="hidden" />
            <div>
              <p className="text-[12px] font-semibold text-[#1c1917]">Track Inventory</p>
              <p className="text-[10px] text-[#a8a29e]">Pantau stok produk</p>
            </div>
          </label>
          <label className={`flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition-all ${form.has_variants ? 'border-[#8b5cf6]/30 bg-[#8b5cf6]/5' : 'border-[#e7e5e4] bg-white hover:bg-[#fafaf9]'}`}>
            <div className={`flex h-5 w-5 items-center justify-center rounded-md transition-all ${form.has_variants ? 'bg-[#8b5cf6] text-white' : 'border border-[#e7e5e4] bg-white'}`}>
              {form.has_variants && IC.check}
            </div>
            <input type="checkbox" checked={form.has_variants} onChange={(e) => setForm({ ...form, has_variants: e.target.checked })} className="hidden" />
            <div>
              <p className="text-[12px] font-semibold text-[#1c1917]">Produk Varian</p>
              <p className="text-[10px] text-[#a8a29e]">Size, warna, dll</p>
            </div>
          </label>
        </div>

        {/* ===== Variants ===== */}
        {form.has_variants && (
          <div className="space-y-3 rounded-2xl border border-[#8b5cf6]/20 bg-[#8b5cf6]/5 p-4">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <span className="text-[#8b5cf6]">{IC.layers}</span>
                <h4 className="text-[13px] font-bold text-[#1c1917]">Variant Options</h4>
              </div>
              <button
                type="button"
                onClick={() => setVariantOptions([...variantOptions, { name: '', values: '' }])}
                className="flex h-8 cursor-pointer items-center gap-1.5 rounded-lg border border-[#8b5cf6]/30 bg-white px-3 text-[11px] font-semibold text-[#8b5cf6] transition-all hover:bg-[#8b5cf6]/10"
              >
                {IC.plus} Tambah Option
              </button>
            </div>
            {variantOptions.map((opt, index) => (
              <div key={index} className="flex gap-2">
                <PremiumInput
                  value={opt.name}
                  onChange={(e) => {
                    const updated = [...variantOptions]
                    updated[index].name = e.target.value
                    setVariantOptions(updated)
                  }}
                  placeholder="Nama option (cth: Size)"
                  className="flex-1"
                />
                <PremiumInput
                  value={opt.values}
                  onChange={(e) => {
                    const updated = [...variantOptions]
                    updated[index].values = e.target.value
                    setVariantOptions(updated)
                  }}
                  placeholder="Nilai (pisah koma: S, M, L)"
                  className="flex-[2]"
                />
                <button
                  type="button"
                  onClick={() => setVariantOptions(variantOptions.filter((_, i) => i !== index))}
                  className="flex h-10 w-10 flex-shrink-0 cursor-pointer items-center justify-center rounded-xl border border-[#dc2626]/20 text-[#dc2626] transition-all hover:bg-[#dc2626]/10"
                >
                  {IC.x}
                </button>
              </div>
            ))}
            {variantOptions.length === 0 && (
              <p className="text-[11px] text-[#a8a29e]">Tambah minimal satu variant option (cth: Size: S, M, L)</p>
            )}
          </div>
        )}
      </form>
    </Modal>
  )
}
