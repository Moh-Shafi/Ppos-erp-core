<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AdjustmentReasonController;
use App\Http\Controllers\AutoReorderController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\BillSplitController;
use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\BusinessTypeController;
use App\Http\Controllers\CashDrawerController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerCreditController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscountPresetController;
use App\Http\Controllers\GrnController;
use App\Http\Controllers\HeldSaleController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryReportController;
use App\Http\Controllers\KotController;
use App\Http\Controllers\LoyaltyController;
use App\Http\Controllers\LoyaltyProgramController;
use App\Http\Controllers\ModifierController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\PriceListController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImportExportController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\RequisitionController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ServiceCatalogController;
use App\Http\Controllers\StaffScheduleController;
use App\Http\Controllers\StockBatchController;
use App\Http\Controllers\StocktakeController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierInvoiceController;
use App\Http\Controllers\SupplierRatingController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantModuleController;
use App\Http\Controllers\TransferRequestController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\DashboardWidgetController;
use App\Http\Controllers\ReportConfigController;
use App\Http\Controllers\XenditWebhookController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\IntegrationApiController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\AdminSecurityController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\AccountController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth routes (public, rate limited)
    Route::middleware(['throttle:auth'])->prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login'])->middleware('lockout');
        Route::post('login-2fa', [TwoFactorController::class, 'loginWith2fa']);
    });

    // Health check (public, rate limited)
    Route::get('health', [HealthController::class, 'check'])->middleware('throttle:health');

    // OpenAPI documentation (public)
    Route::get('openapi.json', [\App\Http\Controllers\OpenApiController::class, 'spec']);

    // Business types (public — for registration dropdown)
    Route::get('business-types', [BusinessTypeController::class, 'index']);

    // Xendit webhook (public — verified by callback token)
    Route::post('webhooks/xendit', [XenditWebhookController::class, 'handle']);

    // Protected routes (rate limited)
    Route::middleware(['throttle:api', 'auth:sanctum'])->group(function () {
        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);

            // 2FA Management
            Route::prefix('2fa')->middleware('feature:2fa')->group(function () {
                Route::post('enable', [TwoFactorController::class, 'enable']);
                Route::post('verify', [TwoFactorController::class, 'verify']);
                Route::post('disable', [TwoFactorController::class, 'disable']);
                Route::get('status', [TwoFactorController::class, 'status']);
                Route::post('backup-codes', [TwoFactorController::class, 'regenerateBackupCodes']);
            });
        });

        // Admin Security
        Route::prefix('admin')->middleware('permission:owner')->group(function () {
            Route::post('users/{id}/unlock', [AdminSecurityController::class, 'unlockUser'])
                ->where('id', '[0-9]+');
            Route::post('users/{id}/reset-2fa', [AdminSecurityController::class, 'reset2fa'])
                ->where('id', '[0-9]+');
        });

        // Account / PDP Compliance
        Route::prefix('account')->group(function () {
            Route::get('export', [AccountController::class, 'export']);
            Route::delete('/', [AccountController::class, 'delete']);
            Route::get('consent', [AccountController::class, 'consent']);
        });

        // Tenant
        Route::get('tenant', [TenantController::class, 'show']);
        Route::put('tenant', [TenantController::class, 'update']);

        // Business Profile
        Route::get('tenant/business-profile', [BusinessProfileController::class, 'show']);
        Route::put('tenant/business-profile', [BusinessProfileController::class, 'update']);

        // Tenant Modules & Features
        Route::get('tenant/modules', [TenantModuleController::class, 'index']);
        Route::put('tenant/modules/{moduleId}', [TenantModuleController::class, 'toggleModule'])
            ->where('moduleId', '[0-9]+');
        Route::put('tenant/features/{featureId}', [TenantModuleController::class, 'toggleFeature'])
            ->where('featureId', '[0-9]+');

        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index']);

        // Audit Logs (Owner only)
        Route::get('audit-logs', [AuditLogController::class, 'index'])
            ->middleware('permission:audit.view');
        Route::get('audit-logs/export', [AuditLogController::class, 'exportCsv'])
            ->middleware('permission:audit.view');

        // Stores
        Route::apiResource('stores', StoreController::class);

        // Categories
        Route::prefix('categories')->group(function () {
            Route::get('/', [CategoryController::class, 'index'])
                ->middleware('permission:categories.view');
            Route::get('/tree', [CategoryController::class, 'tree'])
                ->middleware('permission:categories.view');
            Route::get('/{id}', [CategoryController::class, 'show'])
                ->middleware('permission:categories.view')
                ->where('id', '[0-9]+');
            Route::post('/', [CategoryController::class, 'store'])
                ->middleware('permission:categories.manage');
            Route::put('/{id}', [CategoryController::class, 'update'])
                ->middleware('permission:categories.manage')
                ->where('id', '[0-9]+');
            Route::delete('/{id}', [CategoryController::class, 'destroy'])
                ->middleware('permission:categories.manage')
                ->where('id', '[0-9]+');
        });

        // Products
        Route::prefix('products')->group(function () {
            Route::get('/', [ProductController::class, 'index'])
                ->middleware('permission:products.view');
            Route::post('/upload-image', [ProductController::class, 'uploadImage'])
                ->middleware('permission:products.manage');
            Route::get('/export', [ProductImportExportController::class, 'export'])
                ->middleware('permission:products.view');
            Route::get('/lookup', [ProductImportExportController::class, 'lookup'])
                ->middleware('permission:products.view');
            Route::post('/import', [ProductImportExportController::class, 'import'])
                ->middleware('permission:products.manage');
            Route::get('/{id}', [ProductController::class, 'show'])
                ->middleware('permission:products.view')
                ->where('id', '[0-9]+');
            Route::post('/', [ProductController::class, 'store'])
                ->middleware('permission:products.manage');
            Route::put('/{id}', [ProductController::class, 'update'])
                ->middleware('permission:products.manage')
                ->where('id', '[0-9]+');
            Route::delete('/{id}', [ProductController::class, 'destroy'])
                ->middleware('permission:products.manage')
                ->where('id', '[0-9]+');
            Route::post('/{id}/variants/generate', [ProductImportExportController::class, 'generateVariants'])
                ->middleware('permission:products.manage')
                ->where('id', '[0-9]+');
        });

        // Units
        Route::prefix('units')->group(function () {
            Route::get('/', [UnitController::class, 'index'])
                ->middleware('permission:products.view');
            Route::post('/', [UnitController::class, 'store'])
                ->middleware('permission:products.manage');
            Route::get('/{id}', [UnitController::class, 'show'])
                ->middleware('permission:products.view')
                ->where('id', '[0-9]+');
            Route::put('/{id}', [UnitController::class, 'update'])
                ->middleware('permission:products.manage')
                ->where('id', '[0-9]+');
            Route::delete('/{id}', [UnitController::class, 'destroy'])
                ->middleware('permission:products.manage')
                ->where('id', '[0-9]+');
            Route::post('/conversions', [UnitController::class, 'storeConversion'])
                ->middleware('permission:products.manage');
            Route::delete('/conversions/{id}', [UnitController::class, 'destroyConversion'])
                ->middleware('permission:products.manage')
                ->where('id', '[0-9]+');
        });

        // Price Lists
        Route::prefix('price-lists')->group(function () {
            Route::get('/', [PriceListController::class, 'index'])
                ->middleware('permission:products.view');
            Route::get('/{id}', [PriceListController::class, 'show'])
                ->middleware('permission:products.view')
                ->where('id', '[0-9]+');
            Route::post('/', [PriceListController::class, 'store'])
                ->middleware('permission:products.manage');
            Route::put('/{id}', [PriceListController::class, 'update'])
                ->middleware('permission:products.manage')
                ->where('id', '[0-9]+');
            Route::delete('/{id}', [PriceListController::class, 'destroy'])
                ->middleware('permission:products.manage')
                ->where('id', '[0-9]+');
            Route::post('/{id}/items', [PriceListController::class, 'storeItem'])
                ->middleware('permission:products.manage')
                ->where('id', '[0-9]+');
            Route::put('/{id}/items/{itemId}', [PriceListController::class, 'updateItem'])
                ->middleware('permission:products.manage')
                ->where('id', '[0-9]+')
                ->where('itemId', '[0-9]+');
            Route::delete('/{id}/items/{itemId}', [PriceListController::class, 'destroyItem'])
                ->middleware('permission:products.manage')
                ->where('id', '[0-9]+')
                ->where('itemId', '[0-9]+');
        });

        // Customers
        Route::prefix('customers')->group(function () {
            Route::get('/', [CustomerController::class, 'index'])
                ->middleware('permission:customers.view');
            Route::get('/{id}', [CustomerController::class, 'show'])
                ->middleware('permission:customers.view')
                ->where('id', '[0-9]+');
            Route::post('/', [CustomerController::class, 'store'])
                ->middleware('permission:customers.manage');
            Route::put('/{id}', [CustomerController::class, 'update'])
                ->middleware('permission:customers.manage')
                ->where('id', '[0-9]+');
            Route::delete('/{id}', [CustomerController::class, 'destroy'])
                ->middleware('permission:customers.manage')
                ->where('id', '[0-9]+');
        });

        // Suppliers
        Route::prefix('suppliers')->group(function () {
            Route::get('/', [SupplierController::class, 'index'])
                ->middleware('permission:suppliers.view');
            Route::get('/{id}', [SupplierController::class, 'show'])
                ->middleware('permission:suppliers.view')
                ->where('id', '[0-9]+');
            Route::post('/', [SupplierController::class, 'store'])
                ->middleware('permission:suppliers.manage');
            Route::put('/{id}', [SupplierController::class, 'update'])
                ->middleware('permission:suppliers.manage')
                ->where('id', '[0-9]+');
            Route::delete('/{id}', [SupplierController::class, 'destroy'])
                ->middleware('permission:suppliers.manage')
                ->where('id', '[0-9]+');
        });

        // Purchases
        Route::prefix('purchases')->group(function () {
            Route::get('/', [PurchaseController::class, 'index'])
                ->middleware('permission:purchases.view');
            Route::get('/{id}', [PurchaseController::class, 'show'])
                ->middleware('permission:purchases.view')
                ->where('id', '[0-9]+');
            Route::post('/', [PurchaseController::class, 'store'])
                ->middleware('permission:purchases.manage');
            Route::put('/{id}', [PurchaseController::class, 'update'])
                ->middleware('permission:purchases.manage')
                ->where('id', '[0-9]+');
            Route::delete('/{id}', [PurchaseController::class, 'destroy'])
                ->middleware('permission:purchases.manage')
                ->where('id', '[0-9]+');
            Route::post('/{id}/order', [PurchaseController::class, 'order'])
                ->middleware('permission:purchases.manage')
                ->where('id', '[0-9]+');
            Route::post('/{id}/receive', [PurchaseController::class, 'receive'])
                ->middleware('permission:purchases.manage')
                ->where('id', '[0-9]+');
            Route::post('/{id}/cancel', [PurchaseController::class, 'cancel'])
                ->middleware('permission:purchases.manage')
                ->where('id', '[0-9]+');
        });

        // Purchase Returns
        Route::prefix('purchase-returns')->group(function () {
            Route::get('/', [PurchaseReturnController::class, 'index'])
                ->middleware('permission:purchases.view');
            Route::get('/{id}', [PurchaseReturnController::class, 'show'])
                ->middleware('permission:purchases.view')
                ->where('id', '[0-9]+');
            Route::post('/', [PurchaseReturnController::class, 'store'])
                ->middleware('permission:purchases.manage');
            Route::post('/{id}/complete', [PurchaseReturnController::class, 'complete'])
                ->middleware('permission:purchases.manage')
                ->where('id', '[0-9]+');
            Route::post('/{id}/cancel', [PurchaseReturnController::class, 'cancel'])
                ->middleware('permission:purchases.manage')
                ->where('id', '[0-9]+');
            Route::delete('/{id}', [PurchaseReturnController::class, 'destroy'])
                ->middleware('permission:purchases.manage')
                ->where('id', '[0-9]+');
        });

        // Customer Loyalty (feature-flagged)
        Route::middleware('feature:customers.loyalty_points')->prefix('customers')->group(function () {
            Route::get('/{id}/loyalty', [LoyaltyController::class, 'balance'])
                ->middleware('permission:customers.view')
                ->where('id', '[0-9]+');
            Route::get('/{id}/loyalty/transactions', [LoyaltyController::class, 'transactions'])
                ->middleware('permission:customers.view')
                ->where('id', '[0-9]+');
            Route::post('/{id}/loyalty/adjust', [LoyaltyController::class, 'adjust'])
                ->middleware('permission:crm.manage')
                ->where('id', '[0-9]+');
        });

        // Customer Credit (feature-flagged)
        Route::middleware('feature:sales.customer_credit')->prefix('customers')->group(function () {
            Route::get('/{id}/credit', [CustomerCreditController::class, 'balance'])
                ->middleware('permission:customers.view')
                ->where('id', '[0-9]+');
            Route::get('/{id}/credit/transactions', [CustomerCreditController::class, 'transactions'])
                ->middleware('permission:customers.view')
                ->where('id', '[0-9]+');
            Route::post('/{id}/credit/adjust', [CustomerCreditController::class, 'adjust'])
                ->middleware('permission:crm.manage')
                ->where('id', '[0-9]+');
            Route::post('/{id}/credit/check', [CustomerCreditController::class, 'check'])
                ->middleware('permission:customers.view')
                ->where('id', '[0-9]+');
        });

        // Supplier Ratings
        Route::prefix('suppliers')->group(function () {
            Route::get('/{id}/ratings', [SupplierRatingController::class, 'index'])
                ->middleware('permission:suppliers.view')
                ->where('id', '[0-9]+');
            Route::post('/{id}/ratings', [SupplierRatingController::class, 'store'])
                ->middleware('permission:suppliers.manage')
                ->where('id', '[0-9]+');
            Route::put('/{id}/ratings/{ratingId}', [SupplierRatingController::class, 'update'])
                ->middleware('permission:suppliers.manage')
                ->where('id', '[0-9]+')
                ->where('ratingId', '[0-9]+');
            Route::delete('/{id}/ratings/{ratingId}', [SupplierRatingController::class, 'destroy'])
                ->middleware('permission:suppliers.manage')
                ->where('id', '[0-9]+')
                ->where('ratingId', '[0-9]+');
        });

        // Purchase Requisitions (feature-flagged)
        Route::middleware('feature:purchasing.requisition')->prefix('requisitions')->group(function () {
            Route::get('/', [RequisitionController::class, 'index'])
                ->middleware('permission:purchases.view');
            Route::get('/{id}', [RequisitionController::class, 'show'])
                ->middleware('permission:purchases.view')
                ->where('id', '[0-9]+');
            Route::post('/', [RequisitionController::class, 'store'])
                ->middleware('permission:purchases.manage');
            Route::put('/{id}', [RequisitionController::class, 'update'])
                ->middleware('permission:purchases.manage')
                ->where('id', '[0-9]+');
            Route::delete('/{id}', [RequisitionController::class, 'destroy'])
                ->middleware('permission:purchases.manage')
                ->where('id', '[0-9]+');
            Route::post('/{id}/submit', [RequisitionController::class, 'submit'])
                ->middleware('permission:purchases.manage')
                ->where('id', '[0-9]+');
            Route::post('/{id}/approve', [RequisitionController::class, 'approve'])
                ->middleware('permission:purchasing.requisition')
                ->where('id', '[0-9]+');
            Route::post('/{id}/reject', [RequisitionController::class, 'reject'])
                ->middleware('permission:purchasing.requisition')
                ->where('id', '[0-9]+');
            Route::post('/{id}/cancel', [RequisitionController::class, 'cancel'])
                ->middleware('permission:purchases.manage')
                ->where('id', '[0-9]+');
            Route::post('/{id}/convert', [RequisitionController::class, 'convert'])
                ->middleware('permission:purchases.manage')
                ->where('id', '[0-9]+');
        });

        // Goods Receipt Notes
        Route::prefix('grns')->group(function () {
            Route::get('/', [GrnController::class, 'index'])
                ->middleware('permission:purchases.view');
            Route::get('/{id}', [GrnController::class, 'show'])
                ->middleware('permission:purchases.view')
                ->where('id', '[0-9]+');
            Route::post('/', [GrnController::class, 'store'])
                ->middleware('permission:purchasing.grn');
            Route::post('/from-po/{poId}', [GrnController::class, 'createFromPo'])
                ->middleware('permission:purchasing.grn')
                ->where('poId', '[0-9]+');
            Route::post('/{id}/receive', [GrnController::class, 'receive'])
                ->middleware('permission:purchasing.grn')
                ->where('id', '[0-9]+');
            Route::post('/{id}/cancel', [GrnController::class, 'cancel'])
                ->middleware('permission:purchasing.grn')
                ->where('id', '[0-9]+');
        });

        // Supplier Invoices / 3-Way Matching (feature-flagged)
        Route::middleware('feature:purchasing.invoice_matching')->prefix('supplier-invoices')->group(function () {
            Route::get('/', [SupplierInvoiceController::class, 'index'])
                ->middleware('permission:purchases.view');
            Route::get('/{id}', [SupplierInvoiceController::class, 'show'])
                ->middleware('permission:purchases.view')
                ->where('id', '[0-9]+');
            Route::post('/', [SupplierInvoiceController::class, 'store'])
                ->middleware('permission:purchasing.invoice_match');
            Route::post('/{id}/match', [SupplierInvoiceController::class, 'match'])
                ->middleware('permission:purchasing.invoice_match')
                ->where('id', '[0-9]+');
            Route::post('/{id}/approve', [SupplierInvoiceController::class, 'approve'])
                ->middleware('permission:purchasing.invoice_match')
                ->where('id', '[0-9]+');
            Route::post('/{id}/reject', [SupplierInvoiceController::class, 'reject'])
                ->middleware('permission:purchasing.invoice_match')
                ->where('id', '[0-9]+');
        });

        // Auto-Reorder
        Route::prefix('auto-reorder')->group(function () {
            Route::get('/report', [AutoReorderController::class, 'report'])
                ->middleware('permission:purchases.view');
            Route::post('/generate', [AutoReorderController::class, 'generate'])
                ->middleware('permission:purchases.manage');
        });

        // Sales / POS
        Route::prefix('sales')->group(function () {
            Route::get('/', [SaleController::class, 'index'])
                ->middleware('permission:sales.view');
            Route::get('/{id}', [SaleController::class, 'show'])
                ->middleware('permission:sales.view')
                ->where('id', '[0-9]+');
            Route::post('/checkout', [SaleController::class, 'checkout'])
                ->middleware('permission:sales.manage');
            Route::post('/{id}/cancel', [SaleController::class, 'cancel'])
                ->middleware('permission:sales.manage')
                ->where('id', '[0-9]+');
            Route::get('/{id}/payments', [SaleController::class, 'listPayments'])
                ->middleware('permission:sales.view')
                ->where('id', '[0-9]+');
            Route::post('/{id}/payments', [SaleController::class, 'addPayment'])
                ->middleware('permission:sales.manage')
                ->where('id', '[0-9]+');

            // Refunds (feature-flagged)
            Route::middleware('feature:pos.refund')->group(function () {
                Route::get('/{id}/refunds', [SaleController::class, 'listRefunds'])
                    ->middleware('permission:sales.view')
                    ->where('id', '[0-9]+');
                Route::get('/{id}/refunds/{refundId}', [SaleController::class, 'showRefund'])
                    ->middleware('permission:sales.view')
                    ->where('id', '[0-9]+')
                    ->where('refundId', '[0-9]+');
                Route::post('/{id}/refunds', [SaleController::class, 'processRefund'])
                    ->middleware('permission:pos.refund')
                    ->where('id', '[0-9]+');
            });

            // Gateway charges (QRIS / card / bank transfer)
            Route::middleware('feature:payment.gateway_qris')->post('/{id}/gateway-charge', [PaymentGatewayController::class, 'createCharge'])
                ->middleware('permission:payments.manage')
                ->where('id', '[0-9]+');
            Route::get('/{id}/gateway-charge/{chargeId}', [PaymentGatewayController::class, 'getChargeStatus'])
                ->middleware('permission:payments.view')
                ->where('id', '[0-9]+')
                ->where('chargeId', '[0-9]+');
        });

        // Held Sales (feature-flagged)
        Route::middleware('feature:pos.hold_sale')->prefix('held-sales')->group(function () {
            Route::get('/', [HeldSaleController::class, 'index'])
                ->middleware('permission:sales.view');
            Route::post('/', [HeldSaleController::class, 'store'])
                ->middleware('permission:sales.manage');
            Route::post('/{id}/recall', [HeldSaleController::class, 'recall'])
                ->middleware('permission:sales.manage')
                ->where('id', '[0-9]+');
            Route::delete('/{id}', [HeldSaleController::class, 'destroy'])
                ->middleware('permission:sales.manage')
                ->where('id', '[0-9]+');
        });

        // Discount Presets (feature-flagged)
        Route::middleware('feature:pos.discount_presets')->prefix('discount-presets')->group(function () {
            Route::get('/', [DiscountPresetController::class, 'index'])
                ->middleware('permission:sales.view');
            Route::post('/', [DiscountPresetController::class, 'store'])
                ->middleware('permission:pos.discount_presets');
            Route::put('/{id}', [DiscountPresetController::class, 'update'])
                ->middleware('permission:pos.discount_presets')
                ->where('id', '[0-9]+');
            Route::delete('/{id}', [DiscountPresetController::class, 'destroy'])
                ->middleware('permission:pos.discount_presets')
                ->where('id', '[0-9]+');
        });

        // Store Receipt Settings
        Route::prefix('stores')->group(function () {
            Route::get('/{id}/receipt-settings', [StoreController::class, 'getReceiptSettings'])
                ->middleware('permission:settings.manage')
                ->where('id', '[0-9]+');
            Route::put('/{id}/receipt-settings', [StoreController::class, 'updateReceiptSettings'])
                ->middleware('permission:settings.manage')
                ->where('id', '[0-9]+');
        });

        // Payment Gateway (Phase 5)
        Route::prefix('payment-gateway')->group(function () {
            Route::get('/account', [PaymentGatewayController::class, 'account'])
                ->middleware('permission:payments.view');
            Route::post('/provision', [PaymentGatewayController::class, 'provision'])
                ->middleware('permission:payments.gateway_config');
            Route::get('/settlements', [PaymentGatewayController::class, 'settlements'])
                ->middleware('permission:payments.reconcile');
            Route::post('/reconcile', [PaymentGatewayController::class, 'reconcile'])
                ->middleware('permission:payments.reconcile');
        });

        // Payment Dashboard (Phase 5)
        Route::prefix('payments')->group(function () {
            Route::get('/', [PaymentController::class, 'index'])
                ->middleware('permission:payments.view');
            Route::get('/summary', [PaymentController::class, 'summary'])
                ->middleware('permission:payments.view');
            Route::get('/{id}', [PaymentController::class, 'show'])
                ->middleware('permission:payments.view')
                ->where('id', '[0-9]+');
            Route::post('/{id}/refund', [PaymentGatewayController::class, 'refund'])
                ->middleware('permission:payments.refund')
                ->where('id', '[0-9]+');
        });

        // Cash Drawer (Phase 5)
        Route::prefix('cash-drawer')->middleware('feature:payment.cash_drawer')->group(function () {
            Route::get('/sessions', [CashDrawerController::class, 'index'])
                ->middleware('permission:payments.cash_drawer');
            Route::post('/open', [CashDrawerController::class, 'open'])
                ->middleware('permission:payments.cash_drawer');
            Route::get('/{id}', [CashDrawerController::class, 'show'])
                ->middleware('permission:payments.cash_drawer')
                ->where('id', '[0-9]+');
            Route::post('/{id}/close', [CashDrawerController::class, 'close'])
                ->middleware('permission:payments.cash_drawer')
                ->where('id', '[0-9]+');
            Route::post('/{id}/reconcile', [CashDrawerController::class, 'reconcile'])
                ->middleware('permission:payments.reconcile')
                ->where('id', '[0-9]+');
        });

        // Inventory
        Route::prefix('inventory')->group(function () {
            Route::get('/', [InventoryController::class, 'index'])
                ->middleware('permission:inventory.view');
            Route::get('/movements', [InventoryController::class, 'movements'])
                ->middleware('permission:inventory.view');
            Route::get('/{productId}', [InventoryController::class, 'show'])
                ->middleware('permission:inventory.view')
                ->where('productId', '[0-9]+');
            Route::post('/adjust', [InventoryController::class, 'adjust'])
                ->middleware('permission:inventory.manage');
            Route::post('/transfer', [InventoryController::class, 'transfer'])
                ->middleware('permission:inventory.manage');

            // Inventory Reports
            Route::prefix('reports')->group(function () {
                Route::get('/summary', [InventoryReportController::class, 'summary'])
                    ->middleware('permission:inventory.view');
                Route::get('/valuation', [InventoryReportController::class, 'valuation'])
                    ->middleware(['permission:inventory.view', 'feature:inventory.valuation']);
                Route::get('/low-stock', [InventoryReportController::class, 'lowStock'])
                    ->middleware('permission:inventory.view');
                Route::get('/expiry', [InventoryReportController::class, 'expiry'])
                    ->middleware(['permission:inventory.view', 'feature:inventory.expiry_tracking']);
                Route::get('/movements', [InventoryReportController::class, 'movements'])
                    ->middleware('permission:inventory.view');
            });
        });

        // Warehouses
        Route::prefix('warehouses')->group(function () {
            Route::get('/', [WarehouseController::class, 'index'])
                ->middleware('permission:warehouse.view');
            Route::get('/{id}', [WarehouseController::class, 'show'])
                ->middleware('permission:warehouse.view')
                ->where('id', '[0-9]+');
            Route::post('/', [WarehouseController::class, 'store'])
                ->middleware('permission:warehouse.manage');
            Route::put('/{id}', [WarehouseController::class, 'update'])
                ->middleware('permission:warehouse.manage')
                ->where('id', '[0-9]+');
            Route::delete('/{id}', [WarehouseController::class, 'destroy'])
                ->middleware('permission:warehouse.manage')
                ->where('id', '[0-9]+');
            Route::get('/{id}/stock', [WarehouseController::class, 'stock'])
                ->middleware('permission:warehouse.view')
                ->where('id', '[0-9]+');
            Route::post('/{id}/adjust', [WarehouseController::class, 'adjustStock'])
                ->middleware('permission:warehouse.manage')
                ->where('id', '[0-9]+');
        });

        // Stock Batches (feature-flagged)
        Route::prefix('products/{productId}/batches')->group(function () {
            Route::get('/', [StockBatchController::class, 'index'])
                ->middleware(['permission:inventory.view', 'feature:inventory.batch_tracking']);
            Route::post('/', [StockBatchController::class, 'store'])
                ->middleware(['permission:inventory.manage', 'feature:inventory.batch_tracking']);
        })->where('productId', '[0-9]+');

        Route::get('/batches/{id}', [StockBatchController::class, 'show'])
            ->middleware(['permission:inventory.view', 'feature:inventory.batch_tracking'])
            ->where('id', '[0-9]+');

        // Stocktake (feature-flagged)
        Route::prefix('stocktake')->group(function () {
            Route::get('/', [StocktakeController::class, 'index'])
                ->middleware(['permission:inventory.view', 'feature:inventory.stocktake']);
            Route::get('/{id}', [StocktakeController::class, 'show'])
                ->middleware(['permission:inventory.view', 'feature:inventory.stocktake'])
                ->where('id', '[0-9]+');
            Route::post('/', [StocktakeController::class, 'store'])
                ->middleware(['permission:inventory.stocktake', 'feature:inventory.stocktake']);
            Route::post('/{id}/start', [StocktakeController::class, 'start'])
                ->middleware(['permission:inventory.stocktake', 'feature:inventory.stocktake'])
                ->where('id', '[0-9]+');
            Route::put('/{id}/items/{itemId}', [StocktakeController::class, 'updateItem'])
                ->middleware(['permission:inventory.stocktake', 'feature:inventory.stocktake'])
                ->where(['id' => '[0-9]+', 'itemId' => '[0-9]+']);
            Route::post('/{id}/reconcile', [StocktakeController::class, 'reconcile'])
                ->middleware(['permission:inventory.stocktake', 'feature:inventory.stocktake'])
                ->where('id', '[0-9]+');
            Route::post('/{id}/post', [StocktakeController::class, 'post'])
                ->middleware(['permission:inventory.stocktake', 'feature:inventory.stocktake'])
                ->where('id', '[0-9]+');
            Route::post('/{id}/cancel', [StocktakeController::class, 'cancel'])
                ->middleware(['permission:inventory.stocktake', 'feature:inventory.stocktake'])
                ->where('id', '[0-9]+');
        });

        // Transfer Requests (feature-flagged)
        Route::prefix('transfer-requests')->group(function () {
            Route::get('/', [TransferRequestController::class, 'index'])
                ->middleware(['permission:inventory.view', 'feature:inventory.transfer_request']);
            Route::get('/{id}', [TransferRequestController::class, 'show'])
                ->middleware(['permission:inventory.view', 'feature:inventory.transfer_request'])
                ->where('id', '[0-9]+');
            Route::post('/', [TransferRequestController::class, 'store'])
                ->middleware(['permission:inventory.manage', 'feature:inventory.transfer_request']);
            Route::post('/{id}/submit', [TransferRequestController::class, 'submit'])
                ->middleware(['permission:inventory.manage', 'feature:inventory.transfer_request'])
                ->where('id', '[0-9]+');
            Route::post('/{id}/approve', [TransferRequestController::class, 'approve'])
                ->middleware(['permission:inventory.manage', 'feature:inventory.transfer_request'])
                ->where('id', '[0-9]+');
            Route::post('/{id}/reject', [TransferRequestController::class, 'reject'])
                ->middleware(['permission:inventory.manage', 'feature:inventory.transfer_request'])
                ->where('id', '[0-9]+');
            Route::post('/{id}/transit', [TransferRequestController::class, 'transit'])
                ->middleware(['permission:inventory.manage', 'feature:inventory.transfer_request'])
                ->where('id', '[0-9]+');
            Route::post('/{id}/complete', [TransferRequestController::class, 'complete'])
                ->middleware(['permission:inventory.manage', 'feature:inventory.transfer_request'])
                ->where('id', '[0-9]+');
            Route::post('/{id}/cancel', [TransferRequestController::class, 'cancel'])
                ->middleware(['permission:inventory.manage', 'feature:inventory.transfer_request'])
                ->where('id', '[0-9]+');
        });

        // Stock Adjustment Reasons
        Route::prefix('adjustment-reasons')->group(function () {
            Route::get('/', [AdjustmentReasonController::class, 'index'])
                ->middleware('permission:inventory.view');
            Route::post('/', [AdjustmentReasonController::class, 'store'])
                ->middleware('permission:inventory.manage');
            Route::put('/{id}', [AdjustmentReasonController::class, 'update'])
                ->middleware('permission:inventory.manage')
                ->where('id', '[0-9]+');
            Route::delete('/{id}', [AdjustmentReasonController::class, 'destroy'])
                ->middleware('permission:inventory.manage')
                ->where('id', '[0-9]+');
        });

        // Inventory Settings
        Route::prefix('inventory/settings')->group(function () {
            Route::get('/', [InventoryController::class, 'getSettings'])
                ->middleware('permission:inventory.view');
            Route::put('/', [InventoryController::class, 'updateSettings'])
                ->middleware('permission:inventory.manage');
        });

        // Finance / Accounting (Phase 6)
        Route::prefix('finance')->group(function () {
            Route::prefix('accounts')->group(function () {
                Route::get('/', [AccountingController::class, 'accounts'])
                    ->middleware('permission:finance.view');
                Route::post('/', [AccountingController::class, 'storeAccount'])
                    ->middleware('permission:finance.manage');
                Route::put('/{id}', [AccountingController::class, 'updateAccount'])
                    ->middleware('permission:finance.manage')
                    ->where('id', '[0-9]+');
                Route::delete('/{id}', [AccountingController::class, 'destroyAccount'])
                    ->middleware('permission:finance.manage')
                    ->where('id', '[0-9]+');
            });

            Route::get('/accounts/{id}/ledger', [AccountingController::class, 'ledger'])
                ->middleware('permission:finance.view')
                ->where('id', '[0-9]+');

            Route::prefix('journal-entries')->group(function () {
                Route::get('/', [AccountingController::class, 'journalEntries'])
                    ->middleware('permission:finance.view');
                Route::post('/', [AccountingController::class, 'storeJournalEntry'])
                    ->middleware('permission:finance.post_journals');
                Route::get('/{id}', [AccountingController::class, 'showJournalEntry'])
                    ->middleware('permission:finance.view')
                    ->where('id', '[0-9]+');
            });

            Route::prefix('fiscal-periods')->group(function () {
                Route::get('/', [AccountingController::class, 'fiscalPeriods'])
                    ->middleware('permission:finance.view');
                Route::post('/', [AccountingController::class, 'storeFiscalPeriod'])
                    ->middleware('permission:finance.manage');
                Route::post('/{id}/close', [AccountingController::class, 'closeFiscalPeriod'])
                    ->middleware('permission:finance.close_period')
                    ->where('id', '[0-9]+');
            });

            Route::prefix('reports')->group(function () {
                Route::get('/trial-balance', [AccountingController::class, 'trialBalance'])
                    ->middleware('permission:finance.reports');
                Route::get('/profit-loss', [AccountingController::class, 'profitAndLoss'])
                    ->middleware('permission:finance.reports');
                Route::get('/balance-sheet', [AccountingController::class, 'balanceSheet'])
                    ->middleware('permission:finance.reports');
            });
        });

        Route::prefix('reports')->group(function () {
            Route::get('/dashboard', [\App\Http\Controllers\ReportController::class, 'dashboard'])
                ->middleware('permission:reports.view');
            Route::get('/kpis', [\App\Http\Controllers\ReportController::class, 'kpis'])
                ->middleware('permission:reports.view');

            Route::middleware('permission:reports.dashboard.manage')->group(function () {
                Route::apiResource('dashboard/widgets', DashboardWidgetController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
                Route::apiResource('report-configs', ReportConfigController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
            });

            Route::get('/{report_id}', [\App\Http\Controllers\ReportController::class, 'report'])
                ->middleware('permission:reports.view')
                ->where('report_id', '[a-z0-9\-]+');
            Route::get('/{report_id}/drill-down', [\App\Http\Controllers\ReportController::class, 'drillDown'])
                ->middleware('permission:reports.view')
                ->where('report_id', '[a-z0-9\-]+');
            Route::post('/export', [\App\Http\Controllers\ReportController::class, 'export'])
                ->middleware('permission:reports.export');
        });

        // ===== Phase 8A — Restaurant =====

        Route::middleware('module:tables', 'permission:tables.view')->prefix('tables')->group(function () {
            Route::get('/', [TableController::class, 'index']);
            Route::post('/', [TableController::class, 'store'])->middleware('permission:tables.manage');
            Route::get('/areas', [TableController::class, 'areasIndex']);
            Route::post('/areas', [TableController::class, 'areasStore'])->middleware('permission:tables.manage');
            Route::put('/areas/{id}', [TableController::class, 'areasUpdate'])->middleware('permission:tables.manage');
            Route::delete('/areas/{id}', [TableController::class, 'areasDestroy'])->middleware('permission:tables.manage');
            Route::get('/{id}', [TableController::class, 'show'])->where('id', '[0-9]+');
            Route::put('/{id}', [TableController::class, 'update'])->middleware('permission:tables.manage')->where('id', '[0-9]+');
            Route::delete('/{id}', [TableController::class, 'destroy'])->middleware('permission:tables.manage')->where('id', '[0-9]+');
            Route::post('/{id}/status', [TableController::class, 'updateStatus'])->middleware('permission:tables.manage')->where('id', '[0-9]+');
            Route::post('/{id}/qr-code', [TableController::class, 'generateQrCode'])->middleware('permission:tables.manage', 'feature:tables.qr_ordering')->where('id', '[0-9]+');
        });

        Route::middleware('module:reservations', 'permission:reservations.view')->prefix('reservations')->group(function () {
            Route::get('/', [ReservationController::class, 'index']);
            Route::post('/', [ReservationController::class, 'store'])->middleware('permission:reservations.manage');
            Route::get('/availability', [ReservationController::class, 'availability']);
            Route::get('/{id}', [ReservationController::class, 'show'])->where('id', '[0-9]+');
            Route::put('/{id}', [ReservationController::class, 'update'])->middleware('permission:reservations.manage')->where('id', '[0-9]+');
            Route::post('/{id}/confirm', [ReservationController::class, 'confirm'])->middleware('permission:reservations.manage')->where('id', '[0-9]+');
            Route::post('/{id}/seat', [ReservationController::class, 'seat'])->middleware('permission:reservations.manage')->where('id', '[0-9]+');
            Route::post('/{id}/complete', [ReservationController::class, 'complete'])->middleware('permission:reservations.manage')->where('id', '[0-9]+');
            Route::post('/{id}/cancel', [ReservationController::class, 'cancel'])->middleware('permission:reservations.manage')->where('id', '[0-9]+');
        });

        Route::middleware('module:kitchen', 'permission:kitchen.view')->prefix('kot')->group(function () {
            Route::get('/', [KotController::class, 'index']);
            Route::get('/{id}', [KotController::class, 'show'])->where('id', '[0-9]+');
            Route::post('/{saleId}/generate', [KotController::class, 'generate'])->middleware('permission:kitchen.manage')->where('saleId', '[0-9]+');
            Route::post('/{id}/status', [KotController::class, 'updateStatus'])->middleware('permission:kitchen.manage')->where('id', '[0-9]+');
            Route::post('/items/{itemId}/status', [KotController::class, 'updateItemStatus'])->middleware('permission:kitchen.manage')->where('itemId', '[0-9]+');
        });

        Route::middleware('module:kds', 'permission:kds.view')->prefix('kds')->group(function () {
            Route::get('/queue', [KotController::class, 'kdsQueue']);
        });

        Route::middleware('module:kitchen', 'permission:modifiers.view')->prefix('modifiers')->group(function () {
            Route::get('/', [ModifierController::class, 'index']);
            Route::post('/', [ModifierController::class, 'store'])->middleware('permission:modifiers.manage');
            Route::get('/{id}', [ModifierController::class, 'show'])->where('id', '[0-9]+');
            Route::put('/{id}', [ModifierController::class, 'update'])->middleware('permission:modifiers.manage')->where('id', '[0-9]+');
            Route::delete('/{id}', [ModifierController::class, 'destroy'])->middleware('permission:modifiers.manage')->where('id', '[0-9]+');
            Route::post('/{id}/options', [ModifierController::class, 'storeOption'])->middleware('permission:modifiers.manage')->where('id', '[0-9]+');
            Route::put('/{id}/options/{optionId}', [ModifierController::class, 'updateOption'])->middleware('permission:modifiers.manage')->where('id', '[0-9]+')->where('optionId', '[0-9]+');
            Route::delete('/{id}/options/{optionId}', [ModifierController::class, 'destroyOption'])->middleware('permission:modifiers.manage')->where('id', '[0-9]+')->where('optionId', '[0-9]+');
        });

        Route::middleware('module:kitchen', 'permission:recipes.view')->prefix('recipes')->group(function () {
            Route::get('/', [RecipeController::class, 'index']);
            Route::post('/', [RecipeController::class, 'store'])->middleware('permission:recipes.manage');
            Route::get('/{id}', [RecipeController::class, 'show'])->where('id', '[0-9]+');
            Route::put('/{id}', [RecipeController::class, 'update'])->middleware('permission:recipes.manage')->where('id', '[0-9]+');
            Route::delete('/{id}', [RecipeController::class, 'destroy'])->middleware('permission:recipes.manage')->where('id', '[0-9]+');
        });

        Route::middleware('module:kitchen')->prefix('products')->group(function () {
            Route::get('/{id}/modifiers', [ModifierController::class, 'productModifiers'])->middleware('permission:modifiers.view')->where('id', '[0-9]+');
            Route::post('/{id}/modifiers', [ModifierController::class, 'attachToProduct'])->middleware('permission:modifiers.manage')->where('id', '[0-9]+');
            Route::delete('/{id}/modifiers/{modifierId}', [ModifierController::class, 'detachFromProduct'])->middleware('permission:modifiers.manage')->where('id', '[0-9]+')->where('modifierId', '[0-9]+');
        });

        Route::middleware('module:tables', 'permission:billsplit.view')->prefix('sales')->group(function () {
            Route::get('/{id}/splits', [BillSplitController::class, 'getSplits'])->where('id', '[0-9]+');
            Route::post('/{id}/split', [BillSplitController::class, 'split'])->middleware('permission:billsplit.manage')->where('id', '[0-9]+');
        });

        Route::middleware('module:tables', 'permission:billsplit.manage')->prefix('splits')->group(function () {
            Route::post('/{id}/pay', [BillSplitController::class, 'pay'])->where('id', '[0-9]+');
        });

        // ===== Phase 8B — Retail =====

        Route::middleware('module:promotions', 'permission:promotions.view')->prefix('promotions')->group(function () {
            Route::get('/', [PromotionController::class, 'index']);
            Route::post('/', [PromotionController::class, 'store'])->middleware('permission:promotions.manage');
            Route::post('/validate', [PromotionController::class, 'validateCart']);
            Route::get('/{id}', [PromotionController::class, 'show'])->where('id', '[0-9]+');
            Route::put('/{id}', [PromotionController::class, 'update'])->middleware('permission:promotions.manage')->where('id', '[0-9]+');
            Route::delete('/{id}', [PromotionController::class, 'destroy'])->middleware('permission:promotions.manage')->where('id', '[0-9]+');
            Route::post('/{id}/activate', [PromotionController::class, 'activate'])->middleware('permission:promotions.manage')->where('id', '[0-9]+');
            Route::post('/{id}/deactivate', [PromotionController::class, 'deactivate'])->middleware('permission:promotions.manage')->where('id', '[0-9]+');
        });

        Route::middleware('module:loyalty', 'permission:loyalty.view')->prefix('loyalty')->group(function () {
            Route::get('/programs', [LoyaltyProgramController::class, 'programsIndex']);
            Route::post('/programs', [LoyaltyProgramController::class, 'programsStore'])->middleware('permission:loyalty.manage');
            Route::put('/programs/{id}', [LoyaltyProgramController::class, 'programsUpdate'])->middleware('permission:loyalty.manage')->where('id', '[0-9]+');
            Route::get('/tiers', [LoyaltyProgramController::class, 'tiersIndex']);
            Route::post('/tiers', [LoyaltyProgramController::class, 'tiersStore'])->middleware('permission:loyalty.manage');
            Route::get('/customers/{customerId}/balance', [LoyaltyProgramController::class, 'balance'])->where('customerId', '[0-9]+');
            Route::get('/customers/{customerId}/transactions', [LoyaltyProgramController::class, 'transactions'])->where('customerId', '[0-9]+');
            Route::post('/redeem', [LoyaltyProgramController::class, 'redeem'])->middleware('permission:loyalty.manage');
        });

        // ===== Phase 8C — Service =====

        Route::middleware('module:appointments', 'permission:appointments.view')->prefix('appointments')->group(function () {
            Route::get('/', [AppointmentController::class, 'index']);
            Route::post('/', [AppointmentController::class, 'store'])->middleware('permission:appointments.manage');
            Route::get('/calendar', [AppointmentController::class, 'calendar']);
            Route::get('/{id}', [AppointmentController::class, 'show'])->where('id', '[0-9]+');
            Route::put('/{id}', [AppointmentController::class, 'update'])->middleware('permission:appointments.manage')->where('id', '[0-9]+');
            Route::post('/{id}/confirm', [AppointmentController::class, 'confirm'])->middleware('permission:appointments.manage')->where('id', '[0-9]+');
            Route::post('/{id}/start', [AppointmentController::class, 'start'])->middleware('permission:appointments.manage')->where('id', '[0-9]+');
            Route::post('/{id}/complete', [AppointmentController::class, 'complete'])->middleware('permission:appointments.manage')->where('id', '[0-9]+');
            Route::post('/{id}/cancel', [AppointmentController::class, 'cancel'])->middleware('permission:appointments.manage')->where('id', '[0-9]+');
        });

        Route::middleware('module:appointments', 'permission:services.view')->prefix('services')->group(function () {
            Route::get('/', [ServiceCatalogController::class, 'index']);
            Route::post('/', [ServiceCatalogController::class, 'store'])->middleware('permission:services.manage');
            Route::get('/{id}', [ServiceCatalogController::class, 'show'])->where('id', '[0-9]+');
            Route::put('/{id}', [ServiceCatalogController::class, 'update'])->middleware('permission:services.manage')->where('id', '[0-9]+');
            Route::delete('/{id}', [ServiceCatalogController::class, 'destroy'])->middleware('permission:services.manage')->where('id', '[0-9]+');
        });

        Route::middleware('module:appointments', 'permission:staff.schedule.view')->prefix('staff')->group(function () {
            Route::get('/schedules', [StaffScheduleController::class, 'index']);
            Route::post('/schedules', [StaffScheduleController::class, 'store'])->middleware('permission:staff.schedule.manage');
            Route::put('/schedules/{id}', [StaffScheduleController::class, 'update'])->middleware('permission:staff.schedule.manage')->where('id', '[0-9]+');
            Route::delete('/schedules/{id}', [StaffScheduleController::class, 'destroy'])->middleware('permission:staff.schedule.manage')->where('id', '[0-9]+');
            Route::get('/availability', [StaffScheduleController::class, 'availability']);
        });

        // ===== Phase 9 — Integration & Webhooks =====

        Route::middleware('module:integrations')->prefix('integrations')->group(function () {
            Route::get('/providers', [IntegrationController::class, 'providers'])
                ->middleware('permission:integrations.view');
            Route::get('/', [IntegrationController::class, 'index'])
                ->middleware('permission:integrations.view');
            Route::post('/', [IntegrationController::class, 'store'])
                ->middleware('permission:integrations.manage');
            Route::get('/{id}', [IntegrationController::class, 'show'])
                ->middleware('permission:integrations.view')
                ->where('id', '[0-9]+');
            Route::put('/{id}', [IntegrationController::class, 'update'])
                ->middleware('permission:integrations.manage')
                ->where('id', '[0-9]+');
            Route::put('/{id}/credentials', [IntegrationController::class, 'updateCredentials'])
                ->middleware('permission:integrations.manage')
                ->where('id', '[0-9]+');
            Route::delete('/{id}', [IntegrationController::class, 'destroy'])
                ->middleware('permission:integrations.manage')
                ->where('id', '[0-9]+');
            Route::post('/{id}/test', [IntegrationController::class, 'testConnection'])
                ->middleware('permission:integrations.manage')
                ->where('id', '[0-9]+');
            Route::post('/{id}/activate', [IntegrationController::class, 'activate'])
                ->middleware('permission:integrations.manage')
                ->where('id', '[0-9]+');
            Route::post('/{id}/deactivate', [IntegrationController::class, 'deactivate'])
                ->middleware('permission:integrations.manage')
                ->where('id', '[0-9]+');
            Route::get('/{id}/logs', [IntegrationController::class, 'logs'])
                ->middleware('permission:integrations.view')
                ->where('id', '[0-9]+');
            Route::get('/health', [IntegrationController::class, 'health'])
                ->middleware('permission:integrations.view');
        });

        Route::middleware('module:integrations')->prefix('webhooks')->group(function () {
            Route::get('/endpoints', [WebhookController::class, 'indexEndpoints'])
                ->middleware('permission:webhooks.view');
            Route::post('/endpoints', [WebhookController::class, 'storeEndpoint'])
                ->middleware('permission:webhooks.manage');
            Route::get('/endpoints/{id}', [WebhookController::class, 'showEndpoint'])
                ->middleware('permission:webhooks.view')
                ->where('id', '[0-9]+');
            Route::put('/endpoints/{id}', [WebhookController::class, 'updateEndpoint'])
                ->middleware('permission:webhooks.manage')
                ->where('id', '[0-9]+');
            Route::delete('/endpoints/{id}', [WebhookController::class, 'destroyEndpoint'])
                ->middleware('permission:webhooks.manage')
                ->where('id', '[0-9]+');
            Route::post('/endpoints/{id}/test', [WebhookController::class, 'testEndpoint'])
                ->middleware('permission:webhooks.manage')
                ->where('id', '[0-9]+');
            Route::post('/endpoints/{id}/subscriptions', [WebhookController::class, 'subscribe'])
                ->middleware('permission:webhooks.manage')
                ->where('id', '[0-9]+');
            Route::delete('/endpoints/{id}/subscriptions/{eventType}', [WebhookController::class, 'unsubscribe'])
                ->middleware('permission:webhooks.manage')
                ->where('id', '[0-9]+');

            Route::get('/deliveries', [WebhookController::class, 'indexDeliveries'])
                ->middleware('permission:webhooks.view');
            Route::get('/deliveries/{id}', [WebhookController::class, 'showDelivery'])
                ->middleware('permission:webhooks.view')
                ->where('id', '[0-9]+');
            Route::post('/deliveries/{id}/replay', [WebhookController::class, 'replayDelivery'])
                ->middleware('permission:webhooks.manage')
                ->where('id', '[0-9]+');

            Route::get('/stats', [WebhookController::class, 'stats'])
                ->middleware('permission:webhooks.view');
            Route::get('/events', [WebhookController::class, 'events'])
                ->middleware('permission:webhooks.view');
        });

        Route::middleware('module:integrations')->prefix('api-keys')->group(function () {
            Route::get('/', [ApiKeyController::class, 'index'])
                ->middleware('permission:apikeys.view');
            Route::post('/', [ApiKeyController::class, 'store'])
                ->middleware('permission:apikeys.manage');
            Route::delete('/{id}', [ApiKeyController::class, 'destroy'])
                ->middleware('permission:apikeys.manage')
                ->where('id', '[0-9]+');
            Route::post('/{id}/rotate', [ApiKeyController::class, 'rotate'])
                ->middleware('permission:apikeys.manage')
                ->where('id', '[0-9]+');
        });
    });

    // Integration API (external access — API key auth, NOT user token)
    Route::prefix('v1/integration')->middleware(['integration.key', 'integration.rate'])->group(function () {
        Route::get('/sales', [IntegrationApiController::class, 'listSales'])->middleware('integration.scope:read');
        Route::get('/sales/{id}', [IntegrationApiController::class, 'showSale'])->middleware('integration.scope:read')->where('id', '[0-9]+');
        Route::get('/products', [IntegrationApiController::class, 'listProducts'])->middleware('integration.scope:read');
        Route::get('/products/{id}', [IntegrationApiController::class, 'showProduct'])->middleware('integration.scope:read')->where('id', '[0-9]+');
        Route::get('/inventory', [IntegrationApiController::class, 'listInventory'])->middleware('integration.scope:read');
        Route::get('/customers', [IntegrationApiController::class, 'listCustomers'])->middleware('integration.scope:read');
        Route::get('/customers/{id}', [IntegrationApiController::class, 'showCustomer'])->middleware('integration.scope:read')->where('id', '[0-9]+');
        Route::get('/payments', [IntegrationApiController::class, 'listPayments'])->middleware('integration.scope:read');
    });
});
