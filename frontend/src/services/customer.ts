import api from '@/lib/api'
import type { Customer, PaginatedResponse } from '@/types'

interface CustomerParams {
  page?: number
  per_page?: number
  search?: string
  is_active?: boolean
}

interface CustomerData {
  name: string
  phone?: string
  email?: string
  address?: string
  notes?: string
  is_active?: boolean
}

export const customerService = {
  list: (params?: CustomerParams) =>
    api.get<PaginatedResponse<Customer>>('/customers', { params }).then((r) => r.data),

  show: (id: number) =>
    api.get<Customer>(`/customers/${id}`).then((r) => r.data),

  create: (data: CustomerData) =>
    api.post<Customer>('/customers', data).then((r) => r.data),

  update: (id: number, data: Partial<CustomerData>) =>
    api.put<Customer>(`/customers/${id}`, data).then((r) => r.data),

  delete: (id: number) =>
    api.delete(`/customers/${id}`).then((r) => r.data),
}
