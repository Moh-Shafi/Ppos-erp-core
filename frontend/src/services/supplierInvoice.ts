import api from '@/lib/api'
import type { SupplierInvoice, PaginatedResponse } from '@/types'

interface InvoiceParams {
  page?: number
  per_page?: number
  status?: string
  supplier_id?: number
}

interface InvoiceData {
  invoice_number: string
  supplier_id: number
  purchase_id?: number
  grn_id?: number
  subtotal: number
  tax?: number
  total: number
  invoice_date: string
  due_date?: string
}

export const supplierInvoiceService = {
  list: (params?: InvoiceParams) =>
    api.get<PaginatedResponse<SupplierInvoice>>('/supplier-invoices', { params }).then((r) => r.data),

  show: (id: number) =>
    api.get<SupplierInvoice>(`/supplier-invoices/${id}`).then((r) => r.data),

  create: (data: InvoiceData) =>
    api.post<SupplierInvoice>('/supplier-invoices', data).then((r) => r.data),

  match: (id: number) =>
    api.post<SupplierInvoice>(`/supplier-invoices/${id}/match`).then((r) => r.data),

  approve: (id: number) =>
    api.post<SupplierInvoice>(`/supplier-invoices/${id}/approve`).then((r) => r.data),

  reject: (id: number, rejection_reason: string) =>
    api.post<SupplierInvoice>(`/supplier-invoices/${id}/reject`, { rejection_reason }).then((r) => r.data),
}
