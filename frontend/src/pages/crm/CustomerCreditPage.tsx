import { useState, useEffect, useCallback, useMemo } from 'react'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { Modal } from '@/components/ui/Modal'
import { customerService } from '@/services/customer'
import { customerCreditService, type CreditBalance } from '@/services/customerCredit'
import { useAuthStore } from '@/stores/auth'
import type { Customer, CustomerCreditTransaction, PaginatedResponse } from '@/types'

/* ---------- Icons ---------- */

const IC = {
  search: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
  ),
  refresh: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
  ),
  creditCard: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
  ),
  user: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
  ),
  phone: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
  ),
  eye: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
  ),
  check: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polyline points="20 6 9 17 4 12"/></svg>
  ),
  xCircle: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
  ),
  empty: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" className="h-12 w-12"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
  ),
  arrowUp: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
  ),
  arrowDown: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
  ),
  dollar: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
  ),
  plus: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
  ),
  wallet: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
  ),
}

const TX_TYPE_CONFIG: Record<string, { label: string; bg: string; text: string; icon: React.JSX.Element }> = {
  debit: { label: 'Hutang+', bg: 'bg-[#dc2626]/10', text: 'text-[#dc2626]', icon: IC.arrowUp },
  credit: { label: 'Bayar', bg: 'bg-[#16a34a]/10', text: 'text-[#16a34a]', icon: IC.arrowDown },
  adjust: { label: 'Penyesuaian', bg: 'bg-[#0a84ff]/10', text: 'text-[#0a84ff]', icon: IC.plus },
}

function getTxConfig(type: string) {
  return TX_TYPE_CONFIG[type] ?? { label: type, bg: 'bg-[#a8a29e]/10', text: 'text-[#a8a29e]', icon: IC.plus }
}

function getInitials(name: string): string {
  return name.split(' ').map((n) => n[0]).slice(0, 2).join('').toUpperCase() || '?'
}

function getAvatarColor(name: string): string {
  const colors = [
    'bg-[#f54927]/10 text-[#f54927]',
    'bg-[#0a84ff]/10 text-[#0a84ff]',
    'bg-[#16a34a]/10 text-[#16a34a]',
    'bg-[#ca8a04]/10 text-[#ca8a04]',
    'bg-[#9333ea]/10 text-[#9333ea]',
    'bg-[#0891b2]/10 text-[#0891b2]',
  ]
  const hash = name.split('').reduce((a, b) => a + b.charCodeAt(0), 0)
  return colors[hash % colors.length]
}

function formatRupiah(value: number) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value)
}

function Skeleton({ className }: { className: string }) {
  return <div className={`animate-pulse rounded-xl bg-[#f0efec] ${className}`} />
}

/* ---------- Component ---------- */

