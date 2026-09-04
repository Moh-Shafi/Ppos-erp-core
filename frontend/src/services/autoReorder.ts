import api from '@/lib/api'
import type { AutoReorderItem, PurchaseRequisition } from '@/types'

interface AutoReorderReport {
  data: AutoReorderItem[]
  store_id: number
  count: number
}

export const autoReorderService = {
  report: (storeId: number) =>
    api.get<AutoReorderReport>('/auto-reorder/report', { params: { store_id: storeId } }).then((r) => r.data),

  generate: (storeId: number, productIds: number[]) =>
    api.post<{ requisition: PurchaseRequisition }>('/auto-reorder/generate', { store_id: storeId, product_ids: productIds }).then((r) => r.data),
}
