import api from '@/lib/api'
import type { Supplier, PaginatedResponse } from '@/types'

interface SupplierParams {
  page?: number
  per_page?: number
  search?: string
  is_active?: boolean
}

interface SupplierData {
  name: string
  contact_person?: string
  phone?: string
  email?: string
  address?: string
  tax_number?: string
  notes?: string
  is_active?: boolean
}

export const supplierService = {
  list: (params?: SupplierParams) =>
    api.get<PaginatedResponse<Supplier>>('/suppliers', { params }).then((r) => r.data),

  show: (id: number) =>
    api.get<Supplier>(`/suppliers/${id}`).then((r) => r.data),

  create: (data: SupplierData) =>
    api.post<Supplier>('/suppliers', data).then((r) => r.data),

  update: (id: number, data: Partial<SupplierData>) =>
    api.put<Supplier>(`/suppliers/${id}`, data).then((r) => r.data),

  delete: (id: number) =>
    api.delete(`/suppliers/${id}`).then((r) => r.data),
}
