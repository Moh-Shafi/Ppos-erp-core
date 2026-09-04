import { useEffect, useRef, useState } from 'react'

/* ---------- Data ---------- */

const TESTIMONIALS = [
  {
    quote:
      'Sejak pakai KasirPOS, proses kasir kami dua kali lebih cepat dan laporan harian langsung jadi tanpa hitung manual.',
    initials: 'RA',
    name: 'Raka Aditya',
    role: 'Pemilik Warung Sederhana, Jakarta',
  },
  {
    quote:
      'Mode offline-nya penyelamat. Internet sempat mati saat jam makan siang, tapi semua pesanan tetap tercatat sempurna.',
    initials: 'DS',
    name: 'Dewi Sartika',
    role: 'Manajer Kopi Nusantara, Bandung',
  },
  {
    quote:
      'Stok bahan baku selalu akurat. Saya tidak pernah lagi kehabisan bahan di tengah jam sibuk.',
    initials: 'BP',
    name: 'Budi Prasetyo',
    role: 'Kepala Dapur Bakmi Jaya, Surabaya',
  },
  {
    quote:
      'Laporan penjualan real-time membuat saya bisa pantau tiga cabang sekaligus dari ponsel.',
    initials: 'MW',
    name: 'Maya Wulandari',
    role: 'Owner SateKu Group, Yogyakarta',
  },
]

const FEED_ITEMS = [
  { table: 'Meja 5', amount: 'Rp 245.000', method: 'QRIS' },
  { table: 'Meja 12', amount: 'Rp 89.500', method: 'Tunai' },
  { table: 'GoFood #231', amount: 'Rp 156.000', method: 'Online' },
  { table: 'Meja 3', amount: 'Rp 412.000', method: 'Kartu Debit' },
  { table: 'GrabFood #87', amount: 'Rp 98.000', method: 'Online' },
  { table: 'Meja 8', amount: 'Rp 178.500', method: 'QRIS' },
]

const TRUST_LOGOS = ['Warung+', 'SateKu', 'Kopi Nusantara', 'Bakmi Jaya', 'Soto Mbak Sri']

/* ---------- Animated counter ---------- */

function AnimatedCounter({
  target,
  duration = 1600,
  suffix = '',
  decimals = 0,
}: {
  target: number
  duration?: number
  suffix?: string
  decimals?: number
}) {
  const [value, setValue] = useState(0)
  const ref = useRef<HTMLSpanElement>(null)
  const started = useRef(false)

  useEffect(() => {
    const el = ref.current
    if (!el || started.current) return
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    if (reduced) {
      setValue(target)
      return
    }
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting && !started.current) {
          started.current = true
          const start = performance.now()
          const tick = (now: number) => {
            const p = Math.min((now - start) / duration, 1)
            const eased = 1 - Math.pow(1 - p, 3)
            setValue(target * eased)
            if (p < 1) requestAnimationFrame(tick)
          }
          requestAnimationFrame(tick)
        }
      },
      { threshold: 0.4 },
    )
    observer.observe(el)
    return () => observer.disconnect()
  }, [target, duration])

  const formatted =
    decimals > 0
      ? value.toFixed(decimals).replace('.', ',')
      : Math.round(value).toLocaleString('id-ID')

  return (
    <span ref={ref} className="tabular-nums">
      {formatted}
      {suffix}
    </span>
  )
}

/* ---------- Slides ---------- */

function StatsSlide() {
  return (
    <div className="space-y-6">
      <h1 className="max-w-md text-[46px] leading-[1.05] font-bold tracking-[-0.03em] text-white xl:text-[54px]">
        Kelola restoran,
        <br />
        <span className="text-white/70">tanpa ribet.</span>
      </h1>
      <p className="max-w-sm text-[15px] leading-relaxed text-white/75">
        Satu sistem kasir premium untuk pesanan, pembayaran, stok, dan laporan —
        semuanya dalam satu tempat.
      </p>
      <div className="flex items-center gap-10 pt-2">
        <div>
          <p className="text-[30px] font-bold tracking-tight text-white">
            <AnimatedCounter target={2400} suffix="+" />
          </p>
          <p className="text-[12px] font-medium text-white/60">Restoran aktif</p>
        </div>
        <div className="h-10 w-px bg-white/25" />
        <div>
          <p className="text-[30px] font-bold tracking-tight text-white">
            <AnimatedCounter target={99.9} decimals={1} suffix="%" />
          </p>
          <p className="text-[12px] font-medium text-white/60">Uptime</p>
        </div>
        <div className="h-10 w-px bg-white/25" />
        <div>
          <p className="text-[30px] font-bold tracking-tight text-white">
            <AnimatedCounter target={4.9} decimals={1} />
          </p>
          <p className="text-[12px] font-medium text-white/60">Rating</p>
        </div>
      </div>
    </div>
  )
}

