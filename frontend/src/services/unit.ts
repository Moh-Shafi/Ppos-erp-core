import api from '@/lib/api'
import type { Unit, UnitConversion, PaginatedResponse } from '@/types'

interface UnitParams {
  page?: number
  per_page?: number
  search?: string
}

interface UnitFormData {
  name: string
  symbol: string
  is_base_unit?: boolean
}

interface ConversionFormData {
  from_unit_id: number
  to_unit_id: number
  factor: number
}

export const unitService = {
  list: (params?: UnitParams) =>
    api.get<PaginatedResponse<Unit>>('/units', { params }).then((r) => r.data),

  show: (id: number) =>
    api.get<{ unit: Unit }>(`/units/${id}`).then((r) => r.data.unit),

  create: (data: UnitFormData) =>
    api.post<{ message: string; unit: Unit }>('/units', data).then((r) => r.data.unit),

  update: (id: number, data: Partial<UnitFormData>) =>
    api.put<{ message: string; unit: Unit }>(`/units/${id}`, data).then((r) => r.data.unit),

  delete: (id: number) =>
    api.delete<{ message: string }>(`/units/${id}`).then((r) => r.data),

  createConversion: (data: ConversionFormData) =>
    api.post<{ message: string; conversion: UnitConversion }>('/units/conversions', data).then((r) => r.data.conversion),

  deleteConversion: (id: number) =>
    api.delete<{ message: string }>(`/units/conversions/${id}`).then((r) => r.data),
}
