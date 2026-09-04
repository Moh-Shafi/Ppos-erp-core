import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { useAuthStore } from '@/stores/auth'
import { useModuleConfigStore } from '@/stores/module-config'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import api from '@/lib/api'
import { formatRupiah } from '@/lib/utils'
import type { DashboardData } from '@/types'

/* ---------- Icons ---------- */

const I = {
  revenue: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><line x1="12" y1="2" x2="12" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
  ),
  transactions: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
  ),
  products: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
  ),
  customers: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
  ),
  alert: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
  ),
  arrowRight: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
  ),
  receipt: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1z"/><path d="M8 7h8"/><path d="M8 11h8"/><path d="M8 15h5"/></svg>
  ),
  inventory: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3z"/><path d="M12 12l8-4.5"/><path d="M12 12v9"/><path d="M12 12L4 7.5"/></svg>
  ),
  clock: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
  ),
  cash: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><rect x="1" y="4" width="22" height="16" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 12h.01"/><path d="M18 12h.01"/></svg>
  ),
  qris: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3z"/><path d="M21 14v3"/><path d="M18 21h3"/></svg>
  ),
  card: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
  ),
  transfer: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/><path d="M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
  ),
  star: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="h-4 w-4"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
  ),
  target: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
  ),
  activity: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
  ),
  trendingUp: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
  ),
  trendingDown: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3"><polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/><polyline points="16 17 22 17 22 11"/></svg>
  ),
  check: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
  ),
  login: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
  ),
  create: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
  ),
  update: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
  ),
  delete: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
  ),
  payment: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
  ),
  default: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  ),
}

/* ---------- Helpers ---------- */

function getGreeting(): string {
  const h = new Date().getHours()
  if (h >= 4 && h < 11) return 'Selamat pagi'
  if (h >= 11 && h < 15) return 'Selamat siang'
  if (h >= 15 && h < 18) return 'Selamat sore'
  return 'Selamat malam'
}

function Skeleton({ className }: { className: string }) {
  return <div className={`animate-pulse rounded-xl bg-[#f0efec] ${className}`} />
}

function formatShort(n: number): string {
  if (n >= 1000000) return `Rp ${(n / 1000000).toFixed(1)}jt`
  if (n >= 1000) return `Rp ${(n / 1000).toFixed(0)}rb`
  return `Rp ${n}`
}

/* ---------- Sparkline ---------- */

function Sparkline({ data, color }: { data: number[]; color: string }) {
  if (!data || data.length < 2) return null
  const max = Math.max(...data)
  const min = Math.min(...data)
  const range = max - min || 1
  const h = 32
  const w = 80
  const pts = data.map((v, i) => ({ x: (i / (data.length - 1)) * w, y: h - ((v - min) / range) * h }))
  const path = pts.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ')
  const area = `${path} L ${w} ${h} L 0 ${h} Z`
  return (
    <svg viewBox={`0 0 ${w} ${h}`} className="h-8 w-20" aria-hidden="true">
      <path d={area} fill={color} fillOpacity="0.08" />
      <path d={path} fill="none" stroke={color} strokeWidth="1.5" strokeLinecap="round" />
    </svg>
  )
}

/* ---------- Weekly Bar Chart ---------- */

