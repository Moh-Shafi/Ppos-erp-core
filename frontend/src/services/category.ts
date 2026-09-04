import api from '@/lib/api'
import type { Category, PaginatedResponse } from '@/types'

interface CategoryParams {
  page?: number
  per_page?: number
  search?: string
  is_active?: boolean
}

interface CategoryFormData {
  name: string
  description?: string
  is_active?: boolean
  parent_id?: number | null
  sort_order?: number
}

export const categoryService = {
  list: (params?: CategoryParams) =>
    api.get<PaginatedResponse<Category>>('/categories', { params }).then((r) => r.data),

  all: () =>
    api.get<PaginatedResponse<Category>>('/categories', { params: { per_page: 100 } }).then((r) => r.data.data),

  tree: () =>
    api.get<{ tree: Category[] }>('/categories/tree').then((r) => r.data.tree),

  show: (id: number) =>
    api.get<{ category: Category }>(`/categories/${id}`).then((r) => r.data.category),

  create: (data: CategoryFormData) =>
    api.post<{ message: string; category: Category }>('/categories', data).then((r) => r.data.category),

  update: (id: number, data: Partial<CategoryFormData>) =>
    api.put<{ message: string; category: Category }>(`/categories/${id}`, data).then((r) => r.data.category),

  delete: (id: number) =>
    api.delete<{ message: string }>(`/categories/${id}`).then((r) => r.data),
}
