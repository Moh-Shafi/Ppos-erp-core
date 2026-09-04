import { type ButtonHTMLAttributes, forwardRef } from 'react'
import { cn } from '@/lib/utils'

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'default' | 'outline' | 'ghost' | 'destructive'
  size?: 'sm' | 'md' | 'lg'
}

const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant = 'default', size = 'md', ...props }, ref) => {
    const variants: Record<string, string> = {
      default:
        'bg-gradient-to-r from-[#f54927] to-[#ff6b4a] text-white shadow-[0_4px_14px_rgba(245,73,39,0.25)] hover:shadow-[0_6px_20px_rgba(245,73,39,0.35)] hover:brightness-105',
      outline:
        'border border-[#e7e5e4] bg-white text-[#44403c] hover:border-[#f54927]/30 hover:bg-[#fef8f6] hover:text-[#f54927]',
      ghost:
        'text-[#78716c] hover:bg-[#f5f5f4] hover:text-[#1c1917]',
      destructive:
        'bg-gradient-to-r from-[#dc2626] to-[#ef4444] text-white shadow-[0_4px_14px_rgba(220,38,38,0.25)] hover:shadow-[0_6px_20px_rgba(220,38,38,0.35)] hover:brightness-105',
    }
    const sizes: Record<string, string> = {
      sm: 'h-8 px-3 text-[12px] rounded-lg gap-1.5',
      md: 'h-10 px-4 text-[13px] rounded-xl gap-2',
      lg: 'h-12 px-6 text-[14px] rounded-xl gap-2',
    }
    return (
      <button
        ref={ref}
        className={cn(
          'inline-flex cursor-pointer items-center justify-center font-semibold transition-all duration-200 active:scale-[0.97] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#f54927]/30 disabled:pointer-events-none disabled:opacity-50',
          variants[variant],
          sizes[size],
          className,
        )}
        {...props}
      />
    )
  },
)
Button.displayName = 'Button'

export { Button }
