import api from '@/lib/api'
import type { GoodsReceiptNote, PaginatedResponse } from '@/types'

interface GrnParams {
  page?: number
  per_page?: number
  status?: string
  supplier_id?: number
  store_id?: number
}

interface GrnItemData {
  product_id: number
  quantity_ordered?: number
  unit_cost: number
}

interface GrnData {
  store_id: number
  supplier_id: number
  note?: string
  items: GrnItemData[]
}

interface ReceiveItemData {
  id: number
  quantity_received: number
  quantity_rejected?: number
  rejection_reason?: string
  batch_id?: number | null
  expiry_date?: string | null
  note?: string
}

export const grnService = {
  list: (params?: GrnParams) =>
    api.get<PaginatedResponse<GoodsReceiptNote>>('/grns', { params }).then((r) => r.data),

  show: (id: number) =>
    api.get<GoodsReceiptNote>(`/grns/${id}`).then((r) => r.data),

  create: (data: GrnData) =>
    api.post<GoodsReceiptNote>('/grns', data).then((r) => r.data),

  createFromPo: (poId: number, note?: string) =>
    api.post<GoodsReceiptNote>(`/grns/from-po/${poId}`, { note }).then((r) => r.data),

  receive: (id: number, items: ReceiveItemData[]) =>
    api.post<GoodsReceiptNote>(`/grns/${id}/receive`, { items }).then((r) => r.data),

  cancel: (id: number) =>
    api.post<GoodsReceiptNote>(`/grns/${id}/cancel`).then((r) => r.data),
}
