import api from '@/lib/api'
import type { PaginatedResponse } from '@/types'

export interface Promotion {
  id: number
  name: string
  type: string
  value: number
  start_date: string
  end_date: string
  is_active: boolean
  usage_limit: number | null
  usage_count: number
}

export interface LoyaltyProgram {
  id: number
  name: string
  points_per_amount: number
  is_active: boolean
  tiers: LoyaltyTier[]
}

export interface LoyaltyTier {
  id: number
  name: string
  points_threshold: number
  multiplier: number
}

export const retailService = {
  // Promotions
  getPromotions: (params?: { page?: number; per_page?: number; active_only?: boolean }) =>
    api.get<PaginatedResponse<Promotion>>('/promotions', { params }).then((r) => r.data),

  createPromotion: (data: Record<string, unknown>) =>
    api.post<{ data: Promotion }>('/promotions', data).then((r) => r.data),

  updatePromotion: (id: number, data: Partial<Promotion>) =>
    api.put<{ data: Promotion }>(`/promotions/${id}`, data).then((r) => r.data),

  deletePromotion: (id: number) =>
    api.delete(`/promotions/${id}`),

  activatePromotion: (id: number) =>
    api.post(`/promotions/${id}/activate`),

  deactivatePromotion: (id: number) =>
    api.post(`/promotions/${id}/deactivate`),

  validateCart: (data: { items: unknown[]; customer_id?: number }) =>
    api.post<{ data: { discount: number; promotions: Promotion[] } }>('/promotions/validate', data).then((r) => r.data),

  // Loyalty
  getLoyaltyPrograms: (params?: { page?: number; per_page?: number }) =>
    api.get<PaginatedResponse<LoyaltyProgram>>('/loyalty-programs', { params }).then((r) => r.data),

  createLoyaltyProgram: (data: Record<string, unknown>) =>
    api.post<{ data: LoyaltyProgram }>('/loyalty-programs', data).then((r) => r.data),

  updateLoyaltyProgram: (id: number, data: Record<string, unknown>) =>
    api.put<{ data: LoyaltyProgram }>(`/loyalty-programs/${id}`, data).then((r) => r.data),

  deleteLoyaltyProgram: (id: number) =>
    api.delete(`/loyalty-programs/${id}`),

  getLoyaltyBalance: (customerId: number) =>
    api.get<{ data: { points: number; tier: string | null } }>(`/customers/${customerId}/loyalty`).then((r) => r.data),

  redeemLoyalty: (customerId: number, data: { points: number; sale_id?: number }) =>
    api.post(`/customers/${customerId}/loyalty/redeem`, data).then((r) => r.data),

  // Price tags
  getPriceTagTemplates: (params?: { page?: number; per_page?: number }) =>
    api.get('/price-tags/templates', { params }).then((r) => r.data),

  createPriceTagTemplate: (data: Record<string, unknown>) =>
    api.post('/price-tags/templates', data).then((r) => r.data),

  generatePriceTags: (templateId: number, params?: { store_id?: number }) =>
    api.get(`/price-tags/templates/${templateId}/generate`, { params, responseType: 'blob' }).then((r) => r.data),
}
