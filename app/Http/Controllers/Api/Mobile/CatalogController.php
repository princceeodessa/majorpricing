<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\CategoryResource;
use App\Http\Resources\Mobile\ProductResource;
use App\Models\Category;
use App\Models\FavoriteItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'category_slug' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['popular', 'name', 'price_asc', 'price_desc', 'in_stock'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $user = $request->user();
        $selectedCategory = $this->resolveCategory($data['category_id'] ?? null, $data['category_slug'] ?? null);
        $perPage = (int) ($data['per_page'] ?? 30);

        $query = Product::query()
            ->visibleToCatalogVisitor(true)
            ->with(['category.parent', 'prices', 'productImages'])
            ->when(
                $selectedCategory,
                fn ($query) => $query->whereIn('category_id', $this->categoryTreeIds($selectedCategory)),
            )
            ->search($data['q'] ?? null);

        match ($data['sort'] ?? 'popular') {
            'name' => $query->orderBy('title')->orderBy('id'),
            'price_asc' => $query->orderByRaw('price_from is null')->orderBy('price_from')->orderBy('title'),
            'price_desc' => $query->orderByRaw('price_from is null')->orderByDesc('price_from')->orderBy('title'),
            'in_stock' => $query->whereNotNull('stock_quantity')->where('stock_quantity', '>', 0)->catalogPriorityOrder(),
            default => $query->catalogPriorityOrder(),
        };

        $products = $query->paginate($perPage)->withQueryString();
        $this->attachUserState($products->getCollection(), $user);

        return response()->json([
            'data' => ProductResource::collection($products->getCollection())->resolve(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->visibleToCatalogVisitor(true)
            ->roots()
            ->with(['children' => fn ($query) => $query->visibleToCatalogVisitor(true)])
            ->get();

        return response()->json([
            'data' => CategoryResource::collection($categories)->resolve(),
        ]);
    }

    public function showProduct(Request $request, string $product): ProductResource
    {
        $resolvedProduct = $this->resolveProduct($product);
        abort_unless($resolvedProduct->isVisibleToCatalogVisitor(true), 404);

        $resolvedProduct->loadMissing(['category.parent', 'prices', 'productImages']);
        $this->attachUserState(new EloquentCollection([$resolvedProduct]), $request->user());

        return new ProductResource($resolvedProduct);
    }

    public function favorites(Request $request): JsonResponse
    {
        $products = Product::query()
            ->visibleToCatalogVisitor(true)
            ->with(['category.parent', 'prices', 'productImages'])
            ->whereHas('favoriteItems', fn ($query) => $query->where('user_id', $request->user()->id))
            ->catalogPriorityOrder()
            ->get();

        $this->attachUserState($products, $request->user());

        return response()->json([
            'data' => ProductResource::collection($products)->resolve(),
        ]);
    }

    public function storeFavorite(Request $request, string $product): JsonResponse
    {
        $resolvedProduct = $this->resolveProduct($product);
        abort_unless($resolvedProduct->isVisibleToCatalogVisitor(true), 404);

        FavoriteItem::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => $resolvedProduct->id,
        ]);

        return response()->json([
            'ok' => true,
            'product_id' => $resolvedProduct->id,
            'is_favorite' => true,
        ]);
    }

    public function destroyFavorite(Request $request, string $product): JsonResponse
    {
        $resolvedProduct = $this->resolveProduct($product);

        FavoriteItem::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $resolvedProduct->id)
            ->delete();

        return response()->json([
            'ok' => true,
            'product_id' => $resolvedProduct->id,
            'is_favorite' => false,
        ]);
    }

    private function resolveCategory(null|int|string $id, ?string $slug): ?Category
    {
        if ($id === null && blank($slug)) {
            return null;
        }

        $query = Category::query()->visibleToCatalogVisitor(true);

        if ($id !== null) {
            return $query->whereKey((int) $id)->firstOrFail();
        }

        return $query->where('slug', $slug)->firstOrFail();
    }

    private function resolveProduct(string $product): Product
    {
        $qualifiedKey = (new Product)->getQualifiedKeyName();

        return Product::query()
            ->where(fn ($query) => $query
                ->when(is_numeric($product), fn ($query) => $query->orWhere($qualifiedKey, (int) $product))
                ->orWhere('slug', $product))
            ->firstOrFail();
    }

    /**
     * @return array<int, int>
     */
    private function categoryTreeIds(Category $category): array
    {
        $category->loadMissing(['children' => fn ($query) => $query->visibleToCatalogVisitor(true)]);

        return $category->children
            ->pluck('id')
            ->push($category->id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  EloquentCollection<int, Product>  $products
     */
    private function attachUserState(EloquentCollection $products, User $user): void
    {
        $productIds = $products->pluck('id')->all();

        if ($productIds === []) {
            return;
        }

        $favoriteIds = FavoriteItem::query()
            ->where('user_id', $user->id)
            ->whereIn('product_id', $productIds)
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $cartQuantities = $user->cartItems()
            ->whereIn('product_id', $productIds)
            ->pluck('quantity', 'product_id');

        $products->each(function (Product $product) use ($favoriteIds, $cartQuantities): void {
            $product->setAttribute('mobile_is_favorite', in_array((int) $product->id, $favoriteIds, true));
            $product->setAttribute('mobile_cart_quantity', (int) ($cartQuantities[$product->id] ?? 0));
        });
    }
}
