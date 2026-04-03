<?php

namespace App\Http\Controllers;

use App\Models\FavoriteItem;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request): View
    {
        $favoriteItems = FavoriteItem::query()
            ->with(['product.category.parent', 'product.prices'])
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->get();

        $favoriteProducts = $favoriteItems
            ->pluck('product')
            ->filter()
            ->values();

        return view('favorites.index', [
            'favoriteProducts' => $favoriteProducts,
            'favoritesCount' => $favoriteProducts->count(),
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        FavoriteItem::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);

        if ($request->expectsJson()) {
            return $this->jsonResponse($request, $product, true);
        }

        return back()->with('status', 'Товар добавлен в избранное.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        FavoriteItem::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->delete();

        if ($request->expectsJson()) {
            return $this->jsonResponse($request, $product, false);
        }

        return back()->with('status', 'Товар убран из избранного.');
    }

    private function jsonResponse(Request $request, Product $product, bool $favorited): JsonResponse
    {
        $favoritesCount = FavoriteItem::query()
            ->where('user_id', $request->user()->id)
            ->count();

        return response()->json([
            'favorited' => $favorited,
            'productId' => $product->id,
            'favoritesCount' => $favoritesCount,
            'storeUrl' => route('favorites.store', $product),
            'destroyUrl' => route('favorites.destroy', $product),
        ]);
    }
}
