<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $request->user()->loadMissing('priceProfile');

        $selectedCategory = null;

        if ($request->filled('category')) {
            $selectedCategory = Category::query()
                ->where('slug', $request->string('category')->toString())
                ->first();
        }

        $products = Product::query()
            ->with(['category.parent', 'prices'])
            ->when(
                $selectedCategory,
                fn ($query) => $query->whereIn('category_id', $this->categoryTreeIds($selectedCategory)),
            )
            ->search($request->string('q')->toString())
            ->orderByRaw('price_from is null')
            ->orderBy('title')
            ->paginate(18)
            ->withQueryString();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'html' => view('catalog.partials.product-feed-items', [
                    'products' => $products,
                    'animationStep' => 70,
                    'animationWindow' => 6,
                ])->render(),
                'nextPageUrl' => $products->nextPageUrl(),
                'hasMorePages' => $products->hasMorePages(),
            ]);
        }

        $rootCategories = Category::query()
            ->roots()
            ->with('children')
            ->get();

        $productCounts = Product::query()
            ->selectRaw('category_id, COUNT(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        $rootCategories->each(function (Category $category) use ($productCounts): void {
            $categoryIds = $category->children->pluck('id')->push($category->id);

            $category->setAttribute(
                'catalog_products_count',
                $categoryIds->sum(fn (int $categoryId): int => (int) ($productCounts[$categoryId] ?? 0)),
            );
        });

        $featuredProducts = Product::query()
            ->with(['category.parent', 'prices'])
            ->orderByRaw('image_path is null')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return view('catalog.index', [
            'featuredProducts' => $featuredProducts,
            'products' => $products,
            'rootCategories' => $rootCategories,
            'selectedCategory' => $selectedCategory,
            'totalProducts' => Product::query()->count(),
            'totalSections' => Category::query()->count(),
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function categoryTreeIds(Category $category): array
    {
        $category->loadMissing('children');

        return $category->children
            ->pluck('id')
            ->push($category->id)
            ->unique()
            ->values()
            ->all();
    }
}
