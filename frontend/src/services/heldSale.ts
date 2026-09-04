import api from '@/lib/api'
import type { HeldSale, HoldSaleInput, PaginatedResponse } from '@/types'

export const heldSaleService = {
  list: (params?: { store_id?: number; status?: string }) =>
    api.get<PaginatedResponse<HeldSale>>('/held-sales', { params }).then((r) => r.data),

  hold: (data: HoldSaleInput) =>
    api.post<HeldSale>('/held-sales', data).then((r) => r.data),

  recall: (id: number) =>
    api.post<HeldSale>(`/held-sales/${id}/recall`).then((r) => r.data),

  delete: (id: number) =>
    api.delete(`/held-sales/${id}`).then((r) => r.data),
}
