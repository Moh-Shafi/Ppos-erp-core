import { useEffect } from 'react'
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { useAuthStore } from '@/stores/auth'
import { authService } from '@/services/auth'
import { ProtectedRoute } from '@/router/ProtectedRoute'
import { GuestRoute } from '@/router/GuestRoute'
import { LoginPage } from '@/pages/auth/LoginPage'
import { RegisterPage } from '@/pages/auth/RegisterPage'
import { DashboardPage } from '@/pages/DashboardPage'
import { StoreSettingsPage } from '@/pages/settings/StoreSettingsPage'
import { AccountSettingsPage } from '@/pages/settings/AccountSettingsPage'
import { ProductsPage } from '@/pages/ProductsPage'
import { CategoriesPage } from '@/pages/CategoriesPage'
import { InventoryPage } from '@/pages/InventoryPage'
import { MovementsPage } from '@/pages/MovementsPage'
import { TransferPage } from '@/pages/TransferPage'
import { CustomersPage } from '@/pages/CustomersPage'

function App() {
  const { isAuthenticated, user, setUser, logout } = useAuthStore()

  useEffect(() => {
    if (isAuthenticated && !user) {
      authService
        .me()
        .then(setUser)
        .catch(() => logout())
    }
  }, [isAuthenticated, user, setUser, logout])

  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<GuestRoute><LoginPage /></GuestRoute>} />
        <Route path="/register" element={<GuestRoute><RegisterPage /></GuestRoute>} />
        <Route path="/dashboard" element={<ProtectedRoute><DashboardPage /></ProtectedRoute>} />
        <Route path="/products" element={<ProtectedRoute><ProductsPage /></ProtectedRoute>} />
        <Route path="/categories" element={<ProtectedRoute><CategoriesPage /></ProtectedRoute>} />
        <Route path="/customers" element={<ProtectedRoute><CustomersPage /></ProtectedRoute>} />
        <Route path="/inventory" element={<ProtectedRoute><InventoryPage /></ProtectedRoute>} />
        <Route path="/inventory/movements" element={<ProtectedRoute><MovementsPage /></ProtectedRoute>} />
        <Route path="/inventory/transfer" element={<ProtectedRoute><TransferPage /></ProtectedRoute>} />
        <Route path="/settings/store" element={<ProtectedRoute><StoreSettingsPage /></ProtectedRoute>} />
        <Route path="/settings/account" element={<ProtectedRoute><AccountSettingsPage /></ProtectedRoute>} />
        <Route path="*" element={<Navigate to="/dashboard" replace />} />
      </Routes>
    </BrowserRouter>
  )
}

export default App
