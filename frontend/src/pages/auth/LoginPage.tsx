import { useState, useEffect, type FormEvent } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { useAuthStore } from '@/stores/auth'
import { useModuleConfigStore } from '@/stores/module-config'
import { authService } from '@/services/auth'
import { securityService } from '@/services/security'
import { BrandingPanel } from './components/BrandingPanel'
import { PinLogin } from './components/PinLogin'
import { QrLogin } from './components/QrLogin'

type LoginTab = 'email' | 'pin' | 'qr'

const MAX_ATTEMPTS = 5

function getGreeting(): string {
  const h = new Date().getHours()
  if (h >= 4 && h < 11) return 'Selamat pagi'
  if (h >= 11 && h < 15) return 'Selamat siang'
  if (h >= 15 && h < 18) return 'Selamat sore'
  return 'Selamat malam'
}

function Clock() {
  const [now, setNow] = useState(() => new Date())
  useEffect(() => {
    const t = setInterval(() => setNow(new Date()), 30000)
    return () => clearInterval(t)
  }, [])
  const time = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
  const date = now.toLocaleDateString('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
  return (
    <span className="text-[12px] text-[#a8a29e] tabular-nums">
      {getGreeting()} · {time} WIB · {date}
    </span>
  )
}

export function LoginPage() {
  const navigate = useNavigate()
  const { setAuth } = useAuthStore()
  const moduleConfig = useModuleConfigStore()

  const [tab, setTab] = useState<LoginTab>('email')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const [attempts, setAttempts] = useState(0)
  const [shake, setShake] = useState(0)
  const [magicSent, setMagicSent] = useState(false)
  const [twoFaToken, setTwoFaToken] = useState<string | null>(null)
  const [twoFaCode, setTwoFaCode] = useState('')
  const [isBackupCode, setIsBackupCode] = useState(false)
  const [passkeySupported] = useState(
    () => typeof window !== 'undefined' && !!window.PublicKeyCredential,
  )
  const [lastLogin] = useState<string | null>(() =>
    localStorage.getItem('kasirpos_last_login'),
  )

  const DEMO_EMAIL = 'admin@kasirpos.id'
  const DEMO_PASSWORD = 'KasirPOS2026!'

  const fillDemo = () => {
    setEmail(DEMO_EMAIL)
    setPassword(DEMO_PASSWORD)
    setError('')
    setAttempts(0)
  }

  const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
  const avatarLetter = emailValid ? email[0].toUpperCase() : ''
  const lockedOut = attempts >= MAX_ATTEMPTS

  const completeLogin = (res: Awaited<ReturnType<typeof authService.login>>) => {
    setAuth(res.user, res.token)
    moduleConfig.setConfig({
      modules: res.modules,
      features: res.features,
      permissions: res.permissions,
      stores: res.stores,
      business_profile: res.business_profile,
    })
    localStorage.setItem(
      'kasirpos_last_login',
      new Date().toLocaleString('id-ID', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
      }),
    )
    navigate('/dashboard')
  }

  const failLogin = (message: string) => {
    setError(message)
    setAttempts((a) => a + 1)
    setShake((s) => s + 1)
  }

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault()
    if (lockedOut) return
    setError('')
    setLoading(true)
    try {
      const res = await authService.login({ email, password })
      if (res['2fa_required']) {
        setTwoFaToken(res['2fa_token'])
        setLoading(false)
        return
      }
      completeLogin(res)
    } catch (err: unknown) {
      const err_ = err as { response?: { data?: { message?: string } } }
      failLogin(
        err_.response?.data?.message ??
          'Gagal masuk. Periksa kembali email dan kata sandi Anda.',
      )
    } finally {
      setLoading(false)
    }
  }

  const handlePin = async (pin: string) => {
    if (lockedOut) return
    setError('')
    setLoading(true)
    try {
      const res = await authService.login({ email: `pin:${pin}`, password: pin })
      completeLogin(res)
    } catch {
      failLogin('PIN salah atau tidak terdaftar. Coba lagi.')
    } finally {
      setLoading(false)
    }
  }

  const handleMagicLink = async () => {
    if (!emailValid || magicSent) return
    setMagicSent(true)
    // Tampilkan konfirmasi; endpoint backend akan dikembangkan
    setTimeout(() => setMagicSent(false), 8000)
  }

  const handlePasskey = () => {
    setError('')
    failLogin(
      'Passkey belum dikonfigurasi untuk akun ini. Silakan masuk dengan email terlebih dahulu.',
    )
    setAttempts(0)
    setError('')
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

        <div key={shake} className={`w-full max-w-[400px] ${shake ? 'animate-shake' : ''}`}>
          {/* Mobile logo */}
          <div className="animate-fade-up mb-8 flex items-center gap-3 lg:hidden">
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

          {/* Clock + heading */}
          <div className="animate-fade-up mb-7" style={{ animationDelay: '0.05s' }}>
            <Clock />
            <div className="mt-2 flex items-center gap-3">
              <h2 className="text-[28px] font-bold tracking-[-0.02em] text-[#1c1917]">
                Selamat datang kembali
              </h2>
              {/* Avatar preview */}
              {avatarLetter && tab === 'email' && (
                <div className="animate-fade-up flex h-10 w-10 items-center justify-center rounded-full bg-[#f54927] text-[15px] font-bold text-white shadow-[0_4px_14px_rgba(245,73,39,0.3)]">
                  {avatarLetter}
                </div>
              )}
            </div>
            <p className="mt-1.5 text-[14px] text-[#78716c]">
              Masuk untuk melanjutkan ke dasbor Anda
            </p>
            {lastLogin && (
              <p className="mt-1.5 flex items-center gap-1.5 text-[12px] text-[#a8a29e]">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="1.8"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  className="h-3.5 w-3.5"
                  aria-hidden="true"
                >
                  <circle cx="12" cy="12" r="10" />
                  <polyline points="12 6 12 12 16 14" />
                </svg>
                Login terakhir: {lastLogin}
              </p>
            )}
          </div>

          {/* Tabs */}
          <div
            className="animate-fade-up mb-6 flex gap-1 rounded-xl bg-[#f0efec] p-1"
            style={{ animationDelay: '0.1s' }}
            role="tablist"
            aria-label="Metode masuk"
          >
            {(
              [
                { id: 'email', label: 'Email' },
                { id: 'pin', label: 'PIN' },
                { id: 'qr', label: 'QR Code' },
              ] as const
            ).map((t) => (
              <button
                key={t.id}
                type="button"
                role="tab"
                aria-selected={tab === t.id}
                onClick={() => {
                  setTab(t.id)
                  setError('')
                }}
                className={`flex-1 cursor-pointer rounded-lg py-2 text-[13px] font-semibold transition-all duration-200 ${
                  tab === t.id
                    ? 'bg-white text-[#1c1917] shadow-[0_1px_4px_rgba(0,0,0,0.08)]'
                    : 'text-[#78716c] hover:text-[#44403c]'
                }`}
              >
                {t.label}
              </button>
            ))}
          </div>

          {/* Error banner */}
          {error && (
            <div
              role="alert"
              className="mb-5 flex items-start gap-3 rounded-xl border border-[#f54927]/20 bg-[#fef3ef] px-4 py-3 text-[13px] leading-relaxed text-[#d63a1b]"
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
              <div>
                <span>{error}</span>
                {attempts > 1 && !lockedOut && (
                  <p className="mt-1 text-[11px] text-[#d63a1b]/70">
                    Sisa percobaan: {MAX_ATTEMPTS - attempts}
                  </p>
                )}
                {lockedOut && (
                  <p className="mt-1 text-[11px] font-semibold">
                    Terlalu banyak percobaan. Muat ulang halaman untuk mencoba lagi.
                  </p>
                )}
              </div>
            </div>
          )}

          {/* ===== 2FA VERIFICATION FORM ===== */}
          {twoFaToken && tab === 'email' && (
            <div className="animate-fade-up space-y-5" style={{ animationDelay: '0.15s' }}>
              <div className="rounded-xl border border-[#f54927]/20 bg-[#fef3ef] px-4 py-3 text-[13px] text-[#d63a1b]">
                Two-factor authentication required. Enter the 6-digit code from your authenticator app.
              </div>

              <div className="space-y-1.5">
                <label className="block text-[13px] font-semibold text-[#44403c]">
                  {isBackupCode ? 'Backup Code' : '2FA Code'}
                </label>
                <input
                  type="text"
                  value={twoFaCode}
                  onChange={(e) => setTwoFaCode(e.target.value)}
                  maxLength={isBackupCode ? 8 : 6}
                  placeholder={isBackupCode ? 'XXXXXXXX' : '000000'}
                  className="h-[50px] w-full rounded-xl border border-[#e7e5e4] bg-white px-4 text-center font-mono text-lg tracking-widest text-[#1c1917] outline-none focus:border-[#f54927] focus:ring-4 focus:ring-[#f54927]/10"
                />
              </div>

              <label className="flex cursor-pointer items-center gap-2.5 select-none">
                <input
                  type="checkbox"
                  checked={isBackupCode}
                  onChange={(e) => setIsBackupCode(e.target.checked)}
                  className="h-4 w-4 cursor-pointer rounded border-[#d6d3d1] accent-[#f54927]"
                />
                <span className="text-[13px] text-[#78716c]">Use a backup code instead</span>
              </label>

              <button
                type="button"
                onClick={async () => {
                  setLoading(true)
                  setError('')
                  try {
                    const res = await securityService.loginWith2fa(twoFaToken, twoFaCode, isBackupCode)
                    setAuth(res.user, res.token)
                    moduleConfig.setConfig({
                      modules: res.modules,
                      features: res.features,
                      permissions: res.permissions,
                      stores: res.stores,
                      business_profile: res.business_profile,
                    })
                    navigate('/dashboard')
                  } catch {
                    setError('Invalid 2FA code. Please try again.')
                  } finally {
                    setLoading(false)
                  }
                }}
                disabled={loading || twoFaCode.length < (isBackupCode ? 8 : 6)}
                className="group relative flex h-[52px] w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-[#f54927] text-[15px] font-semibold text-white shadow-[0_4px_14px_rgba(245,73,39,0.28)] transition-all duration-200 hover:bg-[#e13e1c] disabled:pointer-events-none disabled:opacity-60"
              >
                {loading ? 'Memproses...' : 'Verify & Login'}
              </button>

              <button
                type="button"
                onClick={() => {
                  setTwoFaToken(null)
                  setTwoFaCode('')
                  setIsBackupCode(false)
                }}
                className="w-full cursor-pointer text-center text-[13px] font-semibold text-[#78716c] transition-colors hover:text-[#f54927]"
              >
                Back to login
              </button>
            </div>
          )}

          {/* ===== TAB: EMAIL ===== */}
          {tab === 'email' && !twoFaToken && (
            <form
              onSubmit={handleSubmit}
              className="animate-fade-up space-y-5"
              style={{ animationDelay: '0.15s' }}
            >
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
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    required
                    disabled={lockedOut}
                    placeholder="nama@restoran.id"
                    autoComplete="email"
                    className="h-[50px] w-full rounded-xl border border-[#e7e5e4] bg-white pr-4 pl-11 text-[15px] text-[#1c1917] transition-all duration-200 outline-none placeholder:text-[#c2bdb8] hover:border-[#d6d3d1] focus:border-[#f54927] focus:ring-4 focus:ring-[#f54927]/10 disabled:opacity-50"
                  />
                </div>
              </div>

              <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                  <label
                    htmlFor="password"
                    className="block text-[13px] font-semibold text-[#44403c]"
                  >
                    Kata Sandi
                  </label>
                  <button
                    type="button"
                    className="cursor-pointer text-[13px] font-semibold text-[#f54927] transition-colors hover:text-[#d63a1b]"
                  >
                    Lupa kata sandi?
                  </button>
                </div>
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
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    required
                    disabled={lockedOut}
                    placeholder="Masukkan kata sandi"
                    autoComplete="current-password"
                    className="h-[50px] w-full rounded-xl border border-[#e7e5e4] bg-white pr-12 pl-11 text-[15px] text-[#1c1917] transition-all duration-200 outline-none placeholder:text-[#c2bdb8] hover:border-[#d6d3d1] focus:border-[#f54927] focus:ring-4 focus:ring-[#f54927]/10 disabled:opacity-50"
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
              </div>

              <div className="flex items-center justify-between pt-1">
                <label className="flex cursor-pointer items-center gap-2.5 select-none">
                  <input
                    type="checkbox"
                    defaultChecked
                    className="h-4 w-4 cursor-pointer rounded border-[#d6d3d1] accent-[#f54927]"
                  />
                  <span className="text-[13px] text-[#78716c]">Ingat saya</span>
                </label>
                <button
                  type="button"
                  onClick={handleMagicLink}
                  disabled={!emailValid || magicSent}
                  className="cursor-pointer text-[13px] font-semibold text-[#78716c] transition-colors hover:text-[#f54927] disabled:cursor-not-allowed disabled:opacity-50"
                >
                  {magicSent ? 'Tautan terkirim ✓' : 'Masuk tanpa kata sandi'}
                </button>
              </div>

              <button
                type="submit"
                disabled={loading || lockedOut}
                className="group relative flex h-[52px] w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-[#f54927] text-[15px] font-semibold text-white shadow-[0_4px_14px_rgba(245,73,39,0.28)] transition-all duration-200 hover:bg-[#e13e1c] hover:shadow-[0_6px_20px_rgba(245,73,39,0.38)] active:scale-[0.985] disabled:pointer-events-none disabled:opacity-60"
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
                    Memproses...
                  </>
                ) : (
                  <>
                    Masuk
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
          )}

          {/* ===== TAB: PIN ===== */}
          {tab === 'pin' && (
            <div className="animate-fade-up" style={{ animationDelay: '0.15s' }}>
              <PinLogin onSubmit={handlePin} loading={loading} disabled={lockedOut} />
            </div>
          )}

          {/* ===== TAB: QR ===== */}
          {tab === 'qr' && (
            <div className="animate-fade-up" style={{ animationDelay: '0.15s' }}>
              <QrLogin />
            </div>
          )}

          {/* Divider */}
          <div className="animate-fade-up my-7 flex items-center gap-4" style={{ animationDelay: '0.2s' }}>
            <div className="h-px flex-1 bg-[#e7e5e4]" />
            <span className="text-[11px] font-semibold tracking-[0.1em] text-[#a8a29e] uppercase">
              Atau masuk dengan
            </span>
            <div className="h-px flex-1 bg-[#e7e5e4]" />
          </div>

          {/* SSO + Passkey */}
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

          {/* Passkey */}
          {passkeySupported && (
            <button
              type="button"
              onClick={handlePasskey}
              className="animate-fade-up mt-3 flex h-[46px] w-full cursor-pointer items-center justify-center gap-2.5 rounded-xl border border-dashed border-[#e7e5e4] bg-transparent text-[14px] font-medium text-[#78716c] transition-all duration-200 hover:border-[#f54927]/40 hover:text-[#f54927]"
              style={{ animationDelay: '0.3s' }}
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinecap="round"
                strokeLinejoin="round"
                className="h-[18px] w-[18px]"
                aria-hidden="true"
              >
                <path d="M12 11c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3z" />
                <path d="M12 11v10" />
                <path d="M15 15l-3-1.5" />
                <path d="M16 19l-4-2" />
                <circle cx="12" cy="12" r="10" />
              </svg>
              Masuk dengan Passkey
            </button>
          )}

          {/* Demo account quick-fill */}
          <div
            className="animate-fade-up mt-5 rounded-xl border border-[#f54927]/20 bg-[#fef3ef] p-4"
            style={{ animationDelay: '0.35s' }}
          >
            <div className="flex items-center gap-2.5">
              <div className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-[#f54927]/10">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="1.6"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  className="h-4 w-4 text-[#f54927]"
                  aria-hidden="true"
                >
                  <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                  <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                  <line x1="12" y1="22.08" x2="12" y2="12" />
                </svg>
              </div>
              <div className="min-w-0 flex-1">
                <p className="text-[12px] font-semibold text-[#1c1917]">
                  Akun Demo Super Admin
                </p>
                <p className="truncate text-[11px] text-[#78716c] tabular-nums">
                  {DEMO_EMAIL} · {DEMO_PASSWORD}
                </p>
              </div>
              <button
                type="button"
                onClick={fillDemo}
                className="flex-shrink-0 cursor-pointer rounded-lg bg-[#f54927] px-3.5 py-2 text-[12px] font-semibold text-white transition-all duration-200 hover:bg-[#e13e1c] active:scale-95"
              >
                Isi otomatis
              </button>
            </div>
          </div>

          {/* Footer */}
          <p
            className="animate-fade-up mt-8 text-center text-[13px] text-[#78716c]"
            style={{ animationDelay: '0.35s' }}
          >
            Belum punya akun?{' '}
            <Link
              to="/register"
              className="cursor-pointer font-semibold text-[#f54927] transition-colors hover:text-[#d63a1b]"
            >
              Daftar sekarang
            </Link>
          </p>

          <p
            className="animate-fade-up mt-5 flex items-center justify-center gap-1.5 text-center text-[11px] leading-relaxed text-[#a8a29e]"
            style={{ animationDelay: '0.4s' }}
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.8"
              strokeLinecap="round"
              strokeLinejoin="round"
              className="h-3.5 w-3.5"
              aria-hidden="true"
            >
              <rect x="3" y="11" width="18" height="11" rx="2" />
              <path d="M7 11V7a5 5 0 0 1 10 0v4" />
            </svg>
            Dilindungi enkripsi end-to-end ·{' '}
            <span className="cursor-pointer underline underline-offset-2 transition-colors hover:text-[#44403c]">
              Syarat &amp; Ketentuan
            </span>{' '}
            ·{' '}
            <span className="cursor-pointer underline underline-offset-2 transition-colors hover:text-[#44403c]">
              Kebijakan Privasi
            </span>
          </p>
        </div>
      </div>
    </div>
  )
}
