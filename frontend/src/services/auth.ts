import api from '@/lib/api'
import type { AuthResponse, User } from '@/types'

export const authService = {
  register: (data: {
    name: string
    email: string
    password: string
    password_confirmation: string
    store_name: string
  }) => api.post<AuthResponse>('/auth/register', data).then((r) => r.data),

  login: (data: { email: string; password: string }) =>
    api.post<AuthResponse>('/auth/login', data).then((r) => r.data),

  logout: () => api.post('/auth/logout').then((r) => r.data),

  me: () => api.get<{ user: User }>('/auth/me').then((r) => r.data.user),
}
