import { type ReactNode, useState, useMemo } from 'react'
import { NavLink, useNavigate, useLocation } from 'react-router-dom'
import { useAuthStore } from '@/stores/auth'
import { useModuleConfigStore } from '@/stores/module-config'
import { authService } from '@/services/auth'

/* ---------- Icons ---------- */

const IC = {
  dashboard: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
  ),
  pos: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 9h4"/><path d="M7 13h2"/></svg>
  ),
  sales: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
  ),
  discount: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
  ),
  products: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
  ),
  categories: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M4 4h6v6H4z"/><path d="M14 4h6v6h-6z"/><path d="M4 14h6v6H4z"/><path d="M14 14h6v6h-6z"/></svg>
  ),
  units: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M21 6H3"/><path d="M21 12H3"/><path d="M21 18H3"/></svg>
  ),
  pricelist: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
  ),
  inventory: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3z"/><path d="M12 12l8-4.5"/><path d="M12 12v9"/><path d="M12 12L4 7.5"/></svg>
  ),
  movements: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
  ),
  transfer: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M16 3h5v5"/><path d="M8 3H3v5"/><path d="M21 3l-7 7"/><path d="M3 3l7 7"/><path d="M16 21h5v-5"/><path d="M8 21H3v-5"/><path d="M21 21l-7-7"/><path d="M3 21l7-7"/></svg>
  ),
  stocktake: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
  ),
  valuation: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><line x1="12" y1="2" x2="12" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
  ),
  warehouse: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M3 21V8l9-5 9 5v13"/><path d="M9 21v-6h6v6"/><path d="M3 8h18"/></svg>
  ),
  customers: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
  ),
  loyalty: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
  ),
  credit: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
  ),
  suppliers: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
  ),
  purchases: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
  ),
  returns: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
  ),
  requisition: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
  ),
  grn: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/></svg>
  ),
  invoice: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1z"/><path d="M8 7h8"/><path d="M8 11h8"/><path d="M8 15h5"/></svg>
  ),
  reorder: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/><path d="M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
  ),
  settings: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
  ),
  account: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
  ),
  logout: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px]"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
  ),
  chevron: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><polyline points="6 9 12 15 18 9"/></svg>
  ),
  search: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
  ),
  bell: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px]"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
  ),
  menu: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
  ),
  collapse: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px]"><polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/></svg>
  ),
  expand: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px]"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg>
  ),
  store: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><path d="M3 9l3-3h12l3 3"/><path d="M4 9v11a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V9"/><path d="M9 21V12h6v9"/></svg>
  ),
  sidebarSearch: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
  ),
  sparkle: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="h-3 w-3"><path d="M12 2l1.5 5.5L19 9l-5.5 1.5L12 16l-1.5-5.5L5 9l5.5-1.5L12 2z"/></svg>
  ),
  restaurant: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M3 6h18M3 12h18M3 18h18"/><circle cx="7" cy="6" r="1"/><circle cx="17" cy="6" r="1"/><circle cx="7" cy="12" r="1"/><circle cx="17" cy="12" r="1"/><circle cx="7" cy="18" r="1"/><circle cx="17" cy="18" r="1"/></svg>
  ),
  promotion: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
  ),
  appointment: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
  ),
  service: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
  ),
  integration: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M9 2v6"/><path d="M15 2v6"/><path d="M9 22v-6"/><path d="M15 22v-6"/><path d="M5 10h14"/><path d="M5 14h14"/><rect x="7" y="8" width="10" height="8" rx="1"/></svg>
  ),
  webhook: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M18 16.08h-1.5A2.5 2.5 0 0 1 14 13.58V10a6 6 0 0 0-12 0v3.58a2.5 2.5 0 0 1-2.5 2.5H0"/><path d="M12 10v8"/><path d="M8 14h8"/></svg>
  ),
  apikey: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 6.5m0 0l3 3L22 6l-3-3m-3.5 3.5L19 10"/></svg>
  ),
  shield: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
  ),
  audit: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
  ),
  health: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
  ),
  privacy: (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-[18px] w-[18px] flex-shrink-0"><path d="M9 12l2 2 4-4"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/><path d="M3 5c0 1.66 4 3 9 3s9-1.34 9-3-4-3-9-3-9 1.34-9 3z"/></svg>
  ),
}

