import { useEffect, useState } from 'react'
import { useParams, useSearchParams, useNavigate } from 'react-router-dom'
import type { ReportColumn, ReportResult } from '@/types/reports'
import type { ReportFilters } from '@/services/reports'
import { reportService } from '@/services/reports'
import { useModuleConfigStore } from '@/stores/module-config'

const reportCatalog: Record<string, { label: string; groups: string[]; sort: string[] }> = {
  sales: { label: 'Sales', groups: ['day', 'week', 'month'], sort: ['date', 'total', 'quantity'] },
  inventory: { label: 'Inventory', groups: [], sort: ['product_name', 'quantity', 'reorder_point'] },
  purchasing: { label: 'Purchasing', groups: ['day', 'supplier'], sort: ['date', 'supplier', 'total'] },
  customers: { label: 'Customers', groups: [], sort: ['customer_name', 'total', 'orders'] },
  payments: { label: 'Payments', groups: ['day', 'method'], sort: ['date', 'method', 'total'] },
  'product-performance': { label: 'Product Performance', groups: [], sort: ['product_name', 'quantity', 'revenue'] },
  'branch-comparison': { label: 'Branch Comparison', groups: [], sort: ['store_name', 'total'] },
  'trial-balance': { label: 'Trial Balance', groups: [], sort: ['account_code', 'account_name', 'debit', 'credit'] },
  'profit-loss': { label: 'Profit & Loss', groups: [], sort: ['account_type', 'account_name', 'amount'] },
  'balance-sheet': { label: 'Balance Sheet', groups: [], sort: ['account_type', 'account_code', 'balance'] },
  'cash-flow': { label: 'Cash Flow', groups: [], sort: ['classification', 'account_code', 'net_amount'] },
  'general-ledger': { label: 'General Ledger', groups: [], sort: ['entry_date', 'debit', 'credit'] },
  'ar-aging': { label: 'AR Aging', groups: [], sort: ['customer_name', 'balance', 'bucket'] },
  'ap-aging': { label: 'AP Aging', groups: [], sort: ['supplier_name', 'balance', 'bucket'] },
}

function formatValue(value: unknown, format?: string): string {
  if (value == null) return ''
  if (format === 'currency') {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value))
  }
  return String(value)
}

