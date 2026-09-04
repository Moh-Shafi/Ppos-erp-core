import api from '@/lib/api'
import type { PriceList, PriceListItem, PaginatedResponse } from '@/types'

interface PriceListParams {
  page?: number
  per_page?: number
  search?: string
  is_active?: boolean
}

interface PriceListFormData {
  name: string
  description?: string
  is_default?: boolean
  is_active?: boolean
}

interface PriceListItemFormData {
  product_id: number
  variant_id?: number | null
  price: number
}

export const priceListService = {
  list: (params?: PriceListParams) =>
    api.get<PaginatedResponse<PriceList>>('/price-lists', { params }).then((r) => r.data),

  show: (id: number) =>
    api.get<{ price_list: PriceList }>(`/price-lists/${id}`).then((r) => r.data.price_list),

  create: (data: PriceListFormData) =>
    api.post<{ message: string; price_list: PriceList }>('/price-lists', data).then((r) => r.data.price_list),

  update: (id: number, data: Partial<PriceListFormData>) =>
    api.put<{ message: string; price_list: PriceList }>(`/price-lists/${id}`, data).then((r) => r.data.price_list),

  delete: (id: number) =>
    api.delete<{ message: string }>(`/price-lists/${id}`).then((r) => r.data),

  addItem: (priceListId: number, data: PriceListItemFormData) =>
    api.post<{ message: string; item: PriceListItem }>(`/price-lists/${priceListId}/items`, data).then((r) => r.data.item),

  updateItem: (priceListId: number, itemId: number, data: Partial<PriceListItemFormData>) =>
    api.put<{ message: string; item: PriceListItem }>(`/price-lists/${priceListId}/items/${itemId}`, data).then((r) => r.data.item),

  deleteItem: (priceListId: number, itemId: number) =>
    api.delete<{ message: string }>(`/price-lists/${priceListId}/items/${itemId}`).then((r) => r.data),
}
