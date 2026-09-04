import api from '@/lib/api'
import type { PurchaseRequisition, Purchase, PaginatedResponse } from '@/types'

interface RequisitionParams {
  page?: number
  per_page?: number
  status?: string
  store_id?: number
}

interface RequisitionItemData {
  product_id: number
  quantity: number
  estimated_cost?: number
  note?: string
}

interface RequisitionData {
  store_id: number
  note?: string
  items: RequisitionItemData[]
}

interface ConvertData {
  supplier_id: number
  items: { product_id: number; quantity: number; unit_cost: number }[]
}

export const requisitionService = {
  list: (params?: RequisitionParams) =>
    api.get<PaginatedResponse<PurchaseRequisition>>('/requisitions', { params }).then((r) => r.data),

  show: (id: number) =>
    api.get<PurchaseRequisition>(`/requisitions/${id}`).then((r) => r.data),

  create: (data: RequisitionData) =>
    api.post<PurchaseRequisition>('/requisitions', data).then((r) => r.data),

  update: (id: number, data: Partial<RequisitionData>) =>
    api.put<PurchaseRequisition>(`/requisitions/${id}`, data).then((r) => r.data),

  delete: (id: number) =>
    api.delete(`/requisitions/${id}`).then((r) => r.data),

  submit: (id: number) =>
    api.post<PurchaseRequisition>(`/requisitions/${id}/submit`).then((r) => r.data),

  approve: (id: number) =>
    api.post<PurchaseRequisition>(`/requisitions/${id}/approve`).then((r) => r.data),

  reject: (id: number, rejection_reason: string) =>
    api.post<PurchaseRequisition>(`/requisitions/${id}/reject`, { rejection_reason }).then((r) => r.data),

  cancel: (id: number) =>
    api.post<PurchaseRequisition>(`/requisitions/${id}/cancel`).then((r) => r.data),

  convert: (id: number, data: ConvertData) =>
    api.post<Purchase>(`/requisitions/${id}/convert`, data).then((r) => r.data),
}
