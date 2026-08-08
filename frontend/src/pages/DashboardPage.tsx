import { useAuthStore } from '@/stores/auth'
import { DashboardLayout } from '@/layouts/DashboardLayout'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/Card'

export function DashboardPage() {
  const { user } = useAuthStore()

  const stats = [
    { label: 'Produk', value: 0 },
    { label: 'Penjualan', value: 0 },
    { label: 'Stok', value: 0 },
  ]

  return (
    <DashboardLayout>
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Dashboard</h1>
          <p className="text-muted-foreground">
            Selamat datang, {user?.name}
          </p>
          <p className="text-sm text-muted-foreground mt-1">
            {user?.tenant?.name}
          </p>
        </div>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          {stats.map((stat) => (
            <Card key={stat.label}>
              <CardHeader>
                <CardTitle className="text-sm font-medium text-muted-foreground">
                  {stat.label}
                </CardTitle>
              </CardHeader>
              <CardContent>
                <p className="text-3xl font-bold text-foreground">{stat.value}</p>
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    </DashboardLayout>
  )
}
