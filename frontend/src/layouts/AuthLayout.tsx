import { type ReactNode } from 'react'

export function AuthLayout({ children }: { children: ReactNode }) {
  return (
    <div className="relative min-h-screen w-full overflow-hidden bg-[#fafaf9] text-[#1c1917]">
      {/* Premium ambient gradient — warm coral tones */}
      <div
        aria-hidden="true"
        className="pointer-events-none fixed inset-0 overflow-hidden"
      >
        {/* Top-left warm glow */}
        <div className="absolute -top-48 -left-48 h-[38rem] w-[38rem] rounded-full bg-gradient-to-br from-[#f54927]/15 via-[#ff6b4a]/8 to-transparent blur-[100px]" />

        {/* Bottom-right soft glow */}
        <div className="absolute -bottom-48 -right-48 h-[42rem] w-[42rem] rounded-full bg-gradient-to-tl from-[#f54927]/12 via-[#ffa08a]/6 to-transparent blur-[120px]" />

        {/* Center accent */}
        <div className="absolute top-1/3 right-1/3 h-80 w-80 rounded-full bg-gradient-to-br from-[#f54927]/6 to-transparent blur-[80px]" />

        {/* Premium dot grid */}
        <div
          className="absolute inset-0 opacity-[0.025]"
          style={{
            backgroundImage: 'radial-gradient(circle, #1c1917 1px, transparent 1px)',
            backgroundSize: '32px 32px',
          }}
        />
      </div>

      {/* Content */}
      <div className="relative z-10 flex min-h-screen w-full items-center justify-center p-4 sm:p-8">
        {children}
      </div>
    </div>
  )
}
