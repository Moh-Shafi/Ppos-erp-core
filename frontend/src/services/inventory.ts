import api from '@/lib/api'
import type { Inventory, InventoryMovement, PaginatedResponse } from '@/types'

interface InventoryParams {
  page?: number
  per_page?: number
  store_id?: number
  product_id?: number
  low_stock?: boolean
}

interface MovementParams {
  page?: number
  per_page?: number
  store_id?: number
  product_id?: number
  type?: string
}

interface AdjustData {
  store_id: number
  product_id: number
  delta: number
  note?: string
}

interface TransferData {
  from_store_id: number
  to_store_id: number
  product_id: number
  quantity: number
  note?: string
}

export const inventoryService = {
  list: (params?: InventoryParams) =>
    api.get<PaginatedResponse<Inventory>>('/inventory', { params }).then((r) => r.data),

  getByProduct: (productId: number, storeId?: number) =>
    api
      .get<{ inventories: Inventory[] }>(`/inventory/${productId}`, {
        params: storeId ? { store_id: storeId } : undefined,
      })
      .then((r) => r.data.inventories),

  adjust: (data: AdjustData) =>
    api
      .post<{ message: string; movement: InventoryMovement }>('/inventory/adjust', data)
      .then((r) => r.data),

  movements: (params?: MovementParams) =>
    api.get<PaginatedResponse<InventoryMovement>>('/inventory/movements', { params }).then((r) => r.data),

  transfer: (data: TransferData) =>
    api
      .post<{
        message: string
        out_movement: InventoryMovement
        in_movement: InventoryMovement
      }>('/inventory/transfer', data)
      .then((r) => r.data),
}
