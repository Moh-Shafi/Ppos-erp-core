import api from '@/lib/api'
import type { Category, PaginatedResponse } from '@/types'

interface CategoryParams {
  page?: number
  per_page?: number
  search?: string
  is_active?: boolean
}

export const categoryService = {
  list: (params?: CategoryParams) =>
    api.get<PaginatedResponse<Category>>('/categories', { params }).then((r) => r.data),

  all: () =>
    api.get<PaginatedResponse<Category>>('/categories', { params: { per_page: 100 } }).then((r) => r.data.data),

  show: (id: number) =>
    api.get<{ category: Category }>(`/categories/${id}`).then((r) => r.data.category),

  create: (data: { name: string; description?: string; is_active?: boolean }) =>
    api.post<{ message: string; category: Category }>('/categories', data).then((r) => r.data.category),

  update: (id: number, data: Partial<{ name: string; description: string; is_active: boolean }>) =>
    api.put<{ message: string; category: Category }>(`/categories/${id}`, data).then((r) => r.data.category),

  delete: (id: number) =>
    api.delete<{ message: string }>(`/categories/${id}`).then((r) => r.data),
}