function TestimonialSlide() {
  const [quoteIdx, setQuoteIdx] = useState(0)

  useEffect(() => {
    const t = setInterval(() => setQuoteIdx((i) => (i + 1) % TESTIMONIALS.length), 5000)
    return () => clearInterval(t)
  }, [])

  const q = TESTIMONIALS[quoteIdx]

  return (
    <div className="space-y-6">
      <div className="max-w-md rounded-2xl bg-white/10 p-6 backdrop-blur-sm">
        <div className="mb-3 flex gap-1">
          {[...Array(5)].map((_, i) => (
            <svg
              key={i}
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="currentColor"
              className="h-4 w-4 text-[#ffd60a]"
              aria-hidden="true"
            >
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
            </svg>
          ))}
        </div>
        <div key={quoteIdx} className="animate-feed-in">
          <p className="min-h-[88px] text-[15px] leading-relaxed text-white/90">
            &ldquo;{q.quote}&rdquo;
          </p>
          <div className="mt-4 flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-white/20 text-[13px] font-bold text-white">
              {q.initials}
            </div>
            <div>
              <p className="text-[13px] font-semibold text-white">{q.name}</p>
              <p className="text-[11px] text-white/60">{q.role}</p>
            </div>
          </div>
        </div>
        {/* Quote dots */}
        <div className="mt-4 flex gap-1.5">
          {TESTIMONIALS.map((_, i) => (
            <span
              key={i}
              className={`h-1 rounded-full transition-all duration-300 ${
                i === quoteIdx ? 'w-5 bg-white' : 'w-1 bg-white/35'
              }`}
            />
          ))}
        </div>
      </div>
      <p className="max-w-sm text-[14px] leading-relaxed text-white/60">
        Dipercaya oleh ribuan restoran, kafe, dan warung di seluruh Indonesia.
      </p>
    </div>
  )
}

function MockupSlide() {
  return (
    <div className="space-y-6">
      <div className="max-w-md rounded-2xl bg-white/10 p-5 backdrop-blur-sm">
        <div className="mb-4 flex items-center justify-between">
          <p className="text-[13px] font-semibold text-white">Dashboard</p>
          <span className="flex items-center gap-1.5 text-[11px] text-white/60">
            <span className="animate-pulse-dot inline-flex h-1.5 w-1.5 rounded-full bg-[#30d158]" />
            Live
          </span>
        </div>
        <div className="mb-4 grid grid-cols-3 gap-2.5">
          {[
            { label: 'Pendapatan', value: 'Rp 12,4jt' },
            { label: 'Pesanan', value: '148' },
            { label: 'Meja aktif', value: '23' },
          ].map((s) => (
            <div key={s.label} className="rounded-xl bg-white/10 p-3">
              <p className="text-[10px] text-white/55">{s.label}</p>
              <p className="mt-0.5 text-[15px] font-bold text-white">{s.value}</p>
            </div>
          ))}
        </div>
        <div className="flex h-20 items-end gap-1.5">
          {[35, 55, 42, 70, 58, 85, 64, 92, 76, 60, 88, 96].map((h, i) => (
            <div
              key={i}
              className="flex-1 rounded-t-md bg-white/25 transition-all duration-500"
              style={{ height: `${h}%` }}
            />
          ))}
        </div>
      </div>
      <p className="max-w-sm text-[14px] leading-relaxed text-white/60">
        Pantau penjualan, stok, dan performa tim secara real-time dari mana saja.
      </p>
    </div>
  )
}

const SLIDES = [
  { id: 'stats', content: <StatsSlide /> },
  { id: 'testimonial', content: <TestimonialSlide /> },
  { id: 'mockup', content: <MockupSlide /> },
]

/* ---------- Live transaction feed ---------- */

