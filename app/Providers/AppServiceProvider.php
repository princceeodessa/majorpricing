<?php

namespace App\Providers;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\FavoriteItem;
use App\Models\Order;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['layouts.app', 'catalog.*', 'cart.*', 'orders.*', 'favorites.*', 'account.*', 'partials.*'], function ($view): void {
            $shared = once(function (): array {
                $navCategories = collect();
                $headerCartCount = 0;
                $cartProductQuantities = [];
                $headerFavoritesCount = 0;
                $headerOrdersCount = 0;
                $favoriteProductIds = [];
                $supportWidgetEnabled = false;
                $supportWidgetManager = null;
                $supportWidgetMessages = collect();

                if (Schema::hasTable('categories')) {
                    $isAuthenticated = auth()->check();

                    $navCategories = Category::query()
                        ->visibleToCatalogVisitor($isAuthenticated)
                        ->whereNull('parent_id')
                        ->with(['children' => fn ($query) => $query->visibleToCatalogVisitor($isAuthenticated)])
                        ->orderBy('sort_order')
                        ->get();
                }

                if (auth()->check()) {
                    $authUser = auth()->user();

                    if (Schema::hasTable('cart_items')) {
                        $cartItems = CartItem::query()
                            ->when(
                                Schema::hasTable('products') && Schema::hasTable('categories'),
                                fn ($query) => $query->whereHas('product', fn ($productQuery) => $productQuery->visibleInCatalog()),
                            )
                            ->where('user_id', auth()->id())
                            ->get(['product_id', 'quantity']);

                        $headerCartCount = (int) $cartItems->sum('quantity');
                        $cartProductQuantities = $cartItems
                            ->pluck('quantity', 'product_id')
                            ->map(fn ($quantity): int => (int) $quantity)
                            ->all();
                    }

                    if (Schema::hasTable('favorite_items')) {
                        $favoriteProductIds = FavoriteItem::query()
                            ->when(
                                Schema::hasTable('products') && Schema::hasTable('categories'),
                                fn ($query) => $query->whereHas('product', fn ($productQuery) => $productQuery->visibleInCatalog()),
                            )
                            ->where('user_id', auth()->id())
                            ->orderByDesc('id')
                            ->pluck('product_id')
                            ->map(fn ($id): int => (int) $id)
                            ->all();

                        $headerFavoritesCount = count($favoriteProductIds);
                    }

                    if (Schema::hasTable('orders')) {
                        $headerOrdersCount = (int) Order::query()
                            ->where('user_id', auth()->id())
                            ->count();
                    }

                    if (
                        $authUser
                        && ! $authUser->canManageClients()
                        && Schema::hasTable('support_messages')
                    ) {
                        $authUser->loadMissing(['manager', 'supportMessages.sender']);

                        $supportWidgetManager = $authUser->manager;
                        $supportWidgetMessages = $authUser->supportMessages;
                        $supportWidgetEnabled = $supportWidgetManager?->canManageClients() ?? false;
                    }
                }

                return [
                    'navCategories' => $navCategories,
                    'headerCartCount' => $headerCartCount,
                    'cartProductQuantities' => $cartProductQuantities,
                    'headerFavoritesCount' => $headerFavoritesCount,
                    'headerOrdersCount' => $headerOrdersCount,
                    'favoriteProductIds' => $favoriteProductIds,
                    'supportWidgetEnabled' => $supportWidgetEnabled,
                    'supportWidgetManager' => $supportWidgetManager,
                    'supportWidgetMessages' => $supportWidgetMessages,
                ];
            });

            $view->with($shared);
        });
    }
}
