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
        $selectedCategory = null;
        $searchQuery = trim($request->string('q')->toString());
        $hasSearch = filled($searchQuery);

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
            ->search($searchQuery)
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

        $rootCategories = collect();
        $featuredProducts = collect();

        if (! $hasSearch) {
            $rootCategories = Category::query()
                ->roots()
                ->with('children')
                ->get();

            $catalogCategoryIds = $rootCategories
                ->flatMap(fn (Category $category) => $category->children->pluck('id')->push($category->id))
                ->unique()
                ->values();

            $productCounts = Product::query()
                ->selectRaw('category_id, COUNT(*) as aggregate')
                ->whereIn('category_id', $catalogCategoryIds)
                ->groupBy('category_id')
                ->pluck('aggregate', 'category_id');

            $categoryPreviewProducts = Product::query()
                ->select(['category_id', 'title', 'image_path', 'sort_order'])
                ->whereIn('category_id', $catalogCategoryIds)
                ->orderByRaw('image_path is null')
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get()
                ->groupBy('category_id')
                ->map(fn ($products) => $products->first());

            $rootCategories->each(function (Category $category) use ($productCounts, $categoryPreviewProducts): void {
                $categoryIds = $category->children->pluck('id')->push($category->id);
                $previewCandidates = $categoryIds
                    ->map(fn (int $categoryId) => $categoryPreviewProducts->get($categoryId))
                    ->filter();

                $previewProduct = $previewCandidates->first(fn ($product): bool => filled($product->image_path))
                    ?? $previewCandidates->first();

                $category->setAttribute(
                    'catalog_products_count',
                    $categoryIds->sum(fn (int $categoryId): int => (int) ($productCounts[$categoryId] ?? 0)),
                );
                $category->setAttribute('catalog_preview_image', $previewProduct?->image_path);
                $category->setAttribute('catalog_preview_title', $previewProduct?->title);
            });

            $featuredProducts = Product::query()
                ->with(['category.parent', 'prices'])
                ->orderByRaw('image_path is null')
                ->orderByDesc('id')
                ->limit(8)
                ->get();
        }

        return view('catalog.index', [
            'featuredProducts' => $featuredProducts,
            'hasSearch' => $hasSearch,
            'products' => $products,
            'rootCategories' => $rootCategories,
            'searchQuery' => $searchQuery,
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
