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
                $headerFavoritesCount = 0;
                $headerOrdersCount = 0;
                $favoriteProductIds = [];

                if (Schema::hasTable('categories')) {
                    $navCategories = Category::query()
                        ->whereNull('parent_id')
                        ->orderBy('sort_order')
                        ->get();
                }

                if (auth()->check()) {
                    if (Schema::hasTable('cart_items')) {
                        $headerCartCount = (int) CartItem::query()
                            ->where('user_id', auth()->id())
                            ->sum('quantity');
                    }

                    if (Schema::hasTable('favorite_items')) {
                        $favoriteProductIds = FavoriteItem::query()
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
                }

                return [
                    'navCategories' => $navCategories,
                    'headerCartCount' => $headerCartCount,
                    'headerFavoritesCount' => $headerFavoritesCount,
                    'headerOrdersCount' => $headerOrdersCount,
                    'favoriteProductIds' => $favoriteProductIds,
                ];
            });

            $view->with($shared);
        });
    }
}
