import api from '@/lib/api'
import type { Tenant } from '@/types'

export const tenantService = {
  show: () => api.get<{ tenant: Tenant }>('/tenant').then((r) => r.data.tenant),

  update: (data: { name?: string }) =>
    api.put<{ message: string; tenant: Tenant }>('/tenant', data).then((r) => r.data.tenant),
}
