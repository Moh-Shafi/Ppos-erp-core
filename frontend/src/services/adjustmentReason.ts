import api from '@/lib/api'
import type { StockAdjustmentReason } from '@/types'

interface AdjustmentReasonParams {
  is_active?: boolean
  category?: string
}

export const adjustmentReasonService = {
  list: (params?: AdjustmentReasonParams) =>
    api.get<{ data: StockAdjustmentReason[] }>('/adjustment-reasons', { params }).then((r) => r.data.data),

  create: (data: { name: string; category: string; is_active?: boolean }) =>
    api.post<{ message: string; reason: StockAdjustmentReason }>('/adjustment-reasons', data).then((r) => r.data),

  update: (id: number, data: Partial<{ name: string; is_active: boolean }>) =>
    api.put<{ message: string; reason: StockAdjustmentReason }>(`/adjustment-reasons/${id}`, data).then((r) => r.data),

  delete: (id: number) =>
    api.delete<{ message: string }>(`/adjustment-reasons/${id}`).then((r) => r.data),
}