export function CustomerCreditPage() {
  const { user } = useAuthStore()
  const canManage = user?.role?.slug === 'owner' || user?.role?.slug === 'manager'

  const [customers, setCustomers] = useState<Customer[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [search, setSearch] = useState('')
  const [selectedCustomer, setSelectedCustomer] = useState<Customer | null>(null)
  const [balance, setBalance] = useState<CreditBalance | null>(null)
  const [transactions, setTransactions] = useState<CustomerCreditTransaction[]>([])
  const [txLoading, setTxLoading] = useState(false)
  const [adjustOpen, setAdjustOpen] = useState(false)
  const [adjustAmount, setAdjustAmount] = useState('')
  const [adjustNote, setAdjustNote] = useState('')
  const [adjustLoading, setAdjustLoading] = useState(false)

  const fetchCustomers = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const params: Record<string, unknown> = { page, per_page: 20 }
      if (search) params.search = search
      const res: PaginatedResponse<Customer> = await customerService.list(params)
      setCustomers(res.data)
      setLastPage(res.last_page)
      setTotal(res.total)
    } catch {
      setError('Failed to load customers')
    } finally {
      setLoading(false)
    }
  }, [page, search])

  useEffect(() => {
    const timer = setTimeout(() => fetchCustomers(), 250)
    return () => clearTimeout(timer)
  }, [search])

  useEffect(() => { fetchCustomers() }, [fetchCustomers])

  const handleViewTransactions = async (c: Customer) => {
    setSelectedCustomer(c)
    setTxLoading(true)
    setBalance(null)
    try {
      const [bal, tx] = await Promise.all([
        customerCreditService.getBalance(c.id).catch(() => null),
        customerCreditService.getTransactions(c.id, { per_page: 50 }),
      ])
      setBalance(bal)
      setTransactions(tx.data)
    } catch {
      setTransactions([])
    } finally {
      setTxLoading(false)
    }
  }

  const handleAdjust = async () => {
    if (!selectedCustomer) return
    setAdjustLoading(true)
    try {
      await customerCreditService.adjust(selectedCustomer.id, {
        amount: Number(adjustAmount),
        note: adjustNote,
      })
      setAdjustOpen(false)
      setAdjustAmount('')
      setAdjustNote('')
      handleViewTransactions(selectedCustomer)
    } catch {
      // ignore
    } finally {
      setAdjustLoading(false)
    }
  }

  const summary = useMemo(() => {
    const withDebt = customers.filter((c) => parseFloat(c.outstanding_balance ?? '0') > 0).length
    const totalDebt = customers.reduce((sum, c) => sum + parseFloat(c.outstanding_balance ?? '0'), 0)
    const withLimit = customers.filter((c) => c.credit_limit !== null).length
    return { total, withDebt, totalDebt, withLimit }
  }, [customers, total])

  return (
    <DashboardLayout>
      <div className="space-y-5">
        {/* ===== Header ===== */}
        <div className="animate-fade-up">
          <h1 className="text-[22px] font-bold tracking-tight text-[#1c1917]">Kredit Pelanggan</h1>
          <p className="mt-1 text-[13px] text-[#78716c]">Kelola limit kredit dan saldo hutang pelanggan</p>
        </div>

        {/* ===== Summary Cards ===== */}
        <div className="animate-fade-up grid grid-cols-2 gap-4 lg:grid-cols-4" style={{ animationDelay: '0.05s' }}>
          {loading && customers.length === 0 ? (
            [...Array(4)].map((_, i) => <Skeleton key={i} className="h-[90px]" />)
          ) : (
            <>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Total Pelanggan</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#1c1917] tabular-nums">{summary.total}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#0a84ff]/10 text-[#0a84ff]">{IC.user}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Punya Hutang</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#dc2626] tabular-nums">{summary.withDebt}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#dc2626]/10 text-[#dc2626]">{IC.xCircle}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#e7e5e4] bg-white p-4 transition-all hover:shadow-[0_4px_20px_rgba(0,0,0,0.04)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Punya Limit</p>
                    <p className="mt-1 text-[22px] font-bold tracking-tight text-[#16a34a] tabular-nums">{summary.withLimit}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#16a34a]/10 text-[#16a34a]">{IC.creditCard}</div>
                </div>
              </div>
              <div className="rounded-2xl border border-[#dc2626]/20 bg-gradient-to-br from-[#dc2626]/5 to-transparent p-4 transition-all hover:shadow-[0_4px_20px_rgba(220,38,38,0.08)]">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[11px] font-medium text-[#a8a29e]">Total Hutang</p>
                    <p className="mt-1 text-[16px] font-bold tracking-tight text-[#dc2626] tabular-nums">{formatRupiah(summary.totalDebt)}</p>
                  </div>
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#dc2626]/10 text-[#dc2626]">{IC.dollar}</div>
                </div>
              </div>
            </>
          )}
        </div>

        {/* ===== Search ===== */}
        <div className="animate-fade-up flex flex-wrap items-center gap-2" style={{ animationDelay: '0.1s' }}>
          <div className="relative min-w-[200px] flex-1">
            <div className="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-[#a8a29e]">{IC.search}</div>
            <input
              type="text"
              placeholder="Cari pelanggan..."
              value={search}
              onChange={(e) => { setSearch(e.target.value); setPage(1) }}
              className="h-10 w-full rounded-xl border border-[#e7e5e4] bg-white pr-4 pl-10 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            />
          </div>
          {search && (
            <button
              onClick={() => { setSearch(''); setPage(1) }}
              className="flex h-10 cursor-pointer items-center gap-1.5 rounded-xl border border-[#e7e5e4] bg-white px-3 text-[12px] font-medium text-[#78716c] transition-all hover:border-[#dc2626]/30 hover:text-[#dc2626]"
            >
              Reset
            </button>
          )}
        </div>

        {/* ===== Content ===== */}
        {loading ? (
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {[...Array(8)].map((_, i) => (
              <div key={i} className="rounded-2xl border border-[#e7e5e4] bg-white p-4">
                <div className="flex items-center gap-3">
                  <Skeleton className="h-12 w-12 rounded-full" />
                  <div className="flex-1 space-y-2">
                    <Skeleton className="h-4 w-24" />
                    <Skeleton className="h-3 w-16" />
                  </div>
                </div>
                <Skeleton className="mt-3 h-8 w-full" />
              </div>
            ))}
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center rounded-2xl border border-[#dc2626]/20 bg-[#fef2f2] py-16">
            <p className="text-[15px] font-semibold text-[#dc2626]">{error}</p>
            <button onClick={fetchCustomers} className="mt-3 flex h-9 cursor-pointer items-center gap-2 rounded-xl border border-[#e7e5e4] bg-white px-4 text-[13px] font-medium text-[#78716c] transition-all hover:bg-[#f5f5f4]">
              {IC.refresh} Coba lagi
            </button>
          </div>
        ) : customers.length === 0 ? (
          <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-[#e7e5e4] bg-white/50 py-20">
            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-[#f5f5f4] text-[#d6d3d1]">{IC.empty}</div>
            <p className="text-[15px] font-semibold text-[#78716c]">Belum ada pelanggan</p>
            <p className="mt-1 text-[12px] text-[#a8a29e]">Pelanggan akan muncul di sini</p>
          </div>
        ) : (
          /* ===== Grid Cards ===== */
          <div className="animate-fade-up grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" style={{ animationDelay: '0.15s' }}>
            {customers.map((c) => {
              const outstanding = parseFloat(c.outstanding_balance ?? '0')
              const hasDebt = outstanding > 0
              const hasLimit = c.credit_limit !== null && c.credit_limit > 0
              return (
                <div
                  key={c.id}
                  className={`group flex flex-col rounded-2xl border bg-white p-4 transition-all duration-200 hover:shadow-[0_8px_24px_rgba(0,0,0,0.06)] ${
                    hasDebt ? 'border-[#dc2626]/20 hover:border-[#dc2626]/40' : 'border-[#e7e5e4] hover:border-[#f54927]/30'
                  }`}
                >
                  {/* Header */}
                  <div className="flex items-start gap-3">
                    <div className={`flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full text-[14px] font-bold ${getAvatarColor(c.name)}`}>
                      {getInitials(c.name)}
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-[14px] font-bold text-[#1c1917]">{c.name}</p>
                      <span className={`mt-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[9px] font-bold ${
                        c.is_active ? 'bg-[#16a34a]/10 text-[#16a34a]' : 'bg-[#dc2626]/10 text-[#dc2626]'
                      }`}>
                        <span className={`h-1.5 w-1.5 rounded-full ${c.is_active ? 'bg-[#16a34a]' : 'bg-[#dc2626]'}`} />
                        {c.is_active ? 'Aktif' : 'Nonaktif'}
                      </span>
                    </div>
                  </div>

                  {/* Contact */}
                  {c.phone && (
                    <div className="mt-3 flex items-center gap-2 text-[12px] text-[#78716c]">
                      <span className="flex-shrink-0 text-[#a8a29e]">{IC.phone}</span>
                      <span className="truncate">{c.phone}</span>
                    </div>
                  )}

                  {/* Outstanding balance */}
                  <div className={`mt-3 flex items-center justify-between rounded-lg px-3 py-2 ${hasDebt ? 'bg-[#dc2626]/5' : 'bg-[#16a34a]/5'}`}>
                    <span className="flex items-center gap-1.5 text-[11px] font-medium text-[#78716c]">
                      {IC.wallet} Saldo
                    </span>
                    <span className={`text-[13px] font-bold tabular-nums ${hasDebt ? 'text-[#dc2626]' : 'text-[#16a34a]'}`}>
                      {formatRupiah(outstanding)}
                    </span>
                  </div>

                  {/* Credit limit */}
                  {hasLimit && (
                    <div className="mt-1.5 flex items-center justify-between text-[11px]">
                      <span className="text-[#a8a29e]">Limit: <span className="font-semibold text-[#78716c] tabular-nums">{formatRupiah(c.credit_limit!)}</span></span>
                    </div>
                  )}

                  {/* Action */}
                  <button
                    onClick={() => handleViewTransactions(c)}
                    className="mt-3 flex h-9 cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-[#e7e5e4] text-[12px] font-semibold text-[#78716c] transition-all hover:border-[#0a84ff]/30 hover:bg-[#0a84ff]/5 hover:text-[#0a84ff]"
                  >
                    {IC.eye} Lihat Kredit
                  </button>
                </div>
              )
            })}
          </div>
        )}

        {/* ===== Pagination ===== */}
        {lastPage > 1 && (
          <div className="animate-fade-up flex justify-center" style={{ animationDelay: '0.2s' }}>
            <div className="flex items-center gap-1">
              <button
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page === 1}
                className="flex h-9 cursor-pointer items-center gap-1 rounded-lg border border-[#e7e5e4] bg-white px-3 text-[12px] font-medium text-[#78716c] transition-all hover:bg-[#f5f5f4] disabled:cursor-not-allowed disabled:opacity-40"
              >
                Sebelumnya
              </button>
              <span className="px-3 text-[12px] font-medium text-[#78716c] tabular-nums">{page} / {lastPage}</span>
              <button
                onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
                disabled={page === lastPage}
                className="flex h-9 cursor-pointer items-center gap-1 rounded-lg border border-[#e7e5e4] bg-white px-3 text-[12px] font-medium text-[#78716c] transition-all hover:bg-[#f5f5f4] disabled:cursor-not-allowed disabled:opacity-40"
              >
                Berikutnya
              </button>
            </div>
          </div>
        )}
      </div>

      {/* ===== Detail Modal ===== */}
      <Modal
        open={selectedCustomer !== null}
        onClose={() => setSelectedCustomer(null)}
        title={`Kredit — ${selectedCustomer?.name ?? ''}`}
        size="lg"
        footer={
          <div className="flex w-full items-center justify-between">
            <button
              onClick={() => setSelectedCustomer(null)}
              className="flex h-10 cursor-pointer items-center gap-2 rounded-xl border border-[#e7e5e4] bg-white px-5 text-[13px] font-semibold text-[#78716c] transition-all hover:bg-[#f5f5f4]"
            >
              Tutup
            </button>
            {canManage && (
              <button
                onClick={() => setAdjustOpen(true)}
                className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-5 text-[13px] font-semibold text-white shadow-sm transition-all hover:shadow-md active:scale-[0.98]"
              >
                {IC.plus} Sesuaikan Saldo
              </button>
            )}
          </div>
        }
      >
        {txLoading ? (
          <div className="flex items-center justify-center py-12">
            <span className="h-8 w-8 animate-spin rounded-full border-2 border-[#f54927] border-t-transparent" />
          </div>
        ) : (
          <div className="space-y-5">
            {/* Balance cards */}
            {balance && (
              <div className="grid grid-cols-3 gap-3">
                <div className={`rounded-xl border p-4 ${balance.outstanding_balance > 0 ? 'border-[#dc2626]/20 bg-gradient-to-br from-[#dc2626]/5 to-transparent' : 'border-[#16a34a]/20 bg-gradient-to-br from-[#16a34a]/5 to-transparent'}`}>
                  <div className="flex items-center gap-2">
                    <div className={`flex h-8 w-8 items-center justify-center rounded-lg ${balance.outstanding_balance > 0 ? 'bg-[#dc2626]/10 text-[#dc2626]' : 'bg-[#16a34a]/10 text-[#16a34a]'}`}>{IC.wallet}</div>
                    <p className="text-[10px] font-bold tracking-wide text-[#a8a29e] uppercase">Saldo</p>
                  </div>
                  <p className={`mt-2 text-[18px] font-bold tabular-nums ${balance.outstanding_balance > 0 ? 'text-[#dc2626]' : 'text-[#16a34a]'}`}>{formatRupiah(balance.outstanding_balance)}</p>
                </div>
                <div className="rounded-xl border border-[#e7e5e4] bg-white p-4">
                  <div className="flex items-center gap-2">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-[#0a84ff]/10 text-[#0a84ff]">{IC.creditCard}</div>
                    <p className="text-[10px] font-bold tracking-wide text-[#a8a29e] uppercase">Limit</p>
                  </div>
                  <p className="mt-2 text-[18px] font-bold tabular-nums text-[#0a84ff]">{balance.credit_limit ? formatRupiah(balance.credit_limit) : '—'}</p>
                </div>
                <div className="rounded-xl border border-[#e7e5e4] bg-white p-4">
                  <div className="flex items-center gap-2">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-[#16a34a]/10 text-[#16a34a]">{IC.check}</div>
                    <p className="text-[10px] font-bold tracking-wide text-[#a8a29e] uppercase">Sisa</p>
                  </div>
                  <p className="mt-2 text-[18px] font-bold tabular-nums text-[#16a34a]">
                    {balance.credit_limit ? formatRupiah(Math.max(0, balance.credit_limit - balance.outstanding_balance)) : '—'}
                  </p>
                </div>
              </div>
            )}

            {/* Transactions */}
            <div className="space-y-2">
              <p className="text-[12px] font-bold tracking-wide text-[#44403c] uppercase">Riwayat Transaksi</p>
              {transactions.length === 0 ? (
                <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-[#e7e5e4] bg-[#fafaf9] py-10">
                  <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-[#f5f5f4] text-[#d6d3d1]">{IC.empty}</div>
                  <p className="text-[13px] font-medium text-[#78716c]">Belum ada transaksi</p>
                </div>
              ) : (
                <div className="max-h-[350px] space-y-2 overflow-y-auto pr-1">
                  {transactions.map((t) => {
                    const cfg = getTxConfig(t.type)
                    const amount = Number(t.amount)
                    const isPositive = amount >= 0
                    return (
                      <div key={t.id} className="flex items-center gap-3 rounded-xl border border-[#e7e5e4] bg-white p-3 transition-all hover:border-[#f54927]/20">
                        <div className={`flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg ${cfg.bg} ${cfg.text}`}>
                          {cfg.icon}
                        </div>
                        <div className="min-w-0 flex-1">
                          <div className="flex items-center gap-2">
                            <span className={`rounded-full ${cfg.bg} px-2 py-0.5 text-[9px] font-bold ${cfg.text}`}>{cfg.label}</span>
                            <span className="text-[11px] text-[#a8a29e]">{t.source}</span>
                          </div>
                          <p className="mt-0.5 text-[11px] text-[#a8a29e]">
                            {new Date(t.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                          </p>
                          {t.note && <p className="mt-0.5 truncate text-[11px] italic text-[#78716c]">"{t.note}"</p>}
                        </div>
                        <div className="flex flex-shrink-0 flex-col items-end">
                          <p className={`text-[14px] font-bold tabular-nums ${isPositive ? 'text-[#dc2626]' : 'text-[#16a34a]'}`}>
                            {isPositive ? '+' : ''}{formatRupiah(amount)}
                          </p>
                          <p className="text-[10px] text-[#a8a29e] tabular-nums">Saldo: {formatRupiah(Number(t.balance_after))}</p>
                        </div>
                      </div>
                    )
                  })}
                </div>
              )}
            </div>
          </div>
        )}
      </Modal>

      {/* ===== Adjust Modal ===== */}
      <Modal
        open={adjustOpen}
        onClose={() => setAdjustOpen(false)}
        title="Sesuaikan Saldo Kredit"
        size="sm"
        footer={
          <div className="flex w-full items-center justify-between">
            <button
              onClick={() => setAdjustOpen(false)}
              disabled={adjustLoading}
              className="flex h-10 cursor-pointer items-center gap-2 rounded-xl border border-[#e7e5e4] bg-white px-5 text-[13px] font-semibold text-[#78716c] transition-all hover:bg-[#f5f5f4] disabled:opacity-50"
            >
              Batal
            </button>
            <button
              onClick={handleAdjust}
              disabled={adjustLoading}
              className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-r from-[#f54927] to-[#ff6b4a] px-5 text-[13px] font-semibold text-white shadow-sm transition-all hover:shadow-md active:scale-[0.98] disabled:opacity-50"
            >
              {adjustLoading ? (
                <>
                  <span className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                  Menyimpan...
                </>
              ) : (
                <>{IC.check} Sesuaikan</>
              )}
            </button>
          </div>
        }
      >
        <div className="space-y-4">
          {/* Icon header */}
          <div className="flex items-center gap-3 rounded-xl bg-gradient-to-r from-[#f54927]/5 to-transparent p-4">
            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-[#f54927] to-[#ff6b4a] text-white">
              {IC.creditCard}
            </div>
            <div>
              <p className="text-[13px] font-bold text-[#1c1917]">{selectedCustomer?.name}</p>
              <p className="text-[11px] text-[#a8a29e]">Tambah atau kurangi saldo hutang</p>
            </div>
          </div>

          <div className="space-y-1.5">
            <label className="text-[12px] font-semibold text-[#44403c]">Jumlah (positif = tambah hutang, negatif = bayar)</label>
            <input
              type="number"
              value={adjustAmount}
              onChange={(e) => setAdjustAmount(e.target.value)}
              placeholder="-500000"
              className="h-10 w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[14px] font-bold tabular-nums text-[#1c1917] outline-none transition-all placeholder:font-normal placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            />
            {/* Quick buttons */}
            <div className="flex gap-1.5">
              {[-500000, -100000, -50000].map((v) => (
                <button
                  key={v}
                  type="button"
                  onClick={() => setAdjustAmount(String(v))}
                  className="flex h-7 flex-1 cursor-pointer items-center justify-center rounded-lg border border-[#16a34a]/20 text-[10px] font-bold text-[#16a34a] transition-all hover:bg-[#16a34a]/10 active:scale-95"
                >
                  {formatRupiah(v)}
                </button>
              ))}
            </div>
          </div>

          <div className="space-y-1.5">
            <label className="text-[12px] font-semibold text-[#44403c]">Catatan</label>
            <input
              value={adjustNote}
              onChange={(e) => setAdjustNote(e.target.value)}
              placeholder="Pembayaran diterima offline..."
              className="h-10 w-full rounded-xl border border-[#e7e5e4] bg-white px-3.5 text-[13px] text-[#1c1917] outline-none transition-all placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10"
            />
          </div>
        </div>
      </Modal>
    </DashboardLayout>
  )
}
