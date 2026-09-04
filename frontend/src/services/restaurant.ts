import api from '@/lib/api'
import type { PaginatedResponse } from '@/types'

export interface TableArea {
  id: number
  name: string
}

export interface Table {
  id: number
  table_area_id: number
  name: string
  capacity: number
  status: string
  qr_token: string | null
}

export interface Reservation {
  id: number
  customer_id: number
  table_id: number | null
  reservation_date: string
  start_time: string
  end_time: string
  party_size: number
  status: string
  notes: string | null
}

export const restaurantService = {
  // Table areas
  getAreas: (params?: { page?: number; per_page?: number }) =>
    api.get<PaginatedResponse<TableArea>>('/tables/areas', { params }).then((r) => r.data),

  createArea: (data: { name: string }) =>
    api.post<{ data: TableArea }>('/tables/areas', data).then((r) => r.data),

  // Tables
  getTables: (params?: { page?: number; per_page?: number; area_id?: number; status?: string }) =>
    api.get<PaginatedResponse<Table>>('/tables', { params }).then((r) => r.data),

  createTable: (data: { table_area_id: number; name: string; capacity: number }) =>
    api.post<{ data: Table }>('/tables', data).then((r) => r.data),

  updateTable: (id: number, data: Partial<Table>) =>
    api.put<{ data: Table }>(`/tables/${id}`, data).then((r) => r.data),

  updateTableStatus: (id: number, status: string) =>
    api.post<{ data: Table }>(`/tables/${id}/status`, { status }).then((r) => r.data),

  generateQr: (id: number) =>
    api.post<{ data: Table }>(`/tables/${id}/qr-code`).then((r) => r.data),

  // Reservations
  getReservations: (params?: { page?: number; per_page?: number; status?: string; date?: string }) =>
    api.get<PaginatedResponse<Reservation>>('/reservations', { params }).then((r) => r.data),

  createReservation: (data: Record<string, unknown>) =>
    api.post<{ data: Reservation }>('/reservations', data).then((r) => r.data),

  updateReservationStatus: (id: number, status: string) =>
    api.patch<{ data: Reservation }>(`/reservations/${id}/status`, { status }).then((r) => r.data),

  // KOT / KDS
  getKotQueue: (params?: { status?: string }) =>
    api.get('/kitchen/queue', { params }).then((r) => r.data),

  updateKotStatus: (id: number, status: string) =>
    api.patch(`/kitchen/kot/${id}/status`, { status }).then((r) => r.data),

  // Modifiers
  getModifiers: (params?: { page?: number; per_page?: number }) =>
    api.get<PaginatedResponse<unknown>>('/modifiers', { params }).then((r) => r.data),

  createModifier: (data: Record<string, unknown>) =>
    api.post('/modifiers', data).then((r) => r.data),

  // Recipes
  getRecipes: (params?: { page?: number; per_page?: number }) =>
    api.get<PaginatedResponse<unknown>>('/recipes', { params }).then((r) => r.data),

  createRecipe: (data: Record<string, unknown>) =>
    api.post('/recipes', data).then((r) => r.data),

  // Bill splits
  getBillSplits: (saleId: number) =>
    api.get(`/sales/${saleId}/bill-splits`).then((r) => r.data),

  splitBill: (saleId: number, data: Record<string, unknown>) =>
    api.post(`/sales/${saleId}/bill-splits`, data).then((r) => r.data),
}
