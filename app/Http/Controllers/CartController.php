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
    private const PAYMENT_METHOD_BANK_TRANSFER = 'bank_transfer';
    private const PAYMENT_METHOD_CASH = 'cash';

    public function __construct(
        private readonly ErpOrderSyncService $erpOrderSyncService,
    ) {
    }

    public function index(Request $request): View
    {
        $request->user()->loadMissing('addresses');

        $selectedPaymentMethod = $this->normalizePaymentMethod(old('payment_method'));
        [$cartItems, $summary] = $this->resolveCartState($request->user(), $selectedPaymentMethod);

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
            'selectedPaymentMethod' => $selectedPaymentMethod,
            'paymentMethods' => $this->paymentMethods(),
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        abort_unless($product->isVisibleInCatalog(), 404);

        $validated = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $quantity = $product->normalizeCartQuantity((int) ($validated['quantity'] ?? $product->cartQuantityMinimum()));
        $cartItem = CartItem::query()->firstOrNew([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);

        $currentQuantity = $cartItem->exists
            ? $product->normalizeCartQuantity((int) $cartItem->quantity)
            : 0;

        $cartItem->quantity = $product->normalizeCartQuantity($currentQuantity + $quantity);
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

        $cartItem->quantity = $product->normalizeCartQuantity((int) $validated['quantity']);
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

    public function update(Request $request, CartItem $cartItem): RedirectResponse|JsonResponse
    {
        $this->ensureOwner($request, $cartItem);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $cartItem->loadMissing('product');
        $normalizedQuantity = $cartItem->product
            ? $cartItem->product->normalizeCartQuantity((int) $validated['quantity'])
            : (int) $validated['quantity'];

        $cartItem->update([
            'quantity' => $normalizedQuantity,
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            $paymentMethod = $this->normalizePaymentMethod($request->input('payment_method'));
            [$cartItems, $summary] = $this->resolveCartState($request->user(), $paymentMethod);

            $resolvedItem = $cartItems->firstWhere('id', $cartItem->id);
            $cartCount = (int) $cartItems->sum('quantity');

            return response()->json([
                'itemId' => $cartItem->id,
                'quantity' => $cartItem->quantity,
                'cartCount' => $cartCount,
                'summary' => $summary,
                'item' => $resolvedItem ? [
                    'base_unit_amount' => $resolvedItem->getAttribute('base_unit_amount'),
                    'discount_unit_amount' => $resolvedItem->getAttribute('discount_unit_amount'),
                    'base_line_amount' => $resolvedItem->getAttribute('base_line_amount'),
                    'discount_line_amount' => $resolvedItem->getAttribute('discount_line_amount'),
                ] : null,
            ]);
        }

        return back()->with('status', 'Количество в корзине обновлено.');
    }

    public function destroy(Request $request, CartItem $cartItem): RedirectResponse|JsonResponse
    {
        $this->ensureOwner($request, $cartItem);

        $deletedItemId = $cartItem->id;
        $cartItem->delete();

        if ($request->expectsJson() || $request->ajax()) {
            $paymentMethod = $this->normalizePaymentMethod($request->input('payment_method'));
            [$cartItems, $summary] = $this->resolveCartState($request->user(), $paymentMethod);
            $cartCount = (int) $cartItems->sum('quantity');

            return response()->json([
                'itemId' => $deletedItemId,
                'removed' => true,
                'cartCount' => $cartCount,
                'summary' => $summary,
            ]);
        }

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
            'payment_method' => ['nullable', Rule::in(array_keys($this->paymentMethods()))],
            'comment' => ['nullable', 'string', 'max:1500'],
        ], [
            'user_address_id.required' => 'Выберите адрес доставки из профиля.',
            'user_address_id.exists' => 'Выбранный адрес не найден.',
        ]);

        $user = $request->user();
        $user->loadMissing('addresses');

        $paymentMethod = $this->normalizePaymentMethod($validated['payment_method'] ?? null);
        [$cartItems, $summary] = $this->resolveCartState($user, $paymentMethod);

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

        $order = DB::transaction(function () use ($user, $validated, $cartItems, $summary, $contactSnapshot, $paymentMethod): Order {
            $order = Order::query()->create([
                'user_id' => $user->id,
                'status' => 'new',
                'payment_method' => $paymentMethod,
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
     * @return array{0: EloquentCollection<int, CartItem>, 1: array{items_count:int,total_quantity:int,total_amount:float,base_total_amount:float,discount_total_amount:float,payment_method:string,priced_items_count:int,unpriced_items_count:int}}
     */
    private function resolveCartState(User $user, string $paymentMethod = self::PAYMENT_METHOD_BANK_TRANSFER): array
    {
        /** @var EloquentCollection<int, CartItem> $cartItems */
        $cartItems = CartItem::query()
            ->with(['product.category.parent', 'product.prices'])
            ->whereHas('product', fn ($query) => $query->visibleInCatalog())
            ->where('user_id', $user->id)
            ->latest('id')
            ->get();

        $cartItems->each(function (CartItem $cartItem) use ($paymentMethod): void {
            $product = $cartItem->product;

            if ($product) {
                $normalizedQuantity = $product->normalizeCartQuantity((int) $cartItem->quantity);

                if ($normalizedQuantity !== (int) $cartItem->quantity) {
                    $cartItem->quantity = $normalizedQuantity;
                    $cartItem->save();
                }
            }

            $discountPrice = $product?->publicPrice();
            $basePrice = $product?->priceForPaymentMethod(self::PAYMENT_METHOD_BANK_TRANSFER);
            $resolvedPrice = $product?->priceForPaymentMethod($paymentMethod);

            $discountUnitAmount = $discountPrice?->min_amount !== null ? (float) $discountPrice->min_amount : null;
            $baseUnitAmount = $basePrice?->min_amount !== null ? (float) $basePrice->min_amount : null;
            $resolvedUnitAmount = $resolvedPrice?->min_amount !== null ? (float) $resolvedPrice->min_amount : null;

            $cartItem->setAttribute('discount_price', $discountPrice);
            $cartItem->setAttribute('base_price', $basePrice);
            $cartItem->setAttribute('discount_unit_amount', $discountUnitAmount);
            $cartItem->setAttribute('base_unit_amount', $baseUnitAmount);
            $cartItem->setAttribute('discount_line_amount', $discountUnitAmount !== null ? round($discountUnitAmount * $cartItem->quantity, 2) : null);
            $cartItem->setAttribute('base_line_amount', $baseUnitAmount !== null ? round($baseUnitAmount * $cartItem->quantity, 2) : null);
            $cartItem->setAttribute('resolved_price', $resolvedPrice);
            $cartItem->setAttribute('resolved_unit_amount', $resolvedUnitAmount);
            $cartItem->setAttribute('resolved_line_amount', $resolvedUnitAmount !== null ? round($resolvedUnitAmount * $cartItem->quantity, 2) : null);
        });

        $summary = [
            'items_count' => $cartItems->count(),
            'total_quantity' => (int) $cartItems->sum('quantity'),
            'total_amount' => round((float) $cartItems->sum(fn (CartItem $item): float => (float) ($item->getAttribute('resolved_line_amount') ?? 0)), 2),
            'base_total_amount' => round((float) $cartItems->sum(fn (CartItem $item): float => (float) ($item->getAttribute('base_line_amount') ?? 0)), 2),
            'discount_total_amount' => round((float) $cartItems->sum(fn (CartItem $item): float => (float) ($item->getAttribute('discount_line_amount') ?? 0)), 2),
            'payment_method' => $paymentMethod,
            'priced_items_count' => $cartItems->filter(fn (CartItem $item): bool => $item->getAttribute('resolved_line_amount') !== null)->count(),
            'unpriced_items_count' => $cartItems->filter(fn (CartItem $item): bool => $item->getAttribute('resolved_line_amount') === null)->count(),
        ];

        return [$cartItems, $summary];
    }

    /**
     * @return array<string, string>
     */
    private function paymentMethods(): array
    {
        return [
            self::PAYMENT_METHOD_BANK_TRANSFER => 'Безналичный расчет',
            self::PAYMENT_METHOD_CASH => 'Наличный расчет',
        ];
    }

    private function normalizePaymentMethod(?string $paymentMethod): string
    {
        return array_key_exists((string) $paymentMethod, $this->paymentMethods())
            ? (string) $paymentMethod
            : self::PAYMENT_METHOD_BANK_TRANSFER;
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


