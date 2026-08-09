<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth routes (public)
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });

        // Tenant
        Route::get('tenant', [TenantController::class, 'show']);
        Route::put('tenant', [TenantController::class, 'update']);

        // Stores
        Route::apiResource('stores', StoreController::class);

        // Categories
        Route::apiResource('categories', CategoryController::class);

        // Products
        Route::apiResource('products', ProductController::class);

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
        });
    });
});
