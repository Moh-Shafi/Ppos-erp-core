import { useEffect, useRef, useState } from 'react'

const PIN_LENGTH = 6

interface PinLoginProps {
  onSubmit: (pin: string) => void
  loading: boolean
  disabled?: boolean
}

export function PinLogin({ onSubmit, loading, disabled }: PinLoginProps) {
  const [digits, setDigits] = useState<string[]>(Array(PIN_LENGTH).fill(''))
  const inputsRef = useRef<(HTMLInputElement | null)[]>([])

  useEffect(() => {
    inputsRef.current[0]?.focus()
  }, [])

  const reset = () => {
    setDigits(Array(PIN_LENGTH).fill(''))
    inputsRef.current[0]?.focus()
  }

  const handleChange = (i: number, value: string) => {
    const v = value.replace(/\D/g, '').slice(-1)
    const next = [...digits]
    next[i] = v
    setDigits(next)
    if (v && i < PIN_LENGTH - 1) inputsRef.current[i + 1]?.focus()
    if (v && next.every((d) => d !== '')) onSubmit(next.join(''))
  }

  const handleKeyDown = (i: number, e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Backspace') {
      if (!digits[i] && i > 0) {
        const next = [...digits]
        next[i - 1] = ''
        setDigits(next)
        inputsRef.current[i - 1]?.focus()
      } else {
        const next = [...digits]
        next[i] = ''
        setDigits(next)
      }
    } else if (e.key === 'ArrowLeft' && i > 0) {
      inputsRef.current[i - 1]?.focus()
    } else if (e.key === 'ArrowRight' && i < PIN_LENGTH - 1) {
      inputsRef.current[i + 1]?.focus()
    }
  }

  const handlePaste = (e: React.ClipboardEvent) => {
    e.preventDefault()
    const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, PIN_LENGTH)
    if (!pasted) return
    const next = Array(PIN_LENGTH).fill('')
    pasted.split('').forEach((d, i) => (next[i] = d))
    setDigits(next)
    if (pasted.length === PIN_LENGTH) {
      onSubmit(pasted)
    } else {
      inputsRef.current[pasted.length]?.focus()
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-center gap-2.5" onPaste={handlePaste}>
        {digits.map((d, i) => (
          <input
            key={i}
            ref={(el) => {
              inputsRef.current[i] = el
            }}
            type="password"
            inputMode="numeric"
            maxLength={1}
            value={d}
            disabled={loading || disabled}
            onChange={(e) => handleChange(i, e.target.value)}
            onKeyDown={(e) => handleKeyDown(i, e)}
            aria-label={`Digit PIN ${i + 1}`}
            className={`h-14 w-12 cursor-pointer rounded-xl border text-center text-[22px] font-bold text-[#1c1917] transition-all duration-200 outline-none disabled:opacity-50 ${
              d
                ? 'border-[#f54927] bg-[#fef3ef] shadow-[0_2px_10px_rgba(245,73,39,0.12)]'
                : 'border-[#e7e5e4] bg-white hover:border-[#d6d3d1]'
            } focus:border-[#f54927] focus:ring-4 focus:ring-[#f54927]/10`}
          />
        ))}
      </div>

      <div className="flex items-center justify-between">
        <p className="text-[12px] text-[#a8a29e]">
          Masukkan PIN 6 digit karyawan Anda
        </p>
        <button
          type="button"
          onClick={reset}
          disabled={loading}
          className="cursor-pointer text-[13px] font-semibold text-[#f54927] transition-colors hover:text-[#d63a1b] disabled:opacity-50"
        >
          Ulangi
        </button>
      </div>

      {loading && (
        <p className="flex items-center justify-center gap-2 text-[13px] text-[#78716c]">
          <svg
            className="h-4 w-4 animate-spin"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path
              className="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 0 1 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            />
          </svg>
          Memverifikasi PIN...
        </p>
      )}
    </div>
  )
}
