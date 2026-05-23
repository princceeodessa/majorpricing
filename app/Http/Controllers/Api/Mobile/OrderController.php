<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\OrderResource;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\ErpOrderSyncService;
use App\Services\MobileCartService;
use App\Support\MobileApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(
        private readonly MobileCartService $cartService,
        private readonly ErpOrderSyncService $erpOrderSyncService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', 'max:32'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $user = $request->user();
        $orders = Order::query()
            ->with(['user'])
            ->when(
                $user->canManageClients(),
                fn (Builder $query) => $query->whereIn('user_id', $user->visibleClients()->select('id')),
                fn (Builder $query) => $query->where('user_id', $user->id),
            )
            ->when(filled($data['status'] ?? null), fn (Builder $query) => $query->where('status', $data['status']))
            ->latest('placed_at')
            ->latest('id')
            ->paginate((int) ($data['per_page'] ?? 20))
            ->withQueryString();

        return response()->json([
            'data' => OrderResource::collection($orders->getCollection())->resolve(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Request $request, Order $order): OrderResource
    {
        $this->ensureCanViewOrder($request->user(), $order);

        $order->loadMissing(['user', 'items.product']);

        return new OrderResource($order);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'address_id' => [
                'required',
                'integer',
                Rule::exists('user_addresses', 'id')->where(
                    fn ($query) => $query->where('user_id', $request->user()->id)
                ),
            ],
            'payment_method' => ['nullable', Rule::in(array_keys($this->cartService->paymentMethods()))],
            'comment' => ['nullable', 'string', 'max:1500'],
        ]);

        $user = $request->user();
        $paymentMethod = $this->cartService->normalizePaymentMethod($data['payment_method'] ?? null);
        [$cartItems, $summary] = $this->cartService->resolve($user, $paymentMethod);

        if ($cartItems->isEmpty()) {
            return response()->json([
                'message' => 'Корзина пока пуста.',
            ], 422);
        }

        $address = UserAddress::query()
            ->where('user_id', $user->id)
            ->whereKey((int) $data['address_id'])
            ->firstOrFail();

        $order = DB::transaction(function () use ($user, $data, $paymentMethod, $cartItems, $summary, $address): Order {
            $order = Order::query()->create([
                'user_id' => $user->id,
                'status' => 'new',
                'payment_method' => $paymentMethod,
                'integration_status' => 'pending',
                'items_count' => (int) $summary['items_count'],
                'subtotal_amount' => $summary['total_amount'],
                'total_amount' => $summary['total_amount'],
                'price_profile_name' => null,
                'customer_name' => $user->name,
                'customer_company' => $user->company,
                'customer_email' => $user->email,
                'customer_phone' => $user->phone,
                'customer_contact_person' => $user->primaryContactPerson() ?: $user->name,
                'customer_telegram' => $user->primaryMessenger(),
                'customer_delivery_address' => $address->formattedLabel(),
                'comment' => filled($data['comment'] ?? null) ? trim($data['comment']) : null,
                'placed_at' => now(),
            ]);

            $order->update([
                'number' => sprintf('ORD-%s-%05d', now()->format('Ymd'), $order->id),
            ]);

            foreach ($cartItems as $cartItem) {
                $product = $cartItem->product;
                $resolvedPrice = $cartItem->getAttribute('resolved_price');

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product?->id,
                    'product_title' => $product?->publicTitle() ?? 'Товар из каталога',
                    'product_slug' => $product?->slug,
                    'quantity' => $cartItem->quantity,
                    'price_label' => $resolvedPrice?->label ?? 'Цена',
                    'unit_price' => $cartItem->getAttribute('resolved_unit_amount'),
                    'line_total' => $cartItem->getAttribute('resolved_line_amount'),
                    'source_sheet' => $product?->source_sheet,
                    'measurement_value' => $product?->publicUnitLabel(),
                ]);
            }

            CartItem::query()->where('user_id', $user->id)->delete();

            return $order;
        });

        $this->erpOrderSyncService->push($order);
        $order->loadMissing(['items.product']);

        return response()->json([
            'data' => (new OrderResource($order))->resolve(),
            'summary' => [
                'total_amount' => MobileApi::money($order->total_amount),
                'currency' => 'RUB',
            ],
        ], 201);
    }

    private function ensureCanViewOrder(User $user, Order $order): void
    {
        if (! $user->canManageClients()) {
            abort_unless((int) $order->user_id === (int) $user->id, 404);

            return;
        }

        if ($user->isAdmin()) {
            return;
        }

        $order->loadMissing('user');

        abort_unless((int) $order->user?->manager_id === (int) $user->id, 404);
    }
}