function LiveFeed() {
  const [idx, setIdx] = useState(0)

  useEffect(() => {
    const t = setInterval(() => setIdx((i) => (i + 1) % FEED_ITEMS.length), 4000)
    return () => clearInterval(t)
  }, [])

  const item = FEED_ITEMS[idx]

  return (
    <div className="flex items-center gap-3 rounded-xl bg-white/10 px-4 py-3 backdrop-blur-sm">
      <span className="relative flex h-2 w-2 flex-shrink-0">
        <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#30d158] opacity-75" />
        <span className="relative inline-flex h-2 w-2 rounded-full bg-[#30d158]" />
      </span>
      <div key={idx} className="animate-feed-in flex min-w-0 flex-1 items-center justify-between gap-3">
        <p className="truncate text-[13px] font-medium text-white">
          {item.table}
          <span className="ml-2 text-[11px] font-normal text-white/55">{item.method}</span>
        </p>
        <p className="flex-shrink-0 text-[13px] font-bold text-white tabular-nums">
          {item.amount}
        </p>
      </div>
      <span className="flex-shrink-0 text-[10px] tracking-wide text-white/50 uppercase">
        Baru saja
      </span>
    </div>
  )
}

/* ---------- Main panel ---------- */

export function BrandingPanel() {
  const [slide, setSlide] = useState(0)
  const [paused, setPaused] = useState(false)
  const [onlineCount, setOnlineCount] = useState(1842)
  const layer1Ref = useRef<HTMLDivElement>(null)
  const layer2Ref = useRef<HTMLDivElement>(null)
  const panelRef = useRef<HTMLDivElement>(null)

  // Carousel auto-advance
  useEffect(() => {
    if (paused) return
    const t = setInterval(() => setSlide((s) => (s + 1) % SLIDES.length), 6500)
    return () => clearInterval(t)
  }, [paused])

  // Live online count fluctuation
  useEffect(() => {
    const t = setInterval(() => {
      setOnlineCount((c) => Math.max(1600, c + Math.floor(Math.random() * 21) - 10))
    }, 5000)
    return () => clearInterval(t)
  }, [])

  // Multi-layer mouse parallax
  useEffect(() => {
    const el = panelRef.current
    if (!el) return
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    if (reduced) return

    const onMove = (e: MouseEvent) => {
      const rect = el.getBoundingClientRect()
      const x = (e.clientX - rect.left) / rect.width - 0.5
      const y = (e.clientY - rect.top) / rect.height - 0.5
      if (layer1Ref.current) {
        layer1Ref.current.style.transform = `translate(${x * 14}px, ${y * 14}px)`
      }
      if (layer2Ref.current) {
        layer2Ref.current.style.transform = `translate(${x * -24}px, ${y * -24}px)`
      }
    }
    el.addEventListener('mousemove', onMove)
    return () => el.removeEventListener('mousemove', onMove)
  }, [])

  return (
    <div
      ref={panelRef}
      className="animate-gradient-pan relative hidden w-[52%] flex-col justify-between overflow-hidden p-12 lg:flex xl:p-16"
      style={{
        background:
          'linear-gradient(135deg, #f54927 0%, #e13e1c 35%, #f54927 70%, #ff6b42 100%)',
      }}
    >
      {/* Parallax layer 1 — concentric rings */}
      <div
        ref={layer1Ref}
        aria-hidden="true"
        className="pointer-events-none absolute -inset-8 opacity-10 transition-transform duration-300 ease-out"
        style={{
          backgroundImage:
            'repeating-radial-gradient(circle at 110% -10%, transparent 0px, rgba(255,255,255,0.6) 1.5px, transparent 2px, transparent 60px)',
        }}
      />

      {/* Parallax layer 2 — batik kawung-inspired pattern */}
      <div
        ref={layer2Ref}
        aria-hidden="true"
        className="pointer-events-none absolute -inset-8 opacity-[0.07] transition-transform duration-500 ease-out"
        style={{
          backgroundImage:
            'radial-gradient(circle at 25% 25%, rgba(255,255,255,0.9) 2px, transparent 2.5px), radial-gradient(circle at 75% 75%, rgba(255,255,255,0.9) 2px, transparent 2.5px), radial-gradient(circle at 75% 25%, rgba(255,255,255,0.5) 1.5px, transparent 2px), radial-gradient(circle at 25% 75%, rgba(255,255,255,0.5) 1.5px, transparent 2px)',
          backgroundSize: '56px 56px',
        }}
      />

      {/* Drifting glows */}
      <div
        aria-hidden="true"
        className="animate-drift pointer-events-none absolute -bottom-40 -left-40 h-[30rem] w-[30rem] rounded-full bg-white/15 blur-[120px]"
      />
      <div
        aria-hidden="true"
        className="animate-drift pointer-events-none absolute -top-32 -right-32 h-[24rem] w-[24rem] rounded-full bg-[#ffd60a]/10 blur-[100px]"
        style={{ animationDelay: '-7s' }}
      />

      {/* Top: logo + server status */}
      <div className="animate-fade-up relative z-10 flex items-start justify-between">
        <div className="flex items-center gap-3.5">
          <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-white shadow-[0_4px_16px_rgba(0,0,0,0.12)]">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.8"
              strokeLinecap="round"
              strokeLinejoin="round"
              className="h-6 w-6 text-[#f54927]"
              aria-hidden="true"
            >
              <rect x="3" y="3" width="18" height="18" rx="4" />
              <path d="M3 9h18" />
              <path d="M9 9v12" />
            </svg>
          </div>
          <div>
            <p className="text-[17px] font-bold tracking-tight text-white">KasirPOS</p>
            <p className="text-[11px] font-medium tracking-[0.14em] text-white/60 uppercase">
              Sistem Kasir Restoran
            </p>
          </div>
        </div>

        {/* Server status */}
        <div className="flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 backdrop-blur-sm">
          <span className="animate-pulse-dot inline-flex h-1.5 w-1.5 rounded-full bg-[#30d158]" />
          <span className="text-[11px] font-medium text-white/85">
            Semua sistem beroperasi
          </span>
        </div>
      </div>

      {/* Middle: carousel + online count */}
      <div className="relative z-10 space-y-6">
        <div className="flex items-center gap-2 text-[12px] text-white/65">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.8"
            strokeLinecap="round"
            strokeLinejoin="round"
            className="h-4 w-4"
            aria-hidden="true"
          >
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
          </svg>
          <span className="tabular-nums">
            <span className="font-semibold text-white">
              {onlineCount.toLocaleString('id-ID')}
            </span>{' '}
            kasir sedang online
          </span>
        </div>

        <div
          className="animate-fade-up relative min-h-[310px]"
          style={{ animationDelay: '0.15s' }}
          onMouseEnter={() => setPaused(true)}
          onMouseLeave={() => setPaused(false)}
        >
          {SLIDES.map((s, i) => (
            <div
              key={s.id}
              className={`absolute inset-0 transition-all duration-700 ease-out ${
                i === slide
                  ? 'translate-y-0 opacity-100'
                  : 'pointer-events-none translate-y-3 opacity-0'
              }`}
              aria-hidden={i !== slide}
            >
              {s.content}
            </div>
          ))}
          {/* Dots */}
          <div className="absolute -bottom-12 flex gap-2">
            {SLIDES.map((s, i) => (
              <button
                key={s.id}
                type="button"
                onClick={() => setSlide(i)}
                className={`h-1.5 cursor-pointer rounded-full transition-all duration-300 ${
                  i === slide ? 'w-7 bg-white' : 'w-1.5 bg-white/40 hover:bg-white/60'
                }`}
                aria-label={`Slide ${i + 1}`}
              />
            ))}
          </div>
        </div>

        {/* Live feed */}
        <div className="animate-fade-up max-w-md pt-14" style={{ animationDelay: '0.3s' }}>
          <LiveFeed />
        </div>
      </div>

      {/* Bottom: trust + badges + support */}
      <div className="animate-fade-up relative z-10 space-y-4" style={{ animationDelay: '0.35s' }}>
        <div>
          <p className="mb-2.5 text-[10px] font-semibold tracking-[0.18em] text-white/50 uppercase">
            Dipercaya oleh
          </p>
          <div className="flex flex-wrap items-center gap-x-7 gap-y-2">
            {TRUST_LOGOS.map((name) => (
              <span
                key={name}
                className="text-[15px] font-bold tracking-tight text-white/45 transition-colors duration-200 hover:text-white/75"
              >
                {name}
              </span>
            ))}
          </div>
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-white/15 pt-4">
          {/* Security badges */}
          <div className="flex flex-wrap items-center gap-2">
            {['ISO 27001', 'PCI DSS', 'Kominfo'].map((badge) => (
              <span
                key={badge}
                className="flex items-center gap-1.5 rounded-md bg-white/10 px-2.5 py-1 text-[10px] font-semibold tracking-wide text-white/70"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  className="h-3 w-3"
                  aria-hidden="true"
                >
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                </svg>
                {badge}
              </span>
            ))}
            <span className="text-[10px] text-white/40">v2.4.1</span>
          </div>

          {/* WhatsApp support */}
          <button
            type="button"
            className="flex cursor-pointer items-center gap-2 rounded-full bg-white/15 px-3.5 py-1.5 text-[11px] font-semibold text-white transition-all duration-200 hover:bg-white/25"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="currentColor"
              className="h-3.5 w-3.5"
              aria-hidden="true"
            >
              <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
            </svg>
            Butuh bantuan? Chat kami
          </button>
        </div>
      </div>
    </div>
  )
}
