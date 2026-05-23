<?php

use App\Http\Controllers\Api\IntegrationCatalogController;
use App\Http\Controllers\Api\IntegrationOrderController;
use App\Http\Controllers\Api\IntegrationPaymentController;
use App\Http\Controllers\Api\IntegrationPriceSyncController;
use App\Http\Controllers\Api\Mobile\AccountController as MobileAccountController;
use App\Http\Controllers\Api\Mobile\AuthController as MobileAuthController;
use App\Http\Controllers\Api\Mobile\CartController as MobileCartController;
use App\Http\Controllers\Api\Mobile\CatalogController as MobileCatalogController;
use App\Http\Controllers\Api\Mobile\ManagerController as MobileManagerController;
use App\Http\Controllers\Api\Mobile\OrderController as MobileOrderController;
use App\Http\Controllers\Api\Mobile\SupportController as MobileSupportController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')
    ->name('api.mobile.')
    ->group(function (): void {
        Route::post('/auth/login', [MobileAuthController::class, 'login'])->name('auth.login');

        Route::middleware('mobile.auth')->group(function (): void {
            Route::post('/auth/logout', [MobileAuthController::class, 'logout'])->name('auth.logout');
            Route::get('/me', [MobileAuthController::class, 'me'])->name('me');

            Route::get('/account', [MobileAccountController::class, 'show'])->name('account.show');

            Route::get('/catalog', [MobileCatalogController::class, 'index'])->name('catalog.index');
            Route::get('/categories', [MobileCatalogController::class, 'categories'])->name('categories.index');
            Route::get('/products/{product}', [MobileCatalogController::class, 'showProduct'])->name('products.show');
            Route::get('/favorites', [MobileCatalogController::class, 'favorites'])->name('favorites.index');
            Route::post('/favorites/{product}', [MobileCatalogController::class, 'storeFavorite'])->name('favorites.store');
            Route::delete('/favorites/{product}', [MobileCatalogController::class, 'destroyFavorite'])->name('favorites.destroy');

            Route::get('/cart', [MobileCartController::class, 'show'])->name('cart.show');
            Route::post('/cart/items', [MobileCartController::class, 'store'])->name('cart.items.store');
            Route::patch('/cart/items/{item}', [MobileCartController::class, 'update'])->name('cart.items.update');
            Route::delete('/cart/items/{item}', [MobileCartController::class, 'destroy'])->name('cart.items.destroy');

            Route::get('/orders', [MobileOrderController::class, 'index'])->name('orders.index');
            Route::post('/orders', [MobileOrderController::class, 'store'])->name('orders.store');
            Route::get('/orders/{order}', [MobileOrderController::class, 'show'])->name('orders.show');

            Route::get('/support/messages', [MobileSupportController::class, 'messages'])->name('support.messages.index');
            Route::post('/support/messages', [MobileSupportController::class, 'store'])->name('support.messages.store');

            Route::get('/manager/clients', [MobileManagerController::class, 'clients'])->name('manager.clients.index');
            Route::get('/manager/registration-requests', [MobileManagerController::class, 'registrationRequests'])->name('manager.registration-requests.index');
            Route::post('/manager/registration-requests/{registrationRequest}/approve', [MobileManagerController::class, 'approveRegistrationRequest'])->name('manager.registration-requests.approve');
            Route::post('/manager/registration-requests/{registrationRequest}/reject', [MobileManagerController::class, 'rejectRegistrationRequest'])->name('manager.registration-requests.reject');
        });
    });

Route::prefix('integrations')
    ->name('api.integrations.')
    ->group(function (): void {
        Route::middleware('integration.token:erp')
            ->prefix('catalog')
            ->name('catalog.')
            ->group(function (): void {
                Route::get('/products', [IntegrationCatalogController::class, 'products'])->name('products');
                Route::get('/categories', [IntegrationCatalogController::class, 'categories'])->name('categories');
                Route::post('/prices/sync', [IntegrationPriceSyncController::class, 'sync'])->name('prices.sync');
            });

        Route::middleware('integration.token:erp')
            ->prefix('orders')
            ->name('orders.')
            ->group(function (): void {
                Route::get('/', [IntegrationOrderController::class, 'index'])->name('index');
                Route::get('/{order:number}', [IntegrationOrderController::class, 'show'])->name('show');
            });

        Route::middleware('integration.token:erp,payments')
            ->patch('/orders/{order:number}', [IntegrationOrderController::class, 'update'])
            ->name('orders.update');

        Route::middleware('integration.token:payments')
            ->prefix('payments')
            ->name('payments.')
            ->group(function (): void {
                Route::get('/orders/{order:number}', [IntegrationPaymentController::class, 'show'])->name('show');
                Route::post('/webhook', [IntegrationPaymentController::class, 'webhook'])->name('webhook');
            });
    });
