<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function show(Request $request, Product $product): View
    {
        $request->user()->loadMissing('priceProfile');
        $product->loadMissing(['category.children', 'category.parent.children', 'category.parent', 'prices']);
        $cartQuantity = (int) $request->user()->cartItems()->where('product_id', $product->id)->value('quantity');

        $relatedProducts = $this->resolveRelatedProducts($product);

        return view('catalog.product', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
            'cartQuantity' => $cartQuantity,
        ]);
    }

    private function resolveRelatedProducts(Product $product): Collection
    {
        $category = $product->category;
        $rootCategory = $category?->parent ?? $category;
        $rootCategoryIds = $rootCategory
            ? $rootCategory->children->pluck('id')->push($rootCategory->id)->unique()->values()->all()
            : array_values(array_filter([(int) $product->category_id]));
        $tokens = $this->extractSearchTokens($product);

        $candidates = Product::query()
            ->with(['category.parent', 'prices'])
            ->whereKeyNot($product->id)
            ->where(function (Builder $query) use ($product, $rootCategoryIds, $tokens): void {
                if ($product->category_id) {
                    $query->where('category_id', $product->category_id);
                }

                if ($rootCategoryIds !== []) {
                    $query->orWhereIn('category_id', $rootCategoryIds);
                }

                if (filled($product->source_sheet)) {
                    $query->orWhere('source_sheet', $product->source_sheet);
                }

                foreach ($tokens as $token) {
                    $query
                        ->orWhere('title', 'like', "%{$token}%")
                        ->orWhere('name', 'like', "%{$token}%");
                }
            })
            ->limit(48)
            ->get();

        return $candidates
            ->map(function (Product $candidate) use ($product, $rootCategoryIds, $tokens): array {
                return [
                    'product' => $candidate,
                    'score' => $this->scoreRelatedProduct($product, $candidate, $rootCategoryIds, $tokens),
                ];
            })
            ->filter(fn (array $item): bool => $item['score'] > 0)
            ->sortByDesc('score')
            ->take(6)
            ->pluck('product')
            ->values();
    }

    private function scoreRelatedProduct(Product $product, Product $candidate, array $rootCategoryIds, Collection $tokens): int
    {
        $score = 0;

        if ($candidate->category_id === $product->category_id) {
            $score += 70;
        } elseif (in_array($candidate->category_id, $rootCategoryIds, true)) {
            $score += 32;
        }

        if (filled($product->source_sheet) && $candidate->source_sheet === $product->source_sheet) {
            $score += 26;
        }

        if (filled($product->measurement_label) && $candidate->measurement_label === $product->measurement_label) {
            $score += 8;
        }

        $haystack = Str::lower(trim($candidate->title.' '.$candidate->name.' '.$candidate->description));
        $score += $tokens
            ->filter(fn (string $token): bool => Str::contains($haystack, $token))
            ->count() * 6;

        if ($candidate->has_video && $product->has_video) {
            $score += 4;
        }

        return $score;
    }

    private function extractSearchTokens(Product $product): Collection
    {
        $stopWords = [
            'для',
            'или',
            'цена',
            'цены',
            'majpr',
            'major',
            'товар',
            'товары',
            'профиль',
            'комплект',
        ];

        return collect(preg_split('/[^\p{L}\p{N}]+/u', Str::lower(trim($product->title.' '.$product->name))))
            ->filter(fn (?string $token): bool => filled($token) && mb_strlen($token) >= 4)
            ->reject(fn (string $token): bool => in_array($token, $stopWords, true))
            ->unique()
            ->values()
            ->take(6);
    }
}
