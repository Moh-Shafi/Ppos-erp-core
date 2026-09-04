import { useState, useEffect } from 'react'
import type { ReceiptSettings, Store } from '@/types'
import { storeService } from '@/services/store'
import { useModuleConfigStore } from '@/stores/module-config'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Select } from '@/components/ui/Select'

export function ReceiptSettingsPage() {
  const moduleConfig = useModuleConfigStore()
  const [stores, setStores] = useState<Store[]>([])
  const [selectedStoreId, setSelectedStoreId] = useState<number | ''>('')
  const [settings, setSettings] = useState<ReceiptSettings>({})
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState('')

  useEffect(() => {
    const stores = moduleConfig.stores
    setStores(stores)
    if (stores.length > 0 && !selectedStoreId) {
      setSelectedStoreId(stores[0].id)
    }
  }, [moduleConfig.stores])

  useEffect(() => {
    if (!selectedStoreId) return
    setLoading(true)
    storeService
      .getReceiptSettings(Number(selectedStoreId))
      .then((data) => setSettings(data ?? {}))
      .catch(() => setSettings({}))
      .finally(() => setLoading(false))
  }, [selectedStoreId])

  const handleSave = async () => {
    if (!selectedStoreId) return
    setSaving(true)
    setMessage('')
    try {
      await storeService.updateReceiptSettings(Number(selectedStoreId), settings)
      setMessage('Pengaturan struk berhasil disimpan')
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } }
      setMessage(e.response?.data?.message ?? 'Gagal menyimpan')
    } finally {
      setSaving(false)
    }
  }

  const canManage = moduleConfig.hasPermission('settings.manage')

  return (
    <div className="space-y-4 max-w-2xl">
      <h1 className="text-2xl font-bold">Pengaturan Struk</h1>

      <div>
        <label className="text-sm font-medium mb-1 block">Pilih Toko</label>
        <Select
          value={selectedStoreId}
          onChange={(e) => setSelectedStoreId(e.target.value ? Number(e.target.value) : '')}
          options={stores.map((s) => ({ value: s.id, label: s.name }))}
          placeholder="Pilih Toko"
        />
      </div>

      {loading ? (
        <p className="text-muted-foreground">Memuat...</p>
      ) : (
        <div className="space-y-4 rounded-lg border border-border bg-card p-6">
          <div>
            <label className="text-sm font-medium mb-1 block">Teks Header</label>
            <Input
              value={settings.header_text ?? ''}
              onChange={(e) => setSettings({ ...settings, header_text: e.target.value })}
              placeholder="Selamat datang di toko kami"
              disabled={!canManage}
            />
          </div>

          <div>
            <label className="text-sm font-medium mb-1 block">Teks Footer</label>
            <Input
              value={settings.footer_text ?? ''}
              onChange={(e) => setSettings({ ...settings, footer_text: e.target.value })}
              placeholder="Terima kasih!"
              disabled={!canManage}
            />
          </div>

          <div>
            <label className="text-sm font-medium mb-1 block">Lebar Kertas</label>
            <Select
              value={settings.paper_width ?? '80mm'}
              onChange={(e) => setSettings({ ...settings, paper_width: e.target.value })}
              options={[
                { value: '58mm', label: '58mm' },
                { value: '80mm', label: '80mm' },
              ]}
              disabled={!canManage}
            />
          </div>

          <div className="space-y-2">
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={settings.show_cashier ?? true}
                onChange={(e) => setSettings({ ...settings, show_cashier: e.target.checked })}
                disabled={!canManage}
              />
              Tampilkan nama kasir
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={settings.show_customer ?? false}
                onChange={(e) => setSettings({ ...settings, show_customer: e.target.checked })}
                disabled={!canManage}
              />
              Tampilkan nama pelanggan
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={settings.show_qr_code ?? false}
                onChange={(e) => setSettings({ ...settings, show_qr_code: e.target.checked })}
                disabled={!canManage}
              />
              Tampilkan QR code pembayaran
            </label>
          </div>

          {canManage && (
            <Button onClick={handleSave} disabled={saving}>
              {saving ? 'Menyimpan...' : 'Simpan Pengaturan'}
            </Button>
          )}

          {message && (
            <p className={`text-sm ${message.includes('berhasil') ? 'text-green-600' : 'text-destructive'}`}>
              {message}
            </p>
          )}
        </div>
      )}
    </div>
  )
}
