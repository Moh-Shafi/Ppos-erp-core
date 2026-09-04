import api from '@/lib/api'
import type { SaleRefund, RefundInput } from '@/types'

export const refundService = {
  list: (saleId: number) =>
    api.get<SaleRefund[]>(`/sales/${saleId}/refunds`).then((r) => r.data),

  show: (saleId: number, refundId: number) =>
    api.get<SaleRefund>(`/sales/${saleId}/refunds/${refundId}`).then((r) => r.data),

  process: (saleId: number, data: RefundInput) =>
    api.post<SaleRefund>(`/sales/${saleId}/refunds`, data).then((r) => r.data),
}
