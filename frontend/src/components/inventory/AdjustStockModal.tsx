import { useState, useEffect } from 'react'
import { Modal } from '@/components/ui/Modal'
import { inventoryService } from '@/services/inventory'
import { productService } from '@/services/product'
import { storeService } from '@/services/store'
import type { Product, Store } from '@/types'

/* ---------- Icons ---------- */

const IC = {
  adjust: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
  ),
  store: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M3 9l1-5h16l1 5M4 9v11h16V9M9 20v-6h6v6"/></svg>
  ),
  box: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
  ),
  plus: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
  ),
  minus: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><line x1="5" y1="12" x2="19" y2="12"/></svg>
  ),
  check: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polyline points="20 6 9 17 4 12"/></svg>
  ),
  arrowUp: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
  ),
  arrowDown: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
  ),
}

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

    if (!storeId) { setErrors({ store_id: ['Toko wajib diisi'] }); return }
    if (!productId) { setErrors({ product_id: ['Produk wajib diisi'] }); return }
    if (!delta || isNaN(deltaNum) || deltaNum === 0) { setErrors({ delta: ['Penyesuaian harus angka bukan nol'] }); return }

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

  const deltaNum = parseInt(delta, 10)
  const isPositive = !isNaN(deltaNum) && deltaNum > 0
  const isNegative = !isNaN(deltaNum) && deltaNum < 0

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Adjust Stock"
      footer={
        <div className="flex w-full items-center justify-between">
          <button
            onClick={onClose}
            disabled={loading}
            className="flex h-10 cursor-pointer items-center gap-2 rounded-xl border border-[#e7e5e4] bg-white px-5 text-[13px] font-semibold text-[#78716c] transition-all hover:bg-[#f5f5f4] disabled:opacity-50"
          >
            Batal
          </button>
          <button
            onClick={handleSubmit}
            disabled={loading}
            className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-5 text-[13px] font-semibold text-white shadow-[0_4px_14px_rgba(245,73,39,0.3)] transition-all hover:shadow-[0_6px_20px_rgba(245,73,39,0.4)] active:scale-[0.97] disabled:cursor-not-allowed disabled:opacity-50"
          >
            {loading ? (
              <>
                <span className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                Menyesuaikan...
              </>
            ) : (
              <>{IC.check} Sesuaikan</>
            )}
          </button>
        </div>
      }
    >
      <div className="space-y-5">
        {/* Icon header */}
        <div className="flex items-center gap-3 rounded-xl bg-gradient-to-r from-[#f54927]/5 to-transparent p-4">
          <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-[#f54927] to-[#ff6b4a] text-white">
            {IC.adjust}
          </div>
          <div>
            <p className="text-[13px] font-bold text-[#1c1917]">Penyesuaian Stok</p>
            <p className="text-[11px] text-[#a8a29e]">Tambah atau kurangi stok produk</p>
          </div>
        </div>

        {/* Store */}
        <div className="space-y-1.5">
          <label className="flex items-center gap-1.5 text-[12px] font-semibold text-[#44403c]">
            {IC.store} Toko <span className="text-[#f54927]">*</span>
          </label>
          <select
            value={storeId}
            onChange={(e) => setStoreId(e.target.value)}
            className="h-10 w-full cursor-pointer appearance-none rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
          >
            <option value="">Pilih toko</option>
            {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
          </select>
          {errors.store_id && <p className="text-[11px] font-medium text-[#dc2626]">{errors.store_id[0]}</p>}
        </div>

        {/* Product */}
        <div className="space-y-1.5">
          <label className="flex items-center gap-1.5 text-[12px] font-semibold text-[#44403c]">
            {IC.box} Produk <span className="text-[#f54927]">*</span>
          </label>
          <select
            value={productId}
            onChange={(e) => setProductId(e.target.value)}
            className="h-10 w-full cursor-pointer appearance-none rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
          >
            <option value="">Pilih produk</option>
            {products.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
          </select>
          {errors.product_id && <p className="text-[11px] font-medium text-[#dc2626]">{errors.product_id[0]}</p>}
        </div>

        {/* Delta with quick buttons */}
        <div className="space-y-1.5">
          <label className="text-[12px] font-semibold text-[#44403c]">Penyesuaian (+/-) <span className="text-[#f54927]">*</span></label>
          <div className="relative">
            <div className={`pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 ${isPositive ? 'text-[#16a34a]' : isNegative ? 'text-[#dc2626]' : 'text-[#a8a29e]'}`}>
              {isPositive ? IC.arrowUp : isNegative ? IC.arrowDown : IC.adjust}
            </div>
            <input
              value={delta}
              onChange={(e) => setDelta(e.target.value)}
              placeholder="+10 atau -5"
              inputMode="numeric"
              className={`h-10 w-full rounded-xl border bg-white pr-4 pl-10 text-[14px] font-bold tabular-nums text-[#1c1917] outline-none transition-all placeholder:font-normal placeholder:text-[#c2bdb8] focus:ring-2 ${
                isPositive ? 'border-[#16a34a]/40 focus:border-[#16a34a] focus:ring-[#16a34a]/10'
                : isNegative ? 'border-[#dc2626]/40 focus:border-[#dc2626] focus:ring-[#dc2626]/10'
                : 'border-[#e7e5e4] focus:border-[#f54927] focus:ring-[#f54927]/10'
              }`}
            />
          </div>
          {/* Quick adjust buttons */}
          <div className="flex gap-1.5">
            {[-10, -5, -1, 1, 5, 10].map((v) => (
              <button
                key={v}
                type="button"
                onClick={() => setDelta(String(v))}
                className={`flex h-7 flex-1 cursor-pointer items-center justify-center rounded-lg border text-[11px] font-bold transition-all active:scale-95 ${
                  v > 0
                    ? 'border-[#16a34a]/20 text-[#16a34a] hover:bg-[#16a34a]/10'
                    : 'border-[#dc2626]/20 text-[#dc2626] hover:bg-[#dc2626]/10'
                }`}
              >
                {v > 0 ? `+${v}` : v}
              </button>
            ))}
          </div>
          {errors.delta && <p className="text-[11px] font-medium text-[#dc2626]">{errors.delta[0]}</p>}
        </div>

        {/* Note */}
        <div className="space-y-1.5">
          <label className="text-[12px] font-semibold text-[#44403c]">Catatan</label>
          <textarea
            value={note}
            onChange={(e) => setNote(e.target.value)}
            placeholder="Koreksi hasil stock opname..."
            rows={2}
            className="w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 py-2.5 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
          />
        </div>
      </div>
    </Modal>
  )
}
