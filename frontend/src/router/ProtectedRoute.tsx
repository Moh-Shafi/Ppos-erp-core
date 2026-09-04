import { Navigate } from 'react-router-dom'
import { type ReactNode } from 'react'
import { useAuthStore } from '@/stores/auth'
import { useModuleConfigStore } from '@/stores/module-config'

interface ProtectedRouteProps {
  module?: string
  permission?: string
  children: ReactNode
}

export function ProtectedRoute({ module, permission, children }: ProtectedRouteProps) {
  const { isAuthenticated } = useAuthStore()
  const moduleConfig = useModuleConfigStore()

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  // Wait for module config to load before checking module/permission
  // This prevents premature redirect on full page reload (Zustand state resets)
  if ((module || permission) && !moduleConfig.loaded) {
    return null
  }

  if (module && !moduleConfig.hasModule(module)) {
    return <Navigate to="/dashboard" replace />
  }

  if (permission && !moduleConfig.hasPermission(permission)) {
    return <Navigate to="/dashboard" replace />
  }

  return <>{children}</>
}
