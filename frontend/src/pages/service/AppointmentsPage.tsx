import { useEffect, useState } from 'react'
import { serviceService, type Appointment } from '@/services/service'

export function AppointmentsPage() {
  const [appointments, setAppointments] = useState<Appointment[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    serviceService.getAppointments({ per_page: 50 })
      .then((res) => setAppointments(res.data || []))
      .finally(() => setLoading(false))
  }, [])

  return (
    <div className="p-6">
      <h1 className="text-2xl font-bold mb-4">Appointments</h1>
      {loading ? (
        <p>Loading...</p>
      ) : (
        <div className="space-y-3">
          {appointments.map((appt) => (
            <div key={appt.id} className="border rounded p-4 shadow-sm">
              <h2 className="font-semibold">Appointment #{appt.id}</h2>
              <p className="text-sm text-gray-600">Date: {appt.appointment_date} {appt.start_time}</p>
              <p className="text-sm text-gray-600">Status: {appt.status}</p>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
