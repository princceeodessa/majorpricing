<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\NormalizesIntegrationData;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationCatalogController extends Controller
{
    use NormalizesIntegrationData;

    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'updated_since' => ['nullable', 'date'],
            'category' => ['nullable', 'string', 'max:120'],
            'sheet' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $products = Product::query()
            ->with(['category.parent', 'prices'])
            ->when(
                filled($validated['updated_since'] ?? null),
                fn ($query) => $query->where('updated_at', '>=', $validated['updated_since']),
            )
            ->when(
                filled($validated['category'] ?? null),
                fn ($query) => $query->whereHas('category', function ($categoryQuery) use ($validated): void {
                    $categoryQuery->where('slug', $validated['category'])->orWhere('name', $validated['category']);
                }),
            )
            ->when(
                filled($validated['sheet'] ?? null),
                fn ($query) => $query->where('source_sheet', $validated['sheet']),
            )
            ->search($validated['q'] ?? null)
            ->orderBy('id')
            ->paginate((int) ($validated['per_page'] ?? 100))
            ->withQueryString();

        return response()->json([
            'data' => $products->getCollection()->map(fn (Product $product): array => $this->transformProduct($product))->values(),
            'meta' => $this->paginationMeta($products),
            'integration' => [
                'service' => $request->attributes->get('integration_service'),
                'generated_at' => now()->toAtomString(),
            ],
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        $productCounts = Product::query()
            ->selectRaw('category_id, COUNT(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        $categories = Category::query()
            ->roots()
            ->with('children')
            ->get();

        return response()->json([
            'data' => $categories->map(function (Category $category) use ($productCounts): array {
                $children = $category->children->map(function (Category $child) use ($productCounts): array {
                    return [
                        'id' => $child->id,
                        'name' => $child->name,
                        'slug' => $child->slug,
                        'accent_color' => $child->accent_color,
                        'product_count' => (int) ($productCounts[$child->id] ?? 0),
                    ];
                })->values();

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'accent_color' => $category->accent_color,
                    'product_count' => (int) ($productCounts[$category->id] ?? 0) + $children->sum('product_count'),
                    'children' => $children,
                ];
            })->values(),
            'integration' => [
                'service' => $request->attributes->get('integration_service'),
                'generated_at' => now()->toAtomString(),
            ],
        ]);
    }

    private function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'next_page_url' => $paginator->nextPageUrl(),
            'prev_page_url' => $paginator->previousPageUrl(),
        ];
    }
}
