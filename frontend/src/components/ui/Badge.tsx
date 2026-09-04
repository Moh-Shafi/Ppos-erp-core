import { type HTMLAttributes } from 'react'
import { cn } from '@/lib/utils'

interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
  variant?: 'success' | 'danger' | 'warning' | 'default'
}

export function Badge({ className, variant = 'default', ...props }: BadgeProps) {
  const variants = {
    success: 'bg-[#16a34a]/10 text-[#16a34a]',
    danger: 'bg-[#dc2626]/10 text-[#dc2626]',
    warning: 'bg-[#ca8a04]/10 text-[#ca8a04]',
    default: 'bg-[#f5f5f4] text-[#78716c]',
  }
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[10px] font-bold',
        variants[variant],
        className,
      )}
      {...props}
    />
  )
}
