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

export interface ReceiptSettings {
  header_text?: string
  footer_text?: string
  show_cashier?: boolean
  show_customer?: boolean
  show_qr_code?: boolean
  paper_width?: string
  logo_url?: string | null
}

export interface Store {
  id: number
  tenant_id: number
  name: string
  code: string | null
  address: string | null
  phone: string | null
  is_active: boolean
  receipt_settings: ReceiptSettings | null
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
  parent_id: number | null
  sort_order: number
  parent?: Category | null
  children?: Category[]
  products_count?: number
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
  has_variants: boolean
  is_trackable: boolean
  min_stock: number | null
  stock_quantity?: number | null
  base_unit_id: number | null
  purchase_unit_id: number | null
  category?: Category
  variants?: ProductVariant[]
  variant_options?: ProductVariantOption[]
  images?: ProductImage[]
  barcodes?: ProductBarcode[]
  created_at: string
  updated_at: string
}

export interface ProductVariant {
  id: number
  product_id: number
  sku: string | null
  barcode: string | null
  price_override: string | null
  cost_price_override: string | null
  is_active: boolean
  option_values?: ProductVariantOptionValue[]
  created_at: string
  updated_at: string
}

export interface ProductVariantOption {
  id: number
  product_id: number
  name: string
  sort_order: number
  values?: ProductVariantOptionValue[]
  created_at: string
  updated_at: string
}

export interface ProductVariantOptionValue {
  id: number
  option_id: number
  value: string
  sort_order: number
  option?: ProductVariantOption
  created_at: string
  updated_at: string
}

export interface ProductImage {
  id: number
  product_id: number
  url: string
  sort_order: number
  created_at: string
  updated_at: string
}

export interface ProductBarcode {
  id: number
  product_id: number
  variant_id: number | null
  barcode: string
  created_at: string
  updated_at: string
}

export interface Unit {
  id: number
  tenant_id: number
  name: string
  symbol: string
  is_base_unit: boolean
  conversions?: UnitConversion[]
  created_at: string
  updated_at: string
}

export interface UnitConversion {
  id: number
  tenant_id: number
  from_unit_id: number
  to_unit_id: number
  factor: string
  from_unit?: Unit
  to_unit?: Unit
  created_at: string
  updated_at: string
}

export interface PriceList {
  id: number
  tenant_id: number
  name: string
  slug: string
  description: string | null
  is_default: boolean
  is_active: boolean
  items?: PriceListItem[]
  created_at: string
  updated_at: string
}

export interface PriceListItem {
  id: number
  price_list_id: number
  product_id: number
  variant_id: number | null
  price: string
  product?: Product
  variant?: ProductVariant
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
  credit_limit: number | null
  outstanding_balance: string
  price_list_id: number | null
  price_list?: PriceList | null
  created_at: string
  updated_at: string
}

