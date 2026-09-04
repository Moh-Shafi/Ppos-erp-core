import api from '@/lib/api'
import type { TransferRequest, PaginatedResponse } from '@/types'

interface TransferRequestParams {
  page?: number
  per_page?: number
  status?: string
  from_store_id?: number
  to_store_id?: number
}

interface CreateTransferRequestData {
  from_store_id?: number
  from_warehouse_id?: number
  to_store_id?: number
  to_warehouse_id?: number
  items: { product_id: number; quantity: number; batch_id?: number }[]
  note?: string
}

export const transferRequestService = {
  list: (params?: TransferRequestParams) =>
    api.get<PaginatedResponse<TransferRequest>>('/transfer-requests', { params }).then((r) => r.data),

  get: (id: number) =>
    api.get<{ transfer_request: TransferRequest }>(`/transfer-requests/${id}`).then((r) => r.data.transfer_request),

  create: (data: CreateTransferRequestData) =>
    api.post<{ message: string; transfer_request: TransferRequest }>('/transfer-requests', data).then((r) => r.data),

  submit: (id: number) =>
    api.post<{ message: string; transfer_request: TransferRequest }>(`/transfer-requests/${id}/submit`).then((r) => r.data),

  approve: (id: number) =>
    api.post<{ message: string; transfer_request: TransferRequest }>(`/transfer-requests/${id}/approve`).then((r) => r.data),

  reject: (id: number, reason?: string) =>
    api.post<{ message: string; transfer_request: TransferRequest }>(`/transfer-requests/${id}/reject`, { reason }).then((r) => r.data),

  transit: (id: number) =>
    api.post<{ message: string; transfer_request: TransferRequest }>(`/transfer-requests/${id}/transit`).then((r) => r.data),

  complete: (id: number) =>
    api.post<{ message: string; transfer_request: TransferRequest }>(`/transfer-requests/${id}/complete`).then((r) => r.data),

  cancel: (id: number, reason?: string) =>
    api.post<{ message: string; transfer_request: TransferRequest }>(`/transfer-requests/${id}/cancel`, { reason }).then((r) => r.data),
}
