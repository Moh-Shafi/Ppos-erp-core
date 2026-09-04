import api from '@/lib/api'
import type { StockBatch } from '@/types'

interface CreateBatchData {
  batch_number: string
  quantity: number
  received_date: string
  expiry_date?: string
  cost_price?: number
}

export const stockBatchService = {
  list: (productId: number) =>
    api.get<{ data: StockBatch[] }>(`/products/${productId}/batches`).then((r) => r.data.data),

  create: (productId: number, data: CreateBatchData) =>
    api.post<{ message: string; batch: StockBatch }>(`/products/${productId}/batches`, data).then((r) => r.data),

  get: (id: number) =>
    api.get<{ batch: StockBatch }>(`/batches/${id}`).then((r) => r.data.batch),
}