/* ---------- Nav data ---------- */

interface NavItem {
  to: string
  label: string
  icon: keyof typeof IC
  module: string
  permission: string | null
  feature?: string
}

interface NavGroup {
  title: string
  items: NavItem[]
}

const NAV_GROUPS: NavGroup[] = [
  {
    title: 'Utama',
    items: [
      { to: '/dashboard', label: 'Dashboard', icon: 'dashboard', module: 'core', permission: null },
      { to: '/pos', label: 'Kasir / POS', icon: 'pos', module: 'pos', permission: 'pos.use' },
      { to: '/sales', label: 'Riwayat Transaksi', icon: 'sales', module: 'sales', permission: 'sales.view' },
    ],
  },
  {
    title: 'Produk',
    items: [
      { to: '/products', label: 'Produk', icon: 'products', module: 'core', permission: 'products.view' },
      { to: '/categories', label: 'Kategori', icon: 'categories', module: 'core', permission: 'categories.view' },
      { to: '/units', label: 'Satuan', icon: 'units', module: 'core', permission: 'products.view' },
      { to: '/price-lists', label: 'Price Lists', icon: 'pricelist', module: 'core', permission: 'products.view' },
    ],
  },
  {
    title: 'Stok',
    items: [
      { to: '/inventory', label: 'Inventory', icon: 'inventory', module: 'inventory', permission: 'inventory.view' },
      { to: '/inventory/movements', label: 'Movements', icon: 'movements', module: 'inventory', permission: 'inventory.view' },
      { to: '/inventory/transfer', label: 'Quick Transfer', icon: 'transfer', module: 'inventory', permission: 'inventory.manage' },
      { to: '/inventory/transfer-requests', label: 'Transfer Requests', icon: 'movements', module: 'inventory', permission: 'inventory.view' },
      { to: '/inventory/stocktake', label: 'Stocktake', icon: 'stocktake', module: 'inventory', permission: 'inventory.stocktake' },
      { to: '/inventory/valuation', label: 'Valuation', icon: 'valuation', module: 'inventory', permission: 'inventory.valuation' },
      { to: '/warehouses', label: 'Warehouses', icon: 'warehouse', module: 'warehouse', permission: 'warehouse.view' },
    ],
  },
  {
    title: 'Pelanggan',
    items: [
      { to: '/customers', label: 'Customers', icon: 'customers', module: 'customers', permission: 'customers.view' },
      { to: '/crm/loyalty', label: 'Loyalty Points', icon: 'loyalty', module: 'customers', permission: 'crm.view', feature: 'customers.loyalty_points' },
      { to: '/crm/credit', label: 'Customer Credit', icon: 'credit', module: 'customers', permission: 'crm.view', feature: 'sales.customer_credit' },
    ],
  },
  {
    title: 'Pembelian',
    items: [
      { to: '/suppliers', label: 'Suppliers', icon: 'suppliers', module: 'suppliers', permission: 'suppliers.view' },
      { to: '/purchases', label: 'Purchases', icon: 'purchases', module: 'purchasing', permission: 'purchases.view' },
      { to: '/purchase-returns', label: 'Returns', icon: 'returns', module: 'purchasing', permission: 'purchases.view' },
      { to: '/purchasing/requisitions', label: 'Requisitions', icon: 'requisition', module: 'purchasing', permission: 'purchases.view', feature: 'purchasing.requisition' },
      { to: '/purchasing/grns', label: 'Goods Receipt', icon: 'grn', module: 'purchasing', permission: 'purchases.view' },
      { to: '/purchasing/invoices', label: 'Supplier Invoices', icon: 'invoice', module: 'purchasing', permission: 'purchases.view', feature: 'purchasing.invoice_matching' },
      { to: '/purchasing/auto-reorder', label: 'Auto Reorder', icon: 'reorder', module: 'purchasing', permission: 'purchases.view' },
    ],
  },
  {
    title: 'Restoran',
    items: [
      { to: '/tables', label: 'Tables', icon: 'restaurant', module: 'tables', permission: 'tables.view' },
      { to: '/reservations', label: 'Reservations', icon: 'appointment', module: 'reservations', permission: 'reservations.view' },
    ],
  },
  {
    title: 'Retail',
    items: [
      { to: '/promotions', label: 'Promotions', icon: 'promotion', module: 'promotions', permission: 'promotions.view' },
      { to: '/loyalty-programs', label: 'Loyalty Programs', icon: 'loyalty', module: 'loyalty', permission: 'loyalty.view' },
    ],
  },
  {
    title: 'Service',
    items: [
      { to: '/appointments', label: 'Appointments', icon: 'appointment', module: 'appointments', permission: 'appointments.view' },
      { to: '/services', label: 'Service Catalog', icon: 'service', module: 'services', permission: 'services.view' },
    ],
  },
  {
    title: 'Lainnya',
    items: [
      { to: '/pos/discount-presets', label: 'Preset Diskon', icon: 'discount', module: 'pos', permission: 'pos.discount_presets', feature: 'pos.discount_presets' },
      { to: '/integrations', label: 'Integrations', icon: 'integration', module: 'integrations', permission: 'integrations.view' },
      { to: '/webhooks', label: 'Webhooks', icon: 'webhook', module: 'integrations', permission: 'webhooks.view' },
      { to: '/api-keys', label: 'API Keys', icon: 'apikey', module: 'integrations', permission: 'apikeys.view' },
      { to: '/settings/security', label: 'Keamanan', icon: 'shield', module: 'core', permission: null },
      { to: '/settings/audit-logs', label: 'Audit Logs', icon: 'audit', module: 'audit', permission: 'audit.view' },
      { to: '/settings/privacy', label: 'Privasi Data', icon: 'privacy', module: 'core', permission: null },
      { to: '/settings/store', label: 'Pengaturan Toko', icon: 'settings', module: 'settings', permission: 'settings.manage' },
      { to: '/settings/receipt', label: 'Pengaturan Struk', icon: 'invoice', module: 'settings', permission: 'settings.manage' },
      { to: '/settings/account', label: 'Akun', icon: 'account', module: 'core', permission: null },
    ],
  },
]

