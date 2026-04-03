<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function show(Request $request, Category $category): View|JsonResponse
    {
        $request->user()->loadMissing('priceProfile');
        $category->loadMissing(['parent', 'children']);
        $priceColumn = max(1, (int) ($request->user()->priceProfile?->column_index ?? 1));

        $selectedSection = null;
        $categoryIds = [$category->id];

        if ($category->children->isNotEmpty()) {
            $categoryIds = $category->children->pluck('id')->push($category->id)->all();

            if ($request->filled('section')) {
                $selectedSection = $category->children
                    ->firstWhere('slug', $request->string('section')->toString());

                if ($selectedSection) {
                    $categoryIds = [$selectedSection->id];
                }
            }
        }

        $catalogQuery = Product::query()
            ->whereIn('category_id', $categoryIds)
            ->search($request->string('q')->toString());

        $priceStats = (clone $catalogQuery)
            ->join('product_prices', function ($join) use ($priceColumn): void {
                $join->on('product_prices.product_id', '=', 'products.id')
                    ->where('product_prices.column_index', '=', $priceColumn)
                    ->whereNotNull('product_prices.min_amount');
            })
            ->selectRaw('MIN(product_prices.min_amount) as min_amount, MAX(product_prices.min_amount) as max_amount')
            ->first();

        $availableSheets = (clone $catalogQuery)
            ->whereNotNull('source_sheet')
            ->selectRaw('source_sheet, COUNT(*) as aggregate')
            ->groupBy('source_sheet')
            ->orderBy('source_sheet')
            ->get();

        $selectedSheet = trim($request->string('sheet')->toString());
        $hasPriceBounds = $priceStats?->min_amount !== null && $priceStats?->max_amount !== null;

        $priceBounds = [
            'min' => $hasPriceBounds ? (float) $priceStats->min_amount : null,
            'max' => $hasPriceBounds ? (float) $priceStats->max_amount : null,
        ];

        $selectedPriceMin = $request->filled('price_min')
            ? (float) $request->input('price_min')
            : $priceBounds['min'];
        $selectedPriceMax = $request->filled('price_max')
            ? (float) $request->input('price_max')
            : $priceBounds['max'];

        if ($hasPriceBounds) {
            $selectedPriceMin = max($priceBounds['min'], min($selectedPriceMin, $priceBounds['max']));
            $selectedPriceMax = max($priceBounds['min'], min($selectedPriceMax, $priceBounds['max']));

            if ($selectedPriceMin > $selectedPriceMax) {
                [$selectedPriceMin, $selectedPriceMax] = [$selectedPriceMax, $selectedPriceMin];
            }
        }

        $hasActivePriceFilter = $hasPriceBounds && ($request->filled('price_min') || $request->filled('price_max'));

        $products = Product::query()
            ->with(['category.parent', 'prices'])
            ->whereIn('category_id', $categoryIds)
            ->search($request->string('q')->toString())
            ->when(
                filled($selectedSheet),
                fn (Builder $query) => $query->where('source_sheet', $selectedSheet),
            )
            ->when(
                $hasActivePriceFilter,
                fn (Builder $query) => $query->whereHas('prices', function (Builder $priceQuery) use ($priceColumn, $selectedPriceMin, $selectedPriceMax): void {
                    $priceQuery
                        ->where('column_index', $priceColumn)
                        ->whereNotNull('min_amount')
                        ->where('min_amount', '>=', $selectedPriceMin)
                        ->where('min_amount', '<=', $selectedPriceMax);
                }),
            )
            ->orderByRaw('price_from is null')
            ->orderBy('title')
            ->paginate(24)
            ->withQueryString();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'html' => view('catalog.partials.product-feed-items', [
                    'products' => $products,
                    'animationStep' => 55,
                    'animationWindow' => 8,
                ])->render(),
                'nextPageUrl' => $products->nextPageUrl(),
                'hasMorePages' => $products->hasMorePages(),
            ]);
        }

        $sectionCounts = Product::query()
            ->selectRaw('category_id, COUNT(*) as aggregate')
            ->whereIn('category_id', $category->children->pluck('id')->all())
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        return view('catalog.category', [
            'category' => $category,
            'products' => $products,
            'sectionCounts' => $sectionCounts,
            'selectedSection' => $selectedSection,
            'selectedSheet' => $selectedSheet,
            'availableSheets' => $availableSheets,
            'priceBounds' => $priceBounds,
            'selectedPriceMin' => $selectedPriceMin,
            'selectedPriceMax' => $selectedPriceMax,
            'hasActivePriceFilter' => $hasActivePriceFilter,
        ]);
    }
}