export interface Supplier {
  id: number
  tenant_id: number
  name: string
  contact_person: string | null
  phone: string | null
  email: string | null
  address: string | null
  tax_number: string | null
  notes: string | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface PurchaseItem {
  id: number
  purchase_id: number
  product_id: number
  quantity: number
  unit_cost: string
  discount: string
  tax: string
  total: string
  product?: Product
  created_at: string
  updated_at: string
}

export interface Purchase {
  id: number
  tenant_id: number
  supplier_id: number
  store_id: number
  created_by: number
  purchase_number: string
  status: 'draft' | 'ordered' | 'received' | 'cancelled'
  purchase_date: string
  expected_date: string | null
  subtotal: string
  discount: string
  tax: string
  total: string
  notes: string | null
  supplier?: Supplier
  store?: Store
  items?: PurchaseItem[]
  created_at: string
  updated_at: string
}

export interface PurchaseReturnItem {
  id: number
  purchase_return_id: number
  product_id: number
  purchase_item_id: number
  quantity: number
  unit_cost: string
  discount: string
  tax: string
  total: string
  product?: Product
  created_at: string
  updated_at: string
}

export interface PurchaseReturn {
  id: number
  tenant_id: number
  purchase_id: number
  store_id: number
  created_by: number
  return_number: string
  status: 'draft' | 'completed' | 'cancelled'
  return_date: string
  subtotal: string
  discount: string
  tax: string
  total: string
  notes: string | null
  purchase?: Purchase
  store?: Store
  items?: PurchaseReturnItem[]
  created_at: string
  updated_at: string
}

export interface SaleItem {
  id: number
  sale_id: number
  product_id: number
  variant_id: number | null
  product_name: string
  sku: string | null
  unit_price: string
  original_price: string | null
  quantity: number
  total: string
  product?: Product
  variant?: ProductVariant | null
  created_at: string
  updated_at: string
}

export interface Payment {
  id: number
  sale_id: number
  payment_method: 'cash' | 'qris' | 'card' | 'bank_transfer'
  amount: string
  change_amount: string
  payment_reference: string | null
  idempotency_key: string | null
  gateway_transaction_id: string | null
  gateway_status: string | null
  gateway_response: Record<string, unknown> | null
  settlement_amount: string | null
  platform_fee: string | null
  net_amount: string | null
  settled_at: string | null
  expires_at: string | null
  gateway_account_id: string | null
  status: 'success' | 'pending' | 'failed' | 'refunded'
  refund_amount: string
  refund_status: 'none' | 'partial' | 'full'
  metadata: Record<string, unknown> | null
  payment_date: string | null
  created_at: string
  updated_at: string
}

export interface Sale {
  id: number
  store_id: number
  cashier_id: number
  customer_id: number | null
  sale_number: string
  status: 'completed' | 'cancelled' | 'refunded'
  payment_status: 'unpaid' | 'partial' | 'paid'
  refund_status: 'none' | 'partial' | 'full'
  refunded_amount: string
  price_list_id: number | null
  hold_status: 'none' | 'held' | 'recalled'
  held_at: string | null
  sale_date: string
  subtotal: string
  discount: string
  tax: string
  total: string
  paid_amount: string
  change_amount: string
  notes: string | null
  store?: Store
  cashier?: User
  customer?: Customer
  items?: SaleItem[]
  payments?: Payment[]
  refunds?: SaleRefund[]
  created_at: string
  updated_at: string
}

export interface CartItem {
  product: Product
  variant: ProductVariant | null
  quantity: number
  unitPrice: number
  originalPrice: number | null
}

export interface PaymentInput {
  payment_method: 'cash' | 'qris' | 'card' | 'bank_transfer'
  amount: number
  payment_reference?: string
  idempotency_key?: string
  metadata?: Record<string, unknown>
}

export interface CheckoutData {
  store_id: number
  customer_id?: number | null
  items: { product_id: number; variant_id?: number | null; quantity: number }[]
  payments: PaymentInput[]
  discount?: number
  tax?: number
  notes?: string
}

export interface BusinessType {
  id: number
  slug: string
  name: string
  description: string | null
  icon: string | null
}

export interface BusinessProfile {
  id: number
  tenant_id: number
  business_type_id: number
  business_name: string
  tax_id: string | null
  address: string | null
  city: string | null
  province: string | null
  postal_code: string | null
  phone: string | null
  email: string | null
  logo: string | null
  timezone: string
  currency: string
  locale: string
  is_active: boolean
  business_type?: BusinessType
  created_at: string
  updated_at: string
}

export interface ModuleConfigResponse {
  modules: string[]
  features: string[]
  permissions: string[]
  stores: Store[]
  business_profile: BusinessProfile | null
}

export interface EnhancedAuthResponse {
  message: string
  token: string
  user: User
  modules: string[]
  features: string[]
  permissions: string[]
  stores: Store[]
  business_profile: BusinessProfile | null
}

export interface DashboardStats {
  today_revenue: number
  today_sales_count: number
  total_products: number
  total_customers: number
  yesterday_revenue: number
  yesterday_count: number
  revenue_trend_pct: number
  count_trend_pct: number
  revenue_trend: number[]
  count_trend: number[]
}

export interface WeeklyDataPoint {
  day: string
  date: string
  revenue: number
  count: number
}

export interface PaymentMethodData {
  method: string
  label: string
  total: number
  count: number
  percentage: number
}

export interface TopProductData {
  rank: number
  product_id: number
  name: string
  sold: number
  revenue: number
}

export interface SalesTargetData {
  target: number
  current: number
  percentage: number
  remaining: number
}

export interface ActivityData {
  id: number
  text: string
  icon: string
  color: string
  user: string
  time: string
  created_at: string
}

export interface DashboardData {
  stats: DashboardStats
  recent_sales: Sale[]
  low_stock: Inventory[]
  weekly_data: WeeklyDataPoint[]
  weekly_max: number
  payment_methods: PaymentMethodData[]
  top_products: TopProductData[]
  sales_target: SalesTargetData
  activities: ActivityData[]
}

// Phase 2 — Inventory Enhancement Types

export interface Warehouse {
  id: number
  tenant_id: number
  name: string
  address: string | null
  phone: string | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface WarehouseStock {
  id: number
  tenant_id: number
  warehouse_id: number
  product_id: number
  quantity: number
  batch_id: number | null
  expiry_date: string | null
  product?: Product
  batch?: StockBatch | null
  warehouse?: Warehouse
  created_at: string
  updated_at: string
}

export interface StockBatch {
  id: number
  tenant_id: number
  product_id: number
  batch_number: string
  quantity: number
  received_date: string
  expiry_date: string | null
  cost_price: string | null
  product?: Product
  created_at: string
  updated_at: string
}

export interface StockAdjustmentReason {
  id: number
  tenant_id: number
  name: string
  category: string
  is_system: boolean
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface StocktakeItem {
  id: number
  tenant_id: number
  stocktake_session_id: number
  product_id: number
  system_quantity: number
  counted_quantity: number | null
  variance: number | null
  note: string | null
  product?: Product
  created_at: string
  updated_at: string
}

export interface StocktakeSession {
  id: number
  tenant_id: number
  store_id: number
  session_number: string
  status: 'draft' | 'counting' | 'reconciling' | 'posted' | 'cancelled'
  created_by: number | null
  started_at: string | null
  completed_at: string | null
  note: string | null
  store?: Store
  createdBy?: User | null
  items?: StocktakeItem[]
  created_at: string
  updated_at: string
}

export interface TransferRequestItem {
  id: number
  tenant_id: number
  transfer_request_id: number
  product_id: number
  quantity: number
  batch_id: number | null
  product?: Product
  batch?: StockBatch | null
  created_at: string
  updated_at: string
}

export interface TransferRequest {
  id: number
  tenant_id: number
  request_number: string
  from_store_id: number | null
  from_warehouse_id: number | null
  to_store_id: number | null
  to_warehouse_id: number | null
  status: 'draft' | 'pending' | 'approved' | 'rejected' | 'in_transit' | 'completed' | 'cancelled'
  requested_by: number | null
  approved_by: number | null
  approved_at: string | null
  note: string | null
  rejection_reason: string | null
  fromStore?: Store | null
  fromWarehouse?: Warehouse | null
  toStore?: Store | null
  toWarehouse?: Warehouse | null
  requestedBy?: User | null
  approvedBy?: User | null
  items?: TransferRequestItem[]
  created_at: string
  updated_at: string
}

export interface ValuationItem {
  product_id: number
  product: Product
  quantity: number
  unit_cost: number
  total_value: number
}

export interface ValuationResult {
  method: 'fifo' | 'lifo' | 'average'
  data: ValuationItem[]
  grand_total: number
}

// Phase 3 — CRM & Purchasing Enhancement Types

export interface CustomerLoyaltyPoints {
  customer_id: number
  points_balance: number
  total_earned: number
  total_redeemed: number
}

export interface CustomerLoyaltyTransaction {
  id: number
  tenant_id: number
  customer_id: number
  points: number
  type: 'earn' | 'redeem' | 'expire' | 'adjust'
  source: 'sale' | 'manual' | 'expiry_sweep'
  reference_type: string | null
  reference_id: number | null
  balance_after: number
  note: string | null
  created_at: string
  updated_at: string
}

export interface CustomerCreditTransaction {
  id: number
  tenant_id: number
  customer_id: number
  amount: string
  type: 'debit' | 'credit' | 'adjust'
  source: 'sale' | 'payment' | 'manual'
  reference_type: string | null
  reference_id: number | null
  balance_after: string
  note: string | null
  created_at: string
  updated_at: string
}

export interface SupplierRating {
  id: number
  tenant_id: number
  supplier_id: number
  rating: number
  criteria: 'quality' | 'delivery' | 'pricing' | 'service' | 'overall'
  note: string | null
  rated_by: number
  ratedBy?: User | null
  created_at: string
  updated_at: string
}

export interface PurchaseRequisitionItem {
  id: number
  tenant_id: number
  requisition_id: number
  product_id: number
  quantity: number
  estimated_cost: string | null
  note: string | null
  product?: Product
  created_at: string
  updated_at: string
}

export interface PurchaseRequisition {
  id: number
  tenant_id: number
  store_id: number
  request_number: string
  status: 'draft' | 'pending' | 'approved' | 'rejected' | 'cancelled'
  requested_by: number
  approved_by: number | null
  approved_at: string | null
  rejection_reason: string | null
  note: string | null
  store?: Store
  requestedBy?: User | null
  approvedBy?: User | null
  items?: PurchaseRequisitionItem[]
  created_at: string
  updated_at: string
}

export interface GrnItem {
  id: number
  tenant_id: number
  grn_id: number
  product_id: number
  quantity_ordered: number
  quantity_received: number
  quantity_rejected: number
  unit_cost: string
  batch_id: number | null
  expiry_date: string | null
  rejection_reason: string | null
  note: string | null
  product?: Product
  batch?: StockBatch | null
  created_at: string
  updated_at: string
}

export interface GoodsReceiptNote {
  id: number
  tenant_id: number
  grn_number: string
  purchase_id: number | null
  store_id: number
  supplier_id: number
  status: 'draft' | 'received' | 'cancelled'
  received_by: number | null
  received_date: string | null
  note: string | null
  purchase?: Purchase | null
  store?: Store
  supplier?: Supplier
  receivedBy?: User | null
  items?: GrnItem[]
  created_at: string
  updated_at: string
}

export interface SupplierInvoice {
  id: number
  tenant_id: number
  invoice_number: string
  supplier_id: number
  purchase_id: number | null
  grn_id: number | null
  status: 'pending' | 'matched' | 'mismatched' | 'approved' | 'rejected'
  subtotal: string
  tax: string
  total: string
  invoice_date: string
  due_date: string | null
  match_result: Record<string, unknown> | null
  approved_by: number | null
  approved_at: string | null
  rejection_reason: string | null
  supplier?: Supplier
  purchase?: Purchase | null
  grn?: GoodsReceiptNote | null
  approvedBy?: User | null
  created_at: string
  updated_at: string
}

export interface AutoReorderItem {
  product_id: number
  product_name: string
  current_stock: number
  minimum_quantity: number
  maximum_quantity: number | null
  suggested_qty: number
  estimated_cost: number
}

// Phase 4 — POS Enhancement Types

export interface HeldSale {
  id: number
  tenant_id: number
  store_id: number
  cashier_id: number
  customer_id: number | null
  cart_data: Record<string, unknown>
  hold_number: string
  status: 'held' | 'recalled' | 'expired'
  held_at: string
  recalled_at: string | null
  expires_at: string | null
  store?: Store
  cashier?: User
  customer?: Customer | null
  created_at: string
  updated_at: string
}

export interface DiscountPreset {
  id: number
  tenant_id: number
  name: string
  type: 'percentage' | 'fixed'
  value: string
  is_active: boolean
  sort_order: number
  created_at: string
  updated_at: string
}

export interface SaleRefundItem {
  id: number
  sale_refund_id: number
  sale_item_id: number
  product_id: number
  quantity: number
  unit_price: string
  refund_amount: string
  product?: Product
  saleItem?: SaleItem
  created_at: string
  updated_at: string
}

export interface SaleRefund {
  id: number
  tenant_id: number
  sale_id: number
  refunded_by: number
  type: 'full' | 'partial'
  refund_reason: string | null
  refund_amount: string
  status: 'completed' | 'pending' | 'failed'
  refunded_at: string
  refundedBy?: User
  items?: SaleRefundItem[]
  created_at: string
  updated_at: string
}

export interface RefundInput {
  type: 'full' | 'partial'
  reason?: string
  items?: { sale_item_id: number; quantity: number }[]
}

export interface HoldSaleInput {
  store_id: number
  customer_id?: number | null
  cart_data: Record<string, unknown>
}

export interface PaymentGatewayAccount {
  id: number
  tenant_id: number
  gateway: string
  gateway_account_id: string
  status: 'pending' | 'active' | 'suspended' | 'rejected'
  kyc_status: string
  capabilities: string[]
  webhook_url: string | null
  metadata: Record<string, unknown> | null
  activated_at: string | null
  created_at: string
  updated_at: string
}

export interface PaymentSettlement {
  id: number
  tenant_id: number
  payment_id: number | null
  gateway: string
  settlement_id: string
  gross_amount: string
  platform_fee: string
  net_amount: string
  settled_at: string | null
  status: string
  metadata: Record<string, unknown> | null
  created_at: string
  updated_at: string
}

export interface CashDrawerSession {
  id: number
  tenant_id: number
  store_id: number
  user_id: number
  opening_amount: string
  closing_amount: string | null
  expected_amount: string | null
  difference: string | null
  status: 'open' | 'closed' | 'reconciled'
  opened_at: string
  closed_at: string | null
  notes: string | null
  created_at: string
  updated_at: string
}

export interface GatewayChargeInput {
  method: 'qris' | 'card' | 'bank_transfer'
  amount: number
  idempotency_key: string
}

export interface RefundPaymentInput {
  amount: number
  reason?: string
}

export interface PaymentSummary {
  total_payments: number
  total_amount: string
  success_amount: string
  pending_amount: string
  failed_amount: string
  refunded_amount: string
  total_fees: string
  net_settled: string
  by_method: Record<string, { count: number; amount: string }>
}

export interface ReconciliationReport {
  period: { from: string; to: string }
  internal_total: string
  xendit_total: string
  matched_count: number
  mismatched_count: number
  missing_settlement_count: number
  missing_payment_count: number
  matched: unknown[]
  mismatched: unknown[]
  missing_settlements: unknown[]
  missing_payments: unknown[]
}
