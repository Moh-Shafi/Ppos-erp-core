import { useEffect } from 'react'
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { useAuthStore } from '@/stores/auth'
import { useModuleConfigStore } from '@/stores/module-config'
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
import { UnitsPage } from '@/pages/UnitsPage'
import { PriceListsPage } from '@/pages/PriceListsPage'
import { InventoryPage } from '@/pages/InventoryPage'
import { MovementsPage } from '@/pages/MovementsPage'
import { TransferPage } from '@/pages/TransferPage'
import { CustomersPage } from '@/pages/CustomersPage'
import { SuppliersPage } from '@/pages/SuppliersPage'
import { PurchasesPage } from '@/pages/PurchasesPage'
import { PurchaseReturnsPage } from '@/pages/PurchaseReturnsPage'
import { POSPage } from '@/pages/POSPage'
import { SalesPage } from '@/pages/SalesPage'
import { WarehousesPage } from '@/pages/WarehousesPage'
import { StocktakePage } from '@/pages/StocktakePage'
import { TransferRequestsPage } from '@/pages/TransferRequestsPage'
import { AdjustmentReasonsPage } from '@/pages/AdjustmentReasonsPage'
import { ValuationReportPage } from '@/pages/ValuationReportPage'
import { ReportsPage } from '@/pages/ReportsPage'
import { LoyaltyPage } from '@/pages/crm/LoyaltyPage'
import { CustomerCreditPage } from '@/pages/crm/CustomerCreditPage'
import { TablesPage } from '@/pages/restaurant/TablesPage'
import { PromotionsPage } from '@/pages/retail/PromotionsPage'
import { AppointmentsPage } from '@/pages/service/AppointmentsPage'
import { RequisitionsPage } from '@/pages/purchasing/RequisitionsPage'
import { GrnsPage } from '@/pages/purchasing/GrnsPage'
import { SupplierInvoicesPage } from '@/pages/purchasing/SupplierInvoicesPage'
import { AutoReorderPage } from '@/pages/purchasing/AutoReorderPage'
import { DiscountPresetsPage } from '@/pages/DiscountPresetsPage'
import { ReceiptSettingsPage } from '@/pages/settings/ReceiptSettingsPage'
import { IntegrationsPage, WebhooksPage, ApiKeysPage } from '@/pages/integrations'
import { SecuritySettingsPage } from '@/pages/settings/SecuritySettingsPage'
import { AuditLogsPage } from '@/pages/settings/AuditLogsPage'
import { PrivacySettingsPage } from '@/pages/settings/PrivacySettingsPage'

