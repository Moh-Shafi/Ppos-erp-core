<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StoreController;
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
