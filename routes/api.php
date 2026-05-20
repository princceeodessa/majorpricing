<?php

use App\Http\Controllers\Api\IntegrationCatalogController;
use App\Http\Controllers\Api\IntegrationOrderController;
use App\Http\Controllers\Api\IntegrationPaymentController;
use App\Http\Controllers\Api\IntegrationPriceSyncController;
use Illuminate\Support\Facades\Route;

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
