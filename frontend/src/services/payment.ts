import api from '@/lib/api'
import type {
  CashDrawerSession,
  GatewayChargeInput,
  Payment,
  PaymentGatewayAccount,
  PaymentSettlement,
  PaymentSummary,
  ReconciliationReport,
  RefundPaymentInput,
} from '@/types'

export const paymentService = {
  getGatewayAccount: () =>
    api.get<{ data: PaymentGatewayAccount | null }>('/payment-gateway/account').then(r => r.data.data),

  provision: (data: { business_name?: string; business_email?: string; business_type?: string }) =>
    api.post<{ data: PaymentGatewayAccount }>('/payment-gateway/provision', data).then(r => r.data.data),

  createCharge: (saleId: number, data: GatewayChargeInput) =>
    api.post<{ data: Payment }>(`/sales/${saleId}/gateway-charge`, data).then(r => r.data.data),

  getChargeStatus: (saleId: number, chargeId: number) =>
    api.get<{ data: Payment }>(`/sales/${saleId}/gateway-charge/${chargeId}`).then(r => r.data.data),

  refund: (paymentId: number, data: RefundPaymentInput) =>
    api.post<{ data: { refund_id: string; status: string; amount: string; payment_id: number; payment_refund_status: string } }>(`/payments/${paymentId}/refund`, data).then(r => r.data.data),

  list: (params?: Record<string, unknown>) =>
    api.get<{ data: Payment[] } & { current_page: number; last_page: number; per_page: number; total: number }>('/payments', { params }).then(r => r.data),

  show: (paymentId: number) =>
    api.get<{ data: Payment }>(`/payments/${paymentId}`).then(r => r.data.data),

  summary: (params?: Record<string, unknown>) =>
    api.get<{ data: PaymentSummary }>('/payments/summary', { params }).then(r => r.data.data),

  listSettlements: (params?: Record<string, unknown>) =>
    api.get<{ data: PaymentSettlement[] } & { current_page: number; last_page: number; per_page: number; total: number }>('/payment-gateway/settlements', { params }).then(r => r.data),

  reconcile: (data: { date_from: string; date_to: string }) =>
    api.post<{ data: ReconciliationReport }>('/payment-gateway/reconcile', data).then(r => r.data.data),

  listCashDrawerSessions: (params?: Record<string, unknown>) =>
    api.get<{ data: CashDrawerSession[] } & { current_page: number; last_page: number; per_page: number; total: number }>('/cash-drawer/sessions', { params }).then(r => r.data),

  openCashDrawer: (data: { store_id: number; opening_amount: number; notes?: string }) =>
    api.post<{ data: CashDrawerSession }>('/cash-drawer/open', data).then(r => r.data.data),

  closeCashDrawer: (id: number, data: { closing_amount: number; notes?: string }) =>
    api.post<{ data: CashDrawerSession }>(`/cash-drawer/${id}/close`, data).then(r => r.data.data),
}
