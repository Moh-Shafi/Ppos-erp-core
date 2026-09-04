import { useEffect } from 'react'
import { Navigate } from 'react-router-dom'
import { type ReactNode } from 'react'
import { useAuthStore } from '@/stores/auth'
import { useModuleConfigStore } from '@/stores/module-config'
import { authService } from '@/services/auth'

export function GuestRoute({ children }: { children: ReactNode }) {
  const { isAuthenticated, user, setUser } = useAuthStore()
  const moduleConfig = useModuleConfigStore()

  useEffect(() => {
    if (isAuthenticated && !user) {
      authService.me().then((res) => {
        setUser(res.user)
        moduleConfig.setConfig({
          modules: res.modules,
          features: res.features,
          permissions: res.permissions,
          stores: res.stores,
          business_profile: res.business_profile,
        })
      }).catch(() => {
        useAuthStore.getState().logout()
      })
    }
  }, [isAuthenticated, user, setUser, moduleConfig])

  if (isAuthenticated && user) {
    return <Navigate to="/dashboard" replace />
  }

  return <>{children}</>
}
