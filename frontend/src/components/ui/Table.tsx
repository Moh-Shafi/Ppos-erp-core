import { type ReactNode } from 'react'
import { cn } from '@/lib/utils'

interface TableProps {
  children: ReactNode
  className?: string
}

export function Table({ children, className }: TableProps) {
  return (
    <div className="w-full overflow-x-auto">
      <table className={cn('w-full text-sm', className)}>{children}</table>
    </div>
  )
}

export function THead({ children }: { children: ReactNode }) {
  return <thead className="border-b border-border">{children}</thead>
}

export function TBody({ children }: { children: ReactNode }) {
  return <tbody>{children}</tbody>
}

export function TR({ children, className, ...props }: { children: ReactNode; className?: string; onClick?: () => void }) {
  return (
    <tr className={cn('border-b border-border last:border-0', className)} {...props}>
      {children}
    </tr>
  )
}

export function TH({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <th className={cn('px-4 py-3 text-left font-medium text-muted-foreground', className)}>
      {children}
    </th>
  )
}

export function TD({ children, className }: { children: ReactNode; className?: string }) {
  return <td className={cn('px-4 py-3', className)}>{children}</td>
}
