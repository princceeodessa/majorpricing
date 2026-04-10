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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CartController extends Controller
{
    public function __construct(
        private readonly ErpOrderSyncService $erpOrderSyncService,
    ) {
    }

    public function index(Request $request): View
    {
        $request->user()->loadMissing('addresses');

        [$cartItems, $summary] = $this->resolveCartState($request->user());
        $selectedAddress = old('user_address_id')
            ? $request->user()->addresses->firstWhere('id', (int) old('user_address_id'))
            : $request->user()->addresses->firstWhere('is_default', true);

        if (! $selectedAddress) {
            $selectedAddress = $request->user()->addresses->first();
        }

        return view('cart.index', [
            'cartItems' => $cartItems,
            'summary' => $summary,
            'user' => $request->user(),
            'userAddresses' => $request->user()->addresses,
            'selectedAddressId' => $selectedAddress?->id,
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        abort_unless($product->isVisibleInCatalog(), 404);

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

        return $this->cartMutationResponse($request, $product, $cartItem->quantity, 'Товар добавлен в корзину.');
    }

    public function updateProduct(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        abort_unless($product->isVisibleInCatalog(), 404);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $cartItem = CartItem::query()->firstOrNew([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);

        $cartItem->quantity = (int) $validated['quantity'];
        $cartItem->save();

        return $this->cartMutationResponse($request, $product, $cartItem->quantity, 'Количество в корзине обновлено.');
    }

    public function destroyProduct(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        CartItem::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->delete();

        return $this->cartMutationResponse($request, $product, 0, 'Товар удален из корзины.');
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
            'user_address_id' => [
                'required',
                'integer',
                Rule::exists('user_addresses', 'id')->where(
                    fn ($query) => $query->where('user_id', $request->user()->id)
                ),
            ],
            'comment' => ['nullable', 'string', 'max:1500'],
        ], [
            'user_address_id.required' => 'Выберите адрес доставки из профиля.',
            'user_address_id.exists' => 'Выбранный адрес не найден.',
        ]);

        $user = $request->user();
        $user->loadMissing('addresses');

        [$cartItems, $summary] = $this->resolveCartState($user);

        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('status', 'Корзина пока пуста.');
        }

        $selectedAddress = $user->addresses->firstWhere('id', (int) $validated['user_address_id']);

        if (! $selectedAddress) {
            return redirect()->route('cart.index')->withErrors([
                'user_address_id' => 'Выберите корректный адрес доставки.',
            ]);
        }

        $contactSnapshot = [
            'customer_name' => $user->name,
            'customer_company' => $user->company,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'customer_contact_person' => $user->primaryContactPerson() ?: $user->name,
            'customer_telegram' => $user->primaryMessenger(),
            'customer_delivery_address' => $selectedAddress->formattedLabel(),
        ];

        $order = DB::transaction(function () use ($user, $validated, $cartItems, $summary, $contactSnapshot): Order {
            $order = Order::query()->create([
                'user_id' => $user->id,
                'status' => 'new',
                'integration_status' => 'pending',
                'items_count' => (int) $summary['items_count'],
                'subtotal_amount' => $summary['total_amount'],
                'total_amount' => $summary['total_amount'],
                'price_profile_name' => null,
                'customer_name' => $contactSnapshot['customer_name'],
                'customer_company' => $contactSnapshot['customer_company'],
                'customer_email' => $contactSnapshot['customer_email'],
                'customer_phone' => $contactSnapshot['customer_phone'],
                'customer_contact_person' => $contactSnapshot['customer_contact_person'],
                'customer_telegram' => $contactSnapshot['customer_telegram'],
                'customer_delivery_address' => $contactSnapshot['customer_delivery_address'],
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
                    'product_title' => $product?->publicTitle() ?? 'Товар из каталога',
                    'product_slug' => $product?->slug,
                    'quantity' => $cartItem->quantity,
                    'price_label' => $resolvedPrice?->label ?? 'Цена',
                    'unit_price' => $resolvedUnitAmount,
                    'line_total' => $resolvedLineAmount,
                    'source_sheet' => $product?->source_sheet,
                    'measurement_value' => $product?->publicUnitLabel(),
                ]);
            }

            CartItem::query()->where('user_id', $user->id)->delete();

            return $order;
        });

        $this->erpOrderSyncService->push($order);

        return redirect()
            ->route('orders.index')
            ->with('status', 'Заказ '.$order->number.' создан и отправлен в историю.');
    }

    /**
     * @return array{0: EloquentCollection<int, CartItem>, 1: array{items_count:int,total_quantity:int,total_amount:float,priced_items_count:int,unpriced_items_count:int}}
     */
    private function resolveCartState(User $user): array
    {
        /** @var EloquentCollection<int, CartItem> $cartItems */
        $cartItems = CartItem::query()
            ->with(['product.category.parent', 'product.prices'])
            ->whereHas('product', fn ($query) => $query->visibleInCatalog())
            ->where('user_id', $user->id)
            ->latest('id')
            ->get();

        $cartItems->each(function (CartItem $cartItem): void {
            $price = $cartItem->product?->priceForProfile();
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

    private function cartMutationResponse(Request $request, Product $product, int $quantity, string $message): RedirectResponse|JsonResponse
    {
        $cartCount = (int) CartItem::query()
            ->whereHas('product', fn ($query) => $query->visibleInCatalog())
            ->where('user_id', $request->user()->id)
            ->sum('quantity');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'productId' => $product->id,
                'quantity' => $quantity,
                'cartCount' => $cartCount,
                'message' => $message,
            ]);
        }

        return back()->with('status', $message);
    }
}
