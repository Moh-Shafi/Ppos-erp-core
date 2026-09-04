import api from '@/lib/api'
import type { EnhancedAuthResponse, User, ModuleConfigResponse } from '@/types'

export const authService = {
  register: (data: {
    name: string
    email: string
    password: string
    password_confirmation: string
    store_name: string
    business_type_id?: number
  }) => api.post<EnhancedAuthResponse>('/auth/register', data).then((r) => r.data),

  login: (data: { email: string; password: string }) =>
    api.post<EnhancedAuthResponse>('/auth/login', data).then((r) => r.data),

  logout: () => api.post('/auth/logout').then((r) => r.data),

  me: () => api.get<ModuleConfigResponse & { user: User }>('/auth/me').then((r) => r.data),

  getBusinessTypes: () =>
    api.get<{ data: import('@/types').BusinessType[] }>('/business-types').then((r) => r.data.data),
}
