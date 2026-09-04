import api from '@/lib/api'
import type { Product, PaginatedResponse } from '@/types'

interface ProductParams {
  page?: number
  per_page?: number
  search?: string
  category_id?: number
  is_active?: boolean
  has_variants?: boolean
  is_trackable?: boolean
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
  has_variants?: boolean
  is_trackable?: boolean
  min_stock?: number | null
  base_unit_id?: number | null
  purchase_unit_id?: number | null
  images?: { url: string; sort_order?: number }[]
  barcodes?: string[]
  variant_options?: { name: string; sort_order?: number; values: { value: string; sort_order?: number }[] }[]
  variants?: {
    option_value_ids: (number | string)[]
    sku?: string
    barcode?: string
    price_override?: number | null
    cost_price_override?: number | null
    is_active?: boolean
  }[]
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

  export: () =>
    api.get('/products/export', { responseType: 'blob' }).then((r) => r.data),

  import: (file: File) => {
    const formData = new FormData()
    formData.append('file', file)
    return api.post<{ created: number; updated: number; errors: { row: number; sku: string | null; error: string }[] }>('/products/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }).then((r) => r.data)
  },

  lookup: (barcode: string) =>
    api.get<{ product: Product }>(`/products/lookup`, { params: { barcode } }).then((r) => r.data.product),

  uploadImage: (file: File) => {
    const formData = new FormData()
    formData.append('image', file)
    return api.post<{ message: string; url: string; path: string }>('/products/upload-image', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }).then((r) => r.data)
  },

  generateVariants: (id: number, optionValueIds: (number | string)[][]) =>
    api.post<{ combinations: { option_value_ids: (number | string)[]; label: string }[] }>(`/products/${id}/variants/generate`, {
      option_value_ids: optionValueIds,
    }).then((r) => r.data),
}
