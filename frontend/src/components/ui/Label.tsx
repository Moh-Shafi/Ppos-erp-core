import { type LabelHTMLAttributes } from 'react'
import { cn } from '@/lib/utils'

function Label({ className, ...props }: LabelHTMLAttributes<HTMLLabelElement>) {
  return (
    <label
      className={cn('text-[12px] font-semibold leading-none text-[#44403c] peer-disabled:cursor-not-allowed peer-disabled:opacity-70', className)}
      {...props}
    />
  )
}

export { Label }
