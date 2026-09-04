import api from '@/lib/api'
import type { PaginatedResponse } from '@/types'

export interface ServiceCatalogItem {
  id: number
  name: string
  duration_minutes: number
  is_recurring: boolean
  color: string | null
}

export interface StaffSchedule {
  id: number
  user_id: number
  day_of_week: number
  start_time: string
  end_time: string
  break_start: string | null
  break_end: string | null
  is_available: boolean
}

export interface Appointment {
  id: number
  customer_id: number
  user_id: number
  store_id: number
  appointment_date: string
  start_time: string
  end_time: string
  status: string
  notes: string | null
}

export const serviceService = {
  // Service catalog
  getServices: (params?: { page?: number; per_page?: number }) =>
    api.get<PaginatedResponse<ServiceCatalogItem>>('/services', { params }).then((r) => r.data),

  createService: (data: Record<string, unknown>) =>
    api.post<{ data: ServiceCatalogItem }>('/services', data).then((r) => r.data),

  updateService: (id: number, data: Partial<ServiceCatalogItem>) =>
    api.put<{ data: ServiceCatalogItem }>(`/services/${id}`, data).then((r) => r.data),

  deleteService: (id: number) =>
    api.delete(`/services/${id}`),

  // Staff schedules
  getStaffSchedules: (params?: { page?: number; per_page?: number; user_id?: number }) =>
    api.get<PaginatedResponse<StaffSchedule>>('/staff-schedules', { params }).then((r) => r.data),

  createStaffSchedule: (data: Record<string, unknown>) =>
    api.post<{ data: StaffSchedule }>('/staff-schedules', data).then((r) => r.data),

  updateStaffSchedule: (id: number, data: Record<string, unknown>) =>
    api.put<{ data: StaffSchedule }>(`/staff-schedules/${id}`, data).then((r) => r.data),

  deleteStaffSchedule: (id: number) =>
    api.delete(`/staff-schedules/${id}`),

  getStaffAvailability: (userId: number, params?: { date?: string }) =>
    api.get(`/staff-schedules/${userId}/availability`, { params }).then((r) => r.data),

  // Appointments
  getAppointments: (params?: { page?: number; per_page?: number; status?: string; date?: string; user_id?: number }) =>
    api.get<PaginatedResponse<Appointment>>('/appointments', { params }).then((r) => r.data),

  getAppointmentsCalendar: (params?: { start?: string; end?: string }) =>
    api.get('/appointments/calendar', { params }).then((r) => r.data),

  createAppointment: (data: Record<string, unknown>) =>
    api.post<{ data: Appointment }>('/appointments', data).then((r) => r.data),

  updateAppointment: (id: number, data: Partial<Appointment>) =>
    api.put<{ data: Appointment }>(`/appointments/${id}`, data).then((r) => r.data),

  deleteAppointment: (id: number) =>
    api.delete(`/appointments/${id}`),

  confirmAppointment: (id: number) =>
    api.patch<{ data: Appointment }>(`/appointments/${id}/confirm`).then((r) => r.data),

  startAppointment: (id: number) =>
    api.patch<{ data: Appointment }>(`/appointments/${id}/start`).then((r) => r.data),

  completeAppointment: (id: number, data?: { payment_method?: string }) =>
    api.patch<{ data: Appointment }>(`/appointments/${id}/complete`, data).then((r) => r.data),

  cancelAppointment: (id: number, data?: { reason?: string }) =>
    api.patch<{ data: Appointment }>(`/appointments/${id}/cancel`, data).then((r) => r.data),
}
