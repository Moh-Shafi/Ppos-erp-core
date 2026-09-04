import api from '@/lib/api'
import type { Sale, Payment, PaginatedResponse, CheckoutData, PaymentInput, SaleRefund, RefundInput } from '@/types'

interface SaleParams {
  page?: number
  per_page?: number
  search?: string
  status?: string
  payment_status?: string
  store_id?: number
  customer_id?: number
  date_from?: string
  date_to?: string
}

export const saleService = {
  list: (params?: SaleParams) =>
    api.get<PaginatedResponse<Sale>>('/sales', { params }).then((r) => r.data),

  show: (id: number) =>
    api.get<Sale>(`/sales/${id}`).then((r) => r.data),

  checkout: (data: CheckoutData) =>
    api.post<Sale>('/sales/checkout', data).then((r) => r.data),

  cancel: (id: number) =>
    api.post<Sale>(`/sales/${id}/cancel`).then((r) => r.data),

  listPayments: (id: number) =>
    api.get<Payment[]>(`/sales/${id}/payments`).then((r) => r.data),

  addPayment: (id: number, data: PaymentInput) =>
    api.post<Payment>(`/sales/${id}/payments`, data).then((r) => r.data),

  listRefunds: (id: number) =>
    api.get<SaleRefund[]>(`/sales/${id}/refunds`).then((r) => r.data),

  showRefund: (id: number, refundId: number) =>
    api.get<SaleRefund>(`/sales/${id}/refunds/${refundId}`).then((r) => r.data),

  processRefund: (id: number, data: RefundInput) =>
    api.post<SaleRefund>(`/sales/${id}/refunds`, data).then((r) => r.data),
}
