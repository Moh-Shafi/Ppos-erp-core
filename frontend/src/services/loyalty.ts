import api from '@/lib/api'
import type { CustomerLoyaltyPoints, CustomerLoyaltyTransaction, PaginatedResponse } from '@/types'

export const loyaltyService = {
  getBalance: (customerId: number) =>
    api.get<CustomerLoyaltyPoints>(`/customers/${customerId}/loyalty`).then((r) => r.data),

  getTransactions: (customerId: number, params?: { page?: number; per_page?: number }) =>
    api.get<PaginatedResponse<CustomerLoyaltyTransaction>>(`/customers/${customerId}/loyalty/transactions`, { params }).then((r) => r.data),

  adjust: (customerId: number, data: { points: number; note: string }) =>
    api.post<{ customer_id: number; points_balance: number }>(`/customers/${customerId}/loyalty/adjust`, data).then((r) => r.data),
}
