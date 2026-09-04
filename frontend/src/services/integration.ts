import api from '@/lib/api'
import type { PaginatedResponse } from '@/types'

export interface IntegrationProvider {
  id: number
  slug: string
  name: string
  description: string | null
  config_schema: Record<string, unknown> | null
  is_active: boolean
  is_system: boolean
}

export interface TenantIntegration {
  id: number
  tenant_id: number
  integration_provider_id: number
  name: string
  config: Record<string, unknown> | null
  status: 'inactive' | 'active' | 'error' | 'suspended'
  last_connected_at: string | null
  last_error: string | null
  provider?: IntegrationProvider
  created_at: string
  updated_at: string
}

export interface WebhookEvent {
  id: number
  slug: string
  name: string
  description: string | null
  module: string | null
  is_active: boolean
}

export interface WebhookEndpoint {
  id: number
  tenant_id: number
  name: string
  url: string
  is_active: boolean
  description: string | null
  subscriptions?: WebhookSubscription[]
  created_at: string
  updated_at: string
}

export interface WebhookSubscription {
  id: number
  webhook_endpoint_id: number
  event_type: string
}

export interface WebhookDelivery {
  id: number
  tenant_id: number
  webhook_endpoint_id: number
  event_type: string
  event_id: string
  payload: Record<string, unknown>
  status: 'pending' | 'delivered' | 'failed' | 'dead_lettered' | 'replayed'
  attempt_count: number
  last_attempt_at: string | null
  response_status: number | null
  error_message: string | null
  latency_ms: number | null
  original_delivery_id: number | null
  endpoint?: WebhookEndpoint
  created_at: string
  updated_at: string
}

export interface WebhookStats {
  period: string
  total: number
  delivered: number
  failed: number
  pending: number
  dead_lettered: number
  success_rate: number
  avg_latency_ms: number
}

export interface IntegrationApiKey {
  id: number
  tenant_id: number
  name: string
  key_prefix: string
  scopes: string[]
  last_used_at: string | null
  expires_at: string | null
  is_revoked: boolean
  created_at: string
  updated_at: string
}

export interface IntegrationHealth {
  id: number
  name: string
  provider: string | null
  status: string
  last_connected_at: string | null
  error_count_24h: number
  last_error: string | null
}

export const integrationService = {
  listProviders: () =>
    api.get<{ data: IntegrationProvider[] }>('/integrations/providers').then((r) => r.data.data),

  listIntegrations: (params?: { page?: number; per_page?: number; status?: string }) =>
    api.get<PaginatedResponse<TenantIntegration>>('/integrations', { params }).then((r) => r.data),

  showIntegration: (id: number) =>
    api.get<{ data: TenantIntegration }>(`/integrations/${id}`).then((r) => r.data.data),

  createIntegration: (data: { provider_slug: string; name: string; config?: Record<string, unknown>; credentials?: Record<string, unknown> }) =>
    api.post<{ data: TenantIntegration }>('/integrations', data).then((r) => r.data.data),

  updateIntegration: (id: number, data: { name?: string; config?: Record<string, unknown> }) =>
    api.put<{ data: TenantIntegration }>(`/integrations/${id}`, data).then((r) => r.data.data),

  updateCredentials: (id: number, credentials: Record<string, unknown>) =>
    api.put<{ data: TenantIntegration }>(`/integrations/${id}/credentials`, { credentials }).then((r) => r.data.data),

  testConnection: (id: number) =>
    api.post(`/integrations/${id}/test`).then((r) => r.data),

  activate: (id: number) =>
    api.post<{ data: TenantIntegration }>(`/integrations/${id}/activate`).then((r) => r.data.data),

  deactivate: (id: number) =>
    api.post<{ data: TenantIntegration }>(`/integrations/${id}/deactivate`).then((r) => r.data.data),

  deleteIntegration: (id: number) =>
    api.delete(`/integrations/${id}`).then((r) => r.data),

  getHealth: () =>
    api.get<{ data: IntegrationHealth[] }>('/integrations/health').then((r) => r.data.data),
}

export const webhookService = {
  listEvents: () =>
    api.get<{ data: WebhookEvent[] }>('/webhooks/events').then((r) => r.data.data),

  listEndpoints: (params?: { page?: number; per_page?: number; is_active?: boolean }) =>
    api.get<PaginatedResponse<WebhookEndpoint>>('/webhooks/endpoints', { params }).then((r) => r.data),

  showEndpoint: (id: number) =>
    api.get<{ data: WebhookEndpoint }>(`/webhooks/endpoints/${id}`).then((r) => r.data.data),

  createEndpoint: (data: { name: string; url: string; events: string[]; description?: string }) =>
    api.post<{ endpoint: WebhookEndpoint; secret: string }>('/webhooks/endpoints', data).then((r) => r.data),

  updateEndpoint: (id: number, data: { name?: string; url?: string; is_active?: boolean; description?: string }) =>
    api.put<{ data: WebhookEndpoint }>(`/webhooks/endpoints/${id}`, data).then((r) => r.data.data),

  deleteEndpoint: (id: number) =>
    api.delete(`/webhooks/endpoints/${id}`).then((r) => r.data),

  testEndpoint: (id: number) =>
    api.post(`/webhooks/endpoints/${id}/test`).then((r) => r.data),

  subscribe: (id: number, eventType: string) =>
    api.post(`/webhooks/endpoints/${id}/subscriptions`, { event_type: eventType }).then((r) => r.data.data),

  unsubscribe: (id: number, eventType: string) =>
    api.delete(`/webhooks/endpoints/${id}/subscriptions/${eventType}`).then((r) => r.data),

  listDeliveries: (params?: { page?: number; per_page?: number; endpoint_id?: number; event_type?: string; status?: string }) =>
    api.get<PaginatedResponse<WebhookDelivery>>('/webhooks/deliveries', { params }).then((r) => r.data),

  showDelivery: (id: number) =>
    api.get<{ data: WebhookDelivery }>(`/webhooks/deliveries/${id}`).then((r) => r.data.data),

  replayDelivery: (id: number) =>
    api.post<{ data: WebhookDelivery }>(`/webhooks/deliveries/${id}/replay`).then((r) => r.data.data),

  getStats: (period?: string) =>
    api.get<WebhookStats>('/webhooks/stats', { params: { period } }).then((r) => r.data),
}

export const apiKeyService = {
  list: () =>
    api.get<PaginatedResponse<IntegrationApiKey>>('/api-keys').then((r) => r.data),

  generate: (data: { name: string; scopes?: string[] }) =>
    api.post<{ id: number; name: string; key: string; key_prefix: string; scopes: string[] }>('/api-keys', data).then((r) => r.data),

  revoke: (id: number) =>
    api.delete(`/api-keys/${id}`).then((r) => r.data),

  rotate: (id: number) =>
    api.post<{ id: number; name: string; key: string; key_prefix: string; scopes: string[] }>(`/api-keys/${id}/rotate`).then((r) => r.data),
}
