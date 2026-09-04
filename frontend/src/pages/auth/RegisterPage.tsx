import { useState, useEffect, useMemo, type FormEvent } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { useAuthStore } from '@/stores/auth'
import { useModuleConfigStore } from '@/stores/module-config'
import { authService } from '@/services/auth'
import { BrandingPanel } from './components/BrandingPanel'
import type { BusinessType } from '@/types'

interface FormState {
  name: string
  email: string
  password: string
  password_confirmation: string
  store_name: string
  business_type_id: string
}

const INITIAL_FORM: FormState = {
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  store_name: '',
  business_type_id: '',
}

/* Password strength meter */
function scorePassword(pw: string): { score: number; label: string; color: string } {
  if (!pw) return { score: 0, label: '', color: '#e7e5e4' }
  let s = 0
  if (pw.length >= 8) s++
  if (pw.length >= 12) s++
  if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) s++
  if (/\d/.test(pw)) s++
  if (/[^A-Za-z0-9]/.test(pw)) s++
  const levels = [
    { label: 'Terlalu lemah', color: '#dc2626' },
    { label: 'Lemah', color: '#ea580c' },
    { label: 'Cukup', color: '#ca8a04' },
    { label: 'Baik', color: '#16a34a' },
    { label: 'Kuat', color: '#16a34a' },
    { label: 'Sangat kuat', color: '#16a34a' },
  ]
  return { score: s, ...levels[s] }
}

