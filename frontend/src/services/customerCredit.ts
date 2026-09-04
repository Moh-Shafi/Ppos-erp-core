import api from '@/lib/api'
import type { CustomerCreditTransaction, PaginatedResponse } from '@/types'

export interface CreditBalance {
  customer_id: number
  outstanding_balance: number
  credit_limit: number | null
}

export interface CreditCheckResult {
  allowed: boolean
  outstanding_balance: number
  credit_limit: number | null
  remaining: number | null
}

export const customerCreditService = {
  getBalance: (customerId: number) =>
    api.get<CreditBalance>(`/customers/${customerId}/credit`).then((r) => r.data),

  getTransactions: (customerId: number, params?: { page?: number; per_page?: number }) =>
    api.get<PaginatedResponse<CustomerCreditTransaction>>(`/customers/${customerId}/credit/transactions`, { params }).then((r) => r.data),

  adjust: (customerId: number, data: { amount: number; note: string }) =>
    api.post<{ customer_id: number; outstanding_balance: number }>(`/customers/${customerId}/credit/adjust`, data).then((r) => r.data),

  check: (customerId: number, amount: number) =>
    api.post<CreditCheckResult>(`/customers/${customerId}/credit/check`, { amount }).then((r) => r.data),
}
