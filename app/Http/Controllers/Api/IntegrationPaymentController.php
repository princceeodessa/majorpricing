<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\NormalizesIntegrationData;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\OrderStatuses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationPaymentController extends Controller
{
    use NormalizesIntegrationData;

    public function show(Request $request, Order $order): JsonResponse
    {
        $order->loadMissing(['user', 'items.product']);

        return response()->json([
            'data' => [
                'order' => $this->transformOrder($order),
                'payment' => [
                    'currency' => 'RUB',
                    'amount' => $order->total_amount !== null ? (float) $order->total_amount : null,
                    'description' => sprintf('Оплата заказа %s', $order->number ?? $order->id),
                    'webhook_url' => route('api.integrations.payments.webhook'),
                    'success_url' => route('orders.index'),
                    'cancel_url' => route('cart.index'),
                ],
            ],
            'integration' => [
                'service' => $request->attributes->get('integration_service'),
                'generated_at' => now()->toAtomString(),
            ],
        ]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_number' => ['required', 'string', 'exists:orders,number'],
            'payment_status' => ['required', 'string', 'max:80'],
            'status' => ['nullable', 'string', 'max:80'],
            'payment_method' => ['nullable', 'string', 'max:120'],
            'payment_reference' => ['nullable', 'string', 'max:190'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_at' => ['nullable', 'date'],
            'payment_payload' => ['nullable', 'array'],
            'integration_reference' => ['nullable', 'string', 'max:190'],
        ]);

        $order = Order::query()
            ->with(['user', 'items.product'])
            ->where('number', $validated['order_number'])
            ->firstOrFail();

        $order->fill([
            'payment_status' => $validated['payment_status'],
            'status' => $validated['status'] ?? $this->resolveOrderStatus($validated['payment_status'], $order->status),
            'payment_method' => $validated['payment_method'] ?? $order->payment_method,
            'payment_reference' => $validated['payment_reference'] ?? $order->payment_reference,
            'paid_amount' => $validated['paid_amount'] ?? $order->paid_amount,
            'paid_at' => $validated['paid_at'] ?? ($validated['payment_status'] === 'paid' ? now() : $order->paid_at),
            'payment_payload' => $validated['payment_payload'] ?? $order->payment_payload,
            'integration_reference' => $validated['integration_reference'] ?? $order->integration_reference,
        ]);
        $order->save();

        return response()->json([
            'data' => $this->transformOrder($order->fresh(['user', 'items.product'])),
            'integration' => [
                'service' => $request->attributes->get('integration_service'),
                'updated_at' => now()->toAtomString(),
            ],
        ]);
    }

    private function resolveOrderStatus(string $paymentStatus, string $currentStatus): string
    {
        return match ($paymentStatus) {
            'paid' => $currentStatus === OrderStatuses::NEW ? OrderStatuses::ACCEPTED : $currentStatus,
            'failed' => $currentStatus,
            'canceled' => OrderStatuses::CANCELED,
            default => $currentStatus,
        };
    }
}
