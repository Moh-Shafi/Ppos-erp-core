export interface Tenant {
  id: number
  name: string
  slug: string
  plan_id: number | null
  created_at: string
  updated_at: string
}

export interface Role {
  id: number
  name: string
  slug: string
}

export interface User {
  id: number
  tenant_id: number | null
  role_id: number | null
  name: string
  email: string
  email_verified_at: string | null
  tenant?: Tenant
  role?: Role
  created_at: string
  updated_at: string
}

export interface Store {
  id: number
  tenant_id: number
  name: string
  code: string | null
  address: string | null
  phone: string | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface AuthResponse {
  message: string
  token: string
  user: User
}