function WeeklyChart({ data, maxRevenue }: { data: DashboardData['weekly_data']; maxRevenue: number }) {
  const maxVal = maxRevenue || 1
  return (
    <div className="rounded-2xl border border-[#e7e5e4] bg-white">
      <div className="flex items-center justify-between border-b border-[#f5f5f4] px-5 py-4">
        <div className="flex items-center gap-2.5">
          <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-[#f54927]/10 text-[#f54927]">{I.transactions}</div>
          <div>
            <h3 className="text-[14px] font-semibold text-[#1c1917]">Penjualan Mingguan</h3>
            <p className="text-[11px] text-[#a8a29e]">7 hari terakhir</p>
          </div>
        </div>
        <Link to="/sales" className="flex cursor-pointer items-center gap-1 text-[12px] font-semibold text-[#f54927] transition-colors hover:text-[#d63a1b]">Detail {I.arrowRight}</Link>
      </div>
      <div className="px-5 py-5">
        {data && data.length > 0 ? (
          <div className="flex h-40 items-end justify-between gap-2">
            {data.map((d) => {
              const pct = (d.revenue / maxVal) * 100
              return (
                <div key={d.date} className="group flex flex-1 flex-col items-center gap-2">
                  <div className="relative w-full">
                    <div className="w-full rounded-t-lg bg-gradient-to-t from-[#f54927] to-[#ff7a5c] transition-all duration-700 hover:opacity-80" style={{ height: `${Math.max(pct * 1.4, 4)}px` }} />
                    <div className="absolute -top-7 left-1/2 -translate-x-1/2 rounded-md bg-[#1c1917] px-1.5 py-0.5 text-[9px] font-bold whitespace-nowrap text-white opacity-0 transition-opacity group-hover:opacity-100">
                      {formatShort(d.revenue)}
                    </div>
                  </div>
                  <span className="text-[10px] font-medium text-[#a8a29e]">{d.day}</span>
                </div>
              )
            })}
          </div>
        ) : (
          <div className="flex h-40 items-center justify-center text-[13px] text-[#a8a29e]">Belum ada data</div>
        )}
      </div>
    </div>
  )
}

/* ---------- Payment Donut ---------- */

const PAYMENT_COLORS: Record<string, string> = {
  qris: '#f54927',
  cash: '#16a34a',
  card: '#0a84ff',
  bank_transfer: '#8b5cf6',
}

function PaymentDonut({ methods }: { methods: DashboardData['payment_methods'] }) {
  const total = methods.reduce((s, m) => s + m.percentage, 0) || 1
  const size = 140
  const cx = size / 2
  const cy = size / 2
  const r = 52
  const sw = 20
  let acc = 0

  return (
    <div className="rounded-2xl border border-[#e7e5e4] bg-white">
      <div className="flex items-center gap-2.5 border-b border-[#f5f5f4] px-5 py-4">
        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-[#8b5cf6]/10 text-[#8b5cf6]">{I.card}</div>
        <div>
          <h3 className="text-[14px] font-semibold text-[#1c1917]">Metode Pembayaran</h3>
          <p className="text-[11px] text-[#a8a29e]">Distribusi pembayaran</p>
        </div>
      </div>
      {methods && methods.length > 0 ? (
        <>
          <div className="flex items-center justify-center px-5 py-5">
            <div className="relative">
              <svg width={size} height={size} className="-rotate-90">
                <circle cx={cx} cy={cy} r={r} fill="none" stroke="#f0efec" strokeWidth={sw} />
                {methods.map((m) => {
                  const pct = m.percentage / total
                  const dash = pct * 2 * Math.PI * r
                  const gap = 2 * Math.PI * r - dash
                  const offset = -acc * 2 * Math.PI * r
                  acc += pct
                  return (
                    <circle key={m.method} cx={cx} cy={cy} r={r} fill="none" stroke={PAYMENT_COLORS[m.method] ?? '#a8a29e'} strokeWidth={sw} strokeDasharray={`${dash} ${gap}`} strokeDashoffset={offset} strokeLinecap="round" className="transition-all duration-500" />
                  )
                })}
              </svg>
              <div className="absolute inset-0 flex flex-col items-center justify-center">
                <p className="text-[20px] font-bold text-[#1c1917] tabular-nums">{methods.length}</p>
                <p className="text-[10px] text-[#a8a29e]">Metode</p>
              </div>
            </div>
          </div>
          <div className="space-y-2 px-5 pb-5">
            {methods.map((m) => (
              <div key={m.method} className="flex items-center gap-2.5">
                <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: PAYMENT_COLORS[m.method] ?? '#a8a29e' }} />
                <span className="flex-1 text-[12px] text-[#78716c]">{m.label}</span>
                <span className="text-[11px] text-[#a8a29e] tabular-nums">{m.count}x</span>
                <span className="text-[12px] font-bold text-[#1c1917] tabular-nums">{m.percentage}%</span>
              </div>
            ))}
          </div>
        </>
      ) : (
        <div className="flex flex-col items-center py-10 text-center">
          <div className="mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-[#f5f5f4] text-[#a8a29e]">{I.card}</div>
          <p className="text-[12px] font-medium text-[#78716c]">Belum ada pembayaran</p>
        </div>
      )}
    </div>
  )
}

/* ---------- Top Products ---------- */

