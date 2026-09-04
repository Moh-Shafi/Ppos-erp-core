import api from '@/lib/api'
import type { DashboardWidget, ExportFormat, KpiValue, ReportConfig, ReportResult } from '@/types/reports'

export interface ReportFilters {
  date_from?: string
  date_to?: string
  store_id?: number
  category_id?: number
  supplier_id?: number
  customer_id?: number
  group_by?: string
  sort?: string
  page?: number
  per_page?: number
  as_of?: string
  fiscal_period_id?: number
  account_id?: number
  metric?: string
  method?: string
}

export interface DashboardResponse {
  date_range: { from: string | null; to: string | null }
  widgets: Array<
    | { id: number; type: 'kpi'; kpi_id: string; position: Record<string, unknown>; value: KpiValue }
    | { id: number; type: 'report'; report_id: string; position: Record<string, unknown>; data: ReportResult }
  >
}

export const reportService = {
  dashboard: (filters?: { date_from?: string; date_to?: string }) =>
    api.get<DashboardResponse>('/reports/dashboard', { params: filters }).then((r) => r.data),

  listKpis: () => api.get<{ data: string[] }>('/reports/kpis').then((r) => r.data.data),

  run: (reportId: string, filters: ReportFilters = {}) =>
    api.get<ReportResult>(`/reports/${reportId}`, { params: filters }).then((r) => r.data),

  export: (reportId: string, format: ExportFormat, filters: Record<string, unknown> = {}) =>
    api
      .post('/reports/export', { report_id: reportId, format, filters }, { responseType: 'blob' })
      .then((r) => r.data as Blob),

  listWidgets: () => api.get<{ data: DashboardWidget[] }>('/reports/dashboard/widgets').then((r) => r.data.data),

  createWidget: (data: Partial<DashboardWidget>) =>
    api.post<{ data: DashboardWidget }>('/reports/dashboard/widgets', data).then((r) => r.data.data),

  updateWidget: (id: number, data: Partial<DashboardWidget>) =>
    api.put<{ data: DashboardWidget }>(`/reports/dashboard/widgets/${id}`, data).then((r) => r.data.data),

  deleteWidget: (id: number) => api.delete(`/reports/dashboard/widgets/${id}`),

  listConfigs: () => api.get<{ data: ReportConfig[] }>('/reports/report-configs').then((r) => r.data.data),

  createConfig: (data: Partial<ReportConfig>) =>
    api.post<{ data: ReportConfig }>('/reports/report-configs', data).then((r) => r.data.data),

  updateConfig: (id: number, data: Partial<ReportConfig>) =>
    api.put<{ data: ReportConfig }>(`/reports/report-configs/${id}`, data).then((r) => r.data.data),

  deleteConfig: (id: number) => api.delete(`/reports/report-configs/${id}`),
}
