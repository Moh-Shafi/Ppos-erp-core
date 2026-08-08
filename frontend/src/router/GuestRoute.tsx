import { useEffect } from 'react'
import { Navigate } from 'react-router-dom'
import { type ReactNode } from 'react'
import { useAuthStore } from '@/stores/auth'
import { authService } from '@/services/auth'

export function GuestRoute({ children }: { children: ReactNode }) {
  const { isAuthenticated, user, setUser } = useAuthStore()

  useEffect(() => {
    if (isAuthenticated && !user) {
      authService.me().then(setUser).catch(() => {
        useAuthStore.getState().logout()
      })
    }
  }, [isAuthenticated, user, setUser])

  if (isAuthenticated && user) {
    return <Navigate to="/dashboard" replace />
  }

  return <>{children}</>
}