export function RegisterPage() {
  const navigate = useNavigate()
  const { setAuth } = useAuthStore()
  const moduleConfig = useModuleConfigStore()
  const [businessTypes, setBusinessTypes] = useState<BusinessType[]>([])
  const [form, setForm] = useState<FormState>(INITIAL_FORM)
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [loading, setLoading] = useState(false)
  const [showPassword, setShowPassword] = useState(false)
  const [agreed, setAgreed] = useState(false)

  useEffect(() => {
    authService.getBusinessTypes().then(setBusinessTypes).catch(() => {})
  }, [])

  const pwCheck = useMemo(() => scorePassword(form.password), [form.password])
  const passwordsMatch =
    form.password_confirmation.length > 0 &&
    form.password === form.password_confirmation
  const passwordMismatch =
    form.password_confirmation.length > 0 &&
    form.password !== form.password_confirmation

  const handleChange = (field: keyof FormState, value: string) => {
    setForm((prev) => ({ ...prev, [field]: value }))
    setFieldErrors((prev) => ({ ...prev, [field]: '' }))
  }

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault()
    setError('')
    setFieldErrors({})
    if (!agreed) {
      setError('Anda harus menyetujui Syarat & Ketentuan untuk mendaftar.')
      return
    }
    setLoading(true)
    try {
      const res = await authService.register({
        ...form,
        business_type_id: form.business_type_id ? Number(form.business_type_id) : undefined,
      })
      setAuth(res.user, res.token)
      moduleConfig.setConfig({
        modules: res.modules,
        features: res.features,
        permissions: res.permissions,
        stores: res.stores,
        business_profile: res.business_profile,
      })
      navigate('/dashboard')
    } catch (err: unknown) {
      const e = err as {
        response?: {
          data?: {
            message?: string
            errors?: Record<string, string[]>
          }
        }
      }
      setError(e.response?.data?.message ?? 'Pendaftaran gagal. Silakan coba lagi.')
      if (e.response?.data?.errors) {
        const mapped: Record<string, string> = {}
        for (const [k, v] of Object.entries(e.response.data.errors)) {
          mapped[k] = v[0]
        }
        setFieldErrors(mapped)
      }
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="relative flex min-h-screen bg-[#fafaf8]">
      {/* Top progress bar */}
      {loading && (
        <div className="fixed top-0 right-0 left-0 z-50 h-[3px] overflow-hidden bg-[#f54927]/10">
          <div className="progress-indeterminate bg-[#f54927]" />
        </div>
      )}

      <BrandingPanel />

      {/* ===== PANEL KANAN ===== */}
      <div className="relative flex flex-1 items-center justify-center px-6 py-10 sm:px-10">
        <div
          aria-hidden="true"
          className="pointer-events-none absolute -top-24 -right-24 h-80 w-80 rounded-full bg-[#f54927]/8 blur-[90px] lg:hidden"
        />

        <div className="w-full max-w-[440px]">
          {/* Mobile logo */}
          <div className="animate-fade-up mb-7 flex items-center gap-3 lg:hidden">
            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-[#f54927] shadow-[0_4px_16px_rgba(245,73,39,0.3)]">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.8"
                strokeLinecap="round"
                strokeLinejoin="round"
                className="h-5.5 w-5.5 text-white"
                aria-hidden="true"
              >
                <rect x="3" y="3" width="18" height="18" rx="4" />
                <path d="M3 9h18" />
                <path d="M9 9v12" />
              </svg>
            </div>
            <div>
              <p className="text-[16px] font-bold tracking-tight text-[#1c1917]">KasirPOS</p>
              <p className="text-[10px] font-medium tracking-[0.14em] text-[#a8a29e] uppercase">
                Sistem Kasir Restoran
              </p>
            </div>
          </div>

          {/* Heading */}
          <div className="animate-fade-up mb-6" style={{ animationDelay: '0.05s' }}>
            <h2 className="text-[28px] font-bold tracking-[-0.02em] text-[#1c1917]">
              Buat akun baru
            </h2>
            <p className="mt-1.5 text-[14px] text-[#78716c]">
              Gratis 14 hari. Tanpa kartu kredit.
            </p>
          </div>

          {/* Error banner */}
          {error && (
            <div
              role="alert"
              className="animate-fade-up mb-5 flex items-start gap-3 rounded-xl border border-[#f54927]/20 bg-[#fef3ef] px-4 py-3 text-[13px] leading-relaxed text-[#d63a1b]"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                className="mt-0.5 h-4 w-4 flex-shrink-0"
                aria-hidden="true"
              >
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
              </svg>
              <span>{error}</span>
            </div>
          )}

          {/* Form */}
          <form
            onSubmit={handleSubmit}
            className="animate-fade-up space-y-4"
            style={{ animationDelay: '0.1s' }}
          >
            {/* Nama lengkap */}
            <div className="space-y-1.5">
              <label
                htmlFor="name"
                className="block text-[13px] font-semibold text-[#44403c]"
              >
                Nama Lengkap
              </label>
              <div className="group relative">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="1.6"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  className="pointer-events-none absolute top-1/2 left-4 h-[17px] w-[17px] -translate-y-1/2 text-[#a8a29e] transition-colors group-focus-within:text-[#f54927]"
                  aria-hidden="true"
                >
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                  <circle cx="12" cy="7" r="4" />
                </svg>
                <input
                  id="name"
                  type="text"
                  value={form.name}
                  onChange={(e) => handleChange('name', e.target.value)}
                  required
                  placeholder="Raka Aditya"
                  autoComplete="name"
                  className={`h-[50px] w-full rounded-xl border bg-white pr-4 pl-11 text-[15px] text-[#1c1917] transition-all duration-200 outline-none placeholder:text-[#c2bdb8] hover:border-[#d6d3d1] focus:border-[#f54927] focus:ring-4 focus:ring-[#f54927]/10 ${
                    fieldErrors.name ? 'border-[#dc2626]' : 'border-[#e7e5e4]'
                  }`}
                />
              </div>
              {fieldErrors.name && (
                <p className="text-[12px] text-[#dc2626]">{fieldErrors.name}</p>
              )}
            </div>

            {/* Email */}
            <div className="space-y-1.5">
              <label
                htmlFor="email"
                className="block text-[13px] font-semibold text-[#44403c]"
              >
                Email
              </label>
              <div className="group relative">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="1.6"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  className="pointer-events-none absolute top-1/2 left-4 h-[17px] w-[17px] -translate-y-1/2 text-[#a8a29e] transition-colors group-focus-within:text-[#f54927]"
                  aria-hidden="true"
                >
                  <rect x="2" y="4" width="20" height="16" rx="3" />
                  <path d="M22 7l-10 6L2 7" />
                </svg>
                <input
                  id="email"
                  type="email"
                  value={form.email}
                  onChange={(e) => handleChange('email', e.target.value)}
                  required
                  placeholder="nama@restoran.id"
                  autoComplete="email"
                  className={`h-[50px] w-full rounded-xl border bg-white pr-4 pl-11 text-[15px] text-[#1c1917] transition-all duration-200 outline-none placeholder:text-[#c2bdb8] hover:border-[#d6d3d1] focus:border-[#f54927] focus:ring-4 focus:ring-[#f54927]/10 ${
                    fieldErrors.email ? 'border-[#dc2626]' : 'border-[#e7e5e4]'
                  }`}
                />
              </div>
              {fieldErrors.email && (
                <p className="text-[12px] text-[#dc2626]">{fieldErrors.email}</p>
              )}
            </div>

            {/* Nama restoran + Jenis bisnis (2 col) */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div className="space-y-1.5">
                <label
                  htmlFor="store_name"
                  className="block text-[13px] font-semibold text-[#44403c]"
                >
                  Nama Restoran
                </label>
                <div className="group relative">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="1.6"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    className="pointer-events-none absolute top-1/2 left-4 h-[17px] w-[17px] -translate-y-1/2 text-[#a8a29e] transition-colors group-focus-within:text-[#f54927]"
                    aria-hidden="true"
                  >
                    <path d="M3 9l3-3h12l3 3" />
                    <path d="M4 9v11a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V9" />
                    <path d="M9 21V12h6v9" />
                  </svg>
                  <input
                    id="store_name"
                    type="text"
                    value={form.store_name}
                    onChange={(e) => handleChange('store_name', e.target.value)}
                    required
                    placeholder="Warung Sederhana"
                    autoComplete="organization"
                    className={`h-[50px] w-full rounded-xl border bg-white pr-4 pl-11 text-[15px] text-[#1c1917] transition-all duration-200 outline-none placeholder:text-[#c2bdb8] hover:border-[#d6d3d1] focus:border-[#f54927] focus:ring-4 focus:ring-[#f54927]/10 ${
                      fieldErrors.store_name ? 'border-[#dc2626]' : 'border-[#e7e5e4]'
                    }`}
                  />
                </div>
                {fieldErrors.store_name && (
                  <p className="text-[12px] text-[#dc2626]">{fieldErrors.store_name}</p>
                )}
              </div>

              <div className="space-y-1.5">
                <label
                  htmlFor="business_type_id"
                  className="block text-[13px] font-semibold text-[#44403c]"
                >
                  Jenis Bisnis
                </label>
                <div className="group relative">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="1.6"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    className="pointer-events-none absolute top-1/2 left-4 h-[17px] w-[17px] -translate-y-1/2 text-[#a8a29e] transition-colors group-focus-within:text-[#f54927]"
                    aria-hidden="true"
                  >
                    <rect x="2" y="7" width="20" height="14" rx="2" />
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
                  </svg>
                  <select
                    id="business_type_id"
                    value={form.business_type_id}
                    onChange={(e) => handleChange('business_type_id', e.target.value)}
                    className="h-[50px] w-full cursor-pointer rounded-xl border border-[#e7e5e4] bg-white pr-4 pl-11 text-[15px] text-[#1c1917] transition-all duration-200 outline-none hover:border-[#d6d3d1] focus:border-[#f54927] focus:ring-4 focus:ring-[#f54927]/10"
                  >
                    <option value="">Pilih jenis...</option>
                    {businessTypes.map((bt) => (
                      <option key={bt.id} value={bt.id}>
                        {bt.name}
                      </option>
                    ))}
                  </select>
                </div>
              </div>
            </div>

            {/* Password */}
            <div className="space-y-1.5">
              <label
                htmlFor="password"
                className="block text-[13px] font-semibold text-[#44403c]"
              >
                Kata Sandi
              </label>
              <div className="group relative">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="1.6"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  className="pointer-events-none absolute top-1/2 left-4 h-[17px] w-[17px] -translate-y-1/2 text-[#a8a29e] transition-colors group-focus-within:text-[#f54927]"
                  aria-hidden="true"
                >
                  <rect x="3" y="11" width="18" height="11" rx="3" />
                  <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
                <input
                  id="password"
                  type={showPassword ? 'text' : 'password'}
                  value={form.password}
                  onChange={(e) => handleChange('password', e.target.value)}
                  required
                  placeholder="Min. 8 karakter"
                  autoComplete="new-password"
                  className={`h-[50px] w-full rounded-xl border bg-white pr-12 pl-11 text-[15px] text-[#1c1917] transition-all duration-200 outline-none placeholder:text-[#c2bdb8] hover:border-[#d6d3d1] focus:border-[#f54927] focus:ring-4 focus:ring-[#f54927]/10 ${
                    fieldErrors.password ? 'border-[#dc2626]' : 'border-[#e7e5e4]'
                  }`}
                />
                <button
                  type="button"
                  onClick={() => setShowPassword((s) => !s)}
                  className="absolute top-1/2 right-3 -translate-y-1/2 cursor-pointer rounded-lg p-1.5 text-[#a8a29e] transition-colors hover:bg-[#f5f5f4] hover:text-[#44403c]"
                  aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                >
                  {showPassword ? (
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="1.6"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      className="h-5 w-5"
                      aria-hidden="true"
                    >
                      <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
                      <line x1="1" y1="1" x2="23" y2="23" />
                    </svg>
                  ) : (
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="1.6"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      className="h-5 w-5"
                      aria-hidden="true"
                    >
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                      <circle cx="12" cy="12" r="3" />
                    </svg>
                  )}
                </button>
              </div>
              {/* Strength meter */}
              {form.password && (
                <div className="flex items-center gap-2 pt-0.5">
                  <div className="flex h-1.5 flex-1 gap-1 overflow-hidden rounded-full bg-[#f0efec]">
                    {[...Array(5)].map((_, i) => (
                      <div
                        key={i}
                        className="flex-1 rounded-full transition-all duration-300"
                        style={{
                          backgroundColor: i < pwCheck.score ? pwCheck.color : 'transparent',
                        }}
                      />
                    ))}
                  </div>
                  <span
                    className="text-[11px] font-medium tabular-nums"
                    style={{ color: pwCheck.color }}
                  >
                    {pwCheck.label}
                  </span>
                </div>
              )}
              {fieldErrors.password && (
                <p className="text-[12px] text-[#dc2626]">{fieldErrors.password}</p>
              )}
            </div>

            {/* Confirm password */}
            <div className="space-y-1.5">
              <label
                htmlFor="password_confirmation"
                className="block text-[13px] font-semibold text-[#44403c]"
              >
                Konfirmasi Kata Sandi
              </label>
              <div className="group relative">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="1.6"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  className="pointer-events-none absolute top-1/2 left-4 h-[17px] w-[17px] -translate-y-1/2 text-[#a8a29e] transition-colors group-focus-within:text-[#f54927]"
                  aria-hidden="true"
                >
                  <path d="M9 12l2 2 4-4" />
                  <rect x="3" y="11" width="18" height="11" rx="3" />
                  <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
                <input
                  id="password_confirmation"
                  type={showPassword ? 'text' : 'password'}
                  value={form.password_confirmation}
                  onChange={(e) => handleChange('password_confirmation', e.target.value)}
                  required
                  placeholder="Ulangi kata sandi"
                  autoComplete="new-password"
                  className={`h-[50px] w-full rounded-xl border bg-white pr-12 pl-11 text-[15px] text-[#1c1917] transition-all duration-200 outline-none placeholder:text-[#c2bdb8] hover:border-[#d6d3d1] focus:border-[#f54927] focus:ring-4 focus:ring-[#f54927]/10 ${
                    passwordMismatch
                      ? 'border-[#dc2626]'
                      : passwordsMatch
                        ? 'border-[#16a34a]'
                        : 'border-[#e7e5e4]'
                  }`}
                />
                {passwordsMatch && (
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    className="absolute top-1/2 right-4 h-5 w-5 -translate-y-1/2 text-[#16a34a]"
                    aria-hidden="true"
                  >
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                    <polyline points="22 4 12 14.01 9 11.01" />
                  </svg>
                )}
              </div>
              {passwordMismatch && (
                <p className="text-[12px] text-[#dc2626]">Kata sandi tidak cocok.</p>
              )}
            </div>

            {/* Terms */}
            <label className="flex cursor-pointer items-start gap-2.5 pt-1 select-none">
              <input
                type="checkbox"
                checked={agreed}
                onChange={(e) => setAgreed(e.target.checked)}
                className="mt-0.5 h-4 w-4 cursor-pointer rounded border-[#d6d3d1] accent-[#f54927]"
              />
              <span className="text-[13px] leading-relaxed text-[#78716c]">
                Saya menyetujui{' '}
                <span className="cursor-pointer font-semibold text-[#f54927] underline underline-offset-2">
                  Syarat &amp; Ketentuan
                </span>{' '}
                dan{' '}
                <span className="cursor-pointer font-semibold text-[#f54927] underline underline-offset-2">
                  Kebijakan Privasi
                </span>{' '}
                KasirPOS.
              </span>
            </label>

            {/* Submit */}
            <button
              type="submit"
              disabled={loading || !agreed}
              className="group relative flex h-[52px] w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-[#f54927] text-[15px] font-semibold text-white shadow-[0_4px_14px_rgba(245,73,39,0.28)] transition-all duration-200 hover:bg-[#e13e1c] hover:shadow-[0_6px_20px_rgba(245,73,39,0.38)] active:scale-[0.985] disabled:pointer-events-none disabled:opacity-50"
            >
              {loading ? (
                <>
                  <svg
                    className="h-[18px] w-[18px] animate-spin"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                  >
                    <circle
                      className="opacity-25"
                      cx="12"
                      cy="12"
                      r="10"
                      stroke="currentColor"
                      strokeWidth="4"
                    />
                    <path
                      className="opacity-75"
                      fill="currentColor"
                      d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    />
                  </svg>
                  Mendaftarkan...
                </>
              ) : (
                <>
                  Mulai gratis
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    className="h-[18px] w-[18px] transition-transform duration-200 group-hover:translate-x-0.5"
                    aria-hidden="true"
                  >
                    <line x1="5" y1="12" x2="19" y2="12" />
                    <polyline points="12 5 19 12 12 19" />
                  </svg>
                </>
              )}
            </button>
          </form>

          {/* Divider */}
          <div className="animate-fade-up my-6 flex items-center gap-4" style={{ animationDelay: '0.2s' }}>
            <div className="h-px flex-1 bg-[#e7e5e4]" />
            <span className="text-[11px] font-semibold tracking-[0.1em] text-[#a8a29e] uppercase">
              Atau
            </span>
            <div className="h-px flex-1 bg-[#e7e5e4]" />
          </div>

          {/* SSO */}
          <div className="animate-fade-up grid grid-cols-2 gap-3" style={{ animationDelay: '0.25s' }}>
            <button
              type="button"
              className="flex h-[46px] cursor-pointer items-center justify-center gap-2.5 rounded-xl border border-[#e7e5e4] bg-white text-[14px] font-medium text-[#44403c] transition-all duration-200 hover:border-[#d6d3d1] hover:bg-[#fafaf9] active:scale-[0.98]"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                className="h-[17px] w-[17px]"
                aria-hidden="true"
              >
                <path
                  fill="#4285F4"
                  d="M23.49 12.27c0-.79-.07-1.54-.19-2.27H12v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58v3h3.86c2.26-2.09 3.56-5.17 3.56-8.82z"
                />
                <path
                  fill="#34A853"
                  d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-3c-1.08.72-2.45 1.16-4.07 1.16-3.13 0-5.78-2.11-6.73-4.96H1.29v3.09C3.26 21.3 7.31 24 12 24z"
                />
                <path
                  fill="#FBBC05"
                  d="M5.27 14.29c-.25-.72-.38-1.49-.38-2.29s.14-1.57.38-2.29V6.62H1.29C.47 8.24 0 10.06 0 12s.47 3.76 1.29 5.38l3.98-3.09z"
                />
                <path
                  fill="#EA4335"
                  d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.7 1.29 6.62l3.98 3.09C6.22 6.86 8.87 4.75 12 4.75z"
                />
              </svg>
              Google
            </button>
            <button
              type="button"
              className="flex h-[46px] cursor-pointer items-center justify-center gap-2.5 rounded-xl border border-[#e7e5e4] bg-white text-[14px] font-medium text-[#44403c] transition-all duration-200 hover:border-[#d6d3d1] hover:bg-[#fafaf9] active:scale-[0.98]"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="currentColor"
                className="h-[17px] w-[17px] text-[#1c1917]"
                aria-hidden="true"
              >
                <path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.53 4.08zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z" />
              </svg>
              Apple
            </button>
          </div>

          {/* Footer */}
          <p
            className="animate-fade-up mt-7 text-center text-[13px] text-[#78716c]"
            style={{ animationDelay: '0.3s' }}
          >
            Sudah punya akun?{' '}
            <Link
              to="/login"
              className="cursor-pointer font-semibold text-[#f54927] transition-colors hover:text-[#d63a1b]"
            >
              Masuk di sini
            </Link>
          </p>
        </div>
      </div>
    </div>
  )
}
