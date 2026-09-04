import { useState, useEffect, useCallback } from 'react'
import { integrationService, webhookService, apiKeyService } from '@/services/integration'
import type { TenantIntegration, IntegrationProvider, WebhookEndpoint, WebhookDelivery, WebhookStats, WebhookEvent, IntegrationApiKey, IntegrationHealth } from '@/services/integration'

export function IntegrationsPage() {
  const [integrations, setIntegrations] = useState<TenantIntegration[]>([])
  const [providers, setProviders] = useState<IntegrationProvider[]>([])
  const [health, setHealth] = useState<IntegrationHealth[]>([])
  const [loading, setLoading] = useState(true)
  const [showCreate, setShowCreate] = useState(false)
  const [selectedProvider, setSelectedProvider] = useState('')
  const [name, setName] = useState('')
  const [baseUrl, setBaseUrl] = useState('')
  const [apiKey, setApiKey] = useState('')

  const loadData = useCallback(async () => {
    setLoading(true)
    try {
      const [ints, provs, hlth] = await Promise.all([
        integrationService.listIntegrations({ per_page: 100 }),
        integrationService.listProviders(),
        integrationService.getHealth(),
      ])
      setIntegrations(ints.data || [])
      setProviders(provs || [])
      setHealth(hlth || [])
    } catch {
      // ignore
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { loadData() }, [loadData])

  const handleCreate = async () => {
    if (!selectedProvider || !name) return
    try {
      await integrationService.createIntegration({
        provider_slug: selectedProvider,
        name,
        config: baseUrl ? { base_url: baseUrl } : {},
        credentials: apiKey ? { api_key: apiKey } : {},
      })
      setShowCreate(false)
      setSelectedProvider('')
      setName('')
      setBaseUrl('')
      setApiKey('')
      loadData()
    } catch (err: any) {
      alert(err?.response?.data?.message || 'Failed to create integration')
    }
  }

  const handleToggle = async (integration: TenantIntegration) => {
    try {
      if (integration.status === 'active') {
        await integrationService.deactivate(integration.id)
      } else {
        await integrationService.activate(integration.id)
      }
      loadData()
    } catch (err: any) {
      alert(err?.response?.data?.message || 'Failed to toggle integration')
    }
  }

  const handleDelete = async (id: number) => {
    if (!confirm('Delete this integration?')) return
    try {
      await integrationService.deleteIntegration(id)
      loadData()
    } catch (err: any) {
      alert(err?.response?.data?.message || 'Failed to delete')
    }
  }

  const handleTest = async (id: number) => {
    try {
      const result = await integrationService.testConnection(id)
      alert(result.success ? `Connection OK (${result.latency_ms}ms)` : `Failed: ${result.message}`)
      loadData()
    } catch (err: any) {
      alert(err?.response?.data?.message || 'Test failed')
    }
  }

  const statusColors: Record<string, string> = {
    active: 'bg-green-100 text-green-700',
    inactive: 'bg-gray-100 text-gray-600',
    error: 'bg-red-100 text-red-700',
    suspended: 'bg-yellow-100 text-yellow-700',
  }

  return (
    <div className="p-6 max-w-7xl mx-auto">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Integrations</h1>
          <p className="text-sm text-gray-500 mt-1">Manage external system integrations and connections</p>
        </div>
        <button
          onClick={() => setShowCreate(true)}
          className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors"
        >
          Add Integration
        </button>
      </div>

      {showCreate && (
        <div className="mb-6 p-5 border border-gray-200 rounded-xl bg-gray-50">
          <h3 className="font-semibold text-gray-900 mb-4">New Integration</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Provider</label>
              <select
                value={selectedProvider}
                onChange={(e) => setSelectedProvider(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">Select provider...</option>
                {providers.map((p) => (
                  <option key={p.id} value={p.slug}>{p.name}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="My Integration"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Base URL</label>
              <input
                type="text"
                value={baseUrl}
                onChange={(e) => setBaseUrl(e.target.value)}
                placeholder="https://api.example.com"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">API Key</label>
              <input
                type="password"
                value={apiKey}
                onChange={(e) => setApiKey(e.target.value)}
                placeholder="Enter API key"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
          </div>
          <div className="flex gap-2 mt-4">
            <button
              onClick={handleCreate}
              disabled={!selectedProvider || !name}
              className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              Create
            </button>
            <button
              onClick={() => setShowCreate(false)}
              className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors"
            >
              Cancel
            </button>
          </div>
        </div>
      )}

      {loading ? (
        <div className="text-center py-12 text-gray-400">Loading...</div>
      ) : integrations.length === 0 ? (
        <div className="text-center py-12 text-gray-400">
          <p className="text-lg">No integrations configured</p>
          <p className="text-sm mt-1">Click "Add Integration" to get started</p>
        </div>
      ) : (
        <div className="space-y-4">
          {integrations.map((integration) => {
            const hlth = health.find((h) => h.id === integration.id)
            return (
              <div key={integration.id} className="border border-gray-200 rounded-xl p-5 hover:shadow-sm transition-shadow">
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center gap-3">
                      <h3 className="font-semibold text-gray-900">{integration.name}</h3>
                      <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[integration.status] || 'bg-gray-100'}`}>
                        {integration.status}
                      </span>
                    </div>
                    <p className="text-sm text-gray-500 mt-1">
                      Provider: {integration.provider?.name || 'Unknown'}
                    </p>
                    {hlth && hlth.error_count_24h > 0 && (
                      <p className="text-xs text-red-600 mt-1">{hlth.error_count_24h} errors in last 24h</p>
                    )}
                    {integration.last_error && (
                      <p className="text-xs text-red-600 mt-1 truncate">Last error: {integration.last_error}</p>
                    )}
                  </div>
                  <div className="flex gap-2">
                    <button
                      onClick={() => handleTest(integration.id)}
                      className="px-3 py-1.5 text-xs font-medium text-blue-600 border border-blue-200 rounded-lg hover:bg-blue-50 transition-colors"
                    >
                      Test
                    </button>
                    <button
                      onClick={() => handleToggle(integration)}
                      className="px-3 py-1.5 text-xs font-medium border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                      {integration.status === 'active' ? 'Deactivate' : 'Activate'}
                    </button>
                    <button
                      onClick={() => handleDelete(integration.id)}
                      className="px-3 py-1.5 text-xs font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors"
                    >
                      Delete
                    </button>
                  </div>
                </div>
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}

export function WebhooksPage() {
  const [endpoints, setEndpoints] = useState<WebhookEndpoint[]>([])
  const [events, setEvents] = useState<WebhookEvent[]>([])
  const [stats, setStats] = useState<WebhookStats | null>(null)
  const [deliveries, setDeliveries] = useState<WebhookDelivery[]>([])
  const [loading, setLoading] = useState(true)
  const [tab, setTab] = useState<'endpoints' | 'deliveries' | 'stats'>('endpoints')
  const [showCreate, setShowCreate] = useState(false)
  const [name, setName] = useState('')
  const [url, setUrl] = useState('')
  const [selectedEvents, setSelectedEvents] = useState<string[]>([])
  const [newSecret, setNewSecret] = useState<string | null>(null)

  const loadData = useCallback(async () => {
    setLoading(true)
    try {
      const [eps, evs, stat] = await Promise.all([
        webhookService.listEndpoints({ per_page: 100 }),
        webhookService.listEvents(),
        webhookService.getStats('24h'),
      ])
      setEndpoints(eps.data || [])
      setEvents(evs || [])
      setStats(stat)
    } catch {
      // ignore
    } finally {
      setLoading(false)
    }
  }, [])

  const loadDeliveries = useCallback(async () => {
    try {
      const dels = await webhookService.listDeliveries({ per_page: 50 })
      setDeliveries(dels.data || [])
    } catch {
      // ignore
    }
  }, [])

  useEffect(() => { loadData() }, [loadData])
  useEffect(() => { if (tab === 'deliveries') loadDeliveries() }, [tab, loadDeliveries])

  const handleCreate = async () => {
    if (!name || !url || selectedEvents.length === 0) return
    try {
      const result = await webhookService.createEndpoint({ name, url, events: selectedEvents })
      setNewSecret(result.secret)
      setShowCreate(false)
      setName('')
      setUrl('')
      setSelectedEvents([])
      loadData()
    } catch (err: any) {
      alert(err?.response?.data?.message || 'Failed to create endpoint')
    }
  }

  const handleDelete = async (id: number) => {
    if (!confirm('Delete this endpoint?')) return
    try {
      await webhookService.deleteEndpoint(id)
      loadData()
    } catch (err: any) {
      alert(err?.response?.data?.message || 'Failed to delete')
    }
  }

  const handleTest = async (id: number) => {
    try {
      const result = await webhookService.testEndpoint(id)
      alert(result.success ? `Test sent (${result.latency_ms}ms)` : `Failed: ${result.error || 'Unknown error'}`)
    } catch (err: any) {
      alert(err?.response?.data?.message || 'Test failed')
    }
  }

  const handleReplay = async (id: number) => {
    try {
      await webhookService.replayDelivery(id)
      loadDeliveries()
    } catch (err: any) {
      alert(err?.response?.data?.message || 'Replay failed')
    }
  }

  const toggleEvent = (slug: string) => {
    setSelectedEvents((prev) => prev.includes(slug) ? prev.filter((e) => e !== slug) : [...prev, slug])
  }

  const statusColors: Record<string, string> = {
    delivered: 'bg-green-100 text-green-700',
    pending: 'bg-blue-100 text-blue-700',
    failed: 'bg-red-100 text-red-700',
    dead_lettered: 'bg-gray-200 text-gray-700',
    replayed: 'bg-purple-100 text-purple-700',
  }

  return (
    <div className="p-6 max-w-7xl mx-auto">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Webhooks</h1>
          <p className="text-sm text-gray-500 mt-1">Manage webhook endpoints and delivery logs</p>
        </div>
        {tab === 'endpoints' && (
          <button
            onClick={() => setShowCreate(true)}
            className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors"
          >
            Add Endpoint
          </button>
        )}
      </div>

      {newSecret && (
        <div className="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-xl">
          <p className="text-sm font-medium text-yellow-800">Webhook Secret (save this now — it won't be shown again):</p>
          <code className="block mt-2 text-xs bg-white p-2 rounded font-mono break-all">{newSecret}</code>
          <button onClick={() => setNewSecret(null)} className="mt-2 text-xs text-yellow-700 underline">Dismiss</button>
        </div>
      )}

      <div className="flex gap-1 mb-6 border-b border-gray-200">
        {(['endpoints', 'deliveries', 'stats'] as const).map((t) => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
              tab === t ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            {t.charAt(0).toUpperCase() + t.slice(1)}
          </button>
        ))}
      </div>

      {loading ? (
        <div className="text-center py-12 text-gray-400">Loading...</div>
      ) : tab === 'endpoints' ? (
        <>
          {showCreate && (
            <div className="mb-6 p-5 border border-gray-200 rounded-xl bg-gray-50">
              <h3 className="font-semibold text-gray-900 mb-4">New Webhook Endpoint</h3>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
                  <input type="text" value={name} onChange={(e) => setName(e.target.value)} placeholder="My Webhook"
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">URL</label>
                  <input type="text" value={url} onChange={(e) => setUrl(e.target.value)} placeholder="https://example.com/webhook"
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Events</label>
                  <div className="grid grid-cols-2 md:grid-cols-3 gap-2">
                    {events.map((event) => (
                      <label key={event.id} className="flex items-center gap-2 text-sm">
                        <input
                          type="checkbox"
                          checked={selectedEvents.includes(event.slug)}
                          onChange={() => toggleEvent(event.slug)}
                          className="rounded border-gray-300"
                        />
                        <span className="text-gray-700">{event.slug}</span>
                      </label>
                    ))}
                  </div>
                </div>
              </div>
              <div className="flex gap-2 mt-4">
                <button onClick={handleCreate} disabled={!name || !url || selectedEvents.length === 0}
                  className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                  Create
                </button>
                <button onClick={() => setShowCreate(false)}
                  className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
                  Cancel
                </button>
              </div>
            </div>
          )}
          {endpoints.length === 0 ? (
            <div className="text-center py-12 text-gray-400">
              <p className="text-lg">No webhook endpoints</p>
              <p className="text-sm mt-1">Click "Add Endpoint" to get started</p>
            </div>
          ) : (
            <div className="space-y-3">
              {endpoints.map((endpoint) => (
                <div key={endpoint.id} className="border border-gray-200 rounded-xl p-4 hover:shadow-sm transition-shadow">
                  <div className="flex items-start justify-between">
                    <div className="flex-1">
                      <div className="flex items-center gap-3">
                        <h3 className="font-semibold text-gray-900">{endpoint.name}</h3>
                        <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${endpoint.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>
                          {endpoint.is_active ? 'Active' : 'Inactive'}
                        </span>
                      </div>
                      <p className="text-sm text-gray-500 mt-1 truncate">{endpoint.url}</p>
                      {endpoint.subscriptions && endpoint.subscriptions.length > 0 && (
                        <div className="flex flex-wrap gap-1 mt-2">
                          {endpoint.subscriptions.map((sub) => (
                            <span key={sub.id} className="px-2 py-0.5 bg-blue-50 text-blue-600 text-xs rounded">{sub.event_type}</span>
                          ))}
                        </div>
                      )}
                    </div>
                    <div className="flex gap-2">
                      <button onClick={() => handleTest(endpoint.id)}
                        className="px-3 py-1.5 text-xs font-medium text-blue-600 border border-blue-200 rounded-lg hover:bg-blue-50 transition-colors">
                        Test
                      </button>
                      <button onClick={() => handleDelete(endpoint.id)}
                        className="px-3 py-1.5 text-xs font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
                        Delete
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </>
      ) : tab === 'deliveries' ? (
        deliveries.length === 0 ? (
          <div className="text-center py-12 text-gray-400">No deliveries yet</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-gray-200 text-left text-gray-500">
                  <th className="pb-2 pr-4 font-medium">Event</th>
                  <th className="pb-2 pr-4 font-medium">Endpoint</th>
                  <th className="pb-2 pr-4 font-medium">Status</th>
                  <th className="pb-2 pr-4 font-medium">Attempts</th>
                  <th className="pb-2 pr-4 font-medium">Latency</th>
                  <th className="pb-2 pr-4 font-medium">Time</th>
                  <th className="pb-2 font-medium">Actions</th>
                </tr>
              </thead>
              <tbody>
                {deliveries.map((delivery) => (
                  <tr key={delivery.id} className="border-b border-gray-100">
                    <td className="py-2 pr-4 font-mono text-xs">{delivery.event_type}</td>
                    <td className="py-2 pr-4 text-gray-600">{delivery.endpoint?.name || '—'}</td>
                    <td className="py-2 pr-4">
                      <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[delivery.status] || 'bg-gray-100'}`}>
                        {delivery.status}
                      </span>
                    </td>
                    <td className="py-2 pr-4 text-gray-600">{delivery.attempt_count}</td>
                    <td className="py-2 pr-4 text-gray-600">{delivery.latency_ms ? `${delivery.latency_ms}ms` : '—'}</td>
                    <td className="py-2 pr-4 text-gray-500 text-xs">{new Date(delivery.created_at).toLocaleString()}</td>
                    <td className="py-2">
                      {(delivery.status === 'failed' || delivery.status === 'dead_lettered') && (
                        <button onClick={() => handleReplay(delivery.id)}
                          className="text-xs text-blue-600 hover:underline">
                          Replay
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )
      ) : stats ? (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="p-5 border border-gray-200 rounded-xl">
            <p className="text-sm text-gray-500">Total</p>
            <p className="text-2xl font-bold text-gray-900 mt-1">{stats.total}</p>
          </div>
          <div className="p-5 border border-gray-200 rounded-xl">
            <p className="text-sm text-gray-500">Delivered</p>
            <p className="text-2xl font-bold text-green-600 mt-1">{stats.delivered}</p>
          </div>
          <div className="p-5 border border-gray-200 rounded-xl">
            <p className="text-sm text-gray-500">Failed</p>
            <p className="text-2xl font-bold text-red-600 mt-1">{stats.failed}</p>
          </div>
          <div className="p-5 border border-gray-200 rounded-xl">
            <p className="text-sm text-gray-500">Success Rate</p>
            <p className="text-2xl font-bold text-blue-600 mt-1">{stats.success_rate}%</p>
          </div>
          <div className="p-5 border border-gray-200 rounded-xl">
            <p className="text-sm text-gray-500">Pending</p>
            <p className="text-2xl font-bold text-gray-900 mt-1">{stats.pending}</p>
          </div>
          <div className="p-5 border border-gray-200 rounded-xl">
            <p className="text-sm text-gray-500">Dead Lettered</p>
            <p className="text-2xl font-bold text-gray-600 mt-1">{stats.dead_lettered}</p>
          </div>
          <div className="p-5 border border-gray-200 rounded-xl">
            <p className="text-sm text-gray-500">Avg Latency</p>
            <p className="text-2xl font-bold text-gray-900 mt-1">{stats.avg_latency_ms}ms</p>
          </div>
          <div className="p-5 border border-gray-200 rounded-xl">
            <p className="text-sm text-gray-500">Period</p>
            <p className="text-2xl font-bold text-gray-900 mt-1">{stats.period}</p>
          </div>
        </div>
      ) : null}
    </div>
  )
}

export function ApiKeysPage() {
  const [keys, setKeys] = useState<IntegrationApiKey[]>([])
  const [loading, setLoading] = useState(true)
  const [showCreate, setShowCreate] = useState(false)
  const [name, setName] = useState('')
  const [scopes, setScopes] = useState<string[]>(['read'])
  const [newKey, setNewKey] = useState<string | null>(null)

  const loadData = useCallback(async () => {
    setLoading(true)
    try {
      const result = await apiKeyService.list()
      setKeys(result.data || [])
    } catch {
      // ignore
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { loadData() }, [loadData])

  const handleGenerate = async () => {
    if (!name) return
    try {
      const result = await apiKeyService.generate({ name, scopes })
      setNewKey(result.key)
      setShowCreate(false)
      setName('')
      setScopes(['read'])
      loadData()
    } catch (err: any) {
      alert(err?.response?.data?.message || 'Failed to generate key')
    }
  }

  const handleRevoke = async (id: number) => {
    if (!confirm('Revoke this API key? This cannot be undone.')) return
    try {
      await apiKeyService.revoke(id)
      loadData()
    } catch (err: any) {
      alert(err?.response?.data?.message || 'Failed to revoke')
    }
  }

  const handleRotate = async (id: number) => {
    if (!confirm('Rotate this API key? The old key will stop working immediately.')) return
    try {
      const result = await apiKeyService.rotate(id)
      setNewKey(result.key)
      loadData()
    } catch (err: any) {
      alert(err?.response?.data?.message || 'Failed to rotate')
    }
  }

  const toggleScope = (scope: string) => {
    setScopes((prev) => prev.includes(scope) ? prev.filter((s) => s !== scope) : [...prev, scope])
  }

  return (
    <div className="p-6 max-w-5xl mx-auto">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">API Keys</h1>
          <p className="text-sm text-gray-500 mt-1">Manage integration API keys for external access</p>
        </div>
        <button
          onClick={() => setShowCreate(true)}
          className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors"
        >
          Generate Key
        </button>
      </div>

      {newKey && (
        <div className="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-xl">
          <p className="text-sm font-medium text-yellow-800">API Key (save this now — it won't be shown again):</p>
          <code className="block mt-2 text-xs bg-white p-2 rounded font-mono break-all">{newKey}</code>
          <button onClick={() => setNewKey(null)} className="mt-2 text-xs text-yellow-700 underline">Dismiss</button>
        </div>
      )}

      {showCreate && (
        <div className="mb-6 p-5 border border-gray-200 rounded-xl bg-gray-50">
          <h3 className="font-semibold text-gray-900 mb-4">Generate New API Key</h3>
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
              <input type="text" value={name} onChange={(e) => setName(e.target.value)} placeholder="External System Key"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Scopes</label>
              <div className="flex gap-4">
                {['read', 'write', 'webhook'].map((scope) => (
                  <label key={scope} className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={scopes.includes(scope)} onChange={() => toggleScope(scope)} className="rounded border-gray-300" />
                    <span className="text-gray-700">{scope}</span>
                  </label>
                ))}
              </div>
            </div>
          </div>
          <div className="flex gap-2 mt-4">
            <button onClick={handleGenerate} disabled={!name}
              className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
              Generate
            </button>
            <button onClick={() => setShowCreate(false)}
              className="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-100 transition-colors">
              Cancel
            </button>
          </div>
        </div>
      )}

      {loading ? (
        <div className="text-center py-12 text-gray-400">Loading...</div>
      ) : keys.length === 0 ? (
        <div className="text-center py-12 text-gray-400">
          <p className="text-lg">No API keys</p>
          <p className="text-sm mt-1">Click "Generate Key" to create one</p>
        </div>
      ) : (
        <div className="space-y-3">
          {keys.map((key) => (
            <div key={key.id} className="border border-gray-200 rounded-xl p-4 hover:shadow-sm transition-shadow">
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <div className="flex items-center gap-3">
                    <h3 className="font-semibold text-gray-900">{key.name}</h3>
                    {key.is_revoked && (
                      <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Revoked</span>
                    )}
                  </div>
                  <p className="text-sm text-gray-500 mt-1 font-mono">{key.key_prefix}</p>
                  <div className="flex gap-1 mt-2">
                    {key.scopes.map((scope) => (
                      <span key={scope} className="px-2 py-0.5 bg-blue-50 text-blue-600 text-xs rounded">{scope}</span>
                    ))}
                  </div>
                  <p className="text-xs text-gray-400 mt-2">
                    Last used: {key.last_used_at ? new Date(key.last_used_at).toLocaleString() : 'Never'}
                  </p>
                </div>
                {!key.is_revoked && (
                  <div className="flex gap-2">
                    <button onClick={() => handleRotate(key.id)}
                      className="px-3 py-1.5 text-xs font-medium text-yellow-600 border border-yellow-200 rounded-lg hover:bg-yellow-50 transition-colors">
                      Rotate
                    </button>
                    <button onClick={() => handleRevoke(key.id)}
                      className="px-3 py-1.5 text-xs font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
                      Revoke
                    </button>
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
