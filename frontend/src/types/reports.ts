export interface ReportColumn {
  key: string
  label: string
  format?: string
}

export interface ReportMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface ReportResult {
  report: string
  filters: Record<string, unknown>
  columns: ReportColumn[]
  data: Record<string, unknown>[]
  summary: Record<string, unknown>
  meta: ReportMeta
  notes: unknown[]
}

export interface DashboardWidget {
  id: number
  type: 'kpi' | 'report'
  kpi_id?: string
  report_id?: string
  filters?: Record<string, unknown>
  position?: Record<string, unknown>
}

export interface KpiValue {
  value: number
  format?: string
}

export interface ReportConfig {
  id: number
  name: string
  report_id: string
  filters: Record<string, unknown>
}

export type ExportFormat = 'csv' | 'xlsx' | 'pdf'
