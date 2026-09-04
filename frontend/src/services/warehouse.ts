import api from '@/lib/api'
import type { Warehouse, WarehouseStock, PaginatedResponse } from '@/types'

interface WarehouseParams {
  page?: number
  per_page?: number
  search?: string
  is_active?: boolean
}

interface WarehouseStockParams {
  page?: number
  per_page?: number
  search?: string
  batch_id?: number
  low_stock?: boolean
}

interface AdjustStockData {
  product_id: number
  delta: number
  reason_id?: number
  batch_id?: number
  note?: string
}

export const warehouseService = {
  list: (params?: WarehouseParams) =>
    api.get<PaginatedResponse<Warehouse>>('/warehouses', { params }).then((r) => r.data),

  get: (id: number) =>
    api.get<{ warehouse: Warehouse }>(`/warehouses/${id}`).then((r) => r.data.warehouse),

  create: (data: { name: string; address?: string; phone?: string; is_active?: boolean }) =>
    api.post<{ message: string; warehouse: Warehouse }>('/warehouses', data).then((r) => r.data),

  update: (id: number, data: Partial<{ name: string; address: string; phone: string; is_active: boolean }>) =>
    api.put<{ message: string; warehouse: Warehouse }>(`/warehouses/${id}`, data).then((r) => r.data),

  delete: (id: number) =>
    api.delete<{ message: string }>(`/warehouses/${id}`).then((r) => r.data),

  getStock: (id: number, params?: WarehouseStockParams) =>
    api.get<PaginatedResponse<WarehouseStock>>(`/warehouses/${id}/stock`, { params }).then((r) => r.data),

  adjustStock: (id: number, data: AdjustStockData) =>
    api.post<{ message: string; movement: unknown }>(`/warehouses/${id}/adjust`, data).then((r) => r.data),
}
