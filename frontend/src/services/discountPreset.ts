import api from '@/lib/api'
import type { DiscountPreset } from '@/types'

export const discountPresetService = {
  list: () =>
    api.get<DiscountPreset[]>('/discount-presets').then((r) => r.data),

  create: (data: { name: string; type: 'percentage' | 'fixed'; value: number; is_active?: boolean }) =>
    api.post<DiscountPreset>('/discount-presets', data).then((r) => r.data),

  update: (id: number, data: Partial<{ name: string; type: 'percentage' | 'fixed'; value: number; is_active: boolean }>) =>
    api.put<DiscountPreset>(`/discount-presets/${id}`, data).then((r) => r.data),

  delete: (id: number) =>
    api.delete(`/discount-presets/${id}`).then((r) => r.data),
}
