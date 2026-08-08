import api from '@/lib/api'
import type { Product, PaginatedResponse } from '@/types'

interface ProductParams {
  page?: number
  per_page?: number
  search?: string
  category_id?: number
  is_active?: boolean
}

interface ProductFormData {
  category_id: number
  name: string
  sku?: string
  barcode?: string
  description?: string
  cost_price: number
  selling_price: number
  unit: string
  image?: string
  is_active?: boolean
}

export const productService = {
  list: (params?: ProductParams) =>
    api.get<PaginatedResponse<Product>>('/products', { params }).then((r) => r.data),

  show: (id: number) =>
    api.get<{ product: Product }>(`/products/${id}`).then((r) => r.data.product),

  create: (data: ProductFormData) =>
    api.post<{ message: string; product: Product }>('/products', data).then((r) => r.data.product),

  update: (id: number, data: Partial<ProductFormData>) =>
    api.put<{ message: string; product: Product }>(`/products/${id}`, data).then((r) => r.data.product),

  delete: (id: number) =>
    api.delete<{ message: string }>(`/products/${id}`).then((r) => r.data),
}
