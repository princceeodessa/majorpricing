<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminProductImageController;
use App\Http\Controllers\ManagerUserController;
use App\Http\Controllers\OneCDiagnosticsController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ManagerChatController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\OneCExchangeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RegistrationRequestController;
use App\Http\Controllers\SupportMessageController;
use App\Http\Controllers\UserAddressController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'show'])->name('home');
Route::get('/sitemap.xml', [HomeController::class, 'sitemap'])->name('sitemap');

Route::middleware(['guest', 'noindex'])->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register-request', [RegistrationRequestController::class, 'create'])->name('registration-requests.create');
    Route::post('/register-request', [RegistrationRequestController::class, 'store'])->name('registration-requests.store');
});

Route::match(['GET', 'POST'], '/1c/exchange', OneCExchangeController::class)->name('onec.exchange');
Route::match(['GET', 'POST'], '/1c_exchange.php', OneCExchangeController::class);

Route::middleware('auth')->group(function (): void {
    Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::patch('/account', [AccountController::class, 'update'])->name('account.update');
    Route::post('/account/support/messages', [SupportMessageController::class, 'storeForClient'])->name('account.support.messages.store');
    Route::post('/account/support/messages/read', [SupportMessageController::class, 'markThreadReadForClient'])->name('account.support.messages.read');
    Route::post('/account/addresses', [UserAddressController::class, 'store'])->name('account.addresses.store');
    Route::patch('/account/addresses/{userAddress}/default', [UserAddressController::class, 'makeDefault'])->name('account.addresses.default');
    Route::delete('/account/addresses/{userAddress}', [UserAddressController::class, 'destroy'])->name('account.addresses.destroy');
    Route::post('/account/users', [ManagerUserController::class, 'store'])
        ->middleware('manager')
        ->name('manager.users.store');
    Route::post('/account/registration-requests/{registrationRequest}/approve', [RegistrationRequestController::class, 'approve'])
        ->middleware('manager')
        ->name('manager.registration-requests.approve');
    Route::get('/manager/chats/{client?}', [ManagerChatController::class, 'index'])
        ->middleware('manager')
        ->name('manager.chats.index');
    Route::post('/manager/support/messages', [SupportMessageController::class, 'storeForManager'])
        ->middleware('manager')
        ->name('manager.support.messages.store');
    Route::get('/admin/1c', [OneCDiagnosticsController::class, 'show'])
        ->middleware('admin')
        ->name('admin.onec.show');
    Route::get('/admin/1c/catalog/file', [OneCDiagnosticsController::class, 'showCatalogFile'])
        ->middleware('admin')
        ->name('admin.onec.catalog.file');
    Route::get('/admin/1c/catalog/file/download', [OneCDiagnosticsController::class, 'downloadCatalogFile'])
        ->middleware('admin')
        ->name('admin.onec.catalog.file.download');
    Route::get('/admin/1c/catalog/package/download', [OneCDiagnosticsController::class, 'downloadCatalogPackage'])
        ->middleware('admin')
        ->name('admin.onec.catalog.package.download');
    Route::post('/admin/1c/catalog/import', [OneCDiagnosticsController::class, 'importCatalog'])
        ->middleware('admin')
        ->name('admin.onec.catalog.import');
    Route::get('/admin/products/images', [AdminProductImageController::class, 'index'])
        ->middleware('admin')
        ->name('admin.products.images.index');
    Route::get('/admin/products/{product}/images', [AdminProductImageController::class, 'edit'])
        ->middleware('admin')
        ->name('admin.products.images.edit');
    Route::post('/admin/products/{product}/images', [AdminProductImageController::class, 'store'])
        ->middleware('admin')
        ->name('admin.products.images.store');
    Route::patch('/admin/products/{product}/images/{productImage}/cover', [AdminProductImageController::class, 'cover'])
        ->middleware('admin')
        ->name('admin.products.images.cover');
    Route::delete('/admin/products/{product}/images/{productImage}', [AdminProductImageController::class, 'destroy'])
        ->middleware('admin')
        ->name('admin.products.images.destroy');
    Route::get('/categories/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');
    Route::post('/products/{product:slug}/cart', [CartController::class, 'store'])->name('cart.store');
    Route::patch('/products/{product:slug}/cart', [CartController::class, 'updateProduct'])->name('cart.product.update');
    Route::delete('/products/{product:slug}/cart', [CartController::class, 'destroyProduct'])->name('cart.product.destroy');
    Route::post('/products/{product:slug}/favorite', [FavoriteController::class, 'store'])->name('favorites.store');
    Route::delete('/products/{product:slug}/favorite', [FavoriteController::class, 'destroy'])->name('favorites.destroy');
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::get('/notifications/poll', [NotificationsController::class, 'poll'])->name('notifications.poll');
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::patch('/cart/items/{cartItem}', [CartController::class, 'update'])->name('cart.items.update');
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy'])->name('cart.items.destroy');
    Route::post('/cart/checkout', [CartController::class, 'checkout'])->name('cart.checkout');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::patch('/orders/{order}', [OrderController::class, 'update'])->middleware('manager')->name('orders.update');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

