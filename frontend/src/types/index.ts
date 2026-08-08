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

export interface Category {
  id: number
  tenant_id: number
  name: string
  slug: string
  description: string | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface Product {
  id: number
  tenant_id: number
  category_id: number
  name: string
  sku: string | null
  barcode: string | null
  description: string | null
  cost_price: string
  selling_price: string
  unit: string
  image: string | null
  is_active: boolean
  category?: Category
  created_at: string
  updated_at: string
}

export interface PaginatedResponse<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
  next_page_url: string | null
  prev_page_url: string | null
}

export interface AuthResponse {
  message: string
  token: string
  user: User
}

export interface Inventory {
  id: number
  tenant_id: number
  store_id: number
  product_id: number
  quantity: number
  minimum_quantity: number
  store?: Store
  product?: Product
  created_at: string
  updated_at: string
}

export interface InventoryMovement {
  id: number
  tenant_id: number
  store_id: number
  product_id: number
  user_id: number | null
  type: string
  quantity: number
  before_quantity: number
  after_quantity: number
  reference_type: string | null
  reference_id: number | null
  note: string | null
  store?: Store
  product?: Product
  user?: User | null
  created_at: string
  updated_at: string
}

export interface Customer {
  id: number
  tenant_id: number
  name: string
  phone: string | null
  email: string | null
  address: string | null
  notes: string | null
  is_active: boolean
  created_at: string
  updated_at: string
}
