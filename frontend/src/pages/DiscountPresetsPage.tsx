import { useState, useEffect, useCallback } from 'react'
import type { DiscountPreset } from '@/types'
import { discountPresetService } from '@/services/discountPreset'
import { useModuleConfigStore } from '@/stores/module-config'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Select } from '@/components/ui/Select'
import { Modal } from '@/components/ui/Modal'
import { Badge } from '@/components/ui/Badge'

export function DiscountPresetsPage() {
  const moduleConfig = useModuleConfigStore()
  const [presets, setPresets] = useState<DiscountPreset[]>([])
  const [loading, setLoading] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [editingPreset, setEditingPreset] = useState<DiscountPreset | null>(null)
  const [form, setForm] = useState({ name: '', type: 'percentage' as 'percentage' | 'fixed', value: '', is_active: true })
  const [error, setError] = useState('')

  const fetchPresets = useCallback(async () => {
    setLoading(true)
    try {
      const data = await discountPresetService.list()
      setPresets(data)
    } catch {
      // ignore
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchPresets()
  }, [fetchPresets])

  const canManage = moduleConfig.hasPermission('pos.discount_presets')

  const openCreate = () => {
    setEditingPreset(null)
    setForm({ name: '', type: 'percentage', value: '', is_active: true })
    setError('')
    setModalOpen(true)
  }

  const openEdit = (preset: DiscountPreset) => {
    setEditingPreset(preset)
    setForm({ name: preset.name, type: preset.type, value: preset.value, is_active: preset.is_active })
    setError('')
    setModalOpen(true)
  }

  const handleSave = async () => {
    if (!form.name.trim()) {
      setError('Nama wajib diisi')
      return
    }
    const value = parseFloat(form.value)
    if (isNaN(value) || value <= 0) {
      setError('Nilai harus lebih dari 0')
      return
    }
    try {
      if (editingPreset) {
        await discountPresetService.update(editingPreset.id, {
          name: form.name,
          type: form.type,
          value,
          is_active: form.is_active,
        })
      } else {
        await discountPresetService.create({
          name: form.name,
          type: form.type,
          value,
          is_active: form.is_active,
        })
      }
      setModalOpen(false)
      fetchPresets()
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } }
      setError(e.response?.data?.message ?? 'Gagal menyimpan')
    }
  }

  const handleDelete = async (id: number) => {
    if (!confirm('Hapus preset diskon ini?')) return
    try {
      await discountPresetService.delete(id)
      fetchPresets()
    } catch {
      // ignore
    }
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Preset Diskon</h1>
        {canManage && (
          <Button onClick={openCreate}>+ Tambah Preset</Button>
        )}
      </div>

      {loading ? (
        <p className="text-muted-foreground">Memuat...</p>
      ) : presets.length === 0 ? (
        <p className="text-muted-foreground">Belum ada preset diskon</p>
      ) : (
        <div className="space-y-2">
          {presets.map((preset) => (
            <div key={preset.id} className="flex items-center justify-between rounded-lg border border-border bg-card p-4">
              <div className="flex items-center gap-3">
                <div>
                  <p className="font-medium">{preset.name}</p>
                  <p className="text-sm text-muted-foreground">
                    {preset.type === 'percentage' ? `${preset.value}%` : `Rp ${preset.value}`}
                  </p>
                </div>
                <Badge variant={preset.is_active ? 'success' : 'default'}>
                  {preset.is_active ? 'Aktif' : 'Nonaktif'}
                </Badge>
              </div>
              {canManage && (
                <div className="flex gap-2">
                  <Button size="sm" variant="outline" onClick={() => openEdit(preset)}>
                    Edit
                  </Button>
                  <Button size="sm" variant="destructive" onClick={() => handleDelete(preset.id)}>
                    Hapus
                  </Button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      <Modal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editingPreset ? 'Edit Preset Diskon' : 'Tambah Preset Diskon'}
        size="sm"
        footer={
          <>
            <Button variant="outline" onClick={() => setModalOpen(false)}>Batal</Button>
            <Button onClick={handleSave}>Simpan</Button>
          </>
        }
      >
        <div className="space-y-3">
          <div>
            <label className="text-sm font-medium mb-1 block">Nama</label>
            <Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Contoh: Diskon Member 10%" />
          </div>
          <div>
            <label className="text-sm font-medium mb-1 block">Tipe</label>
            <Select
              value={form.type}
              onChange={(e) => setForm({ ...form, type: e.target.value as 'percentage' | 'fixed' })}
              options={[
                { value: 'percentage', label: 'Persentase (%)' },
                { value: 'fixed', label: 'Nominal (Rp)' },
              ]}
            />
          </div>
          <div>
            <label className="text-sm font-medium mb-1 block">Nilai</label>
            <Input
              type="number"
              value={form.value}
              onChange={(e) => setForm({ ...form, value: e.target.value })}
              placeholder={form.type === 'percentage' ? '10' : '5000'}
            />
          </div>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={form.is_active}
              onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
            />
            Aktif
          </label>
          {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
      </Modal>
    </div>
  )
}