export function ReportsPage() {
  const { reportId } = useParams<{ reportId?: string }>()
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()
  const { stores } = useModuleConfigStore()

  const [result, setResult] = useState<ReportResult | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [filters, setFilters] = useState<ReportFilters>(() => ({
    date_from: searchParams.get('date_from') || '',
    date_to: searchParams.get('date_to') || '',
    store_id: Number(searchParams.get('store_id')) || undefined,
    group_by: searchParams.get('group_by') || undefined,
    sort: searchParams.get('sort') || undefined,
    page: Number(searchParams.get('page')) || 1,
    per_page: Number(searchParams.get('per_page')) || 25,
  }))

  const [dashboard, setDashboard] = useState<{ date_range: { from: string | null; to: string | null }; widgets: unknown[] } | null>(null)

  useEffect(() => {
    if (!reportId) {
      reportService.dashboard().then((d) => setDashboard(d)).catch(() => setError('Failed to load dashboard'))
      return
    }

    setLoading(true)
    reportService
      .run(reportId, filters)
      .then((r) => {
        setResult(r)
        setError(null)
      })
      .catch((err: Error) => setError(err.message || 'Report failed'))
      .finally(() => setLoading(false))
  }, [reportId, filters])

  const applyFilters = () => {
    const params = new URLSearchParams()
    if (filters.date_from) params.set('date_from', filters.date_from)
    if (filters.date_to) params.set('date_to', filters.date_to)
    if (filters.store_id) params.set('store_id', String(filters.store_id))
    if (filters.group_by) params.set('group_by', filters.group_by)
    if (filters.sort) params.set('sort', filters.sort)
    if (filters.page && filters.page > 1) params.set('page', String(filters.page))
    if (filters.per_page && filters.per_page !== 25) params.set('per_page', String(filters.per_page))
    setSearchParams(params)
  }

  const setSort = (col: ReportColumn) => {
    const current = filters.sort || ''
    const [currentCol] = current.split(':')
    const direction = currentCol === col.key && current.endsWith('asc') ? 'desc' : 'asc'
    setFilters((f) => ({ ...f, sort: `${col.key}:${direction}`, page: 1 }))
  }

  const exportReport = async (format: 'csv' | 'xlsx' | 'pdf') => {
    if (!reportId) return
    const blob = await reportService.export(reportId, format, filters as Record<string, unknown>)
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${reportId}.${format}`
    a.click()
    window.URL.revokeObjectURL(url)
  }

  const catalog = reportId ? reportCatalog[reportId] : null

  const financialReports = ['trial-balance', 'profit-loss', 'balance-sheet', 'cash-flow', 'general-ledger', 'ar-aging', 'ap-aging']
  const isFinancial = reportId ? financialReports.includes(reportId) : false

  return (
    <div className="min-h-screen bg-background p-4 md:p-6">
      <div className="mx-auto max-w-7xl">
        <h1 className="mb-6 text-2xl font-semibold text-foreground">Reports</h1>

        <div className="grid gap-6 lg:grid-cols-12">
          <aside className="lg:col-span-3">
            <div className="rounded-lg border border-border bg-card p-4">
              <h2 className="mb-3 text-sm font-medium text-muted-foreground">Operational</h2>
              <ul className="space-y-1">
                {['sales', 'inventory', 'purchasing', 'customers', 'payments', 'product-performance', 'branch-comparison'].map((id) => (
                  <li key={id}>
                    <button
                      onClick={() => navigate(`/reports/${id}`)}
                      className={`w-full rounded px-3 py-2 text-left text-sm ${reportId === id ? 'bg-primary text-primary-foreground' : 'hover:bg-secondary'}`}
                    >
                      {reportCatalog[id].label}
                    </button>
                  </li>
                ))}
              </ul>

              <h2 className="mb-3 mt-6 text-sm font-medium text-muted-foreground">Financial</h2>
              <ul className="space-y-1">
                {financialReports.map((id) => (
                  <li key={id}>
                    <button
                      onClick={() => navigate(`/reports/${id}`)}
                      className={`w-full rounded px-3 py-2 text-left text-sm ${reportId === id ? 'bg-primary text-primary-foreground' : 'hover:bg-secondary'}`}
                    >
                      {reportCatalog[id].label}
                    </button>
                  </li>
                ))}
              </ul>
            </div>
          </aside>

          <main className="lg:col-span-9">
            {!reportId && dashboard && (
              <div className="space-y-4">
                <p className="text-sm text-muted-foreground">Select a report to begin. Dashboard widgets will be configurable.</p>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                  {dashboard.widgets
                    .filter((w) => (w as { type: string }).type === 'kpi')
                    .map((w) => {
                      const kpi = w as { kpi_id: string; value?: { value: number; format?: string } }
                      return (
                        <div key={(w as { id: number }).id} className="rounded-lg border border-border bg-card p-4">
                          <p className="text-sm text-muted-foreground capitalize">{kpi.kpi_id.replace(/-/g, ' ')}</p>
                          <p className="mt-1 text-2xl font-semibold">{formatValue(kpi.value?.value, kpi.value?.format)}</p>
                        </div>
                      )
                    })}
                </div>
              </div>
            )}

            {reportId && (
              <div className="space-y-4">
                <div className="rounded-lg border border-border bg-card p-4">
                  <div className="mb-4 flex flex-wrap items-end gap-4">
                    <div>
                      <label className="mb-1 block text-xs text-muted-foreground">From</label>
                      <input
                        type="date"
                        value={filters.date_from}
                        onChange={(e) => setFilters((f) => ({ ...f, date_from: e.target.value }))}
                        className="rounded border border-input bg-background px-3 py-2 text-sm"
                      />
                    </div>
                    <div>
                      <label className="mb-1 block text-xs text-muted-foreground">To</label>
                      <input
                        type="date"
                        value={filters.date_to}
                        onChange={(e) => setFilters((f) => ({ ...f, date_to: e.target.value }))}
                        className="rounded border border-input bg-background px-3 py-2 text-sm"
                      />
                    </div>
                    {catalog?.groups.length ? (
                      <div>
                        <label className="mb-1 block text-xs text-muted-foreground">Group by</label>
                        <select
                          value={filters.group_by || ''}
                          onChange={(e) => setFilters((f) => ({ ...f, group_by: e.target.value || undefined, page: 1 }))}
                          className="rounded border border-input bg-background px-3 py-2 text-sm"
                        >
                          <option value="">None</option>
                          {catalog.groups.map((g) => (
                            <option key={g} value={g}>
                              {g}
                            </option>
                          ))}
                        </select>
                      </div>
                    ) : null}
                    {!isFinancial && (
                      <div>
                        <label className="mb-1 block text-xs text-muted-foreground">Store</label>
                        <select
                          value={filters.store_id || ''}
                          onChange={(e) => setFilters((f) => ({ ...f, store_id: e.target.value ? Number(e.target.value) : undefined, page: 1 }))}
                          className="rounded border border-input bg-background px-3 py-2 text-sm"
                        >
                          <option value="">All stores</option>
                          {stores.map((s) => (
                            <option key={s.id} value={s.id}>
                              {s.name}
                            </option>
                          ))}
                        </select>
                      </div>
                    )}
                    <div className="ml-auto flex gap-2">
                      <button onClick={applyFilters} className="rounded bg-primary px-4 py-2 text-sm text-primary-foreground hover:opacity-90">Run</button>
                      <button onClick={() => exportReport('csv')} className="rounded border border-border px-3 py-2 text-sm hover:bg-secondary">CSV</button>
                      <button onClick={() => exportReport('xlsx')} className="rounded border border-border px-3 py-2 text-sm hover:bg-secondary">XLSX</button>
                      <button onClick={() => exportReport('pdf')} className="rounded border border-border px-3 py-2 text-sm hover:bg-secondary">PDF</button>
                    </div>
                  </div>

                  {error && <p className="text-sm text-destructive">{error}</p>}
                  {loading && <p className="text-sm text-muted-foreground">Loading...</p>}

                  {!loading && result && (
                    <>
                      <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-sm">
                          <thead>
                            <tr className="border-b border-border text-left">
                              {result.columns.map((col) => (
                                <th
                                  key={col.key}
                                  onClick={() => setSort(col)}
                                  className="cursor-pointer px-3 py-2 font-medium text-muted-foreground hover:text-foreground"
                                >
                                  {col.label}
                                  {filters.sort?.startsWith(col.key) && <span className="ml-1">{filters.sort.endsWith('asc') ? '↑' : '↓'}</span>}
                                </th>
                              ))}
                            </tr>
                          </thead>
                          <tbody>
                            {result.data.map((row, idx) => (
                              <tr key={idx} className="border-b border-border/50">
                                {result.columns.map((col) => (
                                  <td key={col.key} className="px-3 py-2">
                                    {formatValue(row[col.key], col.format)}
                                  </td>
                                ))}
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>

                      {result.meta && (
                        <div className="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm">
                          <p className="text-muted-foreground">
                            Page {result.meta.current_page} of {result.meta.last_page} · {result.meta.total} records
                          </p>
                          <div className="flex gap-2">
                            <button
                              disabled={result.meta.current_page === 1}
                              onClick={() => setFilters((f) => ({ ...f, page: (f.page || 1) - 1 }))}
                              className="rounded border border-border px-3 py-1 disabled:opacity-50"
                            >
                              Previous
                            </button>
                            <button
                              disabled={result.meta.current_page === result.meta.last_page}
                              onClick={() => setFilters((f) => ({ ...f, page: (f.page || 1) + 1 }))}
                              className="rounded border border-border px-3 py-1 disabled:opacity-50"
                            >
                              Next
                            </button>
                          </div>
                        </div>
                      )}
                    </>
                  )}
                </div>
              </div>
            )}
          </main>
        </div>
      </div>
    </div>
  )
}
