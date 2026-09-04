import { useEffect, useState } from 'react'
import { retailService, type Promotion } from '@/services/retail'

export function PromotionsPage() {
  const [promotions, setPromotions] = useState<Promotion[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    retailService.getPromotions({ per_page: 50 })
      .then((res) => setPromotions(res.data || []))
      .finally(() => setLoading(false))
  }, [])

  return (
    <div className="p-6">
      <h1 className="text-2xl font-bold mb-4">Promotions</h1>
      {loading ? (
        <p>Loading...</p>
      ) : (
        <div className="space-y-3">
          {promotions.map((promo) => (
            <div key={promo.id} className="border rounded p-4 shadow-sm">
              <h2 className="font-semibold">{promo.name}</h2>
              <p className="text-sm text-gray-600">{promo.type} - {promo.value}</p>
              <p className="text-sm text-gray-600">{promo.start_date} to {promo.end_date}</p>
              <p className="text-sm text-gray-600">Active: {promo.is_active ? 'Yes' : 'No'}</p>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
