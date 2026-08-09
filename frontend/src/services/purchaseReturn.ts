import api from '@/lib/api'
import type { PurchaseReturn, PaginatedResponse } from '@/types'

export const purchaseReturnService = {
  list(params?: Record<string, string | number>): Promise<PaginatedResponse<PurchaseReturn>> {
    return api.get('/purchase-returns', { params }).then((r) => r.data)
  },

  show(id: number): Promise<PurchaseReturn> {
    return api.get(`/purchase-returns/${id}`).then((r) => r.data)
  },

  create(data: {
    purchase_id: number
    store_id?: number
    return_date: string
    notes?: string
    items: Array<{
      product_id: number
      quantity: number
      unit_cost: number
      discount?: number
      tax?: number
    }>
  }): Promise<PurchaseReturn> {
    return api.post('/purchase-returns', data).then((r) => r.data)
  },

  complete(id: number): Promise<PurchaseReturn> {
    return api.post(`/purchase-returns/${id}/complete`).then((r) => r.data)
  },

  cancel(id: number): Promise<PurchaseReturn> {
    return api.post(`/purchase-returns/${id}/cancel`).then((r) => r.data)
  },

  delete(id: number): Promise<void> {
    return api.delete(`/purchase-returns/${id}`).then((r) => r.data)
  },
}