function App() {
  const { isAuthenticated, user, setUser, logout } = useAuthStore()
  const moduleConfig = useModuleConfigStore()

  useEffect(() => {
    if (isAuthenticated && !user) {
      authService
        .me()
        .then((res) => {
          setUser(res.user)
          moduleConfig.setConfig({
            modules: res.modules,
            features: res.features,
            permissions: res.permissions,
            stores: res.stores,
            business_profile: res.business_profile,
          })
        })
        .catch(() => logout())
    }
  }, [isAuthenticated, user, setUser, logout, moduleConfig])

  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<GuestRoute><LoginPage /></GuestRoute>} />
        <Route path="/register" element={<GuestRoute><RegisterPage /></GuestRoute>} />
        <Route path="/dashboard" element={<ProtectedRoute><DashboardPage /></ProtectedRoute>} />
        <Route path="/products" element={<ProtectedRoute module="core" permission="products.view"><ProductsPage /></ProtectedRoute>} />
        <Route path="/categories" element={<ProtectedRoute module="core" permission="categories.view"><CategoriesPage /></ProtectedRoute>} />
        <Route path="/units" element={<ProtectedRoute module="core" permission="products.view"><UnitsPage /></ProtectedRoute>} />
        <Route path="/price-lists" element={<ProtectedRoute module="core" permission="products.view"><PriceListsPage /></ProtectedRoute>} />
        <Route path="/customers" element={<ProtectedRoute module="customers" permission="customers.view"><CustomersPage /></ProtectedRoute>} />
        <Route path="/crm/loyalty" element={<ProtectedRoute module="customers" permission="crm.view"><LoyaltyPage /></ProtectedRoute>} />
        <Route path="/crm/credit" element={<ProtectedRoute module="customers" permission="crm.view"><CustomerCreditPage /></ProtectedRoute>} />
        <Route path="/suppliers" element={<ProtectedRoute module="suppliers" permission="suppliers.view"><SuppliersPage /></ProtectedRoute>} />
        <Route path="/purchases" element={<ProtectedRoute module="purchasing" permission="purchases.view"><PurchasesPage /></ProtectedRoute>} />
        <Route path="/purchase-returns" element={<ProtectedRoute module="purchasing" permission="purchases.view"><PurchaseReturnsPage /></ProtectedRoute>} />
        <Route path="/purchasing/requisitions" element={<ProtectedRoute module="purchasing" permission="purchases.view"><RequisitionsPage /></ProtectedRoute>} />
        <Route path="/purchasing/grns" element={<ProtectedRoute module="purchasing" permission="purchases.view"><GrnsPage /></ProtectedRoute>} />
        <Route path="/purchasing/invoices" element={<ProtectedRoute module="purchasing" permission="purchases.view"><SupplierInvoicesPage /></ProtectedRoute>} />
        <Route path="/purchasing/auto-reorder" element={<ProtectedRoute module="purchasing" permission="purchases.view"><AutoReorderPage /></ProtectedRoute>} />
        <Route path="/pos" element={<ProtectedRoute module="pos" permission="pos.use"><POSPage /></ProtectedRoute>} />
        <Route path="/pos/discount-presets" element={<ProtectedRoute module="pos" permission="pos.discount_presets"><DiscountPresetsPage /></ProtectedRoute>} />
        <Route path="/sales" element={<ProtectedRoute module="sales" permission="sales.view"><SalesPage /></ProtectedRoute>} />
        <Route path="/inventory" element={<ProtectedRoute module="inventory" permission="inventory.view"><InventoryPage /></ProtectedRoute>} />
        <Route path="/inventory/movements" element={<ProtectedRoute module="inventory" permission="inventory.view"><MovementsPage /></ProtectedRoute>} />
        <Route path="/inventory/transfer" element={<ProtectedRoute module="inventory" permission="inventory.manage"><TransferPage /></ProtectedRoute>} />
        <Route path="/inventory/transfer-requests" element={<ProtectedRoute module="inventory" permission="inventory.view"><TransferRequestsPage /></ProtectedRoute>} />
        <Route path="/inventory/stocktake" element={<ProtectedRoute module="inventory" permission="inventory.stocktake"><StocktakePage /></ProtectedRoute>} />
        <Route path="/inventory/valuation" element={<ProtectedRoute module="inventory" permission="inventory.valuation"><ValuationReportPage /></ProtectedRoute>} />
        <Route path="/inventory/adjustment-reasons" element={<ProtectedRoute module="inventory" permission="inventory.view"><AdjustmentReasonsPage /></ProtectedRoute>} />
        <Route path="/warehouses" element={<ProtectedRoute module="warehouse" permission="warehouse.view"><WarehousesPage /></ProtectedRoute>} />
        <Route path="/settings/store" element={<ProtectedRoute module="settings" permission="settings.manage"><StoreSettingsPage /></ProtectedRoute>} />
        <Route path="/settings/receipt" element={<ProtectedRoute module="settings" permission="settings.manage"><ReceiptSettingsPage /></ProtectedRoute>} />
        <Route path="/settings/account" element={<ProtectedRoute><AccountSettingsPage /></ProtectedRoute>} />
        <Route path="/reports" element={<ProtectedRoute module="reports" permission="reports.view"><ReportsPage /></ProtectedRoute>} />
        <Route path="/reports/:reportId" element={<ProtectedRoute module="reports" permission="reports.view"><ReportsPage /></ProtectedRoute>} />
        <Route path="/tables" element={<ProtectedRoute module="tables" permission="tables.view"><TablesPage /></ProtectedRoute>} />
        <Route path="/reservations" element={<ProtectedRoute module="reservations" permission="reservations.view"><div className="p-6"><h1 className="text-2xl font-bold">Reservations</h1></div></ProtectedRoute>} />
        <Route path="/promotions" element={<ProtectedRoute module="promotions" permission="promotions.view"><PromotionsPage /></ProtectedRoute>} />
        <Route path="/loyalty-programs" element={<ProtectedRoute module="loyalty" permission="loyalty.view"><div className="p-6"><h1 className="text-2xl font-bold">Loyalty Programs</h1></div></ProtectedRoute>} />
        <Route path="/appointments" element={<ProtectedRoute module="appointments" permission="appointments.view"><AppointmentsPage /></ProtectedRoute>} />
        <Route path="/services" element={<ProtectedRoute module="services" permission="services.view"><div className="p-6"><h1 className="text-2xl font-bold">Service Catalog</h1></div></ProtectedRoute>} />
        <Route path="/integrations" element={<ProtectedRoute module="integrations" permission="integrations.view"><IntegrationsPage /></ProtectedRoute>} />
        <Route path="/webhooks" element={<ProtectedRoute module="integrations" permission="webhooks.view"><WebhooksPage /></ProtectedRoute>} />
        <Route path="/api-keys" element={<ProtectedRoute module="integrations" permission="apikeys.view"><ApiKeysPage /></ProtectedRoute>} />
      <Route path="/settings/security" element={<ProtectedRoute><SecuritySettingsPage /></ProtectedRoute>} />
      <Route path="/settings/audit-logs" element={<ProtectedRoute module="audit" permission="audit.view"><AuditLogsPage /></ProtectedRoute>} />
      <Route path="/settings/privacy" element={<ProtectedRoute><PrivacySettingsPage /></ProtectedRoute>} />
        <Route path="*" element={<Navigate to="/dashboard" replace />} />
      </Routes>
    </BrowserRouter>
  )
}

export default App
