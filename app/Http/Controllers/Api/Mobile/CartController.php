<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\ProductResource;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\MobileCartService;
use App\Support\MobileApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private readonly MobileCartService $cartService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return $this->cartResponse($request, $request->query('payment_method'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'payment_method' => ['nullable', 'string'],
        ]);

        $product = Product::query()
            ->with(['category.parent', 'prices', 'productImages'])
            ->whereKey((int) $data['product_id'])
            ->firstOrFail();

        abort_unless($product->isVisibleToCatalogVisitor(true), 404);

        $quantity = $product->normalizeCartQuantity((int) ($data['quantity'] ?? $product->cartQuantityMinimum()));
        $cartItem = CartItem::query()->firstOrNew([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);

        $currentQuantity = $cartItem->exists
            ? $product->normalizeCartQuantity((int) $cartItem->quantity)
            : 0;

        $cartItem->quantity = $product->normalizeCartQuantity($currentQuantity + $quantity);
        $cartItem->save();

        return $this->cartResponse($request, $data['payment_method'] ?? null);
    }

    public function update(Request $request, CartItem $item): JsonResponse
    {
        $this->ensureOwner($request, $item);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'payment_method' => ['nullable', 'string'],
        ]);

        $item->loadMissing('product');

        $item->update([
            'quantity' => $item->product
                ? $item->product->normalizeCartQuantity((int) $data['quantity'])
                : (int) $data['quantity'],
        ]);

        return $this->cartResponse($request, $data['payment_method'] ?? null);
    }

    public function destroy(Request $request, CartItem $item): JsonResponse
    {
        $this->ensureOwner($request, $item);

        $paymentMethod = $request->query('payment_method');
        $item->delete();

        return $this->cartResponse($request, is_string($paymentMethod) ? $paymentMethod : null);
    }

    private function cartResponse(Request $request, ?string $paymentMethod = null): JsonResponse
    {
        [$items, $summary] = $this->cartService->resolve($request->user(), $paymentMethod);

        return response()->json([
            'items' => $items->map(fn (CartItem $item): array => [
                'id' => $item->id,
                'product' => (new ProductResource($item->product))->resolve(),
                'quantity' => (int) $item->quantity,
                'unit_price' => MobileApi::money($item->getAttribute('resolved_unit_amount')),
                'line_total' => MobileApi::money($item->getAttribute('resolved_line_amount')),
            ])->values()->all(),
            'summary' => [
                'items_count' => (int) $summary['items_count'],
                'total_quantity' => (int) $summary['total_quantity'],
                'subtotal_amount' => MobileApi::money($summary['total_amount']),
                'total_amount' => MobileApi::money($summary['total_amount']),
                'base_total_amount' => MobileApi::money($summary['base_total_amount']),
                'discount_total_amount' => MobileApi::money($summary['discount_total_amount']),
                'currency' => 'RUB',
                'payment_method' => $summary['payment_method'],
                'priced_items_count' => (int) $summary['priced_items_count'],
                'unpriced_items_count' => (int) $summary['unpriced_items_count'],
            ],
        ]);
    }

    private function ensureOwner(Request $request, CartItem $item): void
    {
        abort_unless((int) $item->user_id === (int) $request->user()->id, 404);
    }
}
