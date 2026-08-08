import api from '@/lib/api'
import type { Purchase, PaginatedResponse } from '@/types'

interface PurchaseParams {
  page?: number
  per_page?: number
  search?: string
  status?: string
  supplier_id?: number
  store_id?: number
}

interface PurchaseItemData {
  product_id: number
  quantity: number
  unit_cost: number
  discount?: number
  tax?: number
}

interface PurchaseData {
  supplier_id: number
  store_id: number
  purchase_date: string
  expected_date?: string
  notes?: string
  items: PurchaseItemData[]
}

export const purchaseService = {
  list: (params?: PurchaseParams) =>
    api.get<PaginatedResponse<Purchase>>('/purchases', { params }).then((r) => r.data),

  show: (id: number) =>
    api.get<Purchase>(`/purchases/${id}`).then((r) => r.data),

  create: (data: PurchaseData) =>
    api.post<Purchase>('/purchases', data).then((r) => r.data),

  update: (id: number, data: Partial<PurchaseData>) =>
    api.put<Purchase>(`/purchases/${id}`, data).then((r) => r.data),

  delete: (id: number) =>
    api.delete(`/purchases/${id}`).then((r) => r.data),

  order: (id: number) =>
    api.post<Purchase>(`/purchases/${id}/order`).then((r) => r.data),

  receive: (id: number) =>
    api.post<Purchase>(`/purchases/${id}/receive`).then((r) => r.data),

  cancel: (id: number) =>
    api.post<Purchase>(`/purchases/${id}/cancel`).then((r) => r.data),
}
