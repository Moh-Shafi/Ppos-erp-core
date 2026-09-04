import api from '@/lib/api'
import type { ValuationResult } from '@/types'

interface ValuationParams {
  method?: 'fifo' | 'lifo' | 'average'
  store_id?: number
}

interface LowStockParams {
  store_id?: number
}

export const inventoryReportService = {
  valuation: (params?: ValuationParams) =>
    api.get<ValuationResult>('/inventory/reports/valuation', { params }).then((r) => r.data),

  lowStock: (params?: LowStockParams) =>
    api.get<{ data: Array<{ product_id: number; product: { id: number; name: string; sku: string | null }; store_id: number; current_qty: number; min_qty: number; max_qty: number | null; status: string; suggested_reorder: number }> }>('/inventory/reports/low-stock', { params }).then((r) => r.data),

  summary: (params?: { store_id?: number; warehouse_id?: number }) =>
    api.get('/inventory/reports/summary', { params }).then((r) => r.data),

  expiry: (params?: { days?: number; store_id?: number }) =>
    api.get('/inventory/reports/expiry', { params }).then((r) => r.data),
}
