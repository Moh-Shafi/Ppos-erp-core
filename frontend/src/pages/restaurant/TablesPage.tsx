import { useEffect, useState } from 'react'
import { restaurantService, type Table } from '@/services/restaurant'

export function TablesPage() {
  const [tables, setTables] = useState<Table[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    restaurantService.getTables({ per_page: 50 })
      .then((res) => setTables(res.data || []))
      .finally(() => setLoading(false))
  }, [])

  return (
    <div className="p-6">
      <h1 className="text-2xl font-bold mb-4">Tables</h1>
      {loading ? (
        <p>Loading...</p>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {tables.map((table) => (
            <div key={table.id} className="border rounded p-4 shadow-sm">
              <h2 className="font-semibold">{table.name}</h2>
              <p className="text-sm text-gray-600">Capacity: {table.capacity}</p>
              <p className="text-sm text-gray-600">Status: {table.status}</p>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
