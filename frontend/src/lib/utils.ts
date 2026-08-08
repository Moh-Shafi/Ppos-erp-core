export function cn(...classes: (string | undefined | null | false)[]): string {
  return classes.filter(Boolean).join(' ')
}

export function formatRupiah(value: number | string): string {
  const num = typeof value === 'string' ? parseFloat(value) : value
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(num || 0)
}

export function parseRupiah(value: string): number {
  const cleaned = value.replace(/[^\d]/g, '')
  return cleaned ? parseInt(cleaned, 10) : 0
}
