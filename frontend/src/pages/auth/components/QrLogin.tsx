import { useEffect, useState } from 'react'

// Deterministic pseudo-QR pattern generator (visual placeholder)
function generatePattern(seed: number): boolean[][] {
  const size = 21
  const grid: boolean[][] = []
  let s = seed
  const rand = () => {
    s = (s * 9301 + 49297) % 233280
    return s / 233280
  }
  for (let y = 0; y < size; y++) {
    const row: boolean[] = []
    for (let x = 0; x < size; x++) {
      row.push(rand() > 0.52)
    }
    grid.push(row)
  }
  // Finder patterns (3 corners)
  const finder = (ox: number, oy: number) => {
    for (let y = 0; y < 7; y++) {
      for (let x = 0; x < 7; x++) {
        const edge = y === 0 || y === 6 || x === 0 || x === 6
        const core = y >= 2 && y <= 4 && x >= 2 && x <= 4
        grid[oy + y][ox + x] = edge || core
      }
    }
  }
  finder(0, 0)
  finder(size - 7, 0)
  finder(0, size - 7)
  return grid
}

export function QrLogin() {
  const [seed, setSeed] = useState(() => Math.floor(Math.random() * 100000))
  const [grid, setGrid] = useState<boolean[][]>(() => generatePattern(seed))
  const [countdown, setCountdown] = useState(60)

  // Auto-refresh QR every 60s with countdown
  useEffect(() => {
    const t = setInterval(() => {
      setCountdown((c) => {
        if (c <= 1) {
          const ns = Math.floor(Math.random() * 100000)
          setSeed(ns)
          setGrid(generatePattern(ns))
          return 60
        }
        return c - 1
      })
    }, 1000)
    return () => clearInterval(t)
  }, [])

  const refresh = () => {
    const ns = Math.floor(Math.random() * 100000)
    setSeed(ns)
    setGrid(generatePattern(ns))
    setCountdown(60)
  }

  const cell = 100 / 21

  return (
    <div className="flex flex-col items-center space-y-5">
      {/* QR card */}
      <div className="relative overflow-hidden rounded-2xl border border-[#e7e5e4] bg-white p-5 shadow-[0_4px_20px_rgba(0,0,0,0.05)]">
        <svg
          viewBox="0 0 100 100"
          className="h-48 w-48"
          role="img"
          aria-label="Kode QR untuk masuk"
        >
          <rect width="100" height="100" fill="#ffffff" />
          {grid.map((row, y) =>
            row.map((on, x) =>
              on ? (
                <rect
                  key={`${x}-${y}`}
                  x={x * cell}
                  y={y * cell}
                  width={cell}
                  height={cell}
                  fill="#1c1917"
                />
              ) : null,
            ),
          )}
          {/* Center logo */}
          <rect x="40" y="40" width="20" height="20" rx="5" fill="#f54927" />
          <rect x="43" y="43" width="14" height="14" rx="3.5" fill="#f54927" stroke="#fff" strokeWidth="2" />
        </svg>

        {/* Scan line */}
        <div
          aria-hidden="true"
          className="qr-scan-line pointer-events-none absolute inset-x-5 top-5 h-[3px] rounded-full bg-gradient-to-r from-transparent via-[#f54927] to-transparent shadow-[0_0_12px_rgba(245,73,39,0.6)]"
        />
      </div>

      <div className="flex items-center gap-2 text-[13px] text-[#78716c]">
        <span className="animate-pulse-dot inline-flex h-2 w-2 rounded-full bg-[#22c55e]" />
        Menunggu pemindaian...
      </div>

      <p className="max-w-[300px] text-center text-[13px] leading-relaxed text-[#a8a29e]">
        Buka aplikasi <span className="font-semibold text-[#44403c]">KasirPOS</span> di
        ponsel Anda, lalu pindai kode ini untuk masuk secara instan.
      </p>

      <div className="flex items-center gap-3">
        <span className="text-[11px] text-[#a8a29e] tabular-nums">
          Kode baru dalam {countdown} detik
        </span>
        <button
          type="button"
          onClick={refresh}
          className="flex cursor-pointer items-center gap-1.5 text-[13px] font-semibold text-[#f54927] transition-colors hover:text-[#d63a1b]"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            className="h-4 w-4"
            aria-hidden="true"
          >
            <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8" />
            <path d="M21 3v5h-5" />
          </svg>
          Muat ulang
        </button>
      </div>
      {/* seed keeps eslint from unused warning */}
      <span className="hidden">{seed}</span>
    </div>
  )
}
