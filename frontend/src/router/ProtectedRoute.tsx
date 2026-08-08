import { Navigate } from 'react-router-dom'
import { type ReactNode } from 'react'
import { useAuthStore } from '@/stores/auth'

export function ProtectedRoute({ children }: { children: ReactNode }) {
  const { isAuthenticated } = useAuthStore()

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  return <>{children}</>
}