/* ---------- Component ---------- */

export function DashboardLayout({ children }: { children: ReactNode }) {
  const { user, logout } = useAuthStore()
  const navigate = useNavigate()
  const location = useLocation()
  const moduleConfig = useModuleConfigStore()
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const [collapsed, setCollapsed] = useState(false)
  const [navSearch, setNavSearch] = useState('')
  const [collapsedGroups, setCollapsedGroups] = useState<Record<string, boolean>>({})

  const visibleGroups = useMemo(() => {
    let groups = NAV_GROUPS.map((g) => ({
      ...g,
      items: g.items.filter(
        (item) =>
          moduleConfig.hasModule(item.module) &&
          (!item.permission || moduleConfig.hasPermission(item.permission)) &&
          (!item.feature || moduleConfig.hasFeature(item.feature)),
      ),
    })).filter((g) => g.items.length > 0)

    if (navSearch.trim()) {
      const q = navSearch.toLowerCase()
      groups = groups
        .map((g) => ({ ...g, items: g.items.filter((i) => i.label.toLowerCase().includes(q)) }))
        .filter((g) => g.items.length > 0)
    }
    return groups
  }, [moduleConfig, navSearch])

  const handleLogout = async () => {
    try { await authService.logout() } catch { /* ignore */ }
    logout()
    navigate('/login')
  }

  const handleStoreChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const storeId = Number(e.target.value)
    const store = moduleConfig.stores.find((s) => s.id === storeId)
    if (store) moduleConfig.setStore(store)
  }

  const toggleGroup = (title: string) => {
    setCollapsedGroups((prev) => ({ ...prev, [title]: !prev[title] }))
  }

  const currentPage = visibleGroups
    .flatMap((g) => g.items)
    .find((i) => i.to === location.pathname)?.label ?? 'Dashboard'

  /* ===== Sidebar content ===== */

  const sidebarContent = (isMobile = false) => (
    <aside
      className={`flex h-full flex-col bg-gradient-to-b from-[#0f0f17] via-[#13131f] to-[#0a0a12] transition-all duration-300 ease-in-out ${
        isMobile ? 'w-[280px]' : collapsed ? 'w-[72px]' : 'w-[270px]'
      }`}
    >
      {/* Ambient glow */}
      <div className="pointer-events-none absolute inset-0 overflow-hidden">
        <div className="absolute -top-20 -left-10 h-40 w-40 rounded-full bg-[#f54927]/20 blur-3xl" />
        <div className="absolute top-1/2 -right-10 h-32 w-32 rounded-full bg-[#6366f1]/10 blur-3xl" />
      </div>

      {/* Logo */}
      <div className="relative flex items-center justify-between px-4 pt-5 pb-4">
        {!collapsed || isMobile ? (
          <div className="flex items-center gap-3">
            <div className="relative flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-[#f54927] to-[#ff6b4a] shadow-[0_4px_16px_rgba(245,73,39,0.4)]">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5 text-white">
                <rect x="3" y="3" width="18" height="18" rx="4"/><path d="M3 9h18"/><path d="M9 9v12"/>
              </svg>
              <div className="absolute -top-0.5 -right-0.5 flex h-3 w-3 items-center justify-center rounded-full bg-[#22c55e] ring-2 ring-[#0f0f17]">
                <div className="h-1.5 w-1.5 rounded-full bg-white" />
              </div>
            </div>
            <div>
              <p className="text-[15px] font-bold tracking-tight text-white">KasirPOS</p>
              <p className="flex items-center gap-1 text-[10px] font-medium tracking-wide text-white/40">
                <span className="text-[#f54927]">{IC.sparkle}</span>
                Premium POS
              </p>
            </div>
          </div>
        ) : (
          <div className="relative mx-auto flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-[#f54927] to-[#ff6b4a] shadow-[0_4px_16px_rgba(245,73,39,0.4)]">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5 text-white">
              <rect x="3" y="3" width="18" height="18" rx="4"/><path d="M3 9h18"/><path d="M9 9v12"/>
            </svg>
          </div>
        )}
        {!isMobile && (
          <button
            type="button"
            onClick={() => setCollapsed((c) => !c)}
            className="hidden cursor-pointer rounded-lg p-1.5 text-white/30 transition-all hover:bg-white/10 hover:text-white/70 lg:block"
            aria-label={collapsed ? 'Expand' : 'Collapse'}
          >
            {collapsed ? IC.expand : IC.collapse}
          </button>
        )}
      </div>

      {/* Search */}
      {(!collapsed || isMobile) && (
        <div className="relative px-3 pb-3">
          <div className="group relative">
            <div className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-white/30 transition-colors group-focus-within:text-[#f54927]">
              {IC.sidebarSearch}
            </div>
            <input
              type="text"
              value={navSearch}
              onChange={(e) => setNavSearch(e.target.value)}
              placeholder="Cari menu..."
              className="h-9 w-full rounded-xl border border-white/10 bg-white/5 pr-3 pl-9 text-[12px] text-white/90 transition-all outline-none placeholder:text-white/30 focus:border-[#f54927]/50 focus:bg-white/8 focus:shadow-[0_0_0_3px_rgba(245,73,39,0.08)]"
            />
          </div>
        </div>
      )}

      {/* Navigation */}
      <nav className="relative flex-1 space-y-1 overflow-y-auto px-3 pb-2 [scrollbar-width:thin] [scrollbar-color:rgba(255,255,255,0.1)_transparent]">
        {visibleGroups.map((group) => (
          <div key={group.title}>
            {(!collapsed || isMobile) && (
              <button
                type="button"
                onClick={() => toggleGroup(group.title)}
                className="flex w-full cursor-pointer items-center justify-between rounded-md px-3 py-2 text-[10px] font-bold tracking-[0.12em] text-white/30 uppercase transition-colors hover:text-white/50"
              >
                {group.title}
                <span className={`transition-transform duration-200 ${collapsedGroups[group.title] ? '-rotate-90' : ''}`}>
                  {IC.chevron}
                </span>
              </button>
            )}
            {(!collapsedGroups[group.title] || collapsed) && (
              <div className="space-y-0.5">
                {group.items.map((item) => (
                  <NavLink
                    key={item.to}
                    to={item.to}
                    title={collapsed && !isMobile ? item.label : undefined}
                    className={({ isActive }) =>
                      `group relative flex items-center gap-3 rounded-xl transition-all duration-200 ${
                        collapsed && !isMobile ? 'justify-center px-0 py-2.5' : 'px-3 py-2.5'
                      } ${isActive ? 'bg-gradient-to-r from-[#f54927]/20 to-transparent' : 'hover:bg-white/5'}`
                    }
                  >
                    {({ isActive }) => (
                      <>
                        {/* Active indicator bar */}
                        {isActive && (
                          <span className="absolute top-1/2 left-0 h-5 w-1 -translate-y-1/2 rounded-r-full bg-gradient-to-b from-[#f54927] to-[#ff6b4a] shadow-[0_0_8px_rgba(245,73,39,0.6)]" />
                        )}
                        {/* Icon */}
                        <span
                          className={`flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg transition-all duration-200 ${
                            isActive
                              ? 'bg-gradient-to-br from-[#f54927] to-[#ff6b4a] text-white shadow-[0_2px_10px_rgba(245,73,39,0.4)]'
                              : 'bg-white/5 text-white/40 group-hover:bg-white/10 group-hover:text-white/70'
                          }`}
                        >
                          {IC[item.icon]}
                        </span>
                        {/* Label */}
                        {(!collapsed || isMobile) && (
                          <span className={`flex-1 text-[13px] font-medium transition-colors ${isActive ? 'text-white' : 'text-white/55 group-hover:text-white/85'}`}>
                            {item.label}
                          </span>
                        )}
                        {/* Active dot */}
                        {isActive && (!collapsed || isMobile) && (
                          <span className="h-1.5 w-1.5 rounded-full bg-[#f54927] shadow-[0_0_6px_rgba(245,73,39,0.8)]" />
                        )}
                      </>
                    )}
                  </NavLink>
                ))}
              </div>
            )}
          </div>
        ))}
      </nav>

      {/* User card — bottom of sidebar */}
      {(!collapsed || isMobile) ? (
        <div className="relative mx-3 mb-3 rounded-2xl border border-white/8 bg-gradient-to-br from-white/8 to-white/3 p-3">
          <div className="flex items-center gap-3">
            <div className="relative">
              <div className="absolute inset-0 rounded-full bg-gradient-to-br from-[#f54927] to-[#6366f1] opacity-60 blur-sm" />
              <div className="relative flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-[#f54927] to-[#ff6b4a] text-[12px] font-bold text-white">
                {user?.name?.charAt(0).toUpperCase() ?? '?'}
              </div>
            </div>
            <div className="min-w-0 flex-1">
              <p className="truncate text-[12.5px] font-semibold text-white">{user?.name ?? '...'}</p>
              <p className="truncate text-[10px] text-white/40">{user?.role?.name ?? 'Super Admin'}</p>
            </div>
            <div className="flex h-2 w-2 items-center justify-center">
              <div className="h-2 w-2 animate-pulse rounded-full bg-[#22c55e]" />
            </div>
          </div>
          {moduleConfig.stores.length > 1 && (
            <div className="mt-2.5 flex items-center gap-2 rounded-lg bg-black/20 px-2.5 py-1.5">
              <span className="text-white/40">{IC.store}</span>
              <select
                value={moduleConfig.currentStore?.id ?? ''}
                onChange={handleStoreChange}
                className="flex-1 cursor-pointer bg-transparent text-[11px] font-medium text-white/70 outline-none [&>option]:text-[#1c1917]"
              >
                {moduleConfig.stores.map((s) => (
                  <option key={s.id} value={s.id}>{s.name}</option>
                ))}
              </select>
            </div>
          )}
        </div>
      ) : (
        <div className="relative mx-auto mb-3 flex h-9 w-9 items-center justify-center">
          <div className="absolute inset-0 rounded-full bg-gradient-to-br from-[#f54927] to-[#6366f1] opacity-60 blur-sm" />
          <div className="relative flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-[#f54927] to-[#ff6b4a] text-[12px] font-bold text-white">
            {user?.name?.charAt(0).toUpperCase() ?? '?'}
          </div>
        </div>
      )}

      {/* Logout */}
      <div className="relative mx-3 mb-3">
        <button
          type="button"
          onClick={handleLogout}
          title={collapsed && !isMobile ? 'Keluar' : undefined}
          className={`group flex cursor-pointer items-center gap-3 rounded-xl border border-white/8 bg-white/3 transition-all duration-200 hover:border-[#dc2626]/30 hover:bg-[#dc2626]/10 ${
            collapsed && !isMobile ? 'mx-auto justify-center p-2.5' : 'w-full px-3 py-2.5'
          }`}
        >
          <span className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-white/5 text-white/40 transition-colors group-hover:bg-[#dc2626]/20 group-hover:text-[#f87171]">
            {IC.logout}
          </span>
          {(!collapsed || isMobile) && (
            <span className="flex-1 text-left text-[13px] font-medium text-white/50 transition-colors group-hover:text-[#f87171]">
              Keluar
            </span>
          )}
        </button>
      </div>
    </aside>
  )

  /* ===== Render ===== */

  return (
    <div className="flex min-h-screen bg-[#fafaf8]">
      {/* Desktop sidebar */}
      <div className="relative hidden lg:block">{sidebarContent()}</div>

      {/* Mobile sidebar overlay */}
      {sidebarOpen && (
        <div className="fixed inset-0 z-50 lg:hidden">
          <div
            className="absolute inset-0 animate-fade-up bg-black/50 backdrop-blur-md"
            onClick={() => setSidebarOpen(false)}
            style={{ animationDuration: '0.2s' }}
          />
          <div className="absolute top-0 left-0 h-full animate-fade-up shadow-2xl" style={{ animationDuration: '0.25s' }}>
            <div className="relative h-full">
              {sidebarContent(true)}
              <button
                type="button"
                onClick={() => setSidebarOpen(false)}
                className="absolute top-5 right-4 cursor-pointer rounded-lg p-1.5 text-white/40 transition-colors hover:bg-white/10 hover:text-white"
                aria-label="Close"
              >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Main */}
      <div className="flex min-w-0 flex-1 flex-col">
        {/* Top header */}
        <header className="sticky top-0 z-30 flex h-14 items-center gap-4 border-b border-[#e7e5e4]/60 bg-white/80 px-4 backdrop-blur-xl sm:px-6">
          <button
            type="button"
            onClick={() => setSidebarOpen(true)}
            className="cursor-pointer rounded-lg p-2 text-[#78716c] transition-colors hover:bg-[#f5f5f4] lg:hidden"
            aria-label="Toggle sidebar"
          >
            {IC.menu}
          </button>

          <h1 className="text-[15px] font-semibold text-[#1c1917]">{currentPage}</h1>

          <div className="flex-1" />

          {/* Search */}
          <div className="relative hidden sm:block">
            <div className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-[#a8a29e]">
              {IC.search}
            </div>
            <input
              type="text"
              placeholder="Cari..."
              className="h-9 w-48 rounded-lg border border-[#e7e5e4] bg-[#fafaf9] pr-3 pl-9 text-[13px] text-[#1c1917] transition-all duration-200 outline-none placeholder:text-[#c2bdb8] focus:border-[#f54927] focus:ring-2 focus:ring-[#f54927]/10 focus:w-56"
            />
          </div>

          {/* Notification */}
          <button
            type="button"
            className="relative cursor-pointer rounded-lg p-2 text-[#78716c] transition-colors hover:bg-[#f5f5f4]"
            aria-label="Notifications"
          >
            {IC.bell}
            <span className="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-[#f54927] ring-2 ring-white" />
          </button>

          {/* Profile */}
          <div className="flex items-center gap-2.5">
            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-[#f54927] to-[#ff6b4a] text-[11px] font-bold text-white">
              {user?.name?.charAt(0).toUpperCase() ?? '?'}
            </div>
            <div className="hidden md:block">
              <p className="text-[12px] font-semibold text-[#1c1917]">{user?.name}</p>
              <p className="text-[10px] text-[#a8a29e]">
                {moduleConfig.businessProfile?.business_name ?? ''}
              </p>
            </div>
          </div>
        </header>

        {/* Content */}
        <main className="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">{children}</main>
      </div>
    </div>
  )
}