function TopProducts({ products }: { products: DashboardData['top_products'] }) {
  return (
    <div className="rounded-2xl border border-[#e7e5e4] bg-white">
      <div className="flex items-center justify-between border-b border-[#f5f5f4] px-5 py-4">
        <div className="flex items-center gap-2.5">
          <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-[#ffd60a]/10 text-[#ca8a04]">{I.star}</div>
          <div>
            <h3 className="text-[14px] font-semibold text-[#1c1917]">Produk Terlaris</h3>
            <p className="text-[11px] text-[#a8a29e]">7 hari terakhir</p>
          </div>
        </div>
        <Link to="/products" className="flex cursor-pointer items-center gap-1 text-[12px] font-semibold text-[#f54927] transition-colors hover:text-[#d63a1b]">Semua {I.arrowRight}</Link>
      </div>
      {products && products.length > 0 ? (
        <div className="divide-y divide-[#f5f5f4]">
          {products.map((p) => (
            <div key={p.product_id} className="flex items-center gap-3.5 px-5 py-3 transition-colors hover:bg-[#fafaf9]">
              <span className={`flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg text-[12px] font-bold ${
                p.rank === 1 ? 'bg-[#ffd60a]/15 text-[#ca8a04]'
                  : p.rank === 2 ? 'bg-[#f54927]/10 text-[#f54927]'
                    : p.rank === 3 ? 'bg-[#0a84ff]/10 text-[#0a84ff]'
                      : 'bg-[#f5f5f4] text-[#a8a29e]'
              }`}>{p.rank}</span>
              <div className="min-w-0 flex-1">
                <p className="truncate text-[13px] font-semibold text-[#1c1917]">{p.name}</p>
                <p className="text-[11px] text-[#a8a29e]">{p.sold} terjual</p>
              </div>
              <div className="text-right">
                <p className="text-[12px] font-bold text-[#1c1917] tabular-nums">{formatShort(p.revenue)}</p>
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="flex flex-col items-center py-10 text-center">
          <div className="mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-[#f5f5f4] text-[#a8a29e]">{I.star}</div>
          <p className="text-[12px] font-medium text-[#78716c]">Belum ada penjualan</p>
        </div>
      )}
    </div>
  )
}

/* ---------- Sales Target Ring ---------- */

function SalesTarget({ target }: { target: DashboardData['sales_target'] }) {
  const pct = target?.percentage ?? 0
  const size = 150
  const cx = size / 2
  const cy = size / 2
  const r = 58
  const sw = 14
  const circ = 2 * Math.PI * r
  const offset = circ - (pct / 100) * circ

  return (
    <div className="rounded-2xl border border-[#e7e5e4] bg-white">
      <div className="flex items-center gap-2.5 border-b border-[#f5f5f4] px-5 py-4">
        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-[#16a34a]/10 text-[#16a34a]">{I.target}</div>
        <div>
          <h3 className="text-[14px] font-semibold text-[#1c1917]">Target Hari Ini</h3>
          <p className="text-[11px] text-[#a8a29e]">{target ? formatRupiah(target.target) : '-'}</p>
        </div>
      </div>
      <div className="flex flex-col items-center px-5 py-5">
        <div className="relative">
          <svg width={size} height={size} className="-rotate-90">
            <circle cx={cx} cy={cy} r={r} fill="none" stroke="#f0efec" strokeWidth={sw} />
            <circle cx={cx} cy={cy} r={r} fill="none" stroke="#f54927" strokeWidth={sw} strokeDasharray={circ} strokeDashoffset={offset} strokeLinecap="round" className="transition-all duration-1000" />
          </svg>
          <div className="absolute inset-0 flex flex-col items-center justify-center">
            <p className="text-[26px] font-bold text-[#1c1917] tabular-nums">{pct}%</p>
            <p className="text-[10px] text-[#a8a29e]">tercapai</p>
          </div>
        </div>
        <div className="mt-4 w-full space-y-2">
          <div className="flex justify-between text-[12px]">
            <span className="text-[#78716c]">Tercapai</span>
            <span className="font-bold text-[#1c1917] tabular-nums">{target ? formatRupiah(target.current) : '-'}</span>
          </div>
          <div className="flex justify-between text-[12px]">
            <span className="text-[#78716c]">Sisa target</span>
            <span className="font-medium text-[#dc2626] tabular-nums">{target ? formatRupiah(target.remaining) : '-'}</span>
          </div>
          <div className="h-2 overflow-hidden rounded-full bg-[#f0efec]">
            <div className="h-full rounded-full bg-gradient-to-r from-[#f54927] to-[#ff7a5c] transition-all duration-1000" style={{ width: `${pct}%` }} />
          </div>
        </div>
      </div>
    </div>
  )
}

/* ---------- Recent Activity ---------- */

const ACTIVITY_COLORS: Record<string, string> = {
  blue: 'bg-[#0a84ff]/10 text-[#0a84ff]',
  green: 'bg-[#16a34a]/10 text-[#16a34a]',
  yellow: 'bg-[#ffd60a]/10 text-[#ca8a04]',
  red: 'bg-[#dc2626]/10 text-[#dc2626]',
  gray: 'bg-[#f5f5f4] text-[#78716c]',
}

function getActivityIcon(icon: string) {
  const icons: Record<string, React.ReactNode> = {
    login: I.login, create: I.create, update: I.update, delete: I.delete, payment: I.payment, register: I.create, default: I.default,
  }
  return icons[icon] ?? I.default
}

function RecentActivity({ activities }: { activities: DashboardData['activities'] }) {
  return (
    <div className="rounded-2xl border border-[#e7e5e4] bg-white">
      <div className="flex items-center gap-2.5 border-b border-[#f5f5f4] px-5 py-4">
        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-[#0a84ff]/10 text-[#0a84ff]">{I.activity}</div>
        <div>
          <h3 className="text-[14px] font-semibold text-[#1c1917]">Aktivitas Terbaru</h3>
          <p className="text-[11px] text-[#a8a29e]">Log sistem</p>
        </div>
      </div>
      {activities && activities.length > 0 ? (
        <div className="divide-y divide-[#f5f5f4]">
          {activities.map((a) => (
            <div key={a.id} className="flex items-start gap-3 px-5 py-3 transition-colors hover:bg-[#fafaf9]">
              <div className={`mt-0.5 flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg ${ACTIVITY_COLORS[a.color] ?? ACTIVITY_COLORS.gray}`}>
                {getActivityIcon(a.icon)}
              </div>
              <div className="min-w-0 flex-1">
                <p className="text-[13px] leading-snug text-[#1c1917]">{a.text}</p>
                <p className="mt-0.5 text-[11px] text-[#a8a29e]">{a.user} · {a.time}</p>
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="flex flex-col items-center py-10 text-center">
          <div className="mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-[#f5f5f4] text-[#a8a29e]">{I.activity}</div>
          <p className="text-[12px] font-medium text-[#78716c]">Belum ada aktivitas</p>
        </div>
      )}
    </div>
  )
}

/* ---------- Dashboard Page ---------- */

export function DashboardPage() {
  const { user } = useAuthStore()
  const moduleConfig = useModuleConfigStore()
  const [data, setData] = useState<DashboardData | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!moduleConfig.loaded) {
      moduleConfig.loadConfig()
    }
  }, [moduleConfig])

  useEffect(() => {
    api
      .get<DashboardData>('/dashboard')
      .then((r) => setData(r.data))
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [moduleConfig.currentStore?.id])

  const today = new Date().toLocaleDateString('id-ID', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
  })

  const stats = data
    ? [
        {
          label: 'Pendapatan Hari Ini',
          value: formatRupiah(data.stats.today_revenue),
          icon: I.revenue,
          color: '#f54927',
          bgColor: 'bg-[#f54927]/10',
          trend: data.stats.revenue_trend_pct,
          sparkline: data.stats.revenue_trend ?? [],
        },
        {
          label: 'Transaksi Hari Ini',
          value: String(data.stats.today_sales_count),
          icon: I.transactions,
          color: '#0a84ff',
          bgColor: 'bg-[#0a84ff]/10',
          trend: data.stats.count_trend_pct,
          sparkline: data.stats.count_trend ?? [],
        },
        {
          label: 'Total Produk',
          value: String(data.stats.total_products),
          icon: I.products,
          color: '#16a34a',
          bgColor: 'bg-[#16a34a]/10',
          trend: null,
          sparkline: [],
        },
        {
          label: 'Total Pelanggan',
          value: String(data.stats.total_customers),
          icon: I.customers,
          color: '#8b5cf6',
          bgColor: 'bg-[#8b5cf6]/10',
          trend: null,
          sparkline: [],
        },
      ]
    : []

  return (
    <DashboardLayout>
      <div className="space-y-6">
        {/* ===== Header ===== */}
        <div className="animate-fade-up flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
          <div>
            <h1 className="text-[22px] font-bold tracking-tight text-[#1c1917]">{getGreeting()}, {user?.name}</h1>
            <p className="mt-1 text-[13px] text-[#78716c]">{today}</p>
            {moduleConfig.businessProfile && (
              <p className="mt-0.5 flex items-center gap-1.5 text-[12px] text-[#a8a29e]">
                <span className="inline-flex h-1.5 w-1.5 rounded-full bg-[#22c55e]" />
                {moduleConfig.businessProfile.business_name} — {moduleConfig.businessProfile.business_type?.name ?? ''}
              </p>
            )}
          </div>
          <Link to="/pos" className="flex h-10 cursor-pointer items-center gap-2 rounded-xl bg-[#f54927] px-4 text-[13px] font-semibold text-white shadow-[0_2px_10px_rgba(245,73,39,0.25)] transition-all duration-200 hover:bg-[#e13e1c] active:scale-[0.97]">
            {I.receipt} Buka Kasir
          </Link>
        </div>

        {/* ===== Stats Cards ===== */}
        <div className="animate-fade-up grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" style={{ animationDelay: '0.05s' }}>
          {loading ? (
            [...Array(4)].map((_, i) => <Skeleton key={i} className="h-[120px]" />)
          ) : (
            stats.map((stat) => (
              <div key={stat.label} className="group relative overflow-hidden rounded-2xl border border-[#e7e5e4] bg-white p-5 transition-all duration-200 hover:border-[#d6d3d1] hover:shadow-[0_8px_30px_rgba(0,0,0,0.06)]">
                <div className="flex items-start justify-between">
                  <div>
                    <p className="text-[12px] font-medium text-[#a8a29e]">{stat.label}</p>
                    <p className="mt-2 text-[26px] font-bold tracking-tight text-[#1c1917] tabular-nums">{stat.value}</p>
                  </div>
                  <div className={`flex h-10 w-10 items-center justify-center rounded-xl ${stat.bgColor}`} style={{ color: stat.color }}>{stat.icon}</div>
                </div>
                <div className="mt-3 flex items-end justify-between">
                  {stat.trend !== null ? (
                    <span className={`flex items-center gap-1 text-[12px] font-medium ${stat.trend >= 0 ? 'text-[#16a34a]' : 'text-[#dc2626]'}`}>
                      {stat.trend >= 0 ? I.trendingUp : I.trendingDown}
                      {stat.trend >= 0 ? '+' : ''}{stat.trend}%
                    </span>
                  ) : (
                    <span className="text-[11px] text-[#a8a29e]">Total</span>
                  )}
                  {stat.sparkline.length > 0 && <Sparkline data={stat.sparkline} color={stat.color} />}
                </div>
              </div>
            ))
          )}
        </div>

        {/* ===== Row 1: Weekly + Payment + Target ===== */}
        <div className="animate-fade-up grid grid-cols-1 gap-6 lg:grid-cols-3" style={{ animationDelay: '0.1s' }}>
          {loading ? (
            <>
              <Skeleton className="h-[260px]" />
              <Skeleton className="h-[260px]" />
              <Skeleton className="h-[260px]" />
            </>
          ) : (
            <>
              <WeeklyChart data={data?.weekly_data ?? []} maxRevenue={data?.weekly_max ?? 1} />
              <PaymentDonut methods={data?.payment_methods ?? []} />
              <SalesTarget target={data?.sales_target ?? { target: 0, current: 0, percentage: 0, remaining: 0 }} />
            </>
          )}
        </div>

        {/* ===== Row 2: Top Products + Activity + Recent Sales/Low Stock ===== */}
        <div className="animate-fade-up grid grid-cols-1 gap-6 lg:grid-cols-3" style={{ animationDelay: '0.15s' }}>
          {loading ? (
            <>
              <Skeleton className="h-[300px]" />
              <Skeleton className="h-[300px]" />
              <Skeleton className="h-[300px]" />
            </>
          ) : (
            <>
              <TopProducts products={data?.top_products ?? []} />
              <RecentActivity activities={data?.activities ?? []} />

              {/* Recent Sales + Low Stock stacked */}
              <div className="space-y-6">
                {/* Recent Sales */}
                <div className="rounded-2xl border border-[#e7e5e4] bg-white">
                  <div className="flex items-center justify-between border-b border-[#f5f5f4] px-5 py-4">
                    <div className="flex items-center gap-2.5">
                      <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-[#0a84ff]/10 text-[#0a84ff]">{I.transactions}</div>
                      <h3 className="text-[14px] font-semibold text-[#1c1917]">Transaksi Terbaru</h3>
                    </div>
                    <Link to="/sales" className="flex cursor-pointer items-center gap-1 text-[12px] font-semibold text-[#f54927] transition-colors hover:text-[#d63a1b]">Semua {I.arrowRight}</Link>
                  </div>
                  <div className="divide-y divide-[#f5f5f4]">
                    {data && data.recent_sales.length > 0 ? (
                      data.recent_sales.slice(0, 4).map((sale) => (
                        <div key={sale.id} className="flex items-center justify-between px-5 py-3 transition-colors hover:bg-[#fafaf9]">
                          <div className="flex items-center gap-3">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-[#f5f5f4] text-[#78716c]">{I.receipt}</div>
                            <div>
                              <p className="text-[12px] font-semibold text-[#1c1917]">{sale.sale_number}</p>
                              <p className="text-[10px] text-[#a8a29e]">{sale.store?.name ?? ''}</p>
                            </div>
                          </div>
                          <div className="flex items-center gap-2">
                            <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${
                              sale.status === 'completed' ? 'bg-[#16a34a]/10 text-[#16a34a]'
                                : sale.status === 'cancelled' ? 'bg-[#dc2626]/10 text-[#dc2626]'
                                  : 'bg-[#ca8a04]/10 text-[#ca8a04]'
                            }`}>
                              {sale.status === 'completed' ? 'Selesai' : sale.status === 'cancelled' ? 'Batal' : 'Refund'}
                            </span>
                            <p className="text-[12px] font-bold text-[#1c1917] tabular-nums">{formatRupiah(Number(sale.total))}</p>
                          </div>
                        </div>
                      ))
                    ) : (
                      <div className="flex flex-col items-center py-8 text-center">
                        <div className="mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-[#f5f5f4] text-[#a8a29e]">{I.receipt}</div>
                        <p className="text-[12px] font-medium text-[#78716c]">Belum ada transaksi</p>
                      </div>
                    )}
                  </div>
                </div>

                {/* Low Stock */}
                <div className="rounded-2xl border border-[#e7e5e4] bg-white">
                  <div className="flex items-center justify-between border-b border-[#f5f5f4] px-5 py-4">
                    <div className="flex items-center gap-2.5">
                      <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-[#dc2626]/10 text-[#dc2626]">{I.alert}</div>
                      <h3 className="text-[14px] font-semibold text-[#1c1917]">Stok Menipis</h3>
                    </div>
                    {data && data.low_stock.length > 0 && (
                      <span className="rounded-full bg-[#dc2626]/10 px-2 py-0.5 text-[11px] font-bold text-[#dc2626]">{data.low_stock.length}</span>
                    )}
                  </div>
                  <div className="divide-y divide-[#f5f5f4]">
                    {data && data.low_stock.length > 0 ? (
                      data.low_stock.slice(0, 4).map((inv) => (
                        <div key={inv.id} className="flex items-center justify-between px-5 py-2.5 transition-colors hover:bg-[#fafaf9]">
                          <div>
                            <p className="text-[12px] font-semibold text-[#1c1917]">{inv.product?.name ?? `Produk #${inv.product_id}`}</p>
                            <p className="text-[10px] text-[#a8a29e]">{inv.product?.sku ?? ''}</p>
                          </div>
                          <p className="text-[12px] font-bold text-[#dc2626] tabular-nums">{inv.quantity}</p>
                        </div>
                      ))
                    ) : (
                      <div className="flex flex-col items-center py-8 text-center">
                        <div className="mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-[#16a34a]/10 text-[#16a34a]">{I.check}</div>
                        <p className="text-[12px] font-medium text-[#78716c]">Stok aman</p>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            </>
          )}
        </div>
      </div>
    </DashboardLayout>
  )
}
