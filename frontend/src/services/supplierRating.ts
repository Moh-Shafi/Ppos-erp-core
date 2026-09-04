import api from '@/lib/api'
import type { SupplierRating, PaginatedResponse } from '@/types'

export interface RatingData {
  rating: number
  criteria: 'quality' | 'delivery' | 'pricing' | 'service' | 'overall'
  note?: string
}

export const supplierRatingService = {
  list: (supplierId: number, params?: { page?: number; per_page?: number }) =>
    api.get<PaginatedResponse<SupplierRating>>(`/suppliers/${supplierId}/ratings`, { params }).then((r) => r.data),

  create: (supplierId: number, data: RatingData) =>
    api.post<SupplierRating>(`/suppliers/${supplierId}/ratings`, data).then((r) => r.data),

  update: (supplierId: number, ratingId: number, data: Partial<RatingData>) =>
    api.put<SupplierRating>(`/suppliers/${supplierId}/ratings/${ratingId}`, data).then((r) => r.data),

  delete: (supplierId: number, ratingId: number) =>
    api.delete(`/suppliers/${supplierId}/ratings/${ratingId}`).then((r) => r.data),
}
