<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CatalogController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $selectedCategory = null;
        $searchQuery = trim($request->string('q')->toString());
        $hasSearch = filled($searchQuery);
        $feed = $request->string('feed')->toString();
        $feed = in_array($feed, ['new', 'hit', 'in_stock'], true) ? $feed : 'new';

        if ($request->filled('category')) {
            $selectedCategory = Category::query()
                ->visibleInCatalog()
                ->where('slug', $request->string('category')->toString())
                ->firstOrFail();
        }

        $productsQuery = Product::query()
            ->visibleInCatalog()
            ->with(['category.parent', 'prices', 'productImages'])
            ->when(
                $selectedCategory,
                fn ($query) => $query->whereIn('category_id', $this->categoryTreeIds($selectedCategory)),
            )
            ->search($searchQuery);

        match ($feed) {
            'hit' => $productsQuery
                ->withCount('orderItems')
                ->catalogPriorityOrder(),
            'in_stock' => $productsQuery
                ->whereNotNull('stock_quantity')
                ->where('stock_quantity', '>', 0)
                ->catalogPriorityOrder(),
            default => $productsQuery
                ->catalogPriorityOrder(),
        };

        $products = $productsQuery
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
        if (! $hasSearch) {
            $rootCategories = Category::query()
                ->visibleInCatalog()
                ->roots()
                ->with(['children' => fn ($query) => $query->visibleInCatalog()])
                ->get();

            $catalogCategoryIds = $rootCategories
                ->flatMap(fn (Category $category) => $category->children->pluck('id')->push($category->id))
                ->unique()
                ->values();

            $productCounts = Product::query()
                ->visibleInCatalog()
                ->selectRaw('category_id, COUNT(*) as aggregate')
                ->whereIn('category_id', $catalogCategoryIds)
                ->groupBy('category_id')
                ->pluck('aggregate', 'category_id');

            $categoryPreviewProducts = Product::query()
                ->visibleInCatalog()
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
                $categoryCover = $this->resolveCategoryCover($category->name);

                $category->setAttribute(
                    'catalog_products_count',
                    $categoryIds->sum(fn (int $categoryId): int => (int) ($productCounts[$categoryId] ?? 0)),
                );
                $category->setAttribute('catalog_preview_image', $categoryCover ?? $previewProduct?->image_path);
                $category->setAttribute('catalog_preview_title', $categoryCover ? $category->name : $previewProduct?->title);
            });
        }

        return view('catalog.index', [
            'feed' => $feed,
            'hasSearch' => $hasSearch,
            'products' => $products,
            'rootCategories' => $rootCategories,
            'searchQuery' => $searchQuery,
            'selectedCategory' => $selectedCategory,
            'totalProducts' => Product::query()->visibleInCatalog()->count(),
            'totalSections' => Category::query()->visibleInCatalog()->count(),
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function categoryTreeIds(Category $category): array
    {
        $category->loadMissing(['children' => fn ($query) => $query->visibleInCatalog()]);

        return $category->children
            ->pluck('id')
            ->push($category->id)
            ->unique()
            ->values()
            ->all();
    }

    private function resolveCategoryCover(?string $categoryName): ?string
    {
        if (blank($categoryName)) {
            return null;
        }

        $imageMap = $this->categoryCoverMap();
        $keys = collect([
            $this->normalizeCategoryCoverKey($categoryName),
            $this->normalizeCategoryCoverKey(Str::transliterate((string) $categoryName)),
        ])->filter()->unique()->values();

        foreach ($keys as $key) {
            if (isset($imageMap[$key])) {
                return $imageMap[$key];
            }

            $aliasTarget = $this->categoryCoverAliases()[$key] ?? null;

            if ($aliasTarget && isset($imageMap[$aliasTarget])) {
                return $imageMap[$aliasTarget];
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function categoryCoverMap(): array
    {
        static $map = null;

        if (is_array($map)) {
            return $map;
        }

        $directory = public_path('catalog-media/category-covers');

        if (! File::isDirectory($directory)) {
            return $map = [];
        }

        $map = [];

        foreach (File::files($directory) as $file) {
            if (! in_array(strtolower($file->getExtension()), ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'], true)) {
                continue;
            }

            $stem = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $relativePath = 'catalog-media/category-covers/'.$file->getFilename();

            foreach ([
                $this->normalizeCategoryCoverKey($stem),
                $this->normalizeCategoryCoverKey(Str::transliterate($stem)),
            ] as $key) {
                if ($key !== '') {
                    $map[$key] = $relativePath;
                }
            }
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private function categoryCoverAliases(): array
    {
        return [
            'алформ' => 'alform',
            'komandaa5' => 'komanda5',
            'командаа5' => 'команда5',
            'командаa5' => 'команда5',
            'решеткидиффузоры' => 'решеткидифузорып',
            'решеткидифузоры' => 'решеткидифузорып',
            'решеткидиффузорып' => 'решеткидифузорып',
            'решеткидифузорып' => 'решеткидифузорып',
            'расходка' => 'расходныематериалы',
            'инструмент' => 'ручнойинструмент',
            'платформы' => 'платформып',
        ];
    }

    private function normalizeCategoryCoverKey(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));

        if ($value === '') {
            return '';
        }

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
    }
}
