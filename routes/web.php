<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ManagerUserController;
use App\Http\Controllers\OneCDiagnosticsController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\OneCExchangeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SupportMessageController;
use App\Http\Controllers\UserAddressController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::match(['GET', 'POST'], '/1c/exchange', OneCExchangeController::class)->name('onec.exchange');
Route::match(['GET', 'POST'], '/1c_exchange.php', OneCExchangeController::class);

Route::middleware('auth')->group(function (): void {
    Route::get('/', [CatalogController::class, 'index'])->name('catalog.index');
    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::patch('/account', [AccountController::class, 'update'])->name('account.update');
    Route::post('/account/support/messages', [SupportMessageController::class, 'storeForClient'])->name('account.support.messages.store');
    Route::post('/account/addresses', [UserAddressController::class, 'store'])->name('account.addresses.store');
    Route::patch('/account/addresses/{userAddress}/default', [UserAddressController::class, 'makeDefault'])->name('account.addresses.default');
    Route::delete('/account/addresses/{userAddress}', [UserAddressController::class, 'destroy'])->name('account.addresses.destroy');
    Route::post('/account/users', [ManagerUserController::class, 'store'])
        ->middleware('manager')
        ->name('manager.users.store');
    Route::get('/manager/1c', [OneCDiagnosticsController::class, 'show'])
        ->middleware('manager')
        ->name('manager.onec.show');
    Route::get('/manager/1c/catalog/file', [OneCDiagnosticsController::class, 'showCatalogFile'])
        ->middleware('manager')
        ->name('manager.onec.catalog.file');
    Route::post('/manager/1c/catalog/import', [OneCDiagnosticsController::class, 'importCatalog'])
        ->middleware('manager')
        ->name('manager.onec.catalog.import');
    Route::post('/manager/support/messages', [SupportMessageController::class, 'storeForManager'])
        ->middleware('manager')
        ->name('manager.support.messages.store');
    Route::get('/categories/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');
    Route::post('/products/{product:slug}/cart', [CartController::class, 'store'])->name('cart.store');
    Route::patch('/products/{product:slug}/cart', [CartController::class, 'updateProduct'])->name('cart.product.update');
    Route::delete('/products/{product:slug}/cart', [CartController::class, 'destroyProduct'])->name('cart.product.destroy');
    Route::post('/products/{product:slug}/favorite', [FavoriteController::class, 'store'])->name('favorites.store');
    Route::delete('/products/{product:slug}/favorite', [FavoriteController::class, 'destroy'])->name('favorites.destroy');
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::patch('/cart/items/{cartItem}', [CartController::class, 'update'])->name('cart.items.update');
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy'])->name('cart.items.destroy');
    Route::post('/cart/checkout', [CartController::class, 'checkout'])->name('cart.checkout');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::patch('/orders/{order}', [OrderController::class, 'update'])->middleware('manager')->name('orders.update');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
