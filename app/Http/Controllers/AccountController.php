<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load('priceProfile');

        $recentOrders = $user->orders()
            ->with('items')
            ->take(4)
            ->get();

        $favoriteItems = $user->favoriteItems()
            ->with(['product.category', 'product.prices'])
            ->latest()
            ->take(3)
            ->get();

        $cartItems = $user->cartItems()
            ->with(['product.category', 'product.prices'])
            ->latest()
            ->take(3)
            ->get();

        return view('account.show', [
            'profile' => $user->priceProfile,
            'recentOrders' => $recentOrders,
            'favoriteItems' => $favoriteItems,
            'cartItems' => $cartItems,
            'accountStats' => [
                'rootCategories' => Category::query()->whereNull('parent_id')->count(),
                'ordersCount' => $user->orders()->count(),
                'favoritesCount' => $user->favoriteItems()->count(),
                'cartQuantity' => (int) $user->cartItems()->sum('quantity'),
                'totalSpent' => (float) $user->orders()->sum('total_amount'),
            ],
        ]);
    }
}
