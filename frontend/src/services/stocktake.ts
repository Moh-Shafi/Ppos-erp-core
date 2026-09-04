import api from '@/lib/api'
import type { StocktakeSession, StocktakeItem, PaginatedResponse } from '@/types'

interface StocktakeParams {
  page?: number
  per_page?: number
  status?: string
  store_id?: number
}

export const stocktakeService = {
  list: (params?: StocktakeParams) =>
    api.get<PaginatedResponse<StocktakeSession>>('/stocktake', { params }).then((r) => r.data),

  get: (id: number) =>
    api.get<{ stocktake: StocktakeSession }>(`/stocktake/${id}`).then((r) => r.data.stocktake),

  create: (data: { store_id: number; note?: string }) =>
    api.post<{ message: string; stocktake: StocktakeSession }>('/stocktake', data).then((r) => r.data),

  start: (id: number) =>
    api.post<{ message: string; stocktake: StocktakeSession }>(`/stocktake/${id}/start`).then((r) => r.data),

  updateItem: (id: number, itemId: number, data: { counted_quantity: number; note?: string }) =>
    api.put<{ message: string; item: StocktakeItem }>(`/stocktake/${id}/items/${itemId}`, data).then((r) => r.data),

  reconcile: (id: number) =>
    api.post<{ message: string; stocktake: StocktakeSession }>(`/stocktake/${id}/reconcile`).then((r) => r.data),

  post: (id: number, reasonId: number) =>
    api.post<{ message: string; stocktake: StocktakeSession; adjustments_created: number }>(`/stocktake/${id}/post`, { reason_id: reasonId }).then((r) => r.data),

  cancel: (id: number) =>
    api.post<{ message: string; stocktake: StocktakeSession }>(`/stocktake/${id}/cancel`).then((r) => r.data),
}
