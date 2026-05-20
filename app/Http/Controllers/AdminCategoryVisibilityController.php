<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AdminCategoryVisibilityController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $hiddenCategoryIds = Category::hiddenFromCatalogIds();
        $productCounts = Product::query()
            ->selectRaw('category_id, COUNT(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        $branchProductCounts = $this->branchProductCounts($categories, $productCounts);
        $categoryRows = $this->categoryRows($categories, $hiddenCategoryIds, $branchProductCounts);
        $hiddenProductCount = $hiddenCategoryIds === []
            ? 0
            : Product::query()->whereIn('category_id', $hiddenCategoryIds)->count();

        return view('admin.catalog-visibility.index', [
            'categoryRows' => $categoryRows,
            'stats' => [
                'categories' => $categories->count(),
                'directlyHidden' => $categories->where('is_hidden_from_clients', true)->count(),
                'effectivelyHidden' => count($hiddenCategoryIds),
                'hiddenProducts' => $hiddenProductCount,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hidden_categories' => ['nullable', 'array'],
            'hidden_categories.*' => ['integer', 'exists:categories,id'],
        ]);

        $hiddenCategoryIds = collect($validated['hidden_categories'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        Category::query()
            ->where('is_hidden_from_clients', true)
            ->update(['is_hidden_from_clients' => false]);

        if ($hiddenCategoryIds !== []) {
            Category::query()
                ->whereIn('id', $hiddenCategoryIds)
                ->update(['is_hidden_from_clients' => true]);
        }

        return redirect()
            ->route('admin.catalog.visibility.index')
            ->with('status', 'Видимость категорий обновлена.');
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @param  Collection<int, int|string>  $productCounts
     * @return array<int, int>
     */
    private function branchProductCounts(Collection $categories, Collection $productCounts): array
    {
        $childrenByParent = $categories->groupBy(fn (Category $category): int => (int) ($category->parent_id ?? 0));
        $counts = [];

        $sumBranch = function (Category $category) use (&$sumBranch, $childrenByParent, $productCounts, &$counts): int {
            if (array_key_exists($category->id, $counts)) {
                return $counts[$category->id];
            }

            $total = (int) ($productCounts[$category->id] ?? 0);

            foreach ($childrenByParent->get((int) $category->id, collect()) as $child) {
                $total += $sumBranch($child);
            }

            return $counts[$category->id] = $total;
        };

        foreach ($categories as $category) {
            $sumBranch($category);
        }

        return $counts;
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @param  array<int, int>  $hiddenCategoryIds
     * @param  array<int, int>  $branchProductCounts
     * @return Collection<int, Category>
     */
    private function categoryRows(Collection $categories, array $hiddenCategoryIds, array $branchProductCounts): Collection
    {
        $childrenByParent = $categories->groupBy(fn (Category $category): int => (int) ($category->parent_id ?? 0));
        $rows = collect();

        $walk = function (int $parentId, int $depth) use (&$walk, $childrenByParent, $rows, $hiddenCategoryIds, $branchProductCounts): void {
            foreach ($childrenByParent->get($parentId, collect()) as $category) {
                $category->setAttribute('visibility_depth', $depth);
                $category->setAttribute('is_effectively_hidden_from_clients', in_array((int) $category->id, $hiddenCategoryIds, true));
                $category->setAttribute('branch_products_count', $branchProductCounts[$category->id] ?? 0);

                $rows->push($category);
                $walk((int) $category->id, $depth + 1);
            }
        };

        $walk(0, 0);

        return $rows;
    }
}
