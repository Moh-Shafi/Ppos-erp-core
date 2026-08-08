import { useState, useEffect, type FormEvent } from 'react'
import { useAuthStore } from '@/stores/auth'
import { tenantService } from '@/services/tenant'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'

export function StoreSettingsPage() {
  const { user, setUser } = useAuthStore()
  const [name, setName] = useState(user?.tenant?.name ?? '')
  const [loading, setLoading] = useState(false)
  const [message, setMessage] = useState('')

  useEffect(() => {
    if (user?.tenant) {
      setName(user.tenant.name)
    }
  }, [user?.tenant])

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault()
    setLoading(true)
    setMessage('')
    try {
      const updatedTenant = await tenantService.update({ name })
      if (user) {
        setUser({ ...user, tenant: updatedTenant })
      }
      setMessage('Toko updated successfully')
    } catch {
      setMessage('Failed to update store')
    } finally {
      setLoading(false)
    }
  }

  return (
    <DashboardLayout>
      <div className="max-w-2xl space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Pengaturan Toko</h1>
          <p className="text-muted-foreground">Manage your store information</p>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Store Details</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-4">
              {message && (
                <div className="rounded-md bg-primary/10 p-3 text-sm text-primary">{message}</div>
              )}
              <div className="space-y-2">
                <Label htmlFor="store_name">Nama Toko</Label>
                <Input
                  id="store_name"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  required
                />
              </div>
              <Button type="submit" disabled={loading}>
                {loading ? 'Saving...' : 'Save Changes'}
              </Button>
            </form>
          </CardContent>
        </Card>
      </div>
    </DashboardLayout>
  )
}
