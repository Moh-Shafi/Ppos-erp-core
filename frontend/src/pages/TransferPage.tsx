import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { inventoryService } from '@/services/inventory'
import { storeService } from '@/services/store'
import { productService } from '@/services/product'
import type { Product, Store } from '@/types'

/* ---------- Icons ---------- */

const IC = {
  back: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
  ),
  transfer: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
  ),
  store: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M3 9l1-5h16l1 5M4 9v11h16V9M9 20v-6h6v6"/></svg>
  ),
  box: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
  ),
  arrowRight: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
  ),
  arrowDown: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
  ),
  check: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polyline points="20 6 9 17 4 12"/></svg>
  ),
  alert: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
  ),
  success: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
  ),
}

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
        .then((invs) => setAvailableStock(invs.length > 0 ? invs[0].quantity : 0))
        .catch(() => setAvailableStock(0))
    }
  }, [fromStoreId, productId])

  const qtyNum = parseInt(quantity, 10)
  const showWarning = availableStock !== null && !isNaN(qtyNum) && qtyNum > availableStock

  const fromStore = stores.find((s) => s.id === parseInt(fromStoreId))
  const toStore = stores.find((s) => s.id === parseInt(toStoreId))
  const product = products.find((p) => p.id === parseInt(productId))

  const handleSubmit = async () => {
    setErrors({})
    setSuccess('')

    if (!fromStoreId) { setErrors({ from_store_id: ['Toko asal wajib diisi'] }); return }
    if (!toStoreId) { setErrors({ to_store_id: ['Toko tujuan wajib diisi'] }); return }
    if (fromStoreId === toStoreId) { setErrors({ to_store_id: ['Toko tujuan harus berbeda dari asal'] }); return }
    if (!productId) { setErrors({ product_id: ['Produk wajib diisi'] }); return }
    if (!quantity || isNaN(qtyNum) || qtyNum <= 0) { setErrors({ quantity: ['Jumlah harus lebih dari 0'] }); return }

    setLoading(true)
    try {
      const res = await inventoryService.transfer({
        from_store_id: parseInt(fromStoreId, 10),
        to_store_id: parseInt(toStoreId, 10),
        product_id: parseInt(productId, 10),
        quantity: qtyNum,
        note: note || undefined,
      })
      setSuccess(`Transfer berhasil: ${res.out_movement.after_quantity} → ${res.in_movement.after_quantity}`)
      setQuantity('')
      setNote('')
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

  return (
    <DashboardLayout>
      <div className="space-y-5">
        {/* ===== Header ===== */}
        <div className="animate-fade-up flex items-center justify-between">
          <div>
            <h1 className="text-[22px] font-bold tracking-tight text-[#1c1917]">Transfer Stock</h1>
            <p className="mt-1 text-[13px] text-[#78716c]">Pindahkan stok antar toko</p>
          </div>
          <button
            onClick={() => navigate('/inventory')}
            className="flex h-10 cursor-pointer items-center gap-2 rounded-xl border border-[#e7e5e4] bg-white px-4 text-[13px] font-semibold text-[#78716c] transition-all hover:border-[#f54927]/30 hover:bg-[#fef8f6] hover:text-[#f54927] active:scale-[0.97]"
          >
            {IC.back} Kembali ke Inventory
          </button>
        </div>

        <div className="animate-fade-up grid gap-5 lg:grid-cols-3" style={{ animationDelay: '0.05s' }}>
          {/* ===== Form Card ===== */}
          <div className="lg:col-span-2">
            <div className="rounded-2xl border border-[#e7e5e4] bg-white p-6">
              {/* Icon header */}
              <div className="mb-5 flex items-center gap-3">
                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-[#f54927] to-[#ff6b4a] text-white">
                  {IC.transfer}
                </div>
                <div>
                  <p className="text-[15px] font-bold text-[#1c1917]">Form Transfer</p>
                  <p className="text-[11px] text-[#a8a29e]">Isi detail transfer stok</p>
                </div>
              </div>

              {success && (
                <div className="mb-5 flex items-center gap-3 rounded-xl border border-[#16a34a]/20 bg-[#16a34a]/5 p-4">
                  <div className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-[#16a34a] text-white">{IC.check}</div>
                  <p className="text-[13px] font-semibold text-[#16a34a]">{success}</p>
                </div>
              )}

              {/* From → To visual */}
              <div className="mb-5 grid grid-cols-[1fr_auto_1fr] items-center gap-3">
                {/* From */}
                <div className={`rounded-xl border p-4 transition-all ${fromStoreId ? 'border-[#f54927]/30 bg-[#f54927]/5' : 'border-[#e7e5e4] bg-[#fafaf9]'}`}>
                  <p className="mb-2 text-[10px] font-bold tracking-wide text-[#a8a29e] uppercase">Dari Toko</p>
                  <div className="flex items-center gap-2">
                    <span className="text-[#f54927]">{IC.store}</span>
                    <select
                      value={fromStoreId}
                      onChange={(e) => setFromStoreId(e.target.value)}
                      className="h-9 w-full cursor-pointer appearance-none rounded-lg border border-[#e7e5e4] bg-white px-3 text-[13px] font-semibold text-[#1c1917] outline-none transition-all focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
                    >
                      <option value="">Pilih toko asal</option>
                      {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>
                  </div>
                  {fromStore && (
                    <p className="mt-2 text-[11px] font-medium text-[#78716c]">{fromStore.name}</p>
                  )}
                  {errors.from_store_id && <p className="mt-1.5 text-[11px] font-medium text-[#dc2626]">{errors.from_store_id[0]}</p>}
                </div>

                {/* Arrow */}
                <div className="flex flex-col items-center text-[#f54927]">
                  <div className="hidden lg:block">{IC.arrowRight}</div>
                  <div className="lg:hidden">{IC.arrowDown}</div>
                </div>

                {/* To */}
                <div className={`rounded-xl border p-4 transition-all ${toStoreId ? 'border-[#16a34a]/30 bg-[#16a34a]/5' : 'border-[#e7e5e4] bg-[#fafaf9]'}`}>
                  <p className="mb-2 text-[10px] font-bold tracking-wide text-[#a8a29e] uppercase">Ke Toko</p>
                  <div className="flex items-center gap-2">
                    <span className="text-[#16a34a]">{IC.store}</span>
                    <select
                      value={toStoreId}
                      onChange={(e) => setToStoreId(e.target.value)}
                      className="h-9 w-full cursor-pointer appearance-none rounded-lg border border-[#e7e5e4] bg-white px-3 text-[13px] font-semibold text-[#1c1917] outline-none transition-all focus:border-[#16a34a] focus:ring-2 focus:ring-[#16a34a]/10"
                    >
                      <option value="">Pilih toko tujuan</option>
                      {stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>
                  </div>
                  {toStore && (
                    <p className="mt-2 text-[11px] font-medium text-[#78716c]">{toStore.name}</p>
                  )}
                  {errors.to_store_id && <p className="mt-1.5 text-[11px] font-medium text-[#dc2626]">{errors.to_store_id[0]}</p>}
                </div>
              </div>

              {/* Product */}
              <div className="mb-4 space-y-1.5">
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

              {/* Quantity + Note */}
              <div className="mb-5 grid grid-cols-2 gap-4">
                <div className="space-y-1.5">
                  <label className="text-[12px] font-semibold text-[#44403c]">Jumlah <span className="text-[#f54927]">*</span></label>
                  <input
                    value={quantity}
                    onChange={(e) => setQuantity(e.target.value)}
                    placeholder="20"
                    inputMode="numeric"
                    className="h-10 w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] font-semibold text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
                  />
                  {showWarning && (
                    <p className="flex items-center gap-1 text-[11px] font-medium text-[#dc2626]">
                      {IC.alert} Jumlah melebihi stok tersedia ({availableStock})
                    </p>
                  )}
                  {errors.quantity && <p className="text-[11px] font-medium text-[#dc2626]">{errors.quantity[0]}</p>}
                </div>

                <div className="space-y-1.5">
                  <label className="text-[12px] font-semibold text-[#44403c]">Catatan</label>
                  <input
                    value={note}
                    onChange={(e) => setNote(e.target.value)}
                    placeholder="Restock cabang..."
                    className="h-10 w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
                  />
                </div>
              </div>

              {/* Buttons */}
              <div className="flex justify-end gap-2 border-t border-[#f5f5f4] pt-4">
                <button
                  onClick={() => navigate('/inventory')}
                  disabled={loading}
                  className="flex h-10 cursor-pointer items-center gap-2 rounded-xl border border-[#e7e5e4] bg-white px-5 text-[13px] font-semibold text-[#78716c] transition-all hover:bg-[#f5f5f4] disabled:opacity-50"
                >
                  Batal
                </button>
                <button
                  onClick={handleSubmit}
                  disabled={loading || showWarning}
                  className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-5 text-[13px] font-semibold text-white shadow-[0_4px_14px_rgba(245,73,39,0.3)] transition-all hover:shadow-[0_6px_20px_rgba(245,73,39,0.4)] active:scale-[0.97] disabled:cursor-not-allowed disabled:opacity-50"
                >
                  {loading ? (
                    <>
                      <span className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                      Memproses...
                    </>
                  ) : (
                    <>{IC.transfer} Transfer</>
                  )}
                </button>
              </div>
            </div>
          </div>

          {/* ===== Summary Sidebar ===== */}
          <div className="space-y-4">
            {/* Available stock card */}
            <div className="rounded-2xl border border-[#e7e5e4] bg-white p-5">
              <p className="mb-3 text-[11px] font-bold tracking-wide text-[#a8a29e] uppercase">Stok Tersedia</p>
              {availableStock !== null ? (
                <div className="flex items-center gap-3">
                  <div className={`flex h-12 w-12 items-center justify-center rounded-xl ${availableStock > 0 ? 'bg-[#16a34a]/10 text-[#16a34a]' : 'bg-[#dc2626]/10 text-[#dc2626]'}`}>
                    {IC.box}
                  </div>
                  <div>
                    <p className={`text-[24px] font-bold tabular-nums ${availableStock > 0 ? 'text-[#16a34a]' : 'text-[#dc2626]'}`}>{availableStock}</p>
                    <p className="text-[11px] text-[#a8a29e]">unit di toko asal</p>
                  </div>
                </div>
              ) : (
                <p className="text-[12px] text-[#a8a29e]">Pilih toko asal dan produk</p>
              )}
            </div>

            {/* Transfer summary */}
            <div className="rounded-2xl border border-[#e7e5e4] bg-gradient-to-br from-[#f54927]/5 to-transparent p-5">
              <p className="mb-3 text-[11px] font-bold tracking-wide text-[#a8a29e] uppercase">Ringkasan Transfer</p>
              <div className="space-y-2.5">
                <div className="flex items-center justify-between text-[12px]">
                  <span className="text-[#78716c]">Produk</span>
                  <span className="max-w-[60%] truncate font-semibold text-[#1c1917]">{product?.name ?? '-'}</span>
                </div>
                <div className="flex items-center justify-between text-[12px]">
                  <span className="text-[#78716c]">Dari</span>
                  <span className="max-w-[60%] truncate font-semibold text-[#f54927]">{fromStore?.name ?? '-'}</span>
                </div>
                <div className="flex items-center justify-between text-[12px]">
                  <span className="text-[#78716c]">Ke</span>
                  <span className="max-w-[60%] truncate font-semibold text-[#16a34a]">{toStore?.name ?? '-'}</span>
                </div>
                <div className="flex items-center justify-between text-[12px]">
                  <span className="text-[#78716c]">Jumlah</span>
                  <span className="font-bold tabular-nums text-[#1c1917]">{quantity || '-'}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </DashboardLayout>
  )
}
