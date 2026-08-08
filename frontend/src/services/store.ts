import api from '@/lib/api'
import type { Store } from '@/types'

export const storeService = {
  list: () => api.get<{ stores: Store[] }>('/stores').then((r) => r.data.stores),

  create: (data: Partial<Store>) =>
    api.post<{ message: string; store: Store }>('/stores', data).then((r) => r.data.store),

  show: (id: number) =>
    api.get<{ store: Store }>(`/stores/${id}`).then((r) => r.data.store),

  update: (id: number, data: Partial<Store>) =>
    api.put<{ message: string; store: Store }>(`/stores/${id}`, data).then((r) => r.data.store),
}
