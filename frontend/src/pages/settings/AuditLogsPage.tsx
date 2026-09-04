import { useState, useEffect, useCallback } from 'react'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/Card'
import api from '@/lib/api'
import { securityService } from '@/services/security'

interface AuditLogEntry {
  id: number
  tenant_id: number
  user_id: number | null
  action: string
  entity_type: string
  entity_id: number | null
  old_values: any
  new_values: any
  ip_address: string | null
  user_agent: string | null
  route: string | null
  method: string | null
  created_at: string
  user?: { id: number; name: string; email: string }
}

interface AuditLogResponse {
  data: AuditLogEntry[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export function AuditLogsPage() {
  const [logs, setLogs] = useState<AuditLogEntry[]>([])
  const [meta, setMeta] = useState<any>(null)
  const [loading, setLoading] = useState(false)
  const [filters, setFilters] = useState({
    entity_type: '',
    action: '',
    user_id: '',
    date_from: '',
    date_to: '',
    route: '',
    method: '',
    page: 1,
  })

  const fetchLogs = useCallback(async () => {
    setLoading(true)
    try {
      const params = Object.entries(filters).filter(([, v]) => v !== '').reduce((acc, [k, v]) => ({ ...acc, [k]: v }), {})
      const res = await api.get('/audit-logs', { params })
      const data: AuditLogResponse = res.data
      setLogs(data.data)
      setMeta(data.meta)
    } catch {
      // ignore
    } finally {
      setLoading(false)
    }
  }, [filters])

  useEffect(() => {
    fetchLogs()
  }, [fetchLogs])

  const handleExport = async () => {
    try {
      const blob = await securityService.exportAuditLogsCsv()
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `audit-logs-${new Date().toISOString().split('T')[0]}.csv`
      a.click()
      window.URL.revokeObjectURL(url)
    } catch {
      // ignore
    }
  }

  const handleFilterChange = (key: string, value: string) => {
    setFilters((prev) => ({ ...prev, [key]: value, page: 1 }))
  }

  const handlePageChange = (page: number) => {
    setFilters((prev) => ({ ...prev, page }))
  }

  return (
    <DashboardLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-foreground">Audit Logs</h1>
            <p className="text-muted-foreground">Track all system and user actions</p>
          </div>
          <button
            onClick={handleExport}
            className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-gray-50"
          >
            Export CSV
          </button>
        </div>

        {/* Filters */}
        <Card>
          <CardHeader>
            <CardTitle>Filters</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
              <div>
                <label className="mb-1 block text-xs font-medium text-muted-foreground">Action</label>
                <input
                  type="text"
                  value={filters.action}
                  onChange={(e) => handleFilterChange('action', e.target.value)}
                  placeholder="e.g. created, updated"
                  className="w-full rounded-lg border px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label className="mb-1 block text-xs font-medium text-muted-foreground">Entity Type</label>
                <input
                  type="text"
                  value={filters.entity_type}
                  onChange={(e) => handleFilterChange('entity_type', e.target.value)}
                  placeholder="e.g. Product"
                  className="w-full rounded-lg border px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label className="mb-1 block text-xs font-medium text-muted-foreground">Route</label>
                <input
                  type="text"
                  value={filters.route}
                  onChange={(e) => handleFilterChange('route', e.target.value)}
                  placeholder="e.g. categories"
                  className="w-full rounded-lg border px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label className="mb-1 block text-xs font-medium text-muted-foreground">Method</label>
                <select
                  value={filters.method}
                  onChange={(e) => handleFilterChange('method', e.target.value)}
                  className="w-full rounded-lg border px-3 py-2 text-sm"
                >
                  <option value="">All</option>
                  <option value="GET">GET</option>
                  <option value="POST">POST</option>
                  <option value="PUT">PUT</option>
                  <option value="PATCH">PATCH</option>
                  <option value="DELETE">DELETE</option>
                </select>
              </div>
              <div>
                <label className="mb-1 block text-xs font-medium text-muted-foreground">Date From</label>
                <input
                  type="date"
                  value={filters.date_from}
                  onChange={(e) => handleFilterChange('date_from', e.target.value)}
                  className="w-full rounded-lg border px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label className="mb-1 block text-xs font-medium text-muted-foreground">Date To</label>
                <input
                  type="date"
                  value={filters.date_to}
                  onChange={(e) => handleFilterChange('date_to', e.target.value)}
                  className="w-full rounded-lg border px-3 py-2 text-sm"
                />
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Logs Table */}
        <Card>
          <CardContent className="p-0">
            {loading ? (
              <div className="p-8 text-center text-muted-foreground">Loading...</div>
            ) : logs.length === 0 ? (
              <div className="p-8 text-center text-muted-foreground">No audit logs found.</div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b bg-gray-50 text-left">
                      <th className="px-4 py-3 font-medium text-muted-foreground">Time</th>
                      <th className="px-4 py-3 font-medium text-muted-foreground">User</th>
                      <th className="px-4 py-3 font-medium text-muted-foreground">Action</th>
                      <th className="px-4 py-3 font-medium text-muted-foreground">Entity</th>
                      <th className="px-4 py-3 font-medium text-muted-foreground">Method</th>
                      <th className="px-4 py-3 font-medium text-muted-foreground">Route</th>
                      <th className="px-4 py-3 font-medium text-muted-foreground">IP</th>
                    </tr>
                  </thead>
                  <tbody>
                    {logs.map((log) => (
                      <tr key={log.id} className="border-b last:border-0 hover:bg-gray-50">
                        <td className="px-4 py-3 text-xs text-muted-foreground">
                          {new Date(log.created_at).toLocaleString()}
                        </td>
                        <td className="px-4 py-3 text-xs">
                          {log.user?.name ?? `User #${log.user_id ?? 'N/A'}`}
                        </td>
                        <td className="px-4 py-3">
                          <span className="inline-block rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">
                            {log.action}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-xs text-muted-foreground">
                          {log.entity_type?.split('\\').pop()} #{log.entity_id}
                        </td>
                        <td className="px-4 py-3">
                          <span className="text-xs font-mono">{log.method}</span>
                        </td>
                        <td className="px-4 py-3 text-xs text-muted-foreground">{log.route}</td>
                        <td className="px-4 py-3 text-xs text-muted-foreground">{log.ip_address}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            {/* Pagination */}
            {meta && meta.last_page > 1 && (
              <div className="flex items-center justify-between border-t px-4 py-3">
                <p className="text-xs text-muted-foreground">
                  Page {meta.current_page} of {meta.last_page} ({meta.total} total)
                </p>
                <div className="flex gap-1">
                  <button
                    onClick={() => handlePageChange(meta.current_page - 1)}
                    disabled={meta.current_page <= 1}
                    className="rounded border px-3 py-1 text-xs disabled:opacity-50"
                  >
                    Prev
                  </button>
                  <button
                    onClick={() => handlePageChange(meta.current_page + 1)}
                    disabled={meta.current_page >= meta.last_page}
                    className="rounded border px-3 py-1 text-xs disabled:opacity-50"
                  >
                    Next
                  </button>
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </DashboardLayout>
  )
}
