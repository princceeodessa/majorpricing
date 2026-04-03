<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\ErpOrderSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function __construct(
        private readonly ErpOrderSyncService $erpOrderSyncService,
    ) {
    }

    public function index(Request $request): View
    {
        $request->user()->loadMissing('priceProfile');

        [$cartItems, $summary] = $this->resolveCartState($request->user());

        return view('cart.index', [
            'cartItems' => $cartItems,
            'summary' => $summary,
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $quantity = (int) ($validated['quantity'] ?? 1);
        $cartItem = CartItem::query()->firstOrNew([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);

        $cartItem->quantity = min(999, ($cartItem->exists ? $cartItem->quantity : 0) + $quantity);
        $cartItem->save();

        return back()->with('status', 'Товар добавлен в корзину.');
    }

    public function update(Request $request, CartItem $cartItem): RedirectResponse
    {
        $this->ensureOwner($request, $cartItem);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $cartItem->update([
            'quantity' => (int) $validated['quantity'],
        ]);

        return back()->with('status', 'Количество в корзине обновлено.');
    }

    public function destroy(Request $request, CartItem $cartItem): RedirectResponse
    {
        $this->ensureOwner($request, $cartItem);

        $cartItem->delete();

        return back()->with('status', 'Товар удален из корзины.');
    }

    public function checkout(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:1500'],
        ]);

        $user = $request->user();
        $user->loadMissing('priceProfile');

        [$cartItems, $summary] = $this->resolveCartState($user);

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('status', 'Корзина пока пуста.');
        }

        $order = DB::transaction(function () use ($user, $validated, $cartItems, $summary): Order {
            $order = Order::query()->create([
                'user_id' => $user->id,
                'status' => 'new',
                'integration_status' => 'pending',
                'items_count' => (int) $summary['items_count'],
                'subtotal_amount' => $summary['total_amount'],
                'total_amount' => $summary['total_amount'],
                'price_profile_name' => $user->priceProfile?->name,
                'comment' => filled($validated['comment'] ?? null) ? trim($validated['comment']) : null,
                'placed_at' => now(),
            ]);

            $order->update([
                'number' => sprintf('ORD-%s-%05d', now()->format('Ymd'), $order->id),
            ]);

            foreach ($cartItems as $cartItem) {
                $resolvedPrice = $cartItem->getAttribute('resolved_price');
                $resolvedLineAmount = $cartItem->getAttribute('resolved_line_amount');
                $resolvedUnitAmount = $cartItem->getAttribute('resolved_unit_amount');
                $product = $cartItem->product;

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product?->id,
                    'product_title' => $product?->title ?? 'Товар из каталога',
                    'product_slug' => $product?->slug,
                    'quantity' => $cartItem->quantity,
                    'price_label' => $resolvedPrice?->label ?? $user->priceProfile?->price_label,
                    'unit_price' => $resolvedUnitAmount,
                    'line_total' => $resolvedLineAmount,
                    'source_sheet' => $product?->source_sheet,
                    'measurement_value' => $product?->measurement_value,
                ]);
            }

            CartItem::query()->where('user_id', $user->id)->delete();

            return $order;
        });

        $this->erpOrderSyncService->push($order);

        return redirect()->route('orders.index')->with('status', 'Заказ '.$order->number.' создан и отправлен в историю.');
    }

    /**
     * @return array{0: EloquentCollection<int, CartItem>, 1: array{items_count:int,total_quantity:int,total_amount:float,priced_items_count:int,unpriced_items_count:int}}
     */
    private function resolveCartState(User $user): array
    {
        /** @var EloquentCollection<int, CartItem> $cartItems */
        $cartItems = CartItem::query()
            ->with(['product.category.parent', 'product.prices'])
            ->where('user_id', $user->id)
            ->latest('id')
            ->get();

        $cartItems->each(function (CartItem $cartItem) use ($user): void {
            $price = $cartItem->product?->priceForProfile($user->priceProfile);
            $unitAmount = $price?->min_amount !== null ? (float) $price->min_amount : null;
            $lineAmount = $unitAmount !== null ? round($unitAmount * $cartItem->quantity, 2) : null;

            $cartItem->setAttribute('resolved_price', $price);
            $cartItem->setAttribute('resolved_unit_amount', $unitAmount);
            $cartItem->setAttribute('resolved_line_amount', $lineAmount);
        });

        $summary = [
            'items_count' => $cartItems->count(),
            'total_quantity' => (int) $cartItems->sum('quantity'),
            'total_amount' => round((float) $cartItems->sum(fn (CartItem $item): float => (float) ($item->getAttribute('resolved_line_amount') ?? 0)), 2),
            'priced_items_count' => $cartItems->filter(fn (CartItem $item): bool => $item->getAttribute('resolved_line_amount') !== null)->count(),
            'unpriced_items_count' => $cartItems->filter(fn (CartItem $item): bool => $item->getAttribute('resolved_line_amount') === null)->count(),
        ];

        return [$cartItems, $summary];
    }

    private function ensureOwner(Request $request, CartItem $cartItem): void
    {
        abort_unless($cartItem->user_id === $request->user()->id, 404);
    }
}
